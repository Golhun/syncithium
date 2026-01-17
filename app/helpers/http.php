<?php
declare(strict_types=1);

function redirect(string $to): void {
  header('Location: ' . $to);
  exit;
}

function render(string $view, array $data = []): void {
  extract($data, EXTR_SKIP);

  $view_file = __DIR__ . '/../views/' . $view . '.php';
  if (!is_file($view_file)) {
    http_response_code(500);
    exit('View not found: ' . htmlspecialchars($view));
  }

  // Layout expects $view_file variable
  require __DIR__ . '/../views/layout.php';
}
