<?php
// config/config.php

return [
    'app' => [
        'name' => 'Syncithium',
        'base_path' => '', // if hosted in subfolder, set like '/syncithium'
        'session_name' => 'syncithium_session',
    ],

    'db' => [
        'driver'  => 'mysql',
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'dbname'  => 'syncithium',
        'charset' => 'utf8mb4',
        'user'    => 'root',
        'pass'    => '',
        'options' => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ],
    ],

    // Seeded admin user (change these immediately after first login)
    'seed_admin' => [
        'email' => 'admin@syncithium.local',
        'password' => 'ChangeMeNow!234', // will be forced to change at first login
    ],
];
