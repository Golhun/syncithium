<?php
declare(strict_types=1);

/** @var array $attempt */
/** @var array $questions */
/** @var int $remainingSeconds */

$attemptId = (int)$attempt['id'];
$total = count($questions);

// Pre-fill JS state from DB
$initialAnswers = [];
$initialMarked  = [];

foreach ($questions as $q) {
    $aqid = (int)$q['aq_id'];
    $sel  = strtoupper(trim((string)($q['selected_option'] ?? '')));
    if (in_array($sel, ['A','B','C','D'], true)) {
        $initialAnswers[(string)$aqid] = $sel;
    }
    $initialMarked[(string)$aqid] = !empty($q['marked_flag']) ? 1 : 0;
}

?>
<div
  class="max-w-6xl mx-auto"
  x-data="quizTakeScreen({
    attemptId: <?= (int)$attemptId ?>,
    remainingSeconds: <?= (int)$remainingSeconds ?>,
    totalQuestions: <?= (int)$total ?>,
    initialAnswers: <?= json_encode($initialAnswers, JSON_UNESCAPED_SLASHES) ?>,
    initialMarked: <?= json_encode($initialMarked, JSON_UNESCAPED_SLASHES) ?>
  })"
  x-init="init()"
>

  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-semibold">Quiz</h1>
      <p class="text-xs text-gray-500 mt-1">
        Attempt #<?= (int)$attemptId ?>, Questions: <?= (int)$total ?>
      </p>
    </div>

    <div class="text-right">
      <div class="text-xs text-gray-500">Time remaining</div>
      <div class="text-2xl font-semibold tabular-nums" x-text="timeLabel"></div>
      <div class="text-[11px] text-gray-500" x-show="remaining <= 60">
        Less than 1 minute left, submit soon.
      </div>
    </div>
  </div>

  <form
    method="post"
    action="/public/index.php?r=quiz_take&id=<?= (int)$attemptId ?>"
    x-ref="quizForm"
    class="grid grid-cols-1 lg:grid-cols-12 gap-4"
  >
    <?= csrf_field() ?>

    <!-- LEFT NAV (Moodle-like) -->
    <aside class="lg:col-span-3">
      <div class="rounded-xl border border-gray-200 bg-white p-4 sticky top-4 space-y-4">

        <!-- Per-page -->
        <div class="flex items-center justify-between">
          <div class="text-xs font-medium text-gray-700">Questions per page</div>
          <select
            class="text-xs px-2 py-1 rounded-lg border border-gray-200"
            x-model.number="perPage"
            @change="onPerPageChange()"
          >
            <option :value="1">1</option>
            <option :value="5">5</option>
            <option :value="10">10</option>
            <option :value="20">20</option>
            <option :value="50">50</option>
            <option :value="9999">All</option>
          </select>
        </div>

        <!-- Legend -->
        <div class="text-[11px] text-gray-500 space-y-1">
          <div class="flex items-center gap-2">
            <span class="inline-block w-3 h-3 rounded bg-gray-200"></span>
            <span>Unanswered</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-block w-3 h-3 rounded bg-sky-600"></span>
            <span>Answered</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-block w-3 h-3 rounded bg-pink-600"></span>
            <span>Flagged (return later)</span>
          </div>
        </div>

        <!-- Question number grid -->
        <div class="grid grid-cols-6 gap-2">
          <template x-for="(q, idx) in questionIndex" :key="q.aqId">
            <button
              type="button"
              class="h-9 rounded-lg text-xs font-semibold border"
              @click="jumpToIndex(idx)"
              :class="navClass(q.aqId)"
              x-text="idx + 1"
              :title="navTitle(q.aqId)"
            ></button>
          </template>
        </div>

        <!-- Paging controls -->
        <div class="flex items-center justify-between pt-2 border-t border-gray-100">
          <button
            type="button"
            class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-xs hover:bg-gray-50"
            @click="prevPage()"
            :disabled="page <= 1"
            :class="page <= 1 ? 'opacity-50 cursor-not-allowed' : ''"
          >
            Prev
          </button>

          <div class="text-xs text-gray-600">
            Page <span class="font-semibold" x-text="page"></span> /
            <span class="font-semibold" x-text="totalPages"></span>
          </div>

          <button
            type="button"
            class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-xs hover:bg-gray-50"
            @click="nextPage()"
            :disabled="page >= totalPages"
            :class="page >= totalPages ? 'opacity-50 cursor-not-allowed' : ''"
          >
            Next
          </button>
        </div>

        <!-- Submit -->
        <div class="pt-2 border-t border-gray-100 space-y-2">
          <div class="text-xs text-gray-500">
            Answered: <span class="font-semibold" x-text="answeredCount"></span> /
            <?= (int)$total ?>
          </div>

          <button
            type="submit"
            class="w-full px-4 py-2 rounded-lg text-sm text-white bg-sky-600 hover:bg-sky-700 border border-sky-600"
          >
            Submit quiz
          </button>

          <button
            type="button"
            class="w-full px-4 py-2 rounded-lg text-xs border border-gray-200 bg-white hover:bg-gray-50"
            @click="scrollToTop()"
          >
            Back to top
          </button>
        </div>

      </div>
    </aside>

    <!-- MAIN QUESTIONS -->
    <section class="lg:col-span-9 space-y-4">

      <?php foreach ($questions as $i => $q): ?>
        <?php
          $aqid = (int)$q['aq_id'];
          $idx  = (int)$i; // 0-based index
          $topic = (string)($q['topic_name'] ?? '');
          $qt = (string)($q['question_text'] ?? '');
        ?>

        <div
          class="rounded-xl border border-gray-200 bg-white p-4"
          x-show="isVisibleIndex(<?= $idx ?>)"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-xs text-gray-500">
                Q<?= $idx + 1 ?> of <?= (int)$total ?>
                <?php if ($topic !== ''): ?>
                  <span class="ml-2 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">
                    <?= htmlspecialchars($topic) ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="mt-2 text-sm font-medium leading-relaxed">
                <?= nl2br(htmlspecialchars($qt)) ?>
              </div>
            </div>

            <div class="flex flex-col items-end gap-2">
              <!-- Flag for later -->
              <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                <input
                  type="checkbox"
                  name="marked[<?= $aqid ?>]"
                  value="1"
                  class="rounded border-gray-300"
                  x-model="marked['<?= $aqid ?>']"
                >
                Flag
              </label>

              <!-- Report issue -->
              <button
                type="button"
                class="text-xs px-3 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
                @click="openReportModal(<?= (int)$q['question_id'] ?>, <?= $aqid ?>)"
              >
                Report issue
              </button>
            </div>
          </div>

          <!-- Options -->
          <div class="mt-4 grid grid-cols-1 gap-2 text-sm">
            <?php
              $opts = [
                'A' => (string)($q['option_a'] ?? ''),
                'B' => (string)($q['option_b'] ?? ''),
                'C' => (string)($q['option_c'] ?? ''),
                'D' => (string)($q['option_d'] ?? ''),
              ];
            ?>
            <?php foreach ($opts as $key => $val): ?>
              <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer">
                <input
                  type="radio"
                  name="answers[<?= $aqid ?>]"
                  value="<?= $key ?>"
                  class="mt-1"
                  x-model="answers['<?= $aqid ?>']"
                >
                <div>
                  <div class="text-xs font-semibold text-gray-600"><?= $key ?></div>
                  <div class="text-sm text-gray-800"><?= nl2br(htmlspecialchars($val)) ?></div>
                </div>
              </label>
            <?php endforeach; ?>

            <!-- Clear answer -->
            <div class="pt-2">
              <button
                type="button"
                class="text-xs px-3 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
                @click="clearAnswer('<?= $aqid ?>')"
              >
                Clear answer
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Bottom paging -->
      <div class="flex items-center justify-between pt-2">
        <button
          type="button"
          class="px-4 py-2 rounded-lg border border-gray-200 bg-white text-sm hover:bg-gray-50"
          @click="prevPage()"
          :disabled="page <= 1"
          :class="page <= 1 ? 'opacity-50 cursor-not-allowed' : ''"
        >
          Prev page
        </button>

        <div class="text-xs text-gray-600">
          Page <span class="font-semibold" x-text="page"></span> /
          <span class="font-semibold" x-text="totalPages"></span>
        </div>

        <button
          type="button"
          class="px-4 py-2 rounded-lg border border-gray-200 bg-white text-sm hover:bg-gray-50"
          @click="nextPage()"
          :disabled="page >= totalPages"
          :class="page >= totalPages ? 'opacity-50 cursor-not-allowed' : ''"
        >
          Next page
        </button>
      </div>

    </section>
  </form>

  <!-- Report Modal -->
  <div
    class="fixed inset-0 z-[255] bg-black/40 flex items-center justify-center px-4"
    x-show="report.open"
    x-cloak
  >
    <div class="w-full max-w-lg rounded-2xl bg-white border border-gray-200 p-4">
      <div class="flex items-center justify-between">
        <div class="text-sm font-semibold">Report an issue</div>
        <button type="button" class="text-sm text-gray-500" @click="closeReportModal()">✕</button>
      </div>

      <div class="mt-2 text-xs text-gray-500">
        Tell us what is wrong, for example wrong answer, unclear question, typo, duplicate, missing option.
      </div>

      <div class="mt-3">
        <textarea
          class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"
          rows="4"
          placeholder="Describe the issue..."
          x-model="report.message"
        ></textarea>
      </div>

      <div class="mt-3 flex items-center justify-end gap-2">
        <button
          type="button"
          class="px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm hover:bg-gray-50"
          @click="closeReportModal()"
        >
          Cancel
        </button>

        <button
          type="button"
          class="px-3 py-2 rounded-lg border border-sky-600 bg-sky-600 text-white text-sm hover:bg-sky-700"
          @click="submitReport()"
          :disabled="report.saving || !report.message.trim()"
          :class="(report.saving || !report.message.trim()) ? 'opacity-60 cursor-not-allowed' : ''"
        >
          Submit report
        </button>
      </div>
    </div>
  </div>

</div>

<script>
function quizTakeScreen(cfg) {
  return {
    attemptId: cfg.attemptId,
    remaining: Number(cfg.remainingSeconds || 0),
    timeLabel: '00:00',

    // paging
    page: 1,
    perPage: 10,
    totalQuestions: Number(cfg.totalQuestions || 0),
    totalPages: 1,

    // answers + flags
    answers: cfg.initialAnswers || {},
    marked: cfg.initialMarked || {},

    // question index list
    questionIndex: [],

    // report modal
    report: {
      open: false,
      questionId: null,
      aqId: null,
      message: '',
      saving: false
    },

    get answeredCount() {
      let c = 0;
      for (const k in this.answers) {
        const v = (this.answers[k] || '').toString().toUpperCase().trim();
        if (['A','B','C','D'].includes(v)) c++;
      }
      return c;
    },

    init() {
      // per-page persistence
      const savedPerPage = Number(localStorage.getItem('quiz_per_page') || 10);
      this.perPage = [1,5,10,20,50,9999].includes(savedPerPage) ? savedPerPage : 10;

      // build index
      this.questionIndex = Array.from({ length: this.totalQuestions }, (_, i) => ({
        aqId: this.getAqIdByIndex(i),
        idx: i
      }));

      this.recalcPages();
      this.updateTimeLabel();
      this.startTimer();
    },

    // helper: map index -> aqId by reading DOM inputs
    // (we rely on the rendered order, stable)
    getAqIdByIndex(i) {
      // Each question has radio inputs name="answers[aqid]"
      // We can parse one from DOM after Alpine init if needed.
      // Since we also have initialAnswers/marked keyed by aqid, we’ll derive from those keys when possible.
      const keys = Object.keys(this.marked || {});
      if (keys.length === this.totalQuestions) {
        return keys[i];
      }
      // Fallback: best effort
      return keys[i] || String(i + 1);
    },

    recalcPages() {
      const pp = this.perPage >= 9999 ? this.totalQuestions : this.perPage;
      this.totalPages = Math.max(1, Math.ceil(this.totalQuestions / Math.max(1, pp)));
      if (this.page > this.totalPages) this.page = this.totalPages;
    },

    onPerPageChange() {
      localStorage.setItem('quiz_per_page', String(this.perPage));
      this.page = 1;
      this.recalcPages();
      this.scrollToTop();
    },

    isVisibleIndex(idx) {
      const pp = this.perPage >= 9999 ? this.totalQuestions : this.perPage;
      const start = (this.page - 1) * pp;
      const end = start + pp;
      return idx >= start && idx < end;
    },

    jumpToIndex(idx) {
      const pp = this.perPage >= 9999 ? this.totalQuestions : this.perPage;
      this.page = Math.floor(idx / Math.max(1, pp)) + 1;
      this.recalcPages();
      this.scrollToTop();
    },

    prevPage() {
      if (this.page > 1) {
        this.page--;
        this.scrollToTop();
      }
    },

    nextPage() {
      if (this.page < this.totalPages) {
        this.page++;
        this.scrollToTop();
      }
    },

    navTitle(aqId) {
      const answered = ['A','B','C','D'].includes(((this.answers[aqId] || '') + '').toUpperCase().trim());
      const flagged  = Number(this.marked[aqId] || 0) === 1;
      if (flagged) return 'Flagged';
      if (answered) return 'Answered';
      return 'Unanswered';
    },

    navClass(aqId) {
      const answered = ['A','B','C','D'].includes(((this.answers[aqId] || '') + '').toUpperCase().trim());
      const flagged  = Number(this.marked[aqId] || 0) === 1;

      if (flagged) return 'bg-pink-600 text-white border-pink-700';
      if (answered) return 'bg-sky-600 text-white border-sky-700';
      return 'bg-gray-100 text-gray-800 border-gray-200 hover:bg-gray-200';
    },

    clearAnswer(aqId) {
      this.answers[aqId] = '';
    },

    scrollToTop() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    // Timer
    startTimer() {
      const tick = () => {
        if (this.remaining <= 0) {
          this.timeLabel = '00:00';
          // Auto-submit once
          if (this.$refs && this.$refs.quizForm) {
            this.$refs.quizForm.submit();
          }
          return;
        }

        this.remaining -= 1;
        this.updateTimeLabel();
        setTimeout(tick, 1000);
      };

      setTimeout(tick, 1000);
    },

    updateTimeLabel() {
      const s = Math.max(0, Number(this.remaining || 0));
      const mm = String(Math.floor(s / 60)).padStart(2, '0');
      const ss = String(s % 60).padStart(2, '0');
      this.timeLabel = `${mm}:${ss}`;
    },

    // Report issue
    openReportModal(questionId, aqId) {
      this.report.open = true;
      this.report.questionId = Number(questionId);
      this.report.aqId = Number(aqId);
      this.report.message = '';
      this.report.saving = false;
    },

    closeReportModal() {
      this.report.open = false;
      this.report.questionId = null;
      this.report.aqId = null;
      this.report.message = '';
      this.report.saving = false;
    },

    async submitReport() {
      const msg = (this.report.message || '').trim();
      if (!msg) return;

      this.report.saving = true;

      try {
        const fd = new FormData();
        fd.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        fd.append('question_id', String(this.report.questionId));
        fd.append('attempt_id', String(this.attemptId));
        fd.append('report_type', 'issue');
        fd.append('message', msg);

        const res = await fetch('/public/index.php?r=question_report_create', {
          method: 'POST',
          body: fd
        });

        const data = await res.json().catch(() => null);

        if (!res.ok || !data || data.ok !== true) {
          const err = (data && data.error) ? data.error : 'Could not submit report.';
          if (window.alertify) alertify.error(err);
          else alert(err);
          this.report.saving = false;
          return;
        }

        if (window.alertify) alertify.success('Report submitted. Admin will review it.');
        this.closeReportModal();
      } catch (e) {
        if (window.alertify) alertify.error('Network error. Try again.');
        this.report.saving = false;
      }
    }
  }
}
</script>
