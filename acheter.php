<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/cart.php';

$productId = (int) ($_GET['id'] ?? 0);
$size = trim((string) ($_GET['size'] ?? ''));
$color = trim((string) ($_GET['color'] ?? ''));
$quantity = max(1, min(99, (int) ($_GET['quantity'] ?? 1)));

$productStmt = $pdo->prepare('SELECT p.*,c.slug category_slug FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.active=1');
$productStmt->execute([$productId]);
$product = $productStmt->fetch();
if (!$product) redirect('boutique.php');

if (is_gift_box_product($product)) {
    try {
        $parentStmt = $pdo->prepare("SELECT id,size,color FROM variants WHERE product_id=? AND size='Unique' AND color='Coffret'");
        $parentStmt->execute([$productId]);
        $parentVariant = $parentStmt->fetch();
        if (!$parentVariant) throw new RuntimeException('Ce coffret n’est pas encore prêt à être commandé.');
        $components = resolve_gift_box_selection($pdo, $productId, $_GET['components'] ?? []);
        $variantId = (int) $parentVariant['id'];
        $lineKey = cart_line_key($variantId, $components);
        $currentQuantity = (int) ($_SESSION['cart'][$lineKey]['quantity'] ?? 0);
        $available = cart_line_available_stock($pdo, ['variant_id' => $variantId, 'components' => $components]);
        $wanted = $currentQuantity + $quantity;
        if ($available < 1) throw new RuntimeException('Les articles de ce coffret sont épuisés.');
        $_SESSION['cart'][$lineKey] = [
            'variant_id' => $variantId,
            'quantity' => min($wanted, $available),
            'name' => $product['name'],
            'size' => $parentVariant['size'],
            'color' => $parentVariant['color'],
            'components' => $components,
        ];
        if ($wanted > $available) flash('Stock limité : ' . $available . ' coffret(s) maximum avec cette composition.', 'error');
    } catch (RuntimeException $exception) {
        flash($exception->getMessage(), 'error');
        redirect('produit.php?id=' . $productId);
    }
    $analyticsProductId = $productId;
} else {
    $statement = $pdo->prepare('SELECT v.*,p.name,p.active FROM variants v JOIN products p ON p.id=v.product_id WHERE p.active=1 AND v.product_id=? AND v.size=? AND v.color=?');
    $statement->execute([$productId, $size, $color]);
    $variant = $statement->fetch();
    if (!$variant) {
        flash('Choisissez une taille et une couleur.', 'error');
        redirect('produit.php?id=' . $productId);
    }
    if ((int) $variant['stock'] < 1) {
        flash('Cette déclinaison est épuisée.', 'error');
        redirect('produit.php?id=' . $productId);
    }

    $variantId = (int) $variant['id'];
    $_SESSION['cart'][$variantId] ??= ['quantity' => 0, 'name' => $variant['name'], 'size' => $variant['size'], 'color' => $variant['color']];
    $_SESSION['cart'][$variantId]['quantity'] = min($_SESSION['cart'][$variantId]['quantity'] + $quantity, (int) $variant['stock']);
    $analyticsProductId = $productId;
}

try {
    $campaignToken = (string) ($_COOKIE['lg_qr_campaign'] ?? '');
    $campaignStmt = $pdo->prepare('SELECT id FROM qr_campaigns WHERE token=?');
    $campaignStmt->execute([preg_match('/^[a-f0-9]{32}$/', $campaignToken) ? $campaignToken : '']);
    $campaignId = $campaignStmt->fetchColumn() ?: null;
    $event = $pdo->prepare('INSERT INTO analytics_events (event_type,visitor_id,session_id,product_id,campaign_id,path) VALUES (?,?,?,?,?,?)');
    $visitorId = preg_match('/^[a-f0-9]{64}$/', (string) ($_COOKIE['lg_visitor'] ?? '')) ? (string) $_COOKIE['lg_visitor'] : hash('sha256', session_id());
    $event->execute(['buy_click', $visitorId, (string) $_SESSION['analytics_session_id'], $analyticsProductId, $campaignId, '/acheter.php']);
} catch (PDOException $exception) {
    error_log('Analytics direct buy: ' . $exception->getMessage());
}
redirect('commande.php');
