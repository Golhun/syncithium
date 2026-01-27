<?php
/**
 * @var array $row
 */
?>
<style>[x-cloak] { display: none !important; }</style>

<div>

    <!-- Content Wrapper -->
    <div class="max-w-5xl mx-auto" id="qe-content">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2">
                <div class="h-9 w-9 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center">
                    <?= icon('pencil-square', 'h-5 w-5 text-sky-700', 'solid') ?>
                </div>
                <h1 class="text-2xl font-semibold text-slate-900">Edit Question</h1>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-2 text-sm text-slate-600">
                <span class="font-medium text-slate-900"><?= e($row['level_code']) ?></span>
                <span class="text-slate-300">&bull;</span>
                <span class="font-medium text-slate-900"><?= e($row['module_code']) ?></span>
                <span class="text-slate-300">&bull;</span>
                <span><?= e($row['subject_name']) ?></span>
                <span class="text-slate-300">&bull;</span>
                <span class="text-sky-700"><?= e($row['topic_name']) ?></span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/public/index.php?r=admin_questions" 
               class="inline-flex items-center gap-2 px-3 py-2 rounded-xl ring-1 ring-slate-200 bg-white hover:bg-slate-50 transition text-sm font-medium text-slate-700">
                <?= icon('arrow-left', 'h-4 w-4', 'outline') ?>
                Back
            </a>
        </div>
    </div>

    <!-- Main Form -->
    <form
          class="bg-white ring-1 ring-slate-200 rounded-2xl shadow-sm overflow-hidden" 
          method="post"
          action="/public/index.php?r=admin_question_edit&id=<?= (int)$row['id'] ?>">
        <?= csrf_field() ?>

        <div class="p-6 space-y-6">
            <!-- Question Text -->
            <div>
                <label class="block text-sm font-semibold text-slate-900 mb-2">Question Text</label>
                <textarea class="w-full px-4 py-3 rounded-xl ring-1 ring-slate-200 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition text-slate-800 leading-relaxed" 
                          rows="3" name="question_text" required placeholder="Enter the question here..."><?= e((string)$row['question_text']) ?></textarea>
            </div>

            <!-- Options Grid -->
            <div class="grid md:grid-cols-2 gap-5">
                <?php foreach(['a','b','c','d'] as $opt): ?>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Option <?= strtoupper($opt) ?></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <span class="text-slate-400 font-mono font-bold"><?= strtoupper($opt) ?></span>
                        </div>
                        <textarea class="w-full pl-8 pr-3 py-2 rounded-xl ring-1 ring-slate-200 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition text-sm" 
                                  rows="2" name="option_<?= $opt ?>" required><?= e((string)$row['option_' . $opt]) ?></textarea>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <hr class="border-slate-100">

            <!-- Settings -->
            <div class="grid md:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Correct Option</label>
                    <select class="w-full px-3 py-2.5 rounded-xl ring-1 ring-slate-200 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 bg-white text-sm font-medium" name="correct_option">
                        <?php foreach (['A','B','C','D'] as $o): ?>
                            <option value="<?= $o ?>" <?= ((string)$row['correct_option'] === $o) ? 'selected' : '' ?>>Option <?= $o ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                    <select class="w-full px-3 py-2.5 rounded-xl ring-1 ring-slate-200 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 bg-white text-sm" name="status">
                        <option value="active" <?= ((string)$row['status'] === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ((string)$row['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end pb-2">
                    <div class="text-xs text-slate-500">
                        Last updated: <span class="font-medium text-slate-700"><?= e((string)$row['updated_at']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Explanation -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Explanation <span class="text-slate-400 font-normal">(Optional)</span></label>
                <textarea class="w-full px-4 py-2 rounded-xl ring-1 ring-slate-200 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition text-sm text-slate-600" 
                          rows="2" name="explanation" placeholder="Explain why the answer is correct..."><?= e((string)($row['explanation'] ?? '')) ?></textarea>
            </div>
        </div>

        <!-- Footer Actions -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-end">
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-slate-900 text-white hover:bg-slate-800 hover:shadow-md transition font-semibold text-sm">
                <?= icon('check', 'h-4 w-4', 'solid') ?>
                Save Changes
            </button>
        </div>
    </form>
    </div>

</div>
