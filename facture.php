<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/orders.php';

$ref = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$order = $ref !== '' ? find_order($pdo, $ref) : null;
$isAdmin = !empty($_SESSION['admin_id']);
if (!$order || !($isAdmin || order_token_valid($ref, (string) ($_GET['t'] ?? '')) || in_array($ref, $_SESSION['orders'] ?? [], true))) {
    http_response_code(404);
    exit('Facture introuvable.');
}
if ($order['payment_method'] === 'wave') $order = wave_sync_order($pdo, $order);
$items = order_items($pdo, (int) $order['id']);
$paid = order_is_paid($order);
$cancelled = $order['status'] === 'annulee';
$number = 'F-' . date('Y', strtotime((string) $order['created_at'])) . '-' . str_pad((string) $order['id'], 5, '0', STR_PAD_LEFT);
$phoneDisplay = phone_display(SHOP_PHONE);
header('X-Robots-Tag: noindex');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title>Facture <?= e($number) ?> | <?= e(SHOP_NAME) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Caslon+Text:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root { --forest:#2E4A36; --ink:#1D1D1B; --muted:#6E6A62; --line:#E7E1D6; --cream:#F8F5EF; --terra:#B4532A; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--cream); color: var(--ink); font: 14px/1.5 Inter, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
    .bar { max-width: 820px; margin: 24px auto 0; padding: 0 16px; display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; gap: 8px; height: 44px; padding: 0 18px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; cursor: pointer; border: 1.5px solid var(--ink); background: transparent; color: var(--ink); font-family: inherit; }
    .btn.primary { background: var(--forest); border-color: var(--forest); color: #fff; }
    .sheet { max-width: 820px; margin: 16px auto 48px; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 48px; }
    .top { display: flex; justify-content: space-between; gap: 24px; flex-wrap: wrap; padding-bottom: 28px; border-bottom: 1px solid var(--line); }
    .brand { font-family: "Libre Caslon Text", Georgia, serif; font-size: 28px; letter-spacing: .02em; }
    .muted { color: var(--muted); }
    h1 { font-family: "Libre Caslon Text", Georgia, serif; font-weight: 400; font-size: 30px; margin: 0; text-align: right; }
    .stamp { display: inline-block; margin-top: 10px; padding: 5px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
    .stamp.paid { background: rgba(46,74,54,.1); color: var(--forest); }
    .stamp.due { background: rgba(180,83,42,.1); color: var(--terra); }
    .stamp.void { background: #eee; color: #777; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; padding: 28px 0; }
    .label { font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); padding: 10px 0; border-bottom: 1.5px solid var(--ink); }
    td { padding: 14px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
    .r { text-align: right; } .num { font-variant-numeric: tabular-nums; white-space: nowrap; }
    .totals { margin-left: auto; width: 100%; max-width: 320px; margin-top: 20px; }
    .totals div { display: flex; justify-content: space-between; padding: 6px 0; }
    .totals .grand { border-top: 1.5px solid var(--ink); margin-top: 6px; padding-top: 12px; font-size: 18px; font-weight: 700; }
    .foot { margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--line); font-size: 12px; color: var(--muted); text-align: center; }
    @media (max-width: 640px) { .sheet { padding: 24px 18px; border-radius: 0; border-left: 0; border-right: 0; } .grid { grid-template-columns: 1fr; } h1 { text-align: left; } .top > div:last-child { text-align: left; } }
    @media print { body { background: #fff; } .bar { display: none; } .sheet { border: 0; margin: 0; padding: 0; max-width: none; } @page { margin: 16mm; } }
  </style>
</head>
<body>
  <div class="bar">
    <a class="btn" href="<?= e(order_page_url($ref)) ?>">← Retour à la commande</a>
    <button class="btn primary" type="button" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
  </div>
  <main class="sheet">
    <div class="top">
      <div>
        <img src="<?= e(url('assets/img/brand/logo.png')) ?>" alt="<?= e(SHOP_NAME) ?>" style="width:190px;height:auto;display:block;margin-left:-4px">
        <div class="muted" style="margin-top:6px"><?= e(SHOP_TAGLINE) ?></div>
        <div class="muted" style="margin-top:10px"><?= e(SHOP_ADDRESS) ?><br>Tél. / WhatsApp : <?= e($phoneDisplay) ?></div>
      </div>
      <div style="text-align:right">
        <h1>Facture</h1>
        <div class="muted">N° <?= e($number) ?></div>
        <div class="muted">Commande <?= e($order['reference']) ?></div>
        <div class="muted">Date : <?= e(date('d/m/Y', strtotime((string) $order['created_at']))) ?></div>
        <?php if ($cancelled): ?><span class="stamp void">Annulée</span>
        <?php elseif ($paid): ?><span class="stamp paid">Payée</span>
        <?php else: ?><span class="stamp due">À régler</span><?php endif; ?>
      </div>
    </div>

    <div class="grid">
      <div>
        <div class="label">Facturé à</div>
        <strong><?= e(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) ?: $order['customer_name']) ?></strong><br>
        <?= e($order['phone']) ?><br>
        <?= e($order['address']) ?><?= $order['zone_name'] ? '<br>' . e($order['zone_name']) : '' ?>
        <?php if (!empty($order['note'])): ?><br><span class="muted">Note : <?= e($order['note']) ?></span><?php endif; ?>
      </div>
      <div>
        <div class="label">Paiement</div>
        <strong><?= e(payment_label((string) $order['payment_method'])) ?></strong><br>
        <?php if ($paid): ?>Payé le <?= e(date('d/m/Y à H:i', strtotime((string) $order['paid_at']))) ?><?= $order['wave_transaction_id'] ? '<br><span class="muted">Transaction ' . e($order['wave_transaction_id']) . '</span>' : '' ?>
        <?php elseif ($order['payment_method'] === 'delivery'): ?>À régler en espèces à la livraison
        <?php else: ?>En attente de paiement<?php endif; ?>
      </div>
    </div>

    <table>
      <thead><tr><th>Article</th><th class="r">Prix unitaire</th><th class="r">Qté</th><th class="r">Total</th></tr></thead>
      <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td>
              <strong><?= e($it['product_name']) ?></strong>
              <?php if (!empty($it['components'])): ?>
                <?php foreach ($it['components'] as $component): $componentOptions = array_filter([$component['size'], $component['color']]); ?>
                  <br><span class="muted"><?= e($component['product_name']) ?><?= $componentOptions ? ' — ' . e(implode(' · ', $componentOptions)) : '' ?> × <?= (int) $component['quantity_per_bundle'] ?></span>
                <?php endforeach; ?>
              <?php else: ?>
                <br><span class="muted">Taille <?= e($it['size']) ?> · <?= e($it['color']) ?></span>
              <?php endif; ?>
              <?php if ($readyText = order_item_ready_text($it)): ?><br><span style="color:var(--forest);font-weight:600;font-size:12px"><?= e($readyText) ?></span><?php endif; ?>
            </td>
            <td class="r num"><?= money((int) $it['price']) ?></td>
            <td class="r num"><?= (int) $it['quantity'] ?></td>
            <td class="r num"><?= money((int) $it['price'] * (int) $it['quantity']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="totals">
      <div><span class="muted">Sous-total</span><span class="num"><?= money((int) $order['subtotal']) ?></span></div>
      <div><span class="muted">Livraison</span><span class="num"><?= (int) $order['delivery_fee'] ? money((int) $order['delivery_fee']) : 'Offerte' ?></span></div>
      <div class="grand"><span>Total</span><span class="num"><?= money((int) $order['total']) ?></span></div>
      <?php if ($paid): ?><div style="color:var(--forest);font-weight:600"><span>Montant réglé</span><span class="num"><?= money((int) $order['total']) ?></span></div><?php endif; ?>
    </div>

    <div class="foot">Merci pour votre confiance. Échange possible sous 7 jours pour un article non porté, dans son état d'origine.<br><?= e(SHOP_NAME) ?> · <?= e(SHOP_ADDRESS) ?></div>
  </main>
</body>
</html>
