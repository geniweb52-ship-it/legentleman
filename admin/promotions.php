<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $action = (string) ($_POST['form_action'] ?? '');
    $pct = max(0, min(90, (int) ($_POST['pct'] ?? 0)));
    $catId = (int) ($_POST['category_id'] ?? 0);
    if ($catId && $action === 'apply') {
        $st = $pdo->prepare('SELECT id FROM products WHERE category_id=?'); $st->execute([$catId]);
        $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    if (!$ids) { flash('Sélectionnez au moins un produit (ou une catégorie).', 'error'); redirect('promotions.php'); }
    $in = implode(',', array_fill(0, count($ids), '?'));
    if ($action === 'apply' && $pct > 0) {
        // Le prix d'origine est conservé dans old_price ; arrondi à la centaine.
        $pdo->prepare("UPDATE products SET old_price=COALESCE(old_price, price), price=GREATEST(100, ROUND(COALESCE(old_price, price)*(100-?)/100, -2)) WHERE id IN ($in)")->execute([$pct, ...$ids]);
        flash('Remise de ' . $pct . ' % appliquée à ' . count($ids) . ' produit(s).');
    } elseif ($action === 'remove') {
        $pdo->prepare("UPDATE products SET price=old_price, old_price=NULL WHERE old_price IS NOT NULL AND id IN ($in)")->execute($ids);
        flash('Promotion retirée : prix d\'origine rétablis.');
    } else {
        flash('Indiquez un pourcentage de remise.', 'error');
    }
    redirect('promotions.php');
}

$products = $pdo->query('SELECT p.id, p.name, p.price, p.old_price, p.image, p.active, c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY (p.old_price IS NULL), c.sort_order, p.name')->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories WHERE slug<>'tous' ORDER BY sort_order")->fetchAll();
$onSale = array_filter($products, fn($p) => $p['old_price']);
$pageTitle = 'Promotions';
$pageSubtitle = count($onSale) . ' produit' . (count($onSale) > 1 ? 's' : '') . ' en promotion · le badge « -X % » s\'affiche automatiquement sur la boutique';
require __DIR__ . '/layout.php';
?>
<form method="post" class="grid xl:grid-cols-[1fr_340px] gap-5 items-start">
  <?= csrf_field() ?>
  <?= card_open('Produits', 'tag', '<label class="text-sm inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" class="w-4 h-4 accent-[#2E4A36]" onchange="document.querySelectorAll(\'[name=&quot;ids[]&quot;]\').forEach(c => c.checked = this.checked)"> Tout sélectionner</label>') ?>
    <div class="overflow-x-auto -mx-5 sm:-mx-6">
      <table class="tbl w-full min-w-[600px]">
        <thead><tr><th class="!pl-5 sm:!pl-6 w-10"></th><th>Produit</th><th>Prix d'origine</th><th>Prix actuel</th><th class="!pr-5 sm:!pr-6">Remise</th></tr></thead>
        <tbody>
          <?php foreach ($products as $p): $disc = $p['old_price'] ? (int) round(100 - $p['price'] * 100 / $p['old_price']) : 0; ?>
            <tr>
              <td class="!pl-5 sm:!pl-6"><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" class="w-4 h-4 accent-[#2E4A36]"></td>
              <td><div class="flex items-center gap-3"><?= product_thumb($p['image'], 'w-10 h-12') ?><span><span class="block font-medium"><?= e($p['name']) ?></span><span class="text-xs text-muted"><?= e($p['category_name'] ?? '—') ?><?= $p['active'] ? '' : ' · masqué' ?></span></span></div></td>
              <td class="tabular-nums whitespace-nowrap text-muted"><?= fcfa((int) ($p['old_price'] ?: $p['price'])) ?></td>
              <td class="tabular-nums whitespace-nowrap font-semibold <?= $disc ? 'text-terra' : '' ?>"><?= fcfa((int) $p['price']) ?></td>
              <td class="!pr-5 sm:!pr-6"><?= $disc ? '<span class="inline-flex h-7 px-2.5 items-center rounded-md bg-terra text-white text-xs font-bold">-' . $disc . ' %</span>' : '<span class="text-xs text-muted">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
  <div class="xl:sticky xl:top-24 space-y-5">
    <?= card_open('Appliquer une remise', 'zap') ?>
      <div class="space-y-4">
        <label class="block"><span class="label">Pourcentage de remise</span>
          <div class="flex gap-2"><input type="number" name="pct" min="1" max="90" value="20" class="field"><span class="h-11 px-3 rounded-lg bg-cream border border-line flex items-center font-semibold">%</span></div>
        </label>
        <div class="flex flex-wrap gap-1.5"><?php foreach ([10, 15, 20, 30, 50] as $v): ?><button type="button" class="text-xs px-2.5 py-1 rounded-md bg-cream border border-line hover:border-forest" onclick="this.form.pct.value=<?= $v ?>">-<?= $v ?> %</button><?php endforeach; ?></div>
        <label class="block"><span class="label">Appliquer à <span class="font-normal text-muted">(facultatif)</span></span><select name="category_id" class="field"><option value="0">Produits cochés</option><?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>">Toute la catégorie « <?= e($c['name']) ?> »</option><?php endforeach; ?></select></label>
        <button name="form_action" value="apply" class="btn btn-primary w-full"><?= icon('tag', 'w-4 h-4') ?> Appliquer la remise</button>
        <button name="form_action" value="remove" class="btn btn-danger w-full" data-confirm="Rétablir le prix d'origine des produits cochés ?">Retirer la promotion (cochés)</button>
      </div>
      <p class="mt-4 text-xs text-muted leading-relaxed">Le prix d'origine est conservé : vous pouvez retirer la promotion à tout moment. Les prix remisés sont arrondis à la centaine.</p>
    </section>
  </div>
</form>
<?php require __DIR__ . '/layout-end.php'; ?>
