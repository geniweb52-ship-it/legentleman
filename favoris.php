<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';

// Les favoris sont stockés dans le navigateur ; la page reçoit la liste des identifiants en paramètre.
$ids = array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? '')))))), 0, 60);
$products = [];
if ($ids) {
    $stmt = $pdo->prepare('SELECT p.*, c.name category_name, ' . PRODUCT_STOCK_SQL . ' stock_total FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND p.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $stmt->execute($ids);
    $products = $stmt->fetchAll();
}
$pageTitle = 'Mes favoris';
require __DIR__ . '/includes/header.php';
?>
<main class="max-w-site mx-auto px-4 sm:px-6 pt-6" data-favorites-page="<?= isset($_GET['ids']) ? 'loaded' : 'pending' ?>">
  <?= breadcrumb([['Mes favoris', null]]) ?>
  <h1 class="font-display text-4xl sm:text-5xl mt-6">Mes favoris</h1>
  <p class="mt-3 text-muted">Les pièces que vous avez mises de côté.</p>
  <?php if (!$products): ?>
    <div class="mt-10 rounded-2xl border border-line bg-white py-16 px-6 text-center">
      <span class="mx-auto w-16 h-16 rounded-full bg-cream flex items-center justify-center text-terra"><?= icon('heart', 'w-7 h-7') ?></span>
      <p class="mt-5 font-display text-2xl">Aucun favori pour l'instant</p>
      <p class="mt-2 text-muted">Touchez le cœur sur un produit pour le retrouver ici.</p>
      <a href="<?= e(url('boutique.php')) ?>" class="btn btn-primary mt-7">Découvrir la boutique</a>
    </div>
  <?php else: ?>
    <div class="mt-8 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6">
      <?php foreach ($products as $product) echo product_card($product); ?>
    </div>
  <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
