<?php
/** @var array $user */
/** @var int|null $presetLevelId */
/** @var int|null $presetModuleId */
?>
<div
  class="max-w-4xl mx-auto"
  x-data="quizStart({
    presetLevelId: <?= $presetLevelId ? (int)$presetLevelId : 'null' ?>,
    presetModuleId: <?= $presetModuleId ? (int)$presetModuleId : 'null' ?>

  })"
  x-init="init()"
>
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Start Quiz</h1>
    <div class="space-x-2 text-sm">
      <a href="/public/index.php" class="px-3 py-1 rounded-lg border border-gray-200">Home</a>
      <a href="/public/index.php?r=quiz_start&preset=gem201" class="px-3 py-1 rounded-lg border border-sky-300 bg-sky-50">
        Level 200 / GEM 201 preset
      </a>
    </div>
  </div>

  <form method="post" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Taxonomy selection -->
    <div class="p-4 rounded-xl border border-gray-200 space-y-3">
      <div class="font-medium mb-2">Select scope</div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
        <!-- Level -->
        <select
          class="px-3 py-2 rounded-lg border border-gray-200"
          x-model="levelId"
          @change="onLevelChange"
        >
          <option value="">Select level</option>
          <template x-for="l in levels" :key="l.id">
            <option :value="l.id" x-text="l.label"></option>
          </template>
        </select>

        <!-- Module -->
        <select
          class="px-3 py-2 rounded-lg border border-gray-200"
          x-model="moduleId"
          @change="onModuleChange"
        >
          <option value="">Select module</option>
          <template x-for="m in modules" :key="m.id">
            <option :value="m.id" x-text="m.label"></option>
          </template>
        </select>

        <!-- Subject -->
        <select
          class="px-3 py-2 rounded-lg border border-gray-200"
          x-model="subjectId"
          @change="onSubjectChange"
        >
          <option value="">Select subject</option>
          <template x-for="s in subjects" :key="s.id">
            <option :value="s.id" x-text="s.label"></option>
          </template>
        </select>
      </div>

      <!-- Topics multi-select -->
      <div class="mt-3">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm font-medium">Topics (multi select)</span>
          <span class="text-xs text-gray-500" x-text="'Selected: ' + selectedTopicIds.length"></span>
        </div>

        <div class="border border-gray-200 rounded-xl max-h-64 overflow-y-auto p-2 bg-white">
          <template x-if="topics.length === 0">
            <p class="text-xs text-gray-500 px-1">Select a subject to load topics.</p>
          </template>

          <template x-for="t in topics" :key="t.id">
            <label class="flex items-center gap-2 px-2 py-1 text-sm hover:bg-gray-50 rounded-lg cursor-pointer">
              <input
                type="checkbox"
                class="rounded border-gray-300"
                :value="t.id"
                @change="toggleTopic(t.id, $event.target.checked)"
                :checked="selectedTopicIds.includes(t.id)"
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
          You can pick multiple topics from the selected subject. We will randomly draw questions from all selected topics.
        </p>
      </div>
    </div>

    <!-- Quiz options -->
    <div class="p-4 rounded-xl border border-gray-200 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
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
      </div>

      <div>
        <label class="block text-xs font-medium mb-1">Scoring mode</label>
        <select
          name="scoring_mode"
          x-model="scoringMode"
          class="w-full px-3 py-2 rounded-lg border border-gray-200"
        >
          <option value="standard">Standard (+1 for correct, 0 for wrong)</option>
          <option value="negative">Negative marking (+1 for correct, -1 for wrong)</option>
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
      </div>
    </div>

    <div class="flex justify-end">
      <button
        type="submit"
        class="px-4 py-2 rounded-lg border border-sky-400 bg-sky-500 text-white text-sm"
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

    selectedTopicIds: [],

    numQuestions: 20,
    scoringMode: 'standard',
    timerSeconds: 3600,

    async init() {
      try {
        const res = await fetch('/public/index.php?r=api_levels');
        this.levels = await res.json();

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

    toggleTopic(id, checked) {
      id = parseInt(id, 10);
      if (checked) {
        if (!this.selectedTopicIds.includes(id)) {
          this.selectedTopicIds.push(id);
        }
      } else {
        this.selectedTopicIds = this.selectedTopicIds.filter(tid => tid !== id);
      }
    },
  };
}
</script>
