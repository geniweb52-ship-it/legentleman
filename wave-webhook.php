<?php
/**
 * Webhook Wave : "checkout.session.completed".
 * À déclarer dans le portail Wave Business (Développeurs > Webhooks) avec l'adresse
 * https://votre-site/gentleman/wave-webhook.php, puis copier le secret dans WAVE_WEBHOOK_SECRET.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/orders.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || WAVE_WEBHOOK_SECRET === '') { http_response_code(404); exit('{}'); }

$body = (string) file_get_contents('php://input');
$header = (string) ($_SERVER['HTTP_WAVE_SIGNATURE'] ?? '');
$timestamp = '';
$signatures = [];
foreach (explode(',', $header) as $part) {
    [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
    if ($k === 't') $timestamp = $v;
    if ($k === 'v1') $signatures[] = $v;
}
$expected = hash_hmac('sha256', $timestamp . $body, WAVE_WEBHOOK_SECRET);
$valid = $timestamp !== '' && abs(time() - (int) $timestamp) < 300;
$valid = $valid && array_filter($signatures, fn($s) => hash_equals($expected, $s));
if (!$valid) { http_response_code(401); exit('{"error":"invalid signature"}'); }

$event = json_decode($body, true) ?: [];
$session = $event['data'] ?? [];
if (($event['type'] ?? '') === 'checkout.session.completed' && ($session['payment_status'] ?? '') === 'succeeded') {
    $order = find_order($pdo, (string) ($session['client_reference'] ?? ''));
    if ($order && (int) ($session['amount'] ?? 0) >= (int) $order['total']) {
        mark_order_paid($pdo, (int) $order['id'], $session['transaction_id'] ?? null);
    }
}
echo '{"ok":true}';
