<?php
declare(strict_types=1);

require_login();
require_admin();

$title = 'Admin: Temp password';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_set('error', 'Invalid user.');
    redirect(url_for('admin_users'));
}

$stmt = db()->prepare("SELECT id, email FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    flash_set('error', 'User not found.');
    redirect(url_for('admin_users'));
}

function random_password(int $len = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

$plain = random_password(12);
$hash = password_hash($plain, PASSWORD_DEFAULT);
$now = date('Y-m-d H:i:s');

$upd = db()->prepare("
    UPDATE users
    SET password_hash = :h, must_change_password = 1, updated_at = :now
    WHERE id = :id
");
$upd->execute([':h' => $hash, ':now' => $now, ':id' => (int)$user['id']]);

ob_start();
?>
<div class="mb-6">
  <h1 class="text-2xl font-semibold text-slate-100">Temporary password</h1>
  <p class="text-slate-400 text-sm mt-1">Share this with the user. They will be forced to change it at login.</p>
</div>

<div class="rounded-xl border border-slate-800 bg-slate-950/40 p-5 max-w-xl" x-data="{copied:false}">
  <div class="space-y-2">
    <p class="text-sm text-slate-300">Email</p>
    <p class="font-mono text-slate-100"><?= e((string)$user['email']) ?></p>

    <p class="text-sm text-slate-300 mt-4">Temporary password</p>
    <p class="font-mono text-slate-100" x-ref="pw"><?= e($plain) ?></p>

    <div class="flex gap-2 mt-4">
      <button
        class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium text-white hover:bg-slate-700"
        @click="navigator.clipboard.writeText($refs.pw.textContent).then(()=>{copied=true; setTimeout(()=>copied=false,1200)})">
        <span x-show="!copied">Copy password</span>
        <span x-show="copied">Copied</span>
      </button>

      <a class="rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium text-white hover:bg-slate-700"
         href="<?= e(url_for('admin_users')) ?>">
        Back
      </a>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
