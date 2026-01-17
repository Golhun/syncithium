<?php
declare(strict_types=1);

function init_session(array $config): void {
  $name = $config['app']['session_name'] ?? 'syncithium_session';

  ini_set('session.use_strict_mode', '1');
  ini_set('session.use_only_cookies', '1');

  $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  session_name($name);
  session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);

  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
}

function current_user(PDO $db): ?array {
  $uid = $_SESSION['uid'] ?? null;
  if (!$uid) return null;

  $stmt = $db->prepare("SELECT id, email, role, must_change_password, disabled_at FROM users WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $uid]);
  $u = $stmt->fetch();
  if (!$u) return null;

  // If user was disabled mid-session, treat as logged out
  if (!empty($u['disabled_at'])) return null;

  return $u;
}

function require_login(PDO $db): array {
  $u = current_user($db);
  if (!$u) {
    flash_set('error', 'Please sign in to continue.');
    redirect('/public/index.php?r=login');
  }
  return $u;
}

function require_admin(PDO $db): array {
  $u = require_login($db);
  if (($u['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
  }
  return $u;
}


function attempt_login(PDO $db, string $email, string $password, array $config): bool {
  $email = strtolower(trim($email));

  $stmt = $db->prepare("
    SELECT id, email, password_hash, role, must_change_password, disabled_at,
           failed_attempts, last_failed_at, lockout_until
    FROM users
    WHERE email = :email
    LIMIT 1
  ");
  $stmt->execute([':email' => $email]);
  $u = $stmt->fetch();
  if (!$u) return false;

  // Do not leak disabled or lockout details to the UI
  if (!empty($u['disabled_at'])) return false;

  if (is_locked_out($u)) return false;

  if (!password_verify($password, (string)$u['password_hash'])) {
    register_failed_login(
      $db,
      (int)$u['id'],
      $u['last_failed_at'] ? (string)$u['last_failed_at'] : null,
      (int)($u['failed_attempts'] ?? 0),
      $config
    );
    return false;
  }

  // Successful login: reset lockout counters
  clear_failed_login_state($db, (int)$u['id']);

  session_regenerate_id(true);
  $_SESSION['uid'] = (int)$u['id'];

  return true;
}

function logout(): void {
  $_SESSION = [];
  if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
  }
}


function is_locked_out(array $u): bool {
  if (empty($u['lockout_until'])) return false;
  $until = strtotime((string)$u['lockout_until']);
  return $until !== false && $until > time();
}

function register_failed_login(PDO $db, int $userId, ?string $lastFailedAt, int $failedAttempts, array $config): void {
  $maxAttempts = (int)($config['security']['login_max_attempts'] ?? 5);
  $windowMin = (int)($config['security']['login_window_minutes'] ?? 15);
  $lockMin = (int)($config['security']['login_lock_minutes'] ?? 15);

  $now = time();
  $lastTs = $lastFailedAt ? strtotime($lastFailedAt) : false;

  // Reset window if last failure was long ago or invalid
  if ($lastTs === false || ($now - $lastTs) > ($windowMin * 60)) {
    $failedAttempts = 0;
  }

  $failedAttempts++;

  $lockoutUntil = null;
  $newFailedAttempts = $failedAttempts;

  if ($failedAttempts >= $maxAttempts) {
    $lockoutUntil = date('Y-m-d H:i:s', $now + ($lockMin * 60));
    $newFailedAttempts = 0; // reset after lock to avoid permanent growth
  }

  $stmt = $db->prepare("
    UPDATE users
    SET failed_attempts = :fa,
        last_failed_at = NOW(),
        lockout_until = :lu
    WHERE id = :id
  ");
  $stmt->execute([
    ':fa' => $newFailedAttempts,
    ':lu' => $lockoutUntil,
    ':id' => $userId,
  ]);
}

function clear_failed_login_state(PDO $db, int $userId): void {
  $stmt = $db->prepare("
    UPDATE users
    SET failed_attempts = 0,
        last_failed_at = NULL,
        lockout_until = NULL
    WHERE id = :id
  ");
  $stmt->execute([':id' => $userId]);
}
