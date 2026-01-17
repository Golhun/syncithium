<?php
declare(strict_types=1);

$routes = [];

$routes = array_merge($routes, require __DIR__ . '/auth.php');
$routes = array_merge($routes, require __DIR__ . '/admin_users.php');
$routes = array_merge($routes, require __DIR__ . '/password_reset.php');
$routes = array_merge($routes, require __DIR__ . '/taxonomy_admin.php');
$routes = array_merge($routes, require __DIR__ . '/taxonomy_api.php');

return $routes;
