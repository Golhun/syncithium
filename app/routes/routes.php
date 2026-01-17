<?php
declare(strict_types=1);

$routes = [];

/**
 * Load a route module that returns an array of [route_name => handler].
 */
$load = function (string $file) use (&$routes): void {
  $path = __DIR__ . '/' . $file;

  // If the module does not exist, skip instead of crashing.
  if (!is_file($path)) {
    return;
  }

  $map = require $path;

  if (!is_array($map)) {
    throw new RuntimeException("Routes file must return an array: {$file}");
  }

  $routes = array_merge($routes, $map);
};

// Auth + password reset flows
$load('auth.php');

// Admin user management (create, bulk, reset, reset-requests, credential reveal)
$load('admin_users.php');

// Taxonomy admin and user selector / APIs
$load('taxonomy_admin.php');
$load('taxonomy_user.php');

// Question bank admin (list, edit, import)
$load('questions_admin.php');

return $routes;
