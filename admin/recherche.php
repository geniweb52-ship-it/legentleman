<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$q = trim((string) ($_GET['q'] ?? ''));
$products = $orders = $clients = [];
if ($q !== '') {
    $like = '%' . $q . '%';
    // Une référence exacte ouvre directement la commande.
    $exact = $pdo->prepare('SELECT reference FROM orders WHERE reference=?'); $exact->execute([strtoupper($q)]);
    if ($ref = $exact->fetchColumn()) redirect('commandes.php?ref=' . rawurlencode((string) $ref));
    $st = $pdo->prepare('SELECT p.id, p.name, p.price, p.image, p.active, c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.name LIKE ? OR p.brand LIKE ? ORDER BY p.name LIMIT 12');
    $st->execute([$like, $like]); $products = $st->fetchAll();
    $st = $pdo->prepare('SELECT reference, customer_name, total, status, created_at FROM orders WHERE reference LIKE ? OR customer_name LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT 12');
    $st->execute([$like, $like, $like]); $orders = $st->fetchAll();
    $st = $pdo->prepare('SELECT name, phone, address FROM customers WHERE name LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT 12');
    $st->execute([$like, $like]); $clients = $st->fetchAll();
}
$pageTitle = $q !== '' ? 'Résultats pour « ' . $q . ' »' : 'Recherche';
$pageSubtitle = count($products) + count($orders) + count($clients) . ' résultat(s)';
require __DIR__ . '/layout.php';
?>
<?php if ($q === '' || !($products || $orders || $clients)): ?>
  <?= card_open() ?><?= empty_state($q === '' ? 'Saisissez un nom de produit, une référence de commande ou un client.' : 'Aucun résultat.', 'search') ?></section>
<?php else: ?>
  <div class="grid xl:grid-cols-3 gap-5 items-start">
    <?= card_open('Produits (' . count($products) . ')', 'package') ?>
      <ul class="divide-y divide-line"><?php foreach ($products as $p): ?><li><a href="<?= admin_can_access((string) admin_user($pdo)['role'], 'produits.php') ? 'produits.php?edit=' . (int) $p['id'] : 'stock.php?produit=' . (int) $p['id'] ?>" class="py-3 flex items-center gap-3 hover:text-forest"><?= product_thumb($p['image'], 'w-10 h-12') ?><span class="flex-1 min-w-0"><span class="block font-medium truncate"><?= e($p['name']) ?></span><span class="text-xs text-muted"><?= e($p['category_name'] ?? '—') ?> · <?= fcfa((int) $p['price']) ?></span></span></a></li><?php endforeach; ?></ul>
      <?php if (!$products): ?><p class="text-sm text-muted">Aucun produit.</p><?php endif; ?>
    </section>
    <?= card_open('Commandes (' . count($orders) . ')', 'cart') ?>
      <ul class="divide-y divide-line"><?php foreach ($orders as $o): ?><li><a href="commandes.php?ref=<?= e(rawurlencode($o['reference'])) ?>" class="py-3 flex items-center gap-3 hover:text-forest"><span class="flex-1 min-w-0"><span class="block font-medium"><?= e($o['reference']) ?> · <?= e($o['customer_name']) ?></span><span class="text-xs text-muted"><?= e(rel_date((string) $o['created_at'])) ?> · <?= fcfa((int) $o['total']) ?></span></span><?= status_badge((string) $o['status']) ?></a></li><?php endforeach; ?></ul>
      <?php if (!$orders): ?><p class="text-sm text-muted">Aucune commande.</p><?php endif; ?>
    </section>
    <?= card_open('Clients (' . count($clients) . ')', 'users') ?>
      <ul class="divide-y divide-line"><?php foreach ($clients as $c): ?><li><a href="commandes.php?q=<?= e(rawurlencode((string) $c['phone'])) ?>" class="py-3 block hover:text-forest"><span class="block font-medium"><?= e($c['name']) ?></span><span class="text-xs text-muted"><?= e($c['phone']) ?> · <?= e($c['address'] ?? '') ?></span></a></li><?php endforeach; ?></ul>
      <?php if (!$clients): ?><p class="text-sm text-muted">Aucun client.</p><?php endif; ?>
    </section>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/layout-end.php'; ?>
