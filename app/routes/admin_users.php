<?php
declare(strict_types=1);

return [

  'admin_users' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    // Actions
    if (is_post()) {
      csrf_verify();

      $action = (string)($_POST['action'] ?? '');
      $userId = (int)($_POST['user_id'] ?? 0);

      if ($userId <= 0) {
        flash_set('error', 'Invalid user.');
        redirect('/public/index.php?r=admin_users');
      }

      // Prevent self-disable
      if ($action === 'disable' && $userId === (int)$admin['id']) {
        flash_set('error', 'You cannot disable your own account.');
        redirect('/public/index.php?r=admin_users');
      }

      if ($action === 'disable') {
        $stmt = $db->prepare("UPDATE users SET disabled_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        audit_log_event($db, (int)$admin['id'], 'USER_DISABLE', 'users', $userId);
        flash_set('success', 'User disabled.');
        redirect('/public/index.php?r=admin_users');
      }

      if ($action === 'enable') {
        $stmt = $db->prepare("UPDATE users SET disabled_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        audit_log_event($db, (int)$admin['id'], 'USER_ENABLE', 'users', $userId);
        flash_set('success', 'User enabled.');
        redirect('/public/index.php?r=admin_users');
      }

      /**
       * IMPORTANT CHANGE:
       * Do NOT show temp passwords in Alertify flash.
       * Use your one-time reveal screen.
       */
      if ($action === 'reset_password') {
        $temp = random_password(14);
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
          UPDATE users
          SET password_hash = :h, must_change_password = 1
          WHERE id = :id
        ");
        $stmt->execute([':h' => $hash, ':id' => $userId]);

        audit_log_event($db, (int)$admin['id'], 'USER_PASSWORD_RESET', 'users', $userId, [
          'must_change_password' => 1
        ]);

        reveal_set([
          'label' => 'One-time temp password (share securely)',
          'secret_label' => 'Temp password',
          'secret' => $temp,
          'meta' => ['user_id' => $userId]
        ]);

        redirect('/public/index.php?r=admin_credential_reveal');
      }

      flash_set('error', 'Unknown action.');
      redirect('/public/index.php?r=admin_users');
    }

    // Listing
    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
      $stmt = $db->prepare("
        SELECT id, email, role, must_change_password, created_at, disabled_at
        FROM users
        WHERE email LIKE :q
        ORDER BY created_at DESC
        LIMIT 200
      ");
      $stmt->execute([':q' => "%{$q}%"]);
    } else {
      $stmt = $db->prepare("
        SELECT id, email, role, must_change_password, created_at, disabled_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 200
      ");
      $stmt->execute();
    }

    $users = $stmt->fetchAll() ?: [];

    render('admin/users_index', [
      'title' => 'User Management',
      'admin' => $admin,
      'users' => $users,
      'q' => $q,
    ]);
  },

  'admin_credential_reveal' => function (PDO $db, array $config): void {
    require_admin($db);
    $reveal = reveal_take();

    render('admin/credential_reveal', [
      'title' => 'One-time Credentials',
      'reveal' => $reveal,
    ]);
  },

];
