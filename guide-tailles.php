<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Guide des tailles';
$pageIntro = 'Mesurez-vous près du corps, sans serrer. Entre deux tailles ? Prenez la plus grande pour un tombé confortable.';
$table = function (string $title, array $head, array $rows): string {
    $h = '<h2>' . e($title) . '</h2><div class="overflow-x-auto rounded-xl border border-line bg-white"><table class="w-full text-sm text-left"><thead class="bg-cream"><tr>';
    foreach ($head as $c) $h .= '<th class="px-4 py-3 font-semibold whitespace-nowrap">' . e($c) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr class="border-t border-line">';
        foreach ($r as $i => $c) $h .= '<td class="px-4 py-3 whitespace-nowrap tabular-nums ' . ($i === 0 ? 'font-semibold' : 'text-muted') . '">' . e($c) . '</td>';
        $h .= '</tr>';
    }
    return $h . '</tbody></table></div>';
};
ob_start();
echo $table('Hauts : t-shirts, polos, chemises', ['Taille', 'Tour de poitrine', 'Tour de taille', 'Tour de cou'], [
    ['S', '88 – 94 cm', '76 – 82 cm', '37 – 38'], ['M', '95 – 101 cm', '83 – 89 cm', '39 – 40'], ['L', '102 – 108 cm', '90 – 96 cm', '41 – 42'],
    ['XL', '109 – 115 cm', '97 – 103 cm', '43 – 44'], ['XXL', '116 – 122 cm', '104 – 110 cm', '45 – 46'],
]);
echo $table('Bas : jeans, pantalons, shorts', ['Taille', 'Tour de taille', 'Tour de hanches', 'Équivalence US'], [
    ['38', '76 – 79 cm', '92 – 95 cm', 'W29 – 30'], ['40', '80 – 83 cm', '96 – 99 cm', 'W31 – 32'], ['42', '84 – 87 cm', '100 – 103 cm', 'W33'],
    ['44', '88 – 92 cm', '104 – 107 cm', 'W34 – 35'], ['46', '93 – 97 cm', '108 – 111 cm', 'W36'],
]);
echo $table('Chaussures', ['EU', 'UK', 'US', 'Longueur du pied'], [
    ['40', '6.5', '7', '25,4 cm'], ['41', '7', '8', '26 cm'], ['42', '8', '9', '26,7 cm'], ['43', '9', '10', '27,3 cm'], ['44', '9.5', '10.5', '28 cm'], ['45', '10.5', '11.5', '28,6 cm'],
]); ?>
<div class="mt-10 rounded-2xl bg-forest text-white p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
  <div><p class="!mb-0 font-display text-xl !text-white">Vous hésitez encore ?</p><p class="!mb-0 !text-white/75 text-sm mt-1">Envoyez-nous votre taille et votre poids, nous vous conseillons.</p></div>
  <a href="<?= e(whatsapp_url("Bonjour, j'aimerais un conseil de taille.")) ?>" target="_blank" rel="noopener" class="btn btn-light shrink-0"><?= icon('whatsapp') ?> Conseil taille</a>
</div>
<?php $pageBody = ob_get_clean();
require __DIR__ . '/includes/page.php';
