<div class="bg-white border border-slate-200 rounded-xl p-6 md:p-8">
  <div class="flex items-center gap-2 mb-2">
    <?= icon('arrow-path', 'w-6 h-6') ?>
    <h1 class="text-xl font-semibold">Set a new password</h1>
  </div>
  <p class="text-sm text-slate-600 mb-6">
    This is your first login or your password was reset. Set a new password to continue.
  </p>

  <form method="post" action="/public/index.php?r=force_password_change" class="space-y-4">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium mb-1">New password</label>
      <input name="password" type="password" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
      <p class="text-xs text-slate-500 mt-1">Minimum length applies.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Confirm password</label>
      <input name="password_confirm" type="password" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
    </div>

    <button class="w-full rounded-lg bg-slate-900 text-white py-2 font-semibold hover:opacity-95">
      Save and continue
    </button>
  </form>
</div>
