<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';
require_once dirname(__DIR__) . '/includes/themes.php';

holiday_tables_ensure($pdo);

/** Image d'accueil d'un thème : ré-encodée en WebP comme les photos produits. */
function theme_upload(string $field, array &$errors): ?string
{
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > 15 * 1024 * 1024) { $errors[] = 'Image trop lourde (15 Mo maximum).'; return null; }
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Échec de l\'envoi de l\'image.'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { $errors[] = 'Format accepté : JPG, PNG ou WebP.'; return null; }
    $dir = dirname(__DIR__) . '/assets/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = date('Ymd') . '-theme-' . bin2hex(random_bytes(6));
    if (!image_optimize($file['tmp_name'], $dir . '/' . $name)) { $errors[] = 'Image illisible ou corrompue.'; return null; }
    return url('assets/uploads/' . $name . '.webp');
}

$validDate = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_post_guard();
    $action = (string) ($_POST['form_action'] ?? '');
    $key = (string) ($_POST['theme'] ?? '');

    if ($action === 'mode') {
        $mode = (string) ($_POST['mode'] ?? 'off');
        if ($mode !== 'auto' && $mode !== 'off' && !isset(HOLIDAY_THEMES[$mode])) $mode = 'off';
        setting_set($pdo, 'holiday_theme_mode', $mode);
        flash(match (true) {
            $mode === 'off' => 'Thèmes de fête désactivés : la boutique reprend son apparence habituelle.',
            $mode === 'auto' => 'Mode automatique activé : chaque fête s\'affichera toute seule à ses dates.',
            default => 'Thème « ' . HOLIDAY_THEMES[$mode]['name'] . ' » activé sur la boutique.',
        });
        redirect('themes.php');
    }

    if (!isset(HOLIDAY_THEMES[$key])) { flash('Fête inconnue.', 'error'); redirect('themes.php'); }

    if ($action === 'reset') {
        $pdo->prepare('DELETE FROM holiday_themes WHERE theme_key=?')->execute([$key]);
        flash('« ' . HOLIDAY_THEMES[$key]['name'] . ' » : textes et dates d\'origine rétablis.');
        redirect('themes.php#theme-' . $key);
    }

    if ($action === 'save') {
        $errors = [];
        $start = trim((string) ($_POST['start_date'] ?? ''));
        $end = trim((string) ($_POST['end_date'] ?? ''));
        if (!$validDate($start) || !$validDate($end)) $errors[] = 'Indiquez une date de début et une date de fin valides.';
        elseif (empty(HOLIDAY_THEMES[$key]['yearly']) && $end < $start) $errors[] = 'La date de fin doit être après la date de début.';
        $ctaUrl = trim((string) ($_POST['cta_url'] ?? ''));
        if ($ctaUrl !== '' && !preg_match('~^(https?://|[a-z0-9_\-]+\.php)~i', $ctaUrl)) $errors[] = 'Lien du bouton : une page du site (ex. boutique.php) ou une adresse complète (https://…).';
        $image = theme_upload('image', $errors);

        if ($errors) {
            flash(implode(' ', $errors), 'error');
            redirect('themes.php#theme-' . $key);
        }
        $current = $pdo->prepare('SELECT image FROM holiday_themes WHERE theme_key=?');
        $current->execute([$key]);
        $currentImage = $current->fetchColumn() ?: null;
        if (!empty($_POST['remove_image'])) $currentImage = null;

        $cut = fn(string $field, int $max) => mb_substr(trim((string) ($_POST[$field] ?? '')), 0, $max);
        $pdo->prepare('INSERT INTO holiday_themes (theme_key, banner, title, text, cta_label, cta_url, image, start_date, end_date, auto_enabled, effect_enabled)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE banner=VALUES(banner), title=VALUES(title), text=VALUES(text), cta_label=VALUES(cta_label), cta_url=VALUES(cta_url),
              image=VALUES(image), start_date=VALUES(start_date), end_date=VALUES(end_date), auto_enabled=VALUES(auto_enabled), effect_enabled=VALUES(effect_enabled)')
            ->execute([
                $key, $cut('banner', 255), $cut('title', 160), $cut('text', 500), $cut('cta_label', 60), mb_substr($ctaUrl, 0, 255),
                $image ?? $currentImage, $start, $end, empty($_POST['auto_enabled']) ? 0 : 1, empty($_POST['effect_enabled']) ? 0 : 1,
            ]);
        flash('« ' . HOLIDAY_THEMES[$key]['name'] . ' » enregistré.');
        redirect('themes.php#theme-' . $key);
    }
    redirect('themes.php');
}

$mode = holiday_mode($pdo);
$themes = holiday_themes($pdo);
$today = date('Y-m-d');

// Thème réellement visible par les clients en ce moment (sans tenir compte d'un aperçu).
$live = null;
if ($mode === 'auto') {
    foreach ($themes as $t) if ($t['auto_enabled'] && holiday_in_window($t)) { $live = $t; break; }
} elseif ($mode !== 'off') {
    $live = $themes[$mode] ?? null;
}

// Ordre d'affichage : la prochaine fête en premier.
uasort($themes, function ($a, $b) {
    $rank = fn($t) => holiday_in_window($t) ? '0000' : (empty($t['yearly']) && $t['end_date'] < date('Y-m-d') ? '9999' : holiday_next_start($t));
    return strcmp($rank($a), $rank($b));
});

$fmt = fn(string $d) => date_fr($d);
$pageTitle = 'Thèmes de fête';
$pageSubtitle = 'Changez l\'ambiance de la boutique pour chaque fête : couleurs, bandeau, accueil et petite animation.';
require __DIR__ . '/layout.php';
?>
<div class="grid gap-5">
  <!-- État actuel et mode -->
  <?= card_open('Sur la boutique en ce moment', 'sparkle') ?>
    <div class="flex flex-col lg:flex-row lg:items-center gap-5 justify-between">
      <div class="flex items-center gap-4">
        <?php if ($live): ?>
          <span class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl shrink-0" style="background:<?= e($live['colors'][0]) ?>1A"><?= e($live['emoji']) ?></span>
          <div>
            <p class="font-display text-2xl"><?= e($live['name']) ?></p>
            <p class="text-sm text-muted mt-0.5"><?= $mode === 'auto' ? 'Activé automatiquement · jusqu\'au ' . e($fmt($live['end_date'])) : 'Activé manuellement · reste en ligne jusqu\'à ce que vous le changiez' ?></p>
          </div>
        <?php else: ?>
          <span class="w-14 h-14 rounded-2xl bg-cream border border-line flex items-center justify-center text-forest shrink-0"><?= icon('home', 'w-6 h-6') ?></span>
          <div>
            <p class="font-display text-2xl">Apparence habituelle</p>
            <p class="text-sm text-muted mt-0.5"><?= $mode === 'auto' ? 'Mode automatique : aucune fête en cours aujourd\'hui.' : 'Aucun thème de fête actif.' ?></p>
          </div>
        <?php endif; ?>
      </div>
      <form method="post" class="flex flex-wrap gap-2">
        <?= csrf_field() ?>
        <input type="hidden" name="form_action" value="mode">
        <button name="mode" value="auto" class="btn <?= $mode === 'auto' ? 'btn-primary' : 'btn-outline' ?>"><?= icon('calendar', 'w-4 h-4') ?> Automatique selon les dates<?= $mode === 'auto' ? ' ✓' : '' ?></button>
        <button name="mode" value="off" class="btn <?= $mode === 'off' ? 'btn-primary' : 'btn-outline' ?>"><?= $mode === 'off' ? 'Aucun thème ✓' : 'Désactiver les thèmes' ?></button>
      </form>
    </div>
    <div class="mt-5 grid sm:grid-cols-3 gap-3 text-sm">
      <div class="rounded-xl bg-cream border border-line p-4"><p class="font-semibold">Manuel</p><p class="text-muted mt-1">Cliquez sur « Activer maintenant » sous une fête : elle reste en ligne jusqu'à ce que vous changiez.</p></div>
      <div class="rounded-xl bg-cream border border-line p-4"><p class="font-semibold">Automatique</p><p class="text-muted mt-1">Chaque fête s'affiche toute seule entre sa date de début et sa date de fin.</p></div>
      <div class="rounded-xl bg-cream border border-line p-4"><p class="font-semibold">Aperçu</p><p class="text-muted mt-1">Voyez le rendu avant de l'activer : seul vous le voyez, les clients ne voient rien.</p></div>
    </div>
  </section>

  <!-- Liste des fêtes -->
  <div class="grid md:grid-cols-2 2xl:grid-cols-3 gap-5 items-start">
    <?php foreach ($themes as $key => $t):
      $isLive = $live && $live['key'] === $key;
      $isManual = $mode === $key;
      $outdated = empty($t['yearly']) && $t['end_date'] < $today;
      [$c1, $c2, $c3] = $t['colors'];
    ?>
      <section id="theme-<?= e($key) ?>" class="bg-white rounded-2xl border <?= $isLive ? 'border-forest ring-2 ring-forest/20' : 'border-line' ?> overflow-hidden scroll-mt-24">
        <div class="relative h-28 px-5 flex items-center gap-4 text-white" style="background:linear-gradient(120deg, <?= e($c2) ?>, <?= e($c1) ?> 60%, <?= e($c3) ?>)">
          <span class="text-4xl drop-shadow" aria-hidden="true"><?= e($t['emoji']) ?></span>
          <div class="min-w-0">
            <p class="font-display text-2xl leading-tight"><?= e($t['name']) ?></p>
            <p class="text-xs text-white/80 mt-1">Animation : <?= e(HOLIDAY_EFFECTS[$t['effect']] ?? '—') ?><?= !$t['effect_enabled'] && $t['effect'] !== 'none' ? ' (désactivée)' : '' ?></p>
          </div>
          <?php if ($isLive): ?><span class="absolute top-3 right-3 text-[11px] font-bold uppercase tracking-wide bg-white text-ink rounded-full px-2.5 py-1">● En ligne</span><?php endif; ?>
        </div>
        <div class="p-5">
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1.5 rounded-md bg-cream border border-line px-2.5 py-1"><?= icon('calendar', 'w-3.5 h-3.5') ?>
              <?= e($fmt($t['start_date'])) ?> → <?= e($fmt($t['end_date'])) ?><?= !empty($t['yearly']) ? ' · chaque année' : '' ?>
            </span>
            <?php if ($outdated): ?><span class="rounded-md bg-terra/10 text-terra px-2.5 py-1 font-semibold">Dates passées : à mettre à jour</span>
            <?php elseif (!$t['auto_enabled']): ?><span class="rounded-md bg-cream text-muted px-2.5 py-1">Pas d'activation automatique</span><?php endif; ?>
          </div>
          <p class="mt-4 text-sm"><span class="font-semibold">Bandeau :</span> <?= e($t['emoji'] . ' ' . $t['banner_text']) ?></p>
          <p class="mt-2 text-sm text-muted"><span class="font-semibold text-ink">Accueil :</span> <?= e(str_replace("\n", ' ', $t['title_text'])) ?></p>

          <div class="mt-5 flex flex-wrap gap-2">
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="form_action" value="mode">
              <button name="mode" value="<?= e($key) ?>" class="btn btn-sm <?= $isManual ? 'btn-primary' : 'btn-outline' ?>" <?= $isManual ? 'disabled' : '' ?>><?= $isManual ? 'Activé ✓' : 'Activer maintenant' ?></button>
            </form>
            <a href="<?= e(url('?apercu_theme=' . rawurlencode($key))) ?>" target="_blank" class="btn btn-outline btn-sm"><?= icon('eye', 'w-4 h-4') ?> Aperçu</a>
          </div>

          <details class="mt-5 border-t border-line pt-4" <?= ($_GET['edit'] ?? '') === $key ? 'open' : '' ?>>
            <summary class="text-sm font-semibold text-forest inline-flex items-center gap-1.5"><?= icon('edit', 'w-4 h-4') ?> Personnaliser textes, dates et image</summary>
            <form method="post" enctype="multipart/form-data" class="mt-4 space-y-3">
              <?= csrf_field() ?>
              <input type="hidden" name="form_action" value="save">
              <input type="hidden" name="theme" value="<?= e($key) ?>">
              <label class="block"><span class="label">Texte du bandeau (haut du site)</span><input name="banner" maxlength="255" class="field" value="<?= e($t['banner_text']) ?>"></label>
              <label class="block"><span class="label">Grand titre de l'accueil <span class="font-normal text-muted">(retour à la ligne permis)</span></span><textarea name="title" rows="2" maxlength="160" class="field"><?= e($t['title_text']) ?></textarea></label>
              <label class="block"><span class="label">Texte de l'accueil</span><textarea name="text" rows="2" maxlength="500" class="field"><?= e($t['body_text']) ?></textarea></label>
              <div class="grid grid-cols-2 gap-3">
                <label class="block"><span class="label">Bouton</span><input name="cta_label" maxlength="60" class="field" value="<?= e($t['cta_text']) ?>"></label>
                <label class="block"><span class="label">Lien du bouton</span><input name="cta_url" maxlength="255" class="field" value="<?= e($t['cta_link']) ?>" placeholder="boutique.php"></label>
                <label class="block"><span class="label">Début</span><input type="date" name="start_date" class="field" value="<?= e($t['start_date']) ?>" required></label>
                <label class="block"><span class="label">Fin</span><input type="date" name="end_date" class="field" value="<?= e($t['end_date']) ?>" required></label>
              </div>
              <?php if (!empty($t['yearly'])): ?><p class="text-xs text-muted">Date fixe : seuls le jour et le mois comptent, le thème revient chaque année.</p>
              <?php else: ?><p class="text-xs text-muted">Date qui change chaque année : pensez à la mettre à jour.</p><?php endif; ?>
              <label class="block"><span class="label">Image de l'accueil <span class="font-normal text-muted">(facultatif, sinon l'image habituelle)</span></span><input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-cream file:px-3 file:py-2 file:font-semibold"></label>
              <?php if ($t['image'] !== ''): ?>
                <div class="flex items-center gap-3"><img src="<?= e(image_url($t['image'], 400)) ?>" alt="" class="w-20 h-14 object-cover rounded-lg border border-line"><label class="text-xs text-muted inline-flex items-center gap-2"><input type="checkbox" name="remove_image" value="1"> Retirer l'image</label></div>
              <?php endif; ?>
              <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                <label class="inline-flex items-center gap-2"><input type="checkbox" name="auto_enabled" value="1" class="w-4 h-4 accent-[#2E4A36]" <?= $t['auto_enabled'] ? 'checked' : '' ?>> Activation automatique</label>
                <?php if ($t['effect'] !== 'none'): ?><label class="inline-flex items-center gap-2"><input type="checkbox" name="effect_enabled" value="1" class="w-4 h-4 accent-[#2E4A36]" <?= $t['effect_enabled'] ? 'checked' : '' ?>> Animation (<?= e(mb_strtolower(HOLIDAY_EFFECTS[$t['effect']])) ?>)</label>
                <?php else: ?><input type="hidden" name="effect_enabled" value="1"><?php endif; ?>
              </div>
              <div class="flex flex-wrap gap-2 pt-1">
                <button class="btn btn-primary btn-sm">Enregistrer</button>
                <?php if ($t['customized']): ?><button name="form_action" value="reset" class="btn btn-outline btn-sm" data-confirm="Rétablir les textes et dates d'origine de « <?= e($t['name']) ?> » ?" formnovalidate>Rétablir par défaut</button><?php endif; ?>
              </div>
            </form>
          </details>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
  <p class="text-xs text-muted">Korité, Tabaski, Magal et Gamou suivent le calendrier lunaire ; la fête des mères et la fête des pères tombent un dimanche différent chaque année ; Black Friday est le dernier vendredi de novembre. Les dates proposées sont approximatives : vérifiez-les chaque année.</p>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>
