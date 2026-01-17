<div class="bg-white border border-slate-200 rounded-xl p-6 md:p-8">
  <div class="flex items-center gap-2 mb-2">
    <?= icon('arrow-path', 'w-6 h-6') ?>
    <h1 class="text-xl font-semibold">Reset password</h1>
  </div>

  <p class="text-sm text-slate-600 mb-6">
    Enter your reset token and choose a new password.
  </p>

  <form method="post" action="/public/index.php?r=password_reset" class="space-y-4">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium mb-1">Token</label>
      <input name="token" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">New password</label>
      <input name="password" type="password" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Confirm password</label>
      <input name="password_confirm" type="password" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
    </div>

    <button class="w-full rounded-lg bg-slate-900 text-white py-2 font-semibold hover:opacity-95">
      Update password
    </button>

    <div class="text-xs text-slate-500">
      Back to <a class="underline" href="/public/index.php?r=login">Sign in</a>
    </div>
  </form>
</div>
