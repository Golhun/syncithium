<?php

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = require __DIR__ . '/../config.php';
        $db = $config['db'] ?? null;

        if (!$db || !isset($db['driver'])) {
            throw new RuntimeException("Database config missing. Please set ['db']['driver'] in config.php");
        }

        $driver = $db['driver'];

        if ($driver === 'sqlite') {
            $path = $db['sqlite_path'] ?? null;
            if (!$path) {
                throw new RuntimeException("sqlite_path not set in config.php");
            }

            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            self::$pdo = new PDO("sqlite:" . $path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Enforce foreign keys in SQLite
            self::$pdo->exec("PRAGMA foreign_keys = ON;");

            return self::$pdo;
        }

        if ($driver === 'mysql') {
            $host = $db['host'] ?? '127.0.0.1';
            $port = (int)($db['port'] ?? 3306);
            $name = $db['name'] ?? '';
            $user = $db['user'] ?? '';
            $pass = $db['pass'] ?? '';
            $charset = $db['charset'] ?? 'utf8mb4';

            if ($name === '' || $user === '') {
                throw new RuntimeException("MySQL config incomplete: set db.name and db.user in config.php");
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return self::$pdo;
        }

        throw new RuntimeException("Unsupported DB driver: {$driver}");
    }
}
