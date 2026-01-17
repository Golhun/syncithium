<?php
/** @var array $results */
/** @var array $summary */
?>
<div class="max-w-4xl mx-auto" x-data="questionImport()" x-init="init()">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Import Questions (CSV)</h1>
    <a class="px-3 py-2 rounded-lg border border-gray-200" href="/public/index.php?r=admin_questions">Back</a>
  </div>

  <div class="p-4 rounded-xl border border-gray-200 bg-gray-50 text-sm">
    <div class="font-medium mb-2">CSV required columns</div>
    <div class="text-gray-700">
      question_text, option_a, option_b, option_c, option_d, correct_option
      <span class="text-gray-500">(optional: explanation, status)</span><br>
      <span class="text-gray-500">Optional taxonomy mapping columns:</span> level_code, module_code, subject_name, topic_name
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" class="mt-4 space-y-3">
    <?= csrf_field() ?>

    <div class="p-4 rounded-xl border border-gray-200">
      <div class="font-medium mb-2">Option A: choose a target topic for this import</div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
        <select class="px-3 py-2 rounded-lg border border-gray-200" x-model="levelId" @change="loadModules()">
          <option value="">Select level</option>
          <template x-for="l in levels" :key="l.id">
            <option :value="l.id" x-text="l.label"></option>
          </template>
        </select>

        <select class="px-3 py-2 rounded-lg border border-gray-200" x-model="moduleId" @change="loadSubjects()">
          <option value="">Select module</option>
          <template x-for="m in modules" :key="m.id">
            <option :value="m.id" x-text="m.label"></option>
          </template>
        </select>

        <select class="px-3 py-2 rounded-lg border border-gray-200" x-model="subjectId" @change="loadTopics()">
          <option value="">Select subject</option>
          <template x-for="s in subjects" :key="s.id">
            <option :value="s.id" x-text="s.label"></option>
          </template>
        </select>

        <select class="px-3 py-2 rounded-lg border border-gray-200" x-model="topicId">
          <option value="">Select topic</option>
          <template x-for="t in topics" :key="t.id">
            <option :value="t.id" x-text="t.label"></option>
          </template>
        </select>
      </div>

      <input type="hidden" name="topic_id" :value="topicId">
      <p class="text-xs text-gray-600 mt-2">
        If your CSV includes taxonomy columns, you can leave this blank.
      </p>
    </div>

    <div class="p-4 rounded-xl border border-gray-200">
      <div class="font-medium mb-2">Upload CSV</div>
      <input type="file" name="csv" accept=".csv" required>
    </div>

    <button class="px-4 py-2 rounded-lg border border-gray-200" type="submit">Import</button>
  </form>

  <?php if (!empty($results)): ?>
    <div class="mt-6 p-4 rounded-xl border border-gray-200">
      <div class="font-medium mb-2">Result summary</div>
      <div class="text-sm text-gray-700">
        Created: <?= (int)$summary['created'] ?> ,
        Duplicates: <?= (int)$summary['duplicate'] ?> ,
        Errors: <?= (int)$summary['error'] ?> ,
        Skipped: <?= (int)$summary['skipped'] ?>
      </div>

      <div class="mt-4 overflow-x-auto border border-gray-200 rounded-xl">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left p-3">Line</th>
              <th class="text-left p-3">Status</th>
              <th class="text-left p-3">Note</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($results as $r): ?>
            <tr class="border-t border-gray-200">
              <td class="p-3"><?= (int)$r['line'] ?></td>
              <td class="p-3"><?= htmlspecialchars((string)$r['status']) ?></td>
              <td class="p-3"><?= htmlspecialchars((string)$r['note']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
function questionImport() {
  return {
    levels: [],
    modules: [],
    subjects: [],
    topics: [],

    levelId: '',
    moduleId: '',
    subjectId: '',
    topicId: '',

    async init() {
  try {
    const res = await fetch(`/public/index.php?r=api_levels`);
    this.levels = await res.json();
  } catch (e) {
    // If levels cannot load, import can still work using CSV taxonomy columns.
    this.levels = [];
  }
},

    async loadModules() {
      this.modules = []; this.subjects = []; this.topics = [];
      this.moduleId = ''; this.subjectId = ''; this.topicId = '';
      if (!this.levelId) return;
      const res = await fetch(`/public/index.php?r=api_modules&level_id=${this.levelId}`);
      this.modules = await res.json();
    },

    async loadSubjects() {
      this.subjects = []; this.topics = [];
      this.subjectId = ''; this.topicId = '';
      if (!this.moduleId) return;
      const res = await fetch(`/public/index.php?r=api_subjects&module_id=${this.moduleId}`);
      this.subjects = await res.json();
    },

    async loadTopics() {
      this.topics = [];
      this.topicId = '';
      if (!this.subjectId) return;
      const res = await fetch(`/public/index.php?r=api_topics&subject_id=${this.subjectId}`);
      this.topics = await res.json();
    }
  }
}
</script>
