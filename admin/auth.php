<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

const ADMIN_ROLES = ['gerant' => 'Gérant', 'vendeur' => 'Vendeur', 'preparateur' => 'Préparateur'];

/**
 * Pages autorisées par rôle (le gérant a accès à tout). La première page est la page d'accueil du rôle.
 * « parametres.php » reste ouvert à tous : chacun doit pouvoir changer son mot de passe.
 */
const ADMIN_PAGE_ACCESS = [
    'vendeur' => ['commandes.php', 'clients.php', 'produits.php', 'stock.php', 'coffrets.php', 'promotions.php', 'recherche.php', 'parametres.php'],
    'preparateur' => ['commandes.php', 'stock.php', 'recherche.php', 'parametres.php'],
];

function admin_can_access(string $role, string $page): bool
{
    return $role === 'gerant' || in_array($page, ADMIN_PAGE_ACCESS[$role] ?? [], true);
}

function admin_home(string $role): string
{
    return $role === 'gerant' ? 'index.php' : (ADMIN_PAGE_ACCESS[$role][0] ?? 'parametres.php');
}

function require_admin(): void
{
    if (empty($_SESSION['admin_id'])) redirect('../admin/login.php');
}

/** Compte connecté (rechargé à chaque requête : un compte désactivé est déconnecté). */
function admin_user(PDO $pdo): array
{
    static $user = null;
    if ($user !== null) return $user;
    $stmt = $pdo->prepare('SELECT id, email, role FROM admins WHERE id=? AND active=1');
    $stmt->execute([(int) ($_SESSION['admin_id'] ?? 0)]);
    $user = $stmt->fetch() ?: [];
    if (!$user) { $_SESSION = []; session_destroy(); redirect('login.php'); }
    return $user;
}

function admin_is(PDO $pdo, string ...$roles): bool
{
    return in_array(admin_user($pdo)['role'], $roles, true);
}

/** Réservé à certains rôles (le gérant a toujours accès). */
function require_role(PDO $pdo, string ...$roles): void
{
    if (!admin_is($pdo, 'gerant', ...$roles)) {
        flash('Accès réservé au gérant.', 'error');
        redirect(admin_home((string) admin_user($pdo)['role']));
    }
}

function admin_post_guard(): void
{
    if (!csrf_valid()) { http_response_code(400); exit('Session expirée, rechargez la page.'); }
}

require_admin();

// Contrôle d'accès par rôle, appliqué à chaque page de l'administration.
(function (PDO $pdo): void {
    $role = (string) admin_user($pdo)['role'];
    $page = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (admin_can_access($role, $page)) return;
    // Le tableau de bord est la page d'arrivée après connexion : on redirige sans message d'erreur.
    if ($page !== 'index.php') flash('Cette page est réservée au gérant.', 'error');
    redirect(admin_home($role));
})($pdo);
