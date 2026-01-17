<?php
declare(strict_types=1);

$title = 'Reset password';

$token = (string)($_GET['t'] ?? '');
$token = trim($token);

$errors = [];
$done = false;

if ($token === '') {
    $errors[] = 'Invalid or missing reset token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $token = trim((string)($_POST['token'] ?? $token));
    $pw1 = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password_confirm'] ?? '');

    if ($token === '') $errors[] = 'Invalid or missing reset token.';
    if (strlen($pw1) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($pw1 !== $pw2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = db()->query("
            SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.used_at, u.email
            FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.used_at IS NULL
            ORDER BY pr.id DESC
            LIMIT 200
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $match = null;
        foreach ($rows as $r) {
            $hash = (string)($r['token_hash'] ?? '');
            if ($hash !== '' && password_verify($token, $hash)) {
                $match = $r;
                break;
            }
        }

        if (!$match) {
            $errors[] = 'This reset token is invalid or has already been used.';
        } else {
            $expiresAt = strtotime((string)$match['expires_at']);
            if ($expiresAt !== false && time() > $expiresAt) {
                $errors[] = 'This reset token has expired. Ask the administrator for a new one.';
            } else {
                $newHash = password_hash($pw1, PASSWORD_DEFAULT);
                $now = date('Y-m-d H:i:s');

                $upd = db()->prepare("
                    UPDATE users
                    SET password_hash = :h, must_change_password = 0, updated_at = :now
                    WHERE id = :uid
                ");
                $upd->execute([
                    ':h' => $newHash,
                    ':now' => $now,
                    ':uid' => (int)$match['user_id'],
                ]);

                $mark = db()->prepare("UPDATE password_resets SET used_at = :now WHERE id = :id");
                $mark->execute([':now' => $now, ':id' => (int)$match['id']]);

                login_user((int)$match['user_id']);
                flash_set('success', 'Password updated successfully.');
                redirect(url_for('home'));
            }
        }
    }
}

ob_start();
?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-slate-100">Reset your password</h1>
  <p class="text-slate-400 text-sm mt-1">Use the reset link provided by the administrator.</p>
</div>

<?php if ($errors): ?>
  <div class="rounded-xl border border-red-500/30 bg-red-500/10 p-4 mb-6">
    <p class="text-red-200 font-medium mb-2">Fix the following:</p>
    <ul class="list-disc pl-5 text-red-100 text-sm space-y-1">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="rounded-xl border border-slate-800 bg-slate-950/40 p-5 max-w-xl">
  <form method="post" class="space-y-4" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <div>
      <label class="block text-sm font-medium text-slate-200 mb-1">New password</label>
      <input class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:outline-none focus:ring-4 focus:ring-sky-100/20 focus:border-sky-400"
             type="password" name="password" required>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-200 mb-1">Confirm password</label>
      <input class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100 focus:outline-none focus:ring-4 focus:ring-sky-100/20 focus:border-sky-400"
             type="password" name="password_confirm" required>
    </div>

    <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500" type="submit">
      Update password
    </button>
  </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
