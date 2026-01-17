<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$email = strtolower(trim((string)($argv[1] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Usage:\n";
    echo "  php bin/seed_admin.php admin@example.com\n";
    exit(1);
}

function random_password(int $len = 12): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#%*!?';
    $out = '';
    for ($i=0; $i<$len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
    }
    return $out;
}

$pdo = db();

$exists = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
$exists->execute([':e' => $email]);
if ($exists->fetchColumn()) {
    echo "User already exists: {$email}\n";
    exit(0);
}

$temp = random_password(12);
$hash = password_hash($temp, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (email, password_hash, is_admin, must_change_password, created_at)
    VALUES (:email, :hash, 1, 1, :created_at)
");
$stmt->execute([
    ':email' => $email,
    ':hash' => $hash,
    ':created_at' => date('Y-m-d H:i:s'),
]);

echo "Admin created.\n";
echo "Email: {$email}\n";
echo "Temp password: {$temp}\n";
echo "User must change password on first login.\n";
