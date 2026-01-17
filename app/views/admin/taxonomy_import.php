<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Taxonomy CSV Import</h1>
    <p class="text-sm text-slate-600">Upload a CSV to create missing levels, modules, subjects, and topics.</p>
  </div>
  <div class="flex gap-2">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_topics">Back</a>
  </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl p-6">
  <div class="text-sm text-slate-700 mb-4">
    <div class="font-semibold mb-1">Required CSV header</div>
    <code class="block bg-slate-50 border border-slate-200 rounded p-2">level_code,module_code,subject_name,topic_name</code>
  </div>

  <form method="post" enctype="multipart/form-data" action="/public/index.php?r=admin_taxonomy_import" class="space-y-4">
    <?= csrf_field() ?>
    <input type="file" name="csv" required accept=".csv,text/csv"
      class="w-full rounded-lg border border-slate-300 px-3 py-2 bg-white">
    <button class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95">Import</button>
  </form>
</div>

<?php if (!empty($results)): ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mt-6">
    <div class="p-4 border-b border-slate-200 bg-slate-50">
      <div class="font-semibold">Import results</div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-white border-b border-slate-200">
          <tr>
            <th class="text-left p-3">Line</th>
            <th class="text-left p-3">Status</th>
            <th class="text-left p-3">Note</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $r): ?>
            <tr class="border-b border-slate-200 hover:bg-slate-50">
              <td class="p-3"><?= (int)$r['line'] ?></td>
              <td class="p-3"><?= e($r['status']) ?></td>
              <td class="p-3"><?= e($r['note']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
