<div class="bg-white border border-slate-200 rounded-xl p-6 md:p-8">
  <div class="flex items-center gap-2 mb-4">
    <?= icon('lock-closed', 'w-6 h-6') ?>
    <h1 class="text-xl font-semibold">Sign in</h1>
  </div>

  <form method="post" action="/public/index.php?r=login" class="space-y-4">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium mb-1">Email</label>
      <input name="email" type="email" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Password</label>
      <input name="password" type="password" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
    </div>

    <button class="w-full rounded-lg bg-slate-900 text-white py-2 font-semibold hover:opacity-95">
      Sign in
    </button>

    <p class="text-xs text-slate-500 mt-3">
      No public registration. If you need access, contact an admin.
    </p>
  </form>
</div>
