<?php
/**
 * Thèmes de fête : couleurs, bandeau, accueil et petite animation selon la fête du moment.
 *
 * Mode (réglage « holiday_theme_mode ») :
 *   - 'off'  : aucun thème ;
 *   - 'auto' : le thème dont les dates englobent aujourd'hui s'active tout seul ;
 *   - une clé de HOLIDAY_THEMES : ce thème est forcé (activation manuelle).
 *
 * Les textes, dates et image de chaque fête sont modifiables dans l'admin (table holiday_themes) ;
 * les valeurs ci-dessous servent par défaut.
 * Fêtes « yearly » : seules les dates jour/mois comptent, le thème revient chaque année.
 * Fêtes mobiles (calendrier lunaire, dimanches…) : dates à vérifier chaque année dans l'admin.
 */
declare(strict_types=1);

const HOLIDAY_EFFECTS = [
    'none' => 'Aucune',
    'snow' => 'Neige',
    'hearts' => 'Cœurs',
    'confetti' => 'Confettis',
    'stars' => 'Étoiles',
    'flowers' => 'Fleurs',
];

const HOLIDAY_THEMES = [
    'noel' => [
        'name' => 'Noël', 'emoji' => '🎄', 'yearly' => true, 'start' => '2026-12-10', 'end' => '2026-12-26',
        'colors' => ['#8E1F24', '#6E171B', '#B8892B'], 'effect' => 'snow', 'fx' => ['#9DB4C8', '#C9D6E2', '#B8892B'],
        'banner' => 'Joyeux Noël ! Coffrets cadeaux livrés partout au Sénégal',
        'title' => "Un Noël\nélégant", 'text' => 'Offrez le style : chemises, accessoires et coffrets cadeaux prêts à offrir.',
        'cta_label' => 'Idées cadeaux', 'cta_url' => 'coffrets.php',
    ],
    'nouvel_an' => [
        'name' => 'Nouvel An', 'emoji' => '✨', 'yearly' => true, 'start' => '2026-12-27', 'end' => '2027-01-05',
        'colors' => ['#1D1D1B', '#000000', '#B8892B'], 'effect' => 'confetti', 'fx' => ['#C8A24A', '#E5C97A', '#1D1D1B'],
        'banner' => 'Bonne année ! Commencez l’année avec style',
        'title' => "Nouvelle année,\nnouveau style", 'text' => 'Les pièces qu’il vous faut pour briller aux fêtes et bien commencer l’année.',
        'cta_label' => 'Découvrir les nouveautés', 'cta_url' => 'boutique.php?sort=new',
    ],
    'saint_valentin' => [
        'name' => 'Saint-Valentin', 'emoji' => '❤️', 'yearly' => true, 'start' => '2027-02-07', 'end' => '2027-02-14',
        'colors' => ['#A3264B', '#7F1B39', '#D14D72'], 'effect' => 'hearts', 'fx' => ['#D14D72', '#E88AA3', '#A3264B'],
        'banner' => 'Saint-Valentin : soyez élégant pour le grand soir, ou offrez un coffret',
        'title' => "Pour la\nSaint-Valentin", 'text' => 'Une tenue soignée pour le grand soir, ou un coffret cadeau pour lui faire plaisir.',
        'cta_label' => 'Offrir un coffret', 'cta_url' => 'coffrets.php',
    ],
    'korite' => [
        'name' => 'Korité', 'emoji' => '🌙', 'yearly' => false, 'start' => '2027-03-01', 'end' => '2027-03-11',
        'colors' => ['#1F6B4F', '#154D39', '#B8892B'], 'effect' => 'stars', 'fx' => ['#C8A24A', '#E5C97A', '#1F6B4F'],
        'banner' => 'Korité : préparez votre tenue de fête, livraison partout au Sénégal',
        'title' => "Élégant\npour la Korité", 'text' => 'Chemises, ensembles et accessoires pour célébrer la fin du Ramadan en famille.',
        'cta_label' => 'Ma tenue de fête', 'cta_url' => 'boutique.php',
    ],
    'independance' => [
        'name' => 'Fête de l’Indépendance', 'emoji' => '🇸🇳', 'yearly' => true, 'start' => '2027-03-29', 'end' => '2027-04-05',
        'colors' => ['#00853F', '#006B32', '#E31B23'], 'effect' => 'confetti', 'fx' => ['#00853F', '#FDEF42', '#E31B23'],
        'banner' => 'Bonne fête de l’Indépendance ! Fiers d’être Sénégalais',
        'title' => "Fête de\nl’Indépendance", 'text' => 'Célébrez le 4 avril avec style. Livraison rapide partout au Sénégal.',
        'cta_label' => 'Découvrir', 'cta_url' => 'boutique.php',
    ],
    'tabaski' => [
        'name' => 'Tabaski', 'emoji' => '🐑', 'yearly' => false, 'start' => '2027-05-04', 'end' => '2027-05-17',
        'colors' => ['#1F6B4F', '#154D39', '#B8892B'], 'effect' => 'stars', 'fx' => ['#C8A24A', '#E5C97A', '#1F6B4F'],
        'banner' => 'Tabaski : votre tenue de fête livrée à temps, commandez dès maintenant',
        'title' => "Votre tenue\nde Tabaski", 'text' => 'Ensembles, chemises, chaussures et accessoires pour être élégant le jour de la fête.',
        'cta_label' => 'Préparer ma Tabaski', 'cta_url' => 'boutique.php',
    ],
    'fete_meres' => [
        'name' => 'Fête des mères', 'emoji' => '💐', 'yearly' => false, 'start' => '2027-05-20', 'end' => '2027-05-30',
        'colors' => ['#8E3B5E', '#6D2C48', '#C0577F'], 'effect' => 'flowers', 'fx' => ['#E88AA3', '#F4B6C2', '#C0577F'],
        'banner' => 'Fête des mères : faites plaisir à maman, livraison partout au Sénégal',
        'title' => "Pour la\nfête des mères", 'text' => 'Un coffret cadeau, un accessoire… Une attention élégante pour celle qui compte le plus.',
        'cta_label' => 'Voir les coffrets', 'cta_url' => 'coffrets.php',
    ],
    'fete_peres' => [
        'name' => 'Fête des pères', 'emoji' => '👔', 'yearly' => false, 'start' => '2027-06-10', 'end' => '2027-06-20',
        'colors' => ['#1F3A5F', '#152A45', '#B8892B'], 'effect' => 'confetti', 'fx' => ['#1F3A5F', '#C8A24A', '#8FA6C4'],
        'banner' => 'Fête des pères : le cadeau idéal est chez Le Gentleman',
        'title' => "Bonne fête\npapa", 'text' => 'Chemises, ceintures, accessoires et coffrets : offrez-lui l’élégance.',
        'cta_label' => 'Idées cadeaux', 'cta_url' => 'coffrets.php',
    ],
    'magal' => [
        'name' => 'Magal de Touba', 'emoji' => '🕌', 'yearly' => false, 'start' => '2027-07-15', 'end' => '2027-07-24',
        'colors' => ['#2F5233', '#1F3A23', '#B8892B'], 'effect' => 'none', 'fx' => ['#C8A24A'],
        'banner' => 'Magal de Touba : bonne préparation à tous les pèlerins',
        'title' => "Magal\nde Touba", 'text' => 'Tenues sobres et élégantes pour le grand Magal. Commandez tôt pour être livré à temps.',
        'cta_label' => 'Découvrir', 'cta_url' => 'boutique.php',
    ],
    'gamou' => [
        'name' => 'Gamou', 'emoji' => '🌟', 'yearly' => false, 'start' => '2027-08-05', 'end' => '2027-08-15',
        'colors' => ['#1F6B4F', '#154D39', '#B8892B'], 'effect' => 'stars', 'fx' => ['#C8A24A', '#E5C97A', '#1F6B4F'],
        'banner' => 'Bonne fête du Gamou ! Tenues de fête livrées partout au Sénégal',
        'title' => "Élégant\npour le Gamou", 'text' => 'Chemises, ensembles et accessoires pour célébrer le Gamou avec élégance.',
        'cta_label' => 'Découvrir', 'cta_url' => 'boutique.php',
    ],
    'black_friday' => [
        'name' => 'Black Friday', 'emoji' => '🖤', 'yearly' => false, 'start' => '2026-11-23', 'end' => '2026-11-30',
        'colors' => ['#1D1D1B', '#000000', '#D9381E'], 'effect' => 'none', 'fx' => ['#D9381E'],
        'banner' => 'Black Friday : nos meilleures offres de l’année, quantités limitées',
        'title' => "Black Friday\nchez Le Gentleman", 'text' => 'Des prix exceptionnels sur une sélection de vêtements et d’accessoires. Jusqu’à épuisement des stocks.',
        'cta_label' => 'Voir les offres', 'cta_url' => 'boutique.php',
    ],
];

/** Crée les tables des thèmes si besoin (appelé depuis l'admin). */
function holiday_tables_ensure(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        name VARCHAR(60) PRIMARY KEY,
        value TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS holiday_themes (
        theme_key VARCHAR(40) PRIMARY KEY,
        banner VARCHAR(255) NULL,
        title VARCHAR(160) NULL,
        text VARCHAR(500) NULL,
        cta_label VARCHAR(60) NULL,
        cta_url VARCHAR(255) NULL,
        image VARCHAR(255) NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        auto_enabled TINYINT(1) NOT NULL DEFAULT 1,
        effect_enabled TINYINT(1) NOT NULL DEFAULT 1,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function setting_get(PDO $pdo, string $name, ?string $default = null): ?string
{
    try {
        $st = $pdo->prepare('SELECT value FROM settings WHERE name=?');
        $st->execute([$name]);
        $value = $st->fetchColumn();
        return $value === false ? $default : $value;
    } catch (PDOException) {
        return $default; // table absente : réglage par défaut
    }
}

function setting_set(PDO $pdo, string $name, ?string $value): void
{
    $pdo->prepare('INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=VALUES(value)')->execute([$name, $value]);
}

function holiday_mode(PDO $pdo): string
{
    $mode = (string) setting_get($pdo, 'holiday_theme_mode', 'off');
    return $mode === 'auto' || isset(HOLIDAY_THEMES[$mode]) ? $mode : 'off';
}

/** Tous les thèmes : valeurs par défaut complétées par les réglages enregistrés dans l'admin. */
function holiday_themes(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $saved = [];
    try {
        foreach ($pdo->query('SELECT * FROM holiday_themes')->fetchAll() as $row) $saved[$row['theme_key']] = $row;
    } catch (PDOException) {
        // table absente : valeurs par défaut
    }
    $cache = [];
    foreach (HOLIDAY_THEMES as $key => $def) {
        $row = $saved[$key] ?? [];
        $pick = fn(string $field, string $defField) => isset($row[$field]) && trim((string) $row[$field]) !== '' ? (string) $row[$field] : (string) ($def[$defField] ?? '');
        $cache[$key] = $def + [
            'key' => $key,
            'banner_text' => $pick('banner', 'banner'),
            'title_text' => $pick('title', 'title'),
            'body_text' => $pick('text', 'text'),
            'cta_text' => $pick('cta_label', 'cta_label'),
            'cta_link' => $pick('cta_url', 'cta_url'),
            'image' => (string) ($row['image'] ?? ''),
            'start_date' => $pick('start_date', 'start'),
            'end_date' => $pick('end_date', 'end'),
            'auto_enabled' => !isset($row['auto_enabled']) || (int) $row['auto_enabled'] === 1,
            'effect_enabled' => !isset($row['effect_enabled']) || (int) $row['effect_enabled'] === 1,
            'customized' => (bool) $row,
        ];
    }
    return $cache;
}

/** Le thème est-il dans sa période ? (les fêtes annuelles ignorent l'année) */
function holiday_in_window(array $theme, ?string $today = null): bool
{
    $today ??= date('Y-m-d');
    $start = (string) $theme['start_date'];
    $end = (string) $theme['end_date'];
    if ($start === '' || $end === '') return false;
    if (!empty($theme['yearly'])) {
        $d = substr($today, 5);
        $s = substr($start, 5);
        $e = substr($end, 5);
        return $s <= $e ? ($d >= $s && $d <= $e) : ($d >= $s || $d <= $e); // période à cheval sur le 1er janvier
    }
    return $today >= $start && $today <= $end;
}

/** Prochaine date de début (pour l'affichage dans l'admin). */
function holiday_next_start(array $theme): string
{
    if (empty($theme['yearly'])) return (string) $theme['start_date'];
    $md = substr((string) $theme['start_date'], 5);
    $year = (int) date('Y');
    return (date('m-d') > $md && !holiday_in_window($theme) ? $year + 1 : $year) . '-' . $md;
}

/** Thème affiché sur la boutique pour cette visite (ou null). */
function active_holiday_theme(PDO $pdo): ?array
{
    static $resolved = false, $theme = null;
    if ($resolved) return $theme;
    $resolved = true;

    // Aperçu réservé aux comptes admin : ?apercu_theme=noel (et ?apercu_theme=0 pour quitter).
    if (isset($_GET['apercu_theme']) && !empty($_SESSION['admin_id'])) {
        $key = (string) $_GET['apercu_theme'];
        if (isset(HOLIDAY_THEMES[$key]) || $key === 'none') $_SESSION['theme_preview'] = $key;
        else unset($_SESSION['theme_preview']);
    }
    $themes = holiday_themes($pdo);
    $preview = !empty($_SESSION['admin_id']) ? ($_SESSION['theme_preview'] ?? null) : null;
    if ($preview !== null) {
        $theme = $preview === 'none' ? null : ($themes[$preview] ?? null);
        if ($theme) $theme['preview'] = true;
        return $theme;
    }

    $mode = holiday_mode($pdo);
    if ($mode === 'off') return null;
    if ($mode !== 'auto') return $theme = $themes[$mode] ?? null;
    foreach ($themes as $candidate) {
        if ($candidate['auto_enabled'] && holiday_in_window($candidate)) return $theme = $candidate;
    }
    return null;
}

/** '#8E1F24' → '142 31 36' (format attendu par les couleurs Tailwind). */
function hex_to_rgb_triplet(string $hex): string
{
    $hex = ltrim($hex, '#');
    return hexdec(substr($hex, 0, 2)) . ' ' . hexdec(substr($hex, 2, 2)) . ' ' . hexdec(substr($hex, 4, 2));
}

/** Bloc <style> qui remplace les couleurs de la boutique par celles du thème. */
function holiday_theme_style(array $theme): string
{
    [$main, $dark, $accent] = $theme['colors'];
    return '<style>:root{'
        . '--c-forest:' . hex_to_rgb_triplet($main) . ';--c-forest-dark:' . hex_to_rgb_triplet($dark) . ';--c-terra:' . hex_to_rgb_triplet($accent) . ';'
        . '--forest:' . $main . ';--forest-dark:' . $dark . ';--terra:' . $accent . '}</style>';
}

/** Lien d'un bouton de thème : chemin du site ou adresse complète. */
function holiday_link(string $link): string
{
    return preg_match('~^https?://~i', $link) ? $link : url($link);
}
