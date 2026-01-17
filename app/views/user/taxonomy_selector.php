<?php
declare(strict_types=1);

/** @var array $levels */
/** @var array $user */
?>
<div class="max-w-4xl mx-auto" x-data="taxonomySelector()" x-init="init()">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-xl font-semibold">Choose Topics</h1>
      <p class="text-xs text-gray-600">
        Signed in as <?= htmlspecialchars((string)$user['email']) ?> (<?= htmlspecialchars((string)$user['role']) ?>)
      </p>
    </div>
  </div>

  <div class="mb-4 p-4 rounded-xl border border-gray-200 bg-gray-50 text-sm">
    <div class="font-medium mb-1">Quick presets</div>
    <button type="button"
            class="px-3 py-1.5 rounded-lg border border-sky-600 text-sky-700 text-xs hover:bg-sky-50"
            @click="presetLevel200GEM201()">
      Level 200 / GEM 201
    </button>
    <span class="ml-2 text-xs text-gray-500" x-text="presetStatus"></span>
  </div>

  <div class="p-4 rounded-xl border border-gray-200">
    <div class="font-medium mb-2">Select Level → Module → Subject → Topic(s)</div>

    <!-- Search helpers for large lists -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-2 text-xs">
      <input type="text" x-model="moduleSearch" placeholder="Search module"
             class="px-2 py-1 rounded-lg border border-gray-200" />
      <input type="text" x-model="subjectSearch" placeholder="Search subject"
             class="px-2 py-1 rounded-lg border border-gray-200" />
      <input type="text" x-model="topicSearch" placeholder="Search topic"
             class="px-2 py-1 rounded-lg border border-gray-200" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
      <select class="px-3 py-2 rounded-lg border border-gray-200"
              x-model="levelId" @change="loadModules()">
        <option value="">Select level</option>
        <template x-for="l in levels" :key="l.id">
          <option :value="l.id" x-text="l.label"></option>
        </template>
      </select>

      <select class="px-3 py-2 rounded-lg border border-gray-200"
              x-model="moduleId" @change="loadSubjects()">
        <option value="">Select module</option>
        <template x-for="m in filteredModules()" :key="m.id">
          <option :value="m.id" x-text="m.label"></option>
        </template>
      </select>

      <select class="px-3 py-2 rounded-lg border border-gray-200"
              x-model="subjectId" @change="loadTopics()">
        <option value="">Select subject</option>
        <template x-for="s in filteredSubjects()" :key="s.id">
          <option :value="s.id" x-text="s.label"></option>
        </template>
      </select>

      <!-- Multi-select topics: ctrl+click or cmd+click to pick many -->
      <select multiple
              class="px-3 py-2 rounded-lg border border-gray-200 h-32"
              x-model="topicIds">
        <template x-for="t in filteredTopics()" :key="t.id">
          <option :value="t.id" x-text="t.label"></option>
        </template>
      </select>
    </div>

    <p class="text-xs text-gray-600 mt-2">
      This screen is your “quiz start” selector. In Phase 5 we will wire it to launch timed quizzes for the chosen topics.
    </p>
  </div>

  <!-- Placeholder form for future quiz start (Phase 5) -->
  <form class="mt-4" @submit.prevent="previewSelection()">
    <input type="hidden" name="level_id" :value="levelId">
    <input type="hidden" name="module_id" :value="moduleId">
    <input type="hidden" name="subject_id" :value="subjectId">
    <input type="hidden" name="topic_ids" :value="topicIds.join(',')">

    <button type="submit"
            class="px-4 py-2 rounded-lg bg-sky-600 text-white text-sm hover:bg-sky-700 disabled:opacity-40"
            :disabled="topicIds.length === 0">
      Preview selection
    </button>
    <span class="ml-2 text-xs text-gray-500" x-text="selectionLabel"></span>
  </form>
</div>

<script>
function taxonomySelector() {
  return {
    levels: [],
    modules: [],
    subjects: [],
    topics: [],

    levelId: '',
    moduleId: '',
    subjectId: '',
    topicIds: [],

    moduleSearch: '',
    subjectSearch: '',
    topicSearch: '',

    presetStatus: '',
    selectionLabel: '',

    async init() {
      await this.loadLevels();
    },

    async loadLevels() {
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
      this.topicIds = [];
      this.moduleSearch = '';
      this.subjectSearch = '';
      this.topicSearch = '';

      if (!this.levelId) return;

      const res = await fetch(`/public/index.php?r=api_modules&level_id=${this.levelId}`);
      this.modules = await res.json();
    },

    async loadSubjects() {
      this.subjects = [];
      this.topics = [];
      this.subjectId = '';
      this.topicIds = [];
      this.subjectSearch = '';
      this.topicSearch = '';

      if (!this.moduleId) return;

      const res = await fetch(`/public/index.php?r=api_subjects&module_id=${this.moduleId}`);
      this.subjects = await res.json();
    },

    async loadTopics() {
      this.topics = [];
      this.topicIds = [];
      this.topicSearch = '';

      if (!this.subjectId) return;

      const res = await fetch(`/public/index.php?r=api_topics&subject_id=${this.subjectId}`);
      this.topics = await res.json();
    },

    filteredModules() {
      const q = this.moduleSearch.toLowerCase().trim();
      if (!q) return this.modules;
      return this.modules.filter(m => m.label.toLowerCase().includes(q));
    },

    filteredSubjects() {
      const q = this.subjectSearch.toLowerCase().trim();
      if (!q) return this.subjects;
      return this.subjects.filter(s => s.label.toLowerCase().includes(q));
    },

    filteredTopics() {
      const q = this.topicSearch.toLowerCase().trim();
      if (!q) return this.topics;
      return this.topics.filter(t => t.label.toLowerCase().includes(q));
    },

    async presetLevel200GEM201() {
      this.presetStatus = 'Looking for Level 200 / GEM 201...';

      if (!this.levels.length) {
        await this.loadLevels();
      }

      // Find level whose label starts with or contains "200"
      const level = this.levels.find(l => l.label.toString().startsWith('200'))
                    || this.levels.find(l => l.label.toString().includes('200'));

      if (!level) {
        this.presetStatus = 'Level 200 not found.';
        return;
      }

      this.levelId = level.id;
      await this.loadModules();

      const module = this.modules.find(m => m.label.toUpperCase().includes('GEM 201'));
      if (!module) {
        this.presetStatus = 'Module GEM 201 not found under level 200.';
        return;
      }

      this.moduleId = module.id;
      await this.loadSubjects();

      this.presetStatus = 'Preset applied. Choose subject and topics to continue.';
    },

    previewSelection() {
      const parts = [];
      if (this.levelId) parts.push('Level ID ' + this.levelId);
      if (this.moduleId) parts.push('Module ID ' + this.moduleId);
      if (this.subjectId) parts.push('Subject ID ' + this.subjectId);
      if (this.topicIds.length) parts.push('Topics: ' + this.topicIds.join(', '));
      this.selectionLabel = parts.join(' | ');
    },
  }
}
</script>
