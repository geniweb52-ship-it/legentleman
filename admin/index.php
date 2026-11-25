<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$period = in_array((int) ($_GET['p'] ?? 7), [7, 30, 90], true) ? (int) ($_GET['p'] ?? 7) : 7;
$start = new DateTimeImmutable('today -' . ($period - 1) . ' days');
$prevStart = $start->modify('-' . $period . ' days');
$s = $start->format('Y-m-d'); $ps = $prevStart->format('Y-m-d');

$sum = function (string $sql, array $p) use ($pdo): float { $st = $pdo->prepare($sql); $st->execute($p); return (float) $st->fetchColumn(); };
$valid = "status<>'annulee'";
$rev = $sum("SELECT COALESCE(SUM(total),0) FROM orders WHERE $valid AND created_at>=?", [$s]);
$revPrev = $sum("SELECT COALESCE(SUM(total),0) FROM orders WHERE $valid AND created_at>=? AND created_at<?", [$ps, $s]);
$ord = $sum("SELECT COUNT(*) FROM orders WHERE $valid AND created_at>=?", [$s]);
$ordPrev = $sum("SELECT COUNT(*) FROM orders WHERE $valid AND created_at>=? AND created_at<?", [$ps, $s]);
$cli = $sum('SELECT COUNT(*) FROM customers WHERE created_at>=?', [$s]);
$cliPrev = $sum('SELECT COUNT(*) FROM customers WHERE created_at>=? AND created_at<?', [$ps, $s]);
$cliTotal = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
$prodTotal = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE active=1')->fetchColumn();
$prod = $sum('SELECT COUNT(*) FROM products WHERE created_at>=?', [$s]);
$prodPrev = $sum('SELECT COUNT(*) FROM products WHERE created_at>=? AND created_at<?', [$ps, $s]);

// Séries journalières sur les deux périodes
$daily = $pdo->prepare("SELECT DATE(created_at) d, SUM(total) rev, COUNT(*) n FROM orders WHERE $valid AND created_at>=? GROUP BY DATE(created_at)");
$daily->execute([$ps]);
$byDay = [];
foreach ($daily->fetchAll() as $r) $byDay[$r['d']] = $r;
$custDaily = $pdo->prepare('SELECT DATE(created_at) d, COUNT(*) n FROM customers WHERE created_at>=? GROUP BY DATE(created_at)');
$custDaily->execute([$ps]);
$custByDay = array_column($custDaily->fetchAll(), 'n', 'd');
$prodDaily = $pdo->prepare('SELECT DATE(created_at) d, COUNT(*) n FROM products WHERE created_at>=? GROUP BY DATE(created_at)');
$prodDaily->execute([$ps]);
$prodByDay = array_column($prodDaily->fetchAll(), 'n', 'd');

$days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
$bucket = $period === 90 ? 7 : 1; // regroupement par semaine sur 90 jours
$series = ['labels' => [], 'current' => [], 'previous' => []];
$spark = ['rev' => [], 'ord' => [], 'cli' => [], 'prod' => []];
for ($i = 0; $i < $period; $i += $bucket) {
    $c = 0; $p = 0; $o = 0; $cu = 0; $pr = 0;
    for ($j = 0; $j < $bucket && $i + $j < $period; $j++) {
        $d = $start->modify('+' . ($i + $j) . ' days')->format('Y-m-d');
        $dp = $prevStart->modify('+' . ($i + $j) . ' days')->format('Y-m-d');
        $c += (float) ($byDay[$d]['rev'] ?? 0); $o += (int) ($byDay[$d]['n'] ?? 0);
        $p += (float) ($byDay[$dp]['rev'] ?? 0);
        $cu += (int) ($custByDay[$d] ?? 0); $pr += (int) ($prodByDay[$d] ?? 0);
    }
    $date = $start->modify('+' . $i . ' days');
    $series['labels'][] = $period === 7 ? $days[(int) $date->format('w')] : $date->format('d/m');
    $series['current'][] = $c; $series['previous'][] = $p;
    $spark['rev'][] = $c; $spark['ord'][] = $o; $spark['cli'][] = $cu; $spark['prod'][] = $pr;
}

$best = $pdo->prepare("SELECT COALESCE(p.name, oi.product_name) name, p.image, p.id, SUM(oi.quantity) qty, SUM(oi.quantity*oi.price) amount
    FROM order_items oi JOIN orders o ON o.id=oi.order_id AND o.status<>'annulee' AND o.created_at>=?
    LEFT JOIN variants v ON v.id=oi.variant_id LEFT JOIN products p ON p.id=v.product_id
    GROUP BY COALESCE(p.id, oi.product_name), name, p.image, p.id ORDER BY qty DESC LIMIT 4");
$best->execute([$s]);
$best = $best->fetchAll();
$bestMax = max(1, ...array_map(fn($b) => (int) $b['qty'], $best ?: [['qty' => 1]]));
$latest = $pdo->query('SELECT reference, customer_name, created_at, total, status FROM orders ORDER BY created_at DESC LIMIT 6')->fetchAll();
$heroImg = is_file(dirname(__DIR__) . '/assets/img/hero.webp') ? url('assets/img/hero.webp') : null;

$hour = (int) date('G');
$hello = $hour < 18 ? 'Bonjour' : 'Bonsoir';
$pageTitle = 'Tableau de bord';
$hidePageHeader = true;
require __DIR__ . '/layout.php';
$name = ucfirst(explode('@', $me['email'])[0]);
$cards = [
    ['Chiffre d\'affaires', number_format((int) $rev, 0, ',', ' ') . ' <span class="font-sans text-xs font-semibold text-muted">FCFA</span>', trend($rev, $revPrev), 'cart', $spark['rev']],
    ['Commandes', (string) (int) $ord, trend($ord, $ordPrev), 'bag', $spark['ord']],
    ['Clients', number_format($cliTotal, 0, ',', ' '), trend($cli, $cliPrev) . '<span class="text-[11px] text-muted ml-1">+' . (int) $cli . ' nouveaux</span>', 'users', $spark['cli']],
    ['Produits', (string) $prodTotal, trend($prod, $prodPrev) . '<span class="text-[11px] text-muted ml-1">' . (int) $prod . ' ajoutés</span>', 'package', $spark['prod']],
];
?>
<div class="flex flex-wrap items-start justify-between gap-4 mb-7">
  <div>
    <h1 class="font-display text-3xl sm:text-[36px] leading-tight"><?= $hello ?>, <?= e($name) ?> <span class="inline-block origin-bottom-right animate-[wave_1.6s_ease-in-out_1]">👋</span></h1>
    <p class="mt-1 text-muted">Voici un aperçu de votre boutique <?= $period === 7 ? 'sur les 7 derniers jours' : 'sur les ' . $period . ' derniers jours' ?>.</p>
  </div>
  <form method="get" class="relative">
    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"><?= icon('calendar', 'w-4 h-4') ?></span>
    <select name="p" data-autosubmit class="h-11 pl-10 pr-10 rounded-xl border border-line bg-white text-sm font-medium appearance-none cursor-pointer outline-none focus:border-forest">
      <?php foreach ([7 => '7 derniers jours', 30 => '30 derniers jours', 90 => '90 derniers jours'] as $v => $l): ?><option value="<?= $v ?>" <?= $period === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none"><?= icon('chevron-down', 'w-4 h-4') ?></span>
  </form>
</div>
<style>@keyframes wave { 20%,60% { transform: rotate(14deg); } 40%,80% { transform: rotate(-8deg); } }</style>

<div class="grid sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">
  <?php foreach ($cards as $i => [$label, $value, $tr, $ic, $sp]): ?>
    <div class="bg-white rounded-2xl border border-line p-5 overflow-hidden">
      <div class="flex items-center gap-4">
        <span class="w-12 h-12 rounded-full bg-[#EFE9DC] text-forest flex items-center justify-center shrink-0"><?= icon($ic, 'w-6 h-6') ?></span>
        <div class="min-w-0">
          <p class="text-sm text-muted whitespace-nowrap"><?= e($label) ?></p>
          <p class="font-display text-[24px] leading-tight mt-0.5 whitespace-nowrap tabular-nums"><?= $value /* valeurs générées en interne (nombres) */ ?></p>
        </div>
      </div>
      <div class="mt-3 flex items-end justify-between gap-3">
        <p class="flex flex-wrap items-center gap-x-1 whitespace-nowrap pb-1"><?= $tr ?></p>
        <div class="shrink-0 -mr-1"><?= sparkline($sp, (string) $i) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="mt-5 grid xl:grid-cols-[1.75fr_1fr] gap-5">
  <?= card_open('Évolution des ventes', 'chart', '<div class="flex items-center gap-4 text-xs text-muted"><span class="inline-flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-forest"></span>Période actuelle</span><span class="inline-flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-[#D9C6A3]"></span>Période précédente</span></div>') ?>
    <?php if (array_sum($series['current']) + array_sum($series['previous']) > 0): ?>
      <?= sales_chart($series) ?>
    <?php else: ?>
      <?= empty_state('Aucune vente sur cette période pour le moment.', 'chart') ?>
    <?php endif; ?>
  </section>

  <?= card_open('Produits les plus vendus', '', see_all('statistiques.php?p=' . $period)) ?>
    <?php if (!$best): ?>
      <?= empty_state('Les meilleures ventes apparaîtront ici.', 'package') ?>
    <?php else: ?>
      <ol class="space-y-4">
        <?php foreach ($best as $i => $b): ?>
          <li class="flex items-center gap-4">
            <span class="w-8 h-8 rounded-full bg-[#EFE9DC] text-sm font-bold flex items-center justify-center shrink-0"><?= $i + 1 ?></span>
            <?= product_thumb($b['image'], 'w-14 h-16') ?>
            <div class="flex-1 min-w-0">
              <p class="font-semibold text-sm truncate"><?= $b['id'] ? '<a href="produits.php?edit=' . (int) $b['id'] . '" class="hover:text-forest">' . e($b['name']) . '</a>' : e($b['name']) ?></p>
              <p class="text-xs text-muted mt-0.5"><?= (int) $b['qty'] ?> vente<?= (int) $b['qty'] > 1 ? 's' : '' ?> · <?= fcfa((int) $b['amount']) ?></p>
              <div class="mt-2 h-1.5 rounded-full bg-[#EFE9DC] overflow-hidden"><div class="h-full rounded-full bg-forest" style="width:<?= (int) round($b['qty'] / $bestMax * 100) ?>%"></div></div>
            </div>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>
</div>

<div class="mt-5 grid xl:grid-cols-[1.2fr_1fr] gap-5">
  <?= card_open('Dernières commandes', 'cart', see_all('commandes.php')) ?>
    <?php if (!$latest): ?>
      <?= empty_state('Aucune commande pour le moment.', 'cart') ?>
    <?php else: ?>
      <div class="overflow-x-auto -mx-5 sm:-mx-6">
        <table class="tbl w-full min-w-[560px]">
          <thead><tr><th class="!pl-5 sm:!pl-6"># Commande</th><th>Client</th><th>Date</th><th>Montant</th><th class="!pr-5 sm:!pr-6">Statut</th></tr></thead>
          <tbody>
            <?php foreach ($latest as $o): ?>
              <tr class="cursor-pointer" onclick="location.href='commandes.php?ref=<?= e(rawurlencode($o['reference'])) ?>'">
                <td class="!pl-5 sm:!pl-6 font-medium whitespace-nowrap"><?= e($o['reference']) ?></td>
                <td class="whitespace-nowrap"><?= e($o['customer_name']) ?></td>
                <td class="text-muted whitespace-nowrap"><?= e(rel_date((string) $o['created_at'])) ?></td>
                <td class="tabular-nums whitespace-nowrap font-medium"><?= fcfa((int) $o['total']) ?></td>
                <td class="!pr-5 sm:!pr-6"><?= status_badge((string) $o['status']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <div class="flex flex-col gap-5">
    <a href="<?= e(url('boutique.php?sort=new')) ?>" target="_blank" class="relative overflow-hidden rounded-2xl bg-sidebar text-white min-h-[170px] flex items-center group">
      <?php if ($heroImg): ?><img src="<?= e(image_url($heroImg, 800)) ?>" alt="" class="absolute right-0 top-0 h-full w-3/5 object-cover object-[70%_25%] group-hover:scale-105 transition duration-700"><?php endif; ?>
      <span class="absolute inset-0 bg-gradient-to-r from-sidebar from-40% via-sidebar/70 to-transparent"></span>
      <span class="relative p-6">
        <span class="block text-sm text-white/75">Nouvelle collection</span>
        <span class="block font-display text-2xl sm:text-[28px] leading-tight mt-1">Élégance masculine</span>
        <span class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-[#F3EBDB] text-sidebar text-xs font-semibold px-3 py-2">Voir la collection <?= icon('arrow-right', 'w-3.5 h-3.5') ?></span>
      </span>
    </a>
    <?= card_open('Actions rapides', 'zap') ?>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php foreach ([['produits.php?nouveau=1#nouveau', 'package', 'Ajouter un produit'], ['promotions.php', 'tag', 'Créer une promotion'], ['coffrets.php', 'gift', 'Gérer les coffrets'], ['statistiques.php', 'chart', 'Voir les statistiques']] as [$h, $ic, $l]): ?>
          <a href="<?= e($h) ?>" class="rounded-xl border border-line p-3 py-4 text-center hover:border-forest hover:bg-cream/60 transition group">
            <span class="inline-flex text-forest group-hover:scale-110 transition"><?= icon($ic, 'w-6 h-6') ?></span>
            <span class="block mt-2 text-xs font-semibold leading-snug"><?= e($l) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  </div>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
