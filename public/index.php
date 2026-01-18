<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/helpers/icons.php';


require_once __DIR__ . '/../app/bootstrap.php';

// Load route map
$routes = array_merge(
    require __DIR__ . '/../app/routes/auth.php',
    require __DIR__ . '/../app/routes/taxonomy_user.php',
    require __DIR__ . '/../app/routes/questions_user.php',
    require __DIR__ . '/../app/routes/admin_users.php',
    require __DIR__ . '/../app/routes/questions_admin.php',
    require __DIR__ . '/../app/routes/taxonomy_admin.php',

);

// Route name from query param
$route = $_GET['r'] ?? null;

// If no explicit route, choose a sane default based on session
if ($route === null || $route === '') {
    $u = current_user($db);
    if ($u) {
        // Logged in user should not see login by default
        $route = (($u['role'] ?? 'user') === 'admin') ? 'admin_users' : 'taxonomy_selector';
    } else {
        $route = 'login';
    }
}

// Dispatch
if (!isset($routes[$route]) || !is_callable($routes[$route])) {
    http_response_code(404);
    echo "Not Found";
    exit;
}

$handler = $routes[$route];
$handler($db, $config);
