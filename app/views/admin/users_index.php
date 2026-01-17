<?php
declare(strict_types=1);

/**
 * app/views/admin/users_index.php
 *
 * Expected variables:
 *  - $users : array
 *  - $q     : string
 *  - $admin : array|null (optional)
 */
$users = (isset($users) && is_array($users)) ? $users : [];
$q = (string)($q ?? '');
$admin = $admin ?? null;

function fmt_dt(?string $dt): string {
  if (!$dt) return '';
  return htmlspecialchars($dt, ENT_QUOTES, 'UTF-8');
}


?>
<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-xl font-semibold">User Management</h1>
      <p class="text-sm text-gray-500">
        Create users, disable or enable access, and reset passwords. Password reset forces a change on next sign-in.
      </p>
    </div>

    <div class="flex gap-2">
      <a class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
         href="/public/index.php?r=admin_users_create">Create user</a>
      <a class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
         href="/public/index.php?r=admin_users_bulk">Bulk import</a>
    </div>
  </div>

  <form method="get" class="mb-4">
    <input type="hidden" name="r" value="admin_users">
    <div class="flex gap-2">
      <input class="w-full px-3 py-2 rounded-lg border border-gray-200"
             name="q" value="<?= e($q) ?>"
             placeholder="Search by email">
      <button class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50" type="submit">
        Search
      </button>

      <?php if ($q !== ''): ?>
        <a class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
           href="/public/index.php?r=admin_users">Clear</a>
      <?php endif; ?>
    </div>
  </form>

  <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white">
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
      <?php if (count($users) === 0): ?>
        <tr>
          <td class="p-3 text-gray-600" colspan="6">No users found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($users as $u): ?>
          <?php
            $uid = (int)($u['id'] ?? 0);
            $email = (string)($u['email'] ?? '');
            $role = (string)($u['role'] ?? 'user');
            $must = (int)($u['must_change_password'] ?? 0) === 1;
            $disabled = !empty($u['disabled_at']);
            $createdAt = (string)($u['created_at'] ?? '');

            $isSelf = false;
            if (is_array($admin) && isset($admin['id'])) {
              $isSelf = ((int)$admin['id'] === $uid);
            }
          ?>
          <tr class="border-t border-gray-200">
            <td class="p-3">
              <div class="font-medium"><?= e($email) ?></div>
              <?php if ($isSelf): ?>
                <div class="text-xs text-gray-500">You</div>
              <?php endif; ?>
            </td>

            <td class="p-3"><?= e($role) ?></td>

            <td class="p-3">
              <?php if ($must): ?>
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs border border-gray-200 bg-gray-50">
                  Yes
                </span>
              <?php else: ?>
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs border border-gray-200 bg-white">
                  No
                </span>
              <?php endif; ?>
            </td>

            <td class="p-3">
              <?php if ($disabled): ?>
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs border border-gray-200 bg-gray-50">
                  Disabled
                </span>
              <?php else: ?>
                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs border border-gray-200 bg-white">
                  Active
                </span>
              <?php endif; ?>
            </td>

            <td class="p-3"><?= fmt_dt($createdAt) ?></td>

            <td class="p-3">
              <form method="post" class="flex flex-wrap gap-2 items-center">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= $uid ?>">

                <?php if ($disabled): ?>
                  <button class="px-2 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
                          name="action" value="enable">
                    Enable
                  </button>
                <?php else: ?>
                  <button class="px-2 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
                          name="action" value="disable"
                          <?php if ($isSelf): ?>disabled title="You cannot disable your own account"<?php endif; ?>>
                    Disable
                  </button>
                <?php endif; ?>

                <button class="px-2 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
                        name="action" value="reset_password"
                        <?php if ($isSelf): ?>title="You can reset via change password, but this is allowed if you prefer"<?php endif; ?>>
                  Reset password
                </button>
              </form>

              <p class="mt-2 text-xs text-gray-500">
                Reset generates a temporary password and forces change on next login.
              </p>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
