<?php
declare(strict_types=1);

function e(mixed $v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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

function render(string $view, array $data = []): void
{
    // Expose these globals inside the function so layout.php can use them
    global $db, $config;

    // Make variables in $data available to the view file
    extract($data);

    // Build the full path to the view file
    $view_file = __DIR__ . '/views/' . $view . '.php';

    if (!is_file($view_file)) {
        http_response_code(500);
        echo "View not found: " . htmlspecialchars($view);
        exit;
    }

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

/**
 * Renders a self-contained, styled error page and terminates the script.
 *
 * @param int $code The HTTP status code (e.g., 404, 403).
 * @param string $title The main title for the error (e.g., 'Page Not Found').
 * @param string $message A technical or straightforward description of the error.
 * @param string $joke A user-friendly, humorous, and relevant message.
 * @return never
 */
function render_error_page(int $code, string $title, string $message, string $joke): void
{
    http_response_code($code);

    // Ensure helpers are available for the standalone error page
    require_once __DIR__ . '/helpers/icons.php';

    // Data for the view
    $data = compact('code', 'title', 'message', 'joke');
    extract($data);

    // Use DIRECTORY_SEPARATOR for better cross-platform compatibility.
    $view_file = __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . "{$code}.php";

    if (!file_exists($view_file)) {
        $view_file = __DIR__ . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'generic.php';
    }

    require $view_file;
    exit;
}
