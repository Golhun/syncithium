<?php

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_auth(array $config): void {
    if (!current_user()) {
        flash_set('error', 'Please sign in to continue.');
        redirect(base_url($config) . '/index.php?r=login');
    }
}

function require_admin(array $config): void {
    require_auth($config);
    $user = current_user();
    if (empty($user['is_admin'])) {
        http_response_code(403);
        echo 'Admin access required';
        exit;
    }
}

function auth_login(PDO $db, array $userRow): void {
    // Store minimal safe session payload
    $_SESSION['user'] = [
        'id' => (int)$userRow['id'],
        'name' => (string)$userRow['name'],
        'email' => (string)$userRow['email'],
        'is_admin' => (int)($userRow['is_admin'] ?? 0),
    ];
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}
