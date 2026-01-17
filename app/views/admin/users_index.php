<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">User Management</h1>
    <p class="text-sm text-slate-600">Create users, disable or enable accounts, and reset passwords.</p>
  </div>

  <div class="flex flex-wrap gap-2 justify-end">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
       href="/public/index.php?r=admin_levels">
      Taxonomy
    </a>

    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
       href="/public/index.php?r=password_reset_request">
      Reset tokens
    </a>

    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
       href="/public/index.php?r=admin_users_create">
      Create user
    </a>

    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
       href="/public/index.php?r=admin_users_bulk">
      Bulk upload
    </a>

    <a class="rounded-lg bg-slate-900 text-white px-3 py-2 hover:opacity-95"
       href="/public/index.php?r=logout">
      Sign out
    </a>
  </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl p-4 mb-4">
  <form method="get" action="/public/index.php" class="flex gap-2">
    <input type="hidden" name="r" value="admin_users">
    <input name="q" value="<?= e($q ?? '') ?>" placeholder="Search by email..."
      class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400">
    <button class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-100">Search</button>
  </form>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr>
          <th class="text-left p-3">Email</th>
          <th class="text-left p-3">Role</th>
          <th class="text-left p-3">Must change</th>
          <th class="text-left p-3">Status</th>
          <th class="text-left p-3">Created</th>
          <th class="text-right p-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr class="border-b border-slate-200 hover:bg-slate-50">
            <td class="p-3"><?= e($u['email']) ?></td>
            <td class="p-3"><?= e($u['role']) ?></td>
            <td class="p-3"><?= ((int)$u['must_change_password'] === 1) ? 'Yes' : 'No' ?></td>

            <td class="p-3">
              <?php if (!empty($u['disabled_at'])): ?>
                <span class="inline-flex items-center gap-1 text-xs bg-slate-200 px-2 py-1 rounded-full">
                  <?= icon('no-symbol', 'w-4 h-4') ?> Disabled
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 text-xs bg-sky-100 px-2 py-1 rounded-full">
                  Active
                </span>
              <?php endif; ?>
            </td>

            <td class="p-3"><?= e((string)$u['created_at']) ?></td>

            <td class="p-3">
              <div class="flex justify-end gap-2">
                <form method="post" action="/public/index.php?r=admin_users">
                  <?= csrf_field() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="action" value="reset_password">
                  <button class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-100">
                    Reset password
                  </button>
                </form>

                <?php if (!empty($u['disabled_at'])): ?>
                  <form method="post" action="/public/index.php?r=admin_users">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="action" value="enable">
                    <button class="rounded-lg bg-slate-900 text-white px-3 py-1.5 hover:opacity-95">
                      Enable
                    </button>
                  </form>
                <?php else: ?>
                  <form method="post" action="/public/index.php?r=admin_users">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="action" value="disable">
                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-100">
                      Disable
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
