<?php
/** Envoie le client sur la page de paiement GeniusPay de sa commande (nouveau paiement à chaque clic : les liens expirent). */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/orders.php';

$ref = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$order = $ref !== '' ? find_order($pdo, $ref) : null;
if (!$order || !order_token_valid($ref, (string) ($_GET['t'] ?? '')) || $order['payment_method'] !== 'geniuspay' || $order['status'] === 'annulee') {
    redirect(url('suivi.php'));
}
$order = geniuspay_sync_order($pdo, $order);
if (order_is_paid($order)) redirect(order_page_url($ref));
$checkoutUrl = geniuspay_checkout_url($pdo, $order);
redirect($checkoutUrl ?? order_page_url($ref, ['gp' => 'unavailable']));
