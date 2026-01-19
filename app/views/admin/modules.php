<?php
declare(strict_types=1);

/**
 * app/views/admin/modules_index.php (drop-in replacement)
 *
 * Expected:
 * - $edit    : array|null
 * - $levels  : array
 * - $modules : array
 *
 * Assumes global helpers:
 * - csrf_field()
 * - e()
 * - icon($name, $class='h-5 w-5', $variant='outline')   // optional
 */

$edit    = $edit ?? null;
$levels  = (isset($levels)  && is_array($levels))  ? $levels  : [];
$modules = (isset($modules) && is_array($modules)) ? $modules : [];

// Minimal payload for pagination/search
$modulesUi = array_map(static function ($m) {
  return [
    'id'         => (int)($m['id'] ?? 0),
    'level_code' => (string)($m['level_code'] ?? ''),
    'code'       => (string)($m['code'] ?? ''),
    'name'       => (string)($m['name'] ?? ''),
  ];
}, $modules);

$modulesJson = json_encode(
  $modulesUi,
  JSON_UNESCAPED_SLASHES
  | JSON_UNESCAPED_UNICODE
  | JSON_HEX_TAG
  | JSON_HEX_AMP
  | JSON_HEX_APOS
  | JSON_HEX_QUOT
);
if ($modulesJson === false) $modulesJson = '[]';
?>

<div class="max-w-6xl mx-auto" x-data="modulesAdmin()" x-init="init()">
  <script type="application/json" id="modulesPayload"><?= $modulesJson ?></script>

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?= icon('squares-2x2', 'h-5 w-5 text-sky-700', 'solid') ?>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Modules</h1>
      </div>

      <p class="text-sm text-slate-600 mt-2">
        Linked to levels. Example: <span class="font-semibold text-slate-800">GEM 201</span> under level <span class="font-semibold text-slate-800">200</span>.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('circle-stack', 'h-4 w-4 text-slate-400', 'outline') ?>
          Total: <span class="font-semibold text-slate-700" x-text="all.length"></span>
        </span>
      </div>
    </div>

    <!-- Quick nav -->
    <div class="flex flex-wrap gap-2 sm:justify-end">
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_levels">
        <?= icon('academic-cap', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Levels</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_subjects">
        <?= icon('bookmark-square', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Subjects</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_topics">
        <?= icon('tag', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Topics</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_taxonomy_import">
        <?= icon('arrow-up-tray', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">CSV Import</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_users">
        <?= icon('users', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Users</span>
      </a>
    </div>
  </div>

  <div class="grid lg:grid-cols-3 gap-5">

    <!-- Left: Create/Edit -->
    <div class="lg:col-span-1">
      <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
          <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
            <?= icon($edit ? 'pencil-square' : 'plus-circle', 'h-4 w-4 text-slate-600', 'outline') ?>
            <span><?= $edit ? 'Edit module' : 'Create module' ?></span>
          </div>

          <?php if ($edit): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 ring-amber-200 bg-amber-50 text-amber-900">
              <?= icon('exclamation-triangle', 'h-4 w-4', 'solid') ?>
              Editing
            </span>
          <?php endif; ?>
        </div>

        <div class="p-5">
          <form method="post" action="/public/index.php?r=admin_modules" class="space-y-4" @submit="saving=true">
            <?= csrf_field() ?>

            <?php if ($edit): ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <?php else: ?>
              <input type="hidden" name="action" value="create">
            <?php endif; ?>

            <!-- Level select -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Level</label>

              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?= icon('academic-cap', 'h-5 w-5 text-slate-400', 'outline') ?>
                </div>

                <select name="level_id" required
                        class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                               focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
                  <option value="">Select level</option>
                  <?php foreach ($levels as $l): ?>
                    <option value="<?= (int)$l['id'] ?>"
                      <?= ($edit && (int)$edit['level_id'] === (int)$l['id']) ? 'selected' : '' ?>>
                      <?= e($l['code']) ?><?= !empty($l['name']) ? (' , ' . e((string)$l['name'])) : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <p class="text-xs text-slate-500">Modules must belong to a level.</p>
            </div>

            <!-- Module code -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Module code</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?= icon('hashtag', 'h-5 w-5 text-slate-400', 'outline') ?>
                </div>
                <input name="code" required value="<?= e($edit['code'] ?? '') ?>"
                       placeholder="e.g., GEM 201"
                       class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
              </div>
            </div>

            <!-- Name -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Name (optional)</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?= icon('pencil', 'h-5 w-5 text-slate-400', 'outline') ?>
                </div>
                <input name="name" value="<?= e($edit['name'] ?? '') ?>"
                       placeholder="e.g., General Mathematics"
                       class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
              </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
              <button type="submit"
                      class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold
                             hover:opacity-95 active:opacity-90 transition disabled:opacity-60
                             focus:outline-none focus:ring-4 focus:ring-sky-100"
                      :disabled="saving">
                <span x-show="!saving" class="inline-flex items-center gap-2">
                  <?= icon('check', 'h-4 w-4 text-white', 'solid') ?>
                  <?= $edit ? 'Save changes' : 'Create' ?>
                </span>
                <span x-show="saving" x-cloak class="inline-flex items-center gap-2">
                  <span class="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
                  Saving...
                </span>
              </button>

              <?php if ($edit): ?>
                <a class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
                   href="/public/index.php?r=admin_modules">
                  <?= icon('x-mark', 'h-4 w-4 text-slate-600', 'outline') ?>
                  Cancel
                </a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Right: List -->
    <div class="lg:col-span-2">
      <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">

        <!-- Toolbar -->
        <div class="px-5 py-4 border-b border-slate-200 bg-white">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
              <?= icon('list-bullet', 'h-4 w-4 text-slate-600', 'outline') ?>
              Existing modules
            </div>

            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
              <!-- Search -->
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?= icon('magnifying-glass', 'h-4 w-4 text-slate-400', 'outline') ?>
                </div>
                <input type="text" x-model.debounce.250ms="search"
                       class="w-full sm:w-80 rounded-xl ring-1 ring-slate-200 bg-white pl-9 pr-3 py-2 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                       placeholder="Search level, module code, or name...">
              </div>

              <!-- Page size -->
              <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 hidden sm:inline">Rows</span>
                <select x-model.number="pageSize"
                        class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-sm
                               focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
                  <option value="10">10</option>
                  <option value="20" selected>20</option>
                  <option value="50">50</option>
                </select>
              </div>
            </div>
          </div>

          <div class="mt-3 text-xs text-slate-500 flex items-center justify-between gap-3">
            <span>
              Showing <span class="font-semibold text-slate-700" x-text="showFrom"></span>
              to <span class="font-semibold text-slate-700" x-text="showTo"></span>
              of <span class="font-semibold text-slate-700" x-text="filteredCount"></span>
            </span>

            <button type="button" @click="search=''; page=1;"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 hover:text-slate-900 transition" x-show="search.length > 0" x-cloak>
              <?= icon('x-circle', 'h-4 w-4', 'outline') ?>
              Clear search
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr class="text-left text-xs font-semibold text-slate-600">
                <th class="p-3">Level</th>
                <th class="p-3">Code</th>
                <th class="p-3">Name</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <template x-if="paged.length === 0">
                <tr><td class="p-4 text-slate-600" colspan="4">No modules found.</td></tr>
              </template>
              <template x-for="m in paged" :key="m.id">
                <tr class="hover:bg-slate-50/60 transition md-enter">
                  <td class="p-3 text-slate-700" x-text="m.level_code"></td>
                  <td class="p-3">
                    <div class="font-medium text-slate-900" x-text="m.code"></div>
                  </td>
                  <td class="p-3 text-slate-700" x-text="m.name"></td>
                  <td class="p-3">
                    <div class="flex justify-end gap-2">
                      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
                         :href="'/public/index.php?r=admin_modules&edit_id=' + m.id">
                        <?= icon('pencil-square', 'h-4 w-4 text-slate-600', 'outline') ?>
                        <span class="text-sm font-medium text-slate-800">Edit</span>
                      </a>
                      <form method="post" action="/public/index.php?r=admin_modules" onsubmit="return confirm('Delete this module? Only allowed if it has no subjects.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" :value="m.id">
                        <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-rose-50 hover:ring-rose-200 transition">
                          <?= icon('trash', 'h-4 w-4 text-slate-600', 'outline') ?>
                          <span class="text-sm font-medium text-slate-800">Delete</span>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-between gap-3">
          <button type="button" @click="prev()" :disabled="page <= 1"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
            <?= icon('chevron-left', 'h-4 w-4 text-slate-600', 'outline') ?>
            Prev
          </button>

          <div class="text-xs text-slate-500">
            Page <span class="font-semibold text-slate-700" x-text="page"></span>
            of <span class="font-semibold text-slate-700" x-text="totalPages"></span>
          </div>

          <button type="button" @click="next()" :disabled="page >= totalPages"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
            Next
            <?= icon('chevron-right', 'h-4 w-4 text-slate-600', 'outline') ?>
          </button>
        </div>

      </div>
    </div>

  </div>
</div>

<style>
.md-enter { animation: mdFadeUp .14s ease-out 1; }
@keyframes mdFadeUp { from { opacity: 0; transform: translateY(8px);} to { opacity: 1; transform: translateY(0);} }
</style>

<script>
function modulesAdmin() {
  const payloadEl = document.getElementById('modulesPayload');
  let parsed = [];
  try { parsed = JSON.parse(payloadEl?.textContent || '[]'); } catch (e) { parsed = []; }

  return {
    all: parsed,
    search: '',
    pageSize: 20,
    page: 1,
    totalPages: 1,
    filteredCount: 0,
    showFrom: 0,
    showTo: 0,
    paged: [],
    saving: false,

    init() {
      // initialize pageSize from the select if present
      const ps = Number(this.pageSize) || 20;
      this.pageSize = ps;
      this.compute();

      // watchers for reactive updates
      if (this.$watch) {
        this.$watch('search', () => { this.page = 1; this.compute(); });
        this.$watch('pageSize', () => { this.page = 1; this.compute(); });
        this.$watch('page', () => { this.compute(); });
      }
    },

    compute() {
      const term = String(this.search || '').trim().toLowerCase();
      const filtered = !term ? this.all : this.all.filter(m => {
        const level = String(m.level_code || '').toLowerCase();
        const code  = String(m.code || '').toLowerCase();
        const name  = String(m.name || '').toLowerCase();
        return level.includes(term) || code.includes(term) || name.includes(term);
      });

      this.filteredCount = filtered.length;
      const pages = Math.max(1, Math.ceil(filtered.length / (Number(this.pageSize) || 20)));
      this.totalPages = pages;
      if (this.page > pages) this.page = pages;
      if (this.page < 1) this.page = 1;

      const start = (this.page - 1) * (Number(this.pageSize) || 20);
      this.showFrom = filtered.length === 0 ? 0 : start + 1;
      this.showTo = filtered.length === 0 ? 0 : Math.min(start + Number(this.pageSize), filtered.length);
      this.paged = filtered.slice(start, start + Number(this.pageSize));
    },

    prev() { if (this.page > 1) { this.page--; this.compute(); } },
    next() { if (this.page < this.totalPages) { this.page++; this.compute(); } },
  };
}
</script>
