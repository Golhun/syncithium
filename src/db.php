<?php

function db_connect(array $db): PDO {
    $driver = $db['driver'] ?? 'mysql';

    if ($driver === 'sqlite') {
        $path = $db['sqlite_path'] ?? (__DIR__ . '/../storage/syncithium.sqlite');
        $dsn = 'sqlite:' . $path;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    $host = $db['host'] ?? '127.0.0.1';
    $port = (int)($db['port'] ?? 3306);
    $dbname = $db['dbname'] ?? 'syncithium';
    $charset = $db['charset'] ?? 'utf8mb4';
    $username = $db['username'] ?? 'root';
    $password = $db['password'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
