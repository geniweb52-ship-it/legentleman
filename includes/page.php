<?php
/**
 * Gabarit des pages d'information.
 * Variables attendues : $pageTitle, $pageIntro (facultatif), $pageBody (HTML de confiance).
 */
require __DIR__ . '/header.php';
?>
<main class="max-w-site mx-auto px-4 sm:px-6 pt-6">
  <?= breadcrumb([[$pageTitle, null]]) ?>
  <div class="mt-6 max-w-3xl">
    <h1 class="font-display text-4xl sm:text-5xl"><?= e($pageTitle) ?></h1>
    <?php if (!empty($pageIntro)): ?><p class="mt-4 text-lg text-muted leading-relaxed"><?= e($pageIntro) ?></p><?php endif; ?>
  </div>
  <div class="mt-10 max-w-3xl prose-shop"><?= $pageBody ?></div>
</main>
<?php require __DIR__ . '/footer.php'; ?>
