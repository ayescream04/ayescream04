<?php
/**
 * Shared bootstrap: session, database, auth + flash + CSRF helpers.
 * Include this FIRST on every protected page (before includes/header.php)
 * so redirects can still send headers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/../db_con.php';   // provides $con (mysqli) and $pdo (PDO)

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user(): array
{
    return [
        'id'       => $_SESSION['user_id']  ?? null,
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role']     ?? '',
        'name'     => $_SESSION['name']     ?? '',
        'org_id'   => $_SESSION['org_id']   ?? null,
    ];
}

/** Redirect to login unless logged in (and, if given, unless the role matches). */
function require_login(?string $role = null): void
{
    if (!is_logged_in() || ($role !== null && ($_SESSION['role'] ?? '') !== $role)) {
        header('Location: ../login.php');
        exit;
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** Call at the top of every POST handler. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(400);
        exit('Invalid or expired form token. Go back, refresh the page and try again.');
    }
}
