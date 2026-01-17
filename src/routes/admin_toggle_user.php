<?php
declare(strict_types=1);

require_login();
require_admin();

$id = (int)($_GET['id'] ?? 0);
$action = (string)($_GET['action'] ?? '');

if ($id <= 0 || !in_array($action, ['disable','enable'], true)) {
    flash_set('error', 'Invalid action.');
    redirect(url_for('admin_users'));
}

$now = date('Y-m-d H:i:s');

if ($action === 'disable') {
    $stmt = db()->prepare("UPDATE users SET disabled_at = :now, updated_at = :now2 WHERE id = :id");
    $stmt->execute([':now' => $now, ':now2' => $now, ':id' => $id]);
    flash_set('success', 'User disabled.');
} else {
    $stmt = db()->prepare("UPDATE users SET disabled_at = NULL, updated_at = :now WHERE id = :id");
    $stmt->execute([':now' => $now, ':id' => $id]);
    flash_set('success', 'User enabled.');
}

redirect(url_for('admin_users'));
