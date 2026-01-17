<?php
$title = 'Create account';
$base = base_url($config);

$pdo = db_connect($config['db']);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    if (!$errors) {
        // First user becomes admin
        $stmt = $pdo->query('SELECT COUNT(*) AS c FROM users');
        $count = (int)($stmt->fetch()['c'] ?? 0);
        $is_admin = ($count === 0) ? 1 : 0;

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, is_admin) VALUES (:n, :e, :p, :a)');
            $stmt->execute([':n' => $name, ':e' => $email, ':p' => $hash, ':a' => $is_admin]);

            // Auto-login
            $user_id = (int)$pdo->lastInsertId();
            $_SESSION['user'] = [
                'id' => $user_id,
                'name' => $name,
                'email' => $email,
                'is_admin' => $is_admin,
            ];

            flash_set('success', $is_admin ? 'Account created. You are the Admin (first user).' : 'Account created. Welcome.');
            redirect($base . '/index.php');
        } catch (PDOException $ex) {
            if (str_contains($ex->getMessage(), 'Duplicate') || str_contains($ex->getMessage(), 'UNIQUE')) {
                $errors[] = 'That email is already registered.';
            } else {
                $errors[] = 'Could not create account. Please try again.';
            }
        }
    }
}

ob_start();
?>
  <h1>Create account</h1>
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
    <form method="post" action="<?= e($base) ?>/index.php?r=register">
      <?= csrf_field() ?>
      <label>Name</label>
      <input name="name" type="text" value="<?= e($_POST['name'] ?? '') ?>" required>

      <label>Email</label>
      <input name="email" type="email" value="<?= e($_POST['email'] ?? '') ?>" required>

      <label>Password (min 8 characters)</label>
      <input name="password" type="password" required>

      <div style="margin-top:14px;display:flex;gap:10px;align-items:center;">
        <button class="btn" type="submit">Create account</button>
        <a class="btn secondary" href="<?= e($base) ?>/index.php?r=login">I already have an account</a>
      </div>
    </form>
  </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
