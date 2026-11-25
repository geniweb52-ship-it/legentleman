<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';

$products = $pdo->query("SELECT p.*, c.name AS category_name, " . PRODUCT_STOCK_SQL . " stock_total FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND p.is_gift_box=0 ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
// Image de la catégorie (choisie dans l'admin), sinon la photo du dernier produit ajouté.
// Les catégories encore vides restent visibles si elles ont une image (« Bientôt disponible »).
$categories = $pdo->query("SELECT c.name, c.slug, c.image cover,
    (SELECT p.image FROM products p WHERE p.category_id=c.id AND p.active=1 AND p.image IS NOT NULL AND p.image<>'' ORDER BY p.created_at DESC LIMIT 1) image,
    (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.active=1) total
    FROM categories c WHERE c.slug<>'tous' HAVING total > 0 OR cover IS NOT NULL ORDER BY c.sort_order")->fetchAll();
foreach ($categories as &$cat) {
    if (!empty($cat['cover'])) $cat['image'] = url($cat['cover']);
}
unset($cat);
$catCols = count($categories) > 6 ? (int) ceil(count($categories) / 2) : count($categories);
$giftImage = $pdo->query("SELECT p.image FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.active=1 AND (p.is_gift_box=1 OR c.slug='coffrets') AND p.image IS NOT NULL AND p.image<>'' ORDER BY p.created_at DESC LIMIT 1")->fetchColumn();

$heroImage = null;
foreach (['webp', 'jpg', 'jpeg', 'png'] as $ext) {
    if (is_file(__DIR__ . "/assets/img/hero.$ext")) { $heroImage = url("assets/img/hero.$ext"); break; }
}
$heroImage ??= $pdo->query("SELECT image FROM products WHERE active=1 AND image IS NOT NULL AND image<>'' ORDER BY created_at DESC LIMIT 1")->fetchColumn() ?: null;

$hideReassurance = true;
$pageTitle = 'Le style masculin au quotidien';
require __DIR__ . '/includes/header.php';
// Thème de fête : l'accueil reprend son titre, son texte, son bouton et (si fournie) son image.
if ($holiday && $holiday['image'] !== '') $heroImage = $holiday['image'];
?>
<main>
  <!-- Hero -->
  <section class="relative overflow-hidden">
    <div class="max-w-site mx-auto lg:px-6 lg:pt-6">
      <div class="relative lg:rounded-2xl overflow-hidden bg-[#E9E2D5] min-h-[560px] sm:min-h-[600px] lg:min-h-[640px] flex items-center">
        <?php if ($heroImage): ?>
          <div class="absolute inset-y-0 right-0 w-full sm:w-[64%] overflow-hidden">
            <?= responsive_img($heroImage, '', 'hero-img w-full h-full object-cover object-[75%_30%]', '(min-width:1320px) 845px, (min-width:640px) 64vw, 100vw', false, 'fetchpriority="high"') ?>
          </div>
          <div class="absolute inset-0 bg-gradient-to-b from-[#F4EFE6]/95 via-[#F4EFE6]/75 to-[#F4EFE6]/0 sm:bg-gradient-to-r sm:from-[#F4EFE6] sm:from-[36%] sm:via-[#F4EFE6]/40 sm:via-[55%] sm:to-transparent sm:to-[75%]"></div>
        <?php else: ?>
          <div class="absolute inset-y-0 right-0 w-full sm:w-1/2 bg-forest overflow-hidden">
            <div class="absolute inset-0 opacity-[.07]" style="background-image:repeating-linear-gradient(135deg,#fff 0 1px,transparent 1px 22px)"></div>
            <span class="absolute right-[-2rem] bottom-[-4rem] font-display text-[300px] sm:text-[380px] leading-none text-white/[.08] select-none">LG</span>
          </div>
          <div class="absolute inset-0 bg-gradient-to-b from-[#F4EFE6]/95 via-[#F4EFE6]/80 to-[#F4EFE6]/10 sm:hidden"></div>
        <?php endif; ?>
        <div class="relative w-full px-6 sm:px-12 lg:px-16 py-16">
          <div class="max-w-xl">
            <?php if ($holiday): ?>
              <p class="fade-up text-xs font-semibold uppercase tracking-[.25em] text-forest inline-flex items-center gap-2"><span class="text-lg leading-none tracking-normal" aria-hidden="true"><?= e($holiday['emoji']) ?></span> <?= e($holiday['name']) ?></p>
              <h1 class="fade-up font-display text-[44px] sm:text-6xl lg:text-[68px] leading-[1.04] mt-5" style="animation-delay:.1s"><?= nl2br(e($holiday['title_text'])) ?></h1>
              <p class="fade-up mt-6 text-base sm:text-lg text-ink/75 max-w-md leading-relaxed" style="animation-delay:.2s"><?= e($holiday['body_text']) ?></p>
              <div class="fade-up mt-9 flex flex-wrap gap-3" style="animation-delay:.3s">
                <a href="<?= e(holiday_link($holiday['cta_link'])) ?>" class="btn btn-primary"><?= e($holiday['cta_text']) ?> <?= icon('arrow-right', 'w-4 h-4') ?></a>
                <a href="<?= e(url($holiday['cta_link'] === 'boutique.php' ? 'coffrets.php' : 'boutique.php')) ?>" class="btn btn-outline bg-white/40"><?= $holiday['cta_link'] === 'boutique.php' ? 'Voir les coffrets' : 'Toute la boutique' ?></a>
              </div>
            <?php else: ?>
            <p class="fade-up text-xs font-semibold uppercase tracking-[.25em] text-forest">Nouvelle collection</p>
            <h1 class="fade-up font-display text-[44px] sm:text-6xl lg:text-[68px] leading-[1.04] mt-5" style="animation-delay:.1s">Le style masculin<br>au quotidien</h1>
            <p class="fade-up mt-6 text-base sm:text-lg text-ink/75 max-w-md leading-relaxed" style="animation-delay:.2s">Vêtements, chaussures et accessoires pour l'homme moderne. Sélectionnés avec soin, livrés chez vous.</p>
            <div class="fade-up mt-9 flex flex-wrap gap-3" style="animation-delay:.3s">
              <a href="<?= e(url('boutique.php')) ?>" class="btn btn-primary">Découvrir <?= icon('arrow-right', 'w-4 h-4') ?></a>
              <a href="<?= e(url('coffrets.php')) ?>" class="btn btn-outline bg-white/40">Voir les coffrets</a>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Réassurance -->
  <section class="max-w-site mx-auto px-4 sm:px-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-y-8 gap-x-4 py-10 border-b border-line">
      <?php foreach ([
        ['truck', 'Livraison rapide', 'à Dakar et dans les régions'],
        ['card', 'Paiement sécurisé', 'Wave, Orange Money ou à la livraison'],
        ['refresh', 'Échange facile', 'en cas de taille non adaptée'],
        ['shield', 'Produits de qualité', 'et marques authentiques'],
      ] as [$ic, $title, $text]): ?>
        <div class="reveal flex flex-col items-center text-center">
          <span class="text-forest"><?= icon($ic, 'w-9 h-9') ?></span>
          <p class="mt-3 font-semibold text-sm"><?= e($title) ?></p>
          <p class="text-xs text-muted mt-1 max-w-[180px]"><?= e($text) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Catégories -->
  <?php if ($categories): ?>
  <section class="max-w-site mx-auto px-4 sm:px-6 pt-16">
    <div class="flex items-end justify-between mb-7">
      <h2 class="font-display text-3xl sm:text-4xl">Nos catégories</h2>
      <a href="<?= e(url('boutique.php')) ?>" class="group text-sm font-semibold inline-flex items-center gap-1.5">Voir tout <span class="transition-transform group-hover:translate-x-1"><?= icon('arrow-right', 'w-4 h-4') ?></span></a>
    </div>
    <div class="flex lg:grid lg:grid-cols-<?= min(6, max(1, $catCols)) ?> gap-4 overflow-x-auto snap-x snap-mandatory scrollbar-none -mx-4 px-4 lg:mx-0 lg:px-0 pb-2">
      <?php foreach ($categories as $cat): ?>
        <a href="<?= e(url('boutique.php?cat[]=' . rawurlencode($cat['slug']))) ?>" class="reveal group snap-start shrink-0 w-[42%] sm:w-[30%] lg:w-auto">
          <div class="relative aspect-square rounded-xl overflow-hidden bg-[#EDE7DC]">
            <?php if ($cat['image']): ?>
              <img src="<?= e(image_url($cat['image'], 400)) ?>" alt="<?= e($cat['name']) ?>" loading="lazy" class="w-full h-full object-cover object-top transition duration-700 group-hover:scale-[1.06]">
            <?php else: ?>
              <?= placeholder() ?>
            <?php endif; ?>
            <div class="absolute inset-0 bg-ink/0 group-hover:bg-ink/10 transition-colors"></div>
          </div>
          <p class="mt-3 text-center text-sm font-semibold group-hover:text-forest transition-colors"><?= e($cat['name']) ?></p>
          <p class="text-center text-xs text-muted"><?= (int) $cat['total'] ? (int) $cat['total'] . ' article' . ((int) $cat['total'] > 1 ? 's' : '') : 'Bientôt disponible' ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- Coffrets -->
  <section class="max-w-site mx-auto px-4 sm:px-6 pt-16">
    <div class="reveal relative overflow-hidden rounded-2xl bg-[#1F2620] text-white grid md:grid-cols-2 min-h-[340px]">
      <div class="relative z-10 p-8 sm:p-12 lg:p-14 flex flex-col justify-center">
        <p class="text-xs font-semibold uppercase tracking-[.25em] text-white/60">L'idée cadeau parfaite</p>
        <h2 class="font-display text-3xl sm:text-[42px] leading-tight mt-4">Nos coffrets cadeaux</h2>
        <p class="mt-4 text-white/75 max-w-sm leading-relaxed">Des ensembles élégants prêts à offrir, pour toutes les occasions : anniversaire, Tabaski, Korité, fête des pères…</p>
        <div class="mt-8"><a href="<?= e(url('coffrets.php')) ?>" class="btn btn-primary !bg-forest hover:!bg-[#3a5c44]">Découvrir les coffrets <?= icon('arrow-right', 'w-4 h-4') ?></a></div>
      </div>
      <div class="relative min-h-[240px]">
        <?php if ($giftImage): ?>
          <img src="<?= e(image_url($giftImage, 800)) ?>" alt="" loading="lazy" class="absolute inset-0 w-full h-full object-cover">
          <div class="absolute inset-0 bg-gradient-to-r from-[#1F2620] via-[#1F2620]/30 to-transparent hidden md:block"></div>
        <?php else: ?>
          <div class="absolute inset-0 flex items-center justify-center">
            <div class="relative w-56 h-40 sm:w-72 sm:h-48 rounded-lg bg-forest shadow-2xl rotate-[-4deg] flex items-center justify-center border border-white/10">
              <div class="absolute inset-y-0 left-1/2 w-6 -translate-x-1/2 bg-[#C8A24A]/80"></div>
              <div class="absolute inset-x-0 top-1/2 h-6 -translate-y-1/2 bg-[#C8A24A]/80"></div>
              <span class="relative font-display text-lg sm:text-xl tracking-wide bg-forest px-4 py-1"><?= e(SHOP_NAME) ?></span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- Nouveautés -->
  <section class="max-w-site mx-auto px-4 sm:px-6 pt-16">
    <div class="flex items-end justify-between mb-7">
      <div><p class="text-xs font-semibold uppercase tracking-[.25em] text-forest">Fraîchement arrivés</p><h2 class="font-display text-3xl sm:text-4xl mt-2">Nouveautés</h2></div>
      <a href="<?= e(url('boutique.php?sort=new')) ?>" class="group text-sm font-semibold inline-flex items-center gap-1.5">Tout voir <span class="transition-transform group-hover:translate-x-1"><?= icon('arrow-right', 'w-4 h-4') ?></span></a>
    </div>
    <?php if (!$products): ?>
      <div class="rounded-2xl border border-dashed border-line bg-white/60 p-10 text-center text-muted">Les nouveautés arrivent très bientôt. Revenez nous voir !</div>
    <?php else: ?>
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8 sm:gap-x-6">
        <?php foreach ($products as $product) echo product_card($product); ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Partenaire : TUUBAA ATAABA HIGH TECH -->
  <section class="max-w-site mx-auto px-4 sm:px-6 pt-16">
    <a href="https://tuubaa-ataaba-hightech.com/" target="_blank" rel="noopener" class="reveal group block rounded-2xl bg-[#1F2620] text-white p-8 sm:p-10 overflow-hidden relative">
      <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="flex items-center gap-5">
          <span class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-white flex items-center justify-center shrink-0 overflow-hidden p-2">
            <img src="https://tuubaa-ataaba-hightech.com/logo.webp" alt="TUUBAA ATAABA HIGH TECH" loading="lazy" class="max-w-full max-h-full object-contain" onerror="this.parentElement.style.display='none'">
          </span>
          <div>
            <p class="text-xs font-semibold uppercase tracking-[.25em] text-[#C8A24A]">Découvrez aussi</p>
            <h2 class="font-display text-2xl sm:text-3xl mt-2">TUUBAA ATAABA HIGH TECH</h2>
            <p class="text-white/75 mt-2 max-w-xl">Écouteurs, casques, powerbanks, chargeurs, ring lights… Accessoires et appareils électroniques à Dakar, livrés partout au Sénégal.</p>
          </div>
        </div>
        <span class="btn bg-white text-forest-dark group-hover:bg-cream shrink-0 inline-flex items-center gap-1.5">Visiter la boutique <span class="transition-transform group-hover:translate-x-1"><?= icon('arrow-right', 'w-4 h-4') ?></span></span>
      </div>
    </a>
  </section>

  <!-- WhatsApp -->
  <section class="max-w-site mx-auto px-4 sm:px-6 pt-16">
    <div class="reveal rounded-2xl border border-line bg-white p-8 sm:p-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
      <div class="flex items-start gap-5">
        <span class="w-14 h-14 rounded-full bg-[#25D366]/10 text-[#1DA851] flex items-center justify-center shrink-0"><?= icon('whatsapp', 'w-7 h-7') ?></span>
        <div><h2 class="font-display text-2xl">Besoin d'un conseil de taille ou de style ?</h2><p class="text-muted mt-2">Notre équipe vous répond sur WhatsApp et vous aide à composer votre tenue.</p></div>
      </div>
      <a href="<?= e(whatsapp_url('Bonjour, j\'aimerais un conseil de style.')) ?>" target="_blank" rel="noopener" class="btn btn-outline shrink-0">Discuter avec nous</a>
    </div>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
