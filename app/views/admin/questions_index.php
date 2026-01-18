<?php
/** @var array $questions */
/** @var array $filters */

$questions = (isset($questions) && is_array($questions)) ? $questions : [];
$filters = (isset($filters) && is_array($filters)) ? $filters : ['q' => '', 'status' => '', 'topic_id' => 0];

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function has_icon_helper(): bool { return function_exists('icon'); }

$qSearch = (string)($filters['q'] ?? '');
$statusFilter = (string)($filters['status'] ?? '');
$topicIdFilter = (int)($filters['topic_id'] ?? 0);

// Minimal payload for client-side pagination and rendering.
// We keep strings safe for JSON and escape output at render time.
$uiRows = array_map(static function ($q) {
  return [
    'id' => (int)($q['id'] ?? 0),
    'level_code' => (string)($q['level_code'] ?? ''),
    'module_code' => (string)($q['module_code'] ?? ''),
    'subject_name' => (string)($q['subject_name'] ?? ''),
    'topic_name' => (string)($q['topic_name'] ?? ''),
    'topic_id' => (int)($q['topic_id'] ?? 0),

    'question_text' => (string)($q['question_text'] ?? ''),
    'option_a' => (string)($q['option_a'] ?? ''),
    'option_b' => (string)($q['option_b'] ?? ''),
    'option_c' => (string)($q['option_c'] ?? ''),
    'option_d' => (string)($q['option_d'] ?? ''),
    'correct_option' => (string)($q['correct_option'] ?? ''),

    'status' => (string)($q['status'] ?? ''),
    'created_at' => (string)($q['created_at'] ?? ''),
  ];
}, $questions);

$rowsJson = json_encode(
  $uiRows,
  JSON_UNESCAPED_SLASHES
  | JSON_UNESCAPED_UNICODE
  | JSON_HEX_TAG
  | JSON_HEX_AMP
  | JSON_HEX_APOS
  | JSON_HEX_QUOT
);
if ($rowsJson === false) $rowsJson = '[]';
?>

<div class="max-w-6xl mx-auto" id="qbRoot">
  <script type="application/json" id="qbPayload"><?= $rowsJson ?></script>

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?php if (has_icon_helper()): ?>
            <?= icon('book-open', 'h-5 w-5 text-sky-700', 'solid') ?>
          <?php else: ?>
            <span class="text-sky-700 font-semibold">QB</span>
          <?php endif; ?>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Question Bank</h1>
      </div>

      <p class="text-sm text-slate-600 mt-2">
        Manage questions, verify taxonomy, and keep content clean for quiz selection.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?php if (has_icon_helper()): ?><?= icon('circle-stack', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
          Total: <span class="font-semibold text-slate-700" id="qbTotal">0</span>
        </span>

        <?php if ($statusFilter !== ''): ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
            <?php if (has_icon_helper()): ?><?= icon('funnel', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
            Status: <span class="font-semibold text-slate-700"><?= h($statusFilter) ?></span>
          </span>
        <?php endif; ?>

        <?php if ($topicIdFilter > 0): ?>
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
            <?php if (has_icon_helper()): ?><?= icon('tag', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
            Topic ID: <span class="font-semibold text-slate-700"><?= (int)$topicIdFilter ?></span>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 sm:justify-end">
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_questions_import">
        <?php if (has_icon_helper()): ?><?= icon('arrow-up-tray', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        <span class="text-sm font-medium text-slate-800">Import CSV</span>
      </a>
    </div>
  </div>

  <!-- Filters (single clean surface, not multiple boxes) -->
  <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden mb-5">
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
      <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
        <?php if (has_icon_helper()): ?><?= icon('adjustments-horizontal', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        Filters
      </div>

      <div class="text-xs text-slate-500">
        Tip: Keep filters narrow for faster review.
      </div>
    </div>

    <div class="p-5">
      <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="hidden" name="r" value="admin_questions">

        <div class="md:col-span-2">
          <label class="block text-xs font-semibold text-slate-600 mb-1">Search</label>
          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
              <?php if (has_icon_helper()): ?><?= icon('magnifying-glass', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
            </div>
            <input class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-9 pr-3 py-2.5 text-sm
                          focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                   name="q"
                   value="<?= h((string)($filters['q'] ?? '')) ?>"
                   placeholder="Search question text">
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
          <select class="w-full rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2.5 text-sm
                         focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                  name="status">
            <option value="">All statuses</option>
            <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-600 mb-1">Topic ID (optional)</label>
          <input class="w-full rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2.5 text-sm
                        focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                 name="topic_id"
                 value="<?= (int)($filters['topic_id'] ?? 0) ?>"
                 placeholder="e.g., 12">
        </div>

        <div class="md:col-span-4 flex items-center gap-2 pt-1">
          <button class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold
                         hover:opacity-95 active:opacity-90 transition focus:outline-none focus:ring-4 focus:ring-sky-100"
                  type="submit">
            <?php if (has_icon_helper()): ?><?= icon('funnel', 'h-4 w-4 text-white', 'solid') ?><?php endif; ?>
            Apply filters
          </button>

          <a class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
             href="/public/index.php?r=admin_questions">
            <?php if (has_icon_helper()): ?><?= icon('x-mark', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
            Clear
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- List surface -->
  <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">
    <!-- Toolbar -->
    <div class="px-5 py-4 border-b border-slate-200 bg-white">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
          <?php if (has_icon_helper()): ?><?= icon('list-bullet', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
          Questions
        </div>

        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
          <div class="text-xs text-slate-500">
            Showing <span class="font-semibold text-slate-700" id="qbShowFrom">0</span>
            to <span class="font-semibold text-slate-700" id="qbShowTo">0</span>
            of <span class="font-semibold text-slate-700" id="qbFiltered">0</span>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500 hidden sm:inline">Rows</span>
            <select id="qbPageSize"
                    class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-sm
                           focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
              <option value="10">10</option>
              <option value="20" selected>20</option>
              <option value="50">50</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Content -->
    <div id="qbList" class="divide-y divide-slate-200"></div>

    <!-- Pagination -->
    <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-between gap-3">
      <button type="button" id="qbPrev"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
        <?php if (has_icon_helper()): ?><?= icon('chevron-left', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
        Prev
      </button>

      <div class="text-xs text-slate-500">
        Page <span class="font-semibold text-slate-700" id="qbPage">1</span>
        of <span class="font-semibold text-slate-700" id="qbPages">1</span>
      </div>

      <button type="button" id="qbNext"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
        Next
        <?php if (has_icon_helper()): ?><?= icon('chevron-right', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
      </button>
    </div>
  </div>
</div>

<style>
/* lightweight motion, material-ish */
.qb-enter { animation: qbFadeUp .16s ease-out 1; }
@keyframes qbFadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
.qb-hl { background: rgba(14,165,233,.12); border-radius: .25rem; padding: 0 .15rem; }
</style>

<script>
(function () {
  const payloadEl = document.getElementById('qbPayload');
  const listEl = document.getElementById('qbList');

  const pageSizeEl = document.getElementById('qbPageSize');
  const prevBtn = document.getElementById('qbPrev');
  const nextBtn = document.getElementById('qbNext');

  const totalEl = document.getElementById('qbTotal');
  const filteredEl = document.getElementById('qbFiltered');
  const showFromEl = document.getElementById('qbShowFrom');
  const showToEl = document.getElementById('qbShowTo');
  const pageEl = document.getElementById('qbPage');
  const pagesEl = document.getElementById('qbPages');

  let all = [];
  try { all = JSON.parse(payloadEl?.textContent || '[]'); } catch (e) { all = []; }

  // Server filters are already applied by backend. Client pagination works on the result set.
  let page = 1;
  let pageSize = parseInt(pageSizeEl?.value || '20', 10) || 20;

  // Highlight search term (from server filter "q")
  const rawQuery = <?= json_encode((string)$qSearch, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  const hlQuery = String(rawQuery || '').trim();

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function highlight(text) {
    const t = String(text ?? '');
    if (!hlQuery) return escapeHtml(t);
    const q = hlQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const re = new RegExp(q, 'ig');
    return escapeHtml(t).replace(re, (m) => `<span class="qb-hl">${m}</span>`);
  }

  function statusChip(status) {
    const s = String(status || '').toLowerCase();
    const base = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1';
    if (s === 'active') return `<span class="${base} ring-emerald-200 bg-emerald-50 text-emerald-900">Active</span>`;
    if (s === 'inactive') return `<span class="${base} ring-rose-200 bg-rose-50 text-rose-900">Inactive</span>`;
    return `<span class="${base} ring-slate-200 bg-white text-slate-700">${escapeHtml(status)}</span>`;
  }

  function renderRow(q) {
    const taxonomy = `L${q.level_code} , ${q.module_code} , ${q.subject_name} , ${q.topic_name}`;
    const correct = String(q.correct_option || '').toUpperCase();

    // Note: the Edit link is GET, Toggle is a POST form. We preserve your endpoints.
    const editHref = `/public/index.php?r=admin_question_edit&id=${encodeURIComponent(q.id)}`;

    return `
      <div class="p-5 qb-enter">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
                ${escapeHtml(taxonomy)}
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
                Topic ID: <span class="font-semibold text-slate-700">${escapeHtml(q.topic_id)}</span>
              </span>
              ${statusChip(q.status)}
              <span class="text-xs text-slate-500">Created: ${escapeHtml(q.created_at)}</span>
            </div>

            <div class="mt-3 text-slate-900 font-semibold leading-snug">
              ${highlight(q.question_text)}
            </div>

            <div class="mt-3 grid sm:grid-cols-2 gap-2 text-sm text-slate-700">
              <div class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2">
                <span class="text-xs font-semibold text-slate-500">A</span>
                <div class="mt-1">${highlight(q.option_a)}</div>
              </div>
              <div class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2">
                <span class="text-xs font-semibold text-slate-500">B</span>
                <div class="mt-1">${highlight(q.option_b)}</div>
              </div>
              <div class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2">
                <span class="text-xs font-semibold text-slate-500">C</span>
                <div class="mt-1">${highlight(q.option_c)}</div>
              </div>
              <div class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2">
                <span class="text-xs font-semibold text-slate-500">D</span>
                <div class="mt-1">${highlight(q.option_d)}</div>
              </div>
            </div>

            <div class="mt-3 text-xs text-slate-600">
              Correct option: <span class="font-semibold text-slate-900">${escapeHtml(correct)}</span>
            </div>
          </div>

          <div class="flex md:flex-col gap-2 md:items-end shrink-0">
            <a class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
               href="${editHref}">
              Edit
            </a>

            <form method="post" class="inline-block">
              <?= str_replace("\n","",csrf_field()) ?>
              <input type="hidden" name="question_id" value="${escapeHtml(q.id)}">
              <button class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
                      name="action" value="toggle_status" type="submit">
                Toggle status
              </button>
            </form>
          </div>
        </div>
      </div>
    `;
  }

  function recalcAndRender() {
    const total = all.length;
    const pages = Math.max(1, Math.ceil(total / pageSize));
    if (page > pages) page = pages;
    if (page < 1) page = 1;

    const start = (page - 1) * pageSize;
    const slice = all.slice(start, start + pageSize);

    if (totalEl) totalEl.textContent = String(total);
    if (filteredEl) filteredEl.textContent = String(total);

    if (showFromEl) showFromEl.textContent = total === 0 ? '0' : String(start + 1);
    if (showToEl) showToEl.textContent = total === 0 ? '0' : String(Math.min(start + pageSize, total));

    if (pageEl) pageEl.textContent = String(page);
    if (pagesEl) pagesEl.textContent = String(pages);

    if (prevBtn) prevBtn.disabled = (page <= 1);
    if (nextBtn) nextBtn.disabled = (page >= pages);

    if (!listEl) return;

    if (total === 0) {
      listEl.innerHTML = `
        <div class="p-6 text-slate-600">
          No questions found for the current filters.
        </div>
      `;
      return;
    }

    listEl.innerHTML = slice.map(renderRow).join('');
  }

  if (pageSizeEl) {
    pageSizeEl.addEventListener('change', () => {
      pageSize = parseInt(pageSizeEl.value || '20', 10) || 20;
      page = 1;
      recalcAndRender();
    });
  }

  if (prevBtn) prevBtn.addEventListener('click', () => { page--; recalcAndRender(); });
  if (nextBtn) nextBtn.addEventListener('click', () => { page++; recalcAndRender(); });

  recalcAndRender();
})();
</script>
