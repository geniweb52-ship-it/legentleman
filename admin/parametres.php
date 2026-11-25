<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once dirname(__DIR__) . '/includes/orders.php';
$me = admin_user($pdo);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $st = $pdo->prepare('SELECT password_hash FROM admins WHERE id=?'); $st->execute([(int) $me['id']]);
    $new = (string) ($_POST['new'] ?? '');
    if (!password_verify((string) ($_POST['current'] ?? ''), (string) $st->fetchColumn())) $error = 'Mot de passe actuel incorrect.';
    elseif (strlen($new) < 10) $error = 'Le nouveau mot de passe doit contenir au moins 10 caractères.';
    elseif ($new !== (string) ($_POST['confirm'] ?? '')) $error = 'Les deux mots de passe ne correspondent pas.';
    else {
        $pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), (int) $me['id']]);
        session_regenerate_id(true);
        flash('Mot de passe modifié.');
        redirect('parametres.php');
    }
}

$checks = [
    ['Numéro WhatsApp de la boutique', phone_display(WHATSAPP_NUMBER), true, 'WHATSAPP_NUMBER'],
    ['Lien de paiement Wave', WAVE_PAYMENT_LINK !== '' ? 'Configuré' : 'Non configuré', WAVE_PAYMENT_LINK !== '', 'WAVE_PAYMENT_LINK'],
    ['API Wave (montant prérempli + retour automatique)', WAVE_API_KEY === '' ? 'Clé API non renseignée' : (wave_api_enabled() ? 'Active' : 'Clé présente, site non https'), wave_api_enabled(), 'WAVE_API_KEY, SITE_URL'],
    ['Webhook Wave (confirmation automatique)', WAVE_WEBHOOK_SECRET !== '' ? 'Configuré' : 'Non configuré (facultatif)', WAVE_WEBHOOK_SECRET !== '', 'WAVE_WEBHOOK_SECRET'],
    ['Lien Orange Money', ORANGE_PAYMENT_LINK !== '' ? 'Configuré' : 'Envoyé manuellement sur WhatsApp', ORANGE_PAYMENT_LINK !== '', 'ORANGE_PAYMENT_LINK'],
    ['Livraison offerte dès', fcfa(FREE_DELIVERY_THRESHOLD), true, 'FREE_DELIVERY_THRESHOLD'],
    ['Adresse du site', site_url(), str_starts_with(site_url(), 'https://'), 'SITE_URL'],
];
$pageTitle = 'Paramètres';
$pageSubtitle = 'Votre compte et la configuration de la boutique';
require __DIR__ . '/layout.php';
?>
<div class="grid xl:grid-cols-[1fr_400px] gap-5 items-start">
  <?= card_open('Configuration de la boutique', 'settings') ?>
    <ul class="divide-y divide-line">
      <?php foreach ($checks as [$label, $value, $ok, $const]): ?>
        <li class="py-3.5 flex flex-wrap items-center gap-3">
          <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 <?= $ok ? 'bg-forest/10 text-forest' : 'bg-amber-50 text-amber-700' ?>"><?= icon($ok ? 'check' : 'bell', 'w-4 h-4') ?></span>
          <div class="flex-1 min-w-[200px]"><p class="font-medium text-sm"><?= e($label) ?></p><p class="text-xs text-muted"><code><?= e($const) ?></code></p></div>
          <span class="text-sm <?= $ok ? '' : 'text-amber-800' ?> break-all"><?= e($value) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="mt-4 text-xs text-muted leading-relaxed">Ces réglages se modifient dans le fichier <code>config.php</code> à la racine du site (par sécurité, ils ne sont pas modifiables depuis le navigateur). La clé API Wave se crée dans le portail Wave Business, rubrique Développeurs.</p>
  </section>
  <?= card_open('Mon mot de passe', 'shield') ?>
    <p class="text-sm text-muted mb-4">Connecté en tant que <strong class="text-ink"><?= e($me['email']) ?></strong> · <?= e(ADMIN_ROLES[$me['role']] ?? '') ?></p>
    <?php if ($error): ?><p class="mb-4 text-sm text-terra"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="space-y-4"><?= csrf_field() ?>
      <label class="block"><span class="label">Mot de passe actuel</span><input type="password" name="current" required class="field" autocomplete="current-password"></label>
      <label class="block"><span class="label">Nouveau mot de passe</span><input type="password" name="new" minlength="10" required class="field" autocomplete="new-password"></label>
      <label class="block"><span class="label">Confirmer</span><input type="password" name="confirm" minlength="10" required class="field" autocomplete="new-password"></label>
      <button class="btn btn-primary w-full">Changer le mot de passe</button>
    </form>
  </section>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
