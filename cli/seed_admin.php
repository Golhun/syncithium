<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$email = $argv[1] ?? 'admin@syncithium.local';

$stmt = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
$stmt->execute([':email' => strtolower(trim($email))]);
$exists = $stmt->fetch();

if ($exists) {
  echo "Admin already exists for {$email}\n";
  exit(0);
}

$temp = random_password(16);
$hash = password_hash($temp, PASSWORD_DEFAULT);

$stmt = $db->prepare("INSERT INTO users (email, password_hash, role, must_change_password)
                      VALUES (:email, :hash, 'admin', 1)");
$stmt->execute([':email' => strtolower(trim($email)), ':hash' => $hash]);

echo "Seeded admin:\n";
echo "  Email: {$email}\n";
echo "  Temp password: {$temp}\n";
echo "User must change password at first login.\n";
