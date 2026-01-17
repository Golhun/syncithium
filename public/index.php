<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/http/helpers.php';

$routes = require __DIR__ . '/../app/routes/routes.php';

$route = (string)($_GET['r'] ?? 'login');

if (!isset($routes[$route]) || !is_callable($routes[$route])) {
  http_response_code(404);
  echo "Not Found";
  exit;
}

$routes[$route]($db, $config);
