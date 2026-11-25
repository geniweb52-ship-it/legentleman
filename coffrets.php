<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$products = $pdo->query("SELECT p.*, c.name category_name, " . PRODUCT_STOCK_SQL . " stock_total FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND (p.is_gift_box=1 OR c.slug='coffrets') ORDER BY p.created_at DESC")->fetchAll();
$pageTitle = 'Coffrets cadeaux';
require __DIR__ . '/includes/header.php';
?>
<main>
  <section class="max-w-site mx-auto px-4 sm:px-6 pt-6">
    <?= breadcrumb([['Coffrets', null]]) ?>
    <div class="mt-6 relative overflow-hidden rounded-2xl bg-[#1F2620] text-white px-6 sm:px-12 py-14 sm:py-20">
      <div class="absolute inset-0 opacity-[.06]" style="background-image:repeating-linear-gradient(135deg,#fff 0 1px,transparent 1px 24px)"></div>
      <span class="absolute -right-10 -bottom-20 font-display text-[260px] leading-none text-white/[.05] select-none">LG</span>
      <div class="relative max-w-xl">
        <p class="text-xs font-semibold uppercase tracking-[.25em] text-white/60">À offrir</p>
        <h1 class="font-display text-4xl sm:text-6xl mt-4 leading-tight">Nos coffrets cadeaux</h1>
        <p class="mt-5 text-white/75 leading-relaxed">Des ensembles élégants, emballés avec soin et prêts à offrir. Vous choisissez les tailles au moment de la commande, nous nous occupons du reste.</p>
        <div class="mt-8 flex flex-wrap gap-3 text-sm">
          <?php foreach (['gift' => 'Emballage soigné', 'refresh' => 'Échange de taille facile', 'truck' => 'Livré en 24–48 h à Dakar'] as $ic => $t): ?>
            <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2"><?= icon($ic, 'w-4 h-4') ?> <?= e($t) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <section class="max-w-site mx-auto px-4 sm:px-6 pt-12">
    <?php if (!$products): ?>
      <div class="rounded-2xl border border-line bg-white py-14 px-6 text-center">
        <span class="mx-auto w-16 h-16 rounded-full bg-cream flex items-center justify-center text-forest"><?= icon('gift', 'w-7 h-7') ?></span>
        <p class="mt-5 font-display text-2xl">Nos coffrets arrivent bientôt</p>
        <p class="mt-2 text-muted max-w-md mx-auto">Envie d'un coffret sur mesure dès maintenant ? Dites-nous votre budget et l'occasion, nous le composons pour vous.</p>
        <div class="mt-7 flex flex-wrap justify-center gap-3"><a href="<?= e(whatsapp_url('Bonjour, je souhaite un coffret cadeau sur mesure.')) ?>" target="_blank" rel="noopener" class="btn btn-primary"><?= icon('whatsapp') ?> Composer mon coffret</a><a href="<?= e(url('boutique.php')) ?>" class="btn btn-outline">Voir la boutique</a></div>
      </div>
    <?php else: ?>
      <div class="flex items-end justify-between mb-7"><h2 class="font-display text-3xl"><?= count($products) ?> coffret<?= count($products) > 1 ? 's' : '' ?> disponible<?= count($products) > 1 ? 's' : '' ?></h2></div>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6">
        <?php foreach ($products as $p) echo product_card($p); ?>
      </div>
      <div class="mt-14 rounded-2xl border border-line bg-white p-8 flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div><h3 class="font-display text-2xl">Un coffret sur mesure ?</h3><p class="text-muted mt-1">Indiquez-nous votre budget et l'occasion : nous composons un coffret unique.</p></div>
        <a href="<?= e(whatsapp_url('Bonjour, je souhaite un coffret cadeau sur mesure.')) ?>" target="_blank" rel="noopener" class="btn btn-primary shrink-0"><?= icon('whatsapp') ?> Nous écrire</a>
      </div>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
