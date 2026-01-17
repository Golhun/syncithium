<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Choose Topics</h1>
    <p class="text-sm text-slate-600">Select Level, Module, Subject, then choose one or more Topics.</p>
  </div>
  <div class="flex gap-2">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=logout">Sign out</a>
  </div>
</div>

<div
  x-data="taxonomySelector(<?= e(json_encode($levels, JSON_UNESCAPED_SLASHES)) ?>)"
  class="bg-white border border-slate-200 rounded-xl p-6 space-y-5"
>
  <div class="grid md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Level</label>
      <select x-model="levelId" @change="loadModules()"
        class="w-full rounded-lg border border-slate-300 px-3 py-2">
        <option value="">Select level</option>
        <template x-for="l in levels" :key="l.id">
          <option :value="l.id" x-text="l.code + (l.name ? (' , ' + l.name) : '')"></option>
        </template>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Module</label>
      <select x-model="moduleId" @change="loadSubjects()" :disabled="modules.length === 0"
        class="w-full rounded-lg border border-slate-300 px-3 py-2">
        <option value="">Select module</option>
        <template x-for="m in modules" :key="m.id">
          <option :value="m.id" x-text="m.label"></option>
        </template>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Subject</label>
      <select x-model="subjectId" @change="loadTopics()" :disabled="subjects.length === 0"
        class="w-full rounded-lg border border-slate-300 px-3 py-2">
        <option value="">Select subject</option>
        <template x-for="s in subjects" :key="s.id">
          <option :value="s.id" x-text="s.label"></option>
        </template>
      </select>
    </div>
  </div>

  <div class="border-t border-slate-200 pt-5">
    <div class="flex items-center justify-between mb-3">
      <div>
        <div class="font-semibold">Topics</div>
        <div class="text-sm text-slate-600">Multi-select, these will drive your quiz later.</div>
      </div>
      <div class="flex gap-2">
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
          @click="selectAll()" :disabled="topics.length === 0">Select all</button>
        <button type="button" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
          @click="clearAll()" :disabled="selectedTopicIds.length === 0">Clear</button>
      </div>
    </div>

    <div x-show="topics.length === 0" class="text-sm text-slate-600">
      Choose a subject to load topics.
    </div>

    <div x-show="topics.length > 0" class="grid md:grid-cols-2 gap-2">
      <template x-for="t in topics" :key="t.id">
        <label class="flex items-center gap-2 border border-slate-200 rounded-lg p-3 hover:bg-slate-50">
          <input type="checkbox" :value="t.id" @change="toggleTopic(t.id)" :checked="selectedTopicIds.includes(t.id)">
          <span x-text="t.label"></span>
        </label>
      </template>
    </div>

    <div class="mt-5">
      <button type="button"
        class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95"
        @click="showSelection()"
        :disabled="selectedTopicIds.length === 0">
        Continue
      </button>

      <div class="text-xs text-slate-500 mt-2">
        For now, "Continue" displays your chosen topic IDs. In Phase 5 we will start a quiz using these selections.
      </div>
    </div>
  </div>
</div>

<script>
function taxonomySelector(levels) {
  return {
    levels: levels || [],
    levelId: '',
    moduleId: '',
    subjectId: '',
    modules: [],
    subjects: [],
    topics: [],
    selectedTopicIds: [],

    async loadModules() {
      this.moduleId = '';
      this.subjectId = '';
      this.modules = [];
      this.subjects = [];
      this.topics = [];
      this.selectedTopicIds = [];

      if (!this.levelId) return;

      const res = await fetch(`/public/index.php?r=api_modules&level_id=${encodeURIComponent(this.levelId)}`);
      this.modules = await res.json();
    },

    async loadSubjects() {
      this.subjectId = '';
      this.subjects = [];
      this.topics = [];
      this.selectedTopicIds = [];

      if (!this.moduleId) return;

      const res = await fetch(`/public/index.php?r=api_subjects&module_id=${encodeURIComponent(this.moduleId)}`);
      this.subjects = await res.json();
    },

    async loadTopics() {
      this.topics = [];
      this.selectedTopicIds = [];

      if (!this.subjectId) return;

      const res = await fetch(`/public/index.php?r=api_topics&subject_id=${encodeURIComponent(this.subjectId)}`);
      this.topics = await res.json();
    },

    toggleTopic(id) {
      id = Number(id);
      const idx = this.selectedTopicIds.indexOf(id);
      if (idx >= 0) this.selectedTopicIds.splice(idx, 1);
      else this.selectedTopicIds.push(id);
    },

    selectAll() {
      this.selectedTopicIds = this.topics.map(t => Number(t.id));
    },

    clearAll() {
      this.selectedTopicIds = [];
    },

    showSelection() {
      if (!window.alertify) return;
      alertify.message(`Selected topic IDs: ${this.selectedTopicIds.join(', ')}`);
    }
  }
}
</script>
