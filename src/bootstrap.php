<?php

$config = require __DIR__ . '/../config.php';

date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

// Basic session hardening
session_name('syncithium_session');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => false, // set true if using HTTPS
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

$db = db_connect($config['db']);

// Ensure first registered user becomes admin
// (Handled in registration route)

return [$config, $db];
