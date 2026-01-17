<?php
declare(strict_types=1);

/**
 * Central route registry.
 * Every routes file MUST return: array<string, callable>
 */

$routes = [];

$load = function (string $file) use (&$routes): void {
  $path = __DIR__ . DIRECTORY_SEPARATOR . $file;

  if (!is_file($path)) {
    throw new RuntimeException("Routes file missing: {$file}");
  }

  $chunk = require $path;

  if (!is_array($chunk)) {
    $type = gettype($chunk);
    throw new RuntimeException(
      "Routes file '{$file}' must return an array, got {$type}. " .
      "Do not echo HTML in routes files, move HTML to app/views and return route closures."
    );
  }

  $routes = array_merge($routes, $chunk);
};

$load('auth.php');
$load('admin_users.php');
$load('taxonomy_admin.php');
$load('taxonomy_user.php');
$load('api_taxonomy.php');
$load('questions_admin.php');
$load('questions_user.php');

return $routes;
