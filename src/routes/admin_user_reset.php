<?php
declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

csrf_verify_or_abort();

function random_password(int $len = 12): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#%*!?';
    $out = '';
    for ($i=0; $i<$len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
    return $out;
}

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    flash_set('error', 'Invalid user.');
    redirect(url_for('admin_users'));
}

$pdo = db();

$stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    flash_set('error', 'User not found.');
    redirect(url_for('admin_users'));
}

$temp = random_password(12);
$hash = password_hash($temp, PASSWORD_DEFAULT);

$pdo->prepare("
    UPDATE users
    SET password_hash = :h,
        must_change_password = 1,
        updated_at = :u
    WHERE id = :id
")->execute([
    ':h' => $hash,
    ':u' => date('Y-m-d H:i:s'),
    ':id' => $userId,
]);

flash_set('success', 'Password reset. Temp password for ' . $u['email'] . ': ' . $temp);
redirect(url_for('admin_users'));
