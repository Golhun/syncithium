<?php
declare(strict_types=1);

require_login();

$title = 'Change password';

$user = current_user();
$userId = (int)($user['id'] ?? 0);

if ($userId <= 0) {
    // Defensive fallback
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

$errors = [];
$base = rtrim(base_url(), '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        $errors[] = 'All fields are required.';
    }

    if ($new !== $confirm) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (strlen($new) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if (!$errors) {
        // Load fresh user row (ensures we verify against current stored hash)
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $hash = (string)($row['password_hash'] ?? $row['password'] ?? '');

        if ($hash === '' || !password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);

            // Update password, clear any reset token, clear must-change flag
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
                ':id' => $userId,
            ]);

            flash_set('success', 'Password updated successfully.');
            header('Location: ' . url_for('home'));
            exit;
        }
    }
}

ob_start();
?>
  <h1>Change password</h1>
  <p class="muted">Use this to change your password. If this is your first login, you must update it before continuing.</p>

  <?php if ($errors): ?>
    <div class="card" style="border-left:4px solid #ef4444;">
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

      <label>Current password</label>
      <input type="password" name="current_password" required>

      <div style="height:10px;"></div>

      <label>New password</label>
      <input type="password" name="new_password" required minlength="8">

      <div style="height:10px;"></div>

      <label>Confirm new password</label>
      <input type="password" name="confirm_password" required minlength="8">

      <div style="height:14px;"></div>

      <button class="btn" type="submit">Update password</button>
      <a class="btn btn-secondary" href="<?= e(url_for('home')) ?>">Cancel</a>
    </form>
  </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
