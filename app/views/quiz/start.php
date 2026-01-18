<?php
/** @var array $user */
/** @var array $topicSummary */
?>
<div class="max-w-4xl mx-auto space-y-4">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-semibold">Start Quiz</h1>
      <p class="text-xs text-gray-500 mt-1">
        Review your chosen topics, then set the quiz options.
      </p>
    </div>

    <a href="/public/index.php?r=taxonomy_selector"
       class="px-3 py-1 rounded-lg border border-gray-200 text-sm">
      Change topics
    </a>
  </div>

  <div class="p-4 rounded-xl border border-gray-200 bg-white">
    <div class="font-medium text-sm mb-2">Selected topics</div>

    <?php if (empty($topicSummary)): ?>
      <p class="text-xs text-gray-500">
        No topics found. Go back and choose topics first.
      </p>
    <?php else: ?>
      <p class="text-xs text-gray-500 mb-2">
        Questions will be drawn randomly from the topics below.
      </p>
      <div class="max-h-48 overflow-y-auto border border-gray-100 rounded-xl">
        <table class="min-w-full text-xs">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="text-left px-3 py-2">Level</th>
              <th class="text-left px-3 py-2">Module</th>
              <th class="text-left px-3 py-2">Subject</th>
              <th class="text-left px-3 py-2">Topic</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($topicSummary as $row): ?>
            <tr class="border-b border-gray-100">
              <td class="px-3 py-2"><?= htmlspecialchars((string)$row['level_code']) ?></td>
              <td class="px-3 py-2"><?= htmlspecialchars((string)$row['module_code']) ?></td>
              <td class="px-3 py-2"><?= htmlspecialchars((string)$row['subject_name']) ?></td>
              <td class="px-3 py-2"><?= htmlspecialchars((string)$row['topic_name']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <form method="post" class="p-4 rounded-xl border border-gray-200 bg-white space-y-4">
    <?= csrf_field() ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
      <div>
        <label class="block text-xs font-medium mb-1">Number of questions</label>
        <input type="number"
               name="num_questions"
               min="1"
               max="200"
               value="20"
               class="w-full px-3 py-2 rounded-lg border border-gray-300">
        <p class="text-xs text-gray-500 mt-1">Recommended: 20–50 per sitting.</p>
      </div>

      <div>
        <label class="block text-xs font-medium mb-1">Scoring mode</label>
        <select name="scoring_mode"
                class="w-full px-3 py-2 rounded-lg border border-gray-300">
          <option value="standard">Standard (+1 correct, 0 wrong)</option>
          <option value="negative">Negative marking (+1 correct, -1 wrong)</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-medium mb-1">Timer</label>
        <select name="timer_seconds"
                class="w-full px-3 py-2 rounded-lg border border-gray-300">
          <option value="1800">30 minutes</option>
          <option value="2700">45 minutes</option>
          <option value="3600" selected>60 minutes</option>
          <option value="5400">90 minutes</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">
          Quiz will auto-submit when the timer ends.
        </p>
      </div>
    </div>

    <div class="flex justify-end">
      <button type="submit"
              class="px-4 py-2 rounded-lg bg-sky-600 border border-sky-600 text-white text-sm hover:bg-sky-700">
        Start quiz
      </button>
    </div>
  </form>
</div>
