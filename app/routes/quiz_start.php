<?php
/** @var string $title */
?>
<div class="max-w-4xl mx-auto" x-data="quizStart()" x-init="init()">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Start a quiz</h1>
  </div>

  <div class="p-4 rounded-xl border border-gray-200 bg-gray-50 text-sm mb-4">
    <p class="text-gray-700">
      Choose one or more topics, pick how many questions you want, set the scoring mode,
      and start the timer. Once the timer ends, your quiz is auto-submitted.
    </p>
  </div>

  <form method="post" action="/public/index.php?r=quiz_start" class="space-y-4">
    <?= csrf_field() ?>

    <!-- Taxonomy selection -->
    <div class="p-4 rounded-xl border border-gray-200">
      <div class="font-medium mb-3">Choose topics</div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
        <!-- Level -->
        <select class="px-3 py-2 rounded-lg border border-gray-200"
                x-model="levelId"
                @change="loadModules()">
          <option value="">Select level</option>
          <template x-for="l in levels" :key="l.id">
            <option :value="l.id" x-text="l.label"></option>
          </template>
        </select>

        <!-- Module -->
        <select class="px-3 py-2 rounded-lg border border-gray-200"
                x-model="moduleId"
                @change="loadSubjects()">
          <option value="">Select module</option>
          <template x-for="m in modules" :key="m.id">
            <option :value="m.id" x-text="m.label"></option>
          </template>
        </select>

        <!-- Subject -->
        <select class="px-3 py-2 rounded-lg border border-gray-200"
                x-model="subjectId"
                @change="loadTopics()">
          <option value="">Select subject</option>
          <template x-for="s in subjects" :key="s.id">
            <option :value="s.id" x-text="s.label"></option>
          </template>
        </select>
      </div>

      <div class="mt-3 border border-gray-200 rounded-xl max-h-64 overflow-y-auto">
        <div class="px-3 py-2 text-xs font-medium text-gray-600 bg-gray-50 border-b border-gray-200">
          Topics under selected subject
        </div>
        <template x-if="topics.length === 0">
          <div class="px-3 py-3 text-xs text-gray-500">
            Select a subject to load its topics.
          </div>
        </template>
        <template x-for="t in topics" :key="t.id">
          <label class="flex items-center gap-2 px-3 py-2 text-sm border-b border-gray-100">
            <input type="checkbox"
                   class="rounded border-gray-300"
                   name="topic_ids[]"
                   :value="t.id">
            <span x-text="t.label"></span>
          </label>
        </template>
      </div>

      <p class="mt-2 text-xs text-gray-600">
        You can select multiple topics; questions will be randomly drawn across them.
      </p>
    </div>

    <!-- Quiz configuration -->
    <div class="p-4 rounded-xl border border-gray-200 grid grid-cols-1 md:grid-cols-3 gap-4">
      <!-- Number of questions -->
      <div>
        <div class="font-medium mb-1 text-sm">Number of questions</div>
        <input type="number"
               name="num_questions"
               class="px-3 py-2 rounded-lg border border-gray-200 w-full"
               min="1" max="100" value="20">
        <p class="text-xs text-gray-500 mt-1">Max 100 per attempt.</p>
      </div>

      <!-- Scoring mode -->
      <div>
        <div class="font-medium mb-1 text-sm">Scoring mode</div>
        <label class="flex items-center gap-2 text-sm mb-1">
          <input type="radio" name="scoring_mode" value="standard" checked>
          <span>Standard (+1 correct, 0 wrong)</span>
        </label>
        <label class="flex items-center gap-2 text-sm">
          <input type="radio" name="scoring_mode" value="negative">
          <span>Negative marking (+1 correct, -1 wrong)</span>
        </label>
      </div>

      <!-- Timer -->
      <div>
        <div class="font-medium mb-1 text-sm">Timer</div>
        <select name="timer_seconds" class="px-3 py-2 rounded-lg border border-gray-200 w-full">
          <option value="1800">30 minutes</option>
          <option value="2700">45 minutes</option>
          <option value="3600" selected>60 minutes</option>
          <option value="4500">75 minutes</option>
          <option value="5400">90 minutes</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">
          Quiz will auto-submit when the timer ends.
        </p>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <button type="submit"
              class="px-4 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-sm">
        Start quiz
      </button>
    </div>
  </form>
</div>

<script>
function quizStart() {
  return {
    levels: [],
    modules: [],
    subjects: [],
    topics: [],

    levelId: '',
    moduleId: '',
    subjectId: '',

    async init() {
      try {
        const res = await fetch('/public/index.php?r=api_levels');
        this.levels = await res.json();
      } catch (e) {
        this.levels = [];
      }
    },

    async loadModules() {
      this.modules = [];
      this.subjects = [];
      this.topics = [];
      this.moduleId = '';
      this.subjectId = '';

      if (!this.levelId) return;

      const res = await fetch(`/public/index.php?r=api_modules&level_id=${this.levelId}`);
      this.modules = await res.json();
    },

    async loadSubjects() {
      this.subjects = [];
      this.topics = [];
      this.subjectId = '';

      if (!this.moduleId) return;

      const res = await fetch(`/public/index.php?r=api_subjects&module_id=${this.moduleId}`);
      this.subjects = await res.json();
    },

    async loadTopics() {
      this.topics = [];

      if (!this.subjectId) return;

      const res = await fetch(`/public/index.php?r=api_topics&subject_id=${this.subjectId}`);
      this.topics = await res.json();
    }
  }
}
</script>
