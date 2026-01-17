<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$route = $_GET['r'] ?? 'login';

function render(string $view, array $data = []): void {
  extract($data);
  $view_file = __DIR__ . '/../app/views/' . $view . '.php';
  if (!is_file($view_file)) {
    http_response_code(500);
    exit('View not found');
  }
  require __DIR__ . '/../app/views/layout.php';
}

switch ($route) {

  // Auth
  case 'login': {
    if (is_post()) {
      csrf_verify();
      $email = (string)($_POST['email'] ?? '');
      $password = (string)($_POST['password'] ?? '');

      // UPDATED: pass $config for lockout policy
      if (attempt_login($db, $email, $password, $config)) {
        $u = current_user($db);
        if ($u && (int)$u['must_change_password'] === 1) {
          redirect('/public/index.php?r=force_password_change');
        }
        redirect('/public/index.php?r=admin_users');
      } else {
        // Avoid leaking whether account exists/locked/disabled
        flash_set('error', 'Sign-in failed. Please try again.');
      }
    }
    render('auth/login', ['title' => 'Sign in']);
    break;
  }

  case 'logout': {
    logout();
    flash_set('success', 'Signed out.');
    redirect('/public/index.php?r=login');
  }

  case 'force_password_change': {
    $u = require_login($db);

    if ((int)$u['must_change_password'] !== 1) {
      redirect('/public/index.php?r=admin_users');
    }

    if (is_post()) {
      csrf_verify();
      $p1 = (string)($_POST['password'] ?? '');
      $p2 = (string)($_POST['password_confirm'] ?? '');

      $minLen = (int)($config['security']['password_min_len'] ?? 10);

      if (strlen($p1) < $minLen) {
        flash_set('error', "Password must be at least {$minLen} characters.");
      } elseif ($p1 !== $p2) {
        flash_set('error', 'Passwords do not match.');
      } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id");
        $stmt->execute([':h' => $hash, ':id' => (int)$u['id']]);

        // AUDIT: user completed forced password change (self)
        audit_log_event($db, (int)$u['id'], 'USER_PASSWORD_CHANGE', 'users', (int)$u['id'], [
          'flow' => 'forced_first_login'
        ]);

        flash_set('success', 'Password updated. Welcome.');
        redirect('/public/index.php?r=admin_users');
      }
    }

    render('auth/force_password_change', ['title' => 'Set new password', 'user' => $u]);
    break;
  }

  // Admin
  case 'admin_users': {
    $admin = require_admin($db);

    // Handle actions
    if (is_post()) {
      csrf_verify();
      $action = (string)($_POST['action'] ?? '');
      $userId = (int)($_POST['user_id'] ?? 0);

      if ($userId <= 0) {
        flash_set('error', 'Invalid user.');
        redirect('/public/index.php?r=admin_users');
      }

      // Do not let admin disable themselves accidentally
      if ($action === 'disable' && $userId === (int)$admin['id']) {
        flash_set('error', 'You cannot disable your own account.');
        redirect('/public/index.php?r=admin_users');
      }

      if ($action === 'disable') {
        $stmt = $db->prepare("UPDATE users SET disabled_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        // AUDIT
        audit_log_event($db, (int)$admin['id'], 'USER_DISABLE', 'users', $userId);

        flash_set('success', 'User disabled.');
      } elseif ($action === 'enable') {
        $stmt = $db->prepare("UPDATE users SET disabled_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        // AUDIT
        audit_log_event($db, (int)$admin['id'], 'USER_ENABLE', 'users', $userId);

        flash_set('success', 'User enabled.');
      } elseif ($action === 'reset_password') {
        $temp = random_password(14);
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE users
                              SET password_hash = :h, must_change_password = 1
                              WHERE id = :id");
        $stmt->execute([':h' => $hash, ':id' => $userId]);

        // AUDIT (never log temp password)
        audit_log_event($db, (int)$admin['id'], 'USER_PASSWORD_RESET', 'users', $userId, [
          'must_change_password' => 1
        ]);

        // Store one-time temp password in flash (shown once)
        flash_set('success', "Temp password: {$temp} (share securely, user must change on login)");
      } else {
        flash_set('error', 'Unknown action.');
      }

      redirect('/public/index.php?r=admin_users');
    }

    $q = trim((string)($_GET['q'] ?? ''));
    if ($q !== '') {
      $stmt = $db->prepare("SELECT id, email, role, must_change_password, created_at, disabled_at
                            FROM users
                            WHERE email LIKE :q
                            ORDER BY created_at DESC
                            LIMIT 200");
      $stmt->execute([':q' => "%{$q}%"]);
    } else {
      $stmt = $db->prepare("SELECT id, email, role, must_change_password, created_at, disabled_at
                            FROM users
                            ORDER BY created_at DESC
                            LIMIT 200");
      $stmt->execute();
    }
    $users = $stmt->fetchAll();

    render('admin/users_index', [
      'title' => 'User Management',
      'admin' => $admin,
      'users' => $users,
      'q' => $q,
    ]);
    break;
  }

  case 'admin_users_create': {
    $admin = require_admin($db);

    $created = null;

    if (is_post()) {
      csrf_verify();
      $email = strtolower(trim((string)($_POST['email'] ?? '')));
      $role = (string)($_POST['role'] ?? 'user');
      if (!in_array($role, ['user','admin'], true)) $role = 'user';

      if (!valid_email($email)) {
        flash_set('error', 'Enter a valid email.');
      } else {
        $temp = random_password(14);
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        try {
          $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, must_change_password)
                                VALUES (:email, :hash, :role, 1)");
          $stmt->execute([':email' => $email, ':hash' => $hash, ':role' => $role]);

          $newId = (int)$db->lastInsertId();

          // AUDIT
          audit_log_event($db, (int)$admin['id'], 'USER_CREATE', 'users', $newId, [
            'email' => $email,
            'role' => $role,
            'source' => 'single'
          ]);

          $created = ['email' => $email, 'role' => $role, 'temp' => $temp];
          flash_set('success', 'User created. Temp password displayed below once.');
        } catch (PDOException $e) {
          // likely duplicate email
          flash_set('error', 'Could not create user. Email may already exist.');
        }
      }
    }

    render('admin/users_create', [
      'title' => 'Create user',
      'created' => $created,
    ]);
    break;
  }

  case 'admin_users_bulk': {
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
        // split on commas/newlines, accept simple csv
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
          $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, must_change_password)
                                VALUES (:email, :hash, :role, 1)");
          $stmt->execute([':email' => $email, ':hash' => $hash, ':role' => $role]);

          $newId = (int)$db->lastInsertId();

          // AUDIT
          audit_log_event($db, (int)$admin['id'], 'USER_CREATE', 'users', $newId, [
            'email' => $email,
            'role' => $role,
            'source' => 'bulk'
          ]);

          $results[] = ['email' => $email, 'status' => 'created', 'temp' => $temp];
        } catch (PDOException $e) {
          $results[] = ['email' => $email, 'status' => 'exists', 'temp' => ''];
        }
      }

      flash_set('success', 'Bulk processing complete. Temp passwords shown only on this page view.');
    }

    render('admin/users_bulk', [
      'title' => 'Bulk upload users',
      'results' => $results,
    ]);
    break;
  }

  case 'password_reset_request': {
  $admin = require_admin($db);

  $generated = null; // show token once
  $users = [];

  // simple search to pick a user
  $q = trim((string)($_GET['q'] ?? ''));
  if ($q !== '') {
    $stmt = $db->prepare("SELECT id, email, role, disabled_at FROM users WHERE email LIKE :q ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([':q' => "%{$q}%"]);
    $users = $stmt->fetchAll();
  }

  if (is_post()) {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'generate') {
      $userId = (int)($_POST['user_id'] ?? 0);
      if ($userId <= 0) {
        flash_set('error', 'Select a valid user.');
        redirect('/public/index.php?r=password_reset_request');
      }

      // refuse if target user is disabled
      $stmt = $db->prepare("SELECT id, email, disabled_at FROM users WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $userId]);
      $target = $stmt->fetch();
      if (!$target || !empty($target['disabled_at'])) {
        flash_set('error', 'User not found or is disabled.');
        redirect('/public/index.php?r=password_reset_request');
      }

      // invalidate any existing active tokens for this user
      $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW()
                            WHERE user_id = :uid AND used_at IS NULL AND expires_at > NOW()");
      $stmt->execute([':uid' => $userId]);

      $ttlMin = (int)($config['security']['reset_token_ttl_minutes'] ?? 60);

      $token = make_reset_token();
      $tokenHash = reset_token_hash($token, $config);

      $stmt = $db->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at)
                            VALUES (:uid, :th, DATE_ADD(NOW(), INTERVAL :ttl MINUTE))");
      $stmt->execute([':uid' => $userId, ':th' => $tokenHash, ':ttl' => $ttlMin]);

      audit_log_event($db, (int)$admin['id'], 'PASSWORD_RESET_TOKEN_GENERATE', 'users', $userId, [
        'ttl_minutes' => $ttlMin
      ]);

      $generated = [
        'email' => (string)$target['email'],
        'token' => $token,
        'ttl_minutes' => $ttlMin,
      ];

      flash_set('success', 'Reset token generated. Copy it now, it will not be shown again.');
    }

    if ($action === 'admin_reset_on_behalf') {
      $token = trim((string)($_POST['token'] ?? ''));
      $p1 = (string)($_POST['password'] ?? '');
      $p2 = (string)($_POST['password_confirm'] ?? '');
      $forceChange = (int)($_POST['force_change'] ?? 1); // default yes

      $minLen = (int)($config['security']['password_min_len'] ?? 10);

      if ($token === '') {
        flash_set('error', 'Token is required.');
        redirect('/public/index.php?r=password_reset_request');
      }
      if (strlen($p1) < $minLen) {
        flash_set('error', "Password must be at least {$minLen} characters.");
        redirect('/public/index.php?r=password_reset_request');
      }
      if ($p1 !== $p2) {
        flash_set('error', 'Passwords do not match.');
        redirect('/public/index.php?r=password_reset_request');
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

      // generic failure, do not disclose details
      if (!$row || !empty($row['used_at']) || strtotime((string)$row['expires_at']) <= time() || !empty($row['disabled_at'])) {
        flash_set('error', 'Token is invalid or expired.');
        redirect('/public/index.php?r=password_reset_request');
      }

      $hash = password_hash($p1, PASSWORD_DEFAULT);
      $mustChange = ($forceChange === 1) ? 1 : 0;

      $stmt = $db->prepare("UPDATE users
                            SET password_hash = :h,
                                must_change_password = :mcp,
                                failed_attempts = 0,
                                last_failed_at = NULL,
                                lockout_until = NULL
                            WHERE id = :uid");
      $stmt->execute([':h' => $hash, ':mcp' => $mustChange, ':uid' => (int)$row['user_id']]);

      $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id");
      $stmt->execute([':id' => (int)$row['pr_id']]);

      audit_log_event($db, (int)$admin['id'], 'PASSWORD_RESET_ADMIN_ON_BEHALF', 'users', (int)$row['user_id'], [
        'force_change' => $mustChange
      ]);

      flash_set('success', 'Password reset completed using token.');
      redirect('/public/index.php?r=password_reset_request');
    }
  }

  render('admin/password_reset_request', [
    'title' => 'Password Reset Tokens',
    'admin' => $admin,
    'users' => $users,
    'q' => $q,
    'generated' => $generated,
  ]);
  break;
}

case 'password_reset': {
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

    // generic failure
    if (!$row || !empty($row['used_at']) || strtotime((string)$row['expires_at']) <= time() || !empty($row['disabled_at'])) {
      flash_set('error', 'Token is invalid or expired.');
      redirect('/public/index.php?r=password_reset');
    }

    $hash = password_hash($p1, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users
                          SET password_hash = :h,
                              must_change_password = 0,
                              failed_attempts = 0,
                              last_failed_at = NULL,
                              lockout_until = NULL
                          WHERE id = :uid");
    $stmt->execute([':h' => $hash, ':uid' => (int)$row['user_id']]);

    $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id");
    $stmt->execute([':id' => (int)$row['pr_id']]);

    audit_log_event($db, null, 'PASSWORD_RESET_TOKEN_USED', 'users', (int)$row['user_id']);

    flash_set('success', 'Password updated. You can sign in now.');
    redirect('/public/index.php?r=login');
  }

  render('auth/password_reset', ['title' => 'Reset password']);
  break;
}


  default:
    http_response_code(404);
    echo "Not Found";
}
