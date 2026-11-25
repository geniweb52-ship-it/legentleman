<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/cart.php';

/** Termine une action du panier : JSON pour les requêtes AJAX, sinon redirection. */
function cart_respond(bool $ok, string $message, string $fallback): never
{
    if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$ok) http_response_code(422);
        echo json_encode(['ok' => $ok, 'message' => $message, 'count' => cart_count()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    flash($message, $ok ? 'success' : 'error');
    redirect($fallback);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) cart_respond(false, 'Session expirée, veuillez réessayer.', 'panier.php');
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'add') {
        $productId = (int) ($_POST['product_id'] ?? 0);
        $back = 'produit.php?id=' . $productId;
        $size = trim((string) ($_POST['size'] ?? ''));
        $color = trim((string) ($_POST['color'] ?? ''));
        $quantity = max(1, min(99, (int) ($_POST['quantity'] ?? 1)));
        $productStmt = $pdo->prepare('SELECT p.*, c.slug category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1');
        $productStmt->execute([$productId]);
        $product = $productStmt->fetch();
        if (!$product) cart_respond(false, 'Ce produit n’est plus disponible.', $back);

        if (is_gift_box_product($product)) {
            $parentStmt = $pdo->prepare("SELECT id,size,color FROM variants WHERE product_id=? AND size='Unique' AND color='Coffret'");
            $parentStmt->execute([$productId]);
            $parentVariant = $parentStmt->fetch();
            if (!$parentVariant) cart_respond(false, 'Ce coffret n’est pas encore prêt à être commandé.', $back);
            try {
                $components = resolve_gift_box_selection($pdo, $productId, $_POST['components'] ?? []);
            } catch (RuntimeException $exception) {
                cart_respond(false, $exception->getMessage(), $back);
            }
            $variantId = (int) $parentVariant['id'];
            $lineKey = cart_line_key($variantId, $components);
            $currentQuantity = (int) ($_SESSION['cart'][$lineKey]['quantity'] ?? 0);
            $available = cart_line_available_stock($pdo, ['variant_id' => $variantId, 'components' => $components]);
            $wanted = $currentQuantity + $quantity;
            if ($available < 1) cart_respond(false, 'Les articles de ce coffret sont épuisés.', $back);
            $_SESSION['cart'][$lineKey] = [
                'variant_id' => $variantId,
                'quantity' => min($wanted, $available),
                'name' => $product['name'],
                'size' => $parentVariant['size'],
                'color' => $parentVariant['color'],
                'components' => $components,
            ];
            $message = $wanted > $available
                ? 'Stock limité : ' . $available . ' coffret(s) maximum avec cette composition.'
                : $product['name'] . ' ajouté au panier.';
            cart_respond(true, $message, $back);
        }

        $stmt = $pdo->prepare('SELECT v.*, p.name FROM variants v JOIN products p ON p.id=v.product_id WHERE p.active=1 AND v.product_id=? AND v.size=? AND v.color=?');
        $stmt->execute([$productId, $size, $color]);
        $variant = $stmt->fetch();
        if (!$variant) cart_respond(false, 'Choisissez une taille et une couleur.', $back);
        $stock = (int) $variant['stock'];
        if ($stock < 1) cart_respond(false, 'Cette déclinaison est épuisée.', $back);

        $variantId = (int) $variant['id'];
        $_SESSION['cart'][$variantId] ??= ['quantity' => 0, 'name' => $variant['name'], 'size' => $variant['size'], 'color' => $variant['color']];
        $wanted = $_SESSION['cart'][$variantId]['quantity'] + $quantity;
        $_SESSION['cart'][$variantId]['quantity'] = min($wanted, $stock);
        $message = $wanted > $stock
            ? 'Stock limité : ' . $stock . ' exemplaire' . ($stock > 1 ? 's' : '') . ' maximum dans votre panier.'
            : $variant['name'] . ' (' . $variant['size'] . ', ' . $variant['color'] . ') ajouté au panier.';
        cart_respond(true, $message, $back);
    }

    if ($action === 'quantity') {
        $lineKey = (string) ($_POST['line_key'] ?? $_POST['variant_id'] ?? '');
        $quantity = max(0, min(99, (int) ($_POST['quantity'] ?? 1)));
        if (isset($_SESSION['cart'][$lineKey])) {
            $item = $_SESSION['cart'][$lineKey];
            $stock = cart_line_available_stock($pdo, $item);
            if ($quantity === 0) unset($_SESSION['cart'][$lineKey]);
            else $_SESSION['cart'][$lineKey]['quantity'] = max(1, min($quantity, $stock));
        }
        redirect('panier.php');
    }

    if ($action === 'remove') {
        $lineKey = (string) ($_POST['line_key'] ?? $_POST['variant_id'] ?? '');
        unset($_SESSION['cart'][$lineKey]);
        flash('Article retiré du panier.');
        redirect('panier.php');
    }
    redirect('panier.php');
}

$items = [];
$stmt = $pdo->prepare('SELECT v.stock, p.id product_id, p.name, p.price, p.image, p.ready_days FROM variants v JOIN products p ON p.id=v.product_id WHERE v.id=?');
foreach ($_SESSION['cart'] as $variantId => $item) {
    $resolvedVariantId = cart_item_variant_id((string) $variantId, $item);
    $stmt->execute([$resolvedVariantId]);
    if ($row = $stmt->fetch()) {
        $items[] = ['line_key' => (string) $variantId, 'variant_id' => $resolvedVariantId, 'quantity' => (int) $item['quantity'], 'size' => $item['size'], 'color' => $item['color'], 'components' => $item['components'] ?? [], 'stock' => cart_line_available_stock($pdo, $item)] + $row;
    } else {
        unset($_SESSION['cart'][$variantId]);
    }
}
$subtotal = array_sum(array_map(fn($item) => (int) $item['price'] * (int) $item['quantity'], $items));
$remaining = max(0, FREE_DELIVERY_THRESHOLD - $subtotal);
$progress = min(100, (int) round($subtotal * 100 / max(1, FREE_DELIVERY_THRESHOLD)));

$waLines = array_map(function ($item) {
    $line = '• ' . $item['name'];
    if ($item['components']) {
        $line .= ' : ' . implode(', ', array_map(function ($component) {
            $options = array_filter([$component['size'], $component['color']]);
            return $component['product_name'] . ($options ? ' (' . implode(' · ', $options) . ')' : '') . ' × ' . (int) $component['quantity_per_bundle'];
        }, $item['components']));
    } else {
        $line .= ' — ' . $item['size'] . ', ' . $item['color'];
    }
    $readyDays = product_ready_days($item['ready_days'] ?? null);
    return $line . ' × ' . $item['quantity'] . ' = ' . money((int) $item['price'] * (int) $item['quantity'])
        . ($readyDays ? ' (sur commande, prêt en ' . days_label($readyDays) . ')' : '');
}, $items);
$waMessage = implode("\n", [
    'Bonjour ' . SHOP_NAME . ', je souhaite commander :',
    '',
    '🛍️ Articles :',
    ...$waLines,
    '',
    'Sous-total : ' . money($subtotal),
    'Livraison : ' . ($remaining === 0 ? 'offerte' : 'à confirmer selon ma zone'),
    '',
    '👤 Nom : ',
    '📍 Adresse de livraison : ',
    '💳 Paiement (Wave / Orange Money / à la livraison) : ',
]);

$pageTitle = 'Panier';
require __DIR__ . '/includes/header.php';
?>
<main class="max-w-site mx-auto px-4 sm:px-6 pt-6">
  <?= breadcrumb([['Panier', null]]) ?>
  <h1 class="font-display text-4xl sm:text-5xl mt-6">Votre panier</h1>

  <?php if (!$items): ?>
    <div class="mt-10 rounded-2xl border border-line bg-white py-16 px-6 text-center">
      <span class="mx-auto w-16 h-16 rounded-full bg-cream flex items-center justify-center text-forest"><?= icon('bag', 'w-7 h-7') ?></span>
      <p class="mt-5 font-display text-2xl">Votre panier est vide</p>
      <p class="mt-2 text-muted">Parcourez nos nouveautés et trouvez la pièce qui vous ressemble.</p>
      <div class="mt-7 flex flex-wrap justify-center gap-3"><a href="<?= e(url('boutique.php')) ?>" class="btn btn-primary">Découvrir la boutique</a><a href="<?= e(url('favoris.php')) ?>" class="btn btn-outline"><?= icon('heart', 'w-4 h-4') ?> Mes favoris</a></div>
    </div>
  <?php else: ?>
    <p class="mt-2 text-muted"><?= cart_count() ?> article<?= cart_count() > 1 ? 's' : '' ?></p>
    <div class="mt-8 grid lg:grid-cols-[1fr_380px] gap-8 items-start">
      <div>
        <div class="rounded-xl bg-white border border-line p-4 sm:p-5 mb-5">
          <?php if ($remaining > 0): ?>
            <p class="text-sm">Plus que <strong class="text-forest tabular-nums"><?= money($remaining) ?></strong> pour profiter de la <strong>livraison offerte</strong>.</p>
          <?php else: ?>
            <p class="text-sm text-forest font-semibold flex items-center gap-2"><?= icon('check', 'w-4 h-4') ?> Félicitations, la livraison vous est offerte !</p>
          <?php endif; ?>
          <div class="mt-3 h-1.5 rounded-full bg-line overflow-hidden"><div class="h-full bg-forest rounded-full transition-all duration-700" style="width:<?= $progress ?>%"></div></div>
        </div>

        <ul class="divide-y divide-line border-y border-line">
          <?php foreach ($items as $item): ?>
            <li class="py-5 flex gap-4 sm:gap-6">
              <a href="<?= e(url('produit.php?id=' . (int) $item['product_id'])) ?>" class="w-24 sm:w-28 aspect-[4/5] rounded-lg overflow-hidden bg-[#EDE7DC] shrink-0">
                <?php if ($item['image']): ?><img src="<?= e(image_url($item['image'], 400)) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= placeholder() ?><?php endif; ?>
              </a>
              <div class="flex-1 min-w-0 flex flex-col">
                <div class="flex justify-between gap-4">
                  <div class="min-w-0">
                    <h2 class="font-semibold leading-snug"><a href="<?= e(url('produit.php?id=' . (int) $item['product_id'])) ?>" class="hover:text-forest"><?= e($item['name']) ?></a></h2>
                    <?php if ($item['components']): ?>
                      <ul class="mt-1 text-sm text-muted">
                        <?php foreach ($item['components'] as $component): $componentOptions = array_filter([$component['size'], $component['color']]); ?>
                          <li><?= e($component['product_name']) ?><?= $componentOptions ? ' — ' . e(implode(' · ', $componentOptions)) : '' ?> × <?= (int) $component['quantity_per_bundle'] ?></li>
                        <?php endforeach; ?>
                      </ul>
                    <?php else: ?>
                      <p class="mt-1 text-sm text-muted flex items-center gap-2"><span class="w-3.5 h-3.5 rounded-full border border-black/10" style="background:<?= e(color_background((string) $item['color'])) ?>"></span><?= e($item['color']) ?> · Taille <?= e($item['size']) ?></p>
                    <?php endif; ?>
                    <?php if ($itemReadyDays = product_ready_days($item['ready_days'] ?? null)): ?>
                      <p class="mt-1.5 text-xs font-semibold text-forest flex items-center gap-1.5"><?= icon('calendar', 'w-3.5 h-3.5') ?> Sur commande · prêt le <?= e(date_fr(ready_date_from_days($itemReadyDays), true)) ?></p>
                    <?php endif; ?>
                  </div>
                  <p class="font-semibold tabular-nums whitespace-nowrap"><?= money((int) $item['price'] * (int) $item['quantity']) ?></p>
                </div>
                <div class="mt-auto pt-4 flex items-center justify-between gap-3">
                  <form method="post" class="inline-flex items-center h-10 rounded-lg border border-line bg-white" data-qty data-autosubmit-qty>
                    <?= csrf_field() ?><input type="hidden" name="action" value="quantity"><input type="hidden" name="line_key" value="<?= e($item['line_key']) ?>">
                    <button type="button" class="w-10 h-full flex items-center justify-center hover:text-forest" data-qty-minus aria-label="Diminuer"><?= icon('minus', 'w-3.5 h-3.5') ?></button>
                    <input type="number" name="quantity" min="1" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>" class="w-10 text-center bg-transparent text-sm font-semibold tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" aria-label="Quantité">
                    <button type="button" class="w-10 h-full flex items-center justify-center hover:text-forest" data-qty-plus aria-label="Augmenter"><?= icon('plus', 'w-3.5 h-3.5') ?></button>
                  </form>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="line_key" value="<?= e($item['line_key']) ?>"><button class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-terra transition-colors"><?= icon('trash', 'w-4 h-4') ?> <span class="hidden sm:inline">Retirer</span></button></form>
                </div>
                <?php if ((int) $item['stock'] > 0 && (int) $item['stock'] <= 3 && !product_ready_days($item['ready_days'] ?? null)): ?><p class="mt-2 text-xs text-terra">Plus que <?= (int) $item['stock'] ?> en stock</p><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= e(url('boutique.php')) ?>" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold hover:text-forest"><?= icon('chevron-left', 'w-4 h-4') ?> Continuer mes achats</a>
      </div>

      <aside class="lg:sticky lg:top-24 rounded-2xl bg-white border border-line p-6">
        <h2 class="font-display text-2xl">Récapitulatif</h2>
        <dl class="mt-5 space-y-3 text-sm">
          <div class="flex justify-between"><dt class="text-muted">Sous-total</dt><dd class="font-semibold tabular-nums"><?= money($subtotal) ?></dd></div>
          <div class="flex justify-between"><dt class="text-muted">Livraison</dt><dd class="tabular-nums"><?= $remaining > 0 ? 'Calculée à l\'étape suivante' : '<span class="text-forest font-semibold">Offerte</span>' ?></dd></div>
        </dl>
        <div class="mt-5 pt-5 border-t border-line flex justify-between items-baseline"><span class="font-semibold">Total estimé</span><span class="text-xl font-bold tabular-nums"><?= money($subtotal) ?></span></div>
        <a href="<?= e(url('commande.php')) ?>" class="btn btn-primary w-full mt-6 !min-h-[52px]">Passer la commande <?= icon('arrow-right', 'w-4 h-4') ?></a>
        <a href="<?= e(whatsapp_url($waMessage)) ?>" target="_blank" rel="noopener" class="btn btn-outline w-full mt-3 !min-h-[52px]"><?= icon('whatsapp', 'w-5 h-5 text-[#1DA851]') ?> Commander sur WhatsApp</a>
        <ul class="mt-6 space-y-2.5 text-xs text-muted">
          <li class="flex items-center gap-2.5"><?= icon('shield', 'w-4 h-4 text-forest') ?> Paiement sécurisé : Wave, Orange Money, espèces</li>
          <li class="flex items-center gap-2.5"><?= icon('truck', 'w-4 h-4 text-forest') ?> Livraison 24–48 h à Dakar</li>
          <li class="flex items-center gap-2.5"><?= icon('refresh', 'w-4 h-4 text-forest') ?> Échange facile sous 7 jours</li>
        </ul>
      </aside>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
