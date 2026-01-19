<?php
/**
 * app/views/admin/topics_index.php  (DROP-IN UPDATED, NO GETTERS)
 *
 * Expected:
 *  - $edit : array|null
 *  - $subjects : array
 *  - $topics : array
 *
 * Requires helpers:
 *  - csrf_field()
 *  - e()
 *  - icon($name, $class = 'h-5 w-5', $variant = 'outline')
 */

$edit = $edit ?? null;
$subjects = (isset($subjects) && is_array($subjects)) ? $subjects : [];
$topics   = (isset($topics) && is_array($topics)) ? $topics : [];

// Minimal payload for client-side pagination/search
$topicsUi = array_map(static function ($t) {
  return [
    'id' => (int)($t['id'] ?? 0),
    'level_code' => (string)($t['level_code'] ?? ''),
    'module_code' => (string)($t['module_code'] ?? ''),
    'subject_name' => (string)($t['subject_name'] ?? ''),
    'name' => (string)($t['name'] ?? ''),
  ];
}, $topics);

// IMPORTANT: Do NOT htmlspecialchars() JSON inside <script type="application/json">.
// Use JSON_HEX_* to keep it safe against </script> edge cases.
$topicsJson = json_encode(
  $topicsUi,
  JSON_UNESCAPED_SLASHES
  | JSON_UNESCAPED_UNICODE
  | JSON_HEX_TAG
  | JSON_HEX_AMP
  | JSON_HEX_APOS
  | JSON_HEX_QUOT
);
if ($topicsJson === false) $topicsJson = '[]';
?>

<div class="max-w-6xl mx-auto" x-data="topicsAdmin()" x-init="init()">
  <script type="application/json" id="topicsPayload"><?= $topicsJson ?></script>

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?= icon('tag', 'h-5 w-5 text-sky-700', 'solid') ?>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Topics</h1>
      </div>

      <p class="text-sm text-slate-600 mt-2">
        Linked to subjects. These are what users will multi-select for quizzes.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('information-circle', 'h-4 w-4 text-slate-400', 'outline') ?>
          Organize questions by topic for better quiz selection
        </span>

        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('circle-stack', 'h-4 w-4 text-slate-400', 'outline') ?>
          Total:
          <span class="font-semibold text-slate-700" x-text="all.length"></span>
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
         href="/public/index.php?r=admin_modules">
        <?= icon('squares-2x2', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Modules</span>
      </a>

      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_subjects">
        <?= icon('bookmark-square', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Subjects</span>
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
            <?php if ($edit): ?>
              <?= icon('pencil-square', 'h-4 w-4 text-slate-600', 'outline') ?>
              <span>Edit topic</span>
            <?php else: ?>
              <?= icon('plus-circle', 'h-4 w-4 text-slate-600', 'outline') ?>
              <span>Create topic</span>
            <?php endif; ?>
          </div>

          <?php if ($edit): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 ring-amber-200 bg-amber-50 text-amber-900">
              <?= icon('exclamation-triangle', 'h-4 w-4', 'solid') ?>
              Editing
            </span>
          <?php endif; ?>
        </div>

        <div class="p-5">
          <form method="post" action="/public/index.php?r=admin_topics" class="space-y-4" @submit="saving=true">
            <?= csrf_field() ?>

            <?php if ($edit): ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <?php else: ?>
              <input type="hidden" name="action" value="create">
            <?php endif; ?>

            <!-- Subject -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Subject</label>

              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?= icon('bookmark-square', 'h-5 w-5 text-slate-400', 'outline') ?>
                </div>

                <select name="subject_id" required
                        class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                               focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
                  <option value="">Select subject</option>
                  <?php foreach ($subjects as $s): ?>
                    <option value="<?= (int)$s['id'] ?>"
                      <?= ($edit && (int)$edit['subject_id'] === (int)$s['id']) ? 'selected' : '' ?>>
                      <?= e($s['level_code']) ?> , <?= e($s['module_code']) ?> , <?= e($s['subject_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <p class="text-xs text-slate-500 flex items-start gap-2">
                <span class="mt-0.5"><?= icon('information-circle', 'h-4 w-4 text-slate-400', 'outline') ?></span>
                <span>Topics must be tied to a subject path for clean quiz filtering.</span>
              </p>
            </div>

            <!-- Topic name -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Topic name</label>

              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?= icon('tag', 'h-5 w-5 text-slate-400', 'outline') ?>
                </div>

                <input name="name" required value="<?= e($edit['name'] ?? '') ?>"
                       placeholder="e.g., Fractions, Customer Service, Pharmacology Basics"
                       class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
              </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
              <button type="submit"
                      class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold
                             hover:opacity-95 active:opacity-90 transition
                             focus:outline-none focus:ring-4 focus:ring-sky-100 disabled:opacity-60 disabled:cursor-not-allowed"
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
                   href="/public/index.php?r=admin_topics">
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
              <span>Existing topics</span>
            </div>

            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
              <!-- Search -->
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?= icon('magnifying-glass', 'h-4 w-4 text-slate-400', 'outline') ?>
                </div>
                <input type="text"
                       class="w-full sm:w-72 rounded-xl ring-1 ring-slate-200 bg-white pl-9 pr-3 py-2 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                       placeholder="Search topic or path..."
                       x-model.debounce.250ms="search">
              </div>

              <!-- Rows -->
              <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 hidden sm:inline">Rows</span>
                <select class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-sm
                               focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                        x-model.number="pageSize">
                  <option value="10">10</option>
                  <option value="20">20</option>
                  <option value="50">50</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Showing -->
          <div class="mt-3 text-xs text-slate-500 flex items-center justify-between gap-3">
            <span>
              Showing <span class="font-semibold text-slate-700" x-text="showFrom"></span>
              to <span class="font-semibold text-slate-700" x-text="showTo"></span>
              of <span class="font-semibold text-slate-700" x-text="filteredCount"></span>
            </span>

            <button type="button"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 hover:text-slate-900 transition"
                    x-show="search.length > 0" x-cloak
                    @click="search=''; page=1; recalc();">
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
                <th class="p-3">Path</th>
                <th class="p-3">Topic</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-200">
              <template x-if="paged.length === 0">
                <tr>
                  <td class="p-4 text-slate-600" colspan="3">
                    <div class="flex items-center gap-2">
                      <?= icon('information-circle', 'h-5 w-5 text-slate-400', 'outline') ?>
                      <span>No topics found.</span>
                    </div>
                  </td>
                </tr>
              </template>

              <template x-for="t in paged" :key="t.id">
                <tr class="hover:bg-slate-50/60 transition">
                  <td class="p-3 text-slate-700">
                    <div class="inline-flex items-center gap-2">
                      <?= icon('map', 'h-4 w-4 text-slate-400', 'outline') ?>
                      <span class="truncate" x-text="t.level_code + ' , ' + t.module_code + ' , ' + t.subject_name"></span>
                    </div>
                  </td>

                  <td class="p-3">
                    <div class="inline-flex items-center gap-2">
                      <?= icon('tag', 'h-4 w-4 text-slate-400', 'outline') ?>
                      <span class="font-medium text-slate-900" x-text="t.name"></span>
                    </div>
                  </td>

                  <td class="p-3">
                    <div class="flex justify-end gap-2">
                      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
                         :href="'/public/index.php?r=admin_topics&edit_id=' + t.id"
                         title="Edit topic">
                        <?= icon('pencil-square', 'h-4 w-4 text-slate-600', 'outline') ?>
                        <span class="text-sm font-medium text-slate-800 hidden sm:inline">Edit</span>
                      </a>

                      <form method="post"
                            action="/public/index.php?r=admin_topics"
                            onsubmit="return confirm('Delete this topic? This may break question mappings in Phase 4.');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" :value="t.id">

                        <button type="submit"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-rose-50 hover:ring-rose-200 transition"
                                title="Delete topic">
                          <?= icon('trash', 'h-4 w-4 text-slate-600', 'outline') ?>
                          <span class="text-sm font-medium text-slate-800 hidden sm:inline">Delete</span>
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
          <button type="button"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
                  @click="prev()"
                  :disabled="page <= 1">
            <?= icon('chevron-left', 'h-4 w-4 text-slate-600', 'outline') ?>
            Prev
          </button>

          <div class="text-xs text-slate-500">
            Page <span class="font-semibold text-slate-700" x-text="page"></span>
            of <span class="font-semibold text-slate-700" x-text="totalPages"></span>
          </div>

          <button type="button"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed"
                  @click="next()"
                  :disabled="page >= totalPages">
            Next
            <?= icon('chevron-right', 'h-4 w-4 text-slate-600', 'outline') ?>
          </button>
        </div>

      </div>
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
function topicsAdmin() {
  return {
    all: [],
    search: '',
    page: 1,
    pageSize: 20,
    saving: false,

    // Derived (MUTABLE, NO GETTERS)
    filteredCount: 0,
    totalPages: 1,
    paged: [],
    showFrom: 0,
    showTo: 0,

    init() {
      const el = document.getElementById('topicsPayload');
      if (el) {
        try {
          this.all = JSON.parse(el.textContent || '[]');
        } catch (e) {
          this.all = [];
        }
      }

      // Watches to keep derived state consistent
      this.$watch('search', () => {
        this.page = 1;
        this.recalc();
      });

      this.$watch('pageSize', () => {
        this.page = 1;
        this.recalc();
      });

      this.$watch('page', () => {
        this.recalc();
      });

      this.recalc();

      requestAnimationFrame(() => {
        if (this.$root) this.$root.classList.add('animate-[fadeInUp_.18s_ease-out_1]');
      });
    },

    filteredArray() {
      const q = String(this.search || '').trim().toLowerCase();
      if (!q) return this.all;

      return this.all.filter(t => {
        const path = (String(t.level_code || '') + ' ' + String(t.module_code || '') + ' ' + String(t.subject_name || '')).toLowerCase();
        const name = String(t.name || '').toLowerCase();
        return path.includes(q) || name.includes(q);
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

    next() {
      if (this.page < this.totalPages) {
        this.page++; // The watcher on 'page' will trigger recalc()
      }
    },

    prev() {
      if (this.page > 1) {
        this.page--; // The watcher on 'page' will trigger recalc()
      }
    },
  }
}
</script>
