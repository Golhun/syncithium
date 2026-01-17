<?php
declare(strict_types=1);

function db_connect(array $db): PDO
{
    // Allow config.php to provide db config either directly or nested
    // e.g. ['db' => [...]] or just [...]
    if (isset($db['db']) && is_array($db['db'])) {
        $db = $db['db'];
    }

    $driver = strtolower((string)($db['driver'] ?? $db['type'] ?? 'sqlite'));

    // ----------------
    // SQLite
    // ----------------
    if ($driver === 'sqlite') {
        $path =
            (string)($db['database']
            ?? $db['path']
            ?? $db['file']
            ?? (dirname(__DIR__) . '/storage/syncithium.sqlite'));

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON;');
        return $pdo;
    }

    // ----------------
    // MySQL
    // ----------------
    if ($driver === 'mysql') {
        // Support "dsn" if user provides it directly
        $dsn = $db['dsn'] ?? null;

        $host = (string)($db['host'] ?? $db['hostname'] ?? '127.0.0.1');
        $port = (int)($db['port'] ?? 3306);

        // Accept common synonyms
        $name = (string)($db['database'] ?? $db['dbname'] ?? $db['name'] ?? $db['db'] ?? '');
        $user = (string)($db['username'] ?? $db['user'] ?? $db['db_user'] ?? '');
        $pass = (string)($db['password'] ?? $db['pass'] ?? $db['db_pass'] ?? '');

        $charset = (string)($db['charset'] ?? 'utf8mb4');

        if (!$dsn) {
            if ($name === '' || $user === '') {
                // Provide a clear, actionable error
                $keys = implode(', ', array_keys($db));
                throw new InvalidArgumentException(
                    "MySQL config is missing database/username. Found keys: {$keys}. " .
                    "Expected keys like database|dbname and username|user, or provide a full dsn."
                );
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
        }

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $pdo;
    }

    throw new InvalidArgumentException("Unsupported db driver: {$driver}");
}
