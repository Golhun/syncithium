<?php
/** @var array $attempt */
/** @var array $questions */
/** @var int $remainingSeconds */
?>
<div
  class="max-w-4xl mx-auto"
  x-data="quizTake({
    remainingSeconds: <?= (int)$remainingSeconds ?>,
  })"
  x-init="startTimer()"
>
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-xl font-semibold">Quiz in progress</h1>
      <p class="text-xs text-gray-500">
        Mode: <?= htmlspecialchars((string)$attempt['scoring_mode']) ?>,
        Questions: <?= (int)$attempt['total_questions'] ?>
      </p>
    </div>
    <div class="text-right">
      <div class="text-xs text-gray-500">Time remaining</div>
      <div class="text-lg font-mono" x-text="formattedTime"></div>
    </div>
  </div>

  <form method="post" x-ref="quizForm">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$attempt['id'] ?>">

    <div class="space-y-4">
      <?php foreach ($questions as $idx => $q): ?>
        <div class="p-4 rounded-xl border border-gray-200 bg-white">
          <div class="flex justify-between mb-2">
            <div class="text-sm font-medium">
              Question <?= $idx + 1 ?>
              <span class="text-xs text-gray-400">
                (Topic: <?= htmlspecialchars((string)$q['topic_name']) ?>)
              </span>
            </div>
          </div>

          <p class="text-sm mb-3">
            <?= nl2br(htmlspecialchars((string)$q['question_text'])) ?>
          </p>

          <?php
            $aqId = (int)$q['aq_id'];
            $sel  = strtoupper((string)($q['selected_option'] ?? ''));
          ?>

          <div class="space-y-1 text-sm">
            <?php
              $options = [
                'A' => $q['option_a'],
                'B' => $q['option_b'],
                'C' => $q['option_c'],
                'D' => $q['option_d'],
              ];
            ?>
            <?php foreach ($options as $key => $text): ?>
              <label class="flex items-center gap-2 px-2 py-1 rounded-lg border border-transparent hover:bg-gray-50 cursor-pointer">
                <input
                  type="radio"
                  name="answers[<?= $aqId ?>]"
                  value="<?= $key ?>"
                  class="border-gray-300"
                  <?= $sel === $key ? 'checked' : '' ?>
                >
                <span class="font-medium mr-1"><?= $key ?>.</span>
                <span><?= htmlspecialchars((string)$text) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="flex justify-end mt-6">
      <button
        type="submit"
        class="px-4 py-2 rounded-lg border border-sky-400 bg-sky-500 text-white text-sm"
        @click="submitting = true"
      >
        Submit quiz
      </button>
    </div>
  </form>
</div>

<script>
function quizTake(cfg) {
  return {
    remaining: cfg.remainingSeconds || 0,
    formattedTime: '',
    submitting: false,
    timerId: null,

    startTimer() {
      this.updateFormatted();
      if (this.remaining <= 0) {
        return;
      }
      this.timerId = setInterval(() => {
        if (this.remaining <= 0) {
          clearInterval(this.timerId);
          if (!this.submitting) {
            this.submitting = true;
            this.$refs.quizForm.submit();
          }
          return;
        }
        this.remaining -= 1;
        this.updateFormatted();
      }, 1000);
    },

    updateFormatted() {
      const m = Math.floor(this.remaining / 60);
      const s = this.remaining % 60;
      this.formattedTime = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    },
  };
}
</script>
