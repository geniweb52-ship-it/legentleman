<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'À propos';
$pageIntro = "LE GENTLEMAN est née d'une conviction simple : l'élégance masculine doit être accessible, au quotidien.";
ob_start(); ?>
<p>Nous sélectionnons des vêtements, chaussures et accessoires qui allient coupe soignée, matières de qualité et prix justes. Chaque pièce est choisie pour durer et se porter aussi bien au bureau qu'en week-end.</p>
<div class="grid sm:grid-cols-3 gap-4 my-10">
  <?php foreach ([['shield', 'Qualité vérifiée', 'Chaque article est contrôlé avant expédition.'], ['sparkle', 'Style intemporel', 'Des basiques bien coupés, faciles à associer.'], ['whatsapp', 'Conseil personnalisé', 'Une équipe disponible sur WhatsApp.']] as [$ic, $t, $d]): ?>
    <div class="rounded-2xl bg-white border border-line p-6"><span class="text-forest"><?= icon($ic, 'w-7 h-7') ?></span><p class="!mb-0 mt-4 font-semibold !text-ink"><?= e($t) ?></p><p class="!mb-0 mt-1 text-sm"><?= e($d) ?></p></div>
  <?php endforeach; ?>
</div>
<h2>Notre engagement</h2>
<p>Livraison rapide à Dakar et dans toutes les régions du Sénégal, paiement simple par Wave, Orange Money ou en espèces, et échange facile si la taille ne convient pas.</p>
<a href="<?= e(url('boutique.php')) ?>" class="btn btn-primary mt-4">Découvrir la collection</a>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
