<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/orders.php';

$ref = strtoupper(trim((string) ($_GET['ref'] ?? $_SESSION['last_order'] ?? '')));
$token = (string) ($_GET['t'] ?? '');
if ($ref === '') redirect('index.php');
$order = find_order($pdo, $ref);
$allowed = $order && (order_token_valid($ref, $token) || in_array($ref, $_SESSION['orders'] ?? [], true));
if (!$allowed) redirect('suivi.php');

// Retour depuis Wave / GeniusPay : on vérifie le paiement directement auprès du prestataire (jamais sur la seule foi de l'URL).
$waveReturn = (string) ($_GET['wave'] ?? '');
$gpReturn = (string) ($_GET['gp'] ?? '');
$order = order_sync_payment($pdo, $order);
$items = order_items($pdo, (int) $order['id']);
$paid = order_is_paid($order);
$isWave = $order['payment_method'] === 'wave';
$isOrange = $order['payment_method'] === 'orange';
$isGeniuspay = $order['payment_method'] === 'geniuspay';
$canPayOnline = $isGeniuspay && !$paid && $order['status'] !== 'annulee' && geniuspay_enabled();
$waUrl = whatsapp_url(order_whatsapp_message($order, $items));
$autoWhatsapp = isset($_GET['wa']) && ($order['channel'] ?? '') === 'whatsapp';

$pageTitle = $paid ? 'Paiement reçu' : 'Commande enregistrée';
$pageNoindex = true;
require __DIR__ . '/includes/header.php';
?>
<main class="max-w-3xl mx-auto px-4 pt-12">
  <div class="text-center fade-up">
    <span class="mx-auto w-16 h-16 rounded-full bg-forest text-white flex items-center justify-center shadow-[0_12px_30px_-10px_rgba(46,74,54,.8)]"><?= icon('check', 'w-8 h-8') ?></span>
    <h1 class="font-display text-4xl sm:text-5xl mt-6"><?= $paid ? 'Paiement reçu, merci !' : 'Merci ' . e($order['first_name'] ?: $order['customer_name']) . ' !' ?></h1>
    <p class="mt-3 text-muted">Commande <strong class="text-ink tracking-wide"><?= e($order['reference']) ?></strong> · <?= money((int) $order['total']) ?></p>
  </div>

  <?php if ($waveReturn === 'error' && !$paid): ?>
    <div class="mt-8 rounded-xl border border-terra/30 bg-terra/5 p-4 text-sm text-terra" role="alert">Le paiement Wave n'a pas abouti. Votre commande est conservée : contactez-nous sur WhatsApp pour obtenir un nouveau lien.</div>
  <?php elseif ($waveReturn === 'success' && !$paid): ?>
    <div class="mt-8 rounded-xl border border-line bg-white p-4 text-sm" role="status">Nous attendons la confirmation de Wave. Actualisez cette page dans quelques secondes.</div>
  <?php elseif (in_array($gpReturn, ['error', 'unavailable'], true) && !$paid): ?>
    <div class="mt-8 rounded-xl border border-terra/30 bg-terra/5 p-4 text-sm text-terra" role="alert"><?= $gpReturn === 'error' ? 'Le paiement n’a pas abouti.' : 'Le service de paiement en ligne est momentanément indisponible.' ?> Votre commande est conservée : vous pouvez réessayer ci-dessous ou nous contacter sur WhatsApp.</div>
  <?php elseif ($gpReturn === 'success' && !$paid): ?>
    <div class="mt-8 rounded-xl border border-line bg-white p-4 text-sm" role="status">Nous attendons la confirmation de votre paiement. Actualisez cette page dans quelques secondes.</div>
  <?php endif; ?>

  <?php if ($canPayOnline): ?>
    <div class="mt-8 rounded-2xl bg-white border-2 border-forest p-6 text-center">
      <p class="font-semibold text-lg">Réglez votre commande en ligne</p>
      <p class="text-sm text-muted mt-1">Wave, Orange Money ou carte bancaire · <?= money((int) $order['total']) ?></p>
      <a href="<?= e(geniuspay_pay_link($ref)) ?>" class="btn btn-primary w-full mt-5 !min-h-[54px] text-base"><?= icon('card') ?> Payer maintenant</a>
    </div>
  <?php endif; ?>

  <?php if ($orderReadyDate = order_ready_date($items)): ?>
    <div class="mt-8 rounded-xl border border-forest/25 bg-forest/5 p-4 text-sm flex gap-3" role="status">
      <span class="text-forest shrink-0"><?= icon('calendar', 'w-5 h-5') ?></span>
      <p><strong class="text-forest">Votre commande contient des articles sur commande.</strong><br>Elle sera prête le <strong><?= e(date_fr($orderReadyDate, true)) ?></strong> ; nous vous contacterons sur WhatsApp pour organiser la livraison.</p>
    </div>
  <?php endif; ?>

  <ol class="mt-10 space-y-4 fade-up" style="animation-delay:.1s">
    <?php if ($paid): ?>
      <li class="rounded-2xl bg-forest/5 border border-forest/30 p-6 flex items-center gap-4">
        <span class="w-9 h-9 rounded-full bg-forest text-white flex items-center justify-center shrink-0"><?= icon('check', 'w-5 h-5') ?></span>
        <div><p class="font-semibold">Paiement confirmé par <?= e(payment_label((string) $order['payment_method'])) ?></p><p class="text-sm text-muted">Le <?= e(date('d/m/Y à H:i', strtotime((string) $order['paid_at']))) ?><?= $order['wave_transaction_id'] ? ' · Transaction ' . e($order['wave_transaction_id']) : '' ?></p></div>
      </li>
    <?php endif; ?>

    <li class="rounded-2xl bg-white border-2 border-[#25D366] p-6">
      <div class="flex items-start gap-4">
        <span class="w-9 h-9 rounded-full bg-[#25D366] text-white flex items-center justify-center shrink-0"><?= icon('whatsapp', 'w-5 h-5') ?></span>
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-lg">Envoyez votre commande sur WhatsApp</p>
          <p class="text-sm text-muted mt-1">Le message est déjà rempli : prénom, nom, téléphone, adresse, articles, total et lien de la facture. Il ne reste qu'à appuyer sur « Envoyer ».</p>
          <?php if (!$paid && $isGeniuspay): ?>
            <p class="text-sm mt-3">Vous avez choisi le paiement en ligne : dès qu’il est effectué, votre commande est confirmée automatiquement.</p>
          <?php elseif (!$paid && $isWave): ?>
            <p class="text-sm mt-3">Après l’envoi de votre commande, nous vous transmettrons le lien de paiement Wave dans cette conversation. Effectuez le paiement, puis envoyez-nous une capture du reçu.</p>
          <?php elseif (!$paid && $isOrange): ?>
            <p class="text-sm mt-3">Nous vous enverrons les instructions de paiement Orange Money dans cette conversation. Après le règlement, envoyez-nous une capture du reçu.</p>
          <?php elseif (!$paid): ?>
            <p class="text-sm mt-3">Vous avez choisi le paiement à la livraison : le montant sera à régler au livreur à la réception.</p>
          <?php endif; ?>
          <details class="mt-3 text-sm"><summary class="text-forest font-medium underline underline-offset-4">Voir le message</summary><pre class="mt-3 whitespace-pre-wrap font-sans text-[13px] leading-relaxed bg-cream rounded-lg p-4 border border-line"><?= e(order_whatsapp_message($order, $items)) ?></pre></details>
          <?php if ($autoWhatsapp): ?><p class="text-sm mt-3 font-medium text-[#1DA851]">Ouverture de WhatsApp… Si rien ne s'ouvre, appuyez sur le bouton ci-dessous.</p><script>(function () { var k = 'wa-sent-<?= e($order['reference']) ?>'; try { if (sessionStorage.getItem(k)) return; sessionStorage.setItem(k, '1'); } catch (e) {} setTimeout(function () { window.location.href = <?= json_encode($waUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>; }, 700); })();</script><?php endif; ?>
          <a href="<?= e($waUrl) ?>" target="_blank" rel="noopener" class="btn w-full mt-5 !min-h-[54px] text-base text-white bg-[#25D366] hover:bg-[#1fb457]"><?= icon('whatsapp') ?> Envoyer ma commande sur WhatsApp</a>
        </div>
      </div>
    </li>

    <li class="rounded-2xl bg-white border border-line p-6 flex flex-col sm:flex-row sm:items-center gap-4">
      <span class="w-9 h-9 rounded-full bg-cream border border-line flex items-center justify-center shrink-0 text-forest"><?= icon('card', 'w-5 h-5') ?></span>
      <div class="flex-1"><p class="font-semibold">Votre facture</p><p class="text-sm text-muted">Consultez-la, imprimez-la ou enregistrez-la en PDF.</p></div>
      <a href="<?= e(invoice_url($ref, false)) ?>" target="_blank" class="btn btn-outline !min-h-11">Voir ma facture</a>
    </li>
  </ol>

  <div class="mt-8 flex flex-wrap justify-center gap-6 text-sm font-semibold">
    <a href="<?= e(url('suivi.php?ref=' . rawurlencode($ref))) ?>" class="inline-flex items-center gap-2 hover:text-forest"><?= icon('package', 'w-4 h-4') ?> Suivre ma commande</a>
    <a href="<?= e(url('boutique.php')) ?>" class="inline-flex items-center gap-2 hover:text-forest">Continuer mes achats <?= icon('arrow-right', 'w-4 h-4') ?></a>
  </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
