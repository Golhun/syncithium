<?php
declare(strict_types=1);

require_login();
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid user.');
    redirect(url_for('admin_users'));
}

$stmt = db()->prepare("SELECT id, email FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    flash_set('error', 'User not found.');
    redirect(url_for('admin_users'));
}

// Create one-time token (valid 30 minutes)
$token = bin2hex(random_bytes(24));
$tokenHash = password_hash($token, PASSWORD_DEFAULT);
$expires = date('Y-m-d H:i:s', time() + 30 * 60);
$now = date('Y-m-d H:i:s');

$ins = db()->prepare("
    INSERT INTO password_resets (user_id, token_hash, expires_at, used_at, created_at)
    VALUES (:uid, :th, :exp, NULL, :now)
");
$ins->execute([
    ':uid' => (int)$user['id'],
    ':th' => $tokenHash,
    ':exp' => $expires,
    ':now' => $now,
]);

$link = url_for('reset_password', ['t' => $token]);

flash_set('success', 'Reset link generated for ' . (string)$user['email'] . ': ' . $link);
redirect(url_for('admin_users'));
