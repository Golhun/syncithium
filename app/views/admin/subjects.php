<?php
declare(strict_types=1);

/**
 * app/views/admin/subjects_index.php (drop-in replacement)
 *
 * Expected:
 * - $edit     : array|null
 * - $modules  : array
 * - $subjects : array
 *
 * Assumes global helpers:
 * - csrf_field()
 * - e()
 * - icon($name, $class='h-5 w-5', $variant='outline')   // optional
 */

$edit     = $edit ?? null;
$modules  = (isset($modules)  && is_array($modules))  ? $modules  : [];
$subjects = (isset($subjects) && is_array($subjects)) ? $subjects : [];

function has_icon_subjects(): bool { return function_exists('icon'); }

// Minimal payload for list UI (search + pagination)
$subjectsUi = array_map(static function ($s) {
  return [
    'id'          => (int)($s['id'] ?? 0),
    'level_code'  => (string)($s['level_code'] ?? ''),
    'module_code' => (string)($s['module_code'] ?? ''),
    'name'        => (string)($s['name'] ?? ''),
  ];
}, $subjects);

$subjectsJson = json_encode(
  $subjectsUi,
  JSON_UNESCAPED_SLASHES
  | JSON_UNESCAPED_UNICODE
  | JSON_HEX_TAG
  | JSON_HEX_AMP
  | JSON_HEX_APOS
  | JSON_HEX_QUOT
);
if ($subjectsJson === false) $subjectsJson = '[]';
?>

<div class="max-w-6xl mx-auto" id="subjectsRoot">
  <script type="application/json" id="subjectsPayload"><?= $subjectsJson ?></script>

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?php if (has_icon_subjects()): ?>
            <?= icon('bookmark-square', 'h-5 w-5 text-sky-700', 'solid') ?>
          <?php else: ?>
            <span class="text-sky-700 font-semibold">S</span>
          <?php endif; ?>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Subjects</h1>
      </div>

      <p class="text-sm text-slate-600 mt-2">Linked to modules.</p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?php if (has_icon_subjects()): ?><?= icon('circle-stack', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
          Total: <span class="font-semibold text-slate-700" id="sbTotal">0</span>
        </span>
      </div>
    </div>

    <!-- Quick nav -->
    <div class="flex flex-wrap gap-2 sm:justify-end">
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_levels">
        <?php if (has_icon_subjects()): ?><?= icon('academic-cap', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">Levels</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_modules">
        <?php if (has_icon_subjects()): ?><?= icon('squares-2x2', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">Modules</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_topics">
        <?php if (has_icon_subjects()): ?><?= icon('tag', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">Topics</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_taxonomy_import">
        <?php if (has_icon_subjects()): ?><?= icon('arrow-up-tray', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">CSV Import</span>
      </a>
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_users">
        <?php if (has_icon_subjects()): ?><?= icon('users', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
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
              <?php if (has_icon_subjects()): ?><?= icon('pencil-square', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
              <span>Edit subject</span>
            <?php else: ?>
              <?php if (has_icon_subjects()): ?><?= icon('plus-circle', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
              <span>Create subject</span>
            <?php endif; ?>
          </div>

          <?php if ($edit): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 ring-amber-200 bg-amber-50 text-amber-900">
              <?php if (has_icon_subjects()): ?><?= icon('exclamation-triangle', 'h-4 w-4', 'solid') ?><?php endif; ?>
              Editing
            </span>
          <?php endif; ?>
        </div>

        <div class="p-5">
          <form method="post" action="/public/index.php?r=admin_subjects" class="space-y-4">
            <?= csrf_field() ?>

            <?php if ($edit): ?>
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
            <?php else: ?>
              <input type="hidden" name="action" value="create">
            <?php endif; ?>

            <!-- Module select -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Module</label>

              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?php if (has_icon_subjects()): ?><?= icon('squares-2x2', 'h-5 w-5 text-slate-400', 'outline') ?><?php endif; ?>
                </div>

                <select name="module_id" required
                        class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                               focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
                  <option value="">Select module</option>
                  <?php foreach ($modules as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"
                      <?= ($edit && (int)$edit['module_id'] === (int)$m['id']) ? 'selected' : '' ?>>
                      <?= e((string)$m['level_code']) ?> , <?= e((string)$m['code']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <p class="text-xs text-slate-500">
                Choose the module this subject belongs to, for clean taxonomy linking.
              </p>
            </div>

            <!-- Subject name -->
            <div class="space-y-1.5">
              <label class="block text-sm font-medium text-slate-700">Subject name</label>
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?php if (has_icon_subjects()): ?><?= icon('bookmark-square', 'h-5 w-5 text-slate-400', 'outline') ?><?php endif; ?>
                </div>
                <input name="name" required value="<?= e($edit['name'] ?? '') ?>"
                       placeholder="e.g., Mathematics, Customer Service, Biology"
                       class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
              </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
              <button type="submit"
                      class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold
                             hover:opacity-95 active:opacity-90 transition
                             focus:outline-none focus:ring-4 focus:ring-sky-100">
                <?php if (has_icon_subjects()): ?><?= icon('check', 'h-4 w-4 text-white', 'solid') ?><?php endif; ?>
                <?= $edit ? 'Save changes' : 'Create' ?>
              </button>

              <?php if ($edit): ?>
                <a class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
                   href="/public/index.php?r=admin_subjects">
                  <?php if (has_icon_subjects()): ?><?= icon('x-mark', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
                  Cancel
                </a>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Right: List (search + pagination) -->
    <div class="lg:col-span-2">
      <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">

        <!-- Toolbar -->
        <div class="px-5 py-4 border-b border-slate-200 bg-white">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
              <?php if (has_icon_subjects()): ?><?= icon('list-bullet', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
              Existing subjects
            </div>

            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
              <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                  <?php if (has_icon_subjects()): ?><?= icon('magnifying-glass', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
                </div>
                <input id="sbSearch" type="text"
                       class="w-full sm:w-80 rounded-xl ring-1 ring-slate-200 bg-white pl-9 pr-3 py-2 text-sm
                              focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                       placeholder="Search level, module, or subject...">
              </div>

              <div class="flex items-center gap-2">
                <span class="text-xs text-slate-500 hidden sm:inline">Rows</span>
                <select id="sbPageSize"
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
              Showing <span class="font-semibold text-slate-700" id="sbShowFrom">0</span>
              to <span class="font-semibold text-slate-700" id="sbShowTo">0</span>
              of <span class="font-semibold text-slate-700" id="sbFiltered">0</span>
            </span>

            <button type="button" id="sbClear"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 hover:text-slate-900 transition hidden">
              <?php if (has_icon_subjects()): ?><?= icon('x-circle', 'h-4 w-4', 'outline') ?><?php endif; ?>
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
                <th class="p-3">Module</th>
                <th class="p-3">Subject</th>
                <th class="p-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody id="sbTbody" class="divide-y divide-slate-200"></tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-between gap-3">
          <button type="button" id="sbPrev"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
            <?php if (has_icon_subjects()): ?><?= icon('chevron-left', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
            Prev
          </button>

          <div class="text-xs text-slate-500">
            Page <span class="font-semibold text-slate-700" id="sbPage">1</span>
            of <span class="font-semibold text-slate-700" id="sbPages">1</span>
          </div>

          <button type="button" id="sbNext"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
            Next
            <?php if (has_icon_subjects()): ?><?= icon('chevron-right', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
          </button>
        </div>

      </div>
    </div>

  </div>
</div>

<style>
.sb-enter { animation: sbFadeUp .14s ease-out 1; }
@keyframes sbFadeUp { from { opacity: 0; transform: translateY(8px);} to { opacity: 1; transform: translateY(0);} }
</style>

<script>
(function () {
  const payloadEl = document.getElementById('subjectsPayload');
  const tbody = document.getElementById('sbTbody');

  const searchEl = document.getElementById('sbSearch');
  const clearBtn = document.getElementById('sbClear');
  const pageSizeEl = document.getElementById('sbPageSize');

  const prevBtn = document.getElementById('sbPrev');
  const nextBtn = document.getElementById('sbNext');

  const totalEl = document.getElementById('sbTotal');
  const filteredEl = document.getElementById('sbFiltered');
  const showFromEl = document.getElementById('sbShowFrom');
  const showToEl = document.getElementById('sbShowTo');
  const pageEl = document.getElementById('sbPage');
  const pagesEl = document.getElementById('sbPages');

  let all = [];
  try { all = JSON.parse(payloadEl?.textContent || '[]'); } catch (e) { all = []; }

  let page = 1;
  let pageSize = parseInt(pageSizeEl?.value || '20', 10) || 20;
  let q = '';

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function getFiltered() {
    const term = String(q || '').trim().toLowerCase();
    if (!term) return all;

    return all.filter(s => {
      const level = String(s.level_code || '').toLowerCase();
      const mod   = String(s.module_code || '').toLowerCase();
      const name  = String(s.name || '').toLowerCase();
      return level.includes(term) || mod.includes(term) || name.includes(term);
    });
  }

  function renderRow(s) {
    const csrf = `<?= str_replace("\n","",csrf_field()) ?>`;
    const editUrl = `/public/index.php?r=admin_subjects&edit_id=${encodeURIComponent(s.id)}`;

    return `
      <tr class="sb-enter hover:bg-slate-50/60 transition">
        <td class="p-3 text-slate-700">${escapeHtml(s.level_code)}</td>
        <td class="p-3 text-slate-700">${escapeHtml(s.module_code)}</td>
        <td class="p-3">
          <div class="font-medium text-slate-900">${escapeHtml(s.name)}</div>
        </td>
        <td class="p-3">
          <div class="flex justify-end gap-2">
            <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
               href="${editUrl}">
              <span class="text-sm font-medium text-slate-800">Edit</span>
            </a>

            <form method="post" action="/public/index.php?r=admin_subjects"
                  onsubmit="return confirm('Delete this subject? Only allowed if it has no topics.');">
              ${csrf}
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="${escapeHtml(s.id)}">
              <button type="submit"
                      class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-rose-50 hover:ring-rose-200 transition">
                <span class="text-sm font-medium text-slate-800">Delete</span>
              </button>
            </form>
          </div>
        </td>
      </tr>
    `;
  }

  function render() {
    const filtered = getFiltered();
    const pages = Math.max(1, Math.ceil(filtered.length / pageSize));

    if (page > pages) page = pages;
    if (page < 1) page = 1;

    const start = (page - 1) * pageSize;
    const slice = filtered.slice(start, start + pageSize);

    if (totalEl) totalEl.textContent = String(all.length);
    if (filteredEl) filteredEl.textContent = String(filtered.length);
    if (showFromEl) showFromEl.textContent = filtered.length === 0 ? '0' : String(start + 1);
    if (showToEl) showToEl.textContent = filtered.length === 0 ? '0' : String(Math.min(start + pageSize, filtered.length));
    if (pageEl) pageEl.textContent = String(page);
    if (pagesEl) pagesEl.textContent = String(pages);

    if (prevBtn) prevBtn.disabled = (page <= 1);
    if (nextBtn) nextBtn.disabled = (page >= pages);

    if (clearBtn) clearBtn.classList.toggle('hidden', !(q && q.trim().length > 0));

    if (!tbody) return;

    if (filtered.length === 0) {
      tbody.innerHTML = `<tr><td class="p-4 text-slate-600" colspan="4">No subjects found.</td></tr>`;
      return;
    }

    tbody.innerHTML = slice.map(renderRow).join('');
  }

  // Events
  if (searchEl) {
    let t = null;
    searchEl.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        q = searchEl.value || '';
        page = 1;
        render();
      }, 200);
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      q = '';
      if (searchEl) searchEl.value = '';
      page = 1;
      render();
    });
  }

  if (pageSizeEl) {
    pageSizeEl.addEventListener('change', () => {
      pageSize = parseInt(pageSizeEl.value || '20', 10) || 20;
      page = 1;
      render();
    });
  }

  if (prevBtn) prevBtn.addEventListener('click', () => { page--; render(); });
  if (nextBtn) nextBtn.addEventListener('click', () => { page++; render(); });

  render();
})();
</script>
