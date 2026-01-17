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
      // Phase 1.1: basic lockout controls
    'login_max_attempts' => 5,        // lockout threshold
    'login_window_minutes' => 15,     // observation window
    'login_lock_minutes' => 15,       // lockout duration

      // Phase 2 reset tokens
    'reset_token_ttl_minutes' => 60,
    'reset_token_pepper' => 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET',
  ],
];
