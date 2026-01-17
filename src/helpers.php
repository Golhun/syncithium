<?php
declare(strict_types=1);

/**
 * HTML escape helper
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Access global config safely (set in bootstrap).
 */
function app_config(?string $key = null, mixed $default = null): mixed
{
    $cfg = $GLOBALS['config'] ?? null;
    if (!is_array($cfg)) return $default;
    if ($key === null) return $cfg;

    // simple dot notation: "app.base_url"
    $parts = explode('.', $key);
    $cur = $cfg;
    foreach ($parts as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return $default;
        $cur = $cur[$p];
    }
    return $cur;
}

/**
 * Build the base URL to your app's public folder.
 * - If config app.base_url is set, it will be used.
 * - Otherwise auto-detect from SCRIPT_NAME.
 *
 * $path can be string, null, or even array (ignored) to avoid fatals.
 */
function base_url(mixed $path = ''): string
{
    $configured = (string)(app_config('app.base_url', ''));
    if ($configured !== '') {
        $base = rtrim($configured, '/');
        $p = is_string($path) ? $path : '';
        return $p !== '' ? $base . '/' . ltrim($p, '/') : $base;
    }

    // Auto-detect (best effort)
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    // e.g. /syncithium/public/index.php -> /syncithium/public
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = ($dir === '/' || $dir === '.') ? '' : $dir;

    $base = $scheme . '://' . $host . $dir;
    $base = rtrim($base, '/');

    $p = is_string($path) ? $path : '';
    return $p !== '' ? $base . '/' . ltrim($p, '/') : $base;
}

/**
 * Build a URL to a route: index.php?r=...
 */
function url_for(string $route, array $query = []): string
{
    $query = array_merge(['r' => $route], $query);
    return rtrim(base_url(), '/') . '/index.php?' . http_build_query($query);
}

/**
 * CSRF helpers
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['_csrf'])) {
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
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $posted = $_POST['_csrf'] ?? '';
    $valid = isset($_SESSION['_csrf']) && is_string($posted) && hash_equals((string)$_SESSION['_csrf'], $posted);

    if (!$valid) {
        http_response_code(419);
        echo '419 CSRF Token Mismatch';
        exit;
    }
}
