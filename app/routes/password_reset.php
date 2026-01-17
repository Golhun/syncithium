<?php
declare(strict_types=1);

return [

  // Credential reveal screen (one time)
  'admin_credential_reveal' => function(PDO $db, array $config): void {
    $admin = require_admin($db);
    $reveal = reveal_take();

    render('admin/credential_reveal', [
      'title' => 'One-time Credentials',
      'reveal' => $reveal,
    ]);
  },

  // Public: user requests reset first
  'forgot_password' => function(PDO $db, array $config): void {
    if (is_post()) {
      csrf_verify();

      $email = strtolower(trim((string)($_POST['email'] ?? '')));
      $note  = trim((string)($_POST['note'] ?? ''));

      $userId = null;
      if ($email !== '') {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
        $stmt->execute([':e' => $email]);
        $u = $stmt->fetch();
        if ($u) $userId = (int)$u['id'];
      }

      $stmt = $db->prepare("
        INSERT INTO password_reset_requests (user_id, email, note, status, request_ip, user_agent)
        VALUES (:uid, :email, :note, 'open', :ip, :ua)
      ");
      $stmt->execute([
        ':uid'   => $userId,
        ':email' => ($email === '' ? 'unknown' : $email),
        ':note'  => ($note === '' ? null : $note),
        ':ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua'    => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
      ]);

      flash_set('success', 'If an account exists for that email, your request has been recorded. Contact admin for the reset token.');
      redirect('/public/index.php?r=login');
    }

    render('auth/forgot_password', ['title' => 'Request Password Reset']);
  },

  // Admin: queue (open requests)
  'admin_reset_requests' => function(PDO $db, array $config): void {
    $admin = require_admin($db);

    $stmt = $db->prepare("
      SELECT r.*, u.email AS user_email
      FROM password_reset_requests r
      LEFT JOIN users u ON u.id = r.user_id
      WHERE r.status = 'open'
      ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll();

    render('admin/reset_requests', [
      'title' => 'Password Reset Requests',
      'requests' => $requests,
    ]);
  },

  'admin_reset_requests_action' => function(PDO $db, array $config): void {
    $admin = require_admin($db);
    if (!is_post()) redirect('/public/index.php?r=admin_reset_requests');
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');
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
      $stmt->execute([':aid' => (int)$admin['id'], ':id' => $requestId]);

      audit_log_event($db, (int)$admin['id'], 'PASSWORD_RESET_REQUEST_REJECT', 'password_reset_requests', $requestId);
      flash_set('success', 'Request rejected.');
      redirect('/public/index.php?r=admin_reset_requests');
    }

    if ($action === 'generate_token') {
      if (empty($req['user_id'])) {
        $stmt = $db->prepare("
          UPDATE password_reset_requests
          SET status = 'invalid', processed_at = NOW(), processed_by = :aid
          WHERE id = :id
        ");
        $stmt->execute([':aid' => (int)$admin['id'], ':id' => $requestId]);

        audit_log_event($db, (int)$admin['id'], 'PASSWORD_RESET_REQUEST_INVALID', 'password_reset_requests', $requestId);
        flash_set('error', 'Request email does not match an existing account.');
        redirect('/public/index.php?r=admin_reset_requests');
      }

      $userId = (int)$req['user_id'];

      $token = bin2hex(random_bytes(16));
      $tokenHash = hash('sha256', $token);

      $stmt = $db->prepare("
        INSERT INTO password_resets (user_id, token_hash, expires_at, used_at, created_at)
        VALUES (:uid, :th, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NULL, NOW())
      ");
      $stmt->execute([':uid' => $userId, ':th' => $tokenHash]);

      $stmt = $db->prepare("
        UPDATE password_reset_requests
        SET status = 'processed', processed_at = NOW(), processed_by = :aid
        WHERE id = :id
      ");
      $stmt->execute([':aid' => (int)$admin['id'], ':id' => $requestId]);

      audit_log_event($db, (int)$admin['id'], 'PASSWORD_RESET_TOKEN_GENERATE', 'users', $userId, [
        'request_id' => $requestId
      ]);

      reveal_set([
        'label' => 'Password reset token (share securely)',
        'secret_label' => 'Reset token',
        'secret' => $token,
      ]);

      redirect('/public/index.php?r=admin_credential_reveal');
    }

    flash_set('error', 'Unknown action.');
    redirect('/public/index.php?r=admin_reset_requests');
  },

  // Admin-only alias route, keep but enforce queue usage
  'password_reset_request' => function(PDO $db, array $config): void {
    require_admin($db);
    redirect('/public/index.php?r=admin_reset_requests');
  },

  // Public: user uses token to set new password
  'password_reset' => function(PDO $db, array $config): void {
    if (is_post()) {
      csrf_verify();

      $token = trim((string)($_POST['token'] ?? ''));
      $p1 = (string)($_POST['password'] ?? '');
      $p2 = (string)($_POST['password_confirm'] ?? '');

      $minLen = (int)($config['security']['password_min_len'] ?? 10);

      if ($token === '') {
        flash_set('error', 'Token is required.');
        redirect('/public/index.php?r=password_reset');
      }
      if (strlen($p1) < $minLen) {
        flash_set('error', "Password must be at least {$minLen} characters.");
        redirect('/public/index.php?r=password_reset');
      }
      if ($p1 !== $p2) {
        flash_set('error', 'Passwords do not match.');
        redirect('/public/index.php?r=password_reset');
      }

      $th = reset_token_hash($token, $config);

      $stmt = $db->prepare("
        SELECT pr.id AS pr_id, pr.user_id, pr.expires_at, pr.used_at, u.disabled_at
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = :th
        LIMIT 1
      ");
      $stmt->execute([':th' => $th]);
      $row = $stmt->fetch();

      if (
        !$row ||
        !empty($row['used_at']) ||
        strtotime((string)$row['expires_at']) <= time() ||
        !empty($row['disabled_at'])
      ) {
        flash_set('error', 'Token is invalid or expired.');
        redirect('/public/index.php?r=password_reset');
      }

      $hash = password_hash($p1, PASSWORD_DEFAULT);

      $stmt = $db->prepare("
        UPDATE users
        SET password_hash = :h,
            must_change_password = 0,
            failed_attempts = 0,
            last_failed_at = NULL,
            lockout_until = NULL
        WHERE id = :uid
      ");
      $stmt->execute([':h' => $hash, ':uid' => (int)$row['user_id']]);

      $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id");
      $stmt->execute([':id' => (int)$row['pr_id']]);

      audit_log_event($db, null, 'PASSWORD_RESET_TOKEN_USED', 'users', (int)$row['user_id']);

      flash_set('success', 'Password updated. You can sign in now.');
      redirect('/public/index.php?r=login');
    }

    render('auth/password_reset', ['title' => 'Reset password']);
  },

];
