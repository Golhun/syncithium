<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Password Reset Tokens</h1>
    <p class="text-sm text-slate-600">Generate a reset token for a user, share it securely, token expires and is single-use.</p>
  </div>
  <div class="flex gap-2">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
       href="/public/index.php?r=admin_users">Back</a>
  </div>
</div>

<div class="grid md:grid-cols-2 gap-4">

  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="font-semibold mb-3">Find user</h2>

    <form method="get" action="/public/index.php" class="flex gap-2 mb-4">
      <input type="hidden" name="r" value="password_reset_request">
      <input name="q" value="<?= e($q ?? '') ?>" placeholder="Search email..."
        class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
      <button class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-100">Search</button>
    </form>

    <?php if (!empty($users)): ?>
      <div class="space-y-2">
        <?php foreach ($users as $u): ?>
          <div class="border border-slate-200 rounded-xl p-3 flex items-center justify-between">
            <div>
              <div class="font-medium"><?= e($u['email']) ?></div>
              <div class="text-xs text-slate-500">Role: <?= e($u['role']) ?><?= !empty($u['disabled_at']) ? ' , Disabled' : '' ?></div>
            </div>

            <form method="post" action="/public/index.php?r=password_reset_request">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="generate">
              <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
              <button class="rounded-lg bg-slate-900 text-white px-3 py-2 hover:opacity-95"
                <?= !empty($u['disabled_at']) ? 'disabled style="opacity:.5;cursor:not-allowed"' : '' ?>>
                Generate token
              </button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="text-sm text-slate-600">Search for a user to generate a token.</p>
    <?php endif; ?>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="font-semibold mb-3">Admin reset on behalf</h2>
    <p class="text-sm text-slate-600 mb-4">Enter a valid token and set a password. Default forces the user to change it on next login.</p>

    <form method="post" action="/public/index.php?r=password_reset_request" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="admin_reset_on_behalf">

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

      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="force_change" value="1" checked>
        Force user to change password on next login
      </label>

      <button class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95">
        Reset using token
      </button>
    </form>

    <?php if (!empty($generated)): ?>
      <hr class="my-6 border-slate-200">
      <div class="bg-slate-50 border border-slate-200 rounded-xl p-4">
        <div class="font-semibold mb-1">Token generated (shown once)</div>
        <div class="text-sm mb-3">
          <b>User:</b> <?= e($generated['email']) ?> , <b>TTL:</b> <?= (int)$generated['ttl_minutes'] ?> minutes
        </div>
        <div class="text-sm">
          <b>Token:</b>
          <code class="block mt-1 bg-white border border-slate-200 rounded p-2 break-all"><?= e($generated['token']) ?></code>
        </div>
        <p class="text-xs text-slate-600 mt-2">Share securely. Do not paste this token into chats or logs.</p>
      </div>
    <?php endif; ?>
  </div>

</div>
