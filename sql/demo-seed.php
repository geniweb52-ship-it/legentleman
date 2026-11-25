<?php
/**
 * Données de démonstration : php sql/demo-seed.php
 * Ajoute des produits, variantes et photos (assets/img/demo). Relançable sans doublons.
 * Suppression : php sql/demo-seed.php --remove
 * Photos : Unsplash (licence libre, usage commercial autorisé).
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/db.php';

$img = fn(string $n) => BASE_URL . '/assets/img/demo/' . $n . '.jpg';
$tops = ['S', 'M', 'L', 'XL', 'XXL'];
$bottoms = ['38', '40', '42', '44', '46'];
$shoes = ['40', '41', '42', '43', '44', '45'];

// [slug, catégorie, nom, marque, prix, ancien prix, image, image 2, coffret, jours depuis l'ajout, description, tailles, [couleur => stock par taille]]
$products = [
    ['demo-tshirt-essentiel-blanc', 'hauts', 'T-shirt essentiel', 'LE GENTLEMAN', 9500, null, 'tshirt-blanc', 'tshirt-blanc-2', 0, 2, "T-shirt col rond en coton peigné, doux et respirant. La base de toutes les tenues.\n- Matière : 100 % coton peigné\n- Coupe : droite\n- Col rond côtelé", $tops, ['Blanc' => 12, 'Noir' => 8]],
    ['demo-tshirt-noir-signature', 'hauts', 'T-shirt Signature noir', 'LE GENTLEMAN', 11000, null, 'tshirt-noir', 'tshirt-noir-2', 0, 3, "T-shirt épais au tombé impeccable, avec petit logo brodé.\n- Matière : coton 220 g/m²\n- Coupe : regular\n- Logo brodé sur la poitrine", $tops, ['Noir' => 10]],
    ['demo-tshirt-coton-leger', 'hauts', 'T-shirt coton léger', 'LE GENTLEMAN', 8500, 10000, 'tshirt-gris', null, 0, 6, "Idéal pour la chaleur de Dakar : léger, doux et facile à porter.\n- Matière : coton flammé\n- Coupe : décontractée", $tops, ['Gris clair' => 9, 'Blanc' => 6]],
    ['demo-tshirt-col-contraste', 'hauts', 'T-shirt col contrasté', 'LE GENTLEMAN', 12500, null, 'tshirt-contraste', null, 0, 1, "T-shirt noir aux finitions contrastées pour un look urbain soigné.\n- Matière : coton stretch\n- Coupe : ajustée", $tops, ['Noir' => 7]],
    ['demo-chemise-oxford', 'hauts', 'Chemise Oxford', 'LE GENTLEMAN', 22500, null, 'chemise-oxford', 'chemise-oxford-2', 0, 4, "La chemise incontournable du bureau comme du week-end.\n- Matière : coton Oxford\n- Coupe : ajustée\n- Col boutonné", $tops, ['Bleu ciel' => 8, 'Blanc' => 6]],
    ['demo-chemise-blanche-ajustee', 'hauts', 'Chemise blanche ajustée', 'LE GENTLEMAN', 24500, null, 'chemise-blanche', 'chemise-blanche-2', 0, 8, "Une chemise blanche impeccable, à porter avec ou sans cravate.\n- Matière : popeline de coton\n- Coupe : slim\n- Poignets mousquetaires", $tops, ['Blanc' => 10]],
    ['demo-chemise-imprimee', 'hauts', 'Chemise imprimée indigo', 'LE GENTLEMAN', 19500, 23000, 'chemise-imprimee', null, 0, 12, "Chemise à micro-motifs, parfaite pour une sortie décontractée.\n- Matière : coton léger\n- Coupe : regular", $tops, ['Indigo' => 5]],
    ['demo-surchemise-carreaux', 'hauts', 'Surchemise à carreaux', 'LE GENTLEMAN', 21000, null, 'surchemise', null, 0, 15, "Surchemise en flanelle à carreaux, à porter ouverte sur un t-shirt.\n- Matière : flanelle de coton\n- Deux poches poitrine", $tops, ['Vert foncé' => 6]],
    ['demo-veste-camel', 'hauts', 'Veste camel', 'LE GENTLEMAN', 39000, null, 'veste-camel', null, 0, 0, "Veste en suédine camel, la pièce forte d'une tenue casual chic.\n- Matière : suédine douce\n- Fermeture boutons pression", $tops, ['Camel' => 5]],
    ['demo-bomber-terracotta', 'hauts', 'Bomber terracotta', 'LE GENTLEMAN', 35000, null, 'bomber', null, 0, 20, "Bomber léger aux finitions côtelées, idéal pour les soirées fraîches.\n- Matière : nylon satiné\n- Doublure légère", $tops, ['Terracotta' => 4]],
    ['demo-veste-jean', 'hauts', 'Veste en jean brut', 'LE GENTLEMAN', 32000, null, 'veste-jean', null, 0, 25, "Veste en denim brut au col contrasté, intemporelle.\n- Matière : denim 100 % coton\n- Coupe : droite", $tops, ['Bleu nuit' => 5]],
    ['demo-blouson-cuir', 'hauts', 'Blouson cuir', 'LE GENTLEMAN', 65000, 75000, 'blouson-cuir', 'blouson-cuir-2', 0, 30, "Blouson perfecto en cuir souple, finitions argentées.\n- Matière : cuir d'agneau\n- Doublure intérieure", $tops, ['Noir' => 3]],
    ['demo-blazer-bleu', 'hauts', 'Blazer bleu roi', 'LE GENTLEMAN', 55000, null, 'blazer', 'blazer-2', 0, 5, "Blazer structuré pour les cérémonies et les rendez-vous importants.\n- Matière : laine mélangée\n- Coupe : ajustée\n- Pochette passepoilée", $tops, ['Bleu marine' => 6]],
    ['demo-chino-beige', 'bas', 'Chino beige', 'LE GENTLEMAN', 18500, null, 'chino', null, 0, 7, "Chino confortable en coton stretch, facile à associer.\n- Matière : coton stretch\n- Coupe : slim", $bottoms, ['Beige' => 8, 'Bleu marine' => 5]],
    ['demo-jean-droit-brut', 'bas', 'Jean droit brut', 'LE GENTLEMAN', 22500, null, 'jean-brut', 'jean-brut-2', 0, 9, "Jean brut à la coupe droite classique, qui se patine avec le temps.\n- Matière : denim 13 oz\n- Coupe : droite", $bottoms, ['Bleu nuit' => 9]],
    ['demo-jean-slim-delave', 'bas', 'Jean slim délavé', 'LE GENTLEMAN', 24000, 28000, 'jean-delave', 'jean-delave-2', 0, 11, "Jean slim délavé aux finitions usées, pour un look décontracté.\n- Matière : denim stretch\n- Coupe : slim", $bottoms, ['Bleu clair' => 7]],
    ['demo-derbies-cuir', 'chaussures', 'Derbies cuir cognac', 'LE GENTLEMAN', 45000, null, 'derbies', null, 0, 10, "Derbies en cuir lisse cognac, cousues à la main.\n- Dessus : cuir pleine fleur\n- Semelle : cuir et gomme", $shoes, ['Cognac' => 4]],
    ['demo-mocassins-boucles', 'chaussures', 'Mocassins à double boucle', 'LE GENTLEMAN', 42000, null, 'mocassins', null, 0, 14, "Chaussures à double boucle, élégantes avec un costume comme avec un chino.\n- Dessus : cuir brossé\n- Doublure cuir", $shoes, ['Marron' => 5]],
    ['demo-montre-cuir', 'accessoires', 'Montre classique bracelet cuir', 'LE GENTLEMAN', 29000, null, 'montre-cuir', null, 0, 4, "Montre au cadran épuré et bracelet en cuir, pour toutes les occasions.\n- Mouvement : quartz\n- Étanchéité : 3 ATM", ['Unique'], ['Taupe' => 6]],
    ['demo-montre-minimaliste', 'accessoires', 'Montre minimaliste', 'LE GENTLEMAN', 25000, null, 'montre-blanche', null, 0, 18, "Montre au design minimaliste et moderne.\n- Mouvement : quartz\n- Bracelet silicone", ['Unique'], ['Blanc' => 5, 'Noir' => 3]],
    ['demo-ceinture-cuir', 'accessoires', 'Ceinture en cuir', 'LE GENTLEMAN', 12500, null, 'ceinture', null, 0, 16, "Ceinture en cuir véritable à boucle ardillon.\n- Largeur : 3,5 cm\n- Cuir pleine fleur", ['90', '100', '110'], ['Cognac' => 6, 'Noir' => 6]],
    ['demo-portefeuille-cuir', 'accessoires', 'Portefeuille en cuir', 'LE GENTLEMAN', 15000, null, 'portefeuille', null, 0, 22, "Portefeuille fin en cuir, 6 emplacements pour cartes.\n- Cuir grainé\n- Format compact", ['Unique'], ['Marron' => 8]],
    ['demo-lunettes-rondes', 'accessoires', 'Lunettes de soleil rondes', 'LE GENTLEMAN', 14000, null, 'lunettes', null, 0, 13, "Lunettes rondes à monture métal, verres teintés anti-UV.\n- Protection UV400\n- Étui fourni", ['Unique'], ['Or' => 7]],
    ['demo-coffret-gentleman', 'coffrets', 'Coffret Gentleman', 'LE GENTLEMAN', 45000, 52000, 'coffret', 'montre-cuir', 1, 2, "Le cadeau idéal, emballé avec soin et prêt à offrir.\n- Une montre classique bracelet cuir\n- Une ceinture en cuir\n- Un portefeuille en cuir\n- Carte message personnalisée", ['Unique'], ['Assorti' => 6]],
    ['demo-coffret-elegance', 'coffrets', 'Coffret Élégance', 'LE GENTLEMAN', 32000, null, 'portefeuille', 'ceinture', 1, 9, "Un duo d'accessoires en cuir dans un coffret élégant.\n- Une ceinture en cuir\n- Un portefeuille en cuir\n- Emballage cadeau offert", ['Unique'], ['Cognac' => 5, 'Noir' => 4]],
];

if (in_array('--remove', $argv, true) || in_array('--remove-orders', $argv, true)) {
    // Commandes et clients de démonstration (reconnaissables : référence LG-DEMO-…, adresse « [démo] … »).
    $o = $pdo->exec("DELETE FROM orders WHERE reference LIKE 'LG-DEMO-%'");
    $c = $pdo->exec("DELETE FROM customers WHERE address LIKE '[démo]%'");
    echo "$o commandes et $c clients de démonstration supprimés.\n";
    if (in_array('--remove', $argv, true)) {
        $n = $pdo->exec("DELETE FROM products WHERE slug LIKE 'demo-%'");
        echo "$n produits de démonstration supprimés.\n";
    }
    exit;
}

if (in_array('--orders', $argv, true)) {
    // Commandes fictives sur 75 jours pour visualiser le tableau de bord (le stock n'est pas modifié).
    $variants = $pdo->query("SELECT v.id, v.size, v.color, p.name, p.price FROM variants v JOIN products p ON p.id=v.product_id WHERE p.slug LIKE 'demo-%'")->fetchAll();
    if (!$variants) exit("Lancez d'abord : php sql/demo-seed.php\n");
    $zones = $pdo->query('SELECT id, fee, name FROM delivery_zones WHERE active=1')->fetchAll();
    $first = ['Mamadou', 'Awa', 'Ibrahima', 'Fatou', 'Cheikh', 'Aminata', 'Moussa', 'Mariama', 'Ousmane', 'Khadija', 'Abdoulaye', 'Seynabou', 'Babacar', 'Ndeye', 'Pape', 'Aïssatou', 'Modou', 'Coumba', 'Serigne', 'Astou'];
    $last = ['Ndiaye', 'Diop', 'Sarr', 'Fall', 'Faye', 'Sow', 'Ba', 'Gueye', 'Diallo', 'Mbaye', 'Cissé', 'Thiam', 'Seck', 'Kane', 'Niang'];
    $streets = ['Sacré-Cœur 3', 'Mermoz', 'Ouakam', 'Point E', 'Almadies', 'Liberté 6', 'Plateau', 'HLM Grand Yoff', 'Parcelles U17', 'Médina', 'Ngor', 'Fann Résidence'];
    $mkCustomer = $pdo->prepare('INSERT INTO customers (name, phone, address, created_at) VALUES (?,?,?,?)');
    $mkOrder = $pdo->prepare('INSERT INTO orders (reference,customer_id,delivery_zone_id,customer_name,first_name,last_name,phone,address,payment_method,subtotal,delivery_fee,total,status,paid_at,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $mkItem = $pdo->prepare('INSERT INTO order_items (order_id,variant_id,product_name,size,color,price,quantity) VALUES (?,?,?,?,?,?,?)');
    $customers = [];
    $n = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE reference LIKE 'LG-DEMO-%'")->fetchColumn();
    mt_srand(2026);
    for ($day = 75; $day >= 0; $day--) {
        $perDay = mt_rand(0, 2) + ($day < 7 ? mt_rand(1, 3) : 0) + ((int) date('N', strtotime("-$day days")) >= 5 ? 1 : 0);
        for ($k = 0; $k < $perDay; $k++) {
            $at = date('Y-m-d H:i:s', strtotime("-$day days") - mt_rand(0, 10 * 3600));
            if (!$customers || mt_rand(1, 100) > 35) {
                $fn = $first[array_rand($first)]; $ln = $last[array_rand($last)];
                $phone = '70 ' . mt_rand(100, 999) . ' ' . mt_rand(10, 99) . ' ' . mt_rand(10, 99);
                $mkCustomer->execute(["$fn $ln", $phone, '[démo] ' . $streets[array_rand($streets)], $at]);
                $customers[] = [(int) $pdo->lastInsertId(), $fn, $ln, $phone];
            }
            [$cid, $fn, $ln, $phone] = $customers[array_rand($customers)];
            $zone = $zones[array_rand($zones)];
            $lines = []; $subtotal = 0;
            foreach ((array) array_rand($variants, mt_rand(1, 3)) as $vi) {
                $v = $variants[$vi]; $q = mt_rand(1, 10) > 8 ? 2 : 1;
                $lines[] = [$v, $q]; $subtotal += (int) $v['price'] * $q;
            }
            $fee = $subtotal >= FREE_DELIVERY_THRESHOLD ? 0 : (int) $zone['fee'];
            $pay = ['wave', 'wave', 'wave', 'orange', 'delivery', 'delivery'][mt_rand(0, 5)];
            $status = $day > 6 ? (mt_rand(1, 100) > 7 ? 'livree' : 'annulee') : ['nouvelle', 'payee', 'en_preparation', 'expediee', 'livree'][min(4, intdiv(6 - $day, 2) + mt_rand(0, 2))];
            $paid = ($pay !== 'delivery' && $status !== 'nouvelle' && $status !== 'annulee') || $status === 'livree' ? $at : null;
            $mkOrder->execute([sprintf('LG-DEMO-%04d', ++$n), $cid, $zone['id'], "$fn $ln", $fn, $ln, $phone, '[démo] ' . $streets[array_rand($streets)], $pay, $subtotal, $fee, $subtotal + $fee, $status, $paid, $at]);
            $oid = (int) $pdo->lastInsertId();
            foreach ($lines as [$v, $q]) $mkItem->execute([$oid, $v['id'], $v['name'], $v['size'], $v['color'], $v['price'], $q]);
        }
    }
    echo count($customers) . " clients et des commandes de démonstration créés (références LG-DEMO-…).\n";
    exit;
}

$pdo->exec("INSERT INTO categories (name, slug, sort_order) SELECT 'Accessoires', 'accessoires', 5 WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug='accessoires')");
$catIds = $pdo->query('SELECT slug, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
$exists = $pdo->prepare('SELECT id FROM products WHERE slug=?');
$insert = $pdo->prepare('INSERT INTO products (category_id,name,slug,description,brand,price,old_price,image,image_back,is_gift_box,active,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,1, NOW() - INTERVAL ? DAY)');
$variant = $pdo->prepare('INSERT INTO variants (product_id,size,color,stock) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE stock=VALUES(stock)');
$added = 0;
foreach ($products as [$slug, $cat, $name, $brand, $price, $old, $image, $image2, $gift, $days, $desc, $sizes, $colors]) {
    $exists->execute([$slug]);
    if ($exists->fetchColumn()) continue;
    $insert->execute([$catIds[$cat] ?? null, $name, $slug, $desc, $brand, $price, $old, $img($image), $image2 ? $img($image2) : null, $gift, $days]);
    $id = (int) $pdo->lastInsertId();
    foreach ($colors as $color => $stock) {
        foreach ($sizes as $i => $size) {
            // Stock varié : certaines tailles extrêmes presque épuisées pour montrer les alertes.
            $qty = $i === 0 && count($sizes) > 2 ? max(0, $stock - 6) : ($i === count($sizes) - 1 && count($sizes) > 2 ? 2 : $stock);
            $variant->execute([$id, $size, $color, $qty]);
        }
    }
    $added++;
}
echo "$added produits ajoutés.\n";
