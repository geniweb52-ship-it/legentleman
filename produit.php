<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/cart.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT p.*, c.name category_name, c.slug category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1');
$stmt->execute([$id]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Produit introuvable';
    require __DIR__ . '/includes/header.php'; ?>
    <main class="max-w-xl mx-auto px-4 py-24 text-center">
      <p class="font-display text-7xl text-forest/20">404</p>
      <h1 class="font-display text-3xl mt-4">Ce produit n'est plus disponible</h1>
      <p class="mt-3 text-muted">Il a peut-être été retiré ou est en rupture. Découvrez nos autres pièces.</p>
      <a href="<?= e(url('boutique.php')) ?>" class="btn btn-primary mt-8">Retour à la boutique</a>
    </main>
    <?php require __DIR__ . '/includes/footer.php';
    exit;
}

$variantStmt = $pdo->prepare('SELECT * FROM variants WHERE product_id=? ORDER BY id');
$variantStmt->execute([$id]);
$variants = $variantStmt->fetchAll();
$isGiftBox = is_gift_box_product($product);
$parentVariant = null;
foreach ($variants as $variant) {
    if (!$isGiftBox || ($variant['size'] === 'Unique' && $variant['color'] === 'Coffret')) {
        $parentVariant = $variant;
        break;
    }
}
$stockMap = [];
foreach ($variants as $v) $stockMap[$v['color'] . '|' . $v['size']] = (int) $v['stock'];
$sizes = sort_sizes(array_values(array_unique(array_column($variants, 'size'))));
$colors = array_values(array_unique(array_column($variants, 'color')));
$totalStock = array_sum($stockMap);
$sizeStock = [];
foreach ($variants as $v) $sizeStock[$v['size']] = ($sizeStock[$v['size']] ?? 0) + (int) $v['stock'];
$colorStock = [];
foreach ($variants as $v) $colorStock[$v['color']] = ($colorStock[$v['color']] ?? 0) + (int) $v['stock'];
$defaultColor = null;
foreach ($colors as $c) if (($colorStock[$c] ?? 0) > 0) { $defaultColor = $c; break; }
$bundleComponents = $isGiftBox ? gift_box_components($pdo, $id) : [];
$bundleReady = $bundleComponents !== [] && (bool) $parentVariant;
if ($isGiftBox) {
    $stockMap = [];
    $sizes = [];
    $colors = [];
    $totalStock = $bundleReady ? (int) $parentVariant['stock'] : 0;
}

// Description : les lignes commençant par « - » ou « • » deviennent une liste à puces.
$paragraphs = [];
$bullets = [];
foreach (preg_split('/\R/', (string) $product['description']) as $line) {
    $line = trim($line);
    if ($line === '') continue;
    if (preg_match('/^[-•*]\s*(.+)$/u', $line, $m)) $bullets[] = $m[1];
    else $paragraphs[] = $line;
}

$images = array_values(array_filter([$product['image'] ?? '', $product['image_back'] ?? ''], fn($v) => $v !== ''));
// Article sur commande : délai de confection et date estimée si le client commande aujourd'hui.
$readyDays = product_ready_days($product['ready_days'] ?? null);
$readyDate = $readyDays ? ready_date_from_days($readyDays) : null;
$readyText = $readyDays ? 'sur commande, prêt en ' . days_label($readyDays) : '';
$discount = (!empty($product['old_price']) && (int) $product['old_price'] > (int) $product['price']) ? (int) round(100 - ((int) $product['price'] * 100 / (int) $product['old_price'])) : 0;

$relatedStmt = $pdo->prepare('SELECT p.*, c.name category_name, ' . PRODUCT_STOCK_SQL . ' stock_total FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND p.id<>? AND (p.category_id <=> ?) ORDER BY RAND() LIMIT 4');
$relatedStmt->execute([$id, $product['category_id']]);
$related = $relatedStmt->fetchAll();
if (count($related) < 4) {
    $more = $pdo->prepare('SELECT p.*, c.name category_name, ' . PRODUCT_STOCK_SQL . ' stock_total FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND p.id<>? ORDER BY p.created_at DESC LIMIT 8');
    $more->execute([$id]);
    $known = array_column($related, 'id');
    foreach ($more->fetchAll() as $r) { if (count($related) >= 4) break; if (!in_array($r['id'], $known, true)) $related[] = $r; }
}

$categoryCrumb = $product['category_name'] ? [[$product['category_name'], url('boutique.php?cat[]=' . rawurlencode((string) $product['category_slug']))]] : [];
$pageTitle = $product['name'];
$pageDescription = mb_substr(implode(' ', $paragraphs) ?: $product['name'], 0, 155);
$pageImage = $images[0] ?? null;
$productUrl = site_url('produit.php?id=' . $id);
$buyUrl = site_url('acheter.php?id=' . $id);
$hasStickyBar = true;
$hideReassurance = true;
require __DIR__ . '/includes/header.php';
?>
<main class="max-w-site mx-auto px-4 sm:px-6 pt-6 pb-10">
  <?= breadcrumb([...$categoryCrumb, [$product['name'], null]]) ?>

  <div class="mt-6 grid lg:grid-cols-[1.15fr_1fr] gap-8 xl:gap-14 items-start">
    <!-- Galerie -->
    <div class="lg:sticky lg:top-24 grid <?= count($images) > 1 ? 'sm:grid-cols-[88px_1fr]' : '' ?> gap-3">
      <?php if (count($images) > 1): ?>
        <div class="order-2 sm:order-1 flex sm:flex-col gap-3">
          <?php foreach ($images as $i => $img): ?>
            <button type="button" data-gallery="<?= e(image_srcset($img) ? image_url($img, 800) : $img) ?>" data-srcset="<?= e(image_srcset($img)) ?>" class="gallery-thumb w-20 sm:w-full aspect-[4/5] rounded-lg overflow-hidden border-2 transition <?= $i === 0 ? 'border-ink' : 'border-transparent opacity-70 hover:opacity-100' ?>" aria-label="Voir la photo <?= $i + 1 ?>">
              <img src="<?= e(image_url($img, 400)) ?>" alt="" class="w-full h-full object-cover" decoding="async">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="order-1 sm:order-2 relative aspect-[4/5] rounded-2xl overflow-hidden bg-[#EDE7DC] group">
        <?php if ($images): ?>
          <?= responsive_img($images[0], (string) $product['name'], 'w-full h-full object-cover transition-opacity duration-300 cursor-zoom-in', '(min-width:1024px) 600px, 100vw', false, 'id="product-image" data-zoom fetchpriority="high"') ?>
        <?php else: ?>
          <?= placeholder((string) ($product['category_name'] ?? '')) ?>
        <?php endif; ?>
        <?php if ($discount): ?><span class="badge bg-terra text-white absolute top-4 left-4">-<?= $discount ?>%</span><?php endif; ?>
        <button type="button" class="wish-btn absolute top-3 right-3 bg-white/90 !w-11 !h-11 !mt-0 shadow-sm" data-wish="<?= (int) $product['id'] ?>" aria-label="Ajouter aux favoris" aria-pressed="false"><?= icon('heart', 'w-5 h-5') ?></button>
      </div>
    </div>

    <!-- Infos -->
    <div>
      <?php if ($product['brand']): ?><p class="text-xs font-semibold uppercase tracking-[.2em] text-muted"><?= e($product['brand']) ?></p><?php endif; ?>
      <h1 class="font-display text-[34px] sm:text-[42px] leading-tight mt-1"><?= e($product['name']) ?></h1>
      <p class="mt-3 flex items-baseline gap-3">
        <span class="text-terra text-[28px] font-bold tabular-nums"><?= money((int) $product['price']) ?></span>
        <?php if ($discount): ?><del class="text-muted tabular-nums"><?= money((int) $product['old_price']) ?></del><?php endif; ?>
      </p>
      <?php if ($readyDate): ?>
        <div class="mt-4 rounded-xl border border-forest/25 bg-forest/5 p-4 flex gap-3 text-sm">
          <span class="text-forest shrink-0"><?= icon('calendar', 'w-5 h-5') ?></span>
          <p><strong class="text-forest">Sur commande — prêt en <?= e(days_label($readyDays)) ?>.</strong><br><span class="text-ink/75">Commandé aujourd'hui, il sera prêt le <?= e(date_fr($readyDate, true)) ?>. Nous vous le livrons dès qu'il est prêt.</span></p>
        </div>
      <?php endif; ?>

      <?php foreach ($paragraphs as $para): ?><p class="mt-4 text-ink/75 leading-relaxed"><?= e($para) ?></p><?php endforeach; ?>
      <?php if ($bullets): ?>
        <ul class="mt-4 space-y-1.5 text-[15px] text-ink/80">
          <?php foreach ($bullets as $b): ?><li class="flex gap-2.5"><span class="mt-[9px] w-1.5 h-1.5 rounded-full bg-ink/70 shrink-0"></span><?= e($b) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <form id="add-to-cart" action="<?= e(url('panier.php')) ?>" method="post" class="mt-7 pt-7 border-t border-line" data-ajax-cart data-name="<?= e($product['name']) ?>" data-price="<?= e(money((int) $product['price'])) ?>" data-image="<?= e(isset($images[0]) ? image_url($images[0], 300) : '') ?>" data-bundle="<?= $isGiftBox ? '1' : '0' ?>" data-bundle-stock="<?= $isGiftBox ? (int) $totalStock : 0 ?>" data-stock='<?= e(json_encode($stockMap, JSON_UNESCAPED_UNICODE)) ?>' data-ready-label="<?= $readyDays ? e('Sur commande · prêt le ' . date_fr($readyDate, true) . ' si vous commandez aujourd\'hui') : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">

        <?php if ($isGiftBox && $parentVariant): ?>
          <input type="hidden" name="size" value="<?= e($parentVariant['size']) ?>">
          <input type="hidden" name="color" value="<?= e($parentVariant['color']) ?>">
          <fieldset class="space-y-4">
            <legend class="text-sm font-semibold mb-3">Composez votre coffret</legend>
            <?php foreach ($bundleComponents as $component): ?>
              <div class="space-y-2" data-bundle-component data-component-id="<?= (int) $component['component_id'] ?>" data-component-name="<?= e($component['name']) ?>" data-component-quantity="<?= (int) $component['quantity'] ?>">
                <span class="label"><?= e($component['name']) ?> × <?= (int) $component['quantity'] ?></span>
                <?php if (count($component['size_options']) === 1): ?>
                  <input type="hidden" name="components[<?= (int) $component['component_id'] ?>][size]" value="<?= e($component['size_options'][0]) ?>" data-component-choice="size">
                  <p class="text-sm text-muted">Taille : <?= e($component['size_options'][0]) ?></p>
                <?php elseif (count($component['size_options']) > 1): ?>
                  <fieldset>
                    <legend class="label">Taille</legend>
                    <div class="flex flex-wrap gap-2">
                      <?php foreach ($component['size_options'] as $option): ?>
                        <label class="cursor-pointer">
                          <input type="radio" name="components[<?= (int) $component['component_id'] ?>][size]" value="<?= e($option) ?>" class="sr-only" data-component-choice="size" required>
                          <span class="size-chip"><?= e($option) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                <?php endif; ?>
                <?php if (count($component['color_options']) === 1): $singleColor = $component['color_options'][0]; ?>
                  <input type="hidden" name="components[<?= (int) $component['component_id'] ?>][color]" value="<?= e($singleColor) ?>" data-component-choice="color">
                  <p class="text-sm text-muted flex items-center gap-2"><span class="swatch !w-6 !h-6" style="background:<?= e(color_background($singleColor)) ?>"></span>Couleur : <?= e($singleColor) ?></p>
                <?php elseif (count($component['color_options']) > 1): ?>
                  <fieldset>
                    <legend class="label">Couleur <span class="font-normal text-ink/80" data-component-color-label></span></legend>
                    <div class="flex flex-wrap gap-3">
                      <?php foreach ($component['color_options'] as $option): ?>
                        <label class="cursor-pointer" title="<?= e($option) ?>">
                          <input type="radio" name="components[<?= (int) $component['component_id'] ?>][color]" value="<?= e($option) ?>" class="sr-only" data-component-choice="color" required>
                          <span class="swatch block !w-9 !h-9" style="background:<?= e(color_background($option)) ?>"></span><span class="sr-only"><?= e($option) ?></span>
                        </label>
                      <?php endforeach; ?>
                    </div>
                  </fieldset>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if (!$bundleComponents): ?><p class="text-sm text-terra">La composition de ce coffret n’est pas encore configurée.</p><?php elseif (!$bundleReady): ?><p class="text-sm text-terra">Le stock du coffret n’est pas configuré.</p><?php endif; ?>
          </fieldset>
        <?php endif; ?>

        <?php if (!$isGiftBox && $colors): ?>
          <fieldset>
            <legend class="text-sm font-semibold mb-3">Couleur : <span class="font-normal text-ink/80" data-color-label><?= e($defaultColor ?? '') ?></span></legend>
            <div class="flex flex-wrap gap-3">
              <?php foreach ($colors as $c): $out = ($colorStock[$c] ?? 0) <= 0; ?>
                <label class="cursor-pointer <?= $out ? 'opacity-40' : '' ?>" title="<?= e($c . ($out ? ' — épuisé' : '')) ?>">
                  <input type="radio" name="color" value="<?= e($c) ?>" class="sr-only" required <?= $c === $defaultColor ? 'checked' : '' ?> <?= $out ? 'disabled' : '' ?>>
                  <span class="swatch block !w-10 !h-10" style="background:<?= e(color_background($c)) ?>"></span><span class="sr-only"><?= e($c) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endif; ?>

        <?php if (!$isGiftBox && $sizes): ?>
          <fieldset class="mt-6">
            <div class="flex items-center justify-between mb-3">
              <legend class="text-sm font-semibold">Taille <span class="font-normal text-terra text-xs ml-1 is-hidden" data-size-error>Choisissez une taille</span></legend>
              <a href="<?= e(url('guide-tailles.php')) ?>" class="text-sm text-forest font-medium underline underline-offset-4 inline-flex items-center gap-1.5"><?= icon('ruler', 'w-4 h-4') ?> Guide des tailles</a>
            </div>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($sizes as $s): ?>
                <label><input type="radio" name="size" value="<?= e($s) ?>" class="sr-only" required <?= ($sizeStock[$s] ?? 0) > 0 ? '' : 'disabled' ?>><span class="size-chip"><?= e($s) ?></span></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endif; ?>

        <p class="mt-4 text-sm min-h-5" data-stock-hint></p>

        <div class="mt-3">
          <p class="text-sm font-semibold mb-3">Quantité</p>
          <div class="inline-flex items-center h-12 rounded-lg border border-line bg-white" data-qty>
            <button type="button" class="w-12 h-full flex items-center justify-center hover:text-forest" data-qty-minus aria-label="Diminuer la quantité"><?= icon('minus', 'w-4 h-4') ?></button>
            <input type="number" name="quantity" value="1" min="1" max="99" class="w-12 text-center bg-transparent font-semibold tabular-nums outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none" aria-label="Quantité">
            <button type="button" class="w-12 h-full flex items-center justify-center hover:text-forest" data-qty-plus aria-label="Augmenter la quantité"><?= icon('plus', 'w-4 h-4') ?></button>
          </div>
        </div>

        <div class="mt-7 grid gap-3">
          <button class="btn btn-primary w-full !min-h-[54px] text-base" data-submit <?= $totalStock > 0 ? '' : 'disabled' ?>><?= icon('bag') ?> <span data-submit-label><?= $totalStock > 0 ? 'Ajouter au panier' : 'Épuisé' ?></span></button>
          <a href="<?= e(whatsapp_url('Bonjour, je souhaite commander : ' . $product['name'] . ' (' . money((int) $product['price']) . ($readyText ? ', ' . $readyText : '') . ')')) ?>" target="_blank" rel="noopener" class="btn btn-outline w-full !min-h-[54px] text-base" data-whatsapp data-product="<?= e($product['name']) ?>" data-price="<?= e(money((int) $product['price']) . ($readyText ? ', ' . $readyText : '')) ?>" data-url="<?= e((isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . url('produit.php?id=' . $id)) ?>"><?= icon('whatsapp', 'w-5 h-5 text-[#1DA851]') ?> Commander sur WhatsApp</a>
          <div class="grid grid-cols-2 gap-3">
            <button type="button" class="btn btn-outline w-full" data-product-share data-product-id="<?= (int) $id ?>" data-share-url="<?= e($productUrl) ?>" data-share-title="<?= e($product['name']) ?>" data-share-text="<?= e($product['name'] . ' — ' . money((int) $product['price'])) ?>">Partager</button>
            <a href="<?= e($buyUrl) ?>" class="btn btn-outline w-full" data-buy-link data-product-id="<?= (int) $id ?>">Acheter directement</a>
          </div>
        </div>
      </form>

      <div class="mt-8 pt-7 border-t border-line grid grid-cols-3 gap-3 text-center">
        <?php foreach ([['truck', 'Livraison rapide', 'Dakar et régions'], ['card', 'Paiement sécurisé', 'Wave, Orange Money ou à la livraison'], ['refresh', 'Échange facile', 'Sous 7 jours']] as [$ic, $t, $d]): ?>
          <div><span class="inline-flex text-forest"><?= icon($ic, 'w-8 h-8') ?></span><p class="mt-2 text-[13px] font-semibold"><?= e($t) ?></p><p class="text-xs text-muted mt-0.5 leading-snug"><?= e($d) ?></p></div>
        <?php endforeach; ?>
      </div>

      <div class="mt-8 divide-y divide-line border-y border-line">
        <details class="py-4"><summary class="flex items-center justify-between font-semibold text-sm">Livraison & paiement<span class="chev transition-transform"><?= icon('chevron-down', 'w-4 h-4') ?></span></summary><p class="pt-3 text-sm text-muted leading-relaxed">Livraison en 24 à 48 h à Dakar, 2 à 4 jours dans les régions. Livraison offerte dès <?= money(FREE_DELIVERY_THRESHOLD) ?> d'achat. Payez par Wave, Orange Money ou en espèces à la réception.</p></details>
        <details class="py-4"><summary class="flex items-center justify-between font-semibold text-sm">Échanges<span class="chev transition-transform"><?= icon('chevron-down', 'w-4 h-4') ?></span></summary><p class="pt-3 text-sm text-muted leading-relaxed">La taille ne convient pas ? Contactez-nous sous 7 jours sur WhatsApp : nous organisons l'échange, l'article doit être non porté et dans son état d'origine.</p></details>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
    <section class="mt-20">
      <h2 class="font-display text-3xl mb-7">Vous aimerez aussi</h2>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6">
        <?php foreach ($related as $r) echo product_card($r); ?>
      </div>
    </section>
  <?php endif; ?>
</main>

<!-- Barre d'achat mobile -->
<div class="fixed lg:hidden bottom-0 inset-x-0 z-40 border-t border-line bg-cream/95 backdrop-blur p-3 flex items-center gap-3">
  <div class="min-w-0 flex-1"><p class="text-xs text-muted truncate"><?= e($product['name']) ?></p><p class="font-bold text-terra tabular-nums"><?= money((int) $product['price']) ?></p></div>
  <button type="submit" form="add-to-cart" class="btn btn-primary !min-h-12 shrink-0" <?= $totalStock > 0 ? '' : 'disabled' ?>><?= icon('bag', 'w-4 h-4') ?> <?= $totalStock > 0 ? 'Ajouter' : 'Épuisé' ?></button>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
