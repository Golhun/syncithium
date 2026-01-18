<?php
/** @var array $results */
/** @var array $summary */

declare(strict_types=1);

function e4(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// Local heroicons helper for views (layout helper is not in scope here)
function hi_svg_view(string $name, string $variant = 'outline', string $class = 'h-5 w-5'): string
{
  $variant = ($variant === 'solid') ? 'solid' : 'outline';

  // app/views/admin -> project root is 4 levels up
  $root = dirname(__DIR__, 4);
  $path = $root . '/public/assets/icons/heroicons/24/' . $variant . '/' . $name . '.svg';

  if (!is_file($path)) return '';
  $svg = file_get_contents($path);
  if ($svg === false) return '';

  $svg = preg_replace(
    '/<svg\b([^>]*)>/',
    '<svg$1 class="' . e4($class) . '" aria-hidden="true" focusable="false">',
    $svg,
    1
  );

  return $svg ?: '';
}

function status_badge(string $status): string {
  $s = strtolower(trim($status));
  $map = [
    'created'   => ['bg-emerald-50 text-emerald-800 ring-emerald-200', 'check-circle'],
    'duplicate' => ['bg-amber-50 text-amber-900 ring-amber-200', 'document-duplicate'],
    'error'     => ['bg-rose-50 text-rose-800 ring-rose-200', 'exclamation-triangle'],
    'skipped'   => ['bg-gray-50 text-gray-700 ring-gray-200', 'minus-circle'],
    'exception' => ['bg-rose-50 text-rose-800 ring-rose-200', 'x-circle'],
  ];
  [$cls, $icon] = $map[$s] ?? ['bg-gray-50 text-gray-700 ring-gray-200', 'information-circle'];

  return '<span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-xl ring-1 ' . $cls . '">'
    . hi_svg_view($icon, 'outline', 'h-4 w-4')
    . '<span class="text-xs font-medium">' . e4($status) . '</span>'
    . '</span>';
}

$results = $results ?? [];
$summary = $summary ?? ['created'=>0,'duplicate'=>0,'error'=>0,'skipped'=>0];
?>

<div class="max-w-5xl mx-auto" x-data="questionImportV2()" x-init="init()">
  <!-- Page header -->
  <div class="flex items-start justify-between gap-4 mb-6">
    <div>
      <div class="flex items-center gap-2">
        <?= hi_svg_view('arrow-up-tray', 'solid', 'h-6 w-6 text-sky-600') ?>
        <h1 class="text-2xl font-semibold">Import Questions</h1>
      </div>
      <p class="text-sm text-gray-600 mt-1">
        Generate a DB-matched template first, then upload your filled CSV. This prevents taxonomy mismatch issues.
      </p>
    </div>

    <a class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm"
       href="/public/index.php?r=admin_questions">
      <?= hi_svg_view('arrow-left', 'outline', 'h-4 w-4') ?>
      <span>Back</span>
    </a>
  </div>

  <!-- Step cards -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
    <!-- Step 1: Template generator -->
    <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex items-center gap-2">
          <?= hi_svg_view('document-text', 'outline', 'h-5 w-5') ?>
          <div>
            <div class="text-sm font-semibold">Step 1, Download a DB-matched template</div>
            <div class="text-xs text-gray-500">Choose taxonomy from your database, we prefill IDs and names.</div>
          </div>
        </div>
      </div>

      <div class="p-5 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Level</label>
            <select class="w-full px-3 py-2 rounded-xl ring-1 ring-gray-200 bg-white text-sm"
                    x-model="levelId" @change="onLevelChange">
              <option value="">Select level</option>
              <template x-for="l in levels" :key="l.id">
                <option :value="l.id" x-text="l.label"></option>
              </template>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Module</label>
            <select class="w-full px-3 py-2 rounded-xl ring-1 ring-gray-200 bg-white text-sm"
                    x-model="moduleId" @change="onModuleChange" :disabled="!levelId">
              <option value="">Select module</option>
              <template x-for="m in modules" :key="m.id">
                <option :value="m.id" x-text="m.label"></option>
              </template>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">Subject</label>
            <select class="w-full px-3 py-2 rounded-xl ring-1 ring-gray-200 bg-white text-sm"
                    x-model="subjectId" @change="onSubjectChange" :disabled="!moduleId">
              <option value="">Select subject</option>
              <template x-for="s in subjects" :key="s.id">
                <option :value="s.id" x-text="s.label"></option>
              </template>
            </select>
            <p class="mt-2 text-xs text-gray-500 flex items-center gap-2">
              <?= hi_svg_view('information-circle', 'outline', 'h-4 w-4 text-gray-400') ?>
              <span>Template can include all topics under a subject so you can populate many topics in one file.</span>
            </p>
          </div>

          <div class="md:col-span-2">
            <div class="flex items-center justify-between gap-3">
              <label class="text-xs font-medium text-gray-700">Template scope</label>
              <span class="text-xs text-gray-500" x-text="topicCountHint"></span>
            </div>

            <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
              <label class="flex items-center gap-2 p-3 rounded-xl ring-1 ring-gray-200 bg-white cursor-pointer hover:bg-gray-50">
                <input type="radio" name="scope" value="subject" x-model="scope" class="border-gray-300">
                <div class="min-w-0">
                  <div class="text-sm font-semibold text-gray-900">All topics under subject</div>
                  <div class="text-xs text-gray-500">Recommended for bulk authoring.</div>
                </div>
              </label>

              <label class="flex items-center gap-2 p-3 rounded-xl ring-1 ring-gray-200 bg-white cursor-pointer hover:bg-gray-50">
                <input type="radio" name="scope" value="topic" x-model="scope" class="border-gray-300">
                <div class="min-w-0">
                  <div class="text-sm font-semibold text-gray-900">Only one topic</div>
                  <div class="text-xs text-gray-500">Pick a topic below.</div>
                </div>
              </label>
            </div>
          </div>

          <div class="md:col-span-2" x-show="scope === 'topic'">
            <label class="block text-xs font-medium text-gray-700 mb-1">Topic (required for topic-only template)</label>
            <select class="w-full px-3 py-2 rounded-xl ring-1 ring-gray-200 bg-white text-sm"
                    x-model="topicId" :disabled="!subjectId">
              <option value="">Select topic</option>
              <template x-for="t in topics" :key="t.id">
                <option :value="t.id" x-text="t.label"></option>
              </template>
            </select>
          </div>
        </div>

        <div class="rounded-2xl ring-1 ring-gray-200 bg-gray-50 p-4 text-sm">
          <div class="flex items-start gap-3">
            <?= hi_svg_view('bolt', 'outline', 'h-5 w-5 text-gray-500') ?>
            <div class="min-w-0">
              <div class="font-semibold text-gray-900">How to use the template</div>
              <ol class="mt-2 space-y-1 text-xs text-gray-700 list-decimal list-inside">
                <li>Download template, do not edit the taxonomy ID columns.</li>
                <li>Fill only question_text, options A–D, correct_option (A–D), explanation (optional), status (optional).</li>
                <li>Leave unused rows blank, they will be skipped safely.</li>
              </ol>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between gap-3">
          <a class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-900 text-white hover:opacity-90 text-sm"
             :class="canDownloadTemplate() ? '' : 'opacity-50 pointer-events-none'"
             :href="templateUrl()">
            <?= hi_svg_view('arrow-down-tray', 'solid', 'h-5 w-5') ?>
            <span>Download template CSV</span>
          </a>

          <div class="text-xs text-gray-500 flex items-center gap-2">
            <?= hi_svg_view('shield-check', 'outline', 'h-4 w-4 text-gray-400') ?>
            <span>Template uses topic_id to prevent taxonomy mismatch.</span>
          </div>
        </div>

        <template x-if="apiError">
          <div class="p-3 rounded-xl border border-rose-200 bg-rose-50 text-xs text-rose-800">
            <div class="font-semibold mb-1">API error</div>
            <div x-text="apiError"></div>
          </div>
        </template>
      </div>
    </div>

    <!-- Step 2: Upload + import -->
    <div class="rounded-2xl border border-gray-200 bg-white overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex items-center gap-2">
          <?= hi_svg_view('arrow-up-tray', 'outline', 'h-5 w-5') ?>
          <div>
            <div class="text-sm font-semibold">Step 2, Upload your filled CSV</div>
            <div class="text-xs text-gray-500">Import runs in strict mode by default.</div>
          </div>
        </div>
      </div>

      <div class="p-5 space-y-4">
        <form method="post" enctype="multipart/form-data" class="space-y-4">
          <?= csrf_field() ?>

          <div class="p-4 rounded-2xl ring-1 ring-gray-200 bg-white">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="text-sm font-semibold text-gray-900">Upload CSV</div>
                <p class="text-xs text-gray-500 mt-1">
                  Required fields per row: question_text, option_a, option_b, option_c, option_d, correct_option.
                </p>
              </div>
              <div class="text-gray-400">
                <?= hi_svg_view('paper-clip', 'outline', 'h-5 w-5') ?>
              </div>
            </div>

            <div class="mt-3">
              <input class="block w-full text-sm file:mr-4 file:py-2 file:px-3 file:rounded-xl file:border-0 file:bg-sky-600 file:text-white hover:file:opacity-90"
                     type="file" name="csv" accept=".csv,.tsv,text/csv" required>
            </div>

            <div class="mt-3 text-xs text-gray-500 flex items-center gap-2">
              <?= hi_svg_view('information-circle', 'outline', 'h-4 w-4 text-gray-400') ?>
              <span>If you downloaded the template, topic_id is already correct, and import will be clean.</span>
            </div>
          </div>

          <!-- Fallback topic selector (optional) -->
          <div class="p-4 rounded-2xl ring-1 ring-gray-200 bg-gray-50">
            <div class="flex items-start gap-3">
              <?= hi_svg_view('adjustments-horizontal', 'outline', 'h-5 w-5 text-gray-500') ?>
              <div class="min-w-0">
                <div class="text-sm font-semibold text-gray-900">Fallback topic (optional)</div>
                <p class="text-xs text-gray-600 mt-1">
                  Only needed if your CSV does not contain topic_id and you are not using taxonomy columns.
                </p>

                <div class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-2">
                  <select class="px-3 py-2 rounded-xl ring-1 ring-gray-200 bg-white text-sm"
                          x-model="fallbackLevelId" @change="loadFallbackModules()">
                    <option value="">Level</option>
                    <template x-for="l in levels" :key="'fb-l-' + l.id">
                      <option :value="l.id" x-text="l.label"></option>
                    </template>
                  </select>

                  <select class="px-3 py-2 rounded-xl ring-1 ring-gray-200 bg-white text-sm"
                          x-model="fallbackModuleId" @change="loadFallbackSubjects()" :disabled="!fallbackLevelId">
                    <option value="">Module</option>
                    <template x-for="m in fallbackModules" :key="'fb-m-' + m.id">
                      <option :value="m.id" x-text="m.label"></option>
                    </template>
                  </select>

                  <select class="px-3 py-2 rounded-xl ring-1 ring-gray-200 bg-white text-sm"
                          x-model="fallbackSubjectId" @change="loadFallbackTopics()" :disabled="!fallbackModuleId">
                    <option value="">Subject</option>
                    <template x-for="s in fallbackSubjects" :key="'fb-s-' + s.id">
                      <option :value="s.id" x-text="s.label"></option>
                    </template>
                  </select>

                  <select class="px-3 py-2 rounded-xl ring-1 ring-gray-200 bg-white text-sm"
                          x-model="fallbackTopicId" :disabled="!fallbackSubjectId">
                    <option value="">Topic</option>
                    <template x-for="t in fallbackTopics" :key="'fb-t-' + t.id">
                      <option :value="t.id" x-text="t.label"></option>
                    </template>
                  </select>
                </div>

                <input type="hidden" name="topic_id" :value="fallbackTopicId">

                <div class="mt-3 text-xs text-gray-500">
                  Recommended: use the template instead, it is cleaner.
                </div>
              </div>
            </div>
          </div>

          <!-- Safety toggle -->
          <div class="p-4 rounded-2xl ring-1 ring-gray-200 bg-white">
            <label class="flex items-start gap-3 cursor-pointer">
              <input type="checkbox" class="mt-1 rounded border-gray-300" x-model="allowCreateTaxonomy">
              <div class="min-w-0">
                <div class="text-sm font-semibold text-gray-900">
                  Allow creating missing taxonomy during import
                </div>
                <div class="text-xs text-gray-600 mt-1">
                  Off by default. Keep it off to prevent accidental creation of new subjects/topics due to spelling differences.
                </div>
              </div>
            </label>
            <input type="hidden" name="allow_create_taxonomy" :value="allowCreateTaxonomy ? 1 : 0">
          </div>

          <div class="flex items-center justify-end">
            <button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-sky-600 text-white hover:bg-sky-700 text-sm"
                    type="submit">
              <?= hi_svg_view('rocket-launch', 'solid', 'h-5 w-5') ?>
              <span>Import now</span>
            </button>
          </div>
        </form>

        <div class="text-xs text-gray-500 flex items-center gap-2">
          <?= hi_svg_view('lock-closed', 'outline', 'h-4 w-4 text-gray-400') ?>
          <span>Strict import uses topic_id if present. This is how we keep your DB clean.</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Results -->
  <?php if (!empty($results)): ?>
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white overflow-hidden">
      <div class="px-5 py-4 border-b border-gray-200 bg-gray-50">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-2">
            <?= hi_svg_view('chart-bar', 'outline', 'h-5 w-5') ?>
            <div>
              <div class="text-sm font-semibold">Import results</div>
              <div class="text-xs text-gray-500">Summary and per-line outcomes</div>
            </div>
          </div>

          <div class="flex flex-wrap gap-2 text-xs">
            <span class="inline-flex items-center px-2.5 py-1 rounded-xl ring-1 ring-emerald-200 bg-emerald-50 text-emerald-800">
              Created: <?= (int)$summary['created'] ?>
            </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-xl ring-1 ring-amber-200 bg-amber-50 text-amber-900">
              Duplicates: <?= (int)$summary['duplicate'] ?>
            </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-xl ring-1 ring-rose-200 bg-rose-50 text-rose-800">
              Errors: <?= (int)$summary['error'] ?>
            </span>
            <span class="inline-flex items-center px-2.5 py-1 rounded-xl ring-1 ring-gray-200 bg-gray-50 text-gray-700">
              Skipped: <?= (int)$summary['skipped'] ?>
            </span>
          </div>
        </div>
      </div>

      <div class="p-5">
        <div class="overflow-x-auto rounded-2xl ring-1 ring-gray-200">
          <table class="min-w-full text-sm bg-white">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left p-3 text-xs font-semibold text-gray-600">Line</th>
                <th class="text-left p-3 text-xs font-semibold text-gray-600">Status</th>
                <th class="text-left p-3 text-xs font-semibold text-gray-600">Note</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $r): ?>
              <tr class="border-t border-gray-200">
                <td class="p-3 text-gray-700"><?= (int)($r['line'] ?? 0) ?></td>
                <td class="p-3"><?= status_badge((string)($r['status'] ?? '')) ?></td>
                <td class="p-3 text-gray-700 whitespace-pre-wrap"><?= e4((string)($r['note'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
function questionImportV2() {
  return {
    // Taxonomy for template
    levels: [],
    modules: [],
    subjects: [],
    topics: [],
    levelId: '',
    moduleId: '',
    subjectId: '',
    topicId: '',
    scope: 'subject',
    apiError: '',
    topicCountHint: '',

    // Fallback taxonomy selector (for import)
    fallbackModules: [],
    fallbackSubjects: [],
    fallbackTopics: [],
    fallbackLevelId: '',
    fallbackModuleId: '',
    fallbackSubjectId: '',
    fallbackTopicId: '',

    // Import safety
    allowCreateTaxonomy: false,

    // ---------- helpers ----------
    async fetchJson(url) {
      const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
      const text = await res.text();
      if (!res.ok) throw new Error(`Request failed (${res.status}) for ${url}: ${text.slice(0, 200)}`);
      try { return JSON.parse(text); }
      catch { throw new Error(`Invalid JSON from ${url}: ${text.slice(0, 200)}`); }
    },

    normLevel(l) {
      return {
        id: Number(l.id ?? l.level_id),
        label: l.label ?? l.code ?? l.name ?? ('Level ' + (l.id ?? l.level_id)),
      };
    },
    normModule(m) {
      return {
        id: Number(m.id ?? m.module_id),
        label: m.label ?? m.code ?? m.name ?? ('Module ' + (m.id ?? m.module_id)),
      };
    },
    normSubject(s) {
      return {
        id: Number(s.id ?? s.subject_id),
        label: s.label ?? s.name ?? ('Subject ' + (s.id ?? s.subject_id)),
      };
    },
    normTopic(t) {
      return {
        id: Number(t.id ?? t.topic_id),
        label: t.label ?? t.name ?? ('Topic ' + (t.id ?? t.topic_id)),
      };
    },

    canDownloadTemplate() {
      if (!this.subjectId) return false;
      if (this.scope === 'topic' && !this.topicId) return false;
      return true;
    },

    templateUrl() {
      const params = new URLSearchParams();
      params.set('r', 'admin_questions_import_template');
      params.set('scope', this.scope);

      if (this.levelId) params.set('level_id', this.levelId);
      if (this.moduleId) params.set('module_id', this.moduleId);
      if (this.subjectId) params.set('subject_id', this.subjectId);
      if (this.scope === 'topic' && this.topicId) params.set('topic_id', this.topicId);

      return '/public/index.php?' + params.toString();
    },

    // ---------- init ----------
    async init() {
      this.apiError = '';
      try {
        const data = await this.fetchJson('/public/index.php?r=api_levels');
        this.levels = (data || []).map(x => this.normLevel(x));
      } catch (e) {
        this.levels = [];
        this.apiError = e?.message ? String(e.message) : 'Failed to load levels.';
      }
    },

    // ---------- template flow ----------
    async onLevelChange() {
      this.apiError = '';
      this.moduleId = '';
      this.subjectId = '';
      this.topicId = '';
      this.modules = [];
      this.subjects = [];
      this.topics = [];
      this.topicCountHint = '';
      if (!this.levelId) return;

      try {
        const data = await this.fetchJson('/public/index.php?r=api_modules&level_id=' + encodeURIComponent(this.levelId));
        this.modules = (data || []).map(x => this.normModule(x));
      } catch (e) {
        this.modules = [];
        this.apiError = e?.message ? String(e.message) : 'Failed to load modules.';
      }
    },

    async onModuleChange() {
      this.apiError = '';
      this.subjectId = '';
      this.topicId = '';
      this.subjects = [];
      this.topics = [];
      this.topicCountHint = '';
      if (!this.moduleId) return;

      try {
        const data = await this.fetchJson('/public/index.php?r=api_subjects&module_id=' + encodeURIComponent(this.moduleId));
        this.subjects = (data || []).map(x => this.normSubject(x));
      } catch (e) {
        this.subjects = [];
        this.apiError = e?.message ? String(e.message) : 'Failed to load subjects.';
      }
    },

    async onSubjectChange() {
      this.apiError = '';
      this.topicId = '';
      this.topics = [];
      this.topicCountHint = '';
      if (!this.subjectId) return;

      try {
        const data = await this.fetchJson('/public/index.php?r=api_topics&subject_id=' + encodeURIComponent(this.subjectId));
        this.topics = (data || []).map(x => this.normTopic(x));
        this.topicCountHint = (this.topics.length > 0) ? (`${this.topics.length} topics available`) : 'No topics found';
      } catch (e) {
        this.topics = [];
        this.apiError = e?.message ? String(e.message) : 'Failed to load topics.';
      }
    },

    // ---------- fallback selector flow ----------
    async loadFallbackModules() {
      this.fallbackModules = [];
      this.fallbackSubjects = [];
      this.fallbackTopics = [];
      this.fallbackModuleId = '';
      this.fallbackSubjectId = '';
      this.fallbackTopicId = '';
      if (!this.fallbackLevelId) return;

      try {
        const data = await this.fetchJson('/public/index.php?r=api_modules&level_id=' + encodeURIComponent(this.fallbackLevelId));
        this.fallbackModules = (data || []).map(x => this.normModule(x));
      } catch {
        this.fallbackModules = [];
      }
    },

    async loadFallbackSubjects() {
      this.fallbackSubjects = [];
      this.fallbackTopics = [];
      this.fallbackSubjectId = '';
      this.fallbackTopicId = '';
      if (!this.fallbackModuleId) return;

      try {
        const data = await this.fetchJson('/public/index.php?r=api_subjects&module_id=' + encodeURIComponent(this.fallbackModuleId));
        this.fallbackSubjects = (data || []).map(x => this.normSubject(x));
      } catch {
        this.fallbackSubjects = [];
      }
    },

    async loadFallbackTopics() {
      this.fallbackTopics = [];
      this.fallbackTopicId = '';
      if (!this.fallbackSubjectId) return;

      try {
        const data = await this.fetchJson('/public/index.php?r=api_topics&subject_id=' + encodeURIComponent(this.fallbackSubjectId));
        this.fallbackTopics = (data || []).map(x => this.normTopic(x));
      } catch {
        this.fallbackTopics = [];
      }
    },
  }
}
</script>
