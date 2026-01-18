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

    // =========================
  // Admin: password reset requests list
  // =========================
  'admin_reset_requests' => function (PDO $db, array $config): void {
      $admin = require_admin($db);

      $stmt = $db->prepare("
        SELECT r.*, u.email AS user_email
        FROM password_reset_requests r
        LEFT JOIN users u ON u.id = r.user_id
        WHERE r.status = 'open'
        ORDER BY r.created_at DESC
      ");
      $stmt->execute();
      $requests = $stmt->fetchAll() ?: [];

      render('admin/reset_requests', [
          'title'    => 'Password Reset Requests',
          'requests' => $requests,
      ]);
  },

  // =========================
  // Admin: password reset request actions
  // =========================
  'admin_reset_requests_action' => function (PDO $db, array $config): void {
      $admin = require_admin($db);
      if (!is_post()) {
          redirect('/public/index.php?r=admin_reset_requests');
      }
      csrf_verify();

      $action    = (string)($_POST['action'] ?? '');
      $requestId = (int)($_POST['request_id'] ?? 0);

      $stmt = $db->prepare("SELECT * FROM password_reset_requests WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $requestId]);
      $req = $stmt->fetch();

      if (!$req || ($req['status'] ?? '') !== 'open') {
          flash_set('error', 'Request not found or already processed.');
          redirect('/public/index.php?r=admin_reset_requests');
      }

      if ($action === 'reject') {
          $stmt = $db->prepare("
            UPDATE password_reset_requests
            SET status = 'rejected', processed_at = NOW(), processed_by = :aid
            WHERE id = :id
          ");
          $stmt->execute([
              ':aid' => (int)$admin['id'],
              ':id'  => $requestId,
          ]);

          audit_log_event($db, (int)$admin['id'], 'PASSWORD_RESET_REQUEST_REJECT', 'password_reset_requests', $requestId);
          flash_set('success', 'Request rejected.');
          redirect('/public/index.php?r=admin_reset_requests');
      }

      if ($action === 'generate_token') {
          if (empty($req['user_id'])) {
              // Email did not map to a user; mark invalid to avoid orphaned tokens
              $stmt = $db->prepare("
                UPDATE password_reset_requests
                SET status = 'invalid', processed_at = NOW(), processed_by = :aid
                WHERE id = :id
              ");
              $stmt->execute([
                  ':aid' => (int)$admin['id'],
                  ':id'  => $requestId,
              ]);

              audit_log_event($db, (int)$admin['id'], 'PASSWORD_RESET_REQUEST_INVALID', 'password_reset_requests', $requestId);
              flash_set('error', 'Request email does not match an existing account.');
              redirect('/public/index.php?r=admin_reset_requests');
          }

          $userId = (int)$req['user_id'];

          // Generate secure token, store hash only
          $token = bin2hex(random_bytes(16)); // 32-char hex
          // Keep consistent with password_reset verifier
          if (function_exists('reset_token_hash')) {
              $tokenHash = reset_token_hash($token, $config);
          } else {
              $tokenHash = hash('sha256', $token);
          }

          $stmt = $db->prepare("
            INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
            VALUES (:uid, :th, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
          ");
          $stmt->execute([
              ':uid' => $userId,
              ':th'  => $tokenHash,
          ]);

          $stmt = $db->prepare("
            UPDATE password_reset_requests
            SET status = 'processed', processed_at = NOW(), processed_by = :aid
            WHERE id = :id
          ");
          $stmt->execute([
              ':aid' => (int)$admin['id'],
              ':id'  => $requestId,
          ]);

          audit_log_event(
              $db,
              (int)$admin['id'],
              'PASSWORD_RESET_TOKEN_GENERATE',
              'users',
              $userId,
              ['request_id' => $requestId]
          );

          // Show token on one-time reveal screen
          reveal_set([
              'label'        => 'Password reset token (share securely)',
              'secret_label' => 'Reset token',
              'secret'       => $token,
              'meta'         => ['user_id' => $userId, 'request_id' => $requestId],
          ]);

          redirect('/public/index.php?r=admin_credential_reveal');
      }

      flash_set('error', 'Unknown action.');
      redirect('/public/index.php?r=admin_reset_requests');
  },

  'admin_question_reports' => function (PDO $db, array $config): void {
    $user = require_login($db);
    if (($user['role'] ?? 'user') !== 'admin') {
        flash_set('error', 'Access denied.');
        redirect('/public/index.php');
    }

    $status = (string)($_GET['status'] ?? 'open');
    if (!in_array($status, ['open','in_review','resolved','rejected','all'], true)) {
        $status = 'open';
    }

    if ($status === 'all') {
        $stmt = $db->query("
            SELECT r.*, u.email, q.question_text
            FROM question_reports r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN questions q ON q.id = r.question_id
            ORDER BY r.created_at DESC
            LIMIT 500
        ");
        $rows = $stmt->fetchAll() ?: [];
    } else {
        $stmt = $db->prepare("
            SELECT r.*, u.email, q.question_text
            FROM question_reports r
            JOIN users u ON u.id = r.user_id
            LEFT JOIN questions q ON q.id = r.question_id
            WHERE r.status = :s
            ORDER BY r.created_at DESC
            LIMIT 500
        ");
        $stmt->execute([':s' => $status]);
        $rows = $stmt->fetchAll() ?: [];
    }

    render('admin/question_reports', [
        'title' => 'Question Reports',
        'user' => $user,
        'status' => $status,
        'reports' => $rows,
    ]);
},

'admin_question_report_update' => function (PDO $db, array $config): void {
    $user = require_login($db);
    if (($user['role'] ?? 'user') !== 'admin') {
        flash_set('error', 'Access denied.');
        redirect('/public/index.php');
    }

    if (!is_post()) {
        redirect('/public/index.php?r=admin_question_reports');
    }

    csrf_verify();

    $id = (int)($_POST['id'] ?? 0);
    $status = (string)($_POST['status'] ?? 'in_review');
    $notes = trim((string)($_POST['admin_notes'] ?? ''));

    if ($id <= 0) {
        flash_set('error', 'Invalid report.');
        redirect('/public/index.php?r=admin_question_reports');
    }

    if (!in_array($status, ['open','in_review','resolved','rejected'], true)) {
        $status = 'in_review';
    }

    $resolvedAt = null;
    $resolvedBy = null;
    if (in_array($status, ['resolved','rejected'], true)) {
        $resolvedAt = date('Y-m-d H:i:s');
        $resolvedBy = (int)$user['id'];
    }

    $stmt = $db->prepare("
        UPDATE question_reports
        SET status = :s,
            admin_notes = :n,
            resolved_by = :rb,
            resolved_at = :ra,
            updated_at = NOW()
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':s'  => $status,
        ':n'  => ($notes !== '' ? $notes : null),
        ':rb' => $resolvedBy,
        ':ra' => $resolvedAt,
        ':id' => $id,
    ]);

    flash_set('success', 'Report updated.');
    redirect('/public/index.php?r=admin_question_reports');
},


];
