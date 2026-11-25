<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_valid()) {
    http_response_code(400);
    exit;
}
$eventType = (string) ($_POST['event_type'] ?? '');
if (!in_array($eventType, ['product_click', 'share_click', 'buy_click'], true)) {
    http_response_code(422);
    exit;
}
$productId = (int) ($_POST['product_id'] ?? 0);
$visitorId = preg_match('/^[a-f0-9]{64}$/', (string) ($_COOKIE['lg_visitor'] ?? '')) ? (string) $_COOKIE['lg_visitor'] : hash('sha256', session_id());
$campaignToken = (string) ($_COOKIE['lg_qr_campaign'] ?? '');
$campaignId = null;
$path = substr((string) parse_url($_SERVER['HTTP_REFERER'] ?? '/', PHP_URL_PATH), 0, 255);
try {
    $campaignStmt = $pdo->prepare('SELECT id FROM qr_campaigns WHERE token=?');
    $campaignStmt->execute([preg_match('/^[a-f0-9]{32}$/', $campaignToken) ? $campaignToken : '']);
    $campaignId = $campaignStmt->fetchColumn() ?: null;
    $statement = $pdo->prepare('INSERT INTO analytics_events (event_type,visitor_id,session_id,product_id,campaign_id,path) VALUES (?,?,?,?,?,?)');
    $statement->execute([$eventType, $visitorId, (string) $_SESSION['analytics_session_id'], $productId ?: null, $campaignId, $path]);
} catch (PDOException $exception) {
    error_log('Analytics interaction: ' . $exception->getMessage());
    http_response_code(503);
    exit;
}
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
