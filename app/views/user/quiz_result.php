<?php
/** @var array $attempt */
/** @var array $questions */
/** @var array $byTopic */
/** @var array $bySubject */
/** @var array $byModule */

$total   = (int)($attempt['total_questions'] ?? 0);
$correct = (int)($attempt['raw_correct'] ?? 0);
$wrong   = (int)($attempt['raw_wrong'] ?? 0);

$percent = $total > 0 ? round(($correct / $total) * 100) : 0;
?>
<div class="max-w-5xl mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-semibold">Quiz result</h1>
      <p class="text-xs text-gray-600 mt-1">
        Scoring mode:
        <span class="font-medium">
          <?= $attempt['scoring_mode'] === 'negative'
            ? '+1 correct, -1 wrong'
            : '+1 correct, 0 wrong' ?>
        </span>
      </p>
      <p class="text-xs text-gray-600">
        Attempt: <?= htmlspecialchars((string)$attempt['started_at']) ?>
      </p>
    </div>

    <div class="text-right">
      <div class="text-xs text-gray-500 mb-1">Overall score</div>
      <div class="px-4 py-3 rounded-xl border border-gray-200 bg-white inline-block text-sm">
        <div class="font-semibold text-lg">
          <?= $correct ?> / <?= $total ?> (<?= $percent ?>%)
        </div>
        <div class="text-xs text-gray-500 mt-1">
          Wrong: <?= $wrong ?> ,
          Raw score: <?= (int)$attempt['score'] ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Performance breakdown -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
    <div class="p-4 rounded-xl border border-gray-200 bg-white">
      <div class="font-medium mb-2 text-sm">By module</div>
      <?php if (empty($byModule)): ?>
        <p class="text-xs text-gray-500">No data.</p>
      <?php else: ?>
        <ul class="space-y-1">
          <?php foreach ($byModule as $row): ?>
            <?php
            $t = (int)$row['total'];
            $c = (int)$row['correct'];
            $p = $t > 0 ? round(($c / $t) * 100) : 0;
            ?>
            <li class="flex justify-between">
              <span><?= htmlspecialchars((string)$row['label']) ?></span>
              <span class="text-gray-600"><?= $c ?>/<?= $t ?> (<?= $p ?>%)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="p-4 rounded-xl border border-gray-200 bg-white">
      <div class="font-medium mb-2 text-sm">By subject</div>
      <?php if (empty($bySubject)): ?>
        <p class="text-xs text-gray-500">No data.</p>
      <?php else: ?>
        <ul class="space-y-1">
          <?php foreach ($bySubject as $row): ?>
            <?php
            $t = (int)$row['total'];
            $c = (int)$row['correct'];
            $p = $t > 0 ? round(($c / $t) * 100) : 0;
            ?>
            <li class="flex justify-between">
              <span><?= htmlspecialchars((string)$row['label']) ?></span>
              <span class="text-gray-600"><?= $c ?>/<?= $t ?> (<?= $p ?>%)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="p-4 rounded-xl border border-gray-200 bg-white">
      <div class="font-medium mb-2 text-sm">By topic</div>
      <?php if (empty($byTopic)): ?>
        <p class="text-xs text-gray-500">No data.</p>
      <?php else: ?>
        <ul class="space-y-1 max-h-40 overflow-y-auto">
          <?php foreach ($byTopic as $row): ?>
            <?php
            $t = (int)$row['total'];
            $c = (int)$row['correct'];
            $p = $t > 0 ? round(($c / $t) * 100) : 0;
            ?>
            <li class="flex justify-between">
              <span><?= htmlspecialchars((string)$row['label']) ?></span>
              <span class="text-gray-600"><?= $c ?>/<?= $t ?> (<?= $p ?>%)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <!-- Question-level review -->
  <div class="mt-4">
    <div class="font-medium mb-2 text-sm">Review questions</div>

    <?php if (empty($questions)): ?>
      <p class="text-xs text-gray-500">No questions found for this attempt.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($questions as $index => $q): ?>
          <?php
          $n      = $index + 1;
          $sel    = strtoupper((string)($q['selected_option'] ?? ''));
          $correctOption = strtoupper((string)$q['correct_option']);
          $isCorrect = ((int)($q['is_correct'] ?? 0) === 1);

          $borderClass = $sel === '' ? 'border-gray-200'
                        : ($isCorrect ? 'border-green-500' : 'border-red-500');
          ?>
          <div class="rounded-xl border <?= $borderClass ?> bg-white p-4">
            <div class="flex justify-between items-baseline gap-4 mb-2">
              <div>
                <div class="text-xs text-gray-500 mb-1">Question <?= $n ?></div>
                <div class="text-sm font-medium mb-2">
                  <?= nl2br(htmlspecialchars((string)$q['question_text'])) ?>
                </div>
              </div>
              <div class="text-xs">
                <?php if ($sel === ''): ?>
                  <span class="text-gray-500">Not answered</span>
                <?php elseif ($isCorrect): ?>
                  <span class="text-green-600 font-medium">Correct</span>
                <?php else: ?>
                  <span class="text-red-600 font-medium">Wrong</span>
                <?php endif; ?>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
              <?php
              $opts = [
                'A' => $q['option_a'],
                'B' => $q['option_b'],
                'C' => $q['option_c'],
                'D' => $q['option_d'],
              ];
              foreach ($opts as $letter => $text):
                $isCorrectOpt = ($letter === $correctOption);
                $isSelected   = ($letter === $sel);

                $bg = '';
                if ($isCorrectOpt) {
                  $bg = 'bg-green-50 border-green-200';
                }
                if ($isSelected && !$isCorrectOpt) {
                  $bg = 'bg-red-50 border-red-200';
                }
              ?>
              <div class="px-3 py-2 rounded-lg border border-gray-200 <?= $bg ?>">
                <div class="flex items-start gap-2">
                  <span class="font-mono text-xs text-gray-500"><?= $letter ?>.</span>
                  <span><?= htmlspecialchars((string)$text) ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

            <?php if (!empty($q['explanation'])): ?>
              <div class="mt-3 text-xs text-gray-700">
                <span class="font-semibold">Explanation:</span>
                <?= nl2br(htmlspecialchars((string)$q['explanation'])) ?>
              </div>
            <?php endif; ?>

            <div class="mt-2 text-[11px] text-gray-500">
              <?= htmlspecialchars((string)$q['level_code']) ?>
              · <?= htmlspecialchars((string)$q['module_code']) ?>
              · <?= htmlspecialchars((string)$q['subject_name']) ?>
              · <?= htmlspecialchars((string)$q['topic_name']) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="mt-4">
    <a href="/public/index.php?r=quiz_start"
       class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-sm">
      Take another quiz
    </a>
  </div>
</div>
