<?php
declare(strict_types=1);

function e(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $to): never {
  header("Location: {$to}");
  exit;
}

function is_post(): bool {
  return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function flash_set(string $type, string $message): void {
  $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all(): array {
  $msgs = $_SESSION['flash'] ?? [];
  $_SESSION['flash'] = [];
  return $msgs;
}

function valid_email(string $email): bool {
  return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function random_password(int $length = 14): string {
  $raw = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
  return substr($raw, 0, $length);
}

/**
 * Local Heroicons (SVG files) helper.
 * Put SVGs into /public/assets/icons/outline/<name>.svg
 */
function icon(string $name, string $classes = 'w-5 h-5'): string {
  $path = dirname(__DIR__) . "/public/assets/icons/outline/{$name}.svg";
  if (!is_file($path)) return '';
  $svg = file_get_contents($path) ?: '';
  // Add class attribute to the opening <svg ...>
  $svg = preg_replace('/<svg\b([^>]*)>/', '<svg$1 class="'.e($classes).'">', $svg, 1) ?? $svg;
  return $svg;
}


function base64url_encode(string $bin): string {
  return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function make_reset_token(): string {
  // 32 bytes => 256-bit entropy, exceeds OWASP minimum guidance
  return base64url_encode(random_bytes(32));
}

function reset_token_hash(string $token, array $config): string {
  $pepper = (string)($config['security']['reset_token_pepper'] ?? '');
  // HMAC-SHA256, store hex
  return hash_hmac('sha256', $token, $pepper);
}

function render(string $view, array $data = []): void
{
    // Expose these globals inside the function so layout.php can use them
    global $db, $config;

    // Make $data keys available as local variables in views/layout
    extract($data);

    $view_file = __DIR__ . '/views/' . $view . '.php';
    if (!is_file($view_file)) {
        http_response_code(500);
        echo 'View not found: ' . htmlspecialchars($view);
        echo 'Resolved path: ' . htmlspecialchars($view_file);
        return;
    }

    // Layout will use $view_file, $db, $config, etc.
    require __DIR__ . '/views/layout.php';
}

function flash_take(): ?array {
    if (empty($_SESSION['flash'])) {
        return null;
    }

    $data = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $data;
}

function http_json(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}
