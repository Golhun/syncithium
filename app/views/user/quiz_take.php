<?php
/** @var array $attempt */
/** @var array $attemptQuestions */
/** @var int   $remainingSeconds */

$attemptId = (int)($attempt['id'] ?? 0);
$totalQ = (int)($attempt['total_questions'] ?? count($attemptQuestions ?? []));
$scoringMode = (string)($attempt['scoring_mode'] ?? 'standard');

function e3(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

function icon(string $name, string $variant = 'outline', string $cls = 'h-5 w-5'): string {
  $variant = ($variant === 'solid') ? 'solid' : 'outline';
  $path = "/public/assets/icons/heroicons/24/{$variant}/{$name}.svg";
  return '<img src="' . e3($path) . '" class="' . e3($cls) . '" alt="">';
}

function is_answered(array $q): bool {
  $sel = strtoupper((string)($q['selected_option'] ?? ''));
  return in_array($sel, ['A','B','C','D'], true);
}
?>
<div class="max-w-6xl mx-auto"
     x-data="quizTake(<?= (int)$remainingSeconds ?>)"
     x-init="startTimer()">

  <!-- Sticky header -->
  <div class="sticky top-0 z-20 bg-gray-50/90 backdrop-blur border-b border-gray-200 -mx-6 px-6 py-4 mb-5">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div class="min-w-0">
        <div class="flex items-center gap-2">
          <h1 class="text-xl font-semibold">Quiz in progress</h1>
          <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-xl ring-1 ring-gray-200 bg-white text-xs">
            <?= icon('academic-cap', 'outline', 'h-4 w-4') ?>
            <span class="text-gray-700">Attempt #<?= $attemptId ?></span>
          </span>
          <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-xl ring-1 ring-gray-200 bg-white text-xs">
            <?= $scoringMode === 'negative'
              ? icon('minus-circle', 'outline', 'h-4 w-4')
              : icon('check-circle', 'outline', 'h-4 w-4') ?>
            <span class="text-gray-700">
              <?= $scoringMode === 'negative' ? 'Negative marking' : 'Standard scoring' ?>
            </span>
          </span>
        </div>

        <p class="text-sm text-gray-600 mt-1">
          Scoring:
          <span class="font-semibold text-gray-800">
            <?= $scoringMode === 'negative' ? '+1 correct, -1 wrong' : '+1 correct, 0 wrong' ?>
          </span>
          <span class="text-gray-400">·</span>
          <span class="text-gray-700"><?= $totalQ ?> questions</span>
        </p>

        <div class="mt-2 text-xs text-gray-500 flex items-center gap-2">
          <?= icon('information-circle', 'outline', 'h-4 w-4') ?>
          <span>When time runs out, the quiz auto-submits.</span>
        </div>
      </div>

      <!-- Timer + quick actions -->
      <div class="flex items-center gap-3 flex-wrap justify-start md:justify-end">
        <div class="inline-flex items-center gap-2 px-3 py-2 rounded-2xl border border-gray-200 bg-white">
          <div class="text-gray-500"><?= icon('clock', 'outline', 'h-5 w-5') ?></div>
          <div>
            <div class="text-[11px] text-gray-500 leading-none">Time remaining</div>
            <div class="font-mono text-sm font-semibold" x-text="timeDisplay"></div>
          </div>
        </div>

        <button type="button"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-2xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
                @click="scrollToFirstUnanswered()"
                title="Jump to the first unanswered question">
          <?= icon('arrow-right-circle', 'outline', 'h-5 w-5') ?>
          <span>Next unanswered</span>
        </button>

        <button type="button"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-2xl bg-gray-900 text-white hover:opacity-90 text-sm"
                @click="confirmSubmit()">
          <?= icon('paper-airplane', 'solid', 'h-5 w-5') ?>
          <span>Submit</span>
        </button>
      </div>
    </div>

    <!-- Progress strip -->
    <div class="mt-4 rounded-2xl border border-gray-200 bg-white px-4 py-3">
      <div class="flex items-center justify-between text-xs text-gray-600">
        <div class="flex items-center gap-2">
          <?= icon('chart-bar', 'outline', 'h-4 w-4') ?>
          <span>
            Answered:
            <span class="font-semibold text-gray-900" x-text="answeredCount"></span>
            / <?= $totalQ ?>
          </span>
        </div>
        <div class="flex items-center gap-2">
          <?= icon('bookmark-square', 'outline', 'h-4 w-4') ?>
          <span>
            Marked:
            <span class="font-semibold text-gray-900" x-text="markedCount"></span>
          </span>
        </div>
      </div>

      <div class="mt-2 h-2 rounded-full bg-gray-100 overflow-hidden">
        <div class="h-2 rounded-full bg-sky-600" :style="'width:' + progressPct + '%'"></div>
      </div>
    </div>
  </div>

  <form id="quiz-form"
        method="post"
        action="/public/index.php?r=quiz_submit&attempt_id=<?= $attemptId ?>"
        class="grid grid-cols-1 lg:grid-cols-12 gap-5">
    <?= csrf_field() ?>
    <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">

    <!-- Navigator (desktop) -->
    <aside class="lg:col-span-3 order-2 lg:order-1">
      <div class="lg:sticky lg:top-32 rounded-2xl border border-gray-200 bg-white overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
          <div class="flex items-center justify-between">
            <div class="text-sm font-semibold flex items-center gap-2">
              <?= icon('squares-2x2', 'outline', 'h-5 w-5') ?>
              <span>Navigator</span>
            </div>
            <span class="text-xs text-gray-500">Jump</span>
          </div>
        </div>

        <div class="p-4">
          <div class="text-xs text-gray-500 mb-3">
            Legend:
            <span class="inline-flex items-center gap-1 ml-2">
              <span class="inline-block h-2.5 w-2.5 rounded-full bg-sky-600"></span> answered
            </span>
            <span class="inline-flex items-center gap-1 ml-2">
              <span class="inline-block h-2.5 w-2.5 rounded-full bg-amber-500"></span> marked
            </span>
            <span class="inline-flex items-center gap-1 ml-2">
              <span class="inline-block h-2.5 w-2.5 rounded-full bg-gray-300"></span> empty
            </span>
          </div>

          <div class="grid grid-cols-6 gap-2">
            <?php foreach ($attemptQuestions as $index => $q): ?>
              <?php
                $num = $index + 1;
                $aqId = (int)($q['aq_id'] ?? 0);
                $answered = is_answered($q);
                $marked = ((int)($q['marked_flag'] ?? 0) === 1);
              ?>
              <button
                type="button"
                class="h-10 w-10 rounded-xl border text-sm font-semibold flex items-center justify-center hover:bg-gray-50"
                data-nav-btn="1"
                data-num="<?= $num ?>"
                data-aqid="<?= $aqId ?>"
                data-answered="<?= $answered ? '1' : '0' ?>"
                data-marked="<?= $marked ? '1' : '0' ?>"
                @click="scrollToQuestion(<?= $num ?>)"
                :class="navClass(<?= $num ?>)"
                title="Go to Question <?= $num ?>"
              >
                <?= $num ?>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="mt-4 flex gap-2">
            <button type="button"
                    class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
                    @click="scrollToTop()">
              <?= icon('arrow-up', 'outline', 'h-5 w-5') ?>
              <span>Top</span>
            </button>
            <button type="button"
                    class="w-full inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
                    @click="scrollToBottom()">
              <?= icon('arrow-down', 'outline', 'h-5 w-5') ?>
              <span>Bottom</span>
            </button>
          </div>
        </div>
      </div>
    </aside>

    <!-- Questions -->
    <section class="lg:col-span-9 order-1 lg:order-2 space-y-4">
      <?php foreach ($attemptQuestions as $index => $q): ?>
        <?php
          $number = $index + 1;
          $aqId = (int)($q['aq_id'] ?? 0);
          $selected = strtoupper((string)($q['selected_option'] ?? ''));
          $marked = ((int)($q['marked_flag'] ?? 0) === 1);

          $options = [
            'A' => $q['option_a'],
            'B' => $q['option_b'],
            'C' => $q['option_c'],
            'D' => $q['option_d'],
          ];
        ?>

        <article id="q<?= $number ?>"
                 data-question="1"
                 data-aqid="<?= $aqId ?>"
                 class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
          <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex items-start justify-between gap-4">
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-xl bg-white ring-1 ring-gray-200 text-xs font-semibold">
                    Q<?= $number ?>
                  </span>

                  <template x-if="isAnswered(<?= $aqId ?>)">
                    <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-xl bg-sky-50 text-sky-700 text-xs border border-sky-200">
                      <?= icon('check-circle', 'outline', 'h-4 w-4') ?>
                      Answered
                    </span>
                  </template>

                  <template x-if="isMarked(<?= $aqId ?>)">
                    <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-xl bg-amber-50 text-amber-800 text-xs border border-amber-200">
                      <?= icon('bookmark-square', 'outline', 'h-4 w-4') ?>
                      Marked
                    </span>
                  </template>
                </div>

                <div class="mt-3 text-sm font-medium text-gray-900 whitespace-pre-wrap">
                  <?= e3((string)($q['question_text'] ?? '')) ?>
                </div>
              </div>

              <!-- Mark toggle -->
              <label class="flex items-center gap-2 text-xs text-gray-700 select-none">
                <input type="checkbox"
                       name="marked[<?= $aqId ?>]"
                       value="1"
                       class="rounded border-gray-300"
                       <?= $marked ? 'checked' : '' ?>
                       @change="syncState()">
                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-xl border border-gray-200 bg-white">
                  <?= $marked ? icon('bookmark-square', 'solid', 'h-4 w-4') : icon('bookmark-square', 'outline', 'h-4 w-4') ?>
                  <span>Mark</span>
                </span>
              </label>
            </div>
          </div>

          <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
              <?php foreach ($options as $letter => $text): ?>
                <?php
                  $isChecked = ($selected === $letter);
                  $optText = (string)($text ?? '');
                ?>
                <label class="group flex gap-3 p-3 rounded-2xl border border-gray-200 hover:bg-gray-50 cursor-pointer transition">
                  <input type="radio"
                         name="answer[<?= $aqId ?>]"
                         value="<?= e3($letter) ?>"
                         class="mt-1 border-gray-300"
                         <?= $isChecked ? 'checked' : '' ?>
                         @change="syncState()">

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                      <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center h-7 w-7 rounded-xl bg-gray-100 text-gray-700 font-mono text-xs font-semibold">
                          <?= e3($letter) ?>
                        </span>
                        <span class="text-gray-900 whitespace-pre-wrap"><?= e3($optText) ?></span>
                      </div>

                      <span class="opacity-0 group-hover:opacity-100 transition text-gray-400">
                        <?= icon('cursor-arrow-rays', 'outline', 'h-5 w-5') ?>
                      </span>
                    </div>

                    <div class="mt-2">
                      <template x-if="isSelectedOption(<?= $aqId ?>, '<?= e3($letter) ?>')">
                        <div class="inline-flex items-center gap-2 text-xs text-sky-700 bg-sky-50 border border-sky-200 px-2.5 py-1 rounded-xl">
                          <?= icon('check-circle', 'outline', 'h-4 w-4') ?>
                          Selected
                        </div>
                      </template>
                    </div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>

      <!-- Footer actions -->
      <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="text-sm text-gray-600 flex items-center gap-2">
          <?= icon('shield-check', 'outline', 'h-5 w-5') ?>
          <span>Your progress is kept until you submit. Auto-submit triggers at time end.</span>
        </div>
        <div class="flex items-center gap-2">
          <button type="button"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
                  @click="scrollToTop()">
            <?= icon('arrow-up', 'outline', 'h-5 w-5') ?>
            <span>Back to top</span>
          </button>

          <button type="button"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-900 text-white hover:opacity-90 text-sm"
                  @click="confirmSubmit()">
            <?= icon('paper-airplane', 'solid', 'h-5 w-5') ?>
            <span>Submit quiz</span>
          </button>
        </div>
      </div>
    </section>
  </form>
</div>

<script>
function quizTake(initialSeconds) {
  return {
    remaining: Number(initialSeconds || 0),
    timeDisplay: '',
    answeredCount: 0,
    markedCount: 0,
    progressPct: 0,

    format(seconds) {
      const m = Math.floor(seconds / 60);
      const s = seconds % 60;
      return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
    },

    // ===== DOM state helpers =====
    syncState() {
      // Count answered and marked based on DOM
      const qEls = document.querySelectorAll('[data-question="1"]');
      let answered = 0;
      let marked = 0;

      qEls.forEach(el => {
        const aqid = el.getAttribute('data-aqid');
        const checked = el.querySelector('input[type="radio"]:checked');
        if (checked) answered += 1;

        const mark = el.querySelector('input[type="checkbox"][name^="marked["]');
        if (mark && mark.checked) marked += 1;

        // Update nav button styling
        this.refreshNavButtonFor(aqid, !!checked, !!(mark && mark.checked));
      });

      this.answeredCount = answered;
      this.markedCount = marked;

      const total = qEls.length || 1;
      this.progressPct = Math.min(100, Math.round((answered / total) * 100));
    },

    refreshNavButtonFor(aqid, answered, marked) {
      const btn = document.querySelector(`[data-nav-btn="1"][data-aqid="${aqid}"]`);
      if (!btn) return;
      btn.dataset.answered = answered ? '1' : '0';
      btn.dataset.marked = marked ? '1' : '0';
      btn.className = this.navClass(Number(btn.dataset.num || 0));
    },

    navClass(num) {
      const btn = document.querySelector(`[data-nav-btn="1"][data-num="${num}"]`);
      const answered = btn && btn.dataset.answered === '1';
      const marked = btn && btn.dataset.marked === '1';

      // Priority: marked > answered > empty
      if (marked) return 'h-10 w-10 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 font-semibold hover:bg-amber-100';
      if (answered) return 'h-10 w-10 rounded-xl border border-sky-200 bg-sky-50 text-sky-800 font-semibold hover:bg-sky-100';
      return 'h-10 w-10 rounded-xl border border-gray-200 bg-white text-gray-700 font-semibold hover:bg-gray-50';
    },

    isAnswered(aqid) {
      const el = document.querySelector(`[data-question="1"][data-aqid="${aqid}"]`);
      if (!el) return false;
      return !!el.querySelector('input[type="radio"]:checked');
    },

    isMarked(aqid) {
      const el = document.querySelector(`[data-question="1"][data-aqid="${aqid}"]`);
      if (!el) return false;
      const mark = el.querySelector('input[type="checkbox"][name^="marked["]');
      return !!(mark && mark.checked);
    },

    isSelectedOption(aqid, letter) {
      const el = document.querySelector(`[data-question="1"][data-aqid="${aqid}"]`);
      if (!el) return false;
      const checked = el.querySelector('input[type="radio"]:checked');
      return checked && String(checked.value || '') === String(letter || '');
    },

    // ===== Navigation =====
    scrollToQuestion(num) {
      const el = document.getElementById('q' + num);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    },

    scrollToTop() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    scrollToBottom() {
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    },

    scrollToFirstUnanswered() {
      const qEls = Array.from(document.querySelectorAll('[data-question="1"]'));
      const first = qEls.find(el => !el.querySelector('input[type="radio"]:checked'));
      if (first) first.scrollIntoView({ behavior: 'smooth', block: 'start' });
      else this.scrollToBottom();
    },

    confirmSubmit() {
      const answered = this.answeredCount;
      const total = document.querySelectorAll('[data-question="1"]').length || 0;

      const msg = (answered < total)
        ? `You have answered ${answered} of ${total}. Submit anyway?`
        : 'Submit your quiz now?';

      if (confirm(msg)) {
        const form = document.getElementById('quiz-form');
        if (form) form.submit();
      }
    },

    // ===== Timer =====
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
      // Initial state scan
      this.syncState();
      this.tick();
    }
  }
}
</script>
