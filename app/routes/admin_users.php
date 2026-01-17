<?php
declare(strict_types=1);

return [

  // =========================
  // Admin: Users list
  // =========================
  'admin_users' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    if (is_post()) {
      csrf_verify();

      $action = (string)($_POST['action'] ?? '');
      $userId = (int)($_POST['user_id'] ?? 0);

      if ($userId <= 0) {
        flash_set('error', 'Invalid user.');
        redirect('/public/index.php?r=admin_users');
      }

      // Prevent disabling your own account
      if ($action === 'disable' && $userId === (int)$admin['id']) {
        flash_set('error', 'You cannot disable your own account.');
        redirect('/public/index.php?r=admin_users');
      }

      if ($action === 'disable') {
        $stmt = $db->prepare("UPDATE users SET disabled_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        audit_log_event($db, (int)$admin['id'], 'USER_DISABLE', 'users', $userId);

        flash_set('success', 'User disabled.');
      } elseif ($action === 'enable') {
        $stmt = $db->prepare("UPDATE users SET disabled_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        audit_log_event($db, (int)$admin['id'], 'USER_ENABLE', 'users', $userId);

        flash_set('success', 'User enabled.');
      } elseif ($action === 'reset_password') {
        // Generate temp password, force change on next login
        $temp = random_password(14);
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
          UPDATE users
          SET password_hash = :h, must_change_password = 1
          WHERE id = :id
        ");
        $stmt->execute([':h' => $hash, ':id' => $userId]);

        audit_log_event($db, (int)$admin['id'], 'USER_PASSWORD_RESET', 'users', $userId, [
          'must_change_password' => 1,
          'flow' => 'direct_admin_reset',
        ]);

        // One-time reveal page, not flash
        reveal_set([
          'label'        => 'Temporary password generated',
          'secret_label' => 'Temp password (share securely)',
          'secret'       => $temp,
          'meta'         => ['user_id' => $userId],
        ]);

        redirect('/public/index.php?r=admin_credential_reveal');
      } else {
        flash_set('error', 'Unknown action.');
      }

      redirect('/public/index.php?r=admin_users');
    }

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
      'q'     => $q,
    ]);
  },

  // =========================
  // Admin: Create user
  // =========================
  'admin_users_create' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    if (is_post()) {
      csrf_verify();

      $email = strtolower(trim((string)($_POST['email'] ?? '')));
      $role  = (string)($_POST['role'] ?? 'user');
      if (!in_array($role, ['user','admin'], true)) $role = 'user';

      if (!valid_email($email)) {
        flash_set('error', 'Enter a valid email.');
        redirect('/public/index.php?r=admin_users_create');
      }

      $temp = random_password(14);
      $hash = password_hash($temp, PASSWORD_DEFAULT);

      try {
        $stmt = $db->prepare("
          INSERT INTO users (email, password_hash, role, must_change_password)
          VALUES (:email, :hash, :role, 1)
        ");
        $stmt->execute([
          ':email' => $email,
          ':hash'  => $hash,
          ':role'  => $role,
        ]);

        $newId = (int)$db->lastInsertId();

        audit_log_event($db, (int)$admin['id'], 'USER_CREATE', 'users', $newId, [
          'email'  => $email,
          'role'   => $role,
          'source' => 'single',
        ]);

        // Show temp password in one-time reveal screen
        reveal_set([
          'label'        => 'User created successfully',
          'secret_label' => 'Temp password for ' . $email,
          'secret'       => $temp,
          'meta'         => ['user_id' => $newId],
        ]);

        redirect('/public/index.php?r=admin_credential_reveal');
      } catch (PDOException $e) {
        flash_set('error', 'Could not create user. Email may already exist.');
        redirect('/public/index.php?r=admin_users_create');
      }
    }

    render('admin/users_create', [
      'title' => 'Create user',
    ]);
  },

  // =========================
  // Admin: Bulk upload users
  // =========================
  'admin_users_bulk' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    $results = [];

    if (is_post()) {
      csrf_verify();

      $mode = (string)($_POST['mode'] ?? 'textarea');
      $role = (string)($_POST['role'] ?? 'user');
      if (!in_array($role, ['user','admin'], true)) $role = 'user';

      $emails = [];

      if ($mode === 'csv' && isset($_FILES['csv']) && is_uploaded_file($_FILES['csv']['tmp_name'])) {
        $content = file_get_contents($_FILES['csv']['tmp_name']) ?: '';
        $parts = preg_split('/[,\r\n]+/', $content) ?: [];
        foreach ($parts as $p) {
          $p = strtolower(trim($p));
          if ($p !== '') $emails[] = $p;
        }
      } else {
        $raw = (string)($_POST['emails'] ?? '');
        $parts = preg_split('/[\r\n,; ]+/', $raw) ?: [];
        foreach ($parts as $p) {
          $p = strtolower(trim($p));
          if ($p !== '') $emails[] = $p;
        }
      }

      $emails = array_values(array_unique($emails));

      if (count($emails) === 0) {
        flash_set('error', 'No emails found.');
        redirect('/public/index.php?r=admin_users_bulk');
      }

      foreach ($emails as $email) {
        if (!valid_email($email)) {
          $results[] = ['email' => $email, 'status' => 'invalid', 'temp' => ''];
          continue;
        }

        $temp = random_password(14);
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        try {
          $stmt = $db->prepare("
            INSERT INTO users (email, password_hash, role, must_change_password)
            VALUES (:email, :hash, :role, 1)
          ");
          $stmt->execute([
            ':email' => $email,
            ':hash'  => $hash,
            ':role'  => $role,
          ]);

          $newId = (int)$db->lastInsertId();

          audit_log_event($db, (int)$admin['id'], 'USER_CREATE', 'users', $newId, [
            'email'  => $email,
            'role'   => $role,
            'source' => 'bulk',
          ]);

          $results[] = ['email' => $email, 'status' => 'created', 'temp' => $temp];
        } catch (PDOException $e) {
          $results[] = ['email' => $email, 'status' => 'exists', 'temp' => ''];
        }
      }

      flash_set('success', 'Bulk processing complete. Temp passwords shown only on this page view.');
    }

    render('admin/users_bulk', [
      'title'   => 'Bulk upload users',
      'results' => $results,
    ]);
  },

  // =========================
  // Admin: one-time credential reveal
  // =========================
  'admin_credential_reveal' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    $reveal = reveal_take();

    render('admin/credential_reveal', [
      'title'  => 'One-time Credentials',
      'reveal' => $reveal,
    ]);
  },

  // Optional: download endpoint (currently disabled)
  'admin_credential_reveal_download' => function (PDO $db, array $config): void {
    $admin = require_admin($db);
    if (!is_post()) redirect('/public/index.php?r=admin_users');
    csrf_verify();

    flash_set('error', 'Download not enabled. Copy from the reveal screen.');
    redirect('/public/index.php?r=admin_users');
  },

];
