<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$pdo = db();

function table_exists(PDO $pdo, string $table): bool
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    try {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=:t LIMIT 1");
            $stmt->execute([':t' => $table]);
            return (bool)$stmt->fetchColumn();
        }

        // MySQL
        $stmt = $pdo->query("SELECT DATABASE()");
        $dbName = (string)$stmt->fetchColumn();
        if ($dbName === '') return false;

        $stmt = $pdo->prepare("
            SELECT TABLE_NAME
            FROM information_schema.tables
            WHERE table_schema = :db AND table_name = :t
            LIMIT 1
        ");
        $stmt->execute([':db' => $dbName, ':t' => $table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function table_columns(PDO $pdo, string $table): array
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $stmt = $pdo->query("PRAGMA table_info(" . $table . ")");
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[] = (string)$row['name'];
        }
        return $cols;
    }

    // MySQL
    $stmt = $pdo->query("SELECT DATABASE()");
    $dbName = (string)$stmt->fetchColumn();
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.columns
        WHERE table_schema = :db AND table_name = :t
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([':db' => $dbName, ':t' => $table]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function pick_first(array $cols, array $candidates): ?string
{
    $lowerMap = [];
    foreach ($cols as $c) {
        $lowerMap[strtolower($c)] = $c; // preserve original case
    }

    foreach ($candidates as $cand) {
        $k = strtolower($cand);
        if (isset($lowerMap[$k])) return $lowerMap[$k];
    }
    return null;
}

function now_string(): string
{
    // ISO-like, works well for sqlite and mysql datetime/varchar
    return date('Y-m-d H:i:s');
}

// -------------
// Safety checks
// -------------
if (!table_exists($pdo, 'users')) {
    echo "ERROR: users table not found.\n";
    echo "Run migrations first:\n";
    echo "  php bin/migrate.php up\n";
    exit(1);
}

$cols = table_columns($pdo, 'users');

$colName = pick_first($cols, ['name', 'full_name', 'fullname']);
$colEmail = pick_first($cols, ['email', 'username', 'user_email']);
$colPass = pick_first($cols, ['password_hash', 'password', 'pass_hash', 'password_digest']);
$colAdmin = pick_first($cols, ['is_admin', 'admin', 'role']);
$colCreated = pick_first($cols, ['created_at', 'created', 'createdOn', 'created_on']);

if (!$colEmail || !$colPass) {
    echo "ERROR: Could not find required columns on users table.\n";
    echo "Found columns: " . implode(', ', $cols) . "\n";
    echo "Need at least: email/username and password/password_hash.\n";
    exit(1);
}

// -------------
// Seed users
// -------------
$seedUsers = [
    [
        'name' => 'Admin One',
        'email' => 'admin@syncithium.test',
        'password' => 'Admin12345!',
        'is_admin' => true,
    ],
    [
        'name' => 'Student One',
        'email' => 'student1@syncithium.test',
        'password' => 'Student12345!',
        'is_admin' => false,
    ],
    [
        'name' => 'Student Two',
        'email' => 'student2@syncithium.test',
        'password' => 'Student12345!',
        'is_admin' => false,
    ],
];

$inserted = 0;
$updated = 0;

foreach ($seedUsers as $u) {
    $email = (string)$u['email'];
    $name = (string)$u['name'];
    $hash = password_hash((string)$u['password'], PASSWORD_DEFAULT);

    // Determine admin value strategy
    $adminVal = null;
    if ($colAdmin) {
        if (strtolower($colAdmin) === 'role') {
            $adminVal = $u['is_admin'] ? 'admin' : 'user';
        } else {
            $adminVal = $u['is_admin'] ? 1 : 0;
        }
    }

    // Check if exists
    $stmt = $pdo->prepare("SELECT 1 FROM users WHERE {$colEmail} = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $exists = (bool)$stmt->fetchColumn();

    if (!$exists) {
        // Build INSERT
        $fields = [];
        $params = [];

        if ($colName) {
            $fields[] = $colName;
            $params[":name"] = $name;
        }

        $fields[] = $colEmail;
        $params[":email"] = $email;

        $fields[] = $colPass;
        $params[":pass"] = $hash;

        if ($colAdmin) {
            $fields[] = $colAdmin;
            $params[":admin"] = $adminVal;
        }

        if ($colCreated) {
            $fields[] = $colCreated;
            $params[":created"] = now_string();
        }

        $sql = "INSERT INTO users (" . implode(", ", $fields) . ")
                VALUES (" . implode(", ", array_keys($params)) . ")";

        $pdo->prepare($sql)->execute($params);
        $inserted++;
        continue;
    }

    // UPDATE existing
    $sets = [];
    $params = [":email" => $email];

    if ($colName) {
        $sets[] = "{$colName} = :name";
        $params[":name"] = $name;
    }

    $sets[] = "{$colPass} = :pass";
    $params[":pass"] = $hash;

    if ($colAdmin) {
        $sets[] = "{$colAdmin} = :admin";
        $params[":admin"] = $adminVal;
    }

    $sql = "UPDATE users SET " . implode(", ", $sets) . " WHERE {$colEmail} = :email";
    $pdo->prepare($sql)->execute($params);
    $updated++;
}

echo "Seed complete.\n";
echo "Inserted: {$inserted}\n";
echo "Updated:  {$updated}\n\n";

echo "Login accounts:\n";
echo "  Admin:    admin@syncithium.test / Admin12345!\n";
echo "  Student1: student1@syncithium.test / Student12345!\n";
echo "  Student2: student2@syncithium.test / Student12345!\n";
