<?php
/**
 * @var array $questions
 * @var array $filters
 * @var array $admin
 */

// Helper to prevent crashes if global icon() isn't loaded
function has_icon_q(): bool { return function_exists('icon'); }

$questionsUi = array_map(function($q) {
    return [
        'id' => (int)$q['id'],
        'q' => (string)$q['question_text'],
        'co' => (string)$q['correct_option'],
        'st' => (string)$q['status'],
        'top' => (string)$q['topic_name'],
        'sub' => (string)$q['subject_name'],
        'mod' => (string)$q['module_code'],
        'lev' => (string)$q['level_code'],
    ];
}, $questions ?? []);

$json = json_encode($questionsUi, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<style>[x-cloak] { display: none !important; }</style>

<?php require_once(__DIR__ . '/_alertify.php'); ?>

<div x-data="questionsAdmin()" x-init="init()">
    <script type="application/json" id="questionsPayload"><?= $json ?: '[]' ?></script>

    <!-- Main Content Wrapper (Animated) -->
    <div class="max-w-6xl mx-auto" id="q-content">
    
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div class="min-w-0">
            <div class="flex items-center gap-2">
                <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
                    <?php if (has_icon_q()): ?>
                        <?= icon('question-mark-circle', 'h-5 w-5 text-sky-700', 'solid') ?>
                    <?php else: ?>
                        <span class="text-sky-700 font-semibold">Q</span>
                    <?php endif; ?>
                </div>
                <h1 class="text-2xl font-semibold text-slate-900">Question Bank</h1>
            </div>
            <p class="text-sm text-slate-600 mt-2">Manage and organize your quiz questions.</p>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-slate-200 bg-white">
                    <?php if (has_icon_q()): ?><?= icon('circle-stack', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
                    Total: <span class="font-semibold text-slate-700" x-text="all.length"></span>
                </span>
                
                <!-- Selection counter -->
                <span x-show="selected.length > 0" x-transition class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full ring-1 ring-sky-200 bg-sky-50 text-sky-700">
                    <span class="font-semibold" x-text="selected.length"></span> selected
                </span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 sm:justify-end">
             <!-- Bulk Delete (Visible when selected) -->
            <div x-show="selected.length > 0" x-transition>
                <form method="post" action="/public/index.php?r=admin_questions" @submit.prevent="initiateDelete($event.target, selected)">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_delete">
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id">
                    </template>
                    <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition">
                        <?php if (has_icon_q()): ?><?= icon('trash', 'h-4 w-4', 'outline') ?><?php endif; ?>
                        <span class="text-sm font-medium">Delete Selected</span>
                    </button>
                </form>
            </div>

            <a href="/public/index.php?r=admin_questions_import" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition">
                <?php if (has_icon_q()): ?><?= icon('arrow-up-tray', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
                <span class="text-sm font-medium text-slate-800">Import</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
        
        <!-- Toolbar -->
        <div class="px-5 py-4 border-b border-slate-200 bg-white">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="inline-flex items-center gap-2 text-sm font-semibold text-slate-800">
                    <?php if (has_icon_q()): ?><?= icon('list-bullet', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
                    Questions
                </div>

                <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                    <!-- Search -->
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <?php if (has_icon_q()): ?><?= icon('magnifying-glass', 'h-4 w-4 text-slate-400', 'outline') ?><?php endif; ?>
                        </div>
                        <input type="text" x-model.debounce.250ms="search"
                               class="w-full sm:w-72 rounded-xl ring-1 ring-slate-200 bg-white pl-9 pr-3 py-2 text-sm
                                      focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
                               placeholder="Search question, topic...">
                    </div>

                    <!-- Status Filter -->
                    <select x-model="statusFilter" class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>

                    <!-- Page size -->
                    <div class="flex items-center gap-2">
                        <select x-model.number="pageSize"
                                class="rounded-xl ring-1 ring-slate-200 bg-white px-3 py-2 text-sm
                                       focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition">
                            <option value="10">10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-3 text-xs text-slate-500 flex items-center justify-between gap-3">
                <span>
                    Showing <span class="font-semibold text-slate-700" x-text="showFrom"></span>
                    to <span class="font-semibold text-slate-700" x-text="showTo"></span>
                    of <span class="font-semibold text-slate-700" x-text="filteredCount"></span>
                </span>

                <button type="button" @click="search=''; statusFilter=''; page=1;"
                        class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 hover:text-slate-900 transition" 
                        x-show="search.length > 0 || statusFilter.length > 0" x-cloak>
                    <?php if (has_icon_q()): ?><?= icon('x-circle', 'h-4 w-4', 'outline') ?><?php endif; ?>
                    Clear filters
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" x-model="allSelected" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                        </th>
                        <th class="p-3 w-1/2">Question</th>
                        <th class="p-3">Taxonomy</th>
                        <th class="p-3">Status</th>
                        <th class="p-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-if="paged.length === 0">
                        <tr>
                            <td class="p-12 text-center text-slate-500" colspan="5">
                                No questions found.
                            </td>
                        </tr>
                    </template>
                    
                    <template x-for="row in paged" :key="row.id">
                        <tr class="hover:bg-slate-50/50 transition group">
                            <td class="p-3 align-top">
                                <input type="checkbox" :value="row.id" x-model="selected" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 mt-1">
                            </td>
                            <td class="p-3 align-top">
                                <div class="font-medium text-slate-900 line-clamp-2 mb-1" x-text="row.q" :title="row.q"></div>
                                <div class="text-xs text-slate-500">
                                    Correct: <span class="font-mono font-bold text-slate-700 bg-slate-100 px-1 rounded" x-text="row.co"></span>
                                </div>
                            </td>
                            <td class="p-3 align-top">
                                <div class="flex flex-col gap-0.5 text-xs text-slate-600">
                                    <span class="font-medium text-slate-900" x-text="row.top"></span>
                                    <span x-text="row.sub"></span>
                                    <span class="text-slate-400"><span x-text="row.mod"></span> &bull; <span x-text="row.lev"></span></span>
                                </div>
                            </td>
                            <td class="p-3 align-top">
                                <form method="post" action="/public/index.php?r=admin_questions">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="question_id" :value="row.id">
                                    <button type="submit" 
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-sky-500"
                                            :class="row.st === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-200 hover:bg-slate-200'">
                                        <span x-text="row.st.charAt(0).toUpperCase() + row.st.slice(1)"></span>
                                    </button>
                                </form>
                            </td>
                            <td class="p-3 align-top text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <a :href="'/public/index.php?r=admin_question_edit&id=' + row.id" 
                                       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition" title="Edit">
                                        <?php if (has_icon_q()): ?><?= icon('pencil-square', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
                                        <span class="text-sm font-medium text-slate-800 hidden sm:inline">Edit</span>
                                    </a>
                                    <form method="post" action="/public/index.php?r=admin_question_delete" 
                                          @submit.prevent="initiateDelete($event.target, [row.id])">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" :value="row.id">
                                        <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-rose-50 hover:ring-rose-200 transition" title="Delete">
                                            <?php if (has_icon_q()): ?><?= icon('trash', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
                                            <span class="text-sm font-medium text-slate-800 hidden sm:inline">Delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="px-5 py-4 border-t border-slate-200 bg-white flex items-center justify-between gap-3" x-show="filteredCount > 0">
            <button type="button" @click="prev()" :disabled="page <= 1"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
                <?php if (has_icon_q()): ?><?= icon('chevron-left', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
                Prev
            </button>

            <div class="text-xs text-slate-500">
                Showing <span class="font-semibold text-slate-700" x-text="showFrom"></span> to <span class="font-semibold text-slate-700" x-text="showTo"></span> of <span class="font-semibold text-slate-700" x-text="filteredCount"></span>
            </div>

            <button type="button" @click="next()" :disabled="page >= totalPages"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
                Next
                <?php if (has_icon_q()): ?><?= icon('chevron-right', 'h-4 w-4 text-slate-600', 'outline') ?><?php endif; ?>
            </button>
        </div>
    </div>
    
    </div> <!-- End Content Wrapper -->

</div>

<script>
function questionsAdmin() {
    return {
        all: [],
        search: '',
        statusFilter: '',
        page: 1,
        pageSize: 20,
        selected: [],
        
        // Computed
        filtered: [],
        paged: [],
        filteredCount: 0,
        totalPages: 1,
        showFrom: 0,
        showTo: 0,

        init() {
            try {
                this.all = JSON.parse(document.getElementById('questionsPayload').textContent);
            } catch(e) { this.all = []; }
            
            this.$watch('search', () => { this.page = 1; this.compute(); });
            this.$watch('statusFilter', () => { this.page = 1; this.compute(); });
            this.$watch('pageSize', () => { this.page = 1; this.compute(); });
            this.$watch('page', () => this.compute());
            
            this.compute();
            
            const content = document.getElementById('q-content');
            if (content) content.classList.add('animate-[fadeInUp_.18s_ease-out_1]');
        },

        compute() {
            const q = this.search.toLowerCase().trim();
            const s = this.statusFilter;
            
            this.filtered = this.all.filter(item => {
                if (s && item.st !== s) return false;
                if (q) {
                    return item.q.toLowerCase().includes(q) || 
                           item.top.toLowerCase().includes(q) || 
                           item.sub.toLowerCase().includes(q);
                }
                return true;
            });

            this.filteredCount = this.filtered.length;
            this.totalPages = Math.ceil(this.filteredCount / this.pageSize) || 1;
            
            if (this.page > this.totalPages) this.page = this.totalPages;
            if (this.page < 1) this.page = 1;

            const start = (this.page - 1) * this.pageSize;
            this.paged = this.filtered.slice(start, start + this.pageSize);
            
            this.showFrom = this.filteredCount === 0 ? 0 : start + 1;
            this.showTo = Math.min(start + this.pageSize, this.filteredCount);
        },

        prev() { if (this.page > 1) this.page--; },
        next() { if (this.page < this.totalPages) this.page++; },
        
        get allSelected() {
            return this.paged.length > 0 && this.paged.every(row => this.selected.includes(row.id));
        },
        set allSelected(value) {
            if (value) {
                const newIds = this.paged.map(row => row.id);
                this.selected = [...new Set([...this.selected, ...newIds])];
            } else {
                const pagedIds = this.paged.map(row => row.id);
                this.selected = this.selected.filter(id => !pagedIds.includes(id));
            }
        },

        initiateDelete(formEl, ids) {
            const msg = ids.length > 1 
                ? `Are you sure you want to delete ${ids.length} questions?` 
                : 'Are you sure you want to delete this question?';

            if (typeof alertify !== 'undefined') {
                alertify.confirm('Delete Questions', msg, () => { HTMLFormElement.prototype.submit.call(formEl); }, () => {}).set('labels', {ok:'Delete', cancel:'Cancel'});
            } else if (confirm(msg)) {
                HTMLFormElement.prototype.submit.call(formEl);
            }
        },
    }
}
</script>