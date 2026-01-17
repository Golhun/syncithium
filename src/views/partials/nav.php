<?php
$user = current_user();
$base = rtrim(base_url(), '/');

$isAdmin = false;
if ($user) {
    $isAdmin = is_admin_user($user);
}
?>
<nav class="border-b border-slate-800 bg-slate-950/60 backdrop-blur">
  <div class="mx-auto max-w-6xl px-4 py-3 flex items-center justify-between">
    <a href="<?= e(url_for('home')) ?>" class="flex items-center gap-2 group">
      <span class="inline-flex items-center justify-center rounded-lg bg-sky-500/10 border border-sky-400/20 p-2">
        <?= heroicon_swap('sparkles', 'h-5 w-5', 'text-sky-300', 'text-sky-200') ?>
      </span>
      <span class="font-semibold text-slate-100 tracking-tight">
        <?= e((string)app_config('app.name', 'Syncithium')) ?>
      </span>
    </a>

    <div class="flex items-center gap-2">
      <a class="group inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-900"
         href="<?= e(url_for('home')) ?>">
        <?= heroicon_swap('home', 'h-5 w-5', 'text-slate-400', 'text-white') ?>
        <span>Home</span>
      </a>

      <?php if ($user): ?>
        <a class="group inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-900"
           href="<?= e(url_for('change_password')) ?>">
          <?= heroicon_swap('key', 'h-5 w-5', 'text-slate-400', 'text-white') ?>
          <span>Password</span>
        </a>

        <?php if ($isAdmin): ?>
          <a class="group inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-900"
             href="<?= e(url_for('admin_users')) ?>">
            <?= heroicon_swap('users', 'h-5 w-5', 'text-slate-400', 'text-white') ?>
            <span>Admin</span>
          </a>
        <?php endif; ?>

        <a class="group inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-900"
           href="<?= e(url_for('logout')) ?>">
          <?= heroicon_swap('arrow-right-on-rectangle', 'h-5 w-5', 'text-slate-400', 'text-white') ?>
          <span>Logout</span>
        </a>
      <?php else: ?>
        <a class="group inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-300 hover:text-white hover:bg-slate-900"
           href="<?= e(url_for('login')) ?>">
          <?= heroicon_swap('arrow-left-on-rectangle', 'h-5 w-5', 'text-slate-400', 'text-white') ?>
          <span>Login</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</nav>
