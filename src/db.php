<?php
declare(strict_types=1);

function db_connect(array $db): PDO
{
    $driver = strtolower((string)($db['driver'] ?? 'sqlite'));

    if ($driver === 'sqlite') {
        $path = (string)($db['database'] ?? $db['path'] ?? (dirname(__DIR__) . '/storage/syncithium.sqlite'));
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Enforce foreign keys in SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');

        return $pdo;
    }

    if ($driver === 'mysql') {
        $host = (string)($db['host'] ?? '127.0.0.1');
        $port = (int)($db['port'] ?? 3306);
        $name = (string)($db['database'] ?? $db['dbname'] ?? '');
        $user = (string)($db['username'] ?? $db['user'] ?? '');
        $pass = (string)($db['password'] ?? $db['pass'] ?? '');
        $charset = (string)($db['charset'] ?? 'utf8mb4');

        if ($name === '' || $user === '') {
            throw new InvalidArgumentException('MySQL config requires database name and username.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $pdo;
    }

    throw new InvalidArgumentException("Unsupported db driver: {$driver}");
}
