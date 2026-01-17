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

/**
 * Role-aware redirect helper (admin -> admin_users, user -> taxonomy_selector)
 */
function redirect_after_auth(array $user): void {
  if (($user['role'] ?? 'user') === 'admin') {
    redirect('/public/index.php?r=admin_users');
  }
  redirect('/public/index.php?r=taxonomy_selector');
}

switch ($route) {

  // =========================
  // Auth
  // =========================
  case 'login': {
    if (is_post()) {
      csrf_verify();
      $email = (string)($_POST['email'] ?? '');
      $password = (string)($_POST['password'] ?? '');

      // pass $config for lockout policy
      if (attempt_login($db, $email, $password, $config)) {
        $u = current_user($db);

        if ($u && (int)$u['must_change_password'] === 1) {
          redirect('/public/index.php?r=force_password_change');
        }

        if ($u) {
          redirect_after_auth($u);
        }

        // extremely defensive fallback
        logout();
        flash_set('error', 'Sign-in failed. Please try again.');
        redirect('/public/index.php?r=login');
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
    break;
  }

  case 'force_password_change': {
    $u = require_login($db);

    if ((int)$u['must_change_password'] !== 1) {
      redirect_after_auth($u);
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
        $u2 = current_user($db);
        if ($u2) redirect_after_auth($u2);
        redirect('/public/index.php?r=login');
      }
    }

    render('auth/force_password_change', ['title' => 'Set new password', 'user' => $u]);
    break;
  }

  // =========================
  // Admin: Users
  // =========================
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

        audit_log_event($db, (int)$admin['id'], 'USER_DISABLE', 'users', $userId);

        flash_set('success', 'User disabled.');
      } elseif ($action === 'enable') {
        $stmt = $db->prepare("UPDATE users SET disabled_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        audit_log_event($db, (int)$admin['id'], 'USER_ENABLE', 'users', $userId);

        flash_set('success', 'User enabled.');
      } elseif ($action === 'reset_password') {
        $temp = random_password(14);
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE users
                              SET password_hash = :h, must_change_password = 1
                              WHERE id = :id");
        $stmt->execute([':h' => $hash, ':id' => $userId]);

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

          audit_log_event($db, (int)$admin['id'], 'USER_CREATE', 'users', $newId, [
            'email' => $email,
            'role' => $role,
            'source' => 'single'
          ]);

          $created = ['email' => $email, 'role' => $role, 'temp' => $temp];
          flash_set('success', 'User created. Temp password displayed below once.');
        } catch (PDOException $e) {
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

  // =========================
  // Phase 2: Password reset tokens
  // =========================
  case 'password_reset_request': {
    $admin = require_admin($db);

    $generated = null; // show token once
    $users = [];

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

        if (
          !$row ||
          !empty($row['used_at']) ||
          strtotime((string)$row['expires_at']) <= time() ||
          !empty($row['disabled_at'])
        ) {
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

  // =========================
  // Phase 3: Taxonomy Admin
  // =========================
  case 'admin_levels': {
    $admin = require_admin($db);

    $editId = (int)($_GET['edit_id'] ?? 0);
    $edit = null;
    if ($editId > 0) {
      $stmt = $db->prepare("SELECT * FROM levels WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $editId]);
      $edit = $stmt->fetch();
    }

    if (is_post()) {
      csrf_verify();
      $action = (string)($_POST['action'] ?? '');
      $code = trim((string)($_POST['code'] ?? ''));
      $name = trim((string)($_POST['name'] ?? ''));

      if ($action === 'create') {
        if ($code === '') {
          flash_set('error', 'Level code is required.');
        } else {
          try {
            $stmt = $db->prepare("INSERT INTO levels (code, name) VALUES (:code, :name)");
            $stmt->execute([':code' => $code, ':name' => ($name === '' ? null : $name)]);
            $newId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_LEVEL_CREATE', 'levels', $newId, ['code' => $code]);
            flash_set('success', 'Level created.');
          } catch (PDOException $e) {
            flash_set('error', 'Could not create level. Code may already exist.');
          }
        }
        redirect('/public/index.php?r=admin_levels');
      }

      if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || $code === '') {
          flash_set('error', 'Invalid update request.');
          redirect('/public/index.php?r=admin_levels');
        }
        try {
          $stmt = $db->prepare("UPDATE levels SET code = :code, name = :name WHERE id = :id");
          $stmt->execute([':code' => $code, ':name' => ($name === '' ? null : $name), ':id' => $id]);
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_LEVEL_UPDATE', 'levels', $id, ['code' => $code]);
          flash_set('success', 'Level updated.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not update level. Code may already exist.');
        }
        redirect('/public/index.php?r=admin_levels');
      }

      if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
          flash_set('error', 'Invalid delete request.');
          redirect('/public/index.php?r=admin_levels');
        }

        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM modules WHERE level_id = :id");
        $stmt->execute([':id' => $id]);
        $c = (int)($stmt->fetch()['c'] ?? 0);

        if ($c > 0) {
          flash_set('error', 'Cannot delete. This level has modules.');
        } else {
          $stmt = $db->prepare("DELETE FROM levels WHERE id = :id");
          $stmt->execute([':id' => $id]);
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_LEVEL_DELETE', 'levels', $id);
          flash_set('success', 'Level deleted.');
        }
        redirect('/public/index.php?r=admin_levels');
      }
    }

    $stmt = $db->prepare("SELECT * FROM levels ORDER BY CAST(code AS UNSIGNED), code");
    $stmt->execute();
    $levels = $stmt->fetchAll();

    render('admin/levels', [
      'title' => 'Taxonomy: Levels',
      'levels' => $levels,
      'edit' => $edit
    ]);
    break;
  }

  case 'admin_modules': {
    $admin = require_admin($db);

    $stmt = $db->prepare("SELECT * FROM levels ORDER BY CAST(code AS UNSIGNED), code");
    $stmt->execute();
    $levels = $stmt->fetchAll();

    $editId = (int)($_GET['edit_id'] ?? 0);
    $edit = null;
    if ($editId > 0) {
      $stmt = $db->prepare("SELECT * FROM modules WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $editId]);
      $edit = $stmt->fetch();
    }

    if (is_post()) {
      csrf_verify();
      $action = (string)($_POST['action'] ?? '');
      $levelId = (int)($_POST['level_id'] ?? 0);
      $code = trim((string)($_POST['code'] ?? ''));
      $name = trim((string)($_POST['name'] ?? ''));

      if ($action === 'create') {
        if ($levelId <= 0 || $code === '') {
          flash_set('error', 'Level and module code are required.');
        } else {
          try {
            $stmt = $db->prepare("INSERT INTO modules (level_id, code, name) VALUES (:lid, :code, :name)");
            $stmt->execute([':lid' => $levelId, ':code' => $code, ':name' => ($name === '' ? null : $name)]);
            $newId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_MODULE_CREATE', 'modules', $newId, [
              'code' => $code,
              'level_id' => $levelId
            ]);
            flash_set('success', 'Module created.');
          } catch (PDOException $e) {
            flash_set('error', 'Could not create module. Code may already exist under this level.');
          }
        }
        redirect('/public/index.php?r=admin_modules');
      }

      if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || $levelId <= 0 || $code === '') {
          flash_set('error', 'Invalid update request.');
          redirect('/public/index.php?r=admin_modules');
        }
        try {
          $stmt = $db->prepare("UPDATE modules SET level_id = :lid, code = :code, name = :name WHERE id = :id");
          $stmt->execute([':lid' => $levelId, ':code' => $code, ':name' => ($name === '' ? null : $name), ':id' => $id]);
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_MODULE_UPDATE', 'modules', $id, [
            'code' => $code,
            'level_id' => $levelId
          ]);
          flash_set('success', 'Module updated.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not update module. Code may already exist under this level.');
        }
        redirect('/public/index.php?r=admin_modules');
      }

      if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
          flash_set('error', 'Invalid delete request.');
          redirect('/public/index.php?r=admin_modules');
        }
        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM subjects WHERE module_id = :id");
        $stmt->execute([':id' => $id]);
        $c = (int)($stmt->fetch()['c'] ?? 0);

        if ($c > 0) {
          flash_set('error', 'Cannot delete. This module has subjects.');
        } else {
          $stmt = $db->prepare("DELETE FROM modules WHERE id = :id");
          $stmt->execute([':id' => $id]);
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_MODULE_DELETE', 'modules', $id);
          flash_set('success', 'Module deleted.');
        }
        redirect('/public/index.php?r=admin_modules');
      }
    }

    $stmt = $db->prepare("
      SELECT m.*, l.code AS level_code
      FROM modules m
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll();

    render('admin/modules', [
      'title' => 'Taxonomy: Modules',
      'levels' => $levels,
      'modules' => $modules,
      'edit' => $edit,
    ]);
    break;
  }

  case 'admin_subjects': {
    $admin = require_admin($db);

    $stmt = $db->prepare("
      SELECT m.id, m.code, l.code AS level_code
      FROM modules m
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll();

    $editId = (int)($_GET['edit_id'] ?? 0);
    $edit = null;
    if ($editId > 0) {
      $stmt = $db->prepare("SELECT * FROM subjects WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $editId]);
      $edit = $stmt->fetch();
    }

    if (is_post()) {
      csrf_verify();
      $action = (string)($_POST['action'] ?? '');
      $moduleId = (int)($_POST['module_id'] ?? 0);
      $name = trim((string)($_POST['name'] ?? ''));

      if ($action === 'create') {
        if ($moduleId <= 0 || $name === '') {
          flash_set('error', 'Module and subject name are required.');
        } else {
          try {
            $stmt = $db->prepare("INSERT INTO subjects (module_id, name) VALUES (:mid, :name)");
            $stmt->execute([':mid' => $moduleId, ':name' => $name]);
            $newId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_SUBJECT_CREATE', 'subjects', $newId, [
              'name' => $name,
              'module_id' => $moduleId
            ]);
            flash_set('success', 'Subject created.');
          } catch (PDOException $e) {
            flash_set('error', 'Could not create subject. Name may already exist under this module.');
          }
        }
        redirect('/public/index.php?r=admin_subjects');
      }

      if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || $moduleId <= 0 || $name === '') {
          flash_set('error', 'Invalid update request.');
          redirect('/public/index.php?r=admin_subjects');
        }
        try {
          $stmt = $db->prepare("UPDATE subjects SET module_id = :mid, name = :name WHERE id = :id");
          $stmt->execute([':mid' => $moduleId, ':name' => $name, ':id' => $id]);
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_SUBJECT_UPDATE', 'subjects', $id, [
            'name' => $name,
            'module_id' => $moduleId
          ]);
          flash_set('success', 'Subject updated.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not update subject. Name may already exist under this module.');
        }
        redirect('/public/index.php?r=admin_subjects');
      }

      if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
          flash_set('error', 'Invalid delete request.');
          redirect('/public/index.php?r=admin_subjects');
        }
        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM topics WHERE subject_id = :id");
        $stmt->execute([':id' => $id]);
        $c = (int)($stmt->fetch()['c'] ?? 0);

        if ($c > 0) {
          flash_set('error', 'Cannot delete. This subject has topics.');
        } else {
          $stmt = $db->prepare("DELETE FROM subjects WHERE id = :id");
          $stmt->execute([':id' => $id]);
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_SUBJECT_DELETE', 'subjects', $id);
          flash_set('success', 'Subject deleted.');
        }
        redirect('/public/index.php?r=admin_subjects');
      }
    }

    $stmt = $db->prepare("
      SELECT s.*, m.code AS module_code, l.code AS level_code
      FROM subjects s
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code, s.name
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll();

    render('admin/subjects', [
      'title' => 'Taxonomy: Subjects',
      'modules' => $modules,
      'subjects' => $subjects,
      'edit' => $edit,
    ]);
    break;
  }

  case 'admin_topics': {
    $admin = require_admin($db);

    $stmt = $db->prepare("
      SELECT s.id, s.name AS subject_name, m.code AS module_code, l.code AS level_code
      FROM subjects s
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code, s.name
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll();

    $editId = (int)($_GET['edit_id'] ?? 0);
    $edit = null;
    if ($editId > 0) {
      $stmt = $db->prepare("SELECT * FROM topics WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $editId]);
      $edit = $stmt->fetch();
    }

    if (is_post()) {
      csrf_verify();
      $action = (string)($_POST['action'] ?? '');
      $subjectId = (int)($_POST['subject_id'] ?? 0);
      $name = trim((string)($_POST['name'] ?? ''));

      if ($action === 'create') {
        if ($subjectId <= 0 || $name === '') {
          flash_set('error', 'Subject and topic name are required.');
        } else {
          try {
            $stmt = $db->prepare("INSERT INTO topics (subject_id, name) VALUES (:sid, :name)");
            $stmt->execute([':sid' => $subjectId, ':name' => $name]);
            $newId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_TOPIC_CREATE', 'topics', $newId, [
              'name' => $name,
              'subject_id' => $subjectId
            ]);
            flash_set('success', 'Topic created.');
          } catch (PDOException $e) {
            flash_set('error', 'Could not create topic. Name may already exist under this subject.');
          }
        }
        redirect('/public/index.php?r=admin_topics');
      }

      if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || $subjectId <= 0 || $name === '') {
          flash_set('error', 'Invalid update request.');
          redirect('/public/index.php?r=admin_topics');
        }
        try {
          $stmt = $db->prepare("UPDATE topics SET subject_id = :sid, name = :name WHERE id = :id");
          $stmt->execute([':sid' => $subjectId, ':name' => $name, ':id' => $id]);
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_TOPIC_UPDATE', 'topics', $id, [
            'name' => $name,
            'subject_id' => $subjectId
          ]);
          flash_set('success', 'Topic updated.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not update topic. Name may already exist under this subject.');
        }
        redirect('/public/index.php?r=admin_topics');
      }

      if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
          flash_set('error', 'Invalid delete request.');
          redirect('/public/index.php?r=admin_topics');
        }

        $stmt = $db->prepare("DELETE FROM topics WHERE id = :id");
        $stmt->execute([':id' => $id]);
        audit_log_event($db, (int)$admin['id'], 'TAXONOMY_TOPIC_DELETE', 'topics', $id);

        flash_set('success', 'Topic deleted.');
        redirect('/public/index.php?r=admin_topics');
      }
    }

    $stmt = $db->prepare("
      SELECT t.*, s.name AS subject_name, m.code AS module_code, l.code AS level_code
      FROM topics t
      JOIN subjects s ON s.id = t.subject_id
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code, s.name, t.name
    ");
    $stmt->execute();
    $topics = $stmt->fetchAll();

    render('admin/topics', [
      'title' => 'Taxonomy: Topics',
      'subjects' => $subjects,
      'topics' => $topics,
      'edit' => $edit,
    ]);
    break;
  }

  case 'admin_taxonomy_import': {
    $admin = require_admin($db);

    $results = [];

    if (is_post()) {
      csrf_verify();

      if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        flash_set('error', 'Upload a CSV file.');
        redirect('/public/index.php?r=admin_taxonomy_import');
      }

      $content = file_get_contents($_FILES['csv']['tmp_name']) ?: '';
      $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];

      $header = [];
      if (count($lines) > 0) {
        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
      }

      $idx = [
        'level_code' => array_search('level_code', $header, true),
        'module_code' => array_search('module_code', $header, true),
        'subject_name' => array_search('subject_name', $header, true),
        'topic_name' => array_search('topic_name', $header, true),
      ];

      foreach ($idx as $k => $v) {
        if ($v === false) {
          flash_set('error', 'CSV header must include: level_code,module_code,subject_name,topic_name');
          redirect('/public/index.php?r=admin_taxonomy_import');
        }
      }

      $db->beginTransaction();
      try {
        foreach ($lines as $lineNo => $line) {
          if (trim($line) === '') continue;
          $row = str_getcsv($line);

          $levelCode = trim((string)($row[$idx['level_code']] ?? ''));
          $moduleCode = trim((string)($row[$idx['module_code']] ?? ''));
          $subjectName = trim((string)($row[$idx['subject_name']] ?? ''));
          $topicName = trim((string)($row[$idx['topic_name']] ?? ''));

          if ($levelCode === '' || $moduleCode === '' || $subjectName === '' || $topicName === '') {
            $results[] = ['line' => $lineNo + 2, 'status' => 'skipped', 'note' => 'Missing required columns'];
            continue;
          }

          // Level
          $stmt = $db->prepare("SELECT id FROM levels WHERE code = :c LIMIT 1");
          $stmt->execute([':c' => $levelCode]);
          $level = $stmt->fetch();
          if (!$level) {
            $stmt = $db->prepare("INSERT INTO levels (code) VALUES (:c)");
            $stmt->execute([':c' => $levelCode]);
            $levelId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_LEVEL_CREATE', 'levels', $levelId, [
              'code' => $levelCode,
              'source' => 'import'
            ]);
          } else {
            $levelId = (int)$level['id'];
          }

          // Module
          $stmt = $db->prepare("SELECT id FROM modules WHERE level_id = :lid AND code = :c LIMIT 1");
          $stmt->execute([':lid' => $levelId, ':c' => $moduleCode]);
          $module = $stmt->fetch();
          if (!$module) {
            $stmt = $db->prepare("INSERT INTO modules (level_id, code) VALUES (:lid, :c)");
            $stmt->execute([':lid' => $levelId, ':c' => $moduleCode]);
            $moduleId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_MODULE_CREATE', 'modules', $moduleId, [
              'code' => $moduleCode,
              'level_id' => $levelId,
              'source' => 'import'
            ]);
          } else {
            $moduleId = (int)$module['id'];
          }

          // Subject
          $stmt = $db->prepare("SELECT id FROM subjects WHERE module_id = :mid AND name = :n LIMIT 1");
          $stmt->execute([':mid' => $moduleId, ':n' => $subjectName]);
          $subject = $stmt->fetch();
          if (!$subject) {
            $stmt = $db->prepare("INSERT INTO subjects (module_id, name) VALUES (:mid, :n)");
            $stmt->execute([':mid' => $moduleId, ':n' => $subjectName]);
            $subjectId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_SUBJECT_CREATE', 'subjects', $subjectId, [
              'name' => $subjectName,
              'module_id' => $moduleId,
              'source' => 'import'
            ]);
          } else {
            $subjectId = (int)$subject['id'];
          }

          // Topic
          $stmt = $db->prepare("SELECT id FROM topics WHERE subject_id = :sid AND name = :n LIMIT 1");
          $stmt->execute([':sid' => $subjectId, ':n' => $topicName]);
          $topic = $stmt->fetch();
          if (!$topic) {
            $stmt = $db->prepare("INSERT INTO topics (subject_id, name) VALUES (:sid, :n)");
            $stmt->execute([':sid' => $subjectId, ':n' => $topicName]);
            $topicId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_TOPIC_CREATE', 'topics', $topicId, [
              'name' => $topicName,
              'subject_id' => $subjectId,
              'source' => 'import'
            ]);
            $results[] = ['line' => $lineNo + 2, 'status' => 'created', 'note' => 'OK'];
          } else {
            $results[] = ['line' => $lineNo + 2, 'status' => 'exists', 'note' => 'No change'];
          }
        }

        $db->commit();
        flash_set('success', 'Import complete.');
      } catch (Throwable $e) {
        $db->rollBack();
        flash_set('error', 'Import failed. Please validate your CSV and try again.');
      }
    }

    render('admin/taxonomy_import', [
      'title' => 'Taxonomy Import',
      'results' => $results,
    ]);
    break;
  }

  // =========================
  // Phase 3: User selector + JSON endpoints
  // =========================
  case 'taxonomy_selector': {
    $u = require_login($db);

    $stmt = $db->prepare("SELECT * FROM levels ORDER BY CAST(code AS UNSIGNED), code");
    $stmt->execute();
    $levels = $stmt->fetchAll();

    render('user/taxonomy_selector', [
      'title' => 'Choose Topics',
      'levels' => $levels,
      'user' => $u,
    ]);
    break;
  }

  case 'api_modules': {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $levelId = (int)($_GET['level_id'] ?? 0);
    if ($levelId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, code, name FROM modules WHERE level_id = :lid ORDER BY code");
    $stmt->execute([':lid' => $levelId]);

    $out = [];
    foreach ($stmt->fetchAll() as $m) {
      $label = $m['code'] . ($m['name'] ? (' , ' . $m['name']) : '');
      $out[] = ['id' => (int)$m['id'], 'label' => $label];
    }
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  }

  case 'api_subjects': {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $moduleId = (int)($_GET['module_id'] ?? 0);
    if ($moduleId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, name FROM subjects WHERE module_id = :mid ORDER BY name");
    $stmt->execute([':mid' => $moduleId]);

    $out = array_map(fn($s) => ['id' => (int)$s['id'], 'label' => $s['name']], $stmt->fetchAll());
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  }

  case 'api_topics': {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $subjectId = (int)($_GET['subject_id'] ?? 0);
    if ($subjectId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, name FROM topics WHERE subject_id = :sid ORDER BY name");
    $stmt->execute([':sid' => $subjectId]);

    $out = array_map(fn($t) => ['id' => (int)$t['id'], 'label' => $t['name']], $stmt->fetchAll());
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  }

  default:
    http_response_code(404);
    echo "Not Found";
}
