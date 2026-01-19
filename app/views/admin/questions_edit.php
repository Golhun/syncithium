<h1 class="text-xl font-semibold">Edit Question</h1>
<p class="text-sm text-gray-600 mt-1">
  <?= e($row['level_code']) ?> / <?= e($row['module_code']) ?> , <?= e($row['subject_name']) ?> , <?= e($row['topic_name']) ?>
</p>

<form class="mt-4 ring-1 ring-gray-200 rounded-2xl bg-white p-5" method="post"
      action="/public/index.php?r=admin_questions_edit&id=<?= (int)$row['id'] ?>">
  <?= csrf_field() ?>

  <div class="space-y-3">
    <div>
      <label class="block text-sm font-medium text-gray-700">Question</label>
      <textarea class="mt-1 px-3 py-2 rounded-xl ring-1 ring-slate-200 w-full focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400" rows="4" name="question_text"><?= e((string)$row['question_text']) ?></textarea>
    </div>

    <div class="grid md:grid-cols-2 gap-3">
      <div>
        <label class="block text-sm font-medium text-gray-700">Option A</label>
        <textarea class="mt-1 px-3 py-2 rounded-xl ring-1 ring-slate-200 w-full focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400" rows="2" name="option_a"><?= e((string)$row['option_a']) ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Option B</label>
        <textarea class="mt-1 px-3 py-2 rounded-xl ring-1 ring-slate-200 w-full focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400" rows="2" name="option_b"><?= e((string)$row['option_b']) ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Option C</label>
        <textarea class="mt-1 px-3 py-2 rounded-xl ring-1 ring-slate-200 w-full focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400" rows="2" name="option_c"><?= e((string)$row['option_c']) ?></textarea>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Option D</label>
        <textarea class="mt-1 px-3 py-2 rounded-xl ring-1 ring-slate-200 w-full focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400" rows="2" name="option_d"><?= e((string)$row['option_d']) ?></textarea>
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-3">
      <div>
        <label class="block text-sm font-medium text-gray-700">Correct option</label>
        <select class="mt-1 px-3 py-2 rounded-xl ring-1 ring-slate-200 w-full focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400" name="correct_option">
          <?php foreach (['A','B','C','D'] as $o): ?>
            <option value="<?= $o ?>" <?= ((string)$row['correct_option'] === $o) ? 'selected' : '' ?>><?= $o ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700">Status</label>
        <select class="mt-1 px-3 py-2 rounded-xl ring-1 ring-slate-200 w-full focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400" name="status">
          <option value="active" <?= ((string)$row['status'] === 'active') ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= ((string)$row['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <div class="text-sm text-gray-600 flex items-end">
        Updated: <?= e((string)$row['updated_at']) ?>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700">Explanation (optional)</label>
      <textarea class="mt-1 px-3 py-2 rounded-xl ring-1 ring-slate-200 w-full focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400" rows="4" name="explanation"><?= e((string)($row['explanation'] ?? '')) ?></textarea>
    </div>
  </div>

  <div class="mt-4 flex gap-2">
    <button class="px-4 py-2 rounded-xl bg-slate-900 text-white hover:opacity-90" type="submit">Save</button>
    <a class="px-4 py-2 rounded-xl ring-1 ring-slate-200 hover:bg-slate-50" href="/public/index.php?r=admin_questions">Back</a>
  </div>
</form>
