<?php
declare(strict_types=1);

// Define a global constant for the public web root directory. This makes the app more portable.
define('PUBLIC_ROOT', __DIR__);

require_once __DIR__ . '/../app/bootstrap.php';

// Load route map
$routes = array_merge(
    require __DIR__ . '/../app/routes/auth.php',
    require __DIR__ . '/../app/routes/password_flows.php',
    require __DIR__ . '/../app/routes/taxonomy_user.php',
    require __DIR__ . '/../app/routes/questions_user.php',
    require __DIR__ . '/../app/routes/admin_users.php',
    require __DIR__ . '/../app/routes/questions_admin.php',
    require __DIR__ . '/../app/routes/taxonomy_admin.php',
    require __DIR__ . '/../app/routes/api_auth.php',

);

// Route name from query param
$route = $_GET['r'] ?? null;
$u = current_user($db); // Get user once at the top.

// If no explicit route, choose a sane default based on session
if ($route === null || $route === '') {
    if ($u) {
        // Logged in user should not see login by default
        $route = (($u['role'] ?? 'user') === 'admin') ? 'admin_users' : 'taxonomy_selector';
    } else {
        $route = 'login';
    }
}

// Centralized Auth Check for Admin Routes
$isAdminRoute = str_starts_with((string)$route, 'admin_');

if ($isAdminRoute) {
    if (!$u) {
        // Not logged in, trying to access admin page. Redirect to login.
        flash_set('error', 'You must be logged in to view this page.');
        redirect('/public/index.php?r=login');
    }

    if (($u['role'] ?? 'user') !== 'admin') {
        // Logged in, but not an admin. Show 403 Forbidden.
        render_error_page(
            403,
            'Access Denied',
            'You do not have permission to access this page.'
        );
    }
}

// Dispatch
if (!isset($routes[$route]) || !is_callable($routes[$route])) {
    render_error_page(
        404,
        'Page Not Found',
        'The page you are looking for does not exist or has been moved.'
    );
}

$handler = $routes[$route];
$handler($db, $config);
