<?php
/** Redirige le client vers Wave pour payer sa commande (nouvelle session à chaque clic : elles expirent après 30 min). */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/orders.php';

$ref = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$order = $ref !== '' ? find_order($pdo, $ref) : null;
if (!$order || !order_token_valid($ref, (string) ($_GET['t'] ?? '')) || $order['payment_method'] !== 'wave' || $order['status'] === 'annulee') {
    redirect(url('suivi.php'));
}
$order = wave_sync_order($pdo, $order);
if (order_is_paid($order)) redirect(order_page_url($ref));
redirect(wave_payment_url($pdo, $order));
