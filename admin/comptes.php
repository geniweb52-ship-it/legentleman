<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_role($pdo);
$me = admin_user($pdo);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $action = (string) ($_POST['form_action'] ?? 'add');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'add') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $role = (string) ($_POST['role'] ?? 'vendeur');
        $exists = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE email=?'); $exists->execute([$email]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || !isset(ADMIN_ROLES[$role])) $error = 'E-mail valide et mot de passe de 10 caractères minimum requis.';
        elseif ($exists->fetchColumn()) $error = 'Un compte existe déjà avec cet e-mail.';
        else {
            $pdo->prepare('INSERT INTO admins (email,password_hash,role,active) VALUES (?,?,?,1)')->execute([$email, password_hash($password, PASSWORD_DEFAULT), $role]);
            flash('Compte ' . $email . ' créé.');
            redirect('comptes.php');
        }
    } elseif ($id && $id !== (int) $me['id']) {
        if ($action === 'toggle') $pdo->prepare('UPDATE admins SET active=1-active WHERE id=?')->execute([$id]);
        if ($action === 'role' && isset(ADMIN_ROLES[$_POST['role'] ?? ''])) $pdo->prepare('UPDATE admins SET role=? WHERE id=?')->execute([$_POST['role'], $id]);
        flash('Compte mis à jour.');
        redirect('comptes.php');
    } else {
        flash('Vous ne pouvez pas modifier votre propre compte ici.', 'error');
        redirect('comptes.php');
    }
}
$accounts = $pdo->query('SELECT id, email, role, active FROM admins ORDER BY active DESC, role, email')->fetchAll();
$pageTitle = 'Comptes';
$pageSubtitle = 'Accès à l\'administration';
require __DIR__ . '/layout.php';
?>
<div class="grid xl:grid-cols-[1fr_360px] gap-5 items-start">
  <?= card_open('Équipe', 'users') ?>
    <ul class="divide-y divide-line">
      <?php foreach ($accounts as $a): $self = (int) $a['id'] === (int) $me['id']; ?>
        <li class="py-3.5 flex flex-wrap items-center gap-3 <?= $a['active'] ? '' : 'opacity-55' ?>">
          <span class="w-10 h-10 rounded-full bg-forest text-white font-display flex items-center justify-center"><?= e(strtoupper($a['email'][0])) ?></span>
          <div class="flex-1 min-w-[180px]"><p class="font-medium"><?= e($a['email']) ?> <?= $self ? '<span class="text-xs text-muted">(vous)</span>' : '' ?></p><p class="text-xs text-muted"><?= $a['active'] ? 'Actif' : 'Désactivé' ?></p></div>
          <?php if ($self): ?>
            <span class="text-sm font-medium"><?= e(ADMIN_ROLES[$a['role']] ?? $a['role']) ?></span>
          <?php else: ?>
            <form method="post" class="flex gap-2"><?= csrf_field() ?><input type="hidden" name="form_action" value="role"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><select name="role" class="field !h-9 !w-auto text-sm" data-autosubmit><?php foreach (ADMIN_ROLES as $k => $l): ?><option value="<?= $k ?>" <?= $a['role'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></form>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="form_action" value="toggle"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm <?= $a['active'] ? 'btn-danger' : 'btn-outline' ?>" <?= $a['active'] ? 'data-confirm="Désactiver ce compte ? La personne ne pourra plus se connecter."' : '' ?>><?= $a['active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?= card_open('Nouveau compte', 'plus') ?>
    <?php if ($error): ?><p class="mb-4 text-sm text-terra"><?= e($error) ?></p><?php endif; ?>
    <form method="post" class="space-y-4"><?= csrf_field() ?><input type="hidden" name="form_action" value="add">
      <label class="block"><span class="label">E-mail</span><input type="email" name="email" required class="field" value="<?= e($_POST['email'] ?? '') ?>"></label>
      <label class="block"><span class="label">Mot de passe <span class="font-normal text-muted">(10 caractères min.)</span></span><input type="password" name="password" minlength="10" required class="field" autocomplete="new-password"></label>
      <label class="block"><span class="label">Rôle</span><select name="role" class="field"><?php foreach (ADMIN_ROLES as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'vendeur' ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      <button class="btn btn-primary w-full"><?= icon('plus', 'w-4 h-4') ?> Créer le compte</button>
      <p class="text-xs text-muted">Seul le gérant peut gérer les comptes. Transmettez le mot de passe en main propre ; la personne pourra le changer dans Paramètres.</p>
    </form>
  </section>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
