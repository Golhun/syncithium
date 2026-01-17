<?php
declare(strict_types=1);

/**
 * Syncithium helpers
 * - Escaping
 * - Config access
 * - URLs + redirects
 * - Sessions
 * - CSRF
 * - Flash messages
 */

/**
 * HTML escape helper
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Access global config safely (set in bootstrap as $GLOBALS['config']).
 * Supports dot notation, e.g. "app.base_url".
 */
function app_config(?string $key = null, mixed $default = null): mixed
{
    $cfg = $GLOBALS['config'] ?? null;
    if (!is_array($cfg)) return $default;
    if ($key === null) return $cfg;

    $parts = explode('.', $key);
    $cur = $cfg;

    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return $default;
        $cur = $cur[$p];
    }

    return $cur;
}

/**
 * Start session if not already started.
 */
function session_start_safe(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

/**
 * Detect https in a consistent way.
 */
function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
    return false;
}

/**
 * Build the base URL to your app's public folder.
 * Priority:
 * 1) config app.base_url (recommended)
 * 2) auto-detect from SCRIPT_NAME (best effort)
 *
 * $path can be string, null, or even array (ignored) to avoid fatals.
 */
function base_url(mixed $path = ''): string
{
    $configured = (string)app_config('app.base_url', '');

    $p = is_string($path) ? $path : '';
    $p = ltrim($p, '/');

    if ($configured !== '') {
        $base = rtrim($configured, '/');
        return $p !== '' ? $base . '/' . $p : $base;
    }

    $scheme = request_is_https() ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    // Example: /syncithium/public/index.php -> /syncithium/public
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = ($dir === '/' || $dir === '.' ? '' : $dir);
    $dir = rtrim($dir, '/');

    $base = $scheme . '://' . $host . ($dir !== '' ? $dir : '');
    $base = rtrim($base, '/');

    return $p !== '' ? $base . '/' . $p : $base;
}

/**
 * Build a URL to a route: index.php?r=...
 */
function url_for(string $route, array $query = []): string
{
    $query = array_merge(['r' => $route], $query);
    return base_url('index.php?' . http_build_query($query));
}

/**
 * Redirect helper.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * CSRF helpers
 */
function csrf_token(): string
{
    session_start_safe();

    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify_or_abort(): void
{
    session_start_safe();

    $posted = $_POST['_csrf'] ?? '';
    $valid = is_string($posted)
        && isset($_SESSION['_csrf'])
        && is_string($_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], $posted);

    if (!$valid) {
        http_response_code(419);
        echo '419 CSRF Token Mismatch';
        exit;
    }
}

/**
 * Flash messages (stored in session, shown once).
 */
function flash_set(string $key, string $message): void
{
    session_start_safe();

    if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        $_SESSION['_flash'] = [];
    }

    $_SESSION['_flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    session_start_safe();

    if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return null;
    }

    if (!array_key_exists($key, $_SESSION['_flash'])) {
        return null;
    }

    $msg = (string)$_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);

    return $msg;
}
