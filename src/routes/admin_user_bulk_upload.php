<?php
declare(strict_types=1);

require_login();
require_admin();

$title = 'Admin: Bulk upload users';

$results = null;
$errors = [];

function random_password(int $len = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    if (empty($_FILES['csv_file']) || ($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errors[] = 'Please upload a valid CSV file.';
    } else {
        $tmp = $_FILES['csv_file']['tmp_name'];
        $fh = fopen($tmp, 'r');
        if (!$fh) {
            $errors[] = 'Unable to read the uploaded file.';
        } else {
            $header = fgetcsv($fh);
            if (!$header) {
                $errors[] = 'CSV appears to be empty.';
            } else {
                $header_norm = array_map(fn($h) => strtolower(trim((string)$h)), $header);

                // Expected: email, optional: is_admin
                $emailIdx = array_search('email', $header_norm, true);
                $adminIdx = array_search('is_admin', $header_norm, true);

                if ($emailIdx === false) {
                    $errors[] = 'Missing required column: email';
                } else {
                    $created = [];
                    $skipped = 0;

                    $stmt = db()->prepare("
                        INSERT INTO users (email, password_hash, is_admin, must_change_password, created_at, updated_at)
                        VALUES (:email, :hash, :is_admin, 1, :created_at, :updated_at)
                    ");

                    $now = date('Y-m-d H:i:s');

                    while (($row = fgetcsv($fh)) !== false) {
                        $email = strtolower(trim((string)($row[$emailIdx] ?? '')));
                        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $skipped++;
                            continue;
                        }

                        $isAdmin = 0;
                        if ($adminIdx !== false) {
                            $raw = trim((string)($row[$adminIdx] ?? '0'));
                            $isAdmin = in_array($raw, ['1','yes','true','admin'], true) ? 1 : 0;
                        }

                        $plain = random_password(12);
                        $hash = password_hash($plain, PASSWORD_DEFAULT);

                        try {
                            $stmt->execute([
                                ':email' => $email,
                                ':hash' => $hash,
                                ':is_admin' => $isAdmin,
                                ':created_at' => $now,
                                ':updated_at' => $now,
                            ]);
                            $created[] = ['email' => $email, 'password' => $plain, 'is_admin' => $isAdmin];
                        } catch (Throwable $e) {
                            $skipped++;
                        }
                    }

                    $results = ['created' => $created, 'skipped' => $skipped];
                }
            }
            fclose($fh);
        }
    }
}

ob_start();
?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-slate-100">Bulk upload users</h1>
  <p class="text-slate-400 text-sm mt-1">CSV columns: <span class="font-mono">email</span> (required), <span class="font-mono">is_admin</span> (optional).</p>
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

<?php if ($results): ?>
  <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 mb-6">
    <p class="text-emerald-200 font-medium">Upload processed.</p>
    <p class="text-slate-200 text-sm mt-1">Created: <?= (int)count($results['created']) ?>, Skipped: <?= (int)$results['skipped'] ?></p>
    <p class="text-slate-400 text-xs mt-2">Copy the passwords now. They are not shown again.</p>
  </div>

  <div class="overflow-x-auto rounded-xl border border-slate-800 mb-6" x-data>
    <table class="min-w-full divide-y divide-slate-800">
      <thead class="bg-slate-900/60">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-300">Email</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-300">Temp password</th>
          <th class="px-4 py-3 text-left text-xs font-semibold text-slate-300">Admin</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800 bg-slate-950/40">
        <?php foreach ($results['created'] as $u): ?>
          <tr class="hover:bg-slate-900/40">
            <td class="px-4 py-3 text-sm text-slate-100 font-mono"><?= e($u['email']) ?></td>
            <td class="px-4 py-3 text-sm text-slate-100 font-mono"><?= e($u['password']) ?></td>
            <td class="px-4 py-3 text-sm text-slate-300"><?= ((int)$u['is_admin'] === 1) ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<div class="rounded-xl border border-slate-800 bg-slate-950/40 p-5 max-w-xl">
  <form method="post" enctype="multipart/form-data" class="space-y-4">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium text-slate-200 mb-1">CSV file</label>
      <input class="w-full rounded-lg border border-slate-800 bg-slate-950 px-3 py-2 text-slate-100"
             type="file" name="csv_file" accept=".csv" required>
    </div>

    <div class="flex gap-2">
      <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500" type="submit">
        Upload
      </button>
      <a class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700"
         href="<?= e(url_for('admin_users')) ?>">
        Back
      </a>
    </div>

    <div class="text-xs text-slate-400">
      Example CSV header: <span class="font-mono">email,is_admin</span>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
