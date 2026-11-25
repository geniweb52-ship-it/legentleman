<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once dirname(__DIR__) . '/includes/orders.php';

$period = in_array((int) ($_GET['p'] ?? 30), [7, 30, 90, 365], true) ? (int) ($_GET['p'] ?? 30) : 30;
$since = (new DateTimeImmutable('today -' . ($period - 1) . ' days'))->format('Y-m-d');
$q = fn(string $sql, array $p = []) => (function () use ($pdo, $sql, $p) { $s = $pdo->prepare($sql); $s->execute($p); return $s; })();
$valid = "o.status<>'annulee'";

$kpi = $q("SELECT COUNT(*) n, COALESCE(SUM(total),0) rev, COALESCE(AVG(total),0) avg_basket, COALESCE(SUM(delivery_fee),0) fees, SUM(paid_at IS NOT NULL) paid FROM orders o WHERE $valid AND created_at>=?", [$since])->fetch();
$items = (int) $q("SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $valid AND o.created_at>=?", [$since])->fetchColumn();
$cancelled = (int) $q("SELECT COUNT(*) FROM orders WHERE status='annulee' AND created_at>=?", [$since])->fetchColumn();

$months = $q("SELECT DATE_FORMAT(created_at,'%Y-%m') m, SUM(total) rev FROM orders o WHERE $valid AND created_at>=DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 11 MONTH) GROUP BY m")->fetchAll(PDO::FETCH_KEY_PAIR);
$mLabels = []; $mValues = [];
$fr = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
for ($i = 11; $i >= 0; $i--) { $d = new DateTimeImmutable("first day of -$i month"); $mLabels[] = $fr[(int) $d->format('n') - 1]; $mValues[] = (float) ($months[$d->format('Y-m')] ?? 0); }

$byCat = $q("SELECT COALESCE(c.name,'Sans catégorie') name, SUM(oi.quantity*oi.price) amount, SUM(oi.quantity) qty FROM order_items oi JOIN orders o ON o.id=oi.order_id
    LEFT JOIN variants v ON v.id=oi.variant_id LEFT JOIN products p ON p.id=v.product_id LEFT JOIN categories c ON c.id=p.category_id
    WHERE $valid AND o.created_at>=? GROUP BY name ORDER BY amount DESC", [$since])->fetchAll();
$byPay = $q("SELECT payment_method m, COUNT(*) n, SUM(total) amount FROM orders o WHERE $valid AND created_at>=? GROUP BY m ORDER BY amount DESC", [$since])->fetchAll();
$byZone = $q("SELECT COALESCE(z.name,'—') name, COUNT(*) n, SUM(o.total) amount FROM orders o LEFT JOIN delivery_zones z ON z.id=o.delivery_zone_id WHERE $valid AND o.created_at>=? GROUP BY name ORDER BY n DESC LIMIT 6", [$since])->fetchAll();
$top = $q("SELECT COALESCE(p.name, oi.product_name) name, p.image, SUM(oi.quantity) qty, SUM(oi.quantity*oi.price) amount FROM order_items oi JOIN orders o ON o.id=oi.order_id
    LEFT JOIN variants v ON v.id=oi.variant_id LEFT JOIN products p ON p.id=v.product_id WHERE $valid AND o.created_at>=? GROUP BY COALESCE(p.id, oi.product_name), name, p.image ORDER BY qty DESC LIMIT 10", [$since])->fetchAll();
// Les coffrets (taille technique « Unique ») sont exclus : leurs tailles réelles sont celles des composants.
$sizes = $q("SELECT oi.size, SUM(oi.quantity) qty FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE $valid AND o.created_at>=?
    AND NOT EXISTS (SELECT 1 FROM order_item_components oic WHERE oic.order_item_id=oi.id) GROUP BY oi.size ORDER BY qty DESC LIMIT 8", [$since])->fetchAll();

$analytics = $q("SELECT
  COUNT(DISTINCT visitor_id) visitors,
  COUNT(DISTINCT CASE WHEN event_type='page_view' THEN session_id END) sessions,
  SUM(event_type='product_view') product_views,
  SUM(event_type='product_click') product_clicks,
  SUM(event_type='share_click') shares,
  SUM(event_type='buy_click') direct_buys,
  SUM(event_type='qr_scan') qr_scans
  FROM analytics_events WHERE created_at>=?", [$since])->fetch();
$productAnalytics = $q("SELECT p.name, p.image,
  SUM(a.event_type='product_view') views,
  SUM(a.event_type='product_click') clicks,
  SUM(a.event_type='share_click') shares,
  SUM(a.event_type='buy_click') buys
  FROM analytics_events a JOIN products p ON p.id=a.product_id
  WHERE a.created_at>=? GROUP BY p.id,p.name,p.image
  HAVING views>0 OR clicks>0 OR shares>0 OR buys>0
  ORDER BY views DESC, buys DESC LIMIT 10", [$since])->fetchAll();
$qrCampaigns = $q("SELECT c.name, c.destination_type, p.name product_name,
  (SELECT COUNT(*) FROM analytics_events ae WHERE ae.campaign_id=c.id AND ae.event_type='qr_scan' AND ae.created_at>=?) scans,
  (SELECT COUNT(*) FROM orders o WHERE o.campaign_id=c.id AND o.status<>'annulee' AND o.created_at>=?) orders_count,
  (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.campaign_id=c.id AND o.status<>'annulee' AND o.created_at>=?) revenue
  FROM qr_campaigns c LEFT JOIN products p ON p.id=c.product_id ORDER BY c.created_at DESC LIMIT 25", [$since, $since, $since])->fetchAll();

$pageTitle = 'Statistiques';
$pageSubtitle = 'Performances de la boutique';
$pageActions = '<form method="get" class="flex gap-1 bg-white border border-line rounded-xl p-1">' . implode('', array_map(fn($v, $l) => '<button name="p" value="' . $v . '" class="h-9 px-3 rounded-lg text-sm font-medium ' . ($period === $v ? 'bg-forest text-white' : 'hover:bg-cream') . '">' . $l . '</button>', [7, 30, 90, 365], ['7 j', '30 j', '90 j', '12 mois'])) . '</form>';
require __DIR__ . '/layout.php';
$bars = function (array $rows, string $key, string $valueKey, bool $money = true): string {
    if (!$rows) return empty_state('Pas encore de données.', 'chart');
    $max = max(1, ...array_map(fn($r) => (float) $r[$valueKey], $rows));
    $h = '<ul class="space-y-3.5">';
    foreach ($rows as $r) {
        $h .= '<li><div class="flex justify-between text-sm mb-1.5"><span class="font-medium">' . e((string) $r[$key]) . '</span><span class="tabular-nums text-muted">' . ($money ? fcfa((int) $r[$valueKey]) : (int) $r[$valueKey]) . '</span></div>'
            . '<div class="h-2 rounded-full bg-[#EFE9DC] overflow-hidden"><div class="h-full rounded-full bg-forest" style="width:' . round((float) $r[$valueKey] / $max * 100) . '%"></div></div></li>';
    }
    return $h . '</ul>';
};
?>
<div class="grid grid-cols-2 lg:grid-cols-3 2xl:grid-cols-7 gap-4 mb-5">
  <?php foreach ([
    ['Visiteurs uniques', (int) ($analytics['visitors'] ?? 0)],
    ['Visites', (int) ($analytics['sessions'] ?? 0)],
    ['Vues produits', (int) ($analytics['product_views'] ?? 0)],
    ['Clics produits', (int) ($analytics['product_clicks'] ?? 0)],
    ['Partages', (int) ($analytics['shares'] ?? 0)],
    ['Achats directs', (int) ($analytics['direct_buys'] ?? 0)],
    ['Scans QR', (int) ($analytics['qr_scans'] ?? 0)],
  ] as [$label, $value]): ?>
    <div class="bg-white rounded-2xl border border-line p-4"><p class="text-xs text-muted"><?= e($label) ?></p><p class="font-display text-xl sm:text-2xl mt-1 tabular-nums"><?= $value ?></p></div>
  <?php endforeach; ?>
</div>
<div class="grid grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-4 mb-5">
  <?php foreach ([
    ['Chiffre d\'affaires', fcfa((int) $kpi['rev'])], ['Commandes', (int) $kpi['n']], ['Panier moyen', fcfa((int) $kpi['avg_basket'])],
    ['Articles vendus', $items], ['Taux payé', $kpi['n'] ? round($kpi['paid'] / $kpi['n'] * 100) . ' %' : '—'], ['Annulations', $cancelled],
  ] as [$l, $v]): ?>
    <div class="bg-white rounded-2xl border border-line p-4"><p class="text-xs text-muted"><?= e($l) ?></p><p class="font-display text-xl sm:text-2xl mt-1 whitespace-nowrap tabular-nums"><?= e((string) $v) ?></p></div>
  <?php endforeach; ?>
</div>
<div class="grid xl:grid-cols-[1.6fr_1fr] gap-5">
  <?= card_open('Chiffre d\'affaires mensuel', 'chart') ?><?= array_sum($mValues) > 0 ? bar_chart($mLabels, $mValues) : empty_state('Pas encore de ventes.', 'chart') ?></section>
  <?= card_open('Par catégorie', 'list') ?><?= $bars($byCat, 'name', 'amount') ?></section>
</div>
<div class="mt-5 grid lg:grid-cols-3 gap-5">
  <?= card_open('Modes de paiement', 'card') ?><?= $bars(array_map(fn($r) => ['name' => payment_label((string) $r['m']) . ' (' . (int) $r['n'] . ')', 'amount' => $r['amount']], $byPay), 'name', 'amount') ?></section>
  <?= card_open('Zones de livraison', 'map-pin') ?><?= $bars($byZone, 'name', 'n', false) ?></section>
  <?= card_open('Tailles les plus vendues', 'ruler') ?><?= $bars($sizes, 'size', 'qty', false) ?></section>
</div>
<div class="mt-5">
  <?= card_open('Top 10 des produits', 'package') ?>
    <?php if (!$top): ?><?= empty_state('Pas encore de ventes sur la période.', 'package') ?><?php else: ?>
      <div class="overflow-x-auto -mx-5 sm:-mx-6"><table class="tbl w-full min-w-[520px]"><thead><tr><th class="!pl-5 sm:!pl-6">#</th><th>Produit</th><th>Quantité</th><th class="!pr-5 sm:!pr-6">Chiffre d'affaires</th></tr></thead><tbody>
        <?php foreach ($top as $i => $t): ?><tr><td class="!pl-5 sm:!pl-6 font-bold text-muted"><?= $i + 1 ?></td><td><div class="flex items-center gap-3"><?= product_thumb($t['image'], 'w-10 h-12') ?><span class="font-medium"><?= e($t['name']) ?></span></div></td><td class="tabular-nums"><?= (int) $t['qty'] ?></td><td class="!pr-5 sm:!pr-6 tabular-nums font-semibold"><?= fcfa((int) $t['amount']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<div class="mt-5">
  <?= card_open('Performance des produits', 'share') ?>
    <?php if (!$productAnalytics): ?><?= empty_state('Aucune interaction produit enregistrée.', 'share') ?><?php else: ?>
      <div class="overflow-x-auto -mx-5 sm:-mx-6"><table class="tbl w-full min-w-[720px]"><thead><tr><th class="!pl-5 sm:!pl-6">Produit</th><th>Vues</th><th>Clics</th><th>Partages</th><th>Achats directs</th><th class="!pr-5 sm:!pr-6">Taux achat direct</th></tr></thead><tbody>
        <?php foreach ($productAnalytics as $p): $views=(int)$p['views']; $buys=(int)$p['buys']; ?>
          <tr><td class="!pl-5 sm:!pl-6"><div class="flex items-center gap-3"><?= product_thumb($p['image'], 'w-10 h-12') ?><span class="font-medium"><?= e($p['name']) ?></span></div></td><td class="tabular-nums"><?= $views ?></td><td class="tabular-nums"><?= (int)$p['clicks'] ?></td><td class="tabular-nums"><?= (int)$p['shares'] ?></td><td class="tabular-nums"><?= $buys ?></td><td class="!pr-5 sm:!pr-6 tabular-nums font-semibold"><?= $views ? round($buys/$views*100,1).' %' : '—' ?></td></tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<div class="mt-5">
  <?= card_open('Résultats des campagnes QR', 'qr') ?>
    <?php if (!$qrCampaigns): ?><?= empty_state('Créez un QR code nommé pour commencer à comparer vos supports.', 'qr') ?><?php else: ?>
      <div class="overflow-x-auto -mx-5 sm:-mx-6"><table class="tbl w-full min-w-[760px]"><thead><tr><th class="!pl-5 sm:!pl-6">Campagne</th><th>Destination</th><th>Scans</th><th>Commandes</th><th>Conversion</th><th class="!pr-5 sm:!pr-6">Chiffre d’affaires</th></tr></thead><tbody>
        <?php foreach ($qrCampaigns as $campaign): $scans=(int)$campaign['scans']; $ordersCount=(int)$campaign['orders_count']; ?>
          <tr>
            <td class="!pl-5 sm:!pl-6 font-semibold"><?= e($campaign['name']) ?></td>
            <td><?= e(['site'=>'Site','product'=>'Fiche produit','direct'=>'Commande directe'][$campaign['destination_type']] ?? $campaign['destination_type']) ?><?= $campaign['product_name'] ? ' · ' . e($campaign['product_name']) : '' ?></td>
            <td class="tabular-nums"><?= $scans ?></td>
            <td class="tabular-nums"><?= $ordersCount ?></td>
            <td class="tabular-nums"><?= $scans ? round($ordersCount / $scans * 100, 1) . ' %' : '—' ?></td>
            <td class="!pr-5 sm:!pr-6 tabular-nums font-semibold"><?= fcfa((int)$campaign['revenue']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
