<?php
require_once __DIR__ . '/../src/bootstrap.php';

$route = $_GET['r'] ?? 'home';

$allowed = [
    'home',
    'login', 'logout',
    'change_password',
    'reset_password',

    // Admin
    'admin_users',
    'admin_user_create',
    'admin_user_bulk_upload',
    'admin_generate_reset',
    'admin_reset_password',
    'admin_toggle_user',
    'admin_users_bulk',
    'admin_user_reset',
    'admin_user_toggle',
    'reset_password',


    // existing quiz routes...
    'admin_import',
    'quiz_start', 'quiz_take', 'quiz_submit',
    'results', 'my_attempts',
];




if (!in_array($route, $allowed, true)) {
    $route = 'home';
}

require __DIR__ . '/../src/routes/' . $route . '.php';
