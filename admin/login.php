<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
if (!empty($_SESSION['admin_id'])) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) { http_response_code(400); exit('Session expirée, rechargez la page.'); }
    // Limitation simple des tentatives (5 essais, puis attente de 5 minutes).
    $_SESSION['login_fails'] = array_filter($_SESSION['login_fails'] ?? [], fn($t) => $t > time() - 300);
    if (count($_SESSION['login_fails']) >= 5) {
        $error = 'Trop de tentatives. Réessayez dans quelques minutes.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM admins WHERE email=? AND active=1');
        $stmt->execute([strtolower(trim((string) ($_POST['email'] ?? '')))]);
        $admin = $stmt->fetch();
        if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_fails']);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_role'] = $admin['role'];
            redirect('index.php');
        }
        $_SESSION['login_fails'][] = time();
        $error = 'E-mail ou mot de passe incorrect.';
    }
}
$side = is_file(dirname(__DIR__) . '/assets/img/hero.webp') ? url('assets/img/hero.webp') : null;
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
  <title>Connexion · Admin <?= e(SHOP_NAME) ?></title>
  <link rel="icon" type="image/png" href="<?= e(url('assets/img/brand/favicon.png')) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Libre+Caslon+Text:wght@400;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(asset('assets/admin.css')) ?>">
</head>
<body class="min-h-screen bg-cream text-[#1D1D1B] font-sans antialiased grid lg:grid-cols-2">
  <div class="hidden lg:block relative bg-sidebar overflow-hidden">
    <?php if ($side): ?><img src="<?= e(image_url($side, 800)) ?>" alt="" class="absolute inset-0 w-full h-full object-cover object-[70%_30%] opacity-80"><?php endif; ?>
    <div class="absolute inset-0 bg-gradient-to-t from-sidebar via-sidebar/50 to-sidebar/10"></div>
    <div class="absolute left-12 bottom-12 right-12 text-white">
      <img src="<?= e(url('assets/img/brand/logo-mark-light.png')) ?>" alt="" class="h-14 w-auto">
      <p class="font-display text-4xl mt-6 leading-tight">L'élégance,<br>sans compromis.</p>
      <p class="mt-3 text-white/70">Gérez votre boutique, vos commandes et vos clients.</p>
    </div>
  </div>
  <main class="flex items-center justify-center px-5 py-12">
    <form method="post" class="w-full max-w-sm">
      <img src="<?= e(url('assets/img/brand/logo.png')) ?>" alt="<?= e(SHOP_NAME) ?>" class="w-48 h-auto mx-auto">
      <h1 class="font-display text-3xl text-center mt-8">Administration</h1>
      <p class="text-center text-muted text-sm mt-2">Connectez-vous pour continuer</p>
      <?php if ($error): ?><p class="mt-6 rounded-xl bg-terra/10 text-terra text-sm p-3 text-center" role="alert"><?= e($error) ?></p><?php endif; ?>
      <?= csrf_field() ?>
      <label class="block mt-6"><span class="block text-sm font-semibold mb-1.5">E-mail</span><input type="email" name="email" required autofocus autocomplete="username" value="<?= e($_POST['email'] ?? '') ?>" class="w-full h-12 rounded-xl border border-line bg-white px-4 outline-none focus:border-forest focus:ring-4 focus:ring-forest/10"></label>
      <label class="block mt-4"><span class="block text-sm font-semibold mb-1.5">Mot de passe</span><input type="password" name="password" required autocomplete="current-password" class="w-full h-12 rounded-xl border border-line bg-white px-4 outline-none focus:border-forest focus:ring-4 focus:ring-forest/10"></label>
      <button class="mt-7 w-full h-12 rounded-xl bg-forest hover:bg-sidebar text-white font-semibold transition">Se connecter</button>
      <a href="<?= e(url()) ?>" class="block text-center text-sm text-muted hover:text-[#1D1D1B] mt-6">← Retour à la boutique</a>
    </form>
  </main>
</body>
</html>
