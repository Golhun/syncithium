<?php
/**
 * Syncithium configuration
 *
 * Steps:
 * 1) Copy this file to config.php
 * 2) Update DB credentials
 */

return [
    'app' => [
        'name' => 'Syncithium',
        'timezone' => 'Africa/Accra',
        // If you host under a subfolder, set base_url like: http://localhost/syncithium/public
        // Leave empty for relative links.
        'base_url' => '',
    ],

    'db' => [
        // Supported: mysql, sqlite
        'driver' => 'mysql',

        // MySQL/MariaDB settings
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'syncithium',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',

        // SQLite settings (if driver = sqlite)
        // 'sqlite_path' => __DIR__ . '/storage/syncithium.sqlite',
    ],

    'security' => [
        // Change this in production. Used for CSRF tokens and session hardening.
        'app_key' => 'change-me-to-a-long-random-string',
    ],
];
