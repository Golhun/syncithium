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

function attempt_login(PDO $db, string $email, string $password): bool {
  $stmt = $db->prepare("SELECT id, email, password_hash, role, must_change_password, disabled_at
                        FROM users WHERE email = :email LIMIT 1");
  $stmt->execute([':email' => strtolower(trim($email))]);
  $u = $stmt->fetch();
  if (!$u) return false;

  if (!empty($u['disabled_at'])) {
    // Treat disabled as invalid login, do not leak info
    return false;
  }

  // Verify password using PHP password API :contentReference[oaicite:5]{index=5}
  if (!password_verify($password, $u['password_hash'])) {
    return false;
  }

  // Prevent session fixation by regenerating session ID after login :contentReference[oaicite:6]{index=6}
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
