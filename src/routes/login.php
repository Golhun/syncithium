<?php
declare(strict_types=1);

$title = 'Login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        flash_set('error', 'Email and password are required.');
        redirect(url_for('login'));
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        flash_set('error', 'Invalid email or password.');
        redirect(url_for('login'));
    }

    // Block disabled accounts
    if (!empty($user['disabled_at'])) {
        flash_set('error', 'This account is disabled. Please contact the administrator.');
        redirect(url_for('login'));
    }

    // Support either password_hash (preferred) or legacy password
    $hash = (string)($user['password_hash'] ?? $user['password'] ?? '');
    if ($hash === '' || !password_verify($pass, $hash)) {
        flash_set('error', 'Invalid email or password.');
        redirect(url_for('login'));
    }

    // Login OK
    login_user((int)$user['id']);

    // First-login password change enforcement
    $mustChange = (int)($user['must_change_password'] ?? 0) === 1;

    if ($mustChange) {
        flash_set('success', 'Welcome. Please change your password to continue.');
        redirect(url_for('change_password'));
    }

    flash_set('success', 'Welcome back.');
    redirect(url_for('home'));
}

ob_start();
?>
  <h1>Sign in</h1>
  <div class="card" style="max-width:520px;">
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>

      <label>Email</label>
      <input type="email" name="email" required autocomplete="username">

      <div style="height:10px;"></div>

      <label>Password</label>
      <input type="password" name="password" required autocomplete="current-password">

      <div style="height:14px;"></div>

      <button class="btn" type="submit">Sign in</button>
      <a class="btn btn-secondary" href="<?= e(url_for('home')) ?>">Back</a>
    </form>
  </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
