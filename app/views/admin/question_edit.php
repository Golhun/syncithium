<?php
/** @var array $question */
?>
<div class="max-w-3xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Edit Question</h1>
    <a class="px-3 py-2 rounded-lg border border-gray-200" href="/public/index.php?r=admin_questions">Back</a>
  </div>

  <form method="post" class="space-y-3">
    <?= csrf_field() ?>

    <textarea class="w-full px-3 py-2 rounded-lg border border-gray-200" rows="3"
              name="question_text" placeholder="Question text"><?= htmlspecialchars((string)$question['question_text']) ?></textarea>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
      <input class="px-3 py-2 rounded-lg border border-gray-200" name="option_a"
             value="<?= htmlspecialchars((string)$question['option_a']) ?>" placeholder="Option A">
      <input class="px-3 py-2 rounded-lg border border-gray-200" name="option_b"
             value="<?= htmlspecialchars((string)$question['option_b']) ?>" placeholder="Option B">
      <input class="px-3 py-2 rounded-lg border border-gray-200" name="option_c"
             value="<?= htmlspecialchars((string)$question['option_c']) ?>" placeholder="Option C">
      <input class="px-3 py-2 rounded-lg border border-gray-200" name="option_d"
             value="<?= htmlspecialchars((string)$question['option_d']) ?>" placeholder="Option D">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
      <select class="px-3 py-2 rounded-lg border border-gray-200" name="correct_option">
        <?php foreach (['A','B','C','D'] as $opt): ?>
          <option value="<?= $opt ?>" <?= ($question['correct_option']===$opt)?'selected':'' ?>>Correct: <?= $opt ?></option>
        <?php endforeach; ?>
      </select>

      <select class="px-3 py-2 rounded-lg border border-gray-200" name="status">
        <option value="active" <?= ($question['status']==='active')?'selected':'' ?>>Active</option>
        <option value="inactive" <?= ($question['status']==='inactive')?'selected':'' ?>>Inactive</option>
      </select>
    </div>

    <textarea class="w-full px-3 py-2 rounded-lg border border-gray-200" rows="4"
              name="explanation" placeholder="Explanation (optional)"><?= htmlspecialchars((string)($question['explanation'] ?? '')) ?></textarea>

    <button class="px-4 py-2 rounded-lg border border-gray-200" type="submit">Save changes</button>
  </form>
</div>
