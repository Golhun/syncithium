<?php
declare(strict_types=1);

return [
  'app' => [
    'name' => 'Syncithium',
    'base_url' => '', // optional, leave empty for relative paths
    'session_name' => 'syncithium_session',
  ],
  'db' => [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'syncithium',
    'username' => 'root',
    'password' => '', // set yours
    'charset' => 'utf8mb4',
  ],
  'security' => [
    'password_min_len' => 10,
  ],
];
