<?php
/** @var array    $user */
/** @var int|null $presetLevelId */
/** @var int|null $presetModuleId */
?>
<div
  class="max-w-5xl mx-auto"
  x-data="taxonomySelector({
    presetLevelId: <?= $presetLevelId ? (int)$presetLevelId : 'null' ?>,
    presetModuleId: <?= $presetModuleId ? (int)$presetModuleId : 'null' ?>
  })"
  x-init="init()"
>
  <!-- Header -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-semibold">Start Quiz</h1>
      <p class="text-xs text-gray-500 mt-1">
        Choose level, module, subject and topics, then set the number of questions, scoring mode and timer.
      </p>
    </div>

    <div class="space-x-2 text-sm">
      <a href="/public/index.php?r=taxonomy_selector&preset=gem201"
         class="px-3 py-1 rounded-full border border-sky-300 bg-sky-50 text-sky-700">
        Level 200 · GEM 201 preset
      </a>
    </div>
  </div>

  <!-- Info -->
  <div class="mb-4 p-4 rounded-xl border border-gray-200 bg-gray-50 text-xs text-gray-600">
    <strong class="font-medium">Note:</strong>
    You must select at least one topic. Questions are drawn randomly across the selected topics.
  </div>

  <!-- Single quiz-start form -->
  <form method="post" action="/public/index.php?r=quiz_start" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Scope -->
    <div class="p-4 rounded-xl border border-gray-200 bg-white space-y-4">
      <div class="flex items-center justify-between text-xs">
        <span class="font-medium">Scope</span>
        <span class="text-gray-400">Level → Module → Subject → Topics</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <!-- Level -->
        <div>
          <label class="block text-xs font-medium mb-1">Level</label>
          <select
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm"
            x-model="levelId"
            @change="onLevelChange"
          >
            <option value="">Select level</option>
            <template x-for="l in levels" :key="l.id">
              <option :value="l.id" x-text="l.label"></option>
            </template>
          </select>
        </div>

        <!-- Module -->
        <div>
          <label class="block text-xs font-medium mb-1">Module</label>
          <select
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm"
            x-model="moduleId"
            @change="onModuleChange"
          >
            <option value="">Select module</option>
            <template x-for="m in modules" :key="m.id">
              <option :value="m.id" x-text="m.label"></option>
            </template>
          </select>
        </div>

        <!-- Subject -->
        <div>
          <label class="block text-xs font-medium mb-1">Subject</label>
          <select
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-sm"
            x-model="subjectId"
            @change="onSubjectChange"
          >
            <option value="">Select subject</option>
            <template x-for="s in subjects" :key="s.id">
              <option :value="s.id" x-text="s.label"></option>
            </template>
          </select>
        </div>
      </div>

      <!-- Topics as chips -->
      <div class="mt-2">
        <div class="flex items-center justify-between mb-2 text-xs">
          <span class="font-medium">Topics (multi select)</span>
          <span class="text-gray-500" x-text="'Selected: ' + selectedTopicIds.length"></span>
        </div>

        <!-- Search -->
        <div class="mb-3">
          <input
            type="text"
            x-model="topicSearch"
            class="w-full px-3 py-2 rounded-lg border border-gray-300 text-xs"
            placeholder="Search topic..."
          >
        </div>

        <!-- Chip list -->
        <div class="border border-gray-200 rounded-xl bg-white px-3 py-3 min-h-[56px]">
          <template x-if="filteredTopics().length === 0">
            <p class="text-xs text-gray-500">
              <span x-show="!subjectId">Select a subject to load topics.</span>
              <span x-show="subjectId">No topics match your search.</span>
            </p>
          </template>

          <div class="flex flex-wrap gap-2" x-show="filteredTopics().length > 0">
            <template x-for="t in filteredTopics()" :key="t.id">
              <button
                type="button"
                @click.prevent="toggleTopic(t.id)"
                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium focus:outline-none"
                :class="isSelected(t.id)
                  ? 'bg-pink-500 text-white'
                  : 'bg-blue-500 text-white'"
              >
                <span x-text="t.label"></span>
                <span class="ml-1 text-[11px] font-semibold">
                  <span x-show="isSelected(t.id)">✓</span>
                  <span x-show="!isSelected(t.id)">+</span>
                </span>
              </button>
            </template>
          </div>
        </div>

        <!-- Hidden inputs for PHP -->
        <template x-for="tid in selectedTopicIds" :key="'topic-' + tid">
          <input type="hidden" name="topic_ids[]" :value="tid">
        </template>

        <div class="flex items-center justify-between mt-2 text-xs text-gray-500">
          <span>You can choose one or many topics. We will randomly draw questions across them.</span>
          <button type="button"
                  class="px-2 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
                  @click="selectedTopicIds = []"
                  x-show="selectedTopicIds.length > 0">
            Clear selection
          </button>
        </div>
      </div>
    </div>

    <!-- Quiz options -->
    <div class="p-4 rounded-xl border border-gray-200 bg-white grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
      <div>
        <label class="block text-xs font-medium mb-1">Number of questions</label>
        <input
          type="number"
          name="num_questions"
          min="1"
          max="200"
          x-model="numQuestions"
          class="w-full px-3 py-2 rounded-lg border border-gray-300"
        >
        <p class="mt-1 text-xs text-gray-500">Recommended: 20–50 per sitting.</p>
      </div>

      <div>
        <label class="block text-xs font-medium mb-1">Scoring mode</label>
        <select
          name="scoring_mode"
          x-model="scoringMode"
          class="w-full px-3 py-2 rounded-lg border border-gray-300"
        >
          <option value="standard">Standard (+1 correct, 0 wrong)</option>
          <option value="negative">Negative marking (+1 correct, -1 wrong)</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-medium mb-1">Timer</label>
        <select
          name="timer_seconds"
          x-model="timerSeconds"
          class="w-full px-3 py-2 rounded-lg border border-gray-300"
        >
          <option value="1800">30 minutes</option>
          <option value="2700">45 minutes</option>
          <option value="3600">60 minutes</option>
          <option value="5400">90 minutes</option>
        </select>
        <p class="mt-1 text-xs text-gray-500">
          Quiz will auto-submit automatically when the timer ends.
        </p>
      </div>
    </div>

    <!-- Action -->
    <div class="flex justify-end">
      <button
        type="submit"
        :disabled="selectedTopicIds.length === 0"
        class="px-4 py-2 rounded-lg text-sm text-white border border-sky-600"
        :class="selectedTopicIds.length === 0
          ? 'bg-sky-300 cursor-not-allowed'
          : 'bg-sky-600 hover:bg-sky-700 cursor-pointer'"
      >
        Start quiz
      </button>
    </div>
  </form>
</div>

<script>
function taxonomySelector(config) {
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

    // Quiz options
    numQuestions: 20,
    scoringMode: 'standard',
    timerSeconds: 3600,

    // Search
    topicSearch: '',

    async init() {
      try {
        const res = await fetch('/public/index.php?r=api_levels');
        const data = await res.json();
        this.levels = data.map(l => ({
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
      }
    },

    filteredTopics() {
      if (!this.topicSearch) return this.topics;
      const q = this.topicSearch.toLowerCase();
      return this.topics.filter(t => (t.label || '').toLowerCase().includes(q));
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
      this.moduleId = '';
      this.subjectId = '';
      this.modules = [];
      this.subjects = [];
      this.topics = [];
      this.selectedTopicIds = [];
      if (this.levelId) {
        await this.loadModules();
      }
    },

    async onModuleChange() {
      this.subjectId = '';
      this.subjects = [];
      this.topics = [];
      this.selectedTopicIds = [];
      if (this.moduleId) {
        await this.loadSubjects();
      }
    },

    async onSubjectChange() {
      this.topics = [];
      this.selectedTopicIds = [];
      if (this.subjectId) {
        await this.loadTopics();
      }
    },

    async loadModules() {
      if (!this.levelId) return;
      const res = await fetch('/public/index.php?r=api_modules&level_id=' + this.levelId);
      const data = await res.json();
      this.modules = data.map(m => this.normaliseModule(m));
    },

    async loadSubjects() {
      if (!this.moduleId) return;
      const res = await fetch('/public/index.php?r=api_subjects&module_id=' + this.moduleId);
      const data = await res.json();
      this.subjects = data.map(s => this.normaliseSubject(s));
    },

    async loadTopics() {
      if (!this.subjectId) return;
      const res = await fetch('/public/index.php?r=api_topics&subject_id=' + this.subjectId);
      const data = await res.json();
      this.topics = data.map(t => this.normaliseTopic(t));
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
