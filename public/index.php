<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

// Load route map
$routes = require __DIR__ . '/../app/routes/routes.php';

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
