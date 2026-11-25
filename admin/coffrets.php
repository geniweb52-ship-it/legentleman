<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

function parse_component_options(string $value): array
{
    return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), fn($option) => $option !== '')));
}

$errors = [];
$editId = (int) ($_GET['edit'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    if (($_POST['form_action'] ?? '') !== 'save_composition') {
        http_response_code(400);
        exit('Action invalide.');
    }
    $giftBoxId = (int) ($_POST['gift_box_id'] ?? 0);
    $boxStmt = $pdo->prepare("SELECT p.id FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=? AND (p.is_gift_box=1 OR c.slug='coffrets')");
    $boxStmt->execute([$giftBoxId]);
    if (!$boxStmt->fetchColumn()) $errors[] = 'Coffret introuvable.';
    $submittedComponents = (array) ($_POST['components'] ?? []);
    $components = [];
    foreach ($submittedComponents as $values) {
        if (!is_array($values)) {
            $errors[] = 'Vérifiez les composants saisis.';
            break;
        }
        $nameValue = $values['name'] ?? '';
        $sizesValue = $values['sizes'] ?? '';
        $colorsValue = $values['colors'] ?? '';
        if (!is_string($nameValue) || !is_string($sizesValue) || !is_string($colorsValue)) {
            $errors[] = 'Vérifiez les composants saisis.';
            break;
        }
        $name = trim($nameValue);
        if ($name === '') continue;
        $quantity = filter_var($values['quantity'] ?? null, FILTER_VALIDATE_INT);
        $sizes = parse_component_options($sizesValue);
        $colors = parse_component_options($colorsValue);
        $invalidOptionLengths = array_filter($sizes, fn($option) => mb_strlen($option) > 40)
            || array_filter($colors, fn($option) => mb_strlen($option) > 60);
        if (mb_strlen($name) > 160 || !is_int($quantity) || $quantity < 1 || $quantity > 99 || count($sizes) > 20 || count($colors) > 20 || $invalidOptionLengths) {
            $errors[] = 'Vérifiez le nom, la quantité et les choix de tailles/couleurs.';
            break;
        }
        $components[] = ['name' => $name, 'quantity' => $quantity, 'sizes' => implode(', ', $sizes), 'colors' => implode(', ', $colors)];
    }
    if (!$components && !$errors) $errors[] = 'Ajoutez au moins un composant au coffret.';
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM gift_box_text_components WHERE gift_box_id=?')->execute([$giftBoxId]);
            $insert = $pdo->prepare('INSERT INTO gift_box_text_components (gift_box_id,component_name,quantity,sizes,colors) VALUES (?,?,?,?,?)');
            foreach ($components as $component) {
                $insert->execute([$giftBoxId, $component['name'], $component['quantity'], $component['sizes'], $component['colors']]);
            }
            $pdo->commit();
            flash('Composition du coffret enregistrée.');
            redirect('coffrets.php?edit=' . $giftBoxId);
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Gift box composition: ' . $exception->getMessage());
            $errors[] = 'Impossible d’enregistrer la composition. Réessayez.';
            $editId = $giftBoxId;
        }
    } else {
        $editId = $giftBoxId;
    }
}

$boxes = $pdo->query("SELECT p.*, " . PRODUCT_STOCK_SQL . " stock_total,
    (SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN variants v ON v.id=oi.variant_id JOIN orders o ON o.id=oi.order_id AND o.status<>'annulee' WHERE v.product_id=p.id) sold
    FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_gift_box=1 OR c.slug='coffrets' ORDER BY p.active DESC, p.created_at DESC")->fetchAll();
$savedComponents = $pdo->query('SELECT gift_box_id,id component_id,component_name name,quantity,sizes,colors FROM gift_box_text_components ORDER BY id')->fetchAll();
$componentsByBox = [];
foreach ($savedComponents as $component) {
    $componentsByBox[(int) $component['gift_box_id']][] = [
        'name' => $component['name'],
        'quantity' => (int) $component['quantity'],
        'sizes' => $component['sizes'],
        'colors' => $component['colors'],
    ];
}
$pageTitle = 'Coffrets cadeaux';
$pageSubtitle = 'Saisissez les composants et les choix disponibles pour chaque coffret';
$pageActions = '<a href="produits.php?nouveau=coffret#nouveau" class="btn btn-primary">' . icon('plus', 'w-4 h-4') . ' Nouveau coffret</a>';
require __DIR__ . '/layout.php';
?>
<?php if ($errors): ?><div class="mb-5 rounded-xl border border-terra/30 bg-terra/5 p-4 text-sm text-terra" role="alert"><?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?></div><?php endif; ?>
<?php if (!$boxes): ?>
  <?= card_open() ?><?= empty_state('Aucun coffret. Créez un produit et cochez « C\'est un coffret cadeau ».', 'gift') ?></section>
<?php else: ?>
  <div class="grid sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-5">
    <?php foreach ($boxes as $b): $lines = array_values(array_filter(array_map(fn($l) => preg_match('/^\s*[-•*]\s*(.+)$/u', $l, $m) ? $m[1] : null, preg_split('/\R/', (string) $b['description'])))); $components = $componentsByBox[(int) $b['id']] ?? []; ?>
      <article class="bg-white rounded-2xl border border-line overflow-hidden flex flex-col <?= $b['active'] ? '' : 'opacity-60' ?>">
        <div class="relative aspect-[4/3] bg-[#EDE7DC]">
          <?php if ($b['image']): ?><img src="<?= e(image_url($b['image'], 800)) ?>" loading="lazy" alt="" class="w-full h-full object-cover"><?php else: ?><div class="w-full h-full flex items-center justify-center text-forest/40"><?= icon('gift', 'w-12 h-12') ?></div><?php endif; ?>
          <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold"><span class="w-2 h-2 rounded-full <?= $b['active'] ? 'bg-emerald-500' : 'bg-gray-300' ?>"></span><?= $b['active'] ? 'En ligne' : 'Masqué' ?></span>
        </div>
        <div class="p-5 flex-1 flex flex-col">
          <h2 class="font-display text-xl"><?= e($b['name']) ?></h2>
          <p class="mt-1"><span class="font-bold text-terra tabular-nums"><?= fcfa((int) $b['price']) ?></span><?php if ($b['old_price']): ?> <del class="text-xs text-muted"><?= fcfa((int) $b['old_price']) ?></del><?php endif; ?></p>
          <?php if ($components): ?><ul class="mt-3 space-y-1 text-sm text-muted"><?php foreach ($components as $component): ?><li><?= e($component['name']) ?> × <?= (int) $component['quantity'] ?><?php if ($component['sizes']): ?> · Tailles : <?= e($component['sizes']) ?><?php endif; ?><?php if ($component['colors']): ?> · Couleurs : <?= e($component['colors']) ?><?php endif; ?></li><?php endforeach; ?></ul>
          <?php elseif ($lines): ?><ul class="mt-3 space-y-1 text-sm text-muted"><?php foreach (array_slice($lines, 0, 4) as $l): ?><li class="flex gap-2"><?= icon('check', 'w-4 h-4 text-forest shrink-0 mt-0.5') ?><?= e($l) ?></li><?php endforeach; ?></ul><?php endif; ?>
          <details class="mt-4 rounded-xl border border-line p-3" <?= $editId === (int) $b['id'] ? 'open' : '' ?>>
            <summary class="cursor-pointer text-sm font-semibold">Composants (<?= count($components) ?>)</summary>
            <?php
              $displayComponents = $editId === (int) $b['id'] && isset($_POST['components'])
                  ? array_values((array) $_POST['components'])
                  : $components;
              $displayComponents = array_map(static function ($component): array {
                  if (!is_array($component)) return ['name' => '', 'quantity' => 1, 'sizes' => '', 'colors' => ''];
                  return [
                      'name' => is_string($component['name'] ?? null) ? $component['name'] : '',
                      'quantity' => is_scalar($component['quantity'] ?? null) ? (string) $component['quantity'] : '1',
                      'sizes' => is_string($component['sizes'] ?? null) ? $component['sizes'] : '',
                      'colors' => is_string($component['colors'] ?? null) ? $component['colors'] : '',
                  ];
              }, $displayComponents);
              if (!$displayComponents) $displayComponents = [['name' => '', 'quantity' => 1, 'sizes' => '', 'colors' => '']];
            ?>
            <form method="post" class="mt-3 space-y-3">
              <?= csrf_field() ?><input type="hidden" name="form_action" value="save_composition"><input type="hidden" name="gift_box_id" value="<?= (int) $b['id'] ?>">
              <div class="space-y-3" data-component-list data-next-index="<?= count($displayComponents) ?>">
                <?php foreach ($displayComponents as $index => $component): ?>
                  <div class="rounded-lg border border-line p-3 space-y-2" data-component-row>
                    <div class="grid grid-cols-[1fr_80px_auto] gap-2 items-end">
                      <label class="block"><span class="label">Composant</span><input name="components[<?= (int) $index ?>][name]" value="<?= e($component['name'] ?? '') ?>" class="field" placeholder="Ex. Tee-shirt"></label>
                      <label class="block"><span class="label">Qté</span><input type="number" name="components[<?= (int) $index ?>][quantity]" value="<?= e($component['quantity'] ?? 1) ?>" min="1" max="99" class="field"></label>
                      <button type="button" class="btn btn-outline btn-sm !px-2" data-remove-component aria-label="Retirer ce composant"><?= icon('trash', 'w-4 h-4') ?></button>
                    </div>
                    <label class="block"><span class="label">Tailles proposées <span class="font-normal text-muted">(facultatif, séparées par des virgules)</span></span><input name="components[<?= (int) $index ?>][sizes]" value="<?= e($component['sizes'] ?? '') ?>" class="field" placeholder="S, M, L, XL"></label>
                    <label class="block"><span class="label">Couleurs proposées <span class="font-normal text-muted">(facultatif, séparées par des virgules)</span></span><input name="components[<?= (int) $index ?>][colors]" value="<?= e($component['colors'] ?? '') ?>" class="field" placeholder="Noir, Blanc"></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <button type="button" class="btn btn-outline btn-sm" data-add-component><?= icon('plus', 'w-4 h-4') ?> Ajouter un composant</button>
              <p class="text-xs text-muted">Le stock se règle séparément dans « Stock global » : c’est le nombre total de coffrets disponibles, indépendant du stock des produits composants.</p>
              <button class="btn btn-primary btn-sm">Enregistrer les composants</button>
            </form>
          </details>
          <div class="mt-auto pt-4 flex items-center justify-between text-xs text-muted border-t border-line mt-4"><span><?= (int) $b['stock_total'] ?> en stock</span><span><?= (int) $b['sold'] ?> vendu<?= (int) $b['sold'] > 1 ? 's' : '' ?></span></div>
          <div class="mt-4 flex gap-2"><a href="produits.php?edit=<?= (int) $b['id'] ?>" class="btn btn-outline btn-sm flex-1"><?= icon('edit', 'w-4 h-4') ?> Modifier</a><a href="stock.php?produit=<?= (int) $b['id'] ?>&amp;type=coffrets" class="btn btn-outline btn-sm flex-1"><?= icon('layers', 'w-4 h-4') ?> Stock global</a><?= share_button($b) ?></div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<template id="gift-component-template">
  <div class="rounded-lg border border-line p-3 space-y-2" data-component-row>
    <div class="grid grid-cols-[1fr_80px_auto] gap-2 items-end">
      <label class="block"><span class="label">Composant</span><input name="components[__INDEX__][name]" class="field" placeholder="Ex. Tee-shirt"></label>
      <label class="block"><span class="label">Qté</span><input type="number" name="components[__INDEX__][quantity]" value="1" min="1" max="99" class="field"></label>
      <button type="button" class="btn btn-outline btn-sm !px-2" data-remove-component aria-label="Retirer ce composant"><?= icon('trash', 'w-4 h-4') ?></button>
    </div>
    <label class="block"><span class="label">Tailles proposées <span class="font-normal text-muted">(facultatif, séparées par des virgules)</span></span><input name="components[__INDEX__][sizes]" class="field" placeholder="S, M, L, XL"></label>
    <label class="block"><span class="label">Couleurs proposées <span class="font-normal text-muted">(facultatif, séparées par des virgules)</span></span><input name="components[__INDEX__][colors]" class="field" placeholder="Noir, Blanc"></label>
  </div>
</template>
<script>
(() => {
  const template = document.getElementById('gift-component-template');
  document.querySelectorAll('[data-add-component]').forEach((button) => button.addEventListener('click', () => {
    const list = button.closest('form').querySelector('[data-component-list]');
    const index = Number(list.dataset.nextIndex);
    list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
    list.dataset.nextIndex = String(index + 1);
  }));
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-component]');
    if (button) button.closest('[data-component-row]').remove();
  });
})();
</script>
<?php require __DIR__ . '/layout-end.php'; ?>
