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

if (!function_exists('fmt_dt')) {
  function fmt_dt(?string $dt): string {
    if (!$dt) return '';
    return htmlspecialchars($dt, ENT_QUOTES, 'UTF-8');
  }
}

$usersCount = count($users);
?>
<div class="max-w-6xl mx-auto" x-data="usersIndex()" x-init="init()">

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-5">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?= icon('users', 'h-5 w-5 text-sky-700', 'solid') ?>
        </div>
        <h1 class="text-xl font-semibold text-slate-900">User Management</h1>
      </div>

      <p class="text-sm text-slate-500 mt-2 max-w-3xl">
        Create users, control access, and reset passwords. Password reset generates a temporary password and forces a change on next sign-in.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('information-circle', 'h-4 w-4 text-slate-400', 'outline') ?>
          <span><?= (int)$usersCount ?> user<?= $usersCount === 1 ? '' : 's' ?></span>
        </span>

        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('key', 'h-4 w-4 text-slate-400', 'outline') ?>
          <span>Resets require next-login password change</span>
        </span>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 sm:justify-end">
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_users_create">
        <?= icon('plus-circle', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Create user</span>
      </a>

      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_users_bulk">
        <?= icon('arrow-up-tray', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Bulk import</span>
      </a>
    </div>
  </div>

  <!-- Search -->
  <form method="get" class="mb-4">
    <input type="hidden" name="r" value="admin_users">

    <div class="bg-white rounded-2xl ring-1 ring-slate-200 p-3 sm:p-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
          <label class="sr-only" for="q">Search</label>
          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
              <?= icon('magnifying-glass', 'h-5 w-5 text-slate-400', 'outline') ?>
            </div>
            <input
              id="q"
              name="q"
              value="<?= e($q) ?>"
              placeholder="Search by email"
              class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                     focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
            >
          </div>
          <p class="text-xs text-slate-500 mt-2">
            Tip: Use a partial email to narrow results quickly.
          </p>
        </div>

        <div class="flex gap-2 sm:justify-end">
          <button
            class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-slate-900 text-white
                   hover:opacity-95 active:opacity-90 focus:outline-none focus:ring-4 focus:ring-sky-100 transition"
            type="submit"
          >
            <?= icon('magnifying-glass', 'h-4 w-4 text-white', 'solid') ?>
            <span class="text-sm font-semibold">Search</span>
          </button>

          <?php if ($q !== ''): ?>
            <a
              class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white
                     hover:bg-slate-50 transition"
              href="/public/index.php?r=admin_users"
              title="Clear search"
            >
              <?= icon('x-mark', 'h-4 w-4 text-slate-600', 'outline') ?>
              <span class="text-sm font-medium text-slate-800">Clear</span>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </form>

  <!-- Table surface -->
  <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
      <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
        <?= icon('user-group', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span>Users</span>
      </div>

      <div class="text-xs text-slate-500 inline-flex items-center gap-2">
        <?= icon('sparkles', 'h-4 w-4 text-sky-600', 'outline') ?>
        <span>Actions are logged in audit trail.</span>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-white">
          <tr class="text-left text-xs font-semibold text-slate-600">
            <th class="px-4 py-3">User</th>
            <th class="px-4 py-3">Role</th>
            <th class="px-4 py-3">Policy</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Created</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-200">
        <?php if ($usersCount === 0): ?>
          <tr>
            <td class="px-4 py-6 text-slate-600" colspan="6">
              <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-slate-50 ring-1 ring-slate-200 flex items-center justify-center">
                  <?= icon('inbox', 'h-5 w-5 text-slate-500', 'outline') ?>
                </div>
                <div>
                  <div class="font-semibold text-slate-800">No users found</div>
                  <div class="text-xs text-slate-500 mt-1">
                    Try a broader search term or clear filters.
                  </div>
                </div>
              </div>
            </td>
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

              // Role chip
              $roleTone = ($role === 'admin') ? 'sky' : 'gray';

              // Status chip + icon variant (solid for strong state)
              $statusLabel = $disabled ? 'Disabled' : 'Active';
              $statusIcon  = $disabled ? 'no-symbol' : 'check-circle';
              $statusTone  = $disabled ? 'bg-amber-50 text-amber-900 ring-amber-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200';

              // Policy chip
              $policyLabel = $must ? 'Must change' : 'OK';
              $policyIcon  = $must ? 'exclamation-triangle' : 'shield-check';
              $policyTone  = $must ? 'bg-rose-50 text-rose-800 ring-rose-200' : 'bg-slate-50 text-slate-700 ring-slate-200';
            ?>

            <tr class="hover:bg-slate-50/60 transition">
              <!-- User -->
              <td class="px-4 py-4">
                <div class="flex items-start gap-3">
                  <div class="h-9 w-9 rounded-xl bg-slate-50 ring-1 ring-slate-200 flex items-center justify-center shrink-0">
                    <?= icon('user-circle', 'h-5 w-5 text-slate-600', $role === 'admin' ? 'solid' : 'outline') ?>
                  </div>

                  <div class="min-w-0">
                    <div class="font-semibold text-slate-900 truncate">
                      <?= e($email) ?>
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-2">
                      <?php if ($isSelf): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs ring-1 ring-slate-200 bg-white text-slate-600">
                          <?= icon('sparkles', 'h-3.5 w-3.5 text-sky-600', 'outline') ?>
                          You
                        </span>
                      <?php endif; ?>

                      <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs ring-1 ring-slate-200 bg-white text-slate-600">
                        <?= icon('identification', 'h-3.5 w-3.5 text-slate-400', 'outline') ?>
                        ID <?= (int)$uid ?>
                      </span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Role -->
              <td class="px-4 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1
                             <?= $roleTone === 'sky' ? 'bg-sky-50 text-sky-800 ring-sky-200' : 'bg-slate-50 text-slate-700 ring-slate-200' ?>">
                  <?= icon($role === 'admin' ? 'key' : 'user', 'h-4 w-4', $role === 'admin' ? 'solid' : 'outline') ?>
                  <?= e($role) ?>
                </span>
              </td>

              <!-- Policy -->
              <td class="px-4 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 <?= $policyTone ?>">
                  <?= icon($policyIcon, 'h-4 w-4', $must ? 'solid' : 'outline') ?>
                  <?= e($policyLabel) ?>
                </span>
              </td>

              <!-- Status -->
              <td class="px-4 py-4">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 <?= $statusTone ?>">
                  <?= icon($statusIcon, 'h-4 w-4', $disabled ? 'solid' : 'solid') ?>
                  <?= e($statusLabel) ?>
                </span>
              </td>

              <!-- Created -->
              <td class="px-4 py-4 text-slate-600">
                <div class="inline-flex items-center gap-2">
                  <?= icon('calendar-days', 'h-4 w-4 text-slate-400', 'outline') ?>
                  <span class="whitespace-nowrap"><?= fmt_dt($createdAt) ?></span>
                </div>
              </td>

              <!-- Actions -->
              <td class="px-4 py-4">
                <div class="flex items-center justify-end">
                  <form method="post" class="flex flex-wrap gap-2 items-center justify-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= $uid ?>">

                    <?php if ($disabled): ?>
                      <button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition
                               focus:outline-none focus:ring-4 focus:ring-sky-100"
                        name="action" value="enable"
                        title="Enable this user"
                      >
                        <?= icon('check-circle', 'h-4 w-4 text-emerald-700', 'solid') ?>
                        <span class="text-xs font-semibold text-slate-800">Enable</span>
                      </button>
                    <?php else: ?>
                      <button
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition
                               focus:outline-none focus:ring-4 focus:ring-sky-100 disabled:opacity-50 disabled:cursor-not-allowed"
                        name="action" value="disable"
                        title="<?= $isSelf ? 'You cannot disable your own account' : 'Disable this user' ?>"
                        <?php if ($isSelf): ?>disabled<?php endif; ?>
                      >
                        <?= icon('no-symbol', 'h-4 w-4 text-amber-700', 'solid') ?>
                        <span class="text-xs font-semibold text-slate-800">Disable</span>
                      </button>
                    <?php endif; ?>

                    <button
                      class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-900 text-white hover:opacity-95 active:opacity-90 transition
                             focus:outline-none focus:ring-4 focus:ring-sky-100"
                      name="action" value="reset_password"
                      title="Generate a temporary password and force change at next login"
                    >
                      <?= icon('arrow-path', 'h-4 w-4 text-white', 'solid') ?>
                      <span class="text-xs font-semibold">Reset</span>
                    </button>
                  </form>
                </div>

                <div class="mt-2 text-xs text-slate-500 flex items-start gap-2 justify-end">
                  <span class="mt-0.5"><?= icon('lock-closed', 'h-3.5 w-3.5 text-slate-400', 'outline') ?></span>
                  <span class="max-w-xs text-right">Reset generates a temporary password and forces change on next login.</span>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<style>
@keyframes fadeInUp {
  0% { opacity: 0; transform: translateY(10px); }
  100% { opacity: 1; transform: translateY(0); }
}
</style>

<script>
function usersIndex() {
  return {
    init() {
      requestAnimationFrame(() => {
        const root = this.$root;
        if (!root) return;
        root.classList.add('animate-[fadeInUp_.18s_ease-out_1]');
      });
    }
  }
}
</script>
