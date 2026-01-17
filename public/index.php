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

      if (attempt_login($db, $email, $password)) {
        $u = current_user($db);
        if ($u && (int)$u['must_change_password'] === 1) {
          redirect('/public/index.php?r=force_password_change');
        }
        redirect('/public/index.php?r=admin_users');
      } else {
        flash_set('error', 'Invalid credentials.');
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
        flash_set('success', 'User disabled.');
      } elseif ($action === 'enable') {
        $stmt = $db->prepare("UPDATE users SET disabled_at = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        flash_set('success', 'User enabled.');
      } elseif ($action === 'reset_password') {
        $temp = random_password(14);
        $hash = password_hash($temp, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE users
                              SET password_hash = :h, must_change_password = 1
                              WHERE id = :id");
        $stmt->execute([':h' => $hash, ':id' => $userId]);

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

  default:
    http_response_code(404);
    echo "Not Found";
}
