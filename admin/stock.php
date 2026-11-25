<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$productId = (int) ($_GET['produit'] ?? 0);
$filter = (string) ($_GET['filtre'] ?? '');
$scope = in_array($_GET['type'] ?? '', ['produits', 'coffrets'], true) ? (string) $_GET['type'] : '';
$back = 'stock.php?' . http_build_query(array_filter(['produit' => $productId ?: null, 'filtre' => $filter ?: null, 'type' => $scope ?: null]));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $action = (string) ($_POST['form_action'] ?? '');
    if ($action === 'add') {
        $pid = (int) ($_POST['product_id'] ?? 0);
        $productStmt = $pdo->prepare("SELECT p.is_gift_box,c.slug category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?");
        $productStmt->execute([$pid]);
        $product = $productStmt->fetch();
        $isGiftBox = $product && (!empty($product['is_gift_box']) || ($product['category_slug'] ?? '') === 'coffrets');
        $sizes = array_filter(array_map('trim', explode(',', (string) ($_POST['sizes'] ?? ''))));
        $colors = array_filter(array_map('trim', explode(',', (string) ($_POST['colors'] ?? ''))));
        $qty = max(0, (int) ($_POST['qty'] ?? 0));
        if ($isGiftBox) {
            flash('Le stock du coffret se règle sur sa ligne « Stock global du coffret ».', 'error');
        } elseif ($pid && $sizes && $colors) {
            $st = $pdo->prepare('INSERT INTO variants (product_id,size,color,stock) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE stock=VALUES(stock)');
            foreach ($sizes as $s) foreach ($colors as $c) $st->execute([$pid, mb_substr($s, 0, 40), mb_substr($c, 0, 60), $qty]);
            flash(count($sizes) * count($colors) . ' déclinaison(s) enregistrée(s).');
        } else {
            flash('Choisissez un produit, au moins une taille et une couleur.', 'error');
        }
    }
    if ($action === 'update') {
        $st = $pdo->prepare("UPDATE variants v JOIN products p ON p.id=v.product_id LEFT JOIN categories c ON c.id=p.category_id SET v.stock=? WHERE v.id=? AND ((p.is_gift_box=1 OR c.slug='coffrets') AND v.size='Unique' AND v.color='Coffret' OR (p.is_gift_box=0 AND (c.slug IS NULL OR c.slug<>'coffrets')))");
        foreach ((array) ($_POST['stock'] ?? []) as $vid => $val) $st->execute([max(0, (int) $val), (int) $vid]);
        flash('Stock mis à jour.');
    }
    if ($action === 'delete') {
        $variantStmt = $pdo->prepare("SELECT v.size,v.color,p.is_gift_box,c.slug category_slug FROM variants v JOIN products p ON p.id=v.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE v.id=?");
        $variantStmt->execute([(int) ($_POST['variant_id'] ?? 0)]);
        $variant = $variantStmt->fetch();
        $isGiftBox = $variant && (!empty($variant['is_gift_box']) || ($variant['category_slug'] ?? '') === 'coffrets');
        if ($isGiftBox && $variant['size'] === 'Unique' && $variant['color'] === 'Coffret') {
            flash('La ligne de stock global du coffret ne peut pas être supprimée.', 'error');
        } else {
            $pdo->prepare('DELETE FROM variants WHERE id=?')->execute([(int) ($_POST['variant_id'] ?? 0)]);
            flash('Déclinaison supprimée.');
        }
    }
    redirect($back);
}

$where = ['1=1']; $params = [];
if ($productId) { $where[] = 'v.product_id=?'; $params[] = $productId; }
if ($filter === 'bas') $where[] = 'v.stock<=2';
if ($scope === 'coffrets') $where[] = "((p.is_gift_box=1 OR c.slug='coffrets') AND v.size='Unique' AND v.color='Coffret')";
if ($scope === 'produits') $where[] = "(p.is_gift_box=0 AND (c.slug IS NULL OR c.slug<>'coffrets'))";
$where[] = "(((p.is_gift_box=1 OR c.slug='coffrets') AND v.size='Unique' AND v.color='Coffret') OR (p.is_gift_box=0 AND (c.slug IS NULL OR c.slug<>'coffrets')))";
$st = $pdo->prepare('SELECT v.*,p.name,p.image,p.active,p.is_gift_box,c.slug category_slug FROM variants v JOIN products p ON p.id=v.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY p.name,v.color,v.id');
$st->execute($params);
$groups = [];
foreach ($st->fetchAll() as $r) $groups[$r['product_id']][] = $r;
foreach ($groups as &$g) usort($g, fn($a, $b) => [$a['color'], array_search($a['size'], sort_sizes(array_column($g, 'size')))] <=> [$b['color'], array_search($b['size'], sort_sizes(array_column($g, 'size')))]);
unset($g);
$products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();
$totals = $pdo->query("SELECT
    COALESCE(SUM(CASE WHEN p.is_gift_box=0 AND (c.slug IS NULL OR c.slug<>'coffrets') THEN v.stock ELSE 0 END),0) product_units,
    COALESCE(SUM(CASE WHEN (p.is_gift_box=1 OR c.slug='coffrets') AND v.size='Unique' AND v.color='Coffret' THEN v.stock ELSE 0 END),0) gift_box_units,
    COALESCE(SUM(v.stock=0),0) out_of_stock,
    COALESCE(SUM(v.stock BETWEEN 1 AND 2),0) low
    FROM variants v JOIN products p ON p.id=v.product_id LEFT JOIN categories c ON c.id=p.category_id
    WHERE p.active=1 AND (((p.is_gift_box=1 OR c.slug='coffrets') AND v.size='Unique' AND v.color='Coffret') OR (p.is_gift_box=0 AND (c.slug IS NULL OR c.slug<>'coffrets')))")->fetch();

$pageTitle = 'Stocks';
$pageSubtitle = 'Tailles, couleurs et quantités disponibles';
require __DIR__ . '/layout.php';
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
  <?php foreach ([['Stock produits (hors coffrets)', (int) $totals['product_units'], 'layers', ''], ['Coffrets disponibles', (int) $totals['gift_box_units'], 'gift', ''], ['Références faibles (1–2)', (int) $totals['low'], 'bell', 'text-amber-700'], ['Références en rupture', (int) $totals['out_of_stock'], 'x', 'text-terra']] as [$l, $n, $ic, $cls]): ?>
    <div class="bg-white rounded-2xl border border-line p-4 flex items-center gap-3"><span class="w-11 h-11 rounded-full bg-[#EFE9DC] text-forest flex items-center justify-center"><?= icon($ic, 'w-5 h-5') ?></span><div><p class="text-xs text-muted"><?= e($l) ?></p><p class="font-display text-2xl <?= $cls ?>"><?= number_format($n, 0, ',', ' ') ?></p></div></div>
  <?php endforeach; ?>
</div>

<div class="grid xl:grid-cols-[1fr_340px] gap-5 items-start">
  <?= card_open('Stocks des produits et coffrets', '', '<form method="get" class="flex flex-wrap gap-2"><select name="type" class="field !w-auto !h-10 text-sm" data-autosubmit><option value="">Tous les stocks</option><option value="produits" ' . ($scope === 'produits' ? 'selected' : '') . '>Produits hors coffrets</option><option value="coffrets" ' . ($scope === 'coffrets' ? 'selected' : '') . '>Coffrets · stock global</option></select><select name="produit" class="field !w-auto !h-10 text-sm" data-autosubmit><option value="0">Tous les produits</option>' . implode('', array_map(fn($p) => '<option value="' . (int) $p['id'] . '" ' . ($productId === (int) $p['id'] ? 'selected' : '') . '>' . e($p['name']) . '</option>', $products)) . '</select><select name="filtre" class="field !w-auto !h-10 text-sm" data-autosubmit><option value="">Tous les niveaux</option><option value="bas" ' . ($filter === 'bas' ? 'selected' : '') . '>Stock ≤ 2</option></select></form>') ?>
    <?php if (!$groups): ?>
      <?= empty_state('Aucune déclinaison. Ajoutez des tailles et couleurs avec le formulaire.', 'layers') ?>
    <?php else: ?>
      <form method="post" id="stock-form"><?= csrf_field() ?><input type="hidden" name="form_action" value="update"></form>
      <div class="space-y-5">
        <?php foreach ($groups as $pid => $rows): ?>
          <div class="rounded-xl border border-line overflow-hidden">
            <div class="flex items-center gap-3 px-4 py-3 bg-[#FAF8F3]">
              <?= product_thumb($rows[0]['image'], 'w-9 h-11') ?>
              <?php if (admin_can_access((string) admin_user($pdo)['role'], 'produits.php')): ?>
                <a href="produits.php?edit=<?= (int) $pid ?>" class="font-semibold hover:text-forest flex-1 min-w-0 truncate"><?= e($rows[0]['name']) ?></a>
              <?php else: ?>
                <span class="font-semibold flex-1 min-w-0 truncate"><?= e($rows[0]['name']) ?></span>
              <?php endif; ?>
              <?php if (!$rows[0]['active']): ?><span class="text-xs text-muted">Masqué</span><?php endif; ?>
              <span class="text-xs text-muted whitespace-nowrap"><?= array_sum(array_column($rows, 'stock')) ?> unités</span>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-px bg-line">
              <?php foreach ($rows as $r): $s = (int) $r['stock']; ?>
                <div class="bg-white px-4 py-3 flex items-center gap-3">
                  <?php if (!empty($r['is_gift_box']) || ($r['category_slug'] ?? '') === 'coffrets'): ?>
                    <span class="flex-1 min-w-0 text-sm font-semibold">Stock global du coffret</span>
                  <?php else: ?>
                    <span class="w-4 h-4 rounded-full border border-black/10 shrink-0" style="background:<?= e(color_background((string) $r['color'])) ?>" title="<?= e($r['color']) ?>"></span>
                    <span class="flex-1 min-w-0 text-sm"><strong><?= e($r['size']) ?></strong> <span class="text-muted">· <?= e($r['color']) ?></span></span>
                  <?php endif; ?>
                  <input form="stock-form" type="number" min="0" name="stock[<?= (int) $r['id'] ?>]" value="<?= $s ?>" class="w-20 h-9 rounded-lg border text-center text-sm font-semibold tabular-nums outline-none focus:border-forest <?= $s === 0 ? 'border-terra/40 bg-terra/5 text-terra' : ($s <= 2 ? 'border-amber-300 bg-amber-50' : 'border-line') ?>">
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="delete"><input type="hidden" name="variant_id" value="<?= (int) $r['id'] ?>"><button class="w-8 h-8 rounded-lg text-muted hover:text-terra hover:bg-terra/5 flex items-center justify-center" title="Supprimer" data-confirm="Supprimer la déclinaison <?= e($r['size'] . ' · ' . $r['color']) ?> ?"><?= icon('trash', 'w-4 h-4') ?></button></form>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="sticky bottom-4 mt-5 flex justify-end"><button form="stock-form" class="btn btn-primary shadow-lg"><?= icon('check', 'w-4 h-4') ?> Enregistrer les quantités</button></div>
    <?php endif; ?>
  </section>

  <?= card_open('Ajouter des déclinaisons', 'plus') ?>
    <form method="post" class="space-y-4">
      <?= csrf_field() ?><input type="hidden" name="form_action" value="add">
      <label class="block"><span class="label">Produit</span><select name="product_id" required class="field"><option value="">Choisir…</option><?php foreach ($products as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $productId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></label>
      <label class="block"><span class="label">Tailles <span class="font-normal text-muted">(séparées par des virgules)</span></span><input name="sizes" required class="field" placeholder="S, M, L, XL"></label>
      <div class="flex flex-wrap gap-1.5 -mt-2">
        <?php foreach (['S, M, L, XL, XXL' => 'Hauts', '38, 40, 42, 44, 46' => 'Pantalons', '40, 41, 42, 43, 44, 45' => 'Chaussures', 'Unique' => 'Taille unique'] as $val => $lab): ?>
          <button type="button" class="text-[11px] px-2 py-1 rounded-md bg-cream border border-line hover:border-forest" onclick="this.closest('form').sizes.value='<?= $val ?>'"><?= e($lab) ?></button>
        <?php endforeach; ?>
      </div>
      <label class="block"><span class="label">Couleurs <span class="font-normal text-muted">(séparées par des virgules)</span></span><input name="colors" required class="field" placeholder="Noir, Blanc, Dégradé bleu"></label>
      <p class="text-xs text-muted -mt-2 leading-relaxed">Couleur dégradée : « Dégradé bleu », « Dégradé bleu noir ». Plusieurs teintes sur un même article : « Bleu/Blanc » ou « Noir et rouge » (pas de virgule, elle sépare les couleurs). Toutes couleurs : « Multicolore ».</p>
      <label class="block"><span class="label">Quantité pour chaque déclinaison</span><input type="number" name="qty" min="0" value="5" class="field"></label>
      <button class="btn btn-primary w-full"><?= icon('plus', 'w-4 h-4') ?> Ajouter</button>
      <p class="text-xs text-muted">Toutes les combinaisons taille × couleur sont créées. Si une déclinaison existe déjà, sa quantité est remplacée.</p>
      <p class="text-xs text-muted">Pour un coffret, modifiez son stock global dans sa ligne existante ; ne créez pas de déclinaisons.</p>
    </form>
  </section>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
