<h1 class="text-xl font-semibold">Import Questions</h1>
<p class="text-sm text-gray-600 mt-1">Upload a CSV to import questions. Duplicates are automatically skipped using hash-based dedupe.</p>

<div class="mt-4 ring-1 ring-gray-200 rounded-2xl bg-white p-4">
  <form method="post" enctype="multipart/form-data" action="/public/index.php?r=admin_questions_import">
    <?= csrf_field() ?>

    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-700 font-medium">Import mode</label>
        <select class="mt-1 px-3 py-2 rounded-xl ring-1 ring-gray-200 w-full" name="mode" x-data>
          <option value="taxonomy_in_csv">CSV contains taxonomy columns (level_code, module_code, subject_name, topic_name)</option>
          <option value="select_topic">Admin selects a Topic (CSV only has questions)</option>
        </select>
      </div>

      <div>
        <label class="block text-sm text-gray-700 font-medium">Topic (required if Admin selects Topic mode)</label>
        <select class="mt-1 px-3 py-2 rounded-xl ring-1 ring-gray-200 w-full" name="topic_id">
          <option value="0">Select Topic</option>
          <?php foreach ($topics as $t): ?>
            <option value="<?= (int)$t['id'] ?>">
              <?= e($t['level_code']) ?> / <?= e($t['module_code']) ?> , <?= e($t['subject_name']) ?> , <?= e($t['topic_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-2">
        <label class="block text-sm text-gray-700 font-medium">CSV file</label>
        <input class="mt-1 block w-full" type="file" name="csv" accept=".csv,text/csv">
        <p class="text-xs text-gray-500 mt-1">
          Required columns: question_text, option_a, option_b, option_c, option_d, correct_option. Optional: explanation, status.
        </p>
      </div>
    </div>

    <div class="mt-4 flex gap-2">
      <button class="px-4 py-2 rounded-xl bg-gray-900 text-white hover:opacity-90" type="submit">Import</button>
      <a class="px-4 py-2 rounded-xl ring-1 ring-gray-200 hover:bg-gray-50" href="/public/index.php?r=admin_questions">Back</a>
    </div>
  </form>
</div>

<?php if (!empty($summary)): ?>
  <div class="mt-4 ring-1 ring-gray-200 rounded-2xl bg-white p-4">
    <h2 class="font-semibold">Import Summary</h2>
    <ul class="text-sm text-gray-700 mt-2 space-y-1">
      <li>Created: <?= (int)$summary['created'] ?></li>
      <li>Duplicates skipped: <?= (int)$summary['duplicates'] ?></li>
      <li>Rows skipped (invalid/missing taxonomy): <?= (int)$summary['skipped'] ?></li>
    </ul>
  </div>
<?php endif; ?>
