<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$q = trim((string) ($_GET['q'] ?? ''));
$sort = in_array($_GET['tri'] ?? '', ['depense', 'commandes', 'recent'], true) ? $_GET['tri'] : 'recent';
$where = '1=1'; $params = [];
if ($q !== '') { $where = '(c.name LIKE ? OR c.phone LIKE ? OR c.address LIKE ?)'; $params = ["%$q%", "%$q%", "%$q%"]; }
$order = ['depense' => 'spent DESC', 'commandes' => 'orders_count DESC', 'recent' => 'last_order DESC, c.created_at DESC'][$sort];
$st = $pdo->prepare("SELECT c.*, COUNT(CASE WHEN o.status<>'annulee' THEN o.id END) orders_count, COALESCE(SUM(CASE WHEN o.status<>'annulee' THEN o.total END),0) spent, MAX(o.created_at) last_order
    FROM customers c LEFT JOIN orders o ON o.customer_id=c.id WHERE $where GROUP BY c.id ORDER BY $order LIMIT 300");
$st->execute($params);
$clients = $st->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) total, (SELECT COUNT(*) FROM customers WHERE created_at>=DATE_SUB(CURDATE(), INTERVAL 30 DAY)) new30,
    (SELECT COUNT(*) FROM (SELECT customer_id FROM orders WHERE status<>'annulee' GROUP BY customer_id HAVING COUNT(*)>1) t) loyal,
    (SELECT COALESCE(AVG(total),0) FROM orders WHERE status<>'annulee') basket FROM customers")->fetch();

$pageTitle = 'Clients';
$pageSubtitle = 'Vos acheteurs et leur historique';
require __DIR__ . '/layout.php';
$waLink = fn(string $phone) => 'https://wa.me/' . (strlen($d = preg_replace('/\D/', '', $phone)) === 9 ? '221' . $d : $d);
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
  <?php foreach ([['Clients', number_format((int) $stats['total'], 0, ',', ' '), 'users'], ['Nouveaux (30 j)', (int) $stats['new30'], 'sparkle'], ['Clients fidèles', (int) $stats['loyal'], 'heart'], ['Panier moyen', fcfa((int) $stats['basket']), 'bag']] as [$l, $v, $ic]): ?>
    <div class="bg-white rounded-2xl border border-line p-4 flex items-center gap-3"><span class="w-11 h-11 rounded-full bg-[#EFE9DC] text-forest flex items-center justify-center shrink-0"><?= icon($ic, 'w-5 h-5') ?></span><div class="min-w-0"><p class="text-xs text-muted"><?= e($l) ?></p><p class="font-display text-xl sm:text-2xl truncate"><?= e((string) $v) ?></p></div></div>
  <?php endforeach; ?>
</div>
<?= card_open() ?>
  <form method="get" class="flex flex-wrap gap-3 mb-5">
    <div class="relative flex-1 min-w-[200px]"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'w-4 h-4') ?></span><input name="q" value="<?= e($q) ?>" placeholder="Nom, téléphone ou adresse…" class="field !pl-9"></div>
    <select name="tri" class="field !w-auto" data-autosubmit><option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Plus récents</option><option value="depense" <?= $sort === 'depense' ? 'selected' : '' ?>>Meilleurs clients</option><option value="commandes" <?= $sort === 'commandes' ? 'selected' : '' ?>>Nombre de commandes</option></select>
    <button class="btn btn-outline">Rechercher</button>
  </form>
  <?php if (!$clients): ?>
    <?= empty_state('Aucun client pour le moment.', 'users') ?>
  <?php else: ?>
    <div class="overflow-x-auto -mx-5 sm:-mx-6">
      <table class="tbl w-full min-w-[760px]">
        <thead><tr><th class="!pl-5 sm:!pl-6">Client</th><th>Téléphone</th><th>Adresse</th><th>Commandes</th><th>Total dépensé</th><th>Dernier achat</th><th class="!pr-5 sm:!pr-6"></th></tr></thead>
        <tbody>
          <?php foreach ($clients as $c): ?>
            <tr>
              <td class="!pl-5 sm:!pl-6"><div class="flex items-center gap-3"><span class="w-9 h-9 rounded-full bg-[#EFE9DC] text-forest font-semibold text-sm flex items-center justify-center shrink-0"><?= e(mb_strtoupper(mb_substr((string) $c['name'], 0, 1))) ?></span><span class="font-medium whitespace-nowrap"><?= e($c['name']) ?></span><?= (int) $c['orders_count'] > 1 ? '<span class="text-[10px] font-bold uppercase tracking-wide text-gold">Fidèle</span>' : '' ?></div></td>
              <td class="whitespace-nowrap"><a href="tel:<?= e(preg_replace('/\s/', '', (string) $c['phone'])) ?>" class="hover:text-forest"><?= e($c['phone']) ?></a></td>
              <td class="text-muted max-w-[220px] truncate"><?= e($c['address'] ?? '') ?></td>
              <td><a href="commandes.php?q=<?= e(rawurlencode((string) $c['phone'])) ?>" class="underline underline-offset-2"><?= (int) $c['orders_count'] ?></a></td>
              <td class="font-semibold tabular-nums whitespace-nowrap"><?= fcfa((int) $c['spent']) ?></td>
              <td class="text-muted whitespace-nowrap"><?= $c['last_order'] ? e(rel_date((string) $c['last_order'])) : '—' ?></td>
              <td class="!pr-5 sm:!pr-6 text-right"><a href="<?= e($waLink((string) $c['phone'])) ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm !px-2.5 text-[#1DA851]" title="WhatsApp"><?= icon('whatsapp', 'w-4 h-4') ?></a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/layout-end.php'; ?>
