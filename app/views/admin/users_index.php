<?php
declare(strict_types=1);

/** @var array $users */
/** @var array $admin */

$users = (isset($users) && is_array($users)) ? $users : [];
$q = (string)($q ?? '');
$admin = $admin ?? null;

$usersJson = json_encode(array_map(static function ($u) {
    return [
        'id' => (int)($u['id'] ?? 0),
        'email' => (string)($u['email'] ?? ''),
        'role' => (string)($u['role'] ?? 'user'),
        'must_change_password' => (int)($u['must_change_password'] ?? 0) === 1,
        'disabled_at' => (string)($u['disabled_at'] ?? ''),
        'created_at' => (string)($u['created_at'] ?? ''),
    ];
}, $users), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($usersJson === false) $usersJson = '[]';
?>
<div class="max-w-6xl mx-auto" x-data="usersIndex()" x-init="init(<?= e($admin['id'] ?? 'null') ?>)">
  <script type="application/json" id="users-payload"><?= $usersJson ?></script>

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
        Create users and manage access. Password resets are handled via user requests in the "Reset Requests" section.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('information-circle', 'h-4 w-4 text-slate-400', 'outline') ?>
          <span><span x-text="allUsers.length"></span> user(s)</span>
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
  <div class="mb-4">
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
              x-model.debounce.300ms="search"
              type="search"
              placeholder="Search by email"
              class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                     focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
            >
          </div>
          <p class="text-xs text-slate-500 mt-2">
            Tip: Use a partial email to narrow results quickly.
          </p>
        </div>

        <div class="flex items-center gap-2 sm:justify-end">
          <span class="text-xs text-slate-500 hidden sm:inline">Rows</span>
          <select x-model.number="pageSize" class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
          </select>
        </div>
      </div>
    </div>
  </div>

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

    <div class="divide-y divide-slate-200">
      <template x-if="pagedUsers.length === 0">
        <div class="p-6 text-slate-600 text-sm">
          No users found for your search.
        </div>
      </template>

      <template x-for="u in pagedUsers" :key="u.id">
        <div class="p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4 user-card">
          <!-- Left: User info -->
          <div class="flex items-start gap-4 min-w-0">
            <div class="h-10 w-10 rounded-full bg-slate-100 ring-1 ring-slate-200 flex items-center justify-center shrink-0"
                 :class="{ 'bg-sky-100 ring-sky-200': u.role === 'admin' }">
              <span x-show="u.role === 'admin'"><?= icon('user-circle', 'h-6 w-6 text-slate-500', 'solid') ?></span>
              <span x-show="u.role !== 'admin'"><?= icon('user-circle', 'h-6 w-6 text-slate-500', 'outline') ?></span>
            </div>

            <div class="min-w-0">
              <div class="font-semibold text-slate-900 truncate" x-text="u.email"></div>
              <div class="mt-1 flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs ring-1"
                      :class="u.role === 'admin' ? 'bg-sky-50 text-sky-800 ring-sky-200' : 'bg-slate-50 text-slate-700 ring-slate-200'">
                  <span x-show="u.role === 'admin'"><?= icon('key', 'h-4 w-4', 'solid') ?></span>
                  <span x-show="u.role !== 'admin'"><?= icon('user', 'h-4 w-4', 'outline') ?></span>
                  <span x-text="u.role"></span>
                </span>

                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs ring-1"
                      :class="u.disabled_at ? 'bg-amber-50 text-amber-900 ring-amber-200' : 'bg-emerald-50 text-emerald-800 ring-emerald-200'">
                  <span x-show="u.disabled_at"><?= icon('no-symbol', 'h-4 w-4', 'solid') ?></span>
                  <span x-show="!u.disabled_at"><?= icon('check-circle', 'h-4 w-4', 'solid') ?></span>
                  <span x-text="u.disabled_at ? 'Disabled' : 'Active'"></span>
                </span>

                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs ring-1"
                      :class="u.must_change_password ? 'bg-rose-50 text-rose-800 ring-rose-200' : 'bg-slate-50 text-slate-700 ring-slate-200'">
                  <span x-show="u.must_change_password"><?= icon('exclamation-triangle', 'h-4 w-4', 'solid') ?></span>
                  <span x-show="!u.must_change_password"><?= icon('shield-check', 'h-4 w-4', 'outline') ?></span>
                  <span x-text="u.must_change_password ? 'Must change' : 'Policy OK'"></span>
                </span>

                <template x-if="u.id === adminId">
                  <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs ring-1 bg-white text-slate-600 ring-slate-200">
                    <span><?= icon('sparkles', 'h-3.5 w-3.5 text-sky-600', 'outline') ?></span>
                    You
                  </span>
                </template>
              </div>
            </div>
          </div>

          <!-- Right: Actions -->
          <div class="flex items-center justify-end gap-2 shrink-0">
            <form method="post" class="flex flex-wrap gap-2 items-center justify-end">
              <?= csrf_field() ?>
              <input type="hidden" name="user_id" :value="u.id">

              <template x-if="u.disabled_at">
                <button
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition
                         focus:outline-none focus:ring-4 focus:ring-sky-100"
                  name="action" value="enable"
                  title="Enable this user"
                >
                  <span><?= icon('check-circle', 'h-4 w-4 text-emerald-700', 'solid') ?></span>
                  <span class="text-xs font-semibold text-slate-800">Enable</span>
                </button>
              </template>

              <template x-if="!u.disabled_at">
                <button
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition
                         focus:outline-none focus:ring-4 focus:ring-sky-100 disabled:opacity-50 disabled:cursor-not-allowed"
                  name="action" value="disable"
                  :title="u.id === adminId ? 'You cannot disable your own account' : 'Disable this user'"
                  :disabled="u.id === adminId"
                >
                  <span><?= icon('no-symbol', 'h-4 w-4 text-amber-700', 'solid') ?></span>
                  <span class="text-xs font-semibold text-slate-800">Disable</span>
                </button>
              </template>
            </form>
          </div>
        </div>
      </template>
    </div>

    <!-- Pagination Footer -->
    <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-between gap-3">
      <button type="button" @click="prev()" :disabled="page <= 1"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
        <?= icon('chevron-left', 'h-4 w-4 text-slate-600', 'outline') ?>
        Prev
      </button>

      <div class="text-xs text-slate-500">
        Showing <span class="font-semibold text-slate-700" x-text="showFrom"></span> to <span class="font-semibold text-slate-700" x-text="showTo"></span> of <span class="font-semibold text-slate-700" x-text="filteredCount"></span>
      </div>

      <button type="button" @click="next()" :disabled="page >= totalPages"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
        Next
        <?= icon('chevron-right', 'h-4 w-4 text-slate-600', 'outline') ?>
      </button>
    </div>
  </div>
</div>

<style>
.user-card { animation: fadeInUp .18s ease-out 1; }
@keyframes fadeInUp { 0% { opacity: 0; transform: translateY(8px); } 100% { opacity: 1; transform: translateY(0); } }
</style>

<script>
function usersIndex() {
  return {
    allUsers: [],
    pagedUsers: [],
    search: '',
    adminId: null,
    page: 1,
    pageSize: 10,
    totalPages: 1,
    filteredCount: 0,
    showFrom: 0,
    showTo: 0,

    init(adminId) {
      this.adminId = adminId;
      const payload = document.getElementById('users-payload');
      if (payload) {
        try { this.allUsers = JSON.parse(payload.textContent); }
        catch (e) { this.allUsers = []; }
      }
      this.compute();

      this.$watch('search', () => { this.page = 1; this.compute(); });
      this.$watch('pageSize', () => { this.page = 1; this.compute(); });
      this.$watch('page', () => this.compute());
    },
    compute() {
      const q = this.search.trim().toLowerCase();
      let filtered = this.allUsers;

      if (q) {
        filtered = this.allUsers.filter(u => u.email.toLowerCase().includes(q));
      }

      this.filteredCount = filtered.length;
      this.totalPages = Math.max(1, Math.ceil(this.filteredCount / this.pageSize));

      if (this.page > this.totalPages) this.page = this.totalPages;
      if (this.page < 1) this.page = 1;

      const start = (this.page - 1) * this.pageSize;
      const end = start + this.pageSize;
      this.pagedUsers = filtered.slice(start, end);

      this.showFrom = this.filteredCount === 0 ? 0 : start + 1;
      this.showTo = Math.min(end, this.filteredCount);
    },
    prev() { if (this.page > 1) this.page--; },
    next() { if (this.page < this.totalPages) this.page++; }
  }
}
</script>
