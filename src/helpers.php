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


/**
 * Heroicons (local SVG) loader
 *
 * Expected folders:
 *  - public/assets/icons/heroicons/outline/<name>.svg
 *  - public/assets/icons/heroicons/solid/<name>.svg
 *
 * Usage:
 *   echo heroicon('outline', 'home', 'h-5 w-5 text-slate-400');
 *   echo heroicon_swap('home', 'h-5 w-5', 'text-slate-400', 'text-white');
 */

function heroicon(string $style, string $name, string $class = '', array $attrs = []): string
{
    static $cache = [];

    $style = strtolower(trim($style));
    $style = in_array($style, ['outline', 'solid'], true) ? $style : 'outline';

    $name = trim($name);
    if ($name === '') return '';

    // Compute absolute file path
    $root = dirname(__DIR__); // src -> project root
    $file = $root . '/public/assets/icons/heroicons/' . $style . '/' . $name . '.svg';

    $key = $style . ':' . $name . ':' . $class . ':' . md5(json_encode($attrs));
    if (isset($cache[$key])) return $cache[$key];

    if (!is_file($file)) {
        // Fail soft. Avoid throwing in UI rendering.
        return $cache[$key] = '';
    }

    $svg = (string)file_get_contents($file);
    if ($svg === '') return $cache[$key] = '';

    // Inject/merge class
    if ($class !== '') {
        if (preg_match('/\sclass="([^"]*)"/', $svg, $m)) {
            $existing = trim($m[1]);
            $merged = trim($existing . ' ' . $class);
            $svg = preg_replace('/\sclass="[^"]*"/', ' class="' . e($merged) . '"', $svg, 1);
        } else {
            $svg = preg_replace('/<svg\b/', '<svg class="' . e($class) . '"', $svg, 1);
        }
    }

    // Inject extra attributes (aria, role, etc.)
    foreach ($attrs as $k => $v) {
        $k = trim((string)$k);
        if ($k === '') continue;

        $v = (string)$v;

        // If attr exists already, replace first occurrence
        if (preg_match('/\s' . preg_quote($k, '/') . '="[^"]*"/', $svg)) {
            $svg = preg_replace(
                '/\s' . preg_quote($k, '/') . '="[^"]*"/',
                ' ' . $k . '="' . e($v) . '"',
                $svg,
                1
            );
        } else {
            $svg = preg_replace('/<svg\b/', '<svg ' . $k . '="' . e($v) . '"', $svg, 1);
        }
    }

    return $cache[$key] = $svg;
}

/**
 * Outline by default, Solid on hover.
 * Works best when the parent has class "group".
 */
function heroicon_swap(
    string $name,
    string $sizeClass = 'h-5 w-5',
    string $baseColorClass = 'text-slate-400',
    string $hoverColorClass = 'text-white'
): string {
    $outline = heroicon('outline', $name, $sizeClass . ' ' . $baseColorClass . ' group-hover:hidden', [
        'aria-hidden' => 'true',
        'focusable' => 'false',
    ]);

    $solid = heroicon('solid', $name, $sizeClass . ' ' . $hoverColorClass . ' hidden group-hover:inline-block', [
        'aria-hidden' => 'true',
        'focusable' => 'false',
    ]);

    return $outline . $solid;
}
