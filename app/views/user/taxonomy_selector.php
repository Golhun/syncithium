<?php
/** @var array    $user */
/** @var int|null $presetLevelId */
/** @var int|null $presetModuleId */
?>
<div
  class="max-w-5xl mx-auto"
  x-data="quizTopicScreen({
    presetLevelId: <?= $presetLevelId ? (int)$presetLevelId : 'null' ?>,
    presetModuleId: <?= $presetModuleId ? (int)$presetModuleId : 'null' ?>
  })"
  x-init="init()"
>
  <!-- Page header -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <?= icon('play-circle', 'h-6 w-6 text-sky-700', 'solid') ?>
        <h1 class="text-2xl font-semibold">Start Quiz</h1>
        <span class="inline-flex items-center px-2 py-1 text-xs rounded-lg ring-1 ring-gray-200 bg-white text-gray-700">
          Setup
        </span>
      </div>
      <p class="text-sm text-gray-600 mt-1">
        Choose your scope, select topics, then set questions, scoring, and timer.
      </p>
    </div>

    <div class="flex items-center gap-2 flex-wrap justify-start md:justify-end">
      <a
        href="/public/index.php?r=taxonomy_selector&preset=gem201"
        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
        title="Load a preset selection"
      >
        <?= icon('sparkles', 'h-4 w-4 text-sky-600', 'outline') ?>
        <span>Load preset</span>
        <span class="px-2 py-1 rounded-lg bg-sky-50 text-sky-700 text-xs border border-sky-200">
          Level 200, GEM 201
        </span>
      </a>
    </div>
  </div>

  <!-- Progress / guidance row -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5">
    <div class="rounded-2xl border border-gray-200 bg-white p-4 flex items-start gap-3">
      <?= icon('map', 'h-6 w-6 text-sky-600', 'outline') ?>
      <div>
        <div class="text-xs text-gray-500">Step 1</div>
        <div class="font-semibold mt-1">Pick scope</div>
        <div class="text-xs text-gray-500 mt-1">Level → Module → Subject</div>
      </div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-4 flex items-start gap-3">
      <?= icon('tag', 'h-6 w-6 text-sky-600', 'outline') ?>
      <div>
        <div class="text-xs text-gray-500">Step 2</div>
        <div class="font-semibold mt-1">Select topics</div>
        <div class="text-xs text-gray-500 mt-1">Choose one or many</div>
      </div>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white p-4 flex items-start gap-3">
      <?= icon('rocket-launch', 'h-6 w-6 text-sky-600', 'outline') ?>
      <div>
        <div class="text-xs text-gray-500">Step 3</div>
        <div class="font-semibold mt-1">Start quiz</div>
        <div class="text-xs text-gray-500 mt-1">Questions, scoring, timer</div>
      </div>
    </div>
  </div>

  <!-- API error -->
  <template x-if="apiError">
    <div class="mb-5 p-4 rounded-2xl border border-rose-200 bg-rose-50 text-sm text-rose-800 flex items-start gap-3">
      <?= icon('exclamation-triangle', 'h-5 w-5 text-rose-500', 'solid') ?>
      <div>
        <div class="font-semibold">Could not load quiz data</div>
        <div class="mt-1" x-text="apiError"></div>
        <div class="mt-2 text-xs text-rose-700">
          If this persists, confirm you are logged in and the API routes return JSON.
        </div>
      </div>
    </div>
  </template>

  <!-- Single quiz-start form -->
  <form method="post" action="/public/index.php?r=quiz_start" class="space-y-5">
    <?= csrf_field() ?>

    <!-- Scope card -->
    <section class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-2">
            <?= icon('map', 'h-4 w-4 text-gray-600', 'outline') ?>
            <div class="text-sm font-semibold">Scope</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mt-1">Select Level, Module, then Subject to load topics.</div>
          </div>
          <div class="text-xs text-gray-500 whitespace-nowrap">
            Step 1 of 3
          </div>
        </div>
      </div>

      <div class="p-5">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Level -->
          <div>
            <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 mb-1">
              <?= icon('academic-cap', 'h-4 w-4 text-gray-500', 'outline') ?>
              <span>Level</span>
            </label>
            <select
              class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
              x-model="levelId"
              @change="onLevelChange"
            >
              <option value="">Select level</option>
              <template x-for="l in levels" :key="l.id">
                <option :value="l.id" x-text="l.label"></option>
              </template>
            </select>
            <p class="mt-1 text-xs text-gray-500">Pick the academic level.</p>
          </div>

          <!-- Module -->
          <div>
            <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 mb-1">
              <?= icon('squares-2x2', 'h-4 w-4 text-gray-500', 'outline') ?>
              <span>Module</span>
            </label>
            <select
              class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:ring-4 focus:ring-sky-100 focus:border-sky-400 disabled:bg-gray-50 disabled:text-gray-400"
              x-model="moduleId"
              @change="onModuleChange"
              :disabled="!levelId"
            >
              <option value="">Select module</option>
              <template x-for="m in modules" :key="m.id">
                <option :value="m.id" x-text="m.label"></option>
              </template>
            </select>
            <p class="mt-1 text-xs text-gray-500">Modules depend on Level.</p>
          </div>

          <!-- Subject -->
          <div>
            <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 mb-1">
              <?= icon('bookmark-square', 'h-4 w-4 text-gray-500', 'outline') ?>
              <span>Subject</span>
            </label>
            <select
              class="w-full px-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:ring-4 focus:ring-sky-100 focus:border-sky-400 disabled:bg-gray-50 disabled:text-gray-400"
              x-model="subjectId"
              @change="onSubjectChange"
              :disabled="!moduleId"
            >
              <option value="">Select subject</option>
              <template x-for="s in subjects" :key="s.id">
                <option :value="s.id" x-text="s.label"></option>
              </template>
            </select>
            <p class="mt-1 text-xs text-gray-500">Subjects depend on Module.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Topics card -->
    <section class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-2">
            <?= icon('tag', 'h-4 w-4 text-gray-600', 'outline') ?>
            <div class="text-sm font-semibold">Topics</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mt-1">Select one or many topics. Questions are drawn randomly across them.</div>
          </div>

          <div class="flex items-center gap-2 text-xs whitespace-nowrap">
            <span class="inline-flex items-center px-2 py-1 rounded-lg ring-1 ring-gray-200 bg-white text-gray-700">
              <?= icon('check-circle', 'h-4 w-4 text-gray-500', 'outline') ?> Selected:
              <span class="ml-1 font-semibold" x-text="selectedTopicIds.length"></span>
            </span>
            <span class="text-gray-400">Step 2 of 3</span>
          </div>
        </div>
      </div>

      <div class="p-5 space-y-4">
        <!-- Search + bulk actions -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-700 mb-1">Filter topics</label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <?= icon('magnifying-glass', 'h-5 w-5 text-gray-400', 'outline') ?>
              </div>
              <input
                type="text"
                x-model="topicSearch"
                class="w-full pl-10 pr-3 py-2 rounded-xl border border-gray-200 text-sm bg-white focus:ring-4 focus:ring-sky-100 focus:border-sky-400 disabled:bg-gray-50 disabled:text-gray-400"
                placeholder="Type to filter topics..."
                :disabled="!subjectId"
              >
            </div>
            <p class="mt-1 text-xs text-gray-500">Tip: search by keyword, for example “bone”, “cardio”, “renal”.</p>
          </div>

          <div class="flex md:items-end gap-2">
            <button
              type="button"
              class="w-full md:w-auto inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm disabled:opacity-60"
              @click="selectedTopicIds = []"
              :disabled="selectedTopicIds.length === 0"
            >
              <?= icon('x-mark', 'h-4 w-4', 'outline') ?>
              <span>Clear</span>
            </button>

            <button
              type="button"
              class="w-full md:w-auto inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm disabled:opacity-60"
              @click="
                (() => {
                  const ids = filteredTopics().map(t => Number(t.id));
                  const merged = Array.from(new Set([...selectedTopicIds, ...ids]));
                  selectedTopicIds = merged;
                })()
              "
              :disabled="!subjectId || filteredTopics().length === 0"
              title="Adds all currently filtered topics to your selection"
            >
              <?= icon('check-circle', 'h-4 w-4', 'outline') ?>
              <span>Select filtered</span>
            </button>
          </div>
        </div>

        <!-- Topics list -->
        <div class="rounded-2xl border border-gray-200 bg-white p-4">
          <template x-if="!subjectId">
            <div class="text-sm text-gray-600">
              Select a subject to load topics.
            </div>
          </template>

          <template x-if="subjectId && filteredTopics().length === 0">
            <div class="text-sm text-gray-600">
              No topics match your search.
            </div>
          </template>

          <div class="flex flex-wrap gap-2" x-show="subjectId && filteredTopics().length > 0">
            <template x-for="t in filteredTopics()" :key="t.id">
              <button
                type="button"
                @click.prevent="toggleTopic(t.id)"
                class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-xs font-semibold border transition focus:outline-none focus:ring-4 focus:ring-sky-100"
                :class="isSelected(t.id)
                  ? 'bg-sky-600 border-sky-600 text-white'
                  : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'"
              >
                <span class="truncate max-w-[220px]" x-text="t.label"></span>
                <span
                  class="inline-flex items-center justify-center h-5 w-5 rounded-full"
                  :class="isSelected(t.id) ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'"
                >
                  <template x-if="isSelected(t.id)"><?= icon('check', 'h-3 w-3', 'solid') ?></template>
                  <template x-if="!isSelected(t.id)"><?= icon('plus', 'h-3 w-3', 'solid') ?></template>
                </span>
              </button>
            </template>
          </div>
        </div>

        <!-- Hidden inputs for PHP -->
        <template x-for="tid in selectedTopicIds" :key="'topic-' + tid">
          <input type="hidden" name="topic_ids[]" :value="tid">
        </template>

        <div class="text-xs text-gray-500 flex items-center gap-2">
          <?= icon('information-circle', 'h-4 w-4 text-gray-400', 'outline') ?>
          <span>
            You must select at least one topic before you can start.
          </span>
        </div>
      </div>
    </section>

    <!-- Options card -->
    <section class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-2">
            <?= icon('cog-6-tooth', 'h-4 w-4 text-gray-600', 'outline') ?>
            <div class="text-sm font-semibold">Quiz options</div>
          </div>
          <div>
            <div class="text-xs text-gray-500 mt-1">Set number of questions, scoring mode, and timer.</div>
          </div>
          <div class="text-xs text-gray-500 whitespace-nowrap">Step 3 of 3</div>
        </div>
      </div>

      <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div>
          <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 mb-1">
            <?= icon('list-bullet', 'h-4 w-4 text-gray-500', 'outline') ?>
            <span>Number of questions</span>
          </label>
          <input
            type="number"
            name="num_questions"
            min="1"
            max="200"
            x-model="numQuestions"
            class="w-full px-3 py-2 rounded-xl border border-gray-200 focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
          >
          <p class="mt-1 text-xs text-gray-500">Recommended: 20 to 50 per sitting.</p>
        </div>

        <div>
          <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 mb-1">
            <?= icon('scale', 'h-4 w-4 text-gray-500', 'outline') ?>
            <span>Scoring mode</span>
          </label>
          <select
            name="scoring_mode"
            x-model="scoringMode"
            class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
          >
            <option value="standard">Standard, +1 correct, 0 wrong</option>
            <option value="negative">Negative marking, +1 correct, -1 wrong</option>
          </select>
          <p class="mt-1 text-xs text-gray-500">Negative marking increases difficulty.</p>
        </div>

        <div>
          <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 mb-1">
            <?= icon('clock', 'h-4 w-4 text-gray-500', 'outline') ?>
            <span>Timer</span>
          </label>
          <select
            name="timer_seconds"
            x-model="timerSeconds"
            class="w-full px-3 py-2 rounded-xl border border-gray-200 bg-white focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
          >
            <option value="1800">30 minutes</option>
            <option value="2700">45 minutes</option>
            <option value="3600">60 minutes</option>
            <option value="5400">90 minutes</option>
          </select>
          <p class="mt-1 text-xs text-gray-500">Auto-submits when time ends.</p>
        </div>
      </div>

      <!-- Action footer -->
      <div class="px-5 py-4 border-t border-gray-200 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="text-xs text-gray-500">
          <span class="font-semibold text-gray-700" x-text="selectedTopicIds.length"></span>
          <span> topic(s) selected.</span>
        </div>

        <button
          type="submit"
          name="start_quiz"
          value="1"
          :disabled="selectedTopicIds.length === 0"
          class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl text-sm font-semibold text-white border border-sky-600 transition"
          :class="selectedTopicIds.length === 0
            ? 'bg-sky-300 cursor-not-allowed'
            : 'bg-sky-600 hover:bg-sky-700'"
        >
          <?= icon('rocket-launch', 'h-4 w-4', 'solid') ?>
          <span>Start quiz</span>
        </button>
      </div>
    </section>
  </form>
</div>

<script>
function quizTopicScreen(config) {
  return {
    // Data
    levels: [],
    modules: [],
    subjects: [],
    topics: [],

    // Selections
    levelId: config.presetLevelId || '',
    moduleId: config.presetModuleId || '',
    subjectId: '',
    selectedTopicIds: [],

    // Options
    numQuestions: 20,
    scoringMode: 'standard',
    timerSeconds: 3600,

    // UI
    topicSearch: '',

    // Diagnostics
    apiError: '',

    // Computed (method)
    filteredTopics() {
      if (!this.topicSearch) return this.topics;
      const q = this.topicSearch.toLowerCase();
      return this.topics.filter(t => (t.label || '').toLowerCase().includes(q));
    },

    async init() {
      this.apiError = '';
      try {
        const data = await this.fetchJson('/public/index.php?r=api_levels');
        this.levels = (data || []).map(l => ({
          id: Number(l.id ?? l.level_id),
          label: l.label ?? l.code ?? l.name ?? ('Level ' + (l.id ?? l.level_id)),
        }));

        if (this.levelId) {
          await this.loadModules();
          if (this.moduleId) {
            await this.loadSubjects();
          }
        }
      } catch (e) {
        this.levels = [];
        this.apiError = e?.message ? String(e.message) : 'Failed to load levels.';
      }
    },

    async fetchJson(url) {
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });

      // If server returns HTML (login page), this makes the issue obvious.
      const text = await res.text();
      if (!res.ok) {
        throw new Error(`Request failed (${res.status}) for ${url}: ${text.slice(0, 200)}`);
      }

      try {
        return JSON.parse(text);
      } catch (err) {
        throw new Error(`Invalid JSON from ${url}: ${text.slice(0, 200)}`);
      }
    },

    normaliseModule(m) {
      return {
        id: Number(m.id ?? m.module_id),
        label: m.label ?? m.code ?? m.name ?? ('Module ' + (m.id ?? m.module_id)),
      };
    },

    normaliseSubject(s) {
      return {
        id: Number(s.id ?? s.subject_id),
        label: s.label ?? s.name ?? ('Subject ' + (s.id ?? s.subject_id)),
      };
    },

    normaliseTopic(t) {
      return {
        id: Number(t.id ?? t.topic_id),
        label: t.label ?? t.name ?? ('Topic ' + (t.id ?? t.topic_id)),
      };
    },

    async onLevelChange() {
      this.apiError = '';
      this.moduleId = '';
      this.subjectId = '';
      this.modules = [];
      this.subjects = [];
      this.topics = [];
      this.selectedTopicIds = [];
      this.topicSearch = '';
      if (this.levelId) {
        await this.loadModules();
      }
    },

    async onModuleChange() {
      this.apiError = '';
      this.subjectId = '';
      this.subjects = [];
      this.topics = [];
      this.selectedTopicIds = [];
      this.topicSearch = '';
      if (this.moduleId) {
        await this.loadSubjects();
      }
    },

    async onSubjectChange() {
      this.apiError = '';
      this.topics = [];
      this.selectedTopicIds = [];
      this.topicSearch = '';
      if (this.subjectId) {
        await this.loadTopics();
      }
    },

    async loadModules() {
      if (!this.levelId) return;
      try {
        const data = await this.fetchJson('/public/index.php?r=api_modules&level_id=' + encodeURIComponent(this.levelId));
        this.modules = (data || []).map(m => this.normaliseModule(m));
      } catch (e) {
        this.modules = [];
        this.apiError = e?.message ? String(e.message) : 'Failed to load modules.';
      }
    },

    async loadSubjects() {
      if (!this.moduleId) return;
      try {
        const data = await this.fetchJson('/public/index.php?r=api_subjects&module_id=' + encodeURIComponent(this.moduleId));
        this.subjects = (data || []).map(s => this.normaliseSubject(s));
      } catch (e) {
        this.subjects = [];
        this.apiError = e?.message ? String(e.message) : 'Failed to load subjects.';
      }
    },

    async loadTopics() {
      if (!this.subjectId) return;
      try {
        const data = await this.fetchJson('/public/index.php?r=api_topics&subject_id=' + encodeURIComponent(this.subjectId));
        this.topics = (data || []).map(t => this.normaliseTopic(t));
      } catch (e) {
        this.topics = [];
        this.apiError = e?.message ? String(e.message) : 'Failed to load topics.';
      }
    },

    isSelected(id) {
      id = Number(id);
      return this.selectedTopicIds.includes(id);
    },

    toggleTopic(id) {
      id = Number(id);
      if (this.isSelected(id)) {
        this.selectedTopicIds = this.selectedTopicIds.filter(tid => tid !== id);
      } else {
        this.selectedTopicIds.push(id);
      }
    }
  };
}
</script>
