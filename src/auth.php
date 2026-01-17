<?php
declare(strict_types=1);

/**
 * Syncithium auth helpers
 * - current_user()
 * - login/logout
 * - admin checks with schema-tolerant fallback
 *
 * Requirements:
 * - helpers.php is loaded (session_start_safe, redirect, url_for)
 * - bootstrap.php exposes db() PDO accessor
 */

function current_user(): ?array
{
    session_start_safe();

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    // Use SELECT * to avoid hard failures when schema differs (e.g., is_admin missing).
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$userId]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function login_user(int $userId): void
{
    session_start_safe();
    $_SESSION['user_id'] = $userId;

    // Basic session hardening (safe even if repeated)
    if (!isset($_SESSION['_regen'])) {
        @session_regenerate_id(true);
        $_SESSION['_regen'] = 1;
    }
}

function logout_user(): void
{
    session_start_safe();

    unset($_SESSION['user_id']);
    unset($_SESSION['_regen']);

    // Optional: clear CSRF and flash to avoid stale state after logout
    unset($_SESSION['_csrf']);
    unset($_SESSION['_flash']);

    @session_regenerate_id(true);
}

/**
 * Determine whether the user is an admin.
 * Supports:
 * - is_admin (truthy)
 * - role = 'admin'
 * - fallback: user with id=1 is admin
 */
function is_admin_user(array $user): bool
{
    if (!empty($user['is_admin'])) {
        return true;
    }

    if (isset($user['role']) && is_string($user['role'])) {
        return strtolower(trim($user['role'])) === 'admin';
    }

    return ((int)($user['id'] ?? 0) === 1);
}

function require_login(): void
{
    if (!current_user()) {
        redirect(url_for('login'));
    }
}

function require_admin(): void
{
    $u = current_user();
    if (!$u || !is_admin_user($u)) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }
}
