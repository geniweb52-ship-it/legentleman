<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/** URL d'un fichier statique avec sa date de modification (le navigateur recharge dès qu'il change). */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_valid(): bool
{
    return isset($_POST['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Réglage GeniusPay de config.php ('' si absent : les anciens config.php restent valides). */
function geniuspay_config(string $name): string
{
    return defined($name) ? (string) constant($name) : '';
}

/** Le paiement en ligne GeniusPay est proposé dès que les deux clés API sont renseignées. */
function geniuspay_enabled(): bool
{
    return geniuspay_config('GENIUSPAY_PUBLIC_KEY') !== '' && geniuspay_config('GENIUSPAY_SECRET_KEY') !== '';
}

/** Moyens de paiement acceptés, en une phrase courte (bandeaux de réassurance, FAQ…). */
function payment_methods_text(): string
{
    return geniuspay_enabled()
        ? 'En ligne (Wave, Orange Money, carte) ou à la livraison'
        : 'Wave, Orange Money ou à la livraison';
}

/** Badges des moyens de paiement affichés en pied de page. */
function payment_badges(): array
{
    return geniuspay_enabled() ? ['Wave', 'Orange Money', 'Carte bancaire', 'Espèces'] : ['Wave', 'Orange Money', 'Espèces'];
}

function money(int $amount): string
{
    return number_format($amount, 0, ' ', ' ') . ' FCFA';
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function wants_json(): bool
{
    return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

function cart_count(): int
{
    return (int) array_sum(array_column($_SESSION['cart'] ?? [], 'quantity'));
}

function cart_total(PDO $pdo): int
{
    $total = 0;
    foreach ($_SESSION['cart'] ?? [] as $variantId => $item) {
        $stmt = $pdo->prepare('SELECT p.price FROM variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?');
        $stmt->execute([(int) ($item['variant_id'] ?? (is_numeric($variantId) ? $variantId : 0))]);
        $price = $stmt->fetchColumn();
        if ($price !== false) {
            $total += (int) $price * (int) $item['quantity'];
        }
    }
    return $total;
}

/**
 * Article sur commande (habits traditionnels…) : délai de confection en jours après la commande,
 * ou null s'il est disponible immédiatement.
 */
function product_ready_days(mixed $days): ?int
{
    $days = (int) $days;
    return $days > 0 ? $days : null;
}

/** Date à laquelle l'article sera prêt si on le commande aujourd'hui (format AAAA-MM-JJ). */
function ready_date_from_days(int $days, ?string $from = null): string
{
    return date('Y-m-d', strtotime(($from ?? date('Y-m-d')) . ' +' . $days . ' days'));
}

/** « 15 jours », « 1 jour ». */
function days_label(int $days): string
{
    return $days . ' jour' . ($days > 1 ? 's' : '');
}

/** Date en français : « 12/12/2026 » ou, en version longue, « mercredi 12 décembre ». */
function date_fr(string $date, bool $long = false): string
{
    $time = strtotime($date);
    if ($time === false) return $date;
    if (!$long) return date('d/m/Y', $time);
    $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $label = $days[(int) date('w', $time)] . ' ' . (int) date('j', $time) . ' ' . $months[(int) date('n', $time) - 1];
    return date('Y', $time) !== date('Y') ? $label . ' ' . date('Y', $time) : $label;
}

/** Libellé court affiché au client : « Sur commande · 15 jours ». */
function ready_label(int $days): string
{
    return 'Sur commande · ' . days_label($days);
}

function whatsapp_url(string $message): string
{
    return 'https://wa.me/' . WHATSAPP_NUMBER . '?text=' . rawurlencode($message);
}

/** Numéro au format sénégalais : +221 77 650 26 98 */
function phone_display(string $digits): string
{
    $d = preg_replace('/\D/', '', $digits);
    if (strlen($d) === 12 && str_starts_with($d, '221')) {
        return '+221 ' . substr($d, 3, 2) . ' ' . substr($d, 5, 3) . ' ' . substr($d, 8, 2) . ' ' . substr($d, 10, 2);
    }
    return '+' . $d;
}

/** Nom de couleur simplifié : minuscules, sans accents ni espaces superflus. */
function color_key(string $name): string
{
    $key = mb_strtolower(trim($name), 'UTF-8');
    $key = strtr($key, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ç' => 'c', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'û' => 'u', 'ù' => 'u']);
    return (string) preg_replace('/\s+/', ' ', $key);
}

/** Couleurs connues (nom simplifié -> code hexadécimal). */
function color_map(): array
{
    return [
        'noir' => '#1C1C1C', 'blanc' => '#FFFFFF', 'ecru' => '#F1EBDD', 'creme' => '#F3EAD7', 'ivoire' => '#F6F1E3',
        'bleu marine' => '#1F2D4E', 'marine' => '#1F2D4E', 'bleu' => '#2F5D9B', 'bleu ciel' => '#8DB6DE', 'bleu clair' => '#9CC0E2', 'bleu nuit' => '#1A2238', 'bleu roi' => '#2747A8', 'indigo' => '#2C3E73', 'denim' => '#4A6A93', 'turquoise' => '#2BA3A0',
        'vert' => '#2E4A36', 'vert fonce' => '#24392A', 'vert foret' => '#2E4A36', 'vert olive' => '#6B6B3A', 'olive' => '#6B6B3A', 'kaki' => '#7A7355', 'vert clair' => '#8DB38B', 'vert emeraude' => '#1F7A55', 'emeraude' => '#1F7A55',
        'bordeaux' => '#7A1F2B', 'rouge' => '#B3261E', 'bourgogne' => '#6E1F2A', 'rose' => '#E5A9B4', 'fuchsia' => '#C2307A', 'corail' => '#E2725B', 'saumon' => '#E9967A',
        'beige' => '#D8C3A0', 'sable' => '#D9C7A7', 'camel' => '#C19A6B', 'marron' => '#6B4429', 'maron' => '#6B4429', 'chocolat' => '#4B2E1E', 'cognac' => '#9A5B2E', 'taupe' => '#8B7D6B',
        'gris' => '#9A9A9A', 'gris clair' => '#C9C9C9', 'gris fonce' => '#4F4F4F', 'anthracite' => '#3A3A3A', 'argent' => '#C0C0C0',
        'terracotta' => '#B4532A', 'brique' => '#A0472E', 'assorti' => '#8B6B4A',
        'jaune' => '#E3B740', 'moutarde' => '#C99A2E', 'orange' => '#D9752B', 'violet' => '#5E3B7A', 'mauve' => '#9A7AA0', 'lavande' => '#B7A6D6', 'or' => '#C8A24A', 'dore' => '#C8A24A',
    ];
}

/** Correspondance nom de couleur -> code hexadécimal (couleur unie). */
function color_hex(string $name): string
{
    return color_map()[color_key($name)] ?? '#B9B2A6';
}

/** Éclaircit une couleur hexadécimale (0 = inchangée, 1 = blanc). */
function color_lighten(string $hex, float $amount): string
{
    $rgb = array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    return sprintf('#%02X%02X%02X', ...array_map(fn($c) => (int) round($c + (255 - $c) * $amount), $rgb));
}

/**
 * Fond CSS d'une pastille de couleur : uni (« Noir »), dégradé (« Dégradé bleu », « Dégradé bleu noir »),
 * mélange (« Bleu/Blanc », « Noir et rouge ») ou multicolore (« Multicolore », « Arc-en-ciel »).
 */
function color_background(string $name): string
{
    $key = color_key($name);
    $map = color_map();
    if (isset($map[$key])) return $map[$key];

    if (preg_match('/multicolor|arc.en.ciel|tie.?dye/', $key)) {
        return 'linear-gradient(135deg,#B3261E,#D9752B,#E3B740,#2E7D4F,#2F5D9B,#5E3B7A)';
    }
    $isGradient = (bool) preg_match('/\b(degrade|ombre|nuance)s?\b/', $key);
    $words = preg_replace('/\b(degrade|ombre|nuance)s?\b|\b(de|du|vers|au)\b/', ' ', $key);
    // Repère les couleurs connues, en privilégiant les noms composés (« bleu marine » avant « bleu »).
    $tokens = array_values(array_filter(preg_split('#[\s/,&+\-]+|\bet\b#', (string) $words), fn($t) => $t !== '' && $t !== 'et'));
    $colors = [];
    for ($i = 0; $i < count($tokens); $i++) {
        $pair = isset($tokens[$i + 1]) ? $tokens[$i] . ' ' . $tokens[$i + 1] : '';
        if ($pair !== '' && isset($map[$pair])) { $colors[] = $map[$pair]; $i++; }
        elseif (isset($map[$tokens[$i]])) $colors[] = $map[$tokens[$i]];
    }
    $colors = array_values(array_unique($colors));
    if (!$colors) return $isGradient ? 'linear-gradient(135deg,#1C1C1C,#C9C9C9)' : '#B9B2A6';
    if (count($colors) === 1) {
        // Dégradé d'une seule couleur : de la teinte vers sa version claire.
        return $isGradient ? 'linear-gradient(135deg,' . $colors[0] . ',' . color_lighten($colors[0], 0.7) . ')' : $colors[0];
    }
    // Plusieurs couleurs : fondu si « dégradé », sinon bandes nettes (bicolore, tricolore…).
    if ($isGradient) return 'linear-gradient(135deg,' . implode(',', $colors) . ')';
    $step = 100 / count($colors);
    $stops = [];
    foreach ($colors as $index => $hex) $stops[] = $hex . ' ' . round($index * $step, 2) . '% ' . round(($index + 1) * $step, 2) . '%';
    return 'linear-gradient(135deg,' . implode(',', $stops) . ')';
}

function sort_sizes(array $sizes): array
{
    $order = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL', '4XL', 'TU', 'UNIQUE'];
    usort($sizes, function ($a, $b) use ($order) {
        $ia = array_search(strtoupper((string) $a), $order, true);
        $ib = array_search(strtoupper((string) $b), $order, true);
        if ($ia !== false && $ib !== false) return $ia <=> $ib;
        if (is_numeric($a) && is_numeric($b)) return (float) $a <=> (float) $b;
        if ($ia !== false) return -1;
        if ($ib !== false) return 1;
        return strnatcasecmp((string) $a, (string) $b);
    });
    return $sizes;
}

/** Icônes SVG (trait) utilisées dans toute la boutique. */
function icon(string $name, string $class = 'w-5 h-5'): string
{
    $paths = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'bag' => '<path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'heart' => '<path d="M12 20s-7-4.4-9.2-9A5 5 0 0 1 12 6a5 5 0 0 1 9.2 5c-2.2 4.6-9.2 9-9.2 9z"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'chevron-right' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-left' => '<path d="m15 6-6 6 6 6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'minus' => '<path d="M5 12h14"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'check' => '<path d="m5 12 5 5L20 7"/>',
        'truck' => '<path d="M3 6h11v10H3z"/><path d="M14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h3"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8"/><path d="M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.5 4.5L20 16"/><path d="M20 20v-4h-4"/>',
        'shield' => '<path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'gift' => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12"/><path d="M12 8S10.5 4 8 4a2 2 0 0 0 0 4M12 8s1.5-4 4-4a2 2 0 0 1 0 4"/>',
        'package' => '<path d="m12 3 8 4.5v9L12 21l-8-4.5v-9z"/><path d="m4 7.5 8 4.5 8-4.5M12 12v9"/>',
        'map-pin' => '<path d="M12 21s-7-6.1-7-12a7 7 0 0 1 14 0c0 5.9-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'ruler' => '<path d="m3 17 14-14 4 4L7 21z"/><path d="m7 13 2 2M10 10l2 2M13 7l2 2"/>',
        'trash' => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
        'home' => '<path d="m3 11 9-7 9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="18" r="1"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'cart' => '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2 3h3l2.6 12.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
        'tag' => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'logout' => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M10 17l-5-5 5-5M5 12h11"/>',
        'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'zap' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'trend-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'trend-down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
        'printer' => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
        'qr' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v2M14 20h2M19 19v2M21 21h-2"/>',
    ];
    if ($name === 'whatsapp') {
        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4a.5.5 0 0 0 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.5-.3z"/></svg>';
    }
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Visuel de remplacement élégant quand un produit n'a pas encore de photo. */
function placeholder(string $label = '', string $class = ''): string
{
    return '<div class="placeholder ' . e($class) . '"><img src="' . e(url('assets/img/brand/logo-mark.png')) . '" alt="" class="w-16 h-auto opacity-25 grayscale-[30%]">'
        . ($label !== '' ? '<span class="mt-2 text-[11px] uppercase tracking-[.2em] text-ink/35">' . e($label) . '</span>' : '')
        . '</div>';
}

function breadcrumb(array $items): string
{
    $html = '<nav aria-label="Fil d\'Ariane" class="text-xs text-muted flex flex-wrap items-center gap-1.5"><a href="' . e(url()) . '" class="hover:text-ink transition-colors">Accueil</a>';
    $last = array_key_last($items);
    foreach ($items as $i => [$label, $href]) {
        $html .= icon('chevron-right', 'w-3 h-3 opacity-60');
        $html .= ($i === $last || !$href)
            ? '<span class="text-ink font-medium" aria-current="page">' . e($label) . '</span>'
            : '<a href="' . e($href) . '" class="hover:text-ink transition-colors">' . e($label) . '</a>';
    }
    return $html . '</nav>';
}

/** Sous-requête commune : stock total d'un produit ou stock global du coffret. */
const PRODUCT_STOCK_SQL = "(CASE WHEN p.is_gift_box=1 OR c.slug='coffrets' THEN
    (SELECT CASE WHEN EXISTS (SELECT 1 FROM gift_box_text_components gbc WHERE gbc.gift_box_id=p.id)
      THEN COALESCE(MAX(sv.stock),0) ELSE 0 END
     FROM variants sv WHERE sv.product_id=p.id AND sv.size='Unique' AND sv.color='Coffret')
    ELSE (SELECT COALESCE(SUM(sv.stock),0) FROM variants sv WHERE sv.product_id=p.id) END)";

function product_card(array $p): string
{
    $id = (int) $p['id'];
    $href = e(url('produit.php?id=' . $id));
    $name = e($p['name']);
    $isNew = !empty($p['created_at']) && strtotime((string) $p['created_at']) > strtotime('-30 days');
    $soldOut = array_key_exists('stock_total', $p) && (int) $p['stock_total'] <= 0;
    $discount = (!empty($p['old_price']) && (int) $p['old_price'] > (int) $p['price'])
        ? (int) round(100 - ((int) $p['price'] * 100 / (int) $p['old_price'])) : 0;

    $media = !empty($p['image'])
        // Cartes : ~50 % de l'écran sur mobile, 1/3 sur tablette, 1/4 sur ordinateur.
        ? responsive_img($p['image'], (string) $p['name'], 'img-fade absolute inset-0 w-full h-full object-cover group-hover:scale-[1.04]', '(min-width:1024px) 300px, (min-width:768px) 33vw, 50vw')
          . (!empty($p['image_back']) ? responsive_img($p['image_back'], '', 'absolute inset-0 w-full h-full object-cover opacity-0 transition-opacity duration-500 group-hover:opacity-100', '(min-width:1024px) 300px, (min-width:768px) 33vw, 50vw') : '')
        : placeholder((string) ($p['category_name'] ?? ''), 'absolute inset-0');

    $readyDays = product_ready_days($p['ready_days'] ?? null);
    $badge = '';
    if ($soldOut) $badge = '<span class="badge bg-ink/80 text-white">Épuisé</span>';
    elseif ($readyDays) $badge = '<span class="badge bg-forest text-white">' . e(ready_label($readyDays)) . '</span>';
    elseif ($discount > 0) $badge = '<span class="badge bg-terra text-white">-' . $discount . '%</span>';
    elseif ($isNew) $badge = '<span class="badge bg-white/90 text-forest">Nouveau</span>';

    $price = '<span class="text-terra font-semibold tabular-nums whitespace-nowrap">' . money((int) $p['price']) . '</span>';
    if ($discount > 0) $price .= ' <del class="text-xs text-muted tabular-nums whitespace-nowrap">' . money((int) $p['old_price']) . '</del>';

    return '<article class="group product-card reveal">'
        . '<div class="relative aspect-[4/5] overflow-hidden rounded-xl bg-[#EDE7DC]' . (!empty($p['image']) ? ' shimmer' : '') . '">'
        . '<a href="' . $href . '" class="absolute inset-0" aria-label="' . $name . '" data-product-click="' . $id . '" data-csrf="' . e($_SESSION['csrf'] ?? '') . '">' . $media . '</a>'
        . ($badge ? '<div class="absolute top-3 left-3 pointer-events-none">' . $badge . '</div>' : '')
        . '<a href="' . $href . '" class="quick-view hidden md:flex" data-product-click="' . $id . '" data-csrf="' . e($_SESSION['csrf'] ?? '') . '">Voir le produit ' . icon('arrow-right', 'w-4 h-4') . '</a>'
        . '</div>'
        . '<div class="pt-3 px-1 flex items-start justify-between gap-3">'
        . '<div class="min-w-0"><h3 class="text-sm font-medium leading-snug truncate"><a href="' . $href . '" class="hover:text-forest transition-colors" data-product-click="' . $id . '" data-csrf="' . e($_SESSION['csrf'] ?? '') . '">' . $name . '</a></h3>'
        . '<p class="mt-1 text-[15px] flex flex-wrap items-baseline gap-x-2">' . $price . '</p></div>'
        . '<button type="button" class="wish-btn" data-wish="' . $id . '" aria-label="Ajouter ' . $name . ' aux favoris" aria-pressed="false">' . icon('heart', 'w-5 h-5') . '</button>'
        . '</div></article>';
}

require_once __DIR__ . '/images.php';
