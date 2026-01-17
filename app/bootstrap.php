<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

// Flash + reveal helpers
require_once __DIR__ . '/lib/flash.php';

// General helpers
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/helpers/auth_redirect.php';

init_session($config);
$db = db($config);
