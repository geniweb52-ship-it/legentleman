<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$zones = $pdo->query('SELECT name, fee FROM delivery_zones WHERE active=1 ORDER BY fee, name')->fetchAll();
$pageTitle = 'Livraison & échanges';
$pageIntro = 'Livraison rapide à Dakar et partout au Sénégal. Offerte dès ' . money(FREE_DELIVERY_THRESHOLD) . " d'achat.";
ob_start(); ?>
<h2 class="!mt-0">Tarifs par zone</h2>
<div class="rounded-xl border border-line bg-white divide-y divide-line">
  <?php foreach ($zones as $z): ?><div class="flex justify-between gap-4 px-5 py-3.5 text-sm"><span class="flex items-center gap-2"><?= icon('map-pin', 'w-4 h-4 text-forest shrink-0') ?><?= e($z['name']) ?></span><span class="font-semibold tabular-nums whitespace-nowrap"><?= money((int) $z['fee']) ?></span></div><?php endforeach; ?>
</div>
<h2>Délais</h2>
<ul><li>Dakar et banlieue : 24 à 48 h.</li><li>Régions : 2 à 4 jours ouvrés.</li><li>Nous vous appelons ou vous écrivons sur WhatsApp avant chaque livraison.</li></ul>
<h2>Échanges</h2>
<p>La taille ne convient pas ? Vous disposez de 7 jours après réception pour demander un échange sur WhatsApp. L'article doit être non porté, non lavé et dans son emballage d'origine. Les frais de livraison de l'échange restent à la charge du client, sauf erreur de notre part.</p>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
