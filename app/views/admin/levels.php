<?php
declare(strict_types=1);

/** @var array|null $edit */
/** @var array $levels */


function has_icon_levels(): bool { return function_exists('icon'); }

// Minimal payload for pagination/search
$levelsUi = array_map(static function ($l) {
  return [
    'id'   => (int)($l['id'] ?? 0),
    'code' => (string)($l['code'] ?? ''),
    'name' => (string)($l['name'] ?? ''),
  ];
}, $levels);

$levelsJson = json_encode(
  $levelsUi,
  JSON_UNESCAPED_SLASHES
  | JSON_UNESCAPED_UNICODE
  | JSON_HEX_TAG
  | JSON_HEX_AMP
  | JSON_HEX_APOS
  | JSON_HEX_QUOT
);
if ($levelsJson === false) $levelsJson = '[]';
?>

<div class="max-w-6xl mx-auto" x-data="levelsAdmin()" x-init="init()">
  <script type="application/json" id="levelsPayload"><?= $levelsJson ?></script>

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?php if (has_icon_levels()): ?>
            <?= icon('academic-cap', 'h-5 w-5 text-sky-700', 'solid') ?>
          <?php else: ?>
            <span class="text-sky-700 font-semibold">L</span>
          <?php endif; ?>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Levels</h1>
      </div>

      <p class="text-sm text-slate-600 mt-2">
        Top of the taxonomy. Example: <span class="font-semibold text-slate-800">200</span>.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?php if (has_icon_levels()): ?><?= icon('circle-stack', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
          Total: <span class="font-semibold text-slate-700" x-text="all.length"></span>
        </span>
      </div>
    </div>

    <!-- Quick nav -->
    <div class="flex flex-wrap gap-2 sm:justify-end">
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_modules">
        <?php if (has_icon_levels()): ?><?= icon('squares-2x2', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">Modules</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_subjects">
        <?php if (has_icon_levels()): ?><?= icon('bookmark-square', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">Subjects</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_topics">
        <?php if (has_icon_levels()): ?><?= icon('tag', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">Topics</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_taxonomy_import">
        <?php if (has_icon_levels()): ?><?= icon('arrow-up-tray', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">CSV Import</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_users">
        <?php if (has_icon_levels()): ?><?= icon('users', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
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
            <?php if ($edit): ?>
              <?php if (has_icon_levels()): ?><?= icon('pencil-square', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
              <span>Edit level</span>
            <?php else: ?>
              <?php if (has_icon_levels()): ?><?= icon('plus-circle', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
              <span>Create level</span>
            <?php endif; ?>
          </div>

          <?php if ($edit): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 ring-amber-200 bg-amber-50 text-amber-900">
              <?php if (has_icon_levels()): ?><?= icon('exclamation-triangle', 'h-4 w-4', 'solid') ?><?php endif; ?>
              Editing
            </span>
          <?php endif; ?>
        </div>

        <div class="p-5">
          <form method="post" action="/public/index.php?r=admin_levels" class="space-y-4" @submit="saving=true">
            <?= csrf_field() ?>

            <?php if ($edit): ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <?php else: ?>
              <input type="hidden" name="action" value="create">
            <?php endif; ?>

            <!-- Code -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Code</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?php if (has_icon_levels()): ?><?= icon('hashtag', 'h-5 w-5 text-slate-400', 'outline') ?><?php endif; ?>
                </div>
                <input name="code" required value="<?= e($edit['code'] ?? '') ?>"
                       placeholder="e.g., 200"
                       class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
              </div>
              <p class="text-xs text-slate-500">
                Use short numeric codes to keep the taxonomy easy to scan.
              </p>
            </div>

            <!-- Name -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Name (optional)</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?php if (has_icon_levels()): ?><?= icon('pencil', 'h-5 w-5 text-slate-400', 'outline') ?><?php endif; ?>
                </div>
                <input name="name" value="<?= e($edit['name'] ?? '') ?>"
                       placeholder="e.g., Junior High"
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
                  <?php if (has_icon_levels()): ?><?= icon('check', 'h-4 w-4 text-white', 'solid') ?><?php endif; ?>
                  <?= $edit ? 'Save changes' : 'Create' ?>
                </span>
                <span x-show="saving" x-cloak class="inline-flex items-center gap-2">
                  <span class="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
                  Saving...
                </span>
              </button>

              <?php if ($edit): ?>
                <a class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
                   href="/public/index.php?r=admin_levels">
                  <?php if (has_icon_levels()): ?><?= icon('x-mark', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
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
              <?php if (has_icon_levels()): ?><?= icon('list-bullet', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
              Existing levels
            </div>

            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
              <!-- Search -->
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?php if (has_icon_levels()): ?><?= icon('magnifying-glass', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
                </div>
                <input type="text" x-model.debounce.250ms="search"
                       class="w-full sm:w-72 rounded-xl ring-1 ring-slate-200 bg-white pl-9 pr-3 py-2 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                       placeholder="Search code or name...">
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
              <?php if (has_icon_levels()): ?><?= icon('x-circle', 'h-4 w-4', 'outline') ?><?php endif; ?>
              Clear search
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
              <tr class="text-left text-xs font-semibold text-slate-600">
                <th class="p-3">Code</th>
                <th class="p-3">Name</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <template x-if="paged.length === 0">
                <tr>
                  <td class="p-4 text-slate-600" colspan="3">No levels found.</td>
                </tr>
              </template>
              <template x-for="l in paged" :key="l.id">
                <tr class="hover:bg-slate-50/60 transition lv-enter">
                  <td class="p-3">
                    <div class="font-medium text-slate-900" x-text="l.code"></div>
                  </td>
                  <td class="p-3 text-slate-700" x-text="l.name"></td>
                  <td class="p-3">
                    <div class="flex justify-end gap-2">
                      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
                         :href="'/public/index.php?r=admin_levels&edit_id=' + l.id">
                        <?= icon('pencil-square', 'h-4 w-4 text-slate-600', 'outline') ?>
                        <span class="text-sm font-medium text-slate-800">Edit</span>
                      </a>
                      <form method="post" action="/public/index.php?r=admin_levels" onsubmit="return confirm('Delete this level? Only allowed if it has no modules.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" :value="l.id">
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
            <?php if (has_icon_levels()): ?><?= icon('chevron-left', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
            Prev
          </button>

          <div class="text-xs text-slate-500">
            Page <span class="font-semibold text-slate-700" x-text="page"></span>
            of <span class="font-semibold text-slate-700" x-text="totalPages"></span>
          </div>

          <button type="button" @click="next()" :disabled="page >= totalPages"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
            Next
            <?php if (has_icon_levels()): ?><?= icon('chevron-right', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
          </button>
        </div>

      </div>
    </div>

  </div>
</div>

<style>
.lv-enter { animation: lvFadeUp .14s ease-out 1; }
@keyframes lvFadeUp { from { opacity: 0; transform: translateY(8px);} to { opacity: 1; transform: translateY(0);} }
</style>

<script>
function levelsAdmin() {
  return {
    all: [],
    search: '',
    page: 1,
    pageSize: 20,
    saving: false,

    // Derived state (mutable)
    filteredCount: 0,
    totalPages: 1,
    paged: [],
    showFrom: 0,
    showTo: 0,

    init() {
      const el = document.getElementById('levelsPayload');
      if (el) {
        try { this.all = JSON.parse(el.textContent || '[]'); } catch (e) { this.all = []; }
      }

      this.$watch('search', () => { this.page = 1; this.recalc(); });
      this.$watch('pageSize', () => { this.page = 1; this.recalc(); });

      this.recalc();

      if (this.$root) this.$root.classList.add('animate-[fadeInUp_.18s_ease-out_1]');
    },

    filteredArray() {
      const q = this.search.trim().toLowerCase();
      if (!q) return this.all;
      return this.all.filter(l => {
        return String(l.code).toLowerCase().includes(q) || String(l.name).toLowerCase().includes(q);
      });
    },

    recalc() {
      const arr = this.filteredArray();
      this.filteredCount = arr.length;
      this.totalPages = Math.max(1, Math.ceil(this.filteredCount / this.pageSize));

      if (this.page > this.totalPages) this.page = this.totalPages;
      if (this.page < 1) this.page = 1;

      const start = (this.page - 1) * this.pageSize;
      this.paged = arr.slice(start, start + this.pageSize);

      this.showFrom = (this.filteredCount === 0) ? 0 : (start + 1);
      this.showTo = Math.min(start + this.pageSize, this.filteredCount);
    },

    next() { if (this.page < this.totalPages) this.page++; this.recalc(); },
    prev() { if (this.page > 1) this.page--; this.recalc(); },
  }
}
</script>
