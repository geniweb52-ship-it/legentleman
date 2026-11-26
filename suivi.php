<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/orders.php';

$ref = strtoupper(trim((string) ($_GET['ref'] ?? $_POST['ref'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$order = null;
$error = '';
$known = in_array($ref, $_SESSION['orders'] ?? [], true);

if ($ref !== '' && ($known || $_SERVER['REQUEST_METHOD'] === 'POST')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) { http_response_code(400); exit('Jeton invalide'); }
    $stmt = $pdo->prepare('SELECT o.*, dz.name zone_name FROM orders o LEFT JOIN delivery_zones dz ON dz.id=o.delivery_zone_id WHERE o.reference=?');
    $stmt->execute([$ref]);
    $found = $stmt->fetch();
    $digits = fn(string $s) => substr(preg_replace('/\D/', '', $s), -8);
    if ($found && ($known || ($phone !== '' && $digits($phone) === $digits((string) $found['phone'])))) {
        $order = $found;
        $order = order_sync_payment($pdo, $order);
        $_SESSION['orders'][] = $ref;
        $_SESSION['orders'] = array_values(array_unique($_SESSION['orders']));
        $items = order_items($pdo, (int) $order['id']);
    } else {
        $error = 'Aucune commande ne correspond à cette référence et ce numéro.';
    }
}

$steps = ['nouvelle' => 'Commande reçue', 'payee' => 'Paiement confirmé', 'en_preparation' => 'En préparation', 'expediee' => 'En livraison', 'livree' => 'Livrée'];
$pageTitle = 'Suivre ma commande';
$pageNoindex = true;
require __DIR__ . '/includes/header.php';
?>
<main class="max-w-3xl mx-auto px-4 sm:px-6 pt-6">
  <?= breadcrumb([['Suivre ma commande', null]]) ?>
  <h1 class="font-display text-4xl sm:text-5xl mt-6">Suivre ma commande</h1>
  <p class="mt-3 text-muted">Saisissez la référence reçue après votre achat (ex. LG-1A2B3C4D) et votre numéro de téléphone.</p>

  <?php if (!$order): ?>
    <form method="post" class="mt-8 rounded-2xl bg-white border border-line p-6 grid sm:grid-cols-[1fr_1fr_auto] gap-4 items-end">
      <?= csrf_field() ?>
      <label class="block"><span class="label">Référence</span><input name="ref" required value="<?= e($ref) ?>" class="field uppercase" placeholder="LG-XXXXXXXX"></label>
      <label class="block"><span class="label">Téléphone</span><input name="phone" required inputmode="tel" value="<?= e($phone) ?>" class="field" placeholder="77 123 45 67"></label>
      <button class="btn btn-primary">Rechercher</button>
      <?php if ($error): ?><p class="sm:col-span-3 text-sm text-terra"><?= e($error) ?></p><?php endif; ?>
    </form>
  <?php else:
    $cancelled = $order['status'] === 'annulee';
    $paid = order_is_paid($order);
    $isCashOnDelivery = $order['payment_method'] === 'delivery';
    // Paiement à la livraison : pas d'étape « Paiement confirmé » avant l'expédition, le client paie en recevant.
    $visibleSteps = $isCashOnDelivery ? array_diff_key($steps, ['payee' => true]) : $steps;
    $statusRank = array_search($order['status'], array_keys($steps), true);
    $stepDone = [];
    foreach (array_keys($visibleSteps) as $key) {
        // L'étape paiement n'est validée que si le paiement est réellement enregistré.
        $stepDone[$key] = $key === 'payee' ? $paid : ($statusRank !== false && array_search($key, array_keys($steps), true) <= $statusRank);
    }
    $lastDone = 0;
    foreach (array_values($stepDone) as $position => $isDone) if ($isDone) $lastDone = $position;
    $stepCount = count($visibleSteps); ?>
    <div class="mt-8 rounded-2xl bg-white border border-line p-6 sm:p-8">
      <div class="flex flex-wrap justify-between gap-4">
        <div><p class="text-sm text-muted">Commande</p><p class="text-xl font-bold tracking-wide"><?= e($order['reference']) ?></p><p class="text-sm text-muted mt-1">Passée le <?= e(date('d/m/Y à H:i', strtotime((string) $order['created_at']))) ?></p></div>
        <div class="text-right"><p class="text-sm text-muted">Total</p><p class="text-xl font-bold text-terra tabular-nums"><?= money((int) $order['total']) ?></p></div>
      </div>

      <?php if ($cancelled): ?>
        <p class="mt-8 rounded-xl bg-terra/10 text-terra p-4 text-sm font-medium">Cette commande a été annulée. Contactez-nous sur WhatsApp pour toute question.</p>
      <?php else: ?>
        <ol class="mt-10 grid relative" style="grid-template-columns:repeat(<?= $stepCount ?>,minmax(0,1fr))">
          <div class="absolute top-4 h-0.5 bg-line" style="left:<?= round(50 / $stepCount, 2) ?>%;right:<?= round(50 / $stepCount, 2) ?>%"><div class="h-full bg-forest transition-all duration-700" style="width:<?= (int) round($lastDone / max(1, $stepCount - 1) * 100) ?>%"></div></div>
          <?php $i = 0; foreach ($visibleSteps as $key => $label): $done = $stepDone[$key]; ?>
            <li class="relative flex flex-col items-center text-center">
              <span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?= $done ? 'bg-forest text-white' : 'bg-white border-2 border-line text-muted' ?>"><?= $done ? icon('check', 'w-4 h-4') : $i + 1 ?></span>
              <span class="mt-2 text-[11px] sm:text-xs <?= $done ? 'font-semibold' : 'text-muted' ?>"><?= e($label) ?></span>
            </li>
          <?php $i++; endforeach; ?>
        </ol>
        <?php
          $paymentState = $paid ? 'Paiement reçu' : ($order['payment_method'] === 'delivery' ? 'À régler à la livraison' : 'En attente de paiement');
        ?>
        <div class="mt-8 rounded-xl border <?= $paid ? 'border-forest/30 bg-forest/5' : 'border-line bg-cream/60' ?> p-4 sm:p-5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <p class="text-sm text-muted">Paiement · <?= e(payment_label((string) $order['payment_method'])) ?></p>
              <p class="mt-1 font-semibold <?= $paid ? 'text-forest' : 'text-ink' ?>"><?= e($paymentState) ?></p>
              <?php if ($paid && $order['paid_at']): ?>
                <p class="mt-1 text-xs text-muted">Confirmé le <?= e(date('d/m/Y à H:i', strtotime((string) $order['paid_at']))) ?></p>
              <?php elseif ($order['payment_method'] === 'geniuspay'): ?>
                <p class="mt-1 text-sm text-muted">Paiement en ligne : Wave, Orange Money ou carte bancaire.</p>
                <?php if ($order['status'] !== 'annulee' && geniuspay_enabled()): ?><a href="<?= e(geniuspay_pay_link((string) $order['reference'])) ?>" class="btn btn-primary mt-3 !min-h-11"><?= icon('card', 'w-4 h-4') ?> Payer maintenant</a><?php endif; ?>
              <?php elseif ($order['payment_method'] === 'wave'): ?>
                <p class="mt-1 text-sm text-muted"><?= $order['status'] === 'nouvelle' ? 'Envoyez d’abord votre commande sur WhatsApp : nous vous transmettrons le lien Wave dans cette conversation.' : 'Utilisez le lien Wave que nous vous avons transmis sur WhatsApp.' ?> Après paiement, envoyez une capture du reçu.</p>
              <?php elseif ($order['payment_method'] === 'orange'): ?>
                <p class="mt-1 text-sm text-muted"><?= $order['status'] === 'nouvelle' ? 'Nous vous enverrons les instructions Orange Money sur WhatsApp.' : 'Suivez les instructions Orange Money que nous vous avons transmises sur WhatsApp.' ?> Après paiement, envoyez une capture du reçu.</p>
              <?php else: ?>
                <p class="mt-1 text-sm text-muted">Le montant sera à régler au livreur à la réception.</p>
              <?php endif; ?>
            </div>
            <p class="font-bold tabular-nums"><?= money((int) $order['total']) ?></p>
          </div>
          <?php if ($order['status'] === 'nouvelle'): ?>
            <a href="<?= e(whatsapp_url(order_whatsapp_message($order, $items))) ?>" target="_blank" rel="noopener" class="btn btn-outline mt-4 !min-h-11"><?= icon('whatsapp', 'w-5 h-5 text-[#1DA851]') ?> Envoyer ma commande sur WhatsApp</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <ul class="mt-10 pt-6 border-t border-line space-y-2 text-sm">
        <?php foreach ($items as $it): ?>
          <li class="flex justify-between gap-4">
            <span>
              <?= e($it['product_name']) ?>
              <?php if (!empty($it['components'])): ?>
                <?php foreach ($it['components'] as $component): $componentOptions = array_filter([$component['size'], $component['color']]); ?>
                  <span class="block text-muted"><?= e($component['product_name']) ?><?= $componentOptions ? ' — ' . e(implode(' · ', $componentOptions)) : '' ?> × <?= (int) $component['quantity_per_bundle'] ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="text-muted">· <?= e($it['size']) ?>, <?= e($it['color']) ?> × <?= (int) $it['quantity'] ?></span>
              <?php endif; ?>
              <?php if ($readyText = order_item_ready_text($it)): ?><span class="block text-xs font-semibold text-forest"><?= e($readyText) ?></span><?php endif; ?>
            </span>
            <span class="tabular-nums"><?= money((int) $it['price'] * (int) $it['quantity']) ?></span>
          </li>
        <?php endforeach; ?>
        <li class="flex justify-between gap-4 text-muted"><span>Livraison · <?= e($order['zone_name'] ?? '') ?></span><span class="tabular-nums"><?= (int) $order['delivery_fee'] ? money((int) $order['delivery_fee']) : 'Offerte' ?></span></li>
      </ul>
      <a href="<?= e(whatsapp_url('Bonjour, je souhaite des nouvelles de ma commande ' . $order['reference'] . '.')) ?>" target="_blank" rel="noopener" class="btn btn-outline mt-8"><?= icon('whatsapp', 'w-5 h-5 text-[#1DA851]') ?> Une question sur cette commande ?</a>
    </div>
    <a href="<?= e(url('suivi.php')) ?>" class="mt-6 inline-block text-sm underline underline-offset-4 text-muted hover:text-ink">Suivre une autre commande</a>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
