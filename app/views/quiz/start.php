<?php
/** @var array $user */
/** @var int|null $presetLevelId */
/** @var int|null $presetModuleId */
?>
<div
  class="max-w-4xl mx-auto space-y-4"
  x-data="quizStart({
    presetLevelId: <?= $presetLevelId ? (int)$presetLevelId : 'null' ?>,
    presetModuleId: <?= $presetModuleId ? (int)$presetModuleId : 'null' ?>

  })"
  x-init="init()"
>
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-xl font-semibold">Start Quiz</h1>
      <p class="text-xs text-gray-600 mt-1">
        Choose your scope, pick topics, set the number of questions and timer, then start.
      </p>
    </div>

    <div class="space-x-2 text-xs">
      <a href="/public/index.php"
         class="px-3 py-1 rounded-lg border border-gray-200 hover:bg-gray-50">
        Home
      </a>
      <a href="/public/index.php?r=quiz_start&preset=gem201"
         class="px-3 py-1 rounded-lg border border-sky-300 bg-sky-50 text-sky-700 hover:bg-sky-100">
        Level 200 · GEM 201 preset
      </a>
    </div>
  </div>

  <div class="p-4 rounded-xl border border-gray-200 bg-gray-50 text-xs text-gray-700">
    <p>
      <span class="font-semibold">Tip:</span> You must select at least one topic under a subject.
      Level / Module / Subject alone is not enough.
    </p>
  </div>

  <form method="post" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Taxonomy selection -->
    <div class="p-4 rounded-xl border border-gray-200 space-y-3">
      <div class="flex items-center justify-between">
        <div class="font-medium text-sm">Select scope</div>
        <div class="text-xs text-gray-500">
          Level → Module → Subject → Topics
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
        <!-- Level -->
        <div>
          <label class="block text-xs font-medium mb-1">Level</label>
          <select
            class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"
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
            class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"
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
            class="w-full px-3 py-2 rounded-lg border border-gray-200 text-sm"
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

      <!-- Topics multi-select -->
      <div class="mt-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium">Topics (multi select)</span>
          <span class="text-xs text-gray-500" x-text="'Selected: ' + selectedTopicIds.length"></span>
        </div>

        <div class="border border-gray-200 rounded-xl max-h-64 overflow-y-auto bg-white">
          <template x-if="topics.length === 0">
            <p class="px-3 py-3 text-xs text-gray-500">
              Select a subject to load its topics.
            </p>
          </template>

          <template x-for="t in topics" :key="t.id">
            <label class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-gray-50 cursor-pointer border-b border-gray-50">
              <input
                type="checkbox"
                class="rounded border-gray-300"
                :value="t.id"
                x-model="selectedTopicIds"
              >
              <span x-text="t.label"></span>
            </label>
          </template>
        </div>

        <!-- Hidden inputs reflecting selected topics -->
        <template x-for="tid in selectedTopicIds" :key="'sel-'+tid">
          <input type="hidden" name="topic_ids[]" :value="tid">
        </template>

        <p class="mt-1 text-xs text-gray-500">
          You can pick multiple topics from the selected subject. Questions will be randomly drawn across all selected topics.
        </p>
      </div>
    </div>

    <!-- Quiz options -->
    <div class="p-4 rounded-xl border border-gray-200 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm bg-white">
      <div>
        <label class="block text-xs font-medium mb-1">Number of questions</label>
        <input
          type="number"
          name="num_questions"
          min="1"
          max="200"
          x-model="numQuestions"
          class="w-full px-3 py-2 rounded-lg border border-gray-200"
        >
        <p class="text-xs text-gray-500 mt-1">Recommended: 20–50 questions per sitting.</p>
      </div>

      <div>
        <label class="block text-xs font-medium mb-1">Scoring mode</label>
        <select
          name="scoring_mode"
          x-model="scoringMode"
          class="w-full px-3 py-2 rounded-lg border border-gray-200"
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
          class="w-full px-3 py-2 rounded-lg border border-gray-200"
        >
          <option value="1800">30 minutes</option>
          <option value="2700">45 minutes</option>
          <option value="3600">60 minutes</option>
          <option value="5400">90 minutes</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">
          Quiz will auto-submit when the timer ends.
        </p>
      </div>
    </div>

    <div class="flex justify-end">
      <button
        type="submit"
        class="px-4 py-2 rounded-lg border border-sky-500 bg-sky-600 text-white text-sm hover:bg-sky-700"
      >
        Start quiz
      </button>
    </div>
  </form>
</div>

<script>
function quizStart(config) {
  return {
    levels: [],
    modules: [],
    subjects: [],
    topics: [],

    levelId: config.presetLevelId || '',
    moduleId: config.presetModuleId || '',
    subjectId: '',

    // Alpine will keep this array in sync with the checkboxes
    selectedTopicIds: [],

    numQuestions: 20,
    scoringMode: 'standard',
    timerSeconds: 3600,

    async init() {
      try {
        const res = await fetch('/public/index.php?r=api_levels');
        this.levels = await res.json();

        // If preset, auto-load modules / subjects chain
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
      this.modules = await res.json();
    },

    async loadSubjects() {
      if (!this.moduleId) return;
      const res = await fetch('/public/index.php?r=api_subjects&module_id=' + this.moduleId);
      this.subjects = await res.json();
    },

    async loadTopics() {
      if (!this.subjectId) return;
      const res = await fetch('/public/index.php?r=api_topics&subject_id=' + this.subjectId);
      this.topics = await res.json();
    },
  };
}
</script>
