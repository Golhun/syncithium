<?php
/** @var array $questions */
/** @var array $filters */
?>
<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Question Bank</h1>
    <a class="px-3 py-2 rounded-lg border border-gray-200" href="/public/index.php?r=admin_questions_import">Import CSV</a>
  </div>

  <form method="get" class="mb-4">
    <input type="hidden" name="r" value="admin_questions">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
      <input class="px-3 py-2 rounded-lg border border-gray-200" name="q"
             value="<?= htmlspecialchars((string)$filters['q']) ?>" placeholder="Search question text">

      <select class="px-3 py-2 rounded-lg border border-gray-200" name="status">
        <option value="">All statuses</option>
        <option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Active</option>
        <option value="inactive" <?= $filters['status']==='inactive'?'selected':'' ?>>Inactive</option>
      </select>

      <input class="px-3 py-2 rounded-lg border border-gray-200" name="topic_id"
             value="<?= (int)$filters['topic_id'] ?>" placeholder="Topic ID (optional)">

      <button class="px-3 py-2 rounded-lg border border-gray-200" type="submit">Filter</button>
    </div>
  </form>

  <div class="overflow-x-auto border border-gray-200 rounded-xl">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-3">Taxonomy</th>
          <th class="text-left p-3">Question</th>
          <th class="text-left p-3">Status</th>
          <th class="text-left p-3">Created</th>
          <th class="text-left p-3">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($questions)): ?>
        <tr><td class="p-3" colspan="5">No questions found.</td></tr>
      <?php else: ?>
        <?php foreach ($questions as $q): ?>
          <tr class="border-t border-gray-200 align-top">
            <td class="p-3">
              <div class="text-xs text-gray-600">
                L<?= htmlspecialchars((string)$q['level_code']) ?> ,
                <?= htmlspecialchars((string)$q['module_code']) ?> ,
                <?= htmlspecialchars((string)$q['subject_name']) ?> ,
                <?= htmlspecialchars((string)$q['topic_name']) ?>
              </div>
              <div class="text-xs text-gray-500 mt-1">Topic ID: <?= (int)$q['topic_id'] ?></div>
            </td>

            <td class="p-3">
              <div class="font-medium"><?= htmlspecialchars((string)$q['question_text']) ?></div>
              <div class="text-xs text-gray-600 mt-2">
                A. <?= htmlspecialchars((string)$q['option_a']) ?><br>
                B. <?= htmlspecialchars((string)$q['option_b']) ?><br>
                C. <?= htmlspecialchars((string)$q['option_c']) ?><br>
                D. <?= htmlspecialchars((string)$q['option_d']) ?><br>
                <span class="text-gray-700">Correct:</span> <?= htmlspecialchars((string)$q['correct_option']) ?>
              </div>
            </td>

            <td class="p-3"><?= htmlspecialchars((string)$q['status']) ?></td>
            <td class="p-3"><?= htmlspecialchars((string)$q['created_at']) ?></td>

            <td class="p-3">
              <div class="flex gap-2">
                <a class="px-2 py-1 rounded-lg border border-gray-200"
                   href="/public/index.php?r=admin_question_edit&id=<?= (int)$q['id'] ?>">Edit</a>

                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                  <button class="px-2 py-1 rounded-lg border border-gray-200"
                          name="action" value="toggle_status" type="submit">
                    Toggle
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
