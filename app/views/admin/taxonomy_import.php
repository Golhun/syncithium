<?php
/** @var array $results */
$results = (isset($results) && is_array($results)) ? $results : [];
$hasResults = !empty($results);

// quick status tone helper (UI only)
$toneClass = function (string $status): string {
  $s = strtolower(trim($status));
  if (in_array($s, ['created','inserted','ok','success'], true)) return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
  if (in_array($s, ['skipped'], true)) return 'bg-slate-50 text-slate-700 ring-slate-200';
  if (in_array($s, ['duplicate','exists'], true)) return 'bg-amber-50 text-amber-900 ring-amber-200';
  if (in_array($s, ['error','exception','failed'], true)) return 'bg-rose-50 text-rose-800 ring-rose-200';
  return 'bg-slate-50 text-slate-700 ring-slate-200';
};
?>

<div class="max-w-4xl mx-auto" x-data="taxonomyImport()" x-init="init()">

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?= icon('squares-plus', 'h-5 w-5 text-sky-700', 'solid') ?>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Taxonomy CSV Import</h1>
      </div>

      <p class="text-sm text-slate-600 mt-2">
        Upload a CSV to create missing levels, modules, subjects, and topics.
      </p>

      <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('information-circle', 'h-4 w-4 text-slate-400', 'outline') ?>
          Creates missing records only
        </span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
          <?= icon('table-cells', 'h-4 w-4 text-slate-400', 'outline') ?>
          CSV header must match exactly
        </span>
      </div>
    </div>

    <div class="flex gap-2 sm:justify-end">
      <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition"
         href="/public/index.php?r=admin_topics">
        <?= icon('arrow-left', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span class="text-sm font-medium text-slate-800">Back</span>
      </a>
    </div>
  </div>

  <!-- Main card -->
  <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
      <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
        <?= icon('arrow-up-tray', 'h-4 w-4 text-slate-600', 'outline') ?>
        <span>Upload CSV</span>
      </div>

      <div class="text-xs text-slate-500 inline-flex items-center gap-2">
        <?= icon('shield-check', 'h-4 w-4 text-slate-400', 'outline') ?>
        <span>Validated before import</span>
      </div>
    </div>

    <div class="p-5 sm:p-6">
      <!-- Instructions -->
      <div class="text-sm text-slate-700">
        <div class="font-semibold mb-2 flex items-center gap-2">
          <?= icon('document-text', 'h-4 w-4 text-slate-500', 'outline') ?>
          <span>Required CSV header</span>
        </div>

        <div class="rounded-xl bg-white ring-1 ring-slate-200 overflow-hidden">
          <div class="px-4 py-3 flex items-center justify-between bg-slate-50 border-b border-slate-200">
            <span class="text-xs text-slate-600">Copy exactly</span>
            <button type="button"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 hover:text-slate-900 transition"
                    @click="copyHeader()">
              <?= icon('clipboard', 'h-4 w-4', 'outline') ?>
              Copy
            </button>
          </div>
          <code class="block px-4 py-3 text-xs sm:text-sm text-slate-800 bg-white">
            level_code,module_code,subject_name,topic_name
          </code>
        </div>

        <p class="mt-3 text-xs text-slate-500 flex items-start gap-2">
          <span class="mt-0.5"><?= icon('exclamation-triangle', 'h-4 w-4 text-amber-600', 'solid') ?></span>
          <span>
            Keep codes and names consistent. If you already have taxonomy in the database, reuse the same codes and names to avoid duplicates.
          </span>
        </p>
      </div>

      <!-- Form -->
      <form method="post"
            enctype="multipart/form-data"
            action="/public/index.php?r=admin_taxonomy_import"
            class="mt-5 space-y-4"
            @submit="submitting=true">

        <?= csrf_field() ?>

        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">CSV file</label>

          <div class="relative">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
              <?= icon('paper-clip', 'h-5 w-5 text-slate-400', 'outline') ?>
            </div>

            <input type="file"
                   name="csv"
                   required
                   accept=".csv,text/csv"
                   class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                          focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                   @change="fileSelected=true">
          </div>

          <div class="mt-2 text-xs text-slate-500 flex items-center gap-2">
            <span x-show="!fileSelected"><?= icon('circle-stack', 'h-4 w-4 text-slate-400', 'outline') ?></span>
            <span x-show="fileSelected" x-cloak><?= icon('check-circle', 'h-4 w-4 text-emerald-600', 'solid') ?></span>
            <span x-text="fileSelected ? 'File selected, ready to import.' : 'Choose a CSV file to continue.'"></span>
          </div>
        </div>

        <div class="flex items-center justify-between gap-3 pt-2">
          <div class="text-xs text-slate-500 inline-flex items-center gap-2">
            <?= icon('clock', 'h-4 w-4 text-slate-400', 'outline') ?>
            <span>Imports may take longer for large files.</span>
          </div>

          <button type="submit"
                  class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold
                         hover:opacity-95 active:opacity-90 transition
                         focus:outline-none focus:ring-4 focus:ring-sky-100 disabled:opacity-60 disabled:cursor-not-allowed"
                  :disabled="submitting">
            <span x-show="!submitting" class="inline-flex items-center gap-2">
              <?= icon('arrow-up-tray', 'h-4 w-4 text-white', 'solid') ?>
              Import
            </span>
            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
              <span class="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
              Importing...
            </span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Results -->
  <?php if ($hasResults): ?>
    <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden mt-6">
      <div class="px-5 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between gap-3">
        <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
          <?= icon('list-bullet', 'h-4 w-4 text-slate-600', 'outline') ?>
          <span>Import results</span>
        </div>

        <div class="text-xs text-slate-500 inline-flex items-center gap-2">
          <?= icon('check-badge', 'h-4 w-4 text-slate-400', 'outline') ?>
          <span>Review what was created or skipped</span>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-white border-b border-slate-200">
            <tr class="text-left text-xs font-semibold text-slate-600">
              <th class="p-3">Line</th>
              <th class="p-3">Status</th>
              <th class="p-3">Note</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <?php foreach ($results as $r): ?>
              <?php
                $line = (int)($r['line'] ?? 0);
                $status = (string)($r['status'] ?? '');
                $note = (string)($r['note'] ?? '');

                $s = strtolower(trim($status));
                $statusIcon = 'information-circle';
                $variant = 'outline';

                if (in_array($s, ['created','inserted','ok','success'], true)) { $statusIcon = 'check-circle'; $variant = 'solid'; }
                elseif (in_array($s, ['duplicate','exists'], true)) { $statusIcon = 'exclamation-triangle'; $variant = 'solid'; }
                elseif (in_array($s, ['error','exception','failed'], true)) { $statusIcon = 'x-circle'; $variant = 'solid'; }
                elseif (in_array($s, ['skipped'], true)) { $statusIcon = 'minus-circle'; $variant = 'outline'; }
              ?>
              <tr class="hover:bg-slate-50/60 transition">
                <td class="p-3 text-slate-700 whitespace-nowrap"><?= $line ?></td>

                <td class="p-3">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs ring-1 <?= $toneClass($status) ?>">
                    <?= icon($statusIcon, 'h-4 w-4', $variant) ?>
                    <?= e($status) ?>
                  </span>
                </td>

                <td class="p-3 text-slate-700"><?= e($note) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div>

<style>
@keyframes fadeInUp {
  0% { opacity: 0; transform: translateY(10px); }
  100% { opacity: 1; transform: translateY(0); }
}
</style>

<script>
function taxonomyImport() {
  return {
    submitting: false,
    fileSelected: false,

    init() {
      requestAnimationFrame(() => {
        const root = this.$root;
        if (!root) return;
        root.classList.add('animate-[fadeInUp_.18s_ease-out_1]');
      });
    },

    async copyHeader() {
      const text = 'level_code,module_code,subject_name,topic_name';
      try {
        await navigator.clipboard.writeText(text);
        if (window.alertify) alertify.success('Header copied');
      } catch (e) {
        if (window.alertify) alertify.message('Copy not supported here');
      }
    }
  }
}
</script>
