<?php
/**
 * Gabarit de l'administration (à inclure APRÈS le traitement des formulaires).
 * Variables : $pageTitle, $pageSubtitle (facultatif), $pageActions (HTML facultatif).
 * Fermer la page avec : require __DIR__ . '/layout-end.php';
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$me = admin_user($pdo);
$current = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('nouvelle','payee')")->fetchColumn();
$lowStock = (int) $pdo->query('SELECT COUNT(*) FROM variants v JOIN products p ON p.id=v.product_id WHERE p.active=1 AND v.stock<=2')->fetchColumn();
$notifOrders = $pdo->query("SELECT reference, customer_name, total, created_at, status FROM orders WHERE status IN ('nouvelle','payee') ORDER BY created_at DESC LIMIT 5")->fetchAll();
$notifCount = $pendingOrders + ($lowStock > 0 ? 1 : 0);
$flash = take_flash();

$nav = [
    ['index.php', 'Tableau de bord', 'home', null],
    ['produits.php', 'Produits', 'package', null],
    ['categories.php', 'Catégories', 'list', null],
    ['stock.php', 'Stocks', 'layers', $lowStock ?: null],
    ['commandes.php', 'Commandes', 'cart', $pendingOrders ?: null],
    ['clients.php', 'Clients', 'users', null],
    ['coffrets.php', 'Coffrets', 'gift', null],
    ['promotions.php', 'Promotions', 'tag', null],
    ['themes.php', 'Thèmes de fête', 'sparkle', null],
    ['livraison.php', 'Livraisons', 'truck', null],
    ['statistiques.php', 'Statistiques', 'chart', null],
    ['qrcode.php', 'QR codes', 'qr', null],
    ['parametres.php', 'Paramètres', 'settings', null],
];
if ($me['role'] === 'gerant') array_splice($nav, 11, 0, [['comptes.php', 'Comptes', 'user', null]]);
// Chaque rôle ne voit que les pages auxquelles il a accès.
$nav = array_values(array_filter($nav, fn($item) => admin_can_access((string) $me['role'], $item[0])));
$home = admin_home((string) $me['role']);
$initials = strtoupper(substr($me['email'], 0, 1));
$sideImg = is_file(dirname(__DIR__) . '/assets/img/demo/coffret.webp') ? url('assets/img/demo/coffret-400.webp') : null;
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($pageTitle ?? 'Administration') ?> · Admin <?= e(SHOP_NAME) ?></title>
  <link rel="icon" type="image/png" href="<?= e(url('assets/img/brand/favicon.png')) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Caslon+Text:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('assets/admin.css')) ?>">
</head>
<body class="bg-cream text-ink font-sans">
<div class="min-h-screen lg:flex">
  <!-- Barre latérale -->
  <div id="side-overlay" class="fixed inset-0 z-40 bg-ink/40 hidden lg:hidden" data-side-close></div>
  <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-[272px] bg-sidebar text-white flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-300 lg:sticky lg:top-0 lg:h-screen shrink-0">
    <a href="<?= e($home) ?>" class="block px-6 pt-7 pb-6 text-center">
      <img src="<?= e(url('assets/img/brand/logo-mark-light.png')) ?>" alt="" class="h-14 w-auto mx-auto">
      <span class="block font-display text-[21px] tracking-[.05em] mt-2"><?= e(SHOP_NAME) ?></span>
      <span class="block text-[12px] text-white/60 mt-0.5">Administration</span>
    </a>
    <nav class="flex-1 overflow-y-auto sidebar-scroll px-3 pb-4 space-y-1" aria-label="Menu administration">
      <?php foreach ($nav as [$href, $label, $ic, $badge]): $active = $current === $href; ?>
        <a href="<?= e($href) ?>" class="nav-item <?= $active ? 'active' : '' ?>" <?= $active ? 'aria-current="page"' : '' ?>>
          <?= icon($ic, 'w-5 h-5 shrink-0') ?><span class="flex-1"><?= e($label) ?></span>
          <?php if ($badge): ?><span class="min-w-6 h-6 px-1.5 rounded-full text-[11px] font-bold flex items-center justify-center <?= $href === 'stock.php' ? 'bg-terra/90 text-white' : 'bg-[#E9DFC9] text-sidebar' ?>"><?= (int) $badge ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if (admin_can_access((string) $me['role'], 'coffrets.php')): ?>
    <div class="p-4">
      <a href="coffrets.php" class="relative block overflow-hidden rounded-2xl border border-white/10 h-40 group">
        <?php if ($sideImg): ?><img src="<?= e($sideImg) ?>" alt="" class="absolute inset-0 w-full h-full object-cover opacity-70 group-hover:scale-105 transition duration-700"><?php endif; ?>
        <span class="absolute inset-0 bg-gradient-to-t from-sidebar via-sidebar/60 to-transparent"></span>
        <span class="absolute left-4 right-4 bottom-4">
          <span class="block font-display text-xl leading-tight">Offrez avec<br>élégance</span>
          <span class="mt-2 inline-flex items-center gap-1.5 rounded-md bg-[#F3EBDB] text-sidebar text-xs font-semibold px-3 py-1.5">Voir les coffrets <?= icon('arrow-right', 'w-3.5 h-3.5') ?></span>
        </span>
      </a>
    </div>
    <?php endif; ?>
  </aside>

  <div class="flex-1 min-w-0 flex flex-col">
    <!-- Barre du haut -->
    <header class="sticky top-0 z-30 bg-cream/90 backdrop-blur border-b border-line">
      <div class="h-[72px] px-4 sm:px-6 lg:px-8 flex items-center gap-3 sm:gap-5">
        <button type="button" class="lg:hidden w-10 h-10 rounded-lg hover:bg-white flex items-center justify-center shrink-0" data-side-open aria-label="Ouvrir le menu"><?= icon('menu', 'w-6 h-6') ?></button>
        <form action="recherche.php" method="get" class="flex-1 max-w-xl relative">
          <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><?= icon('search', 'w-[18px] h-[18px]') ?></span>
          <input name="q" type="search" value="<?= e($current === 'recherche.php' ? ($_GET['q'] ?? '') : '') ?>" placeholder="Rechercher un produit, une commande, un client…" class="w-full h-11 pl-10 pr-4 rounded-xl border border-line bg-white text-sm outline-none focus:border-forest focus:ring-4 focus:ring-forest/10 transition">
        </form>
        <div class="ml-auto flex items-center gap-1 sm:gap-3">
          <a href="<?= e(url()) ?>" target="_blank" class="hidden xl:inline-flex btn btn-outline btn-sm"><?= icon('external', 'w-4 h-4') ?> Voir la boutique</a>
          <details class="relative" data-dropdown>
            <summary class="relative w-11 h-11 rounded-full hover:bg-white flex items-center justify-center" aria-label="Notifications">
              <?= icon('bell', 'w-[22px] h-[22px]') ?>
              <?php if ($notifCount): ?><span class="absolute top-1.5 right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-terra text-white text-[10px] font-bold flex items-center justify-center ring-2 ring-cream"><?= $notifCount > 9 ? '9+' : $notifCount ?></span><?php endif; ?>
            </summary>
            <div class="absolute right-0 mt-2 w-[320px] max-w-[90vw] bg-white rounded-2xl border border-line shadow-xl overflow-hidden fade-in">
              <p class="px-4 py-3 font-semibold text-sm border-b border-line">Notifications</p>
              <?php if ($lowStock): ?><a href="stock.php?filtre=bas" class="flex gap-3 px-4 py-3 hover:bg-cream border-b border-line text-sm"><span class="w-8 h-8 rounded-full bg-terra/10 text-terra flex items-center justify-center shrink-0"><?= icon('layers', 'w-4 h-4') ?></span><span><strong><?= $lowStock ?> déclinaison<?= $lowStock > 1 ? 's' : '' ?></strong> presque épuisée<?= $lowStock > 1 ? 's' : '' ?> (≤ 2)</span></a><?php endif; ?>
              <?php foreach ($notifOrders as $n): ?>
                <a href="commandes.php?ref=<?= e(rawurlencode($n['reference'])) ?>" class="flex gap-3 px-4 py-3 hover:bg-cream border-b border-line text-sm">
                  <span class="w-8 h-8 rounded-full bg-forest/10 text-forest flex items-center justify-center shrink-0"><?= icon('cart', 'w-4 h-4') ?></span>
                  <span class="min-w-0"><span class="block font-medium truncate"><?= e($n['customer_name']) ?> · <?= fcfa((int) $n['total']) ?></span><span class="block text-xs text-muted"><?= e($n['reference']) ?> · <?= e(rel_date((string) $n['created_at'])) ?></span></span>
                </a>
              <?php endforeach; ?>
              <?php if (!$notifCount): ?><p class="px-4 py-6 text-sm text-muted text-center">Tout est à jour.</p><?php endif; ?>
              <a href="commandes.php" class="block px-4 py-3 text-center text-sm font-semibold text-forest hover:bg-cream">Toutes les commandes</a>
            </div>
          </details>
          <details class="relative" data-dropdown>
            <summary class="flex items-center gap-3 pl-1 pr-2 py-1 rounded-full hover:bg-white">
              <span class="relative w-10 h-10 rounded-full bg-forest text-white font-display text-lg flex items-center justify-center"><?= e($initials) ?><span class="absolute bottom-0 right-0 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-cream"></span></span>
              <span class="hidden sm:block leading-tight text-left"><span class="block text-sm font-semibold max-w-[150px] truncate"><?= e(ucfirst(explode('@', $me['email'])[0])) ?></span><span class="block text-xs text-muted"><?= e(ADMIN_ROLES[$me['role']] ?? $me['role']) ?></span></span>
              <?= icon('chevron-down', 'w-4 h-4 hidden sm:block') ?>
            </summary>
            <div class="absolute right-0 mt-2 w-64 bg-white rounded-2xl border border-line shadow-xl overflow-hidden fade-in text-sm">
              <div class="px-4 py-3 border-b border-line"><p class="font-semibold truncate"><?= e($me['email']) ?></p><p class="text-xs text-muted"><?= e(ADMIN_ROLES[$me['role']] ?? '') ?></p></div>
              <a href="parametres.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-cream"><?= icon('settings', 'w-4 h-4') ?> Paramètres & mot de passe</a>
              <a href="<?= e(url()) ?>" target="_blank" class="flex items-center gap-3 px-4 py-2.5 hover:bg-cream"><?= icon('external', 'w-4 h-4') ?> Voir la boutique</a>
              <a href="logout.php" class="flex items-center gap-3 px-4 py-2.5 hover:bg-cream text-terra border-t border-line"><?= icon('logout', 'w-4 h-4') ?> Déconnexion</a>
            </div>
          </details>
        </div>
      </div>
    </header>

    <?php if ($flash): ?>
      <div id="toast" class="toast fixed z-[70] right-4 bottom-4 left-4 sm:left-auto sm:w-[360px] rounded-xl shadow-2xl p-4 text-sm font-medium flex items-start gap-3 <?= $flash['type'] === 'error' ? 'bg-terra text-white' : 'bg-forest text-white' ?>" role="status">
        <?= icon($flash['type'] === 'error' ? 'x' : 'check', 'w-5 h-5 shrink-0') ?><span><?= e($flash['message']) ?></span>
      </div>
    <?php endif; ?>

    <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 lg:py-8 fade-in">
      <?php if (!empty($pageTitle) && empty($hidePageHeader)): ?>
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
          <div><h1 class="font-display text-3xl sm:text-[34px] leading-tight"><?= e($pageTitle) ?></h1><?php if (!empty($pageSubtitle)): ?><p class="mt-1 text-muted"><?= e($pageSubtitle) ?></p><?php endif; ?></div>
          <?php if (!empty($pageActions)): ?><div class="flex flex-wrap gap-2"><?= $pageActions ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
