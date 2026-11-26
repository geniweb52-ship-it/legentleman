<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/orders.php';
require_once __DIR__ . '/includes/cart.php';

if (!$_SESSION['cart']) redirect('panier.php');

$zones = $pdo->query('SELECT * FROM delivery_zones WHERE active=1 ORDER BY fee, name')->fetchAll();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) { http_response_code(400); exit('Jeton invalide'); }
    $firstName = mb_substr(trim((string) ($_POST['first_name'] ?? '')), 0, 80);
    $lastName = mb_substr(trim((string) ($_POST['last_name'] ?? '')), 0, 80);
    $name = trim($firstName . ' ' . $lastName);
    $phone = preg_replace('/\s+/', ' ', trim((string) ($_POST['phone'] ?? '')));
    $address = mb_substr(trim((string) ($_POST['address'] ?? '')), 0, 255);
    $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 160);
    $zoneId = (int) ($_POST['zone_id'] ?? 0);
    $payment = (string) ($_POST['payment_method'] ?? '');
    // « Commander sur WhatsApp » (fenêtre du panier) : adresse et paiement peuvent être précisés dans la conversation.
    $channel = ($_POST['channel'] ?? '') === 'whatsapp' ? 'whatsapp' : 'site';
    if ($channel === 'whatsapp') {
        if ($address === '') $address = 'À préciser sur WhatsApp';
        if ($payment === '') $payment = 'delivery';
    }
    if ($firstName === '' || $lastName === '') $errors[] = 'Indiquez votre prénom et votre nom.';
    if ($phone === '' || $address === '') $errors[] = 'Téléphone et adresse sont requis.';
    if ($phone !== '' && !preg_match('/^\+?[0-9\s]{8,20}$/', $phone)) $errors[] = 'Numéro de téléphone invalide.';
    if (!in_array($payment, array_column(checkout_payment_options(), 0), true)) $errors[] = 'Choisissez un mode de paiement.';
    $zoneStmt = $pdo->prepare('SELECT * FROM delivery_zones WHERE id=? AND active=1');
    $zoneStmt->execute([$zoneId]);
    $zone = $zoneStmt->fetch();
    if (!$zone) $errors[] = 'Choisissez une zone de livraison.';

    if (!$errors) {
        orders_channel_ensure($pdo);
        if ($payment === 'geniuspay') orders_geniuspay_ensure($pdo);
        try {
            $pdo->beginTransaction();
            $subtotal = 0;
            $lines = [];
            $stmt = $pdo->prepare('SELECT v.id,v.stock,v.size,v.color,p.id product_id,p.name,p.price,p.ready_days,p.is_gift_box,c.slug category_slug FROM variants v JOIN products p ON p.id=v.product_id LEFT JOIN categories c ON c.id=p.category_id WHERE v.id=? AND p.active=1 FOR UPDATE');
            $reservedStocks = [];
            foreach ($_SESSION['cart'] as $cartKey => $item) {
                $variantId = cart_item_variant_id((string) $cartKey, $item);
                $stmt->execute([$variantId]);
                $variant = $stmt->fetch();
                if (!$variant) throw new RuntimeException('Un produit de votre panier n\'est plus disponible.');
                $quantity = (int) $item['quantity'];
                $components = $item['components'] ?? [];
                if (is_gift_box_product($variant)) {
                    if ($variant['size'] !== 'Unique' || $variant['color'] !== 'Coffret') {
                        throw new RuntimeException('Le stock global du coffret a changé. Veuillez le retirer puis le réajouter.');
                    }
                    if (!$components) throw new RuntimeException('Veuillez retirer puis recomposer ce coffret dans votre panier.');
                    $submittedComponents = [];
                    foreach ($components as $component) {
                        $submittedComponents[(int) $component['component_id']] = [
                            'size' => (string) $component['size'],
                            'color' => (string) $component['color'],
                        ];
                    }
                    $components = resolve_gift_box_selection($pdo, (int) $variant['product_id'], $submittedComponents);
                    $reserved = (int) ($reservedStocks[$variantId] ?? 0);
                    if ((int) $variant['stock'] - $reserved < $quantity) {
                        throw new RuntimeException('Stock insuffisant pour le coffret « ' . $variant['name'] . ' » (' . max(0, (int) $variant['stock'] - $reserved) . ' disponible(s)).');
                    }
                    $reservedStocks[$variantId] = $reserved + $quantity;
                } else {
                    if ($components) throw new RuntimeException('La composition de votre panier n’est plus valide. Veuillez retirer puis réajouter le coffret.');
                    $reserved = (int) ($reservedStocks[$variantId] ?? 0);
                    if ((int) $variant['stock'] - $reserved < $quantity) {
                        throw new RuntimeException('Stock insuffisant pour « ' . $variant['name'] . ' » (' . max(0, (int) $variant['stock'] - $reserved) . ' disponible(s)).');
                    }
                    $reservedStocks[$variantId] = $reserved + $quantity;
                }
                $subtotal += (int) $variant['price'] * $quantity;
                // Article sur commande : la date « prêt le » est fixée à partir du jour de la commande.
                $lineReadyDays = product_ready_days($variant['ready_days'] ?? null);
                $lines[] = ['variant_id' => $variantId, 'name' => $variant['name'], 'size' => $variant['size'], 'color' => $variant['color'], 'price' => (int) $variant['price'], 'quantity' => $quantity, 'components' => $components, 'ready_date' => $lineReadyDays ? ready_date_from_days($lineReadyDays) : null];
            }
            $deliveryFee = $subtotal >= FREE_DELIVERY_THRESHOLD ? 0 : (int) $zone['fee'];
            $total = $subtotal + $deliveryFee;
            $reference = 'LG-' . strtoupper(bin2hex(random_bytes(4)));

            // Réutilise la fiche client existante si le numéro est déjà connu.
            $find = $pdo->prepare('SELECT id FROM customers WHERE REPLACE(phone," ","")=? LIMIT 1');
            $find->execute([str_replace(' ', '', $phone)]);
            $customerId = (int) $find->fetchColumn();
            if ($customerId) {
                $pdo->prepare('UPDATE customers SET name=?, address=? WHERE id=?')->execute([$name, $address, $customerId]);
            } else {
                $pdo->prepare('INSERT INTO customers (name,phone,address) VALUES (?,?,?)')->execute([$name, $phone, $address]);
                $customerId = (int) $pdo->lastInsertId();
            }
            $campaignToken = (string) ($_COOKIE['lg_qr_campaign'] ?? '');
            $campaignStmt = $pdo->prepare('SELECT id FROM qr_campaigns WHERE token=?');
            $campaignStmt->execute([preg_match('/^[a-f0-9]{32}$/', $campaignToken) ? $campaignToken : '']);
            $campaignId = $campaignStmt->fetchColumn() ?: null;
            $orderStmt = $pdo->prepare('INSERT INTO orders (reference,customer_id,delivery_zone_id,campaign_id,customer_name,first_name,last_name,phone,address,note,payment_method,channel,subtotal,delivery_fee,total,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"nouvelle")');
            $orderStmt->execute([$reference, $customerId, $zoneId, $campaignId, $name, $firstName, $lastName, $phone, $address, $note ?: null, $payment, $channel, $subtotal, $deliveryFee, $total]);
            $orderId = (int) $pdo->lastInsertId();
            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id,variant_id,product_name,size,color,price,quantity,ready_date) VALUES (?,?,?,?,?,?,?,?)');
            $componentInsert = $pdo->prepare('INSERT INTO order_item_components (order_item_id,variant_id,product_name,size,color,quantity_per_bundle) VALUES (?,?,?,?,?,?)');
            foreach ($lines as $line) {
                $itemStmt->execute([$orderId, $line['variant_id'], $line['name'], $line['size'], $line['color'], $line['price'], $line['quantity'], $line['ready_date']]);
                $orderItemId = (int) $pdo->lastInsertId();
                foreach ($line['components'] as $component) {
                    $componentInsert->execute([$orderItemId, null, $component['product_name'], $component['size'], $component['color'], $component['quantity_per_bundle']]);
                }
            }
            $stockStmt = $pdo->prepare('UPDATE variants SET stock=stock-? WHERE id=? AND stock>=?');
            foreach ($reservedStocks as $stockVariantId => $reservedQuantity) {
                $stockStmt->execute([$reservedQuantity, $stockVariantId, $reservedQuantity]);
                if ($stockStmt->rowCount() !== 1) throw new RuntimeException('Le stock a changé pendant la validation. Veuillez réessayer.');
            }
            $pdo->commit();
            $_SESSION['cart'] = [];
            $_SESSION['last_order'] = $reference;
            $_SESSION['orders'][] = $reference;
            $_SESSION['orders'] = array_slice(array_values(array_unique($_SESSION['orders'])), -20);
            // Paiement en ligne : direction la page de paiement GeniusPay, retour automatique sur merci.php.
            if ($payment === 'geniuspay' && $channel === 'site') redirect(geniuspay_pay_link($reference));
            redirect(order_page_url($reference, $channel === 'whatsapp' ? ['wa' => 1] : []));
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $exception->getMessage();
        }
    }
}

$items = [];
$stmt = $pdo->prepare('SELECT p.name, p.price, p.image, p.ready_days FROM variants v JOIN products p ON p.id=v.product_id WHERE v.id=?');
foreach ($_SESSION['cart'] as $variantId => $item) {
    $stmt->execute([cart_item_variant_id((string) $variantId, $item)]);
    if ($row = $stmt->fetch()) $items[] = $row + $item;
}
$subtotal = cart_total($pdo);
$isFree = $subtotal >= FREE_DELIVERY_THRESHOLD;
$selectedZone = (int) ($_POST['zone_id'] ?? 0);
$selectedPayment = (string) ($_POST['payment_method'] ?? '');

$pageTitle = 'Finaliser la commande';
$pageNoindex = true;
require __DIR__ . '/includes/header.php';
?>
<main class="max-w-site mx-auto px-4 sm:px-6 pt-6">
  <?= breadcrumb([['Panier', url('panier.php')], ['Commande', null]]) ?>
  <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
    <h1 class="font-display text-4xl sm:text-5xl">Finaliser la commande</h1>
    <ol class="flex items-center gap-2 text-xs font-medium text-muted">
      <li class="flex items-center gap-2 text-forest"><span class="w-6 h-6 rounded-full bg-forest text-white flex items-center justify-center"><?= icon('check', 'w-3.5 h-3.5') ?></span> Panier</li>
      <li class="w-8 h-px bg-line"></li>
      <li class="flex items-center gap-2 text-ink"><span class="w-6 h-6 rounded-full bg-ink text-white flex items-center justify-center">2</span> Livraison & paiement</li>
      <li class="w-8 h-px bg-line"></li>
      <li class="flex items-center gap-2"><span class="w-6 h-6 rounded-full border border-line flex items-center justify-center">3</span> Confirmation</li>
    </ol>
  </div>

  <?php if ($errors): ?>
    <div class="mt-6 rounded-xl border border-terra/30 bg-terra/5 p-4 text-sm text-terra" role="alert">
      <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="mt-8 grid lg:grid-cols-[1fr_400px] gap-8 items-start" data-checkout data-subtotal="<?= $subtotal ?>" data-free="<?= FREE_DELIVERY_THRESHOLD ?>">
    <?= csrf_field() ?>
    <?php if (($_POST['channel'] ?? '') === 'whatsapp'): ?><input type="hidden" name="channel" value="whatsapp"><?php endif; ?>
    <div class="space-y-6">
      <section class="rounded-2xl bg-white border border-line p-5 sm:p-7">
        <h2 class="font-display text-2xl flex items-center gap-3"><span class="text-forest"><?= icon('map-pin', 'w-6 h-6') ?></span> Livraison</h2>
        <div class="mt-6 grid sm:grid-cols-2 gap-4">
          <label class="block"><span class="label">Prénom(s)</span><input name="first_name" required autocomplete="given-name" value="<?= e($_POST['first_name'] ?? '') ?>" class="field" placeholder="Ex. Moussa"></label>
          <label class="block"><span class="label">Nom</span><input name="last_name" required autocomplete="family-name" value="<?= e($_POST['last_name'] ?? '') ?>" class="field" placeholder="Ex. Diop"></label>
          <label class="block sm:col-span-2"><span class="label">Téléphone (WhatsApp)</span><input name="phone" required autocomplete="tel" inputmode="tel" value="<?= e($_POST['phone'] ?? '') ?>" class="field" placeholder="77 123 45 67"></label>
          <label class="block sm:col-span-2"><span class="label">Zone de livraison</span>
            <span class="relative block">
              <select name="zone_id" required class="field appearance-none pr-10" data-zone>
                <option value="">Choisir une zone</option>
                <?php foreach ($zones as $zone): ?><option value="<?= (int) $zone['id'] ?>" data-fee="<?= (int) $zone['fee'] ?>" <?= $selectedZone === (int) $zone['id'] ? 'selected' : '' ?>><?= e($zone['name']) ?> — <?= $isFree ? 'Offerte' : money((int) $zone['fee']) ?></option><?php endforeach; ?>
              </select>
              <span class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"><?= icon('chevron-down', 'w-4 h-4') ?></span>
            </span>
          </label>
          <label class="block sm:col-span-2"><span class="label">Adresse précise</span><input name="address" required autocomplete="street-address" value="<?= e($_POST['address'] ?? '') ?>" class="field" placeholder="Quartier, rue, repère…"></label>
          <label class="block sm:col-span-2"><span class="label">Instructions <span class="font-normal text-muted">(facultatif)</span></span><input name="note" maxlength="160" value="<?= e($_POST['note'] ?? '') ?>" class="field" placeholder="Ex. livrer après 18 h, appeler avant"></label>
        </div>
      </section>

      <section class="rounded-2xl bg-white border border-line p-5 sm:p-7">
        <h2 class="font-display text-2xl flex items-center gap-3"><span class="text-forest"><?= icon('card', 'w-6 h-6') ?></span> Paiement</h2>
        <?php $paymentOptions = checkout_payment_options(); ?>
        <div class="mt-6 grid <?= count($paymentOptions) === 2 ? 'sm:grid-cols-2' : 'sm:grid-cols-3' ?> gap-3">
          <?php foreach ($paymentOptions as [$value, $label, $hint, $dot]): ?>
            <label class="relative cursor-pointer">
              <input type="radio" name="payment_method" value="<?= $value ?>" required class="peer sr-only" <?= $selectedPayment === $value ? 'checked' : '' ?>>
              <span class="block h-full rounded-xl border border-line p-4 transition peer-checked:border-forest peer-checked:ring-1 peer-checked:ring-forest peer-checked:bg-forest/[.03] hover:border-ink/40 peer-focus-visible:ring-2 peer-focus-visible:ring-forest">
                <span class="flex items-center gap-2 font-semibold"><span class="w-2.5 h-2.5 rounded-full" style="background:<?= $dot ?>"></span><?= e($label) ?></span>
                <span class="block text-xs text-muted mt-1.5"><?= e($hint) ?></span>
              </span>
              <span class="absolute top-3 right-3 w-5 h-5 rounded-full bg-forest text-white items-center justify-center hidden peer-checked:flex"><?= icon('check', 'w-3 h-3') ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>
    </div>

    <aside class="lg:sticky lg:top-24 rounded-2xl bg-white border border-line p-6">
      <h2 class="font-display text-2xl">Votre commande</h2>
      <ul class="mt-5 space-y-4 max-h-[320px] overflow-y-auto pr-1">
        <?php foreach ($items as $item): ?>
          <li class="flex gap-3">
            <div class="relative w-16 aspect-[4/5] rounded-lg overflow-hidden bg-[#EDE7DC] shrink-0">
              <?php if ($item['image']): ?><img src="<?= e(image_url($item['image'], 400)) ?>" alt="" class="w-full h-full object-cover"><?php else: ?><?= placeholder() ?><?php endif; ?>
              <span class="absolute -top-0 -right-0 min-w-5 h-5 px-1 rounded-bl-lg bg-ink text-white text-[11px] font-bold flex items-center justify-center"><?= (int) $item['quantity'] ?></span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium truncate"><?= e($item['name']) ?></p>
              <?php if (!empty($item['components'])): ?>
                <?php foreach ($item['components'] as $component): $componentOptions = array_filter([$component['size'], $component['color']]); ?>
                  <p class="text-xs text-muted mt-0.5"><?= e($component['product_name']) ?><?= $componentOptions ? ' — ' . e(implode(' · ', $componentOptions)) : '' ?> × <?= (int) $component['quantity_per_bundle'] ?></p>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-xs text-muted mt-0.5"><?= e($item['color']) ?> · <?= e($item['size']) ?></p>
              <?php endif; ?>
              <?php if ($itemReadyDays = product_ready_days($item['ready_days'] ?? null)): ?>
                <p class="text-xs font-semibold text-forest mt-0.5">Sur commande · prêt le <?= e(date_fr(ready_date_from_days($itemReadyDays))) ?></p>
              <?php endif; ?>
            </div>
            <p class="text-sm font-semibold tabular-nums whitespace-nowrap"><?= money((int) $item['price'] * (int) $item['quantity']) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>
      <dl class="mt-6 pt-5 border-t border-line space-y-3 text-sm">
        <div class="flex justify-between"><dt class="text-muted">Sous-total</dt><dd class="font-semibold tabular-nums"><?= money($subtotal) ?></dd></div>
        <div class="flex justify-between"><dt class="text-muted">Livraison</dt><dd class="tabular-nums" data-fee-label><?= $isFree ? '<span class="text-forest font-semibold">Offerte</span>' : 'Choisissez une zone' ?></dd></div>
      </dl>
      <div class="mt-5 pt-5 border-t border-line flex justify-between items-baseline"><span class="font-semibold">Total</span><span class="text-2xl font-bold tabular-nums" data-total-label><?= money($subtotal) ?></span></div>
      <button class="btn btn-primary w-full mt-6 !min-h-[54px] text-base">Valider la commande</button>
      <p class="mt-3 text-xs text-muted text-center leading-relaxed">En validant, vous acceptez nos <a href="<?= e(url('cgv.php')) ?>" target="_blank" class="underline underline-offset-2 hover:text-ink">conditions générales de vente</a>.</p>
      <p class="mt-3 text-xs text-muted text-center leading-relaxed">Après validation, votre récapitulatif complet est prêt à envoyer sur WhatsApp et votre facture est disponible.</p>
      <p class="mt-4 text-xs text-muted text-center flex items-center justify-center gap-2"><?= icon('shield', 'w-4 h-4 text-forest') ?> Vos informations servent uniquement à la livraison.</p>
    </aside>
  </form>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
