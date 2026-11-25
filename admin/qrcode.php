<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once dirname(__DIR__) . '/includes/orders.php';

$input = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$type = (string) ($input['type'] ?? 'site');
if (!in_array($type, ['site', 'product', 'direct'], true)) $type = 'site';
$productId = (int) ($input['product_id'] ?? 0);
$products = $pdo->query('SELECT p.id,p.name,p.price,p.is_gift_box,c.slug category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 ORDER BY p.name')->fetchAll();
$campaigns = $pdo->query('SELECT c.token,c.name,c.destination_type,p.name product_name,c.created_at FROM qr_campaigns c LEFT JOIN products p ON p.id=c.product_id ORDER BY c.created_at DESC LIMIT 25')->fetchAll();
$product = null;
foreach ($products as $candidate) {
    if ((int) $candidate['id'] === $productId) {
        $product = $candidate;
        break;
    }
}
$productIsGiftBox = $product && (!empty($product['is_gift_box']) || ($product['category_slug'] ?? '') === 'coffrets');
$sizes = [];
$colors = [];
$targetUrl = null;
$error = '';
$savedCampaign = null;
$campaignToken = (string) ($_GET['campaign'] ?? '');
if ($campaignToken !== '') {
    $campaignStmt = $pdo->prepare('SELECT c.*, p.name product_name FROM qr_campaigns c LEFT JOIN products p ON p.id=c.product_id WHERE c.token=?');
    $campaignStmt->execute([$campaignToken]);
    $savedCampaign = $campaignStmt->fetch() ?: null;
    if (!$savedCampaign) $error = 'Cette campagne QR n’existe plus.';
    else {
        $type = (string) $savedCampaign['destination_type'];
        $productId = (int) ($savedCampaign['product_id'] ?? 0);
        $targetUrl = site_url((string) $savedCampaign['target_path']);
    }
}

if (!$savedCampaign) {
    if ($type === 'site') {
        $targetUrl = site_url();
    } elseif (!$product) {
        if ($productId) $error = 'Ce produit n’est pas disponible ou n’existe plus.';
    } else {
        $targetUrl = site_url('produit.php?id=' . (int) $product['id']);
        if ($type === 'direct' && !$productIsGiftBox) {
            $targetUrl = null;
            $variantsStmt = $pdo->prepare('SELECT size,color,stock FROM variants WHERE product_id=? AND stock>0 ORDER BY id');
            $variantsStmt->execute([$productId]);
            $variants = $variantsStmt->fetchAll();
            foreach ($variants as $variant) {
                $sizes[$variant['size']] = $variant['size'];
                $colors[$variant['color']] = $variant['color'];
            }
            $size = trim((string) ($input['size'] ?? ''));
            $color = trim((string) ($input['color'] ?? ''));
            $quantity = max(1, min(99, (int) ($input['quantity'] ?? 1)));
            $selectedVariant = null;
            foreach ($variants as $variant) {
                if ($variant['size'] === $size && $variant['color'] === $color) {
                    $selectedVariant = $variant;
                    break;
                }
            }
            if ($size !== '' || $color !== '') {
                if (!$selectedVariant) $error = 'Cette combinaison taille/couleur n’est pas disponible en stock.';
                elseif ($quantity > (int) $selectedVariant['stock']) $error = 'La quantité choisie dépasse le stock disponible pour cette variante.';
                else {
                    $targetUrl = site_url('acheter.php?' . http_build_query([
                        'id' => $productId,
                        'size' => $size,
                        'color' => $color,
                        'quantity' => $quantity,
                    ]));
                }
            } elseif (!$variants) {
                $error = 'Aucune variante n’est actuellement en stock pour ce produit.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    if (($_POST['form_action'] ?? '') !== 'create_campaign') {
        http_response_code(400);
        exit('Action invalide.');
    }
    $campaignName = mb_substr(trim((string) ($_POST['campaign_name'] ?? '')), 0, 120);
    if ($campaignName === '') $error = 'Donnez un nom à cette campagne QR.';
    if (!$targetUrl || $error !== '') $error = $error ?: 'Choisissez une destination valide avant de créer le lien.';
    if ($error === '') {
        $sitePath = (string) parse_url($targetUrl, PHP_URL_PATH);
        $basePath = trim(BASE_URL, '/');
        $pathWithoutBase = ltrim($sitePath, '/');
        if ($basePath !== '' && !str_starts_with($pathWithoutBase, $basePath . '/')) {
            $error = 'La destination ne correspond pas au chemin du site.';
        } else {
            if ($basePath !== '') $pathWithoutBase = substr($pathWithoutBase, strlen($basePath) + 1);
            $targetPath = $pathWithoutBase !== '' ? $pathWithoutBase : 'index.php';
            $query = parse_url($targetUrl, PHP_URL_QUERY);
            if ($query) $targetPath .= '?' . $query;
            $token = bin2hex(random_bytes(16));
            $campaignInsert = $pdo->prepare('INSERT INTO qr_campaigns (token,name,destination_type,product_id,target_path) VALUES (?,?,?,?,?)');
            $campaignInsert->execute([$token, $campaignName, $type, $type === 'site' ? null : $productId, $targetPath]);
            redirect('qrcode.php?campaign=' . $token);
        }
    }
}

$trackedUrl = $savedCampaign ? site_url('qr-go.php?c=' . rawurlencode((string) $savedCampaign['token'])) : null;
$qrImageUrl = $trackedUrl
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&format=png&margin=12&data=' . rawurlencode($trackedUrl)
    : null;
$pageTitle = 'Générateur de QR codes';
$pageSubtitle = 'Créez des liens et QR codes traçables';
require __DIR__ . '/layout.php';
?>
<div class="grid xl:grid-cols-[1fr_380px] gap-5 items-start">
  <section class="bg-white rounded-2xl border border-line p-5 sm:p-6">
    <h2 class="font-display text-2xl">Choisir une destination</h2>
    <?php if (!$savedCampaign): ?>
    <form method="get" class="mt-5 space-y-4">
      <label class="block">
        <span class="label">Destination</span>
        <select name="type" class="field">
          <option value="site" <?= $type === 'site' ? 'selected' : '' ?>>Page d’accueil du site</option>
          <option value="product" <?= $type === 'product' ? 'selected' : '' ?>>Fiche d’un produit</option>
          <option value="direct" <?= $type === 'direct' ? 'selected' : '' ?>>Commande directe d’une variante</option>
        </select>
      </label>
      <?php if ($type !== 'site'): ?>
        <label class="block">
          <span class="label">Produit</span>
          <select name="product_id" required class="field">
            <option value="">Choisir un produit</option>
            <?php foreach ($products as $item): ?>
              <option value="<?= (int) $item['id'] ?>" <?= $productId === (int) $item['id'] ? 'selected' : '' ?>><?= e($item['name']) ?> — <?= e(money((int) $item['price'])) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <?php if ($type === 'direct' && $product && !$productIsGiftBox && !$savedCampaign): ?>
        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block">
            <span class="label">Taille</span>
            <select name="size" required class="field">
              <option value="">Choisir une taille</option>
              <?php foreach ($sizes as $availableSize): ?>
                <option value="<?= e($availableSize) ?>" <?= (($input['size'] ?? '') === $availableSize) ? 'selected' : '' ?>><?= e($availableSize) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block">
            <span class="label">Couleur</span>
            <select name="color" required class="field">
              <option value="">Choisir une couleur</option>
              <?php foreach ($colors as $availableColor): ?>
                <option value="<?= e($availableColor) ?>" <?= (($input['color'] ?? '') === $availableColor) ? 'selected' : '' ?>><?= e($availableColor) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <label class="block max-w-[180px]">
          <span class="label">Quantité</span>
          <input type="number" name="quantity" min="1" max="99" value="<?= max(1, min(99, (int) ($input['quantity'] ?? 1))) ?>" class="field">
        </label>
      <?php endif; ?>
      <?php if ($type === 'direct' && $productIsGiftBox && !$savedCampaign): ?>
        <p class="text-sm text-muted">Le client choisira les variantes du coffret avant de l’ajouter à sa commande.</p>
      <?php endif; ?>
      <button class="btn btn-primary"><?= icon('package', 'w-4 h-4') ?> Préparer le lien</button>
    </form>
    <?php else: ?>
      <a href="qrcode.php" class="btn btn-outline mt-5">Créer une autre campagne QR</a>
    <?php endif; ?>

    <?php if ($error): ?>
      <p class="mt-5 rounded-xl border border-terra/30 bg-terra/5 p-4 text-sm text-terra" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <?php if ($targetUrl && !$savedCampaign && $error === ''): ?>
      <form method="post" class="mt-6 pt-5 border-t border-line space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="create_campaign">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="hidden" name="product_id" value="<?= $productId ?>">
        <?php if ($type === 'direct'): ?>
          <input type="hidden" name="size" value="<?= e((string) ($input['size'] ?? '')) ?>">
          <input type="hidden" name="color" value="<?= e((string) ($input['color'] ?? '')) ?>">
          <input type="hidden" name="quantity" value="<?= max(1, min(99, (int) ($input['quantity'] ?? 1))) ?>">
        <?php endif; ?>
        <label class="block">
          <span class="label">Nom de la campagne / emplacement</span>
          <input name="campaign_name" maxlength="120" required class="field" placeholder="Ex. Statut WhatsApp, Flyer boutique, Instagram">
        </label>
        <p class="text-xs text-muted">Chaque nom crée un lien unique : les scans et commandes issus de ce support seront comptés séparément.</p>
        <button class="btn btn-primary"><?= icon('qr', 'w-4 h-4') ?> Créer le lien suivi et le QR code</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="bg-white rounded-2xl border border-line p-5 sm:p-6 text-center">
    <h2 class="font-display text-2xl">Lien et QR code</h2>
    <?php if ($savedCampaign && $qrImageUrl): ?>
      <p class="mt-4 text-sm font-semibold"><?= e($savedCampaign['name']) ?><?= $savedCampaign['product_name'] ? ' · ' . e($savedCampaign['product_name']) : '' ?></p>
      <img src="<?= e($qrImageUrl) ?>" alt="QR code de la campagne <?= e($savedCampaign['name']) ?>" class="mt-4 mx-auto w-full max-w-[300px] aspect-square rounded-lg border border-line" loading="eager">
      <p class="mt-4 text-xs font-semibold text-muted">LIEN À PARTAGER</p>
      <a href="<?= e($trackedUrl) ?>" target="_blank" rel="noopener" class="mt-1 block text-xs text-forest underline break-all"><?= e($trackedUrl) ?></a>
      <p class="mt-4 text-xs font-semibold text-muted">DESTINATION</p>
      <a href="<?= e($targetUrl) ?>" target="_blank" rel="noopener" class="mt-1 block text-xs text-muted underline break-all"><?= e($targetUrl) ?></a>
      <div class="mt-5 flex flex-wrap justify-center gap-2">
        <button type="button" class="btn btn-outline" data-copy-qr-link="<?= e($trackedUrl) ?>" onclick="navigator.clipboard.writeText(this.dataset.copyQrLink).then(()=>{this.textContent='Lien copié'}).catch(()=>alert('Copiez le lien affiché ci-dessus.'))">Copier le lien</button>
        <a href="<?= e($qrImageUrl) ?>" download="qr-code.png" target="_blank" rel="noopener" class="btn btn-outline"><?= icon('external', 'w-4 h-4') ?> Télécharger le QR</a>
        <button type="button" class="btn btn-primary" onclick="window.print()"><?= icon('printer', 'w-4 h-4') ?> Imprimer</button>
      </div>
    <?php else: ?>
      <div class="mt-5 rounded-xl bg-cream p-8 text-sm text-muted">Choisissez une destination, nommez le support, puis créez son lien suivi et son QR code. Une commande directe nécessite aussi le choix d’une taille et d’une couleur.</div>
    <?php endif; ?>
    <p class="mt-4 text-xs text-muted">Le QR visuel est généré en ligne à partir du lien de suivi public.</p>
  </section>
</div>
<?php if ($campaigns): ?>
  <div class="mt-5">
    <?= card_open('Campagnes QR enregistrées', 'qr') ?>
      <div class="overflow-x-auto -mx-5 sm:-mx-6">
        <table class="tbl w-full min-w-[620px]">
          <thead><tr><th class="!pl-5 sm:!pl-6">Nom</th><th>Destination</th><th>Créée le</th><th class="!pr-5 sm:!pr-6 text-right">Lien et QR</th></tr></thead>
          <tbody>
            <?php foreach ($campaigns as $item): ?>
              <tr>
                <td class="!pl-5 sm:!pl-6 font-semibold"><?= e($item['name']) ?></td>
                <td><?= e(['site'=>'Site','product'=>'Fiche produit','direct'=>'Commande directe'][$item['destination_type']] ?? $item['destination_type']) ?><?= $item['product_name'] ? ' · ' . e($item['product_name']) : '' ?></td>
                <td class="text-muted whitespace-nowrap"><?= e(date('d/m/Y', strtotime((string) $item['created_at']))) ?></td>
                <td class="!pr-5 sm:!pr-6 text-right"><a href="qrcode.php?campaign=<?= e(rawurlencode($item['token'])) ?>" class="btn btn-outline btn-sm">Ouvrir</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/layout-end.php'; ?>
