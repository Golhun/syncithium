<?php
declare(strict_types=1);

function render(string $view, array $data = []): void {
  extract($data);
  $view_file = __DIR__ . '/../views/' . $view . '.php';

  if (!is_file($view_file)) {
    http_response_code(500);
    exit('View not found');
  }

  require __DIR__ . '/../views/layout.php';
}

/**
 * Role-aware redirect helper (admin -> admin_users, user -> taxonomy_selector)
 */
function redirect_after_auth(array $user): void {
  if (($user['role'] ?? 'user') === 'admin') {
    redirect('/public/index.php?r=admin_users');
  }
  redirect('/public/index.php?r=taxonomy_selector');
}
