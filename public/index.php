<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../app/bootstrap.php';

// Load route map
$routes = array_merge(
    require __DIR__ . '/../app/routes/auth.php',
    require __DIR__ . '/../app/routes/taxonomy_user.php',
    require __DIR__ . '/../app/routes/questions_user.php'
);


// Route name from query param
$route = $_GET['r'] ?? 'login';

// Dispatch
if (!isset($routes[$route]) || !is_callable($routes[$route])) {
  http_response_code(404);
  echo "Not Found";
  exit;
}

$handler = $routes[$route];
$handler($db, $config);
