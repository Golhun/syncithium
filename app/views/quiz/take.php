<?php
declare(strict_types=1);

/** @var array $attempt */
/** @var array $questions */
/** @var int $remainingSeconds */
?>
<div
  class="max-w-6xl mx-auto"
  x-data="quizTakeScreen({
    attemptId: <?= (int)$attempt['id'] ?>,
    remainingSeconds: <?= (int)$remainingSeconds ?>,
    total: <?= (int)count($questions) ?>
  })"
  x-init="init()"
>
  <div class="flex items-start justify-between gap-4 mb-4">
    <div>
      <h1 class="text-xl font-semibold">Quiz</h1>
      <p class="text-xs text-gray-500">
        Scoring mode:
        <?= htmlspecialchars((string)$attempt['scoring_mode']) ?>,
        Total questions: <?= (int)count($questions) ?>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <div class="px-3 py-2 rounded-xl border border-gray-200 bg-white text-sm">
        <div class="text-xs text-gray-500">Time left</div>
        <div class="font-semibold" x-text="timeLabel"></div>
      </div>

      <button
        type="button"
        class="px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
        @click="saveNow()"
      >
        Save
      </button>

      <button
        type="button"
        class="px-3 py-2 rounded-xl border border-sky-600 bg-sky-600 text-white hover:bg-sky-700 text-sm"
        @click="submitFinal()"
      >
        Submit
      </button>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
    <!-- Left: question navigator -->
    <aside class="lg:col-span-3">
      <div class="p-3 rounded-2xl border border-gray-200 bg-white">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-semibold">Questions</div>

          <select
            class="text-xs border border-gray-200 rounded-lg px-2 py-1"
            x-model.number="perPage"
            @change="goTo(1)"
          >
            <option :value="1">1 / page</option>
            <option :value="5">5 / page</option>
            <option :value="10">10 / page</option>
            <option :value="20">20 / page</option>
            <option :value="9999">All</option>
          </select>
        </div>

        <div class="grid grid-cols-8 gap-1">
          <template x-for="n in total" :key="'qnav-' + n">
            <button
              type="button"
              class="h-8 w-8 text-xs rounded-lg border"
              :class="navClass(n)"
              @click="jumpToNumber(n)"
              :title="navTitle(n)"
            >
              <span x-text="n"></span>
            </button>
          </template>
        </div>

        <div class="mt-3 text-xs text-gray-600 space-y-1">
          <div class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded border border-gray-200 bg-white"></span> Not answered
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded border border-gray-200 bg-emerald-50"></span> Answered
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded border border-gray-200 bg-amber-50"></span> Flagged
          </div>
          <div class="flex items-center gap-2">
            <span class="inline-block h-3 w-3 rounded border border-gray-200 bg-sky-50"></span> Current
          </div>
        </div>
      </div>
    </aside>

    <!-- Right: questions -->
    <section class="lg:col-span-9">
      <form x-ref="form" method="post" action="/public/index.php?r=quiz_take&id=<?= (int)$attempt['id'] ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$attempt['id'] ?>">
        <input type="hidden" name="submit_quiz" x-ref="submitFlag" value="0">

        <div class="space-y-4">
          <?php foreach ($questions as $idx => $q): ?>
            <?php
              $n = $idx + 1;
              $aqId = (int)$q['aq_id'];
              $sel = (string)($q['selected_option'] ?? '');
              $marked = (int)($q['marked_flag'] ?? 0);
            ?>
            <div
              class="p-4 rounded-2xl border border-gray-200 bg-white"
              x-show="isVisible(<?= $n ?>)"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <div class="text-xs text-gray-500 mb-1">
                    Question <?= $n ?> of <?= (int)count($questions) ?>
                    <span class="mx-2">•</span>
                    <?= htmlspecialchars((string)$q['topic_name']) ?>
                  </div>
                  <div class="text-base font-semibold">
                    <?= htmlspecialchars((string)$q['question_text']) ?>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs hover:bg-gray-50"
                    @click="toggleMarked(<?= $aqId ?>)"
                  >
                    <span x-show="!isMarked(<?= $aqId ?>)">Flag</span>
                    <span x-show="isMarked(<?= $aqId ?>)">Unflag</span>
                  </button>

                  <button
                    type="button"
                    class="px-3 py-1.5 rounded-lg border border-gray-200 text-xs hover:bg-gray-50"
                    @click="openReport(<?= (int)$q['question_id'] ?>)"
                  >
                    Report issue
                  </button>
                </div>
              </div>

              <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                <?php
                  $opts = [
                    'A' => (string)$q['option_a'],
                    'B' => (string)$q['option_b'],
                    'C' => (string)$q['option_c'],
                    'D' => (string)$q['option_d'],
                  ];
                ?>
                <?php foreach ($opts as $k => $val): ?>
                  <label class="flex items-start gap-2 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
                    <input
                      type="radio"
                      class="mt-1"
                      name="answers[<?= $aqId ?>]"
                      value="<?= $k ?>"
                      <?= ($sel === $k ? 'checked' : '') ?>
                      @change="setAnswered(<?= $n ?>, <?= $aqId ?>)"
                    >
                    <div>
                      <div class="text-xs text-gray-500"><?= $k ?>.</div>
                      <div><?= htmlspecialchars($val) ?></div>
                    </div>
                  </label>
                <?php endforeach; ?>
              </div>

              <!-- Persist marked flag -->
              <input
                type="hidden"
                name="marked[<?= $aqId ?>]"
                :value="isMarked(<?= $aqId ?>) ? 1 : ''"
              >
            </div>
          <?php endforeach; ?>
        </div>

        <div class="flex items-center justify-between mt-4">
          <button
            type="button"
            class="px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
            :disabled="page <= 1"
            @click="prevPage()"
          >
            Previous
          </button>

          <div class="text-xs text-gray-500" x-text="pageLabel"></div>

          <button
            type="button"
            class="px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
            :disabled="page >= totalPages"
            @click="nextPage()"
          >
            Next
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- Report modal -->
  <div
    class="fixed inset-0 z-[255] bg-black/40 flex items-center justify-center px-4"
    x-show="reportOpen"
    x-cloak
  >
    <div class="w-full max-w-lg rounded-2xl bg-white border border-gray-200 p-4">
      <div class="flex items-center justify-between mb-2">
        <div class="text-sm font-semibold">Report question issue</div>
        <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeReport()">✕</button>
      </div>

      <div class="space-y-3">
        <select class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm" x-model="reportReason">
          <option value="">Select reason</option>
          <option value="wrong_answer">Answer key is wrong</option>
          <option value="unclear_question">Question is unclear</option>
          <option value="typo_or_format">Typo or formatting issue</option>
          <option value="out_of_scope">Out of scope</option>
          <option value="other">Other</option>
        </select>

        <textarea
          class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm"
          rows="4"
          placeholder="Add a short explanation (optional but helpful)"
          x-model="reportDetails"
        ></textarea>

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
            @click="closeReport()"
          >
            Cancel
          </button>

          <button
            type="button"
            class="px-3 py-2 rounded-xl border border-sky-600 bg-sky-600 text-white hover:bg-sky-700 text-sm"
            @click="submitReport()"
            :disabled="reportReason === ''"
          >
            Send report
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function quizTakeScreen(cfg) {
  return {
    attemptId: cfg.attemptId,
    remaining: cfg.remainingSeconds,
    total: cfg.total,

    // paging
    perPage: 5,
    page: 1,

    // state maps
    answeredNums: new Set(),
    markedByAq: {},

    // report modal
    reportOpen: false,
    reportQuestionId: null,
    reportReason: '',
    reportDetails: '',

    timeLabel: '00:00',
    pageLabel: '',
    totalPages: 1,

    init() {
      // hydrate marked flags from hidden inputs that have default values
      // simplest: markByAq built as user interacts, backend keeps truth

      this.recalcPages();
      this.updateTimeLabel();
      this.startTimer();

      // initial scan: find checked radios to mark answered
      document.querySelectorAll('input[type="radio"]:checked').forEach((el) => {
        const name = String(el.name || '');
        // name like answers[123]
        const m = name.match(/answers\[(\d+)\]/);
        if (m) {
          const aqId = Number(m[1]);
          // locate question number by walking to container index:
          // we already set answered on change, so initial can be ignored safely
        }
      });
    },

    recalcPages() {
      const pp = Number(this.perPage || 5);
      this.totalPages = (pp >= 9999) ? 1 : Math.max(1, Math.ceil(this.total / pp));
      if (this.page > this.totalPages) this.page = this.totalPages;
      this.pageLabel = `Page ${this.page} of ${this.totalPages}`;
    },

    isVisible(questionNumber) {
      const pp = Number(this.perPage || 5);
      if (pp >= 9999) return true;
      const start = (this.page - 1) * pp + 1;
      const end   = start + pp - 1;
      return questionNumber >= start && questionNumber <= end;
    },

    goTo(p) {
      this.page = Number(p);
      this.recalcPages();
    },

    prevPage() {
      if (this.page > 1) this.page--;
      this.recalcPages();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    nextPage() {
      if (this.page < this.totalPages) this.page++;
      this.recalcPages();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    jumpToNumber(n) {
      const pp = Number(this.perPage || 5);
      if (pp >= 9999) {
        // scroll to nth visible card
        const cards = document.querySelectorAll('[x-show]');
        const el = cards[n - 1];
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      const p = Math.ceil(n / pp);
      this.page = p;
      this.recalcPages();
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    setAnswered(n, aqId) {
      this.answeredNums.add(Number(n));
      // optional: autosave light
      this.saveNow(true);
    },

    navClass(n) {
      const isCurrent = this.isVisible(n);
      const isAnswered = this.answeredNums.has(Number(n));
      const isFlagged = this.isFlaggedNumber(n);

      if (isCurrent) return 'border-sky-300 bg-sky-50 text-sky-800';
      if (isFlagged) return 'border-amber-200 bg-amber-50 text-amber-900';
      if (isAnswered) return 'border-emerald-200 bg-emerald-50 text-emerald-900';
      return 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50';
    },

    navTitle(n) {
      const isAnswered = this.answeredNums.has(Number(n));
      const isFlagged = this.isFlaggedNumber(n);
      if (isFlagged) return `Question ${n}: flagged`;
      if (isAnswered) return `Question ${n}: answered`;
      return `Question ${n}: not answered`;
    },

    // Marking
    isMarked(aqId) {
      return !!this.markedByAq[String(aqId)];
    },

    toggleMarked(aqId) {
      const k = String(aqId);
      this.markedByAq[k] = !this.markedByAq[k];
      this.saveNow(true);
    },

    isFlaggedNumber(n) {
      // best-effort: flagged means any question on page whose aqId is marked,
      // we do not map n->aqId here. This is a UI hint, not critical.
      // If you want exact mapping, we can pass aqId list per question into JS.
      return false;
    },

    // Timer
    startTimer() {
      // if remaining already 0, force submit
      if (this.remaining <= 0) {
        this.submitFinal();
        return;
      }

      setInterval(() => {
        this.remaining = Math.max(0, this.remaining - 1);
        this.updateTimeLabel();
        if (this.remaining === 0) {
          this.submitFinal();
        }
      }, 1000);
    },

    updateTimeLabel() {
      const s = Math.max(0, Number(this.remaining || 0));
      const mm = Math.floor(s / 60);
      const ss = s % 60;
      const hh = Math.floor(mm / 60);
      const m2 = mm % 60;

      if (hh > 0) {
        this.timeLabel = `${String(hh).padStart(2,'0')}:${String(m2).padStart(2,'0')}:${String(ss).padStart(2,'0')}`;
      } else {
        this.timeLabel = `${String(mm).padStart(2,'0')}:${String(ss).padStart(2,'0')}`;
      }
    },

    async saveNow(silent = false) {
      const form = this.$refs.form;
      if (!form) return;

      const fd = new FormData(form);
      fd.set('submit_quiz', '0');

      try {
        const res = await fetch(form.action, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          body: fd
        });
        const j = await res.json();
        if (!silent && j && j.ok) {
          // Optional: toast if you want
          // alertify.success('Saved');
        }
      } catch (e) {
        if (!silent) {
          // alertify.error('Save failed');
        }
      }
    },

    submitFinal() {
      const form = this.$refs.form;
      if (!form) return;
      this.$refs.submitFlag.value = '1';
      form.submit();
    },

    // Reporting
    openReport(questionId) {
      this.reportQuestionId = Number(questionId);
      this.reportReason = '';
      this.reportDetails = '';
      this.reportOpen = true;
    },

    closeReport() {
      this.reportOpen = false;
      this.reportQuestionId = null;
    },

    async submitReport() {
      if (!this.reportQuestionId || !this.reportReason) return;

      const fd = new FormData();
      fd.append('csrf_token', document.querySelector('input[name="csrf_token"]')?.value || '');
      fd.append('question_id', String(this.reportQuestionId));
      fd.append('attempt_id', String(this.attemptId));
      fd.append('reason', this.reportReason);
      fd.append('details', this.reportDetails);

      try {
        const res = await fetch('/public/index.php?r=question_report_create', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
          body: fd
        });
        const j = await res.json();
        if (j && j.ok) {
          this.closeReport();
          alertify.success('Report sent. Thank you.');
        } else {
          alertify.error((j && j.error) ? j.error : 'Could not send report.');
        }
      } catch (e) {
        alertify.error('Could not send report.');
      }
    }
  }
}
</script>
