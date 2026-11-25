<?php
/** robots.txt (servi via .htaccess) : indique à Google ce qu'il peut explorer et où trouver le plan du site. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/orders.php';
header('Content-Type: text/plain; charset=utf-8');
$base = rtrim(BASE_URL, '/');
echo "User-agent: *\n";
foreach (['/admin/', '/panier.php', '/commande.php', '/merci.php', '/facture.php', '/suivi.php', '/favoris.php', '/wave-pay.php', '/wave-webhook.php', '/acheter.php', '/qr-go.php', '/analytics-track.php'] as $path) {
    echo 'Disallow: ' . $base . $path . "\n";
}
echo "\nSitemap: " . site_url('sitemap.xml') . "\n";
