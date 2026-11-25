<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/themes.php';
$holiday = active_holiday_theme($pdo);
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isNewArrivals = $currentPage === 'boutique.php' && ($_GET['sort'] ?? '') === 'new' && empty($_GET['cat']);
$navLinks = [
    ['Boutique', url('boutique.php'), $currentPage === 'boutique.php' && !$isNewArrivals],
    ['Coffrets', url('coffrets.php'), $currentPage === 'coffrets.php'],
    ['Nouveautés', url('boutique.php?sort=new'), $isNewArrivals],
    ['À propos', url('a-propos.php'), $currentPage === 'a-propos.php'],
];
$flash = take_flash();
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? SHOP_NAME) ?> | <?= e(SHOP_NAME) ?></title>
  <meta name="description" content="<?= e($pageDescription ?? 'Vêtements, chaussures, accessoires et coffrets cadeaux pour l\'homme moderne. Livraison rapide à Dakar et partout au Sénégal.') ?>">
  <meta name="theme-color" content="<?= e($holiday['colors'][0] ?? '#2E4A36') ?>">
  <meta property="og:title" content="<?= e(($pageTitle ?? SHOP_NAME) . ' | ' . SHOP_NAME) ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= e($pageImage ?? url('assets/img/brand/logo.png')) ?>">
  <link rel="icon" type="image/png" href="<?= e(url('assets/img/brand/favicon.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e(url('assets/img/brand/apple-touch-icon.png')) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Caslon+Text:wght@400;700&display=swap" rel="stylesheet">
  <style>/* Secours : panneaux et notification restent cachés même si le CSS tarde à charger */ .is-hidden{display:none!important} .toast{opacity:0;pointer-events:none} .toast.show{opacity:1;pointer-events:auto}</style>
  <link rel="stylesheet" href="<?= e(asset('assets/app.css')) ?>">
  <?= $holiday ? holiday_theme_style($holiday) : '' ?>
</head>
<body class="bg-cream text-ink font-sans antialiased<?= $holiday ? ' theme-' . e($holiday['key']) : '' ?>">
  <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:bg-white focus:px-4 focus:py-2 focus:rounded">Aller au contenu</a>

  <?php if (!empty($holiday['preview'])): ?>
    <div class="bg-ink text-white text-xs py-2 px-4 flex items-center justify-center gap-3">
      <span>👁️ Aperçu du thème <strong><?= e($holiday['name']) ?></strong>, visible par vous seul</span>
      <a href="?apercu_theme=0" class="underline underline-offset-2">Quitter l'aperçu</a>
    </div>
  <?php endif; ?>

  <?php if ($holiday): ?>
  <a href="<?= e(holiday_link($holiday['cta_link'])) ?>" class="holiday-banner block text-white text-xs sm:text-[13px] overflow-hidden">
    <div class="max-w-site mx-auto px-4 min-h-9 py-2 flex items-center justify-center gap-2 font-medium tracking-wide text-center">
      <span class="text-base leading-none" aria-hidden="true"><?= e($holiday['emoji']) ?></span>
      <span><?= e($holiday['banner_text']) ?></span>
      <span class="hidden sm:inline-flex items-center opacity-80"><?= icon('arrow-right', 'w-3.5 h-3.5') ?></span>
    </div>
  </a>
  <?php else: ?>
  <div class="bg-forest text-white text-xs sm:text-[13px] overflow-hidden">
    <div class="max-w-site mx-auto px-4 h-9 flex items-center justify-center gap-2 font-medium tracking-wide">
      <?= icon('truck', 'w-4 h-4') ?>
      <span>Livraison rapide à Dakar et partout au Sénégal</span>
      <span class="hidden md:inline opacity-50">·</span>
      <span class="hidden md:inline opacity-90">Livraison offerte dès <?= money(FREE_DELIVERY_THRESHOLD) ?></span>
    </div>
  </div>
  <?php endif; ?>

  <header id="site-header" class="sticky top-0 z-40 bg-cream/90 backdrop-blur-md border-b border-line transition-shadow">
    <nav class="max-w-site mx-auto px-4 sm:px-6 h-[72px] flex items-center justify-between gap-4">
      <div class="flex items-center gap-2">
        <button type="button" class="icon-btn lg:hidden -ml-2" data-open="mobile-menu" aria-label="Ouvrir le menu"><?= icon('menu', 'w-6 h-6') ?></button>
        <a href="<?= e(url()) ?>" class="flex items-center gap-2.5 sm:gap-3 leading-none" aria-label="<?= e(SHOP_NAME) ?> — accueil">
          <img src="<?= e(url('assets/img/brand/logo-mark.png')) ?>" alt="" width="275" height="220" class="h-9 sm:h-12 w-auto">
          <span class="border-l border-line pl-2 sm:pl-3">
            <span class="block font-display text-[14px] min-[400px]:text-[16px] sm:text-[22px] tracking-[.03em] whitespace-nowrap text-forest-dark"><?= e(SHOP_NAME) ?></span>
            <span class="hidden sm:block text-[11.5px] text-muted mt-1"><?= e(SHOP_TAGLINE) ?></span>
          </span>
        </a>
      </div>
      <div class="hidden lg:flex items-center gap-9">
        <?php foreach ($navLinks as [$label, $href, $active]): ?>
          <a href="<?= e($href) ?>" class="nav-link <?= $active ? 'is-active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="flex items-center gap-0.5 sm:gap-1 -mr-2">
        <button type="button" class="icon-btn" data-open="search-panel" aria-label="Rechercher"><?= icon('search', 'w-[22px] h-[22px]') ?></button>
        <a href="<?= e(url('suivi.php')) ?>" class="icon-btn hidden sm:inline-flex" aria-label="Suivre ma commande" title="Suivre ma commande"><?= icon('user', 'w-[22px] h-[22px]') ?></a>
        <a href="<?= e(url('favoris.php')) ?>" class="icon-btn hidden min-[380px]:inline-flex" aria-label="Mes favoris" title="Mes favoris"><?= icon('heart', 'w-[22px] h-[22px]') ?><span class="cart-badge !bg-terra is-hidden" data-wish-count>0</span></a>
        <a href="<?= e(url('panier.php')) ?>" class="icon-btn" aria-label="Voir le panier" title="Panier"><?= icon('bag', 'w-[22px] h-[22px]') ?><span class="cart-badge" data-cart-count><?= cart_count() ?></span></a>
      </div>
    </nav>
  </header>

  <!-- Recherche -->
  <div id="search-panel" class="fixed inset-0 z-[60] is-hidden" role="dialog" aria-modal="true" aria-label="Recherche">
    <div class="overlay absolute inset-0 bg-ink/40" data-close></div>
    <div class="relative bg-cream shadow-2xl fade-up" style="animation-duration:.4s">
      <form action="<?= e(url('boutique.php')) ?>" method="get" class="max-w-3xl mx-auto px-4 py-8 sm:py-12">
        <div class="flex items-center justify-between mb-5"><p class="font-display text-2xl">Que recherchez-vous ?</p><button type="button" class="icon-btn" data-close aria-label="Fermer"><?= icon('x', 'w-6 h-6') ?></button></div>
        <div class="flex items-center gap-3 border-b-2 border-ink pb-3">
          <?= icon('search', 'w-6 h-6 text-muted') ?>
          <input name="q" type="search" placeholder="Polo, chemise, baskets, coffret…" class="flex-1 bg-transparent text-lg sm:text-xl outline-none placeholder:text-muted/70" autocomplete="off">
          <button class="btn btn-primary !min-h-11 !px-5">Rechercher</button>
        </div>
        <div class="mt-5 flex flex-wrap gap-2 text-sm">
          <span class="text-muted mr-1 py-1.5">Populaire :</span>
          <?php foreach (['Polo', 'Chemise', 'Jean', 'Baskets', 'Coffret'] as $term): ?>
            <a href="<?= e(url('boutique.php?q=' . rawurlencode($term))) ?>" class="px-3.5 py-1.5 rounded-full border border-line bg-white hover:border-ink transition-colors"><?= e($term) ?></a>
          <?php endforeach; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- Menu mobile -->
  <div id="mobile-menu" class="fixed inset-0 z-[60] is-hidden lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
    <div class="overlay absolute inset-0 bg-ink/40" data-close></div>
    <aside class="drawer absolute inset-y-0 left-0 w-[86%] max-w-sm bg-cream flex flex-col -translate-x-full">
      <div class="flex items-center justify-between px-5 h-[72px] border-b border-line">
        <span class="flex items-center gap-2.5"><img src="<?= e(url('assets/img/brand/logo-mark.png')) ?>" alt="" class="h-9 w-auto"><span class="font-display text-lg text-forest-dark"><?= e(SHOP_NAME) ?></span></span>
        <button type="button" class="icon-btn -mr-2" data-close aria-label="Fermer le menu"><?= icon('x', 'w-6 h-6') ?></button>
      </div>
      <nav class="flex-1 overflow-y-auto px-5 py-4">
        <?php foreach ($navLinks as [$label, $href, $active]): ?>
          <a href="<?= e($href) ?>" class="flex items-center justify-between py-4 border-b border-line text-lg <?= $active ? 'text-forest font-semibold' : '' ?>"><?= e($label) ?><?= icon('chevron-right', 'w-5 h-5 text-muted') ?></a>
        <?php endforeach; ?>
        <div class="mt-6 grid gap-3 text-[15px]">
          <a href="<?= e(url('favoris.php')) ?>" class="flex items-center gap-3"><?= icon('heart') ?> Mes favoris</a>
          <a href="<?= e(url('suivi.php')) ?>" class="flex items-center gap-3"><?= icon('package') ?> Suivre ma commande</a>
          <a href="<?= e(url('guide-tailles.php')) ?>" class="flex items-center gap-3"><?= icon('ruler') ?> Guide des tailles</a>
          <a href="<?= e(url('livraison.php')) ?>" class="flex items-center gap-3"><?= icon('truck') ?> Livraison & échanges</a>
        </div>
      </nav>
      <div class="p-5 border-t border-line">
        <a href="<?= e(whatsapp_url('Bonjour LE GENTLEMAN, j\'ai une question.')) ?>" target="_blank" rel="noopener" class="btn btn-primary w-full"><?= icon('whatsapp') ?> Écrire sur WhatsApp</a>
      </div>
    </aside>
  </div>

  <!-- Notification -->
  <div id="toast" class="toast fixed z-[70] left-4 right-4 bottom-4 sm:left-auto sm:right-6 sm:bottom-6 sm:w-[380px] bg-ink text-white rounded-xl shadow-2xl p-4 flex items-start gap-3 pointer-events-none" role="status" aria-live="polite">
    <span class="mt-0.5 w-7 h-7 rounded-full bg-white/15 flex items-center justify-center shrink-0" data-toast-icon><?= icon('check', 'w-4 h-4') ?></span>
    <div class="flex-1 min-w-0"><p class="text-sm font-medium" data-toast-message></p><a href="<?= e(url('panier.php')) ?>" class="text-xs underline underline-offset-2 opacity-80 hover:opacity-100 mt-1 inline-block is-hidden" data-toast-link>Voir le panier</a></div>
  </div>
  <?php if ($flash): ?><script>window.__flash = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script><?php endif; ?>
  <div id="contenu"></div>
