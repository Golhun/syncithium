<?php
$title = 'Sign in';
$base = base_url($config);

$pdo = db_connect($config['db']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($password === '') $errors[] = 'Password is required.';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id, name, email, password_hash, is_admin FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            $errors[] = 'Invalid email or password.';
        } else {
            $_SESSION['user'] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'email' => (string)$row['email'],
                'is_admin' => (int)$row['is_admin'],
            ];
            flash_set('success', 'Signed in.');
            redirect($base . '/index.php');
        }
    }
}

ob_start();
?>
  <h1>Sign in</h1>
  <?php if ($errors): ?>
    <div class="alert error">
      <strong>Please fix the following:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= e($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card">
    <form method="post" action="<?= e($base) ?>/index.php?r=login">
      <?= csrf_field() ?>
      <label>Email</label>
      <input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required>

      <label>Password</label>
      <input name="password" type="password" required>

      <div style="margin-top:14px;display:flex;gap:10px;align-items:center;">
        <button class="btn" type="submit">Sign in</button>
        <a class="btn secondary" href="<?= e($base) ?>/index.php?r=register">Create an account</a>
      </div>
    </form>
  </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
