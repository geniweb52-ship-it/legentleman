<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

/**
 * Enregistre une photo envoyée : redressée, redimensionnée (1600 px max), convertie en WebP,
 * avec ses déclinaisons 800 et 400 px. Renvoie son URL publique (ou null).
 */
function store_upload(string $field, array &$errors): ?string
{
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > 15 * 1024 * 1024) { $errors[] = 'Image trop lourde (15 Mo maximum).'; return null; }
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Échec de l\'envoi de l\'image.'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { $errors[] = 'Format accepté : JPG, PNG ou WebP.'; return null; }
    $dir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8));
    // L'image est entièrement ré-encodée : aucun contenu caché du fichier d'origine n'est conservé.
    if (!image_optimize($file['tmp_name'], $dir . '/' . $name)) { $errors[] = 'Image illisible ou corrompue.'; return null; }
    return url('assets/uploads/' . $name . '.webp');
}

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $action = (string) ($_POST['form_action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'toggle') {
        $pdo->prepare('UPDATE products SET active = 1 - active WHERE id=?')->execute([$id]);
        flash('Visibilité du produit mise à jour.');
        redirect('produits.php?' . http_build_query(array_intersect_key($_GET, array_flip(['q', 'cat', 'etat']))));
    }
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        flash('Produit supprimé.');
        redirect('produits.php');
    }
    if ($action === 'save') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $price = (int) ($_POST['price'] ?? 0);
        $old = (int) ($_POST['old_price'] ?? 0);
        $giftCategoryId = (int) $pdo->query("SELECT id FROM categories WHERE slug='coffrets'")->fetchColumn();
        $isGiftBox = !empty($_POST['is_gift_box']) || ((int) ($_POST['category_id'] ?? 0) === $giftCategoryId && $giftCategoryId > 0);
        if ($name === '' || $price <= 0) $errors[] = 'Le nom et un prix supérieur à 0 sont requis.';
        if ($old && $old <= $price) $errors[] = 'L\'ancien prix doit être supérieur au prix actuel (sinon laissez-le vide).';
        // Délai de confection (habits sur commande) : vide ou 0 = disponible immédiatement.
        $readyDaysRaw = trim((string) ($_POST['ready_days'] ?? ''));
        $readyDays = $readyDaysRaw === '' ? 0 : (int) $readyDaysRaw;
        if ($readyDaysRaw !== '' && (!ctype_digit($readyDaysRaw) || $readyDays > 365)) {
            $errors[] = 'Le délai de confection doit être un nombre de jours entre 1 et 365 (laissez vide si l\'article est disponible tout de suite).';
        }
        $image = store_upload('image', $errors);
        $imageBack = store_upload('image_back', $errors);
        $data = [
            (int) ($_POST['category_id'] ?? 0) ?: null, $name, trim((string) ($_POST['description'] ?? '')), trim((string) ($_POST['brand'] ?? '')),
            $price, $old ?: null, $readyDays > 0 ? $readyDays : null, (int) $isGiftBox, (int) !empty($_POST['active']),
        ];
        if (!$errors) {
            if ($id) {
                $pdo->prepare('UPDATE products SET category_id=?, name=?, description=?, brand=?, price=?, old_price=?, ready_days=?, is_gift_box=?, active=? WHERE id=?')->execute([...$data, $id]);
                if ($isGiftBox) {
                    $hasVariant = $pdo->prepare("SELECT COUNT(*) FROM variants WHERE product_id=? AND size='Unique' AND color='Coffret'");
                    $hasVariant->execute([$id]);
                    if (!(int) $hasVariant->fetchColumn()) $pdo->prepare("INSERT INTO variants (product_id,size,color,stock) VALUES (?,'Unique','Coffret',0)")->execute([$id]);
                }
                if ($image) $pdo->prepare('UPDATE products SET image=? WHERE id=?')->execute([$image, $id]);
                if ($imageBack) $pdo->prepare('UPDATE products SET image_back=? WHERE id=?')->execute([$imageBack, $id]);
                if (!empty($_POST['remove_back'])) $pdo->prepare('UPDATE products SET image_back=NULL WHERE id=?')->execute([$id]);
                flash('Produit enregistré.');
                redirect('produits.php?edit=' . $id);
            }
            $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name), '-'));
            $pdo->prepare('INSERT INTO products (category_id,name,description,brand,price,old_price,ready_days,is_gift_box,active,slug,image,image_back) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([...$data, $base . '-' . bin2hex(random_bytes(3)), $image, $imageBack]);
            $newId = (int) $pdo->lastInsertId();
            if ($isGiftBox) {
                $pdo->prepare("INSERT INTO variants (product_id,size,color,stock) VALUES (?,'Unique','Coffret',0)")->execute([$newId]);
                flash('Coffret créé. Choisissez maintenant les produits qui le composent.');
                redirect('coffrets.php?edit=' . $newId);
            }
            flash('Produit créé. Ajoutez maintenant ses tailles et couleurs.');
            redirect('stock.php?produit=' . $newId);
        }
        $editId = $id;
    }
}

$categories = $pdo->query("SELECT id, name FROM categories WHERE slug<>'tous' ORDER BY sort_order, name")->fetchAll();
$edit = null;
if ($editId) {
    $st = $pdo->prepare('SELECT p.*, (SELECT COALESCE(SUM(stock),0) FROM variants v WHERE v.product_id=p.id) stock_total, (SELECT COUNT(*) FROM variants v WHERE v.product_id=p.id) variant_count FROM products p WHERE id=?');
    $st->execute([$editId]);
    $edit = $st->fetch() ?: null;
}

$q = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['cat'] ?? 0);
$etat = (string) ($_GET['etat'] ?? '');
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.brand LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($cat) { $where[] = 'p.category_id=?'; $params[] = $cat; }
if ($etat === 'actif') $where[] = 'p.active=1';
if ($etat === 'masque') $where[] = 'p.active=0';
if ($etat === 'rupture') $where[] = '(SELECT COALESCE(SUM(stock),0) FROM variants v WHERE v.product_id=p.id)=0';
$st = $pdo->prepare('SELECT p.*, c.name category_name, (SELECT COALESCE(SUM(stock),0) FROM variants v WHERE v.product_id=p.id) stock_total FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC, p.id DESC');
$st->execute($params);
$products = $st->fetchAll();
$counts = $pdo->query('SELECT COUNT(*) total, SUM(active) actifs FROM products')->fetch();

$pageTitle = 'Produits';
$pageSubtitle = (int) $counts['total'] . ' produits · ' . (int) $counts['actifs'] . ' en ligne';
$pageActions = '<a href="produits.php?nouveau=1#nouveau" class="btn btn-primary">' . icon('plus', 'w-4 h-4') . ' Nouveau produit</a>';
require __DIR__ . '/layout.php';
$isNew = isset($_GET['nouveau']);
$newGift = ($_GET['nouveau'] ?? '') === 'coffret';
$giftCat = $newGift ? (int) $pdo->query("SELECT id FROM categories WHERE slug='coffrets'")->fetchColumn() : 0;
$v = $edit ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST + ['image' => null, 'image_back' => null] : ['active' => 1, 'is_gift_box' => $newGift ? 1 : 0, 'category_id' => $giftCat]);
?>
<?php if ($errors): ?><div class="mb-5 rounded-xl border border-terra/30 bg-terra/5 p-4 text-sm text-terra"><?php foreach ($errors as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div><?php endif; ?>

<details id="nouveau" class="mb-6 bg-white rounded-2xl border border-line scroll-mt-24" <?= ($edit || $errors || $isNew) ? 'open' : '' ?>>
  <summary class="flex items-center justify-between gap-3 p-5 sm:p-6">
    <span class="font-display text-xl flex items-center gap-2.5"><span class="text-forest"><?= icon($edit ? 'edit' : 'plus', 'w-5 h-5') ?></span><?= $edit ? 'Modifier « ' . e($edit['name']) . ' »' : 'Ajouter un produit' ?></span>
    <span class="flex items-center gap-2">
      <?php if ($edit): ?><a href="<?= e(url('produit.php?id=' . (int) $edit['id'])) ?>" target="_blank" class="btn btn-outline btn-sm"><?= icon('eye', 'w-4 h-4') ?> Voir</a><?= share_button($edit, 'btn btn-outline btn-sm', true) ?><a href="produits.php" class="btn btn-outline btn-sm">Fermer</a><?php else: ?><?= icon('chevron-down', 'w-5 h-5') ?><?php endif; ?>
    </span>
  </summary>
  <form method="post" enctype="multipart/form-data" class="px-5 sm:px-6 pb-6 grid lg:grid-cols-[1fr_340px] gap-6">
    <?= csrf_field() ?><input type="hidden" name="form_action" value="save"><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="grid sm:grid-cols-2 gap-4 content-start">
      <label class="sm:col-span-2"><span class="label">Nom du produit</span><input name="name" required value="<?= e($v['name'] ?? '') ?>" class="field" placeholder="Ex. Polo classique"></label>
      <label><span class="label">Catégorie</span><select name="category_id" class="field"><option value="0">— Aucune —</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) ($v['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
      <label><span class="label">Marque</span><input name="brand" value="<?= e($v['brand'] ?? '') ?>" class="field" placeholder="LE GENTLEMAN"></label>
      <label><span class="label">Prix (FCFA)</span><input type="number" name="price" min="1" required value="<?= e($v['price'] ?? '') ?>" class="field" placeholder="18500"></label>
      <label><span class="label">Ancien prix <span class="font-normal text-muted">(promo, facultatif)</span></span><input type="number" name="old_price" min="0" value="<?= e($v['old_price'] ?? '') ?>" class="field" placeholder="Vide = pas de promotion"></label>
      <label class="sm:col-span-2"><span class="label">Délai de confection <span class="font-normal text-muted">(article sur commande, ex. habit traditionnel — facultatif)</span></span>
        <span class="flex items-center gap-2 sm:max-w-[240px]"><input type="number" name="ready_days" min="1" max="365" value="<?= e(product_ready_days($v['ready_days'] ?? null) ?? '') ?>" class="field" placeholder="Ex. 15"><span class="text-sm text-muted whitespace-nowrap">jours</span></span>
        <span class="text-xs text-muted mt-1 block">Vide = disponible immédiatement. Avec un délai, le client commande tout de suite et voit « Sur commande · prêt en 15 jours » ; la date exacte est calculée à partir du jour de sa commande.</span>
      </label>
      <label class="sm:col-span-2"><span class="label">Description</span><textarea name="description" rows="6" class="field" placeholder="Polo en coton piqué, confortable et élégant.&#10;- Matière : 100 % coton&#10;- Coupe : ajustée"><?= e($v['description'] ?? '') ?></textarea><span class="text-xs text-muted mt-1 block">Astuce : une ligne commençant par « - » s'affiche en liste à puces.</span></label>
      <div class="sm:col-span-2 flex flex-wrap gap-6 text-sm">
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="active" value="1" class="w-4 h-4 accent-[#2E4A36]" <?= !empty($v['active']) ? 'checked' : '' ?>> Visible sur la boutique</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_gift_box" value="1" class="w-4 h-4 accent-[#2E4A36]" <?= !empty($v['is_gift_box']) ? 'checked' : '' ?>> C'est un coffret cadeau</label>
      </div>
    </div>
    <div class="grid grid-cols-2 gap-3 content-start">
      <?php foreach ([['image', 'Photo principale', 'pv1'], ['image_back', 'Photo 2 (dos, porté)', 'pv2']] as [$f, $l, $pid]): ?>
        <label class="block min-w-0">
          <span class="label"><?= e($l) ?></span>
          <span class="relative block aspect-[4/5] rounded-xl border-2 border-dashed border-line bg-cream/60 overflow-hidden cursor-pointer hover:border-forest transition">
            <img id="<?= $pid ?>" src="<?= e(image_url($v[$f] ?? '', 400)) ?>" alt="" class="absolute inset-0 w-full h-full object-cover <?= empty($v[$f]) ? 'hidden' : '' ?>">
            <span class="absolute inset-0 flex flex-col items-center justify-center text-muted text-xs gap-2 <?= !empty($v[$f]) ? 'opacity-0 hover:opacity-100 bg-ink/40 text-white' : '' ?> transition"><?= icon('upload', 'w-6 h-6') ?> <?= !empty($v[$f]) ? 'Remplacer' : 'Choisir une image' ?></span>
            <input type="file" name="<?= $f ?>" accept="image/jpeg,image/png,image/webp" class="sr-only" data-preview="<?= $pid ?>">
          </span>
        </label>
      <?php endforeach; ?>
      <?php if (!empty($edit['image_back'])): ?><label class="col-span-2 inline-flex items-center gap-2 text-xs text-muted"><input type="checkbox" name="remove_back" value="1"> Retirer la photo 2</label><?php endif; ?>
      <p class="col-span-2 text-xs text-muted">Format vertical conseillé. JPG, PNG ou WebP jusqu’à 15 Mo : la photo est automatiquement redressée, allégée et convertie en WebP.</p>
    </div>
    <div class="lg:col-span-2 flex flex-wrap items-center gap-3 pt-2 border-t border-line">
      <button class="btn btn-primary mt-4"><?= icon('check', 'w-4 h-4') ?> <?= $edit ? 'Enregistrer' : 'Créer le produit' ?></button>
      <?php if ($edit): ?>
        <a href="stock.php?produit=<?= (int) $edit['id'] ?>" class="btn btn-outline mt-4"><?= icon('layers', 'w-4 h-4') ?> Tailles & stock (<?= (int) $edit['variant_count'] ?> déclinaisons, <?= (int) $edit['stock_total'] ?> pièces)</a>
      <?php endif; ?>
    </div>
  </form>
  <?php if ($edit): ?>
    <form method="post" class="px-5 sm:px-6 pb-6 -mt-2"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><button class="text-xs text-terra underline underline-offset-2" data-confirm="Supprimer définitivement ce produit et son stock ? Les commandes passées restent conservées.">Supprimer ce produit</button></form>
  <?php endif; ?>
</details>

<?= card_open() ?>
  <form method="get" class="flex flex-wrap gap-3 mb-5">
    <div class="relative flex-1 min-w-[200px]"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'w-4 h-4') ?></span><input name="q" value="<?= e($q) ?>" placeholder="Nom ou marque…" class="field !pl-9"></div>
    <select name="cat" class="field !w-auto" data-autosubmit><option value="0">Toutes catégories</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select name="etat" class="field !w-auto" data-autosubmit><option value="">Tous les états</option><option value="actif" <?= $etat === 'actif' ? 'selected' : '' ?>>En ligne</option><option value="masque" <?= $etat === 'masque' ? 'selected' : '' ?>>Masqués</option><option value="rupture" <?= $etat === 'rupture' ? 'selected' : '' ?>>En rupture</option></select>
    <button class="btn btn-outline">Filtrer</button>
  </form>
  <?php if (!$products): ?>
    <?= empty_state('Aucun produit ne correspond.', 'package') ?>
  <?php else: ?>
    <div class="overflow-x-auto -mx-5 sm:-mx-6">
      <table class="tbl w-full min-w-[760px]">
        <thead><tr><th class="!pl-5 sm:!pl-6">Produit</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Statut</th><th class="!pr-5 sm:!pr-6 text-right">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($products as $p): $stock = (int) $p['stock_total']; ?>
            <tr>
              <td class="!pl-5 sm:!pl-6"><a href="produits.php?edit=<?= (int) $p['id'] ?>" class="flex items-center gap-3 group"><?= product_thumb($p['image'], 'w-11 h-[52px]') ?><span class="min-w-0"><span class="block font-semibold group-hover:text-forest truncate max-w-[260px]"><?= e($p['name']) ?></span><span class="block text-xs text-muted"><?= e($p['brand'] ?: '—') ?><?= $p['is_gift_box'] ? ' · Coffret' : '' ?><?= ($pReady = product_ready_days($p['ready_days'] ?? null)) ? ' · <span class="text-forest font-semibold">' . e(ready_label($pReady)) . '</span>' : '' ?></span></span></a></td>
              <td class="text-muted whitespace-nowrap"><?= e($p['category_name'] ?? '—') ?></td>
              <td class="whitespace-nowrap tabular-nums"><span class="font-semibold"><?= fcfa((int) $p['price']) ?></span><?php if ($p['old_price']): ?><br><del class="text-xs text-muted"><?= fcfa((int) $p['old_price']) ?></del><?php endif; ?></td>
              <td><a href="stock.php?produit=<?= (int) $p['id'] ?>" class="inline-flex items-center h-7 px-2.5 rounded-md text-xs font-semibold <?= $stock === 0 ? 'bg-terra/10 text-terra' : ($stock <= 5 ? 'bg-amber-50 text-amber-800' : 'bg-forest/10 text-forest') ?>"><?= $stock === 0 ? 'Rupture' : $stock . ' en stock' ?></a></td>
              <td><span class="inline-flex items-center gap-1.5 text-xs font-medium <?= $p['active'] ? 'text-forest' : 'text-muted' ?>"><span class="w-2 h-2 rounded-full <?= $p['active'] ? 'bg-emerald-500' : 'bg-gray-300' ?>"></span><?= $p['active'] ? 'En ligne' : 'Masqué' ?></span></td>
              <td class="!pr-5 sm:!pr-6">
                <div class="flex justify-end gap-1.5">
                  <a href="produits.php?edit=<?= (int) $p['id'] ?>" class="btn btn-outline btn-sm !px-2.5" title="Modifier"><?= icon('edit', 'w-4 h-4') ?></a>
                  <a href="<?= e(url('produit.php?id=' . (int) $p['id'])) ?>" target="_blank" class="btn btn-outline btn-sm !px-2.5" title="Voir sur la boutique"><?= icon('eye', 'w-4 h-4') ?></a>
                  <?= share_button($p) ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-outline btn-sm"><?= $p['active'] ? 'Masquer' : 'Publier' ?></button></form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/layout-end.php'; ?>
