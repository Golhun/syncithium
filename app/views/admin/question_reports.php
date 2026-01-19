<?php
declare(strict_types=1);

/**
 * app/views/admin/question_reports.php (drop-in replacement)
 *
 * Expected variables:
 * - $reports : array
 * - $status  : string
 *
 * Assumes global helpers exist:
 * - csrf_field()
 * - e()   (DO NOT redeclare here)
 * - icon($name, $class = 'h-5 w-5', $variant = 'outline')  // optional
 */

$reports = (isset($reports) && is_array($reports)) ? $reports : [];
$status  = (string)($status ?? 'open');

// Tabs
$tabs = [
  'open'      => 'Open',
  'in_review' => 'In Review',
  'resolved'  => 'Resolved',
  'rejected'  => 'Rejected',
  'all'       => 'All',
];

// Prepare client-side pagination payload (minimal fields, safe types)
$uiRows = array_map(static function ($r) {
  return [
    'id' => (int)($r['id'] ?? 0),
    'created_at' => (string)($r['created_at'] ?? ''),
    'email' => (string)($r['email'] ?? ($r['user_email'] ?? '')),
    'reason' => (string)($r['reason'] ?? ($r['report_type'] ?? '')),
    'question_text' => (string)($r['question_text'] ?? ''),
    'details' => (string)($r['details'] ?? ($r['message'] ?? '')),
    'status' => (string)($r['status'] ?? 'open'),
    'admin_notes' => (string)($r['admin_notes'] ?? ''),
  ];
}, $reports);

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

<div class="max-w-6xl mx-auto" id="qrRoot">
  <script type="application/json" id="qrPayload"><?= $rowsJson ?></script>

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?= icon('flag', 'h-5 w-5 text-sky-700', 'solid') ?>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Question Reports</h1>
      </div>
      <p class="text-sm text-slate-600 mt-2">
        Review reported questions, add admin notes, and update status.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('circle-stack', 'h-4 w-4 text-slate-400', 'outline') ?>
          Total: <span class="font-semibold text-slate-700" id="qrTotal">0</span>
        </span>

        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('funnel', 'h-4 w-4 text-slate-400', 'outline') ?>
          Filter: <span class="font-semibold text-slate-700"><?= e($tabs[$status] ?? $status) ?></span>
        </span>
      </div>
    </div>

    <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
       href="/public/index.php?r=admin_users">
      <?= icon('arrow-left', 'h-4 w-4 text-slate-600', 'outline') ?>
      <span class="text-sm font-medium text-slate-800">Back to Users</span>
    </a>
  </div>

  <!-- Tabs -->
  <div class="flex flex-wrap gap-2 mb-5">
    <?php foreach ($tabs as $k => $label): ?>
      <?php $active = ($status === $k); ?>
      <a
        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 transition
               <?= $active ? 'bg-slate-900 text-white ring-slate-900' : 'bg-white text-slate-800 ring-slate-200 hover:bg-slate-50' ?>"
        href="/public/index.php?r=admin_question_reports&status=<?= e($k) ?>"
      >
        <?php
          $ico = match ($k) {
            'open' => 'exclamation-circle',
            'in_review' => 'eye',
            'resolved' => 'check-circle',
            'rejected' => 'x-circle',
            'all' => 'list-bullet',
            default => 'tag',
          };
        ?>
        <?= icon($ico, 'h-4 w-4 '.($active ? 'text-white' : 'text-slate-600'), $active ? 'solid' : 'outline') ?>
        <span class="text-sm font-medium"><?= e($label) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Single primary surface -->
  <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">
    <!-- Toolbar -->
    <div class="px-5 py-4 border-b border-slate-200 bg-white">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
          <?= icon('table-cells', 'h-4 w-4 text-slate-600', 'outline') ?>
          Reports list
        </div>

        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
          <div class="text-xs text-slate-500">
            Showing <span class="font-semibold text-slate-700" id="qrShowFrom">0</span>
            to <span class="font-semibold text-slate-700" id="qrShowTo">0</span>
            of <span class="font-semibold text-slate-700" id="qrFiltered">0</span>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500 hidden sm:inline">Rows</span>
            <select id="qrPageSize"
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

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
          <tr class="text-left text-xs font-semibold text-slate-600">
            <th class="p-3 whitespace-nowrap">Date</th>
            <th class="p-3">Reporter</th>
            <th class="p-3">Reason</th>
            <th class="p-3">Question</th>
            <th class="p-3">Details</th>
            <th class="p-3 whitespace-nowrap">Status</th>
            <th class="p-3 whitespace-nowrap">Admin Action</th>
          </tr>
        </thead>

        <tbody id="qrTbody" class="divide-y divide-slate-200"></tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-between gap-3">
      <button type="button" id="qrPrev"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
        <?= icon('chevron-left', 'h-4 w-4 text-slate-600', 'outline') ?>
        Prev
      </button>

      <div class="text-xs text-slate-500">
        Page <span class="font-semibold text-slate-700" id="qrPage">1</span>
        of <span class="font-semibold text-slate-700" id="qrPages">1</span>
      </div>

      <button type="button" id="qrNext"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
        Next
        <?= icon('chevron-right', 'h-4 w-4 text-slate-600', 'outline') ?>
      </button>
    </div>
  </div>
</div>

<style>
.qr-enter { animation: qrFadeUp .14s ease-out 1; }
@keyframes qrFadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
</style>

<script>
(function () {
  const payloadEl = document.getElementById('qrPayload');
  const tbody = document.getElementById('qrTbody');

  const pageSizeEl = document.getElementById('qrPageSize');
  const prevBtn = document.getElementById('qrPrev');
  const nextBtn = document.getElementById('qrNext');

  const totalEl = document.getElementById('qrTotal');
  const filteredEl = document.getElementById('qrFiltered');
  const showFromEl = document.getElementById('qrShowFrom');
  const showToEl = document.getElementById('qrShowTo');
  const pageEl = document.getElementById('qrPage');
  const pagesEl = document.getElementById('qrPages');

  let all = [];
  try { all = JSON.parse(payloadEl?.textContent || '[]'); } catch (e) { all = []; }

  let page = 1;
  let pageSize = parseInt(pageSizeEl?.value || '20', 10) || 20;

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function chipClass(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'open') return 'ring-amber-200 bg-amber-50 text-amber-900';
    if (s === 'in_review') return 'ring-sky-200 bg-sky-50 text-sky-900';
    if (s === 'resolved') return 'ring-emerald-200 bg-emerald-50 text-emerald-900';
    if (s === 'rejected') return 'ring-rose-200 bg-rose-50 text-rose-900';
    return 'ring-slate-200 bg-white text-slate-700';
  }

  function renderRow(r) {
    const status = String(r.status || 'open');
    const chip = `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs ring-1 ${chipClass(status)}">${escapeHtml(status)}</span>`;
    const csrf = `<?= str_replace("\n","",csrf_field()) ?>`;

    const options = [
      { v: 'open', label: 'Open' },
      { v: 'in_review', label: 'In Review' },
      { v: 'resolved', label: 'Resolved' },
      { v: 'rejected', label: 'Rejected' }
    ].map(o => {
      const sel = (status === o.v) ? 'selected' : '';
      return `<option value="${escapeHtml(o.v)}" ${sel}>${escapeHtml(o.label)}</option>`;
    }).join('');

    return `
      <tr class="qr-enter hover:bg-slate-50/60 transition align-top">
        <td class="p-3 whitespace-nowrap text-slate-700">${escapeHtml(r.created_at)}</td>
        <td class="p-3 text-slate-700">${escapeHtml(r.email)}</td>
        <td class="p-3 text-slate-700">${escapeHtml(r.reason)}</td>
        <td class="p-3">
          <div class="max-w-md whitespace-pre-wrap text-slate-900 font-medium">${escapeHtml(r.question_text)}</div>
        </td>
        <td class="p-3">
          <div class="max-w-md whitespace-pre-wrap text-slate-600">${escapeHtml(r.details)}</div>
        </td>
        <td class="p-3 whitespace-nowrap">${chip}</td>
        <td class="p-3 w-[360px]">
          <form method="post" action="/public/index.php?r=admin_question_report_update" class="space-y-2">
            ${csrf}
            <input type="hidden" name="id" value="${escapeHtml(r.id)}">

            <select class="w-full rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-sm
                           focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                    name="status">
              ${options}
            </select>

            <textarea
              class="w-full rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-sm
                     focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
              rows="3"
              name="admin_notes"
              placeholder="Admin notes (optional)"
            >${escapeHtml(r.admin_notes || '')}</textarea>

            <button class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition
                           focus:outline-none focus:ring-4 focus:ring-sky-100"
                    type="submit">
              Update
            </button>
          </form>
        </td>
      </tr>
    `;
  }

  function render() {
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

    if (!tbody) return;

    if (total === 0) {
      tbody.innerHTML = `<tr><td class="p-4 text-slate-600" colspan="7">No reports found for this filter.</td></tr>`;
      return;
    }

    tbody.innerHTML = slice.map(renderRow).join('');
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
