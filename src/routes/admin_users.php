<?php
declare(strict_types=1);

require_admin();

$title = 'Admin: Users';

function random_password(int $len = 12): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#%*!?';
    $out = '';
    for ($i=0; $i<$len; $i++) $out .= $alphabet[random_int(0, strlen($alphabet)-1)];
    return $out;
}

$created = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $makeAdmin = !empty($_POST['is_admin']) ? 1 : 0;

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Enter a valid email address.');
        redirect(url_for('admin_users'));
    }

    $pdo = db();

    $exists = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
    $exists->execute([':e' => $email]);
    if ($exists->fetchColumn()) {
        flash_set('error', 'User already exists.');
        redirect(url_for('admin_users'));
    }

    $temp = random_password(12);
    $hash = password_hash($temp, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, is_admin, must_change_password, created_at)
        VALUES (:email, :hash, :is_admin, 1, :created_at)
    ");
    $stmt->execute([
        ':email' => $email,
        ':hash' => $hash,
        ':is_admin' => $makeAdmin,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);

    $created = ['email' => $email, 'temp_password' => $temp, 'is_admin' => $makeAdmin];
    flash_set('success', 'User created. Copy the temporary password now, it will not be shown again.');
}

$rows = db()->query("
    SELECT id, email, is_admin, must_change_password, disabled_at, created_at
    FROM users
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
<div class="max-w-4xl mx-auto">
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">User Management</h1>
      <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
        Create users, enforce first-login password change, reset access when needed.
      </p>
    </div>
    <div class="flex gap-2">
      <a class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
         href="<?= e(url_for('admin_users_bulk')) ?>">
        Bulk add
      </a>
    </div>
  </div>

  <?php if ($created): ?>
    <div class="mt-4 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
      <div class="text-sm text-slate-700 dark:text-slate-200">
        <div class="font-semibold">New user created</div>
        <div class="mt-2 grid gap-2">
          <div><span class="font-medium">Email:</span> <?= e($created['email']) ?></div>
          <div><span class="font-medium">Temp password:</span> <code class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800"><?= e($created['temp_password']) ?></code></div>
          <div><span class="font-medium">Admin:</span> <?= $created['is_admin'] ? 'Yes' : 'No' ?></div>
        </div>
        <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
          Share the temp password securely. The user will be forced to change it on first login.
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-4">
    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Create a user</h2>

    <form class="mt-4 grid gap-4" method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Email</label>
        <input class="mt-1 w-full rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-2 bg-white dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
               type="email" name="email" required inputmode="email" autocomplete="off">
      </div>

      <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
        <input type="checkbox" name="is_admin" value="1">
        Make this user an Admin
      </label>

      <div class="flex gap-2">
        <button class="inline-flex items-center rounded-xl bg-sky-600 hover:bg-sky-700 text-white px-4 py-2 text-sm" type="submit">
          Create user
        </button>
        <a class="inline-flex items-center rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-sm hover:bg-slate-50 dark:hover:bg-slate-800"
           href="<?= e(url_for('home')) ?>">
          Back
        </a>
      </div>
    </form>
  </div>

  <div class="mt-6 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 overflow-hidden">
    <div class="p-4 border-b border-slate-200 dark:border-slate-700">
      <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Users</h2>
      <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Use actions to reset or disable accounts.</p>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-800">
          <tr class="text-left">
            <th class="px-4 py-3">ID</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Admin</th>
            <th class="px-4 py-3">Must change</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $u): ?>
          <tr class="border-t border-slate-200 dark:border-slate-700">
            <td class="px-4 py-3"><?= (int)$u['id'] ?></td>
            <td class="px-4 py-3"><?= e((string)$u['email']) ?></td>
            <td class="px-4 py-3"><?= !empty($u['is_admin']) ? 'Yes' : 'No' ?></td>
            <td class="px-4 py-3"><?= !empty($u['must_change_password']) ? 'Yes' : 'No' ?></td>
            <td class="px-4 py-3">
              <?= !empty($u['disabled_at']) ? 'Disabled' : 'Active' ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-2">
                <form method="post" action="<?= e(url_for('admin_user_reset')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button class="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800"
                          type="submit">
                    Reset (temp password)
                  </button>
                </form>

                <a class="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800"
                   href="<?= e(url_for('reset_password', ['admin_generate' => 1, 'user_id' => (int)$u['id']])) ?>">
                  Generate reset token
                </a>

                <form method="post" action="<?= e(url_for('admin_user_toggle')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button class="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800"
                          type="submit">
                    <?= !empty($u['disabled_at']) ? 'Enable' : 'Disable' ?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
