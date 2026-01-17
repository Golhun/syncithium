<?php
declare(strict_types=1);

require_admin();

$title = 'Admin: Bulk add users';

function random_password(int $len = 12): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#%*!?';
    $out = '';
    for ($i=0; $i<$len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
    return $out;
}

$created = [];
$skipped = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $raw = (string)($_POST['emails'] ?? '');
    $makeAdmin = !empty($_POST['is_admin']) ? 1 : 0;

    $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $emails = [];
    foreach ($parts as $p) {
        $e = strtolower(trim((string)$p));
        if ($e !== '') $emails[] = $e;
    }
    $emails = array_values(array_unique($emails));

    if (!$emails) {
        $errors[] = 'Paste at least one email.';
    } else {
        $pdo = db();

        $check = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
        $ins = $pdo->prepare("
            INSERT INTO users (email, password_hash, is_admin, must_change_password, created_at)
            VALUES (:email, :hash, :is_admin, 1, :created_at)
        ");

        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = [$email, 'Invalid email'];
                continue;
            }

            $check->execute([':e' => $email]);
            if ($check->fetchColumn()) {
                $skipped[] = [$email, 'Already exists'];
                continue;
            }

            $temp = random_password(12);
            $hash = password_hash($temp, PASSWORD_DEFAULT);

            try {
                $ins->execute([
                    ':email' => $email,
                    ':hash' => $hash,
                    ':is_admin' => $makeAdmin,
                    ':created_at' => date('Y-m-d H:i:s'),
                ]);
                $created[] = [$email, $temp];
            } catch (Throwable $t) {
                $skipped[] = [$email, 'Insert failed'];
            }
        }

        if ($created) {
            flash_set('success', 'Bulk add complete. Copy passwords now, they will not be shown again.');
        }
    }
}

ob_start();
?>
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Bulk add users</h1>
  <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
    Paste emails separated by spaces, commas, or new lines. Each user gets a temp password and must change it on first login.
  </p>

  <?php if ($errors): ?>
    <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
      <ul class="list-disc ml-5">
        <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
    <form method="post">
      <?= csrf_field() ?>

      <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Emails</label>
      <textarea class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
                name="emails" rows="8" placeholder="user1@example.com&#10;user2@example.com" required></textarea>

      <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
        <input type="checkbox" name="is_admin" value="1">
        Make all these users Admins
      </label>

      <div class="mt-4 flex gap-2">
        <button class="inline-flex items-center rounded-xl bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 text-sm" type="submit">
          Create users
        </button>
        <a class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
           href="<?= e(url_for('admin_users')) ?>">
          Back
        </a>
      </div>
    </form>
  </div>

  <?php if ($created): ?>
    <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Created users</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">Copy these passwords now.</p>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-800">
            <tr class="text-left">
              <th class="px-4 py-3">Email</th>
              <th class="px-4 py-3">Temp password</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($created as $row): ?>
            <tr class="border-t border-slate-200 dark:border-slate-700">
              <td class="px-4 py-3"><?= e($row[0]) ?></td>
              <td class="px-4 py-3"><code class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800"><?= e($row[1]) ?></code></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($skipped): ?>
    <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden">
      <div class="p-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Skipped</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-800">
            <tr class="text-left">
              <th class="px-4 py-3">Email</th>
              <th class="px-4 py-3">Reason</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($skipped as $row): ?>
            <tr class="border-t border-slate-200 dark:border-slate-700">
              <td class="px-4 py-3"><?= e($row[0]) ?></td>
              <td class="px-4 py-3"><?= e($row[1]) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';

