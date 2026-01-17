<?php
// src/bootstrap.php

$config = require __DIR__ . '/../config/config.php';

// Sessions
session_name($config['app']['session_name']);
session_start();

// Basic security headers (lightweight, safe defaults)
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// PDO
$db = $config['db'];
$dsn = sprintf(
    '%s:host=%s;port=%d;dbname=%s;charset=%s',
    $db['driver'],
    $db['host'],
    (int)$db['port'],
    $db['dbname'],
    $db['charset']
);

$pdo = new PDO($dsn, $db['user'], $db['pass'], $db['options']);

// Helpers
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/render.php';
