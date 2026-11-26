<?php
/**
 * Webhook GeniusPay (événements payment.success, payment.failed…).
 * À déclarer dans le tableau de bord GeniusPay (Webhooks) avec l'adresse
 * https://votre-site/geniuspay-webhook.php, puis copier le secret (whsec_…) dans GENIUSPAY_WEBHOOK_SECRET.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/orders.php';

header('Content-Type: application/json');
$secret = geniuspay_config('GENIUSPAY_WEBHOOK_SECRET');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $secret === '') { http_response_code(404); exit('{}'); }

$body = (string) file_get_contents('php://input');
$timestamp = (string) ($_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '');
$signature = strtolower(trim((string) ($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '')));
$signature = preg_replace('/^sha256=/', '', $signature);
$event = json_decode($body, true) ?: [];

// Signature = HMAC-SHA256(timestamp + "." + corps JSON, secret). On accepte le corps brut
// et le JSON réencodé (c'est ainsi que l'exemple PHP de la documentation GeniusPay la calcule).
$candidates = array_unique([$body, json_encode($event), json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]);
$valid = $timestamp !== '' && $signature !== '' && abs(time() - (int) $timestamp) <= 300;
$valid = $valid && array_filter($candidates, fn($payload) => hash_equals(hash_hmac('sha256', $timestamp . '.' . $payload, $secret), $signature));
if (!$valid) { http_response_code(401); exit('{"error":"invalid signature"}'); }

$type = (string) ($event['event'] ?? $_SERVER['HTTP_X_WEBHOOK_EVENT'] ?? '');
$payment = $event['data'] ?? [];
if ($type === 'payment.success') {
    $order = find_order($pdo, (string) ($payment['metadata']['order_reference'] ?? ''));
    if ($order && $order['payment_method'] === 'geniuspay' && geniuspay_payment_settles($payment, $order)) {
        mark_order_paid($pdo, (int) $order['id'], (string) ($payment['reference'] ?? '') ?: null);
    }
}
echo '{"ok":true}';
