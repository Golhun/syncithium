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
