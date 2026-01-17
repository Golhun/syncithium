<?php
/** @var array $attempt */
/** @var array $questions */
/** @var array $topicStats */
?>
<div class="max-w-5xl mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-semibold">Quiz review</h1>
      <p class="text-xs text-gray-500">
        Score: <?= (int)$attempt['score'] ?> ,
        Correct: <?= (int)$attempt['raw_correct'] ?> ,
        Wrong: <?= (int)$attempt['raw_wrong'] ?> ,
        Total: <?= (int)$attempt['total_questions'] ?>
      </p>
      <p class="text-xs text-gray-500">
        Mode: <?= htmlspecialchars((string)$attempt['scoring_mode']) ?>
      </p>
    </div>
    <div class="text-right text-xs text-gray-500">
      <div>Started: <?= htmlspecialchars((string)$attempt['started_at']) ?></div>
      <div>Submitted: <?= htmlspecialchars((string)($attempt['submitted_at'] ?? '')) ?></div>
    </div>
  </div>

  <!-- Topic performance -->
  <div class="p-4 rounded-xl border border-gray-200 bg-white">
    <div class="font-medium mb-2 text-sm">Performance by topic</div>
    <?php if (empty($topicStats)): ?>
      <p class="text-xs text-gray-500">No topic breakdown available.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left px-3 py-2">Level</th>
              <th class="text-left px-3 py-2">Module</th>
              <th class="text-left px-3 py-2">Subject</th>
              <th class="text-left px-3 py-2">Topic</th>
              <th class="text-right px-3 py-2">Correct</th>
              <th class="text-right px-3 py-2">Wrong</th>
              <th class="text-right px-3 py-2">Total</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($topicStats as $row): ?>
            <tr class="border-t border-gray-200">
              <td class="px-3 py-2"><?= htmlspecialchars((string)$row['level_code']) ?></td>
              <td class="px-3 py-2"><?= htmlspecialchars((string)$row['module_code']) ?></td>
              <td class="px-3 py-2"><?= htmlspecialchars((string)$row['subject_name']) ?></td>
              <td class="px-3 py-2"><?= htmlspecialchars((string)$row['topic_name']) ?></td>
              <td class="px-3 py-2 text-right"><?= (int)$row['correct_count'] ?></td>
              <td class="px-3 py-2 text-right"><?= (int)$row['wrong_count'] ?></td>
              <td class="px-3 py-2 text-right"><?= (int)$row['total'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- Question by question -->
  <div class="space-y-4">
    <?php foreach ($questions as $idx => $q): ?>
      <?php
        $sel = strtoupper((string)($q['selected_option'] ?? ''));
        $correct = strtoupper((string)$q['correct_option']);
        $isCorrect = $q['is_correct'];
      ?>
      <div class="p-4 rounded-xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-medium">
            Question <?= $idx + 1 ?>
            <span class="text-xs text-gray-400">
              (<?= htmlspecialchars((string)$q['level_code']) ?>
              / <?= htmlspecialchars((string)$q['module_code']) ?>
              / <?= htmlspecialchars((string)$q['topic_name']) ?>)
            </span>
          </div>
          <div class="text-xs">
            <?php if ($isCorrect === null): ?>
              <span class="px-2 py-1 rounded-full bg-gray-100 text-gray-600">Not answered</span>
            <?php elseif ((int)$isCorrect === 1): ?>
              <span class="px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Correct</span>
            <?php else: ?>
              <span class="px-2 py-1 rounded-full bg-rose-100 text-rose-700">Wrong</span>
            <?php endif; ?>
          </div>
        </div>

        <p class="text-sm mb-3">
          <?= nl2br(htmlspecialchars((string)$q['question_text'])) ?>
        </p>

        <?php
          $options = [
            'A' => $q['option_a'],
            'B' => $q['option_b'],
            'C' => $q['option_c'],
            'D' => $q['option_d'],
          ];
        ?>

        <div class="space-y-1 text-sm">
          <?php foreach ($options as $key => $text): ?>
            <?php
              $isUser  = ($sel === $key);
              $isCorr  = ($correct === $key);
              $classes = 'px-2 py-1 rounded-lg flex items-center gap-2';

              if ($isCorr && $isUser) {
                $classes .= ' bg-emerald-50 border border-emerald-300';
              } elseif ($isCorr) {
                $classes .= ' bg-emerald-50 border border-emerald-200';
              } elseif ($isUser && !$isCorr) {
                $classes .= ' bg-rose-50 border border-rose-200';
              } else {
                $classes .= ' border border-transparent';
              }
            ?>
            <div class="<?= $classes ?>">
              <span class="font-medium mr-1"><?= $key ?>.</span>
              <span><?= htmlspecialchars((string)$text) ?></span>
              <?php if ($isCorr): ?>
                <span class="text-xs text-emerald-600 ml-2">Correct</span>
              <?php elseif ($isUser && !$isCorr): ?>
                <span class="text-xs text-rose-600 ml-2">Your choice</span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
