<?php
/**
 * Convertit en WebP optimisé (+ versions 800 et 400 px) les photos produits existantes
 * et l'image d'accueil. Relançable sans risque : les images déjà converties sont ignorées.
 * Les fichiers d'origine ne sont pas supprimés.
 *
 *   php sql/optimize-images.php
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/db.php';
require dirname(__DIR__) . '/includes/helpers.php';

$root = dirname(__DIR__);
$before = $after = 0;
$done = [];

$convert = function (string $url) use (&$before, &$after, &$done): ?string {
    if (isset($done[$url])) return $done[$url];
    $path = image_local_path($url);
    if (!$path || !is_file($path) || str_ends_with($path, '.webp')) return null;
    $noExt = preg_replace('/\.[a-z0-9]+$/i', '', $path);
    if (!image_optimize($path, $noExt)) { echo "  ! illisible : $url\n"; return null; }
    $before += filesize($path);
    $after += filesize($noExt . '.webp');
    return $done[$url] = preg_replace('/\.[a-z0-9]+$/i', '.webp', strtok($url, '?'));
};

$rows = $pdo->query("SELECT id, name, image, image_back FROM products WHERE COALESCE(image,'')<>'' OR COALESCE(image_back,'')<>''")->fetchAll();
$update = $pdo->prepare('UPDATE products SET image=?, image_back=? WHERE id=?');
$n = 0;
foreach ($rows as $r) {
    $img = $r['image'] ? ($convert($r['image']) ?? $r['image']) : null;
    $back = $r['image_back'] ? ($convert($r['image_back']) ?? $r['image_back']) : null;
    if ($img !== $r['image'] || $back !== $r['image_back']) { $update->execute([$img, $back, $r['id']]); $n++; echo "  ✓ {$r['name']}\n"; }
}

// Image d'accueil (choisie automatiquement en .webp si elle existe)
foreach (['jpg', 'jpeg', 'png'] as $ext) {
    $hero = "$root/assets/img/hero.$ext";
    if (is_file($hero) && (!is_file("$root/assets/img/hero.webp") || filemtime($hero) > filemtime("$root/assets/img/hero.webp"))) {
        image_optimize($hero, "$root/assets/img/hero", 1800);
        $before += filesize($hero); $after += filesize("$root/assets/img/hero.webp");
        echo "  ✓ image d'accueil\n";
        break;
    }
}

printf("%d produit(s) mis à jour. Poids des images : %s → %s (versions pleine taille).\n", $n, number_format($before / 1024, 0, ',', ' ') . ' Ko', number_format($after / 1024, 0, ',', ' ') . ' Ko');
