<?php
// Copiez ce fichier en config.php puis renseignez vos valeurs (config.php n'est jamais envoyé sur GitHub).
// Clé secrète : php -r "echo bin2hex(random_bytes(24));"

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'le_gentleman';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

const BASE_URL = '/gentleman';
// Adresse publique du site, en https, une fois en ligne (ex. 'https://legentleman.sn').
// Vide = détectée automatiquement. Wave exige une adresse https pour le retour après paiement.
const SITE_URL = '';
const SHOP_NAME = 'LE GENTLEMAN';
const SHOP_TAGLINE = "L'élégance, sans compromis.";
const SHOP_ADDRESS = 'Dakar, Sénégal';
const SHOP_PHONE = '221776502698';
const WHATSAPP_NUMBER = '221776502698';
const SHOP_TIKTOK = 'https://www.tiktok.com/@legentleman395'; // page TikTok (laisser '' pour masquer)
const ORANGE_PAYMENT_LINK = '';
const FREE_DELIVERY_THRESHOLD = 100000;

// Clé secrète du site (liens de facture). Ne pas partager, ne pas modifier une fois en ligne.
const APP_SECRET = 'A_REMPLACER_par_une_longue_cle_aleatoire';

/*
 * Paiement Wave
 * - Sans clé API : le client est envoyé sur WAVE_PAYMENT_LINK et saisit lui-même le montant.
 * - Avec clé API (portail Wave Business > Développeurs) : montant prérempli, retour automatique
 *   sur le site après paiement et confirmation automatique de la commande.
 *   WAVE_WEBHOOK_SECRET (facultatif, conseillé) : secret du webhook "checkout.session.completed"
 *   pointant vers https://votre-site/wave-webhook.php
 */
const WAVE_PAYMENT_LINK = 'https://pay.wave.com/m/M_sn_qo9ZfHWTdm-T/c/sn/';
const WAVE_API_KEY = '';
const WAVE_WEBHOOK_SECRET = '';

/*
 * Paiement en ligne GeniusPay (Wave, Orange Money, carte bancaire…) — https://pay.genius.ci
 * - Clés API : tableau de bord GeniusPay > Développeurs / API. Les clés pk_sandbox_… / sk_sandbox_…
 *   servent aux tests (aucun débit réel) ; passez aux clés pk_live_… / sk_live_… pour encaisser.
 * - Dès que les deux clés sont renseignées, l'option « Payer en ligne maintenant » apparaît à la commande.
 * - GENIUSPAY_WEBHOOK_SECRET (conseillé) : secret whsec_… du webhook (événement payment.success)
 *   pointant vers https://votre-site/geniuspay-webhook.php
 */
const GENIUSPAY_PUBLIC_KEY = '';
const GENIUSPAY_SECRET_KEY = '';
const GENIUSPAY_WEBHOOK_SECRET = '';
const GENIUSPAY_API_URL = 'https://pay.genius.ci/api/v1/merchant';
