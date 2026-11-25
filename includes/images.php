<?php
/**
 * Optimisation des images : redressement (EXIF), redimensionnement, conversion WebP
 * et déclinaisons légères pour les écrans mobiles.
 *
 * Convention : photo.webp (1600 px max) + photo-800.webp + photo-400.webp
 */
declare(strict_types=1);

const IMAGE_MAX = 1600;
const IMAGE_WIDTHS = [400, 800];
const IMAGE_QUALITY = 80;

/** Charge une image JPEG/PNG/WebP en respectant l'orientation des photos de téléphone. */
function image_load(string $path): ?GdImage
{
    $info = @getimagesize($path);
    if (!$info) return null;
    // Une photo de téléphone 12 Mpx occupe ~50 Mo décompressée (×2 pendant la rotation).
    if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 512) @ini_set('memory_limit', '512M');
    $img = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
        IMAGETYPE_PNG => @imagecreatefrompng($path),
        IMAGETYPE_WEBP => @imagecreatefromwebp($path),
        default => false,
    };
    if (!$img) return null;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => null,
        };
        if ($rotated) $img = $rotated;
    }
    if (!imageistruecolor($img)) imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    return $img;
}

function image_resize(GdImage $img, int $maxSide): GdImage
{
    $w = imagesx($img); $h = imagesy($img);
    $ratio = min(1, $maxSide / max($w, $h));
    if ($ratio >= 1) return $img;
    $nw = max(1, (int) round($w * $ratio)); $nh = max(1, (int) round($h * $ratio));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $out;
}

/**
 * Crée la version optimisée d'une image et ses déclinaisons.
 * $destNoExt : chemin de destination sans extension (ex. …/uploads/20261123-ab12cd).
 * Renvoie le chemin du fichier .webp principal, ou null si l'image est illisible.
 */
function image_optimize(string $source, string $destNoExt, int $maxSide = IMAGE_MAX): ?string
{
    $img = image_load($source);
    if (!$img) return null;
    $main = image_resize($img, $maxSide);
    imagewebp($main, $destNoExt . '.webp', IMAGE_QUALITY);
    foreach (IMAGE_WIDTHS as $width) {
        if ($width >= imagesx($main)) continue; // inutile de créer une version plus large que l'original
        $small = image_resize($main, (int) round($width * max(imagesx($main), imagesy($main)) / imagesx($main)));
        imagewebp($small, $destNoExt . '-' . $width . '.webp', IMAGE_QUALITY);
    }
    return $destNoExt . '.webp';
}

/**
 * Adresse d'une image du site, recalculée avec le BASE_URL actuel.
 * Les chemins enregistrés en base peuvent contenir un ancien dossier (ex. « /gentleman/assets/uploads/x.webp »
 * créé en local) ou être relatifs (« assets/img/… ») : tout ce qui précède « assets/ » est remplacé,
 * pour que les photos s'affichent aussi une fois le site installé à la racine d'un domaine.
 */
function image_normalize(?string $url): string
{
    $url = (string) $url;
    if ($url === '' || preg_match('~^([a-z][a-z0-9+.-]*:|//)~i', $url)) return $url; // adresse externe ou data:
    $pos = strpos($url, 'assets/');
    return $pos === false ? $url : url(substr($url, $pos));
}

/** Chemin disque d'une URL locale du site (ou null si externe). */
function image_local_path(?string $url): ?string
{
    $url = image_normalize($url);
    if ($url === '') return null;
    $base = rtrim(BASE_URL, '/') . '/';
    if (!str_starts_with($url, $base)) return null;
    return dirname(__DIR__) . '/' . substr(strtok($url, '?'), strlen($base));
}

/** URL de la déclinaison la plus proche de $width (ou l'image d'origine). */
function image_url(?string $url, int $width): string
{
    $url = image_normalize($url);
    $path = image_local_path($url);
    if ($path && str_ends_with($path, '.webp')) {
        foreach (IMAGE_WIDTHS as $w) {
            if ($w >= $width && is_file(substr($path, 0, -5) . "-$w.webp")) return substr($url, 0, -5) . "-$w.webp";
        }
    }
    return $url;
}

/** Attribut srcset pour une image optimisée (chaîne vide sinon). */
function image_srcset(?string $url): string
{
    $url = image_normalize($url);
    $path = image_local_path($url);
    if (!$path || !str_ends_with($path, '.webp') || !is_file($path)) return '';
    $set = [];
    foreach (IMAGE_WIDTHS as $w) {
        if (is_file(substr($path, 0, -5) . "-$w.webp")) $set[] = substr((string) $url, 0, -5) . "-$w.webp {$w}w";
    }
    if (!$set) return '';
    $size = @getimagesize($path);
    $set[] = $url . ' ' . ($size ? max($size[0], 1) : IMAGE_MAX) . 'w';
    return implode(', ', $set);
}

/** Balise <img> responsive (srcset + sizes + lazy loading). */
function responsive_img(?string $url, string $alt, string $class, string $sizes = '100vw', bool $lazy = true, string $extra = ''): string
{
    $srcset = image_srcset($url);
    return '<img src="' . e($srcset ? image_url($url, 800) : image_normalize($url)) . '"'
        . ($srcset ? ' srcset="' . e($srcset) . '" sizes="' . e($sizes) . '"' : '')
        . ' alt="' . e($alt) . '" class="' . e($class) . '"' . ($lazy ? ' loading="lazy" decoding="async"' : ' decoding="async"') . ($extra ? ' ' . $extra : '') . '>';
}
