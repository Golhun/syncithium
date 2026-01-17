<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Create user</h1>
    <p class="text-sm text-slate-600">System generates a temporary password and forces change on first login.</p>
  </div>
  <div class="flex gap-2">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
       href="/public/index.php?r=admin_users">Back</a>
  </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
  <form method="post" action="/public/index.php?r=admin_users_create" class="space-y-4">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium mb-1">Email</label>
      <input name="email" type="email" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Role</label>
      <select name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2">
        <option value="user">user</option>
        <option value="admin">admin</option>
      </select>
    </div>

    <button class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95">
      Create user
    </button>
  </form>

  <?php if (!empty($created)): ?>
    <div class="border border-slate-200 rounded-xl p-4 bg-slate-50">
      <p class="font-semibold mb-2">Created (shown once)</p>
      <ul class="text-sm space-y-1">
        <li><b>Email:</b> <?= e($created['email']) ?></li>
        <li><b>Role:</b> <?= e($created['role']) ?></li>
        <li><b>Temp password:</b> <code class="bg-white px-2 py-1 rounded"><?= e($created['temp']) ?></code></li>
      </ul>
      <p class="text-xs text-slate-600 mt-2">Share securely. User must change it on first login.</p>
    </div>
  <?php endif; ?>
</div>
