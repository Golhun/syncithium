<?php
require_once __DIR__ . '/../src/bootstrap.php';

$route = $_GET['r'] ?? 'home';

$allowed = [
  'home',
  'login', 'logout',
  'change_password',

  // Admin
  'admin_users',

  // Password reset (token-based, user-facing)
  'reset_password',

  'admin_import',
  'quiz_start', 'quiz_take', 'quiz_submit',
  'results', 'my_attempts',
];



if (!in_array($route, $allowed, true)) {
    $route = 'home';
}

require __DIR__ . '/../src/routes/' . $route . '.php';
