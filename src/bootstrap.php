<?php
declare(strict_types=1);

// Project root (…/syncithium)
$root = dirname(__DIR__);

// 1) Load config (supports either: return array OR $config = [...])
$config_return = require $root . '/config.php';
if (is_array($config_return)) {
    $config = $config_return;
}
if (!isset($config) || !is_array($config)) {
    throw new RuntimeException('config.php must return an array or define $config as an array.');
}

$GLOBALS['config'] = $config;
date_default_timezone_set(($config['app']['timezone'] ?? 'Africa/Accra'));

// 2) Load DB helper
require_once __DIR__ . '/db.php';

// 3) Connect and expose PDO
$db_cfg = $config['db'] ?? $config; // supports both config shapes
$pdo = db_connect($db_cfg);

// make PDO easy to access anywhere
$GLOBALS['pdo'] = $pdo;

function db(): PDO
{
    return $GLOBALS['pdo'];
}

// 4) Start session early (needed for current_user(), flash messages, etc.)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 5) Load general helpers + auth helpers
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
