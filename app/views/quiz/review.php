<?php
declare(strict_types=1);

/** @var array $attempt */
/** @var array $questions */
/** @var array $topicStats */

$attemptId = (int)($attempt['id'] ?? 0);
?>
<div class="max-w-5xl mx-auto space-y-4">
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold">Quiz Review</h1>
      <p class="text-xs text-gray-500 mt-1">Attempt #<?= (int)$attemptId ?></p>
    </div>

    <div class="p-3 rounded-xl border border-gray-200 bg-white text-sm">
      <div class="text-xs text-gray-500">Score</div>
      <div class="text-lg font-semibold"><?= (int)($attempt['score'] ?? 0) ?></div>
      <div class="text-xs text-gray-500 mt-1">
        Correct: <?= (int)($attempt['raw_correct'] ?? 0) ?>,
        Wrong: <?= (int)($attempt['raw_wrong'] ?? 0) ?>
      </div>
    </div>
  </div>

  <div class="p-4 rounded-xl border border-gray-200 bg-white">
    <div class="text-sm font-medium mb-3">Performance by topic</div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-gray-500 border-b border-gray-200">
            <th class="py-2 pr-4">Level</th>
            <th class="py-2 pr-4">Module</th>
            <th class="py-2 pr-4">Subject</th>
            <th class="py-2 pr-4">Topic</th>
            <th class="py-2 pr-4">Correct</th>
            <th class="py-2 pr-4">Wrong</th>
            <th class="py-2 pr-4">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topicStats as $row): ?>
            <tr class="border-b border-gray-100">
              <td class="py-2 pr-4"><?= htmlspecialchars((string)($row['level_code'] ?? '')) ?></td>
              <td class="py-2 pr-4"><?= htmlspecialchars((string)($row['module_code'] ?? '')) ?></td>
              <td class="py-2 pr-4"><?= htmlspecialchars((string)($row['subject_name'] ?? '')) ?></td>
              <td class="py-2 pr-4"><?= htmlspecialchars((string)($row['topic_name'] ?? '')) ?></td>
              <td class="py-2 pr-4"><?= (int)($row['correct_count'] ?? 0) ?></td>
              <td class="py-2 pr-4"><?= (int)($row['wrong_count'] ?? 0) ?></td>
              <td class="py-2 pr-4"><?= (int)($row['total'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>

          <?php if (count($topicStats) === 0): ?>
            <tr><td colspan="7" class="py-3 text-gray-600">No topic stats available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="flex justify-end gap-2">
    <a
      href="/public/index.php?r=taxonomy_selector"
      class="px-4 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-sm"
    >
      Back to topics
    </a>
  </div>
</div>
