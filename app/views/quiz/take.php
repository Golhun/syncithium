<?php
/** @var array $attempt */
/** @var array $questions */
/** @var int   $remainingSeconds */
?>
<div class="max-w-5xl mx-auto"
     x-data="quizTake(<?= (int)$remainingSeconds ?>)"
     x-init="startTimer()">

  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-xl font-semibold">Quiz in progress</h1>
      <p class="text-xs text-gray-600 mt-1">
        Scoring mode:
        <span class="font-medium">
          <?= $attempt['scoring_mode'] === 'negative'
            ? '+1 correct, -1 wrong'
            : '+1 correct, 0 wrong' ?>
        </span>
        · Questions: <?= (int)$attempt['total_questions'] ?>
      </p>
    </div>

    <div class="text-right">
      <div class="text-xs text-gray-500 mb-1">Time remaining</div>
      <div class="px-3 py-2 rounded-lg border border-gray-200 inline-flex items-center gap-2">
        <span class="text-xs text-gray-500">⏱</span>
        <span class="font-mono text-sm" x-text="timeDisplay"></span>
      </div>
    </div>
  </div>

  <form id="quiz-form"
        method="post"
        action="/public/index.php?r=quiz_take&id=<?= (int)$attempt['id'] ?>"
        class="space-y-4">
    <?= csrf_field() ?>

    <?php foreach ($questions as $index => $q): ?>
      <?php
      $number   = $index + 1;
      $selected = strtoupper((string)($q['selected_option'] ?? ''));
      ?>
      <div class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs text-gray-500 mb-1">Question <?= $number ?></div>
            <div class="text-sm font-medium mb-3">
              <?= nl2br(htmlspecialchars((string)$q['question_text'])) ?>
            </div>
          </div>
          <label class="flex items-center gap-2 text-xs text-gray-500">
            <input type="checkbox"
                   name="marked[<?= (int)$q['aq_id'] ?>]"
                   value="1"
                   class="rounded border-gray-300"
                   <?= ((int)($q['marked_flag'] ?? 0) === 1) ? 'checked' : '' ?>>
            <span>Mark for review</span>
          </label>
        </div>

        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
          <?php
          $options = [
            'A' => $q['option_a'],
            'B' => $q['option_b'],
            'C' => $q['option_c'],
            'D' => $q['option_d'],
          ];
          foreach ($options as $letter => $text):
          ?>
          <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50">
            <input type="radio"
                   name="answers[<?= (int)$q['aq_id'] ?>]"
                   value="<?= $letter ?>"
                   class="border-gray-300"
                   <?= ($selected === $letter) ? 'checked' : '' ?>>
            <span class="font-mono text-xs text-gray-500"><?= $letter ?>.</span>
            <span><?= htmlspecialchars((string)$text) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="flex items-center justify-between mt-4">
      <p class="text-xs text-gray-500">
        When time runs out, your answers are auto-submitted.
      </p>
      <button type="submit"
              class="px-4 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-sm">
        Submit quiz now
      </button>
    </div>
  </form>
</div>

<script>
function quizTake(initialSeconds) {
  return {
    remaining: initialSeconds,
    timeDisplay: '',

    format(seconds) {
      const m = Math.floor(seconds / 60);
      const s = seconds % 60;
      return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    },

    tick() {
      if (this.remaining <= 0) {
        const form = document.getElementById('quiz-form');
        if (form) form.submit();
        return;
      }
      this.timeDisplay = this.format(this.remaining);
      this.remaining -= 1;
      setTimeout(() => this.tick(), 1000);
    },

    startTimer() {
      this.timeDisplay = this.format(this.remaining);
      this.tick();
    }
  }
}
</script>
