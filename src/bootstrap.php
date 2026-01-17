<?php
declare(strict_types=1);

$root = dirname(__DIR__);

// 1) Load config
$config_return = require $root . '/config.php';
if (is_array($config_return)) {
    $config = $config_return;
}
if (!isset($config) || !is_array($config)) {
    throw new RuntimeException('config.php must return an array or define $config as an array.');
}

// expose config globally (helpers need it)
$GLOBALS['config'] = $config;

// 2) Load helpers (base_url etc.)
require_once __DIR__ . '/helpers.php';

// 3) Load DB helper
require_once __DIR__ . '/db.php';

// 4) Connect
$db_cfg = $config['db'] ?? $config;
$pdo = db_connect($db_cfg);

$GLOBALS['pdo'] = $pdo;

function db(): PDO
{
    return $GLOBALS['pdo'];
}
