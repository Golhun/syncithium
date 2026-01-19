<?php
declare(strict_types=1);

/**
 * app/views/taxonomy_selector.php
 *
 * User-facing page to select topics for a quiz.
 *
 * Assumes global helpers:
 * - csrf_field()
 * - e()
 * - icon($name, $class='h-5 w-5', $variant='outline')
 */

?>

<div class="max-w-4xl mx-auto" x-data="quizTopicSelector()" x-init="init()">

  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mb-6">
    <div class="min-w-0">
      <div class="flex items-center gap-2">
        <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
          <?= icon('squares-2x2', 'h-5 w-5 text-sky-700', 'solid') ?>
        </div>
        <h1 class="text-2xl font-semibold text-slate-900">Select Quiz Topics</h1>
      </div>
      <p class="text-sm text-slate-600 mt-2">
        Choose one or more topics to start your quiz. You can select topics from different subjects.
      </p>
    </div>
  </div>

  <!-- Main UI -->
  <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">
    <div class="grid md:grid-cols-2">

      <!-- Left: Selection Tree -->
      <div class="p-5 border-b md:border-b-0 md:border-r border-slate-200">
        <div class="space-y-4">
          <!-- Levels -->
          <div>
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-800 mb-2">
              <?= icon('academic-cap', 'h-4 w-4 text-slate-600', 'outline') ?>
              <span>1. Select a Level</span>
            </div>
            <div class="space-y-1">
              <template x-for="level in levels" :key="'l'+level.id">
                <button @click="selectLevel(level.id)"
                        class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition"
                        :class="level.id === selectedLevelId ? 'bg-sky-100 text-sky-900' : 'hover:bg-slate-50'">
                  <span class="font-medium" x-text="level.code + (level.name ? ' - ' + level.name : '')"></span>
                </button>
              </template>
            </div>
          </div>

          <!-- Modules -->
          <div x-show="selectedLevelId && modules.length > 0" x-cloak>
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-800 mb-2">
              <?= icon('squares-2x2', 'h-4 w-4 text-slate-600', 'outline') ?>
              <span>2. Select a Module</span>
            </div>
            <div class="space-y-1">
              <template x-for="module in modules" :key="'m'+module.id">
                <button @click="selectModule(module.id)"
                        class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition"
                        :class="module.id === selectedModuleId ? 'bg-sky-100 text-sky-900' : 'hover:bg-slate-50'">
                  <span class="font-medium" x-text="module.code + (module.name ? ' - ' + module.name : '')"></span>
                </button>
              </template>
            </div>
          </div>

          <!-- Subjects -->
          <div x-show="selectedModuleId && subjects.length > 0" x-cloak>
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-800 mb-2">
              <?= icon('bookmark-square', 'h-4 w-4 text-slate-600', 'outline') ?>
              <span>3. Select a Subject</span>
            </div>
            <div class="space-y-1">
              <template x-for="subject in subjects" :key="'s'+subject.id">
                <button @click="selectSubject(subject.id)"
                        class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition"
                        :class="subject.id === selectedSubjectId ? 'bg-sky-100 text-sky-900' : 'hover:bg-slate-50'">
                  <span class="font-medium" x-text="subject.name"></span>
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Topics and Selections -->
      <div class="p-5">
        <!-- Topics list -->
        <div x-show="selectedSubjectId" x-cloak>
          <div class="flex items-center gap-2 text-sm font-semibold text-slate-800 mb-2">
            <?= icon('tag', 'h-4 w-4 text-slate-600', 'outline') ?>
            <span>4. Choose Topics</span>
          </div>
          <div class="max-h-64 overflow-y-auto space-y-2 p-3 rounded-xl bg-slate-50 ring-1 ring-slate-200">
            <template x-if="topics.length === 0">
              <div class="text-sm text-slate-500 p-2">No topics found for this subject.</div>
            </template>
            <template x-for="topic in topics" :key="'t'+topic.id">
              <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-100 cursor-pointer">
                <input type="checkbox" :value="topic.id" x-model="selectedTopicIds" class="rounded border-slate-400">
                <span class="text-sm font-medium text-slate-800" x-text="topic.name"></span>
              </label>
            </template>
          </div>
        </div>

        <!-- Selected topics summary -->
        <div class="mt-6">
          <div class="flex items-center justify-between gap-3 mb-2">
            <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
              <?= icon('check-circle', 'h-4 w-4 text-slate-600', 'outline') ?>
              <span>Your Selections</span>
            </div>
            <button @click="selectedTopicIds = []" x-show="selectedTopicIds.length > 0" x-cloak
                    class="text-xs font-semibold text-slate-600 hover:text-slate-900">
              Clear all
            </button>
          </div>

          <div class="p-3 rounded-xl bg-white ring-1 ring-slate-200 min-h-[80px]">
            <template x-if="selectedTopicIds.length === 0">
              <div class="text-sm text-slate-500 p-2 text-center">Select topics from the list to begin.</div>
            </template>
            <ul class="space-y-1">
              <template x-for="topicId in selectedTopicIds" :key="'sel'+topicId">
                <li class="flex items-center justify-between gap-2 text-sm text-slate-700">
                  <span x-text="getTopicName(topicId)"></span>
                  <button @click="selectedTopicIds = selectedTopicIds.filter(id => id !== topicId)">
                    <?= icon('x-circle', 'h-4 w-4 text-slate-400 hover:text-slate-600', 'solid') ?>
                  </button>
                </li>
              </template>
            </ul>
          </div>
        </div>

        <!-- Start Quiz Form -->
        <form method="post" action="/public/index.php?r=quiz_start" class="mt-6">
          <?= csrf_field() ?>
          <template x-for="topicId in selectedTopicIds">
            <input type="hidden" name="topic_ids[]" :value="topicId">
          </template>

          <div class="flex items-center gap-3">
            <div class="flex-1">
              <label for="question_count" class="block text-xs font-medium text-slate-600 mb-1">Number of questions</label>
              <select name="question_count" id="question_count"
                      class="w-full rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2.5 text-sm
                             focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
                <option>10</option>
                <option>20</option>
                <option>30</option>
                <option>50</option>
              </select>
            </div>

            <button type="submit"
                    :disabled="selectedTopicIds.length === 0"
                    class="self-end inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold
                           hover:opacity-95 active:opacity-90 transition disabled:opacity-50 disabled:cursor-not-allowed
                           focus:outline-none focus:ring-4 focus:ring-sky-100">
              <?= icon('play', 'h-4 w-4 text-white', 'solid') ?>
              <span>Start Quiz</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function quizTopicSelector() {
  return {
    levels: [], modules: [], subjects: [], topics: [],
    allTopics: {}, // Cache for topic names {id: {name: '...'}}
    selectedLevelId: null, selectedModuleId: null, selectedSubjectId: null,
    selectedTopicIds: [],
    loading: false, error: '',

    async init() {
      this.loading = true; this.error = '';
      try {
        const data = await this.fetchJson('/public/index.php?r=api_levels');
        this.levels = (data || []).map(this.normalize);
      } catch (e) { this.error = 'Could not load levels.'; console.error(e); } 
      finally { this.loading = false; }
    },
    async selectLevel(id) {
      this.selectedLevelId = id; this.selectedModuleId = null; this.selectedSubjectId = null;
      this.modules = []; this.subjects = []; this.topics = [];
      try {
        const data = await this.fetchJson(`/public/index.php?r=api_modules&level_id=${id}`);
        this.modules = (data || []).map(this.normalize);
      } catch (e) { this.error = 'Could not load modules.'; console.error(e); }
    },
    async selectModule(id) {
      this.selectedModuleId = id; this.selectedSubjectId = null;
      this.subjects = []; this.topics = [];
      try {
        const data = await this.fetchJson(`/public/index.php?r=api_subjects&module_id=${id}`);
        this.subjects = (data || []).map(this.normalize);
      } catch (e) { this.error = 'Could not load subjects.'; console.error(e); }
    },
    async selectSubject(id) {
      this.selectedSubjectId = id; this.topics = [];
      try {
        const data = await this.fetchJson(`/public/index.php?r=api_topics&subject_id=${id}`);
        this.topics = (data || []).map(this.normalize);
        this.topics.forEach(t => { if (!this.allTopics[t.id]) this.allTopics[t.id] = { id: t.id, name: t.name }; });
      } catch (e) { this.error = 'Could not load topics.'; console.error(e); }
    },
    getTopicName(id) { return this.allTopics[id]?.name || `Topic #${id}`; },
    normalize(item) { return { id: item.id, name: item.name || '', code: item.code || '' }; },
    async fetchJson(url) {
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error(`API Error (${res.status})`);
      return res.json();
    }
  }
}
</script>