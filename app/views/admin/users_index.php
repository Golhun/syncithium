<?php
/** @var array $users */
/** @var string $q */
?>
<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">User Management</h1>
    <div class="flex gap-2">
      <a class="px-3 py-2 rounded-lg border border-gray-200" href="/public/index.php?r=admin_users_create">Create user</a>
      <a class="px-3 py-2 rounded-lg border border-gray-200" href="/public/index.php?r=admin_users_bulk">Bulk import</a>
    </div>
  </div>

  <form method="get" class="mb-4">
    <input type="hidden" name="r" value="admin_users">
    <div class="flex gap-2">
      <input class="w-full px-3 py-2 rounded-lg border border-gray-200"
             name="q" value="<?= htmlspecialchars((string)$q) ?>"
             placeholder="Search by email">
      <button class="px-3 py-2 rounded-lg border border-gray-200" type="submit">Search</button>
    </div>
  </form>

  <div class="overflow-x-auto border border-gray-200 rounded-xl">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-3">Email</th>
          <th class="text-left p-3">Role</th>
          <th class="text-left p-3">Must change</th>
          <th class="text-left p-3">Status</th>
          <th class="text-left p-3">Created</th>
          <th class="text-left p-3">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($users)): ?>
        <tr><td class="p-3" colspan="6">No users found.</td></tr>
      <?php else: ?>
        <?php foreach ($users as $u): ?>
          <tr class="border-t border-gray-200">
            <td class="p-3"><?= htmlspecialchars((string)$u['email']) ?></td>
            <td class="p-3"><?= htmlspecialchars((string)$u['role']) ?></td>
            <td class="p-3"><?= ((int)$u['must_change_password'] === 1) ? 'Yes' : 'No' ?></td>
            <td class="p-3"><?= empty($u['disabled_at']) ? 'Active' : 'Disabled' ?></td>
            <td class="p-3"><?= htmlspecialchars((string)$u['created_at']) ?></td>
            <td class="p-3">
              <form method="post" class="flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">

                <?php if (empty($u['disabled_at'])): ?>
                  <button class="px-2 py-1 rounded-lg border border-gray-200" name="action" value="disable">Disable</button>
                <?php else: ?>
                  <button class="px-2 py-1 rounded-lg border border-gray-200" name="action" value="enable">Enable</button>
                <?php endif; ?>

                <button class="px-2 py-1 rounded-lg border border-gray-200" name="action" value="reset_password">
                  Reset password
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
