<?php
declare(strict_types=1);

$title = 'Reset password';

$errors = [];

$email = strtolower(trim((string)($_GET['email'] ?? ($_POST['email'] ?? ''))));
$token = trim((string)($_GET['token'] ?? ($_POST['token'] ?? '')));

$success = false;

function find_user_by_reset_token(string $email, string $token): ?array
{
    if ($email === '' || $token === '') return null;

    $tokenHash = hash('sha256', $token);

    $stmt = db()->prepare("
        SELECT *
          FROM users
         WHERE email = :email
           AND reset_token_hash = :h
           AND reset_token_expires_at IS NOT NULL
           AND reset_token_expires_at >= :now
         LIMIT 1
    ");
    $stmt->execute([
        ':email' => $email,
        ':h' => $tokenHash,
        ':now' => date('Y-m-d H:i:s'),
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($email === '' || $token === '') {
        $errors[] = 'Missing email or token.';
    }

    if ($new === '' || $confirm === '') {
        $errors[] = 'New password and confirmation are required.';
    }

    if ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (strlen($new) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        $user = find_user_by_reset_token($email, $token);

        if (!$user) {
            $errors[] = 'Invalid or expired reset token.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);

            $upd = db()->prepare("
                UPDATE users
                   SET password_hash = :ph,
                       must_change_password = 0,
                       reset_token_hash = NULL,
                       reset_token_expires_at = NULL,
                       reset_token_created_at = NULL
                 WHERE id = :id
                 LIMIT 1
            ");
            $upd->execute([
                ':ph' => $newHash,
                ':id' => (int)$user['id'],
            ]);

            $success = true;
            flash_set('success', 'Password reset successful. Please sign in.');
            header('Location: ' . url_for('login'));
            exit;
        }
    }
}

ob_start();
?>
  <h1>Reset password</h1>
  <p class="muted">Enter the reset token shared by the Admin and set a new password.</p>

  <?php if ($errors): ?>
    <div class="card" style="border-left:4px solid #ef4444; margin-bottom:12px;">
      <p><strong>Fix the following:</strong></p>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card" style="max-width:560px;">
    <form method="post">
      <?= csrf_field() ?>

      <label>Email</label>
      <input type="email" name="email" value="<?= e($email) ?>" required>

      <div style="height:10px;"></div>

      <label>Reset token</label>
      <input type="text" name="token" value="<?= e($token) ?>" required>

      <div style="height:10px;"></div>

      <label>New password</label>
      <input type="password" name="new_password" required minlength="8">

      <div style="height:10px;"></div>

      <label>Confirm new password</label>
      <input type="password" name="confirm_password" required minlength="8">

      <div style="height:14px;"></div>

      <button class="btn" type="submit">Reset password</button>
      <a class="btn btn-secondary" href="<?= e(url_for('login')) ?>">Back</a>
    </form>
  </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
