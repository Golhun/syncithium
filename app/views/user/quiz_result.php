<?php
/** @var array $attempt */
/** @var array $questions */
/** @var array $byTopic */
/** @var array $bySubject */
/** @var array $byModule */

$total   = (int)($attempt['total_questions'] ?? 0);
$correct = (int)($attempt['raw_correct'] ?? 0);
$wrong   = (int)($attempt['raw_wrong'] ?? 0);

$percent = $total > 0 ? (int)round(($correct / $total) * 100) : 0;

$modeLabel = (($attempt['scoring_mode'] ?? '') === 'negative')
  ? '+1 correct, -1 wrong'
  : '+1 correct, 0 wrong';

/**
 * Helper: convert breakdown arrays into a normalized list:
 * [
 *   ['label' => string, 'total' => int, 'correct' => int, 'pct' => int]
 * ]
 */
$normBreakdown = function (array $items): array {
  $out = [];
  foreach ($items as $row) {
    $t = (int)($row['total'] ?? 0);
    $c = (int)($row['correct'] ?? 0);
    $pct = $t > 0 ? (int)round(($c / $t) * 100) : 0;
    $out[] = [
      'label' => (string)($row['label'] ?? ''),
      'total' => $t,
      'correct' => $c,
      'pct' => $pct,
    ];
  }
  return $out;
};

$moduleList  = $normBreakdown($byModule ?? []);
$subjectList = $normBreakdown($bySubject ?? []);
$topicList   = $normBreakdown($byTopic ?? []);

// Sort topics weakest first (lowest pct, then higher total)
usort($topicList, function ($a, $b) {
  if ($a['pct'] === $b['pct']) return $b['total'] <=> $a['total'];
  return $a['pct'] <=> $b['pct'];
});

// Keep module/subject stable order as provided, but you can sort if you want.
// For display, cap weakest topics shown
$topicTop = array_slice($topicList, 0, 8);
?>
<div class="max-w-5xl mx-auto space-y-6">

  <!-- Header -->
  <div class="flex items-start justify-between gap-6">
    <div>
      <h1 class="text-xl font-semibold">Quiz result</h1>
      <div class="mt-2 space-y-1 text-xs text-gray-600">
        <div>Scoring mode: <span class="font-medium"><?= htmlspecialchars($modeLabel) ?></span></div>
        <div>Attempt started: <?= htmlspecialchars((string)($attempt['started_at'] ?? '')) ?></div>
      </div>

      <div class="mt-4 flex items-center gap-2">
        <a href="/public/index.php?r=taxonomy_selector"
           class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-sm">
          Take another quiz
        </a>

        <a href="/public/index.php?r=my_reports"
           class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-sm">
          My review requests
        </a>
      </div>
    </div>

    <!-- Score Card -->
    <div class="text-right">
      <div class="text-xs text-gray-500 mb-1">Overall score</div>
      <div class="px-4 py-3 rounded-xl border border-gray-200 bg-white inline-block">
        <div class="font-semibold text-lg">
          <?= $correct ?> / <?= $total ?> (<?= $percent ?>%)
        </div>
        <div class="text-xs text-gray-500 mt-1">
          Wrong: <?= $wrong ?>, Raw score: <?= (int)($attempt['score'] ?? 0) ?>
        </div>

        <div class="mt-3 h-2 rounded-full bg-gray-100">
          <div class="h-2 rounded-full bg-gray-900" style="width: <?= $percent ?>%"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Breakdown Panel -->
  <div x-data="{ tab: 'topic' }" class="rounded-2xl border border-gray-200 bg-white">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
      <div>
        <div class="text-sm font-semibold text-gray-900">Performance breakdown</div>
        <div class="text-xs text-gray-500">Start with your weakest areas and retake targeted quizzes.</div>
      </div>

      <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1 text-xs">
        <button type="button"
                @click="tab='topic'"
                :class="tab==='topic' ? 'bg-white border border-gray-200' : 'text-gray-600'"
                class="px-3 py-1.5 rounded-md">
          Topics
        </button>
        <button type="button"
                @click="tab='subject'"
                :class="tab==='subject' ? 'bg-white border border-gray-200' : 'text-gray-600'"
                class="px-3 py-1.5 rounded-md">
          Subjects
        </button>
        <button type="button"
                @click="tab='module'"
                :class="tab==='module' ? 'bg-white border border-gray-200' : 'text-gray-600'"
                class="px-3 py-1.5 rounded-md">
          Modules
        </button>
      </div>
    </div>

    <div class="p-5">

      <!-- TOPICS -->
      <div x-show="tab==='topic'">
        <?php if (empty($topicList)): ?>
          <p class="text-sm text-gray-600">No topic breakdown available.</p>
        <?php else: ?>
          <div class="text-xs text-gray-500 mb-3">
            Showing your weakest topics first (top <?= count($topicTop) ?>).
          </div>

          <div class="space-y-3">
            <?php foreach ($topicTop as $row): ?>
              <div class="rounded-xl border border-gray-200 px-4 py-3">
                <div class="flex items-center justify-between">
                  <div class="text-sm font-medium text-gray-900">
                    <?= htmlspecialchars($row['label']) ?>
                  </div>
                  <div class="text-sm text-gray-700">
                    <?= $row['correct'] ?>/<?= $row['total'] ?> (<?= $row['pct'] ?>%)
                  </div>
                </div>
                <div class="mt-2 h-2 rounded-full bg-gray-100">
                  <div class="h-2 rounded-full bg-gray-900" style="width: <?= $row['pct'] ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-4 text-xs text-gray-500">
            Tip: Retake quizzes focusing on 1 to 2 weak topics until you hit 80%+.
          </div>
        <?php endif; ?>
      </div>

      <!-- SUBJECTS -->
      <div x-show="tab==='subject'">
        <?php if (empty($subjectList)): ?>
          <p class="text-sm text-gray-600">No subject breakdown available.</p>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($subjectList as $row): ?>
              <div class="rounded-xl border border-gray-200 px-4 py-3">
                <div class="flex items-center justify-between">
                  <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['label']) ?></div>
                  <div class="text-sm text-gray-700"><?= $row['correct'] ?>/<?= $row['total'] ?> (<?= $row['pct'] ?>%)</div>
                </div>
                <div class="mt-2 h-2 rounded-full bg-gray-100">
                  <div class="h-2 rounded-full bg-gray-900" style="width: <?= $row['pct'] ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- MODULES -->
      <div x-show="tab==='module'">
        <?php if (empty($moduleList)): ?>
          <p class="text-sm text-gray-600">No module breakdown available.</p>
        <?php else: ?>
          <div class="space-y-3">
            <?php foreach ($moduleList as $row): ?>
              <div class="rounded-xl border border-gray-200 px-4 py-3">
                <div class="flex items-center justify-between">
                  <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($row['label']) ?></div>
                  <div class="text-sm text-gray-700"><?= $row['correct'] ?>/<?= $row['total'] ?> (<?= $row['pct'] ?>%)</div>
                </div>
                <div class="mt-2 h-2 rounded-full bg-gray-100">
                  <div class="h-2 rounded-full bg-gray-900" style="width: <?= $row['pct'] ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- Question Review -->
  <div class="mt-4">
    <div class="flex items-center justify-between mb-2">
      <div class="font-semibold text-sm">Review questions</div>
      <div class="text-xs text-gray-500"><?= count($questions ?? []) ?> items</div>
    </div>

    <?php if (empty($questions)): ?>
      <p class="text-xs text-gray-500">No questions found for this attempt.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($questions as $index => $q): ?>
          <?php
          $n      = $index + 1;
          $sel    = strtoupper((string)($q['selected_option'] ?? ''));
          $correctOption = strtoupper((string)($q['correct_option'] ?? ''));
          $isCorrect = ((int)($q['is_correct'] ?? 0) === 1);

          $borderClass = $sel === '' ? 'border-gray-200'
                        : ($isCorrect ? 'border-green-500' : 'border-red-500');
          ?>
          <div class="rounded-xl border <?= $borderClass ?> bg-white p-4">
            <div class="flex justify-between items-start gap-4 mb-2">
              <div class="min-w-0">
                <div class="text-xs text-gray-500 mb-1">Question <?= $n ?></div>
                <div class="text-sm font-medium">
                  <?= nl2br(htmlspecialchars((string)($q['question_text'] ?? ''))) ?>
                </div>
              </div>
              <div class="text-xs shrink-0">
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
                'A' => $q['option_a'] ?? '',
                'B' => $q['option_b'] ?? '',
                'C' => $q['option_c'] ?? '',
                'D' => $q['option_d'] ?? '',
              ];
              foreach ($opts as $letter => $text):
                $isCorrectOpt = ($letter === $correctOption);
                $isSelected   = ($letter === $sel);

                $bg = '';
                if ($isCorrectOpt) $bg = 'bg-green-50 border-green-200';
                if ($isSelected && !$isCorrectOpt) $bg = 'bg-red-50 border-red-200';
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

            <?php
              $trail = [];
              if (!empty($q['level_code']))   $trail[] = (string)$q['level_code'];
              if (!empty($q['module_code']))  $trail[] = (string)$q['module_code'];
              if (!empty($q['subject_name'])) $trail[] = (string)$q['subject_name'];
              if (!empty($q['topic_name']))   $trail[] = (string)$q['topic_name'];
            ?>
            <?php if (!empty($trail)): ?>
              <div class="mt-2 text-[11px] text-gray-500">
                <?= htmlspecialchars(implode(' · ', $trail)) ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>
