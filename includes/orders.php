<?php
/**
 * Commandes : facture, message WhatsApp complet et paiement Wave.
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Adresse absolue d'une image ou d'une page du site (les adresses externes sont gardées telles quelles). */
function absolute_url(string $url): string
{
    $url = image_normalize($url);
    if ($url === '' || preg_match('~^https?://~i', $url)) return $url;
    $base = rtrim(BASE_URL, '/');
    return site_url(ltrim($base !== '' && str_starts_with($url, $base) ? substr($url, strlen($base)) : $url, '/'));
}

/** Adresse absolue du site (ex. https://legentleman.sn/gentleman/facture.php). */
function site_url(string $path = ''): string
{
    if (SITE_URL !== '') return rtrim(SITE_URL, '/') . '/' . ltrim(url($path), '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    // Derrière un tunnel / proxy (devtunnels, ngrok…), l'hôte public arrive dans X-Forwarded-Host.
    $host = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '')[0]) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https://' : 'http://') . $host . url($path);
}

/** Jeton infalsifiable donnant accès à la facture d'une commande. */
function order_token(string $reference): string
{
    return substr(hash_hmac('sha256', $reference, APP_SECRET), 0, 20);
}

function order_token_valid(string $reference, string $token): bool
{
    return $token !== '' && hash_equals(order_token($reference), $token);
}

function invoice_url(string $reference, bool $absolute = true): string
{
    $path = 'facture.php?ref=' . rawurlencode($reference) . '&t=' . order_token($reference);
    return $absolute ? site_url($path) : url($path);
}

function order_page_url(string $reference, array $extra = []): string
{
    return site_url('merci.php?' . http_build_query(['ref' => $reference, 't' => order_token($reference)] + $extra));
}

function find_order(PDO $pdo, string $reference): ?array
{
    $stmt = $pdo->prepare('SELECT o.*, dz.name zone_name FROM orders o LEFT JOIN delivery_zones dz ON dz.id=o.delivery_zone_id WHERE o.reference=?');
    $stmt->execute([$reference]);
    return $stmt->fetch() ?: null;
}

function order_items(PDO $pdo, int $orderId): array
{
    $stmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    if (!$items) return [];
    $componentStmt = $pdo->prepare(
        'SELECT oic.* FROM order_item_components oic
         JOIN order_items oi ON oi.id=oic.order_item_id
         WHERE oi.order_id=? ORDER BY oic.id'
    );
    $componentStmt->execute([$orderId]);
    $components = [];
    foreach ($componentStmt->fetchAll() as $component) {
        $components[(int) $component['order_item_id']][] = $component;
    }
    foreach ($items as &$item) $item['components'] = $components[(int) $item['id']] ?? [];
    unset($item);
    return $items;
}

/** Date à laquelle toute la commande sera prête (articles sur commande), ou null si tout est disponible. */
function order_ready_date(array $items): ?string
{
    $dates = array_filter(array_map(fn($item) => (string) ($item['ready_date'] ?? ''), $items));
    return $dates ? max($dates) : null;
}

/** Mention « Sur commande · prêt le … » d'une ligne de commande (vide si l'article était disponible). */
function order_item_ready_text(array $item): string
{
    return !empty($item['ready_date']) ? 'Sur commande · prêt le ' . date_fr((string) $item['ready_date']) : '';
}

/** Ajoute la colonne « channel » (site / WhatsApp) aux bases installées avant cette fonctionnalité. */
function orders_channel_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (!$pdo->query("SHOW COLUMNS FROM orders LIKE 'channel'")->fetch()) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN channel ENUM('site','whatsapp') NOT NULL DEFAULT 'site' AFTER payment_method");
    }
}

/** Petit badge « WhatsApp » pour les commandes passées via le bouton WhatsApp. */
function order_channel_badge(array $order): string
{
    if (($order['channel'] ?? 'site') !== 'whatsapp') return '';
    return '<span class="inline-flex items-center gap-1 rounded-full bg-[#25D366]/10 text-[#1DA851] px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap">' . icon('whatsapp', 'w-3 h-3') . ' WhatsApp</span>';
}

function payment_label(string $method): string
{
    return ['wave' => 'Wave', 'orange' => 'Orange Money', 'delivery' => 'Espèces à la livraison'][$method] ?? $method;
}

function order_is_paid(array $order): bool
{
    return !empty($order['paid_at']);
}

/** Message WhatsApp prérempli avec toutes les informations de la commande. */
function order_whatsapp_message(array $order, array $items): string
{
    $lines = ['Bonjour ' . SHOP_NAME . ', voici ma commande :', '', '🧾 Référence : ' . $order['reference'], ''];
    $lines[] = '👤 Prénom : ' . ($order['first_name'] ?: $order['customer_name']);
    if (!empty($order['last_name'])) $lines[] = '👤 Nom : ' . $order['last_name'];
    $lines[] = '📞 Téléphone : ' . $order['phone'];
    $lines[] = '📍 Livraison : ' . trim(($order['zone_name'] ?? '') . ' — ' . $order['address'], ' —');
    if (!empty($order['note'])) $lines[] = '📝 Instructions : ' . $order['note'];
    $lines[] = '';
    $lines[] = '🛍️ Articles :';
    foreach ($items as $it) {
        $line = '• ' . $it['product_name'];
        if (!empty($it['components'])) {
            $line .= ' : ' . implode(', ', array_map(
                function ($component) {
                    $options = array_filter([
                        !empty($component['size']) ? 'taille ' . $component['size'] : '',
                        !empty($component['color']) ? 'couleur ' . $component['color'] : '',
                    ]);
                    return $component['product_name'] . ($options ? ' (' . implode(', ', $options) . ')' : '') . ' × ' . (int) $component['quantity_per_bundle'];
                },
                $it['components']
            ));
        } else {
            $line .= ' (taille ' . $it['size'] . ', ' . $it['color'] . ')';
        }
        $lines[] = $line . ' × ' . (int) $it['quantity'] . ' = ' . money((int) $it['price'] * (int) $it['quantity'])
            . (!empty($it['ready_date']) ? ' — sur commande, prêt le ' . date_fr((string) $it['ready_date']) : '');
    }
    if ($readyDate = order_ready_date($items)) {
        $lines[] = '';
        $lines[] = '🗓️ Commande prête le ' . date_fr($readyDate, true) . ' (articles sur commande).';
    }
    $lines[] = '';
    $lines[] = 'Sous-total : ' . money((int) $order['subtotal']);
    $lines[] = 'Livraison : ' . ((int) $order['delivery_fee'] ? money((int) $order['delivery_fee']) : 'offerte');
    $lines[] = '💰 Total : ' . money((int) $order['total']);
    $paymentMethod = (string) $order['payment_method'];
    $lines[] = '💳 Paiement : ' . payment_label($paymentMethod) . (order_is_paid($order) ? ' — PAYÉ ✅' . ($order['wave_transaction_id'] ? ' (transaction ' . $order['wave_transaction_id'] . ')' : '') : ' — en attente');
    if (!order_is_paid($order)) {
        if ($paymentMethod === 'wave') $lines[] = 'Merci de m’envoyer le lien de paiement Wave. Après le règlement, je vous transmettrai une capture du reçu.';
        elseif ($paymentMethod === 'orange') $lines[] = 'Merci de m’envoyer les instructions de paiement Orange Money. Après le règlement, je vous transmettrai une capture du reçu.';
        else $lines[] = 'Je réglerai le montant au livreur à la réception.';
    }
    $lines[] = '';
    $lines[] = '📄 Ma facture : ' . invoice_url((string) $order['reference']);
    return implode("\n", $lines);
}

/** Marque une commande comme payée (une seule fois). */
function mark_order_paid(PDO $pdo, int $orderId, ?string $transactionId = null): void
{
    $pdo->prepare("UPDATE orders SET paid_at=COALESCE(paid_at, NOW()), wave_transaction_id=COALESCE(?, wave_transaction_id), status=IF(status='nouvelle','payee',status) WHERE id=?")
        ->execute([$transactionId, $orderId]);
}

/* ---------------------------------------------------------------- Wave */

/** L'API Wave Checkout est utilisable : clé présente et site en https (exigé par Wave). */
function wave_api_enabled(): bool
{
    return WAVE_API_KEY !== '' && str_starts_with(site_url(), 'https://');
}

/** Appel à l'API Wave. Renvoie [code HTTP, réponse décodée]. */
function wave_request(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init('https://api.wave.com' . $path);
    $options = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . WAVE_API_KEY, 'Content-Type: application/json'],
    ];
    if ($body !== null) $options[CURLOPT_POSTFIELDS] = json_encode($body);
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return [$code, is_string($raw) ? (json_decode($raw, true) ?: []) : []];
}

/**
 * Lien de paiement Wave pour une commande.
 * Avec l'API : session Checkout au montant exact, retour automatique sur le site.
 * Sans l'API : lien marchand classique (le client saisit le montant affiché).
 */
function wave_payment_url(PDO $pdo, array $order): string
{
    if (!wave_api_enabled()) return WAVE_PAYMENT_LINK;

    [$code, $session] = wave_request('POST', '/v1/checkout/sessions', [
        'amount' => (string) (int) $order['total'],
        'currency' => 'XOF',
        'client_reference' => (string) $order['reference'],
        'success_url' => order_page_url((string) $order['reference'], ['wave' => 'success']),
        'error_url' => order_page_url((string) $order['reference'], ['wave' => 'error']),
    ]);
    if ($code >= 200 && $code < 300 && !empty($session['wave_launch_url'])) {
        $pdo->prepare('UPDATE orders SET wave_session_id=? WHERE id=?')->execute([$session['id'] ?? null, (int) $order['id']]);
        return (string) $session['wave_launch_url'];
    }
    error_log('Wave checkout: HTTP ' . $code . ' ' . json_encode($session));
    return WAVE_PAYMENT_LINK;
}

/** Vérifie auprès de Wave si la session de la commande est payée ; met la commande à jour. */
function wave_sync_order(PDO $pdo, array $order): array
{
    if (order_is_paid($order) || empty($order['wave_session_id']) || WAVE_API_KEY === '') return $order;
    [$code, $session] = wave_request('GET', '/v1/checkout/sessions/' . rawurlencode((string) $order['wave_session_id']));
    if ($code === 200 && ($session['payment_status'] ?? '') === 'succeeded'
        && (int) ($session['amount'] ?? 0) >= (int) $order['total']) {
        mark_order_paid($pdo, (int) $order['id'], $session['transaction_id'] ?? null);
        return find_order($pdo, (string) $order['reference']) ?? $order;
    }
    return $order;
}
