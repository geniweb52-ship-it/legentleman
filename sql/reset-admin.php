<?php
/**
 * Réinitialise (ou crée) un compte administrateur, en ligne de commande uniquement :
 *   php sql/reset-admin.php admin@legentleman.sn            -> génère un mot de passe
 *   php sql/reset-admin.php admin@legentleman.sn MonMotDePasse
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/db.php';

$email = $argv[1] ?? 'admin@legentleman.sn';
$password = $argv[2] ?? substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 14);
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
    exit("E-mail valide et mot de passe de 10 caractères minimum requis.\n");
}
$pdo->prepare("INSERT INTO admins (email, password_hash, role, active) VALUES (?, ?, 'gerant', 1)
               ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash), active=1")
    ->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
echo "Compte administrateur prêt.\nE-mail       : $email\nMot de passe : $password\n";
