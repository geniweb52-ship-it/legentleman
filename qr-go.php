<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';

$token = (string) ($_GET['c'] ?? '');
if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
    http_response_code(404);
    exit('Lien introuvable.');
}

$statement = $pdo->prepare('SELECT id,product_id,target_path FROM qr_campaigns WHERE token=?');
$statement->execute([$token]);
$campaign = $statement->fetch();
if (!$campaign) {
    http_response_code(404);
    exit('Lien introuvable.');
}

$targetPath = (string) $campaign['target_path'];
$path = parse_url($targetPath, PHP_URL_PATH);
if (!$path || parse_url($targetPath, PHP_URL_SCHEME) || parse_url($targetPath, PHP_URL_HOST) || str_contains($path, '..')) {
    http_response_code(500);
    error_log('QR campaign has an invalid destination: ' . (int) $campaign['id']);
    exit('Destination invalide.');
}

$visitorId = preg_match('/^[a-f0-9]{64}$/', (string) ($_COOKIE['lg_visitor'] ?? ''))
    ? (string) $_COOKIE['lg_visitor']
    : hash('sha256', session_id());
try {
    $event = $pdo->prepare('INSERT INTO analytics_events (event_type,visitor_id,session_id,product_id,campaign_id,path) VALUES (?,?,?,?,?,?)');
    $event->execute(['qr_scan', $visitorId, (string) $_SESSION['analytics_session_id'], $campaign['product_id'], $campaign['id'], '/qr-go.php']);
} catch (PDOException $exception) {
    error_log('QR campaign scan: ' . $exception->getMessage());
}

setcookie('lg_qr_campaign', $token, [
    'expires' => time() + 30 * 86400,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
redirect(url($targetPath));
