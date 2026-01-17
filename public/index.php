<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$route = $_GET['r'] ?? 'login';

$routes = require __DIR__ . '/../app/routes/routes.php';

if (!isset($routes[$route]) || !is_callable($routes[$route])) {
  http_response_code(404);
  echo "Not Found";
  exit;
}

// Each handler: function(PDO $db, array $config): void
$routes[$route]($db, $config);
