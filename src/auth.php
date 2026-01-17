<?php
declare(strict_types=1);

/**
 * Return the currently logged-in user as an associative array, or null.
 * Uses session user_id and fetches from DB for fresh state (e.g., is_admin changes).
 */
function current_user(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, is_admin, created_at FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

/**
 * Log a user in by storing their id in the session.
 */
function login_user(int $userId): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $_SESSION['user_id'] = $userId;
}

/**
 * Log out the current user.
 */
function logout_user(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    unset($_SESSION['user_id']);
}

/**
 * Require login, otherwise redirect to login.
 */
function require_login(): void
{
    if (!current_user()) {
        header('Location: ' . url_for('login'));
        exit;
    }
}

/**
 * Require admin, otherwise show 403.
 */
function require_admin(): void
{
    $u = current_user();
    if (!$u || empty($u['is_admin'])) {
        http_response_code(403);
        echo '403 Forbidden';
        exit;
    }
}
