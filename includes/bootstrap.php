<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$_SESSION['cart'] ??= [];

// Suivi analytique léger : identifiants anonymes, sans adresse IP ni données personnelles.
if (PHP_SAPI !== 'cli' && empty($_COOKIE['lg_visitor'])) {
    $visitorId = hash('sha256', bin2hex(random_bytes(32)));
    setcookie('lg_visitor', $visitorId, [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['lg_visitor'] = $visitorId;
}
if (PHP_SAPI !== 'cli') {
    $_SESSION['analytics_session_id'] ??= hash('sha256', bin2hex(random_bytes(32)));
    $visitorId = preg_match('/^[a-f0-9]{64}$/', (string) ($_COOKIE['lg_visitor'] ?? '')) ? (string) $_COOKIE['lg_visitor'] : hash('sha256', session_id());
    $sessionId = (string) $_SESSION['analytics_session_id'];
    $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $script = basename($scriptName);
    $campaignId = null;
    $campaignToken = (string) ($_COOKIE['lg_qr_campaign'] ?? '');
    if (preg_match('/^[a-f0-9]{32}$/', $campaignToken)) {
        try {
            $campaignStmt = $pdo->prepare('SELECT id FROM qr_campaigns WHERE token=?');
            $campaignStmt->execute([$campaignToken]);
            $campaignId = $campaignStmt->fetchColumn() ?: null;
        } catch (PDOException $exception) {
            error_log('Analytics campaign lookup: ' . $exception->getMessage());
        }
    }
    if (!str_contains($scriptName, '/admin/') && !in_array($script, ['analytics-track.php', 'qr-go.php'], true)) {
        $eventType = $script === 'produit.php' ? 'product_view' : 'page_view';
        $productId = $script === 'produit.php' ? max(0, (int) ($_GET['id'] ?? 0)) : null;
        $key = $eventType . ':' . ($productId ?? 0) . ':' . $script;
        if (($_SESSION['analytics_last'] ?? '') !== $key) {
            $_SESSION['analytics_last'] = $key;
            $path = '/' . ltrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
            $referrer = isset($_SERVER['HTTP_REFERER'])
                ? substr((string) (parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: ''), 0, 255)
                : null;
            try {
                $statement = $pdo->prepare('INSERT INTO analytics_events (event_type,visitor_id,session_id,product_id,campaign_id,path,referrer) VALUES (?,?,?,?,?,?,?)');
                $statement->execute([$eventType, $visitorId, $sessionId, $productId ?: null, $campaignId, substr($path, 0, 255), $referrer]);
            } catch (PDOException $exception) {
                error_log('Analytics page view: ' . $exception->getMessage());
            }
        }
    }
}
