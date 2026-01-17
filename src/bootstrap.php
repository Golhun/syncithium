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

date_default_timezone_set(($config['app']['timezone'] ?? 'Africa/Accra'));

// Make config globally available (so helpers can read it safely)
$GLOBALS['config'] = $config;

// 2) Load DB helper (this defines db_connect())
require_once __DIR__ . '/db.php';

// 3) Connect and expose PDO
$db_cfg = $config['db'] ?? $config; // supports both config shapes
$pdo = db_connect($db_cfg);
$GLOBALS['pdo'] = $pdo;

function db(): PDO
{
    return $GLOBALS['pdo'];
}

// 4) App helpers (escaping, urls, csrf, flash, auth)
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/auth.php';
