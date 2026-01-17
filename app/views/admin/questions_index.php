<?php /** @var array $rows */ ?>
<div class="flex items-center justify-between">
  <div>
    <h1 class="text-xl font-semibold">Question Bank</h1>
    <p class="text-sm text-gray-600">Search, review, and edit questions.</p>
  </div>
  <div class="flex gap-2">
    <a class="px-3 py-2 rounded-xl ring-1 ring-gray-200 hover:bg-gray-50" href="/public/index.php?r=admin_questions_import">Import CSV</a>
  </div>
</div>

<form class="mt-4 flex flex-wrap gap-2 items-end" method="get" action="/public/index.php">
  <input type="hidden" name="r" value="admin_questions">
  <div>
    <label class="block text-sm text-gray-600">Search</label>
    <input class="px-3 py-2 rounded-xl ring-1 ring-gray-200 w-72" name="q" value="<?= e($q ?? '') ?>" placeholder="Question text, topic, module, level">
  </div>
  <div>
    <label class="block text-sm text-gray-600">Status</label>
    <select class="px-3 py-2 rounded-xl ring-1 ring-gray-200" name="status">
      <option value="" <?= ($status ?? '') === '' ? 'selected' : '' ?>>All</option>
      <option value="active" <?= ($status ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
      <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
  </div>
  <button class="px-4 py-2 rounded-xl bg-gray-900 text-white hover:opacity-90" type="submit">Filter</button>
</form>

<div class="mt-4 overflow-x-auto ring-1 ring-gray-200 rounded-2xl bg-white">
  <table class="min-w-full text-sm">
    <thead class="bg-gray-50 text-gray-700">
      <tr>
        <th class="text-left p-3">Taxonomy</th>
        <th class="text-left p-3">Question</th>
        <th class="text-left p-3">Correct</th>
        <th class="text-left p-3">Status</th>
        <th class="text-left p-3">Updated</th>
        <th class="text-right p-3">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t border-gray-200">
          <td class="p-3">
            <div class="text-gray-900 font-medium">
              <?= e($r['level_code']) ?> / <?= e($r['module_code']) ?>
            </div>
            <div class="text-gray-600">
              <?= e($r['subject_name']) ?> , <?= e($r['topic_name']) ?>
            </div>
          </td>
          <td class="p-3 text-gray-900">
            <?= e(mb_strimwidth((string)$r['question_text'], 0, 120, '...')) ?>
          </td>
          <td class="p-3 font-semibold"><?= e($r['correct_option']) ?></td>
          <td class="p-3">
            <span class="px-2 py-1 rounded-lg ring-1 ring-gray-200">
              <?= e($r['status']) ?>
            </span>
          </td>
          <td class="p-3 text-gray-600"><?= e((string)$r['updated_at']) ?></td>
          <td class="p-3 text-right">
            <a class="px-3 py-2 rounded-xl ring-1 ring-gray-200 hover:bg-gray-50"
               href="/public/index.php?r=admin_questions_edit&id=<?= (int)$r['id'] ?>">
              Edit
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (count($rows) === 0): ?>
        <tr><td class="p-3 text-gray-600" colspan="6">No questions found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
