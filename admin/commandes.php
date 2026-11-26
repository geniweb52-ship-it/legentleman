<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once dirname(__DIR__) . '/includes/orders.php';
orders_channel_ensure($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $ref = (string) ($_POST['ref'] ?? '');
    $status = (string) ($_POST['status'] ?? '');
    $order = find_order($pdo, $ref);
    if ($order && isset(ORDER_STATUSES[$status]) && $status !== $order['status']) {
        $pdo->beginTransaction();
        // Annulation : on remet les articles en stock (et on les retire si la commande est réactivée).
        $sign = null;
        if ($status === 'annulee') $sign = '+';
        elseif ($order['status'] === 'annulee') $sign = '-';
        if ($sign) {
            if ($sign === '-') {
                $required = $pdo->prepare(
                    'SELECT variant_id, SUM(quantity) quantity FROM (
                       SELECT oi.variant_id, oi.quantity FROM order_items oi
                       WHERE oi.order_id=? AND NOT EXISTS (SELECT 1 FROM order_item_components oic WHERE oic.order_item_id=oi.id AND oic.variant_id IS NOT NULL)
                       UNION ALL
                       SELECT oic.variant_id, oic.quantity_per_bundle*oi.quantity FROM order_item_components oic
                       JOIN order_items oi ON oi.id=oic.order_item_id WHERE oi.order_id=? AND oic.variant_id IS NOT NULL
                     ) stock_requirements WHERE variant_id IS NOT NULL GROUP BY variant_id'
                );
                $required->execute([(int) $order['id'], (int) $order['id']]);
                $lockStock = $pdo->prepare('SELECT stock FROM variants WHERE id=? FOR UPDATE');
                foreach ($required->fetchAll() as $row) {
                    $lockStock->execute([(int) $row['variant_id']]);
                    if ((int) $lockStock->fetchColumn() < (int) $row['quantity']) {
                        $pdo->rollBack();
                        flash('Impossible de réactiver la commande : le stock de ses articles est insuffisant.', 'error');
                        redirect('commandes.php?ref=' . rawurlencode($ref));
                    }
                }
            }
            $pdo->prepare("UPDATE variants v JOIN order_items oi ON oi.variant_id=v.id SET v.stock=GREATEST(0, CAST(v.stock AS SIGNED) $sign oi.quantity) WHERE oi.order_id=? AND NOT EXISTS (SELECT 1 FROM order_item_components oic WHERE oic.order_item_id=oi.id AND oic.variant_id IS NOT NULL)")->execute([(int) $order['id']]);
            $pdo->prepare("UPDATE variants v JOIN order_item_components oic ON oic.variant_id=v.id JOIN order_items oi ON oi.id=oic.order_item_id SET v.stock=GREATEST(0, CAST(v.stock AS SIGNED) $sign (oic.quantity_per_bundle * oi.quantity)) WHERE oi.order_id=? AND oic.variant_id IS NOT NULL")->execute([(int) $order['id']]);
        }
        $pdo->prepare("UPDATE orders SET status=?, paid_at=IF(? IN ('payee','livree') AND paid_at IS NULL AND (?='payee' OR payment_method='delivery'), NOW(), paid_at) WHERE id=?")
            ->execute([$status, $status, $status, (int) $order['id']]);
        $pdo->commit();
        flash('Commande ' . $ref . ' : ' . ORDER_STATUSES[$status][0] . ($sign === '+' ? ' (articles remis en stock)' : '') . '.');
    }
    redirect('commandes.php?' . http_build_query(['ref' => $ref]));
}

$ref = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$order = $ref !== '' ? find_order($pdo, $ref) : null;
if ($order) $order = order_sync_payment($pdo, $order);

if ($order) {
    $items = order_items($pdo, (int) $order['id']);
    $imgs = $pdo->prepare('SELECT p.image FROM variants v JOIN products p ON p.id=v.product_id WHERE v.id=?');
    $history = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(total),0) spent FROM orders WHERE REPLACE(phone,' ','')=REPLACE(?,' ','') AND status<>'annulee'");
    $history->execute([$order['phone']]);
    $history = $history->fetch();
    $first = $order['first_name'] ?: $order['customer_name'];
    if ($order['paid_at']) {
        $paymentInstruction = 'Votre paiement est confirmé.';
    } elseif ($order['payment_method'] === 'geniuspay') {
        $paymentInstruction = 'Vous pouvez la régler en ligne (Wave, Orange Money ou carte) avec ce lien : ' . geniuspay_pay_link((string) $order['reference']);
    } elseif ($order['payment_method'] === 'wave') {
        $paymentInstruction = 'Vous avez choisi Wave. Nous vous enverrons le lien de paiement dans cette conversation. Après le règlement, envoyez-nous une capture du reçu.';
    } elseif ($order['payment_method'] === 'orange') {
        $paymentInstruction = 'Vous avez choisi Orange Money. Nous vous enverrons les instructions de paiement dans cette conversation. Après le règlement, envoyez-nous une capture du reçu.';
    } else {
        $paymentInstruction = 'Vous avez choisi le paiement à la livraison : le montant sera à régler au livreur à la réception.';
    }
    $orderReadyDate = order_ready_date($items);
    $readyInfo = $orderReadyDate ? ' Vos articles sur commande seront prêts le ' . date_fr($orderReadyDate, true) . ' : nous vous recontacterons pour la livraison.' : '';
    $messages = [
        'Confirmer la commande' => "Bonjour $first, nous avons bien reçu votre commande {$order['reference']} (" . fcfa((int) $order['total']) . ").$readyInfo $paymentInstruction Merci pour votre confiance ! — " . SHOP_NAME,
        'Annoncer la livraison' => "Bonjour $first, votre commande {$order['reference']} est en route ! Le livreur vous appellera à l'approche. " . ($order['payment_method'] === 'delivery' && !$order['paid_at'] ? 'Montant à régler : ' . fcfa((int) $order['total']) . '. ' : '') . '— ' . SHOP_NAME,
    ];
    if (!$order['paid_at'] && $order['payment_method'] === 'geniuspay') {
        $messages['Envoyer le lien de paiement en ligne'] = "Bonjour $first,\n\nPour votre commande {$order['reference']} :\nMontant à payer : " . fcfa((int) $order['total']) . "\nLien de paiement (Wave, Orange Money ou carte) : " . geniuspay_pay_link((string) $order['reference']) . "\n\nVotre commande sera confirmée automatiquement après le paiement.\n— " . SHOP_NAME;
    } elseif (!$order['paid_at'] && $order['payment_method'] === 'wave') {
        $wavePayUrl = site_url('wave-pay.php?ref=' . rawurlencode((string) $order['reference']) . '&t=' . order_token((string) $order['reference']));
        $messages['Envoyer le lien de paiement Wave'] = "Bonjour $first,\n\nPour votre commande {$order['reference']} :\nMontant exact à payer : " . fcfa((int) $order['total']) . "\nLien de paiement Wave : $wavePayUrl\n\nAprès le paiement, envoyez-nous une capture du reçu dans cette conversation.\n— " . SHOP_NAME;
    } elseif (!$order['paid_at'] && $order['payment_method'] === 'orange') {
        $orangePaymentInfo = ORANGE_PAYMENT_LINK !== '' ? 'Voici le lien de paiement : ' . ORANGE_PAYMENT_LINK : 'Nous vous transmettons les instructions de paiement dans cette conversation.';
        $messages['Envoyer les instructions Orange Money'] = "Bonjour $first, pour votre commande {$order['reference']} (" . fcfa((int) $order['total']) . "), $orangePaymentInfo Après le règlement, envoyez-nous une capture du reçu. — " . SHOP_NAME;
    }
    $clientWa = 'https://wa.me/' . (strlen($d = preg_replace('/\D/', '', (string) $order['phone'])) === 9 ? '221' . $d : $d) . '?text=';
    $pageTitle = 'Commande ' . $order['reference'];
    $pageSubtitle = 'Passée ' . lcfirst(rel_date((string) $order['created_at']));
    $pageActions = '<a href="commandes.php" class="btn btn-outline">' . icon('chevron-left', 'w-4 h-4') . ' Toutes les commandes</a><a href="' . e(url('facture.php?ref=' . rawurlencode($order['reference']))) . '" target="_blank" class="btn btn-outline">' . icon('printer', 'w-4 h-4') . ' Facture</a>';
    require __DIR__ . '/layout.php';
    $flow = ['nouvelle', 'payee', 'en_preparation', 'expediee', 'livree'];
    $idx = array_search($order['status'], $flow, true);
    // Paiement à la livraison : pas d'étape « Payée » avant l'expédition ; l'étape « Payée » n'est cochée que si le paiement est enregistré.
    $steps = $order['payment_method'] === 'delivery' ? array_values(array_diff($flow, ['payee'])) : $flow;
    $stepDone = array_map(fn($sk) => $sk === 'payee' ? !empty($order['paid_at']) : ($idx !== false && array_search($sk, $flow, true) <= $idx), $steps);
    $lastDone = 0;
    foreach ($stepDone as $i => $isDone) if ($isDone) $lastDone = $i;
    ?>
    <div class="grid xl:grid-cols-[1fr_360px] gap-5 items-start">
      <div class="space-y-5">
        <?= card_open() ?>
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 flex-wrap"><?= status_badge((string) $order['status']) ?><?= order_channel_badge($order) ?><?php if ($order['paid_at']): ?><span class="text-xs font-semibold text-forest inline-flex items-center gap-1"><?= icon('check', 'w-3.5 h-3.5') ?> Payée le <?= e(date('d/m à H:i', strtotime((string) $order['paid_at']))) ?></span><?php else: ?><span class="text-xs text-muted">Non payée</span><?php endif; ?></div>
            <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="ref" value="<?= e($order['reference']) ?>">
              <select name="status" class="field !w-auto !h-10 text-sm"><?php foreach (ORDER_STATUSES as $k => [$l]): ?><option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
              <button class="btn btn-primary btn-sm !h-10">Mettre à jour</button>
            </form>
          </div>
          <?php if ($idx !== false): ?>
            <?php $stepCount = count($steps); $edge = round(50 / $stepCount, 2); ?>
            <ol class="mt-6 grid relative" style="grid-template-columns:repeat(<?= $stepCount ?>,minmax(0,1fr))">
              <div class="absolute top-4 h-0.5 bg-line" style="left:<?= $edge ?>%;right:<?= $edge ?>%"><div class="h-full bg-forest" style="width:<?= (int) round($lastDone / max(1, $stepCount - 1) * 100) ?>%"></div></div>
              <?php foreach ($steps as $i => $sk): $done = $stepDone[$i]; ?>
                <li class="relative flex flex-col items-center text-center"><span class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?= $done ? 'bg-forest text-white' : 'bg-white border-2 border-line text-muted' ?>"><?= $done ? icon('check', 'w-4 h-4') : $i + 1 ?></span><span class="mt-2 text-[11px] sm:text-xs <?= $done ? 'font-semibold' : 'text-muted' ?>"><?= e(ORDER_STATUSES[$sk][0]) ?></span></li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>

        <?= card_open('Articles', 'bag') ?>
          <ul class="divide-y divide-line">
            <?php foreach ($items as $it): $imgs->execute([(int) $it['variant_id']]); ?>
              <li class="py-3 flex items-center gap-4">
                <?= product_thumb($imgs->fetchColumn() ?: null, 'w-12 h-14') ?>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-sm"><?= e($it['product_name']) ?></p>
                  <?php if (!empty($it['components'])): ?>
                    <?php foreach ($it['components'] as $component): $componentOptions = array_filter([$component['size'], $component['color']]); ?>
                      <p class="text-xs text-muted"><?= e($component['product_name']) ?><?= $componentOptions ? ' — ' . e(implode(' · ', $componentOptions)) : '' ?> × <?= (int) $component['quantity_per_bundle'] ?></p>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <p class="text-xs text-muted">Taille <?= e($it['size']) ?> · <?= e($it['color']) ?></p>
                  <?php endif; ?>
                  <?php if ($readyText = order_item_ready_text($it)): ?><p class="text-xs font-semibold text-forest"><?= e($readyText) ?></p><?php endif; ?>
                  <p class="text-xs text-muted"><?= fcfa((int) $it['price']) ?> × <?= (int) $it['quantity'] ?></p>
                </div>
                <p class="font-semibold tabular-nums text-sm whitespace-nowrap"><?= fcfa((int) $it['price'] * (int) $it['quantity']) ?></p>
              </li>
            <?php endforeach; ?>
          </ul>
          <dl class="mt-4 pt-4 border-t border-line space-y-2 text-sm max-w-xs ml-auto">
            <div class="flex justify-between"><dt class="text-muted">Sous-total</dt><dd class="tabular-nums"><?= fcfa((int) $order['subtotal']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-muted">Livraison</dt><dd class="tabular-nums"><?= (int) $order['delivery_fee'] ? fcfa((int) $order['delivery_fee']) : 'Offerte' ?></dd></div>
            <div class="flex justify-between text-base font-bold pt-2 border-t border-line"><dt>Total</dt><dd class="tabular-nums"><?= fcfa((int) $order['total']) ?></dd></div>
          </dl>
        </section>
      </div>

      <div class="space-y-5">
        <?= card_open('Client', 'user') ?>
          <p class="font-semibold"><?= e(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: $order['customer_name']) ?></p>
          <p class="text-sm mt-1"><a href="tel:<?= e(preg_replace('/\s/', '', (string) $order['phone'])) ?>" class="hover:text-forest"><?= e($order['phone']) ?></a></p>
          <p class="text-xs text-muted mt-1"><?= (int) $history['n'] ?> commande<?= (int) $history['n'] > 1 ? 's' : '' ?> · <?= fcfa((int) $history['spent']) ?> au total</p>
          <div class="mt-4 pt-4 border-t border-line text-sm space-y-1">
            <p class="flex gap-2"><?= icon('map-pin', 'w-4 h-4 text-forest shrink-0 mt-0.5') ?><span><?= e($order['zone_name'] ?? '') ?><br><span class="text-muted"><?= e($order['address']) ?></span></span></p>
            <?php if ($order['note']): ?><p class="flex gap-2 mt-2"><?= icon('edit', 'w-4 h-4 text-forest shrink-0 mt-0.5') ?><span class="text-muted"><?= e($order['note']) ?></span></p><?php endif; ?>
          </div>
          <div class="mt-4 pt-4 border-t border-line text-sm"><p class="text-muted text-xs mb-1">Paiement</p><p class="font-medium"><?= e(payment_label((string) $order['payment_method'])) ?></p><?php if ($order['wave_transaction_id']): ?><p class="text-xs text-muted">Transaction <?= e($order['wave_transaction_id']) ?></p><?php endif; ?></div>
        </section>
        <?= card_open('Écrire au client', 'whatsapp') ?>
          <div class="space-y-2">
            <?php foreach ($messages as $label => $msg): ?>
              <a href="<?= e($clientWa . rawurlencode($msg)) ?>" target="_blank" rel="noopener" class="flex items-center justify-between gap-3 rounded-xl border border-line px-4 py-3 text-sm font-medium hover:border-[#25D366] hover:bg-[#25D366]/5 transition"><?= e($label) ?><?= icon('arrow-right', 'w-4 h-4 text-muted') ?></a>
            <?php endforeach; ?>
          </div>
          <p class="text-xs text-muted mt-3">Ouvre WhatsApp avec un message prérempli, modifiable avant l'envoi.</p>
        </section>
      </div>
    </div>
    <?php
    require __DIR__ . '/layout-end.php';
    exit;
}

// ---- Liste
$status = (string) ($_GET['statut'] ?? '');
$payment = in_array($_GET['paiement'] ?? '', ['attente', 'payee'], true) ? (string) $_GET['paiement'] : '';
$q = trim((string) ($_GET['q'] ?? ''));
$where = ['1=1']; $params = [];
if (isset(ORDER_STATUSES[$status])) { $where[] = 'o.status=?'; $params[] = $status; }
if ($status === 'a_traiter') $where[] = "o.status IN ('nouvelle','payee','en_preparation')";
if ($payment === 'attente') $where[] = "o.payment_method IN ('wave','orange','geniuspay') AND o.paid_at IS NULL AND o.status<>'annulee'";
if ($payment === 'payee') $where[] = 'o.paid_at IS NOT NULL AND o.status<>\'annulee\'';
if ($q !== '') { $where[] = '(o.reference LIKE ? OR o.customer_name LIKE ? OR o.phone LIKE ?)'; array_push($params, "%$q%", "%$q%", "%$q%"); }
$page = max(1, (int) ($_GET['page'] ?? 1)); $per = 20;
$count = $pdo->prepare('SELECT COUNT(*) FROM orders o WHERE ' . implode(' AND ', $where)); $count->execute($params);
$total = (int) $count->fetchColumn(); $pages = max(1, (int) ceil($total / $per)); $page = min($page, $pages);
$st = $pdo->prepare('SELECT o.*, dz.name zone_name, (SELECT SUM(quantity) FROM order_items WHERE order_id=o.id) items FROM orders o LEFT JOIN delivery_zones dz ON dz.id=o.delivery_zone_id WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$st->execute($params);
$orders = $st->fetchAll();
$byStatus = $pdo->query('SELECT status, COUNT(*) n FROM orders GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$pendingOnlinePayments = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE payment_method IN ('wave','orange','geniuspay') AND paid_at IS NULL AND status<>'annulee'")->fetchColumn();
$todo = ($byStatus['nouvelle'] ?? 0) + ($byStatus['payee'] ?? 0) + ($byStatus['en_preparation'] ?? 0);

$pageTitle = 'Commandes';
$pageSubtitle = $todo . ' commande' . ($todo > 1 ? 's' : '') . ' à traiter';
require __DIR__ . '/layout.php';
$tabs = ['' => ['Toutes', array_sum($byStatus)], 'a_traiter' => ['À traiter', $todo]];
foreach (ORDER_STATUSES as $k => [$l]) $tabs[$k] = [$l, $byStatus[$k] ?? 0];
?>
<div class="flex gap-2 overflow-x-auto pb-2 mb-4 -mx-1 px-1">
  <?php foreach ($tabs as $k => [$l, $n]): ?>
    <a href="commandes.php?<?= e(http_build_query(array_filter(['statut' => $k, 'paiement' => $payment, 'q' => $q]))) ?>" class="shrink-0 inline-flex items-center gap-2 h-10 px-4 rounded-full text-sm font-medium border transition <?= $status === $k ? 'bg-forest text-white border-forest' : 'bg-white border-line hover:border-ink' ?>"><?= e($l) ?><span class="text-xs <?= $status === $k ? 'text-white/70' : 'text-muted' ?>"><?= (int) $n ?></span></a>
  <?php endforeach; ?>
</div>
<?= card_open() ?>
  <form method="get" class="flex flex-col sm:flex-row gap-3 mb-5">
    <input type="hidden" name="statut" value="<?= e($status) ?>">
    <div class="relative flex-1"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'w-4 h-4') ?></span><input name="q" value="<?= e($q) ?>" placeholder="Référence, nom ou téléphone…" class="field !pl-9"></div>
    <select name="paiement" class="field sm:!w-auto">
      <option value="">Tous les paiements</option>
      <option value="attente" <?= $payment === 'attente' ? 'selected' : '' ?>>À vérifier · paiements en ligne (<?= $pendingOnlinePayments ?>)</option>
      <option value="payee" <?= $payment === 'payee' ? 'selected' : '' ?>>Paiements reçus</option>
    </select>
    <button class="btn btn-outline">Filtrer</button>
  </form>
  <?php if (!$orders): ?>
    <?= empty_state('Aucune commande ici.', 'cart') ?>
  <?php else: ?>
    <div class="overflow-x-auto -mx-5 sm:-mx-6">
      <table class="tbl w-full min-w-[820px]">
        <thead><tr><th class="!pl-5 sm:!pl-6"># Commande</th><th>Client</th><th>Date</th><th>Articles</th><th>Montant</th><th>Paiement</th><th class="!pr-5 sm:!pr-6">Statut</th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
            <tr class="cursor-pointer" onclick="location.href='commandes.php?ref=<?= e(rawurlencode($o['reference'])) ?>'">
              <td class="!pl-5 sm:!pl-6 font-semibold whitespace-nowrap"><a href="commandes.php?ref=<?= e(rawurlencode($o['reference'])) ?>"><?= e($o['reference']) ?></a></td>
              <td><span class="block whitespace-nowrap"><?= e($o['customer_name']) ?></span><span class="text-xs text-muted"><?= e($o['zone_name'] ?? '') ?></span></td>
              <td class="text-muted whitespace-nowrap"><?= e(rel_date((string) $o['created_at'])) ?></td>
              <td class="text-muted"><?= (int) $o['items'] ?></td>
              <td class="font-semibold tabular-nums whitespace-nowrap"><?= fcfa((int) $o['total']) ?></td>
              <td class="whitespace-nowrap text-xs"><?= e(payment_label((string) $o['payment_method'])) ?><br><span class="<?= $o['paid_at'] ? 'text-forest font-semibold' : 'text-muted' ?>"><?= $o['paid_at'] ? 'Payée' : 'En attente' ?></span></td>
              <td class="!pr-5 sm:!pr-6"><div class="flex flex-col items-start gap-1"><?= status_badge((string) $o['status']) ?><?= order_channel_badge($o) ?></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($pages > 1): ?><div class="mt-5 flex justify-center gap-1.5"><?php for ($i = 1; $i <= $pages; $i++): ?><a href="commandes.php?<?= e(http_build_query(array_filter(['statut' => $status, 'paiement' => $payment, 'q' => $q, 'page' => $i]))) ?>" class="w-9 h-9 rounded-lg flex items-center justify-center text-sm font-semibold <?= $i === $page ? 'bg-forest text-white' : 'border border-line hover:border-ink' ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/layout-end.php'; ?>
