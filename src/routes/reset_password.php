<?php
declare(strict_types=1);

// Two modes:
// 1) Admin generate token: ?admin_generate=1&user_id=123   (admin only)
// 2) User consumes token: ?token=...  (public)

$title = 'Reset Password';

$pdo = db();

$adminGenerate = ((int)($_GET['admin_generate'] ?? 0) === 1);
if ($adminGenerate) {
    require_admin();

    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        flash_set('error', 'Invalid user.');
        redirect(url_for('admin_users'));
    }

    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $userId]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        flash_set('error', 'User not found.');
        redirect(url_for('admin_users'));
    }

    $token = bin2hex(random_bytes(24)); // share this
    $tokenHash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 60 * 60); // 1 hour

    $ins = $pdo->prepare("
        INSERT INTO password_resets (user_id, token_hash, expires_at, used_at, created_at)
        VALUES (:uid, :th, :ex, NULL, :cr)
    ");
    $ins->execute([
        ':uid' => (int)$u['id'],
        ':th' => $tokenHash,
        ':ex' => $expires,
        ':cr' => date('Y-m-d H:i:s'),
    ]);

    $link = url_for('reset_password', ['token' => $token]);

    ob_start();
    ?>
    <div class="max-w-2xl mx-auto">
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Reset token generated</h1>
      <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
        Share this link with the user. It expires in 1 hour.
      </p>

      <div class="mt-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
        <div class="text-sm text-slate-700 dark:text-slate-200">
          <div><span class="font-medium">User:</span> <?= e((string)$u['email']) ?></div>
          <div class="mt-3">
            <div class="font-medium mb-1">Reset link</div>
            <code class="block p-3 rounded-xl bg-slate-100 dark:bg-slate-800 break-all"><?= e($link) ?></code>
          </div>
          <div class="mt-3 text-xs text-slate-500 dark:text-slate-400">
            If the user cannot access the link, you can instead use “Reset (temp password)” from the users list.
          </div>
        </div>

        <div class="mt-4">
          <a class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
             href="<?= e(url_for('admin_users')) ?>">
            Back to users
          </a>
        </div>
      </div>
    </div>
    <?php
    $content = ob_get_clean();
    require __DIR__ . '/../views/layout.php';
    exit;
}

// Token consumption (public)
$token = (string)($_GET['token'] ?? '');
$token = trim($token);

if ($token === '') {
    http_response_code(400);
    echo 'Missing token.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $new1 = (string)($_POST['password'] ?? '');
    $new2 = (string)($_POST['password2'] ?? '');

    if ($new1 === '' || strlen($new1) < 8) {
        flash_set('error', 'Password must be at least 8 characters.');
        redirect(url_for('reset_password', ['token' => $token]));
    }
    if ($new1 !== $new2) {
        flash_set('error', 'Passwords do not match.');
        redirect(url_for('reset_password', ['token' => $token]));
    }

    $hash = hash('sha256', $token);

    $stmt = $pdo->prepare("
        SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at
        FROM password_resets pr
        WHERE pr.token_hash = :h
        ORDER BY pr.id DESC
        LIMIT 1
    ");
    $stmt->execute([':h' => $hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        flash_set('error', 'Invalid or expired token.');
        redirect(url_for('login'));
    }
    if (!empty($row['used_at'])) {
        flash_set('error', 'This token has already been used.');
        redirect(url_for('login'));
    }
    if (strtotime((string)$row['expires_at']) < time()) {
        flash_set('error', 'Token expired. Ask the Admin for a new one.');
        redirect(url_for('login'));
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE users
            SET password_hash = :ph,
                must_change_password = 0,
                updated_at = :u
            WHERE id = :id
        ")->execute([
            ':ph' => password_hash($new1, PASSWORD_DEFAULT),
            ':u' => date('Y-m-d H:i:s'),
            ':id' => (int)$row['user_id'],
        ]);

        $pdo->prepare("
            UPDATE password_resets
            SET used_at = :used
            WHERE id = :id
        ")->execute([
            ':used' => date('Y-m-d H:i:s'),
            ':id' => (int)$row['id'],
        ]);

        $pdo->commit();
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash_set('error', 'Reset failed. Please try again.');
        redirect(url_for('login'));
    }

    flash_set('success', 'Password reset successful. Please sign in.');
    redirect(url_for('login'));
}

ob_start();
?>
<div class="max-w-md mx-auto">
  <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Set a new password</h1>
  <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Choose a new password to regain access.</p>

  <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
    <form method="post">
      <?= csrf_field() ?>

      <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">New password</label>
      <input class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
             type="password" name="password" minlength="8" required>

      <div class="h-3"></div>

      <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Confirm password</label>
      <input class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
             type="password" name="password2" minlength="8" required>

      <div class="mt-4 flex gap-2">
        <button class="inline-flex items-center rounded-xl bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 text-sm" type="submit">
          Save new password
        </button>
        <a class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
           href="<?= e(url_for('login')) ?>">
          Back to login
        </a>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
