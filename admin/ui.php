<?php
/** Composants d'interface de l'administration. */
declare(strict_types=1);

const ORDER_STATUSES = [
    'nouvelle' => ['Nouvelle', 'bg-amber-50 text-amber-800 ring-amber-200'],
    'payee' => ['Payée', 'bg-emerald-50 text-emerald-800 ring-emerald-200'],
    'en_preparation' => ['En préparation', 'bg-orange-50 text-orange-800 ring-orange-200'],
    'expediee' => ['Expédiée', 'bg-sky-50 text-sky-800 ring-sky-200'],
    'livree' => ['Livrée', 'bg-forest/10 text-forest ring-forest/20'],
    'annulee' => ['Annulée', 'bg-gray-100 text-gray-500 ring-gray-200'],
];

function status_badge(string $status): string
{
    [$label, $cls] = ORDER_STATUSES[$status] ?? [$status, 'bg-gray-100 text-gray-600 ring-gray-200'];
    return '<span class="inline-flex items-center h-7 px-2.5 rounded-md text-xs font-semibold ring-1 ring-inset whitespace-nowrap ' . $cls . '">' . e($label) . '</span>';
}

function fcfa(int $n): string
{
    return number_format($n, 0, ',', ' ') . ' FCFA';
}

function short_num(int|float $n): string
{
    if ($n >= 1_000_000) return rtrim(rtrim(number_format($n / 1_000_000, 1, ',', ''), '0'), ',') . 'M';
    if ($n >= 1_000) return rtrim(rtrim(number_format($n / 1_000, 1, ',', ''), '0'), ',') . 'k';
    return (string) (int) $n;
}

function rel_date(string $datetime): string
{
    $t = strtotime($datetime);
    if (date('Y-m-d', $t) === date('Y-m-d')) return "Aujourd'hui, " . date('H:i', $t);
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('-1 day'))) return 'Hier, ' . date('H:i', $t);
    return date('d/m/Y, H:i', $t);
}

/** Variation en % entre deux périodes. */
function trend(float $current, float $previous): string
{
    if ($previous <= 0) {
        return $current > 0
            ? '<span class="inline-flex items-center gap-1 text-xs font-semibold whitespace-nowrap text-forest">' . icon('trend-up', 'w-3.5 h-3.5') . 'Nouveau</span>'
            : '<span class="text-xs text-muted">—</span>';
    }
    $pct = ($current - $previous) / $previous * 100;
    $up = $pct >= 0;
    return '<span class="inline-flex items-center gap-1 text-xs font-semibold whitespace-nowrap ' . ($up ? 'text-forest' : 'text-terra') . '">'
        . icon($up ? 'trend-up' : 'trend-down', 'w-3.5 h-3.5') . ($up ? '+' : '') . number_format($pct, 1, ',', ' ') . ' %</span>';
}

/** Chemin lissé (Catmull-Rom -> Bézier) passant par les points. */
function smooth_path(array $pts): string
{
    if (!$pts) return '';
    $d = 'M' . round($pts[0][0], 1) . ',' . round($pts[0][1], 1);
    $n = count($pts);
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = $pts[max(0, $i - 1)]; $p1 = $pts[$i]; $p2 = $pts[$i + 1]; $p3 = $pts[min($n - 1, $i + 2)];
        $c1 = [$p1[0] + ($p2[0] - $p0[0]) / 6, $p1[1] + ($p2[1] - $p0[1]) / 6];
        $c2 = [$p2[0] - ($p3[0] - $p1[0]) / 6, $p2[1] - ($p3[1] - $p1[1]) / 6];
        $d .= ' C' . round($c1[0], 1) . ',' . round($c1[1], 1) . ' ' . round($c2[0], 1) . ',' . round($c2[1], 1) . ' ' . round($p2[0], 1) . ',' . round($p2[1], 1);
    }
    return $d;
}

/** Mini-courbe de tendance pour les cartes indicateurs. */
function sparkline(array $values, string $id): string
{
    $w = 120; $h = 44; $n = max(2, count($values));
    $max = max(1, ...$values); $min = min(0, ...$values);
    $pts = [];
    foreach (array_values($values) as $i => $v) $pts[] = [$i * $w / ($n - 1), $h - 4 - (($v - $min) / max(1, $max - $min)) * ($h - 8)];
    $line = smooth_path($pts);
    return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="w-28 h-11" preserveAspectRatio="none" aria-hidden="true">'
        . '<defs><linearGradient id="sg' . $id . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2E4A36" stop-opacity=".28"/><stop offset="1" stop-color="#2E4A36" stop-opacity="0"/></linearGradient></defs>'
        . '<path d="' . $line . ' L' . $w . ',' . $h . ' L0,' . $h . ' Z" fill="url(#sg' . $id . ')"/>'
        . '<path d="' . $line . '" fill="none" stroke="#2E4A36" stroke-width="2" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg>';
}

/**
 * Graphique d'évolution (période actuelle vs précédente).
 * $series = ['labels'=>[], 'current'=>[], 'previous'=>[]]
 */
function sales_chart(array $series): string
{
    $W = 760; $H = 260; $L = 52; $R = 16; $T = 14; $B = 34;
    $labels = $series['labels']; $cur = $series['current']; $prev = $series['previous'];
    $n = count($labels);
    $peak = max(1, ...$cur, ...$prev);
    // Graduation « ronde » : environ 4 intervalles de 1, 2, 2,5 ou 5 × 10^n.
    $raw = $peak / 4;
    $mag = 10 ** floor(log10($raw));
    $norm = $raw / $mag;
    $step = ($norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 2.5 ? 2.5 : ($norm <= 5 ? 5 : 10)))) * $mag;
    $top = $step * ceil($peak / $step);
    $x = fn(int $i) => $L + ($n > 1 ? $i * ($W - $L - $R) / ($n - 1) : ($W - $L - $R) / 2);
    $y = fn(float $v) => $T + ($H - $T - $B) * (1 - $v / $top);
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="w-full h-auto" role="img" aria-label="Évolution des ventes">';
    $svg .= '<defs><linearGradient id="chartFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2E4A36" stop-opacity=".16"/><stop offset="1" stop-color="#2E4A36" stop-opacity="0"/></linearGradient></defs>';
    for ($v = 0; $v <= $top + 0.1; $v += $step) {
        $yy = round($y($v), 1);
        $svg .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $yy . '" y2="' . $yy . '" stroke="#E7E1D6" stroke-dasharray="' . ($v == 0 ? '0' : '3 4') . '"/>';
        $svg .= '<text x="' . ($L - 10) . '" y="' . ($yy + 4) . '" text-anchor="end" font-size="12" fill="#6E6A62">' . short_num($v) . '</text>';
    }
    $every = max(1, (int) ceil($n / 10));
    foreach ($labels as $i => $lab) {
        if ($i % $every && $i !== $n - 1) continue;
        $svg .= '<text x="' . round($x($i), 1) . '" y="' . ($H - 10) . '" text-anchor="middle" font-size="12" fill="#6E6A62">' . e($lab) . '</text>';
    }
    $pc = []; $pp = [];
    foreach ($cur as $i => $v) $pc[] = [$x($i), $y($v)];
    foreach ($prev as $i => $v) $pp[] = [$x($i), $y($v)];
    $svg .= '<path d="' . smooth_path($pc) . ' L' . round($x($n - 1), 1) . ',' . $y(0) . ' L' . $x(0) . ',' . $y(0) . ' Z" fill="url(#chartFill)"/>';
    $svg .= '<path d="' . smooth_path($pp) . '" fill="none" stroke="#D9C6A3" stroke-width="2.5" stroke-linecap="round"/>';
    $svg .= '<path d="' . smooth_path($pc) . '" fill="none" stroke="#2E4A36" stroke-width="3" stroke-linecap="round"/>';
    $dots = $n <= 14;
    foreach ($pp as $i => [$px, $py]) if ($dots) $svg .= '<circle cx="' . round($px, 1) . '" cy="' . round($py, 1) . '" r="4" fill="#D9C6A3" stroke="#fff" stroke-width="2"/>';
    foreach ($pc as $i => [$px, $py]) {
        $svg .= '<g class="chart-pt">'
            . '<rect x="' . round($px - ($W - $L - $R) / max(1, $n - 1) / 2, 1) . '" y="' . $T . '" width="' . round(($W - $L - $R) / max(1, $n - 1), 1) . '" height="' . ($H - $T - $B) . '" fill="transparent"/>'
            . ($dots ? '<circle cx="' . round($px, 1) . '" cy="' . round($py, 1) . '" r="5" fill="#2E4A36" stroke="#fff" stroke-width="2"/>' : '')
            . '<title>' . e($labels[$i]) . ' : ' . fcfa((int) $cur[$i]) . ' (période précédente : ' . fcfa((int) ($prev[$i] ?? 0)) . ')</title></g>';
    }
    return $svg . '</svg>';
}

function bar_chart(array $labels, array $values, string $unit = 'FCFA'): string
{
    $max = max(1, ...$values);
    $html = '<div class="flex items-end gap-2 sm:gap-3 h-56">';
    foreach ($values as $i => $v) {
        $h = max(2, (int) round($v / $max * 100));
        $html .= '<div class="flex-1 flex flex-col items-center justify-end h-full group" title="' . e($labels[$i] . ' : ' . number_format((int) $v, 0, ',', ' ') . ' ' . $unit) . '">'
            . '<span class="text-[10px] text-muted mb-1 opacity-0 group-hover:opacity-100 transition">' . short_num($v) . '</span>'
            . '<div class="w-full rounded-t-md bg-forest/80 group-hover:bg-forest transition-all" style="height:' . $h . '%"></div>'
            . '<span class="mt-2 text-[11px] text-muted whitespace-nowrap">' . e($labels[$i]) . '</span></div>';
    }
    return $html . '</div>';
}

function card_open(string $title = '', string $iconName = '', string $action = ''): string
{
    $head = $title !== '' ? '<div class="flex items-center justify-between gap-3 mb-5"><h2 class="font-display text-xl flex items-center gap-2.5">' . ($iconName ? '<span class="text-forest">' . icon($iconName, 'w-5 h-5') . '</span>' : '') . e($title) . '</h2>' . $action . '</div>' : '';
    return '<section class="bg-white rounded-2xl border border-line shadow-[0_1px_2px_rgba(0,0,0,.03)] p-5 sm:p-6">' . $head;
}

function see_all(string $href, string $label = 'Voir tout'): string
{
    return '<a href="' . e($href) . '" class="text-sm font-semibold inline-flex items-center gap-1.5 hover:text-forest">' . e($label) . ' ' . icon('arrow-right', 'w-4 h-4') . '</a>';
}

function empty_state(string $text, string $iconName = 'package'): string
{
    return '<div class="py-10 text-center text-muted"><span class="mx-auto w-12 h-12 rounded-full bg-cream flex items-center justify-center text-forest/70">' . icon($iconName, 'w-6 h-6') . '</span><p class="mt-3 text-sm">' . e($text) . '</p></div>';
}

/**
 * Bouton « Partager » d'un produit (WhatsApp, Facebook, TikTok/Instagram via le téléphone, copie du lien).
 * La fenêtre de partage est commune à toute l'administration (voir layout-end.php).
 */
function share_button(array $p, string $class = 'btn btn-outline btn-sm !px-2.5', bool $withLabel = false): string
{
    require_once dirname(__DIR__) . '/includes/orders.php';
    $readyDays = product_ready_days($p['ready_days'] ?? null);
    $text = $p['name'] . ' — ' . money((int) $p['price'])
        . (!empty($p['old_price']) && (int) $p['old_price'] > (int) $p['price'] ? ' au lieu de ' . money((int) $p['old_price']) : '')
        . ($readyDays ? ' · Sur commande, prêt en ' . days_label($readyDays) : '');
    $image = !empty($p['image']) ? image_url((string) $p['image'], 800) : '';
    return '<button type="button" class="' . e($class) . '" title="Partager" data-share-product'
        . ' data-name="' . e($p['name']) . '" data-text="' . e($text) . '"'
        . ' data-url="' . e(site_url('produit.php?id=' . (int) $p['id'])) . '" data-image="' . e($image) . '"'
        . ' data-active="' . (!empty($p['active']) ? '1' : '0') . '">'
        . icon('external', 'w-4 h-4') . ($withLabel ? ' Partager' : '') . '</button>';
}

function product_thumb(?string $image, string $class = 'w-12 h-14'): string
{
    return '<span class="' . $class . ' rounded-lg overflow-hidden bg-[#EDE7DC] shrink-0 flex items-center justify-center">'
        . ($image ? '<img src="' . e(image_url($image, 400)) . '" alt="" class="w-full h-full object-cover" loading="lazy" decoding="async">' : '<img src="' . e(url('assets/img/brand/logo-mark.png')) . '" alt="" class="w-2/3 opacity-25">')
        . '</span>';
}
