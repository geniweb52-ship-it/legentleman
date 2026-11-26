<?php
/** sitemap.xml (servi via .htaccess) : liste des pages et produits à faire connaître à Google, mise à jour automatiquement. */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/orders.php';
header('Content-Type: application/xml; charset=utf-8');

$urls = [];
foreach (['' => '1.0', 'boutique.php' => '0.9', 'coffrets.php' => '0.8', 'boutique.php?sort=new' => '0.7', 'a-propos.php' => '0.5',
          'contact.php' => '0.5', 'livraison.php' => '0.4', 'guide-tailles.php' => '0.4', 'cgv.php' => '0.2', 'conditions-utilisation.php' => '0.2', 'confidentialite.php' => '0.2'] as $page => $priority) {
    $urls[] = ['loc' => site_url($page), 'priority' => $priority];
}
foreach ($pdo->query("SELECT slug FROM categories WHERE slug<>'tous' ORDER BY sort_order") as $c) {
    $urls[] = ['loc' => site_url('boutique.php?cat[]=' . rawurlencode($c['slug'])), 'priority' => '0.7'];
}
foreach ($pdo->query('SELECT id, name, image, created_at FROM products WHERE active=1 ORDER BY created_at DESC') as $p) {
    $urls[] = ['loc' => site_url('produit.php?id=' . (int) $p['id']), 'priority' => '0.8', 'lastmod' => substr((string) $p['created_at'], 0, 10),
               'image' => $p['image'] ? absolute_url($p['image']) : '', 'title' => $p['name']];
}

$x = fn($s) => htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . $x($u['loc']) . '</loc>';
    if (!empty($u['lastmod'])) echo '<lastmod>' . $x($u['lastmod']) . '</lastmod>';
    echo '<priority>' . $u['priority'] . '</priority>';
    if (!empty($u['image'])) echo '<image:image><image:loc>' . $x($u['image']) . '</image:loc></image:image>';
    echo "</url>\n";
}
echo "</urlset>\n";
