<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Contact';
$pageIntro = 'Une question sur une taille, une livraison ou une commande ? Nous vous répondons rapidement.';
$display = phone_display(SHOP_PHONE);
ob_start(); ?>
<div class="grid sm:grid-cols-2 gap-4">
  <a href="<?= e(whatsapp_url("Bonjour LE GENTLEMAN, j'ai une question.")) ?>" target="_blank" rel="noopener" class="rounded-2xl bg-white border border-line p-6 hover:border-forest transition-colors">
    <span class="w-12 h-12 rounded-full bg-[#25D366]/10 text-[#1DA851] flex items-center justify-center"><?= icon('whatsapp', 'w-6 h-6') ?></span>
    <p class="!mb-0 mt-4 font-semibold !text-ink">WhatsApp</p><p class="!mb-0 text-sm mt-1">Réponse rapide, 24 h/24, 7 j/7</p>
    <p class="!mb-0 mt-4 text-sm font-semibold !text-forest inline-flex items-center gap-1.5">Démarrer la discussion <?= icon('arrow-right', 'w-4 h-4') ?></p>
  </a>
  <a href="tel:+<?= e(SHOP_PHONE) ?>" class="rounded-2xl bg-white border border-line p-6 hover:border-forest transition-colors">
    <span class="w-12 h-12 rounded-full bg-forest/10 text-forest flex items-center justify-center"><?= icon('phone', 'w-6 h-6') ?></span>
    <p class="!mb-0 mt-4 font-semibold !text-ink">Téléphone</p><p class="!mb-0 text-sm mt-1">24 h/24, 7 j/7</p>
    <p class="!mb-0 mt-4 text-sm font-semibold !text-forest tabular-nums"><?= e($display) ?></p>
  </a>
</div>
<h2>Questions fréquentes</h2>
<div class="divide-y divide-line border-y border-line">
  <?php foreach ([
    ['Quels sont les délais de livraison ?', '24 à 48 h à Dakar, 2 à 4 jours ouvrés dans les régions.'],
    ['Comment payer ma commande ?', 'Par Wave, Orange Money ou en espèces à la livraison.'],
    ['Puis-je échanger un article ?', "Oui, sous 7 jours si la taille ne convient pas, article non porté et dans son état d'origine."],
    ['Comment suivre ma commande ?', 'Rendez-vous sur la page « Suivre ma commande » avec votre référence et votre numéro.'],
  ] as [$q, $a]): ?>
    <details class="py-4"><summary class="flex items-center justify-between font-semibold text-ink"><?= e($q) ?><span class="chev transition-transform"><?= icon('chevron-down', 'w-4 h-4') ?></span></summary><p class="pt-3 !mb-0"><?= e($a) ?></p></details>
  <?php endforeach; ?>
</div>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
