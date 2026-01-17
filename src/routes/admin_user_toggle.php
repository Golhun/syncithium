<?php
declare(strict_types=1);

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

csrf_verify_or_abort();

$userId = (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    flash_set('error', 'Invalid user.');
    redirect(url_for('admin_users'));
}

$pdo = db();

$stmt = $pdo->prepare("SELECT id, disabled_at FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    flash_set('error', 'User not found.');
    redirect(url_for('admin_users'));
}

if ((int)$userId === (int)(current_user()['id'] ?? 0)) {
    flash_set('error', 'You cannot disable your own account.');
    redirect(url_for('admin_users'));
}

if (!empty($u['disabled_at'])) {
    $pdo->prepare("UPDATE users SET disabled_at = NULL, updated_at = :u WHERE id = :id")
        ->execute([':u' => date('Y-m-d H:i:s'), ':id' => $userId]);
    flash_set('success', 'User enabled.');
} else {
    $pdo->prepare("UPDATE users SET disabled_at = :d, updated_at = :u WHERE id = :id")
        ->execute([':d' => date('Y-m-d H:i:s'), ':u' => date('Y-m-d H:i:s'), ':id' => $userId]);
    flash_set('success', 'User disabled.');
}

redirect(url_for('admin_users'));
