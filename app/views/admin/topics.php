<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Topics</h1>
    <p class="text-sm text-slate-600">Linked to subjects. These are what users will multi-select for quizzes.</p>
  </div>
  <div class="flex gap-2">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_levels">Levels</a>
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_modules">Modules</a>
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_subjects">Subjects</a>
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_taxonomy_import">CSV Import</a>
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_users">Users</a>
  </div>
</div>

<div class="grid md:grid-cols-2 gap-4">
  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="font-semibold mb-3"><?= $edit ? 'Edit topic' : 'Create topic' ?></h2>

    <form method="post" action="/public/index.php?r=admin_topics" class="space-y-4">
      <?= csrf_field() ?>
      <?php if ($edit): ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <?php else: ?>
        <input type="hidden" name="action" value="create">
      <?php endif; ?>

      <div>
        <label class="block text-sm font-medium mb-1">Subject</label>
        <select name="subject_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
          <option value="">Select subject</option>
          <?php foreach ($subjects as $s): ?>
            <option value="<?= (int)$s['id'] ?>" <?= ($edit && (int)$edit['subject_id'] === (int)$s['id']) ? 'selected' : '' ?>>
              <?= e($s['level_code']) ?> , <?= e($s['module_code']) ?> , <?= e($s['subject_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Topic name</label>
        <input name="name" required value="<?= e($edit['name'] ?? '') ?>"
          class="w-full rounded-lg border border-slate-300 px-3 py-2">
      </div>

      <div class="flex gap-2">
        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95">
          <?= $edit ? 'Save changes' : 'Create' ?>
        </button>
        <?php if ($edit): ?>
          <a class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_topics">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-slate-200 bg-slate-50">
      <div class="font-semibold">Existing topics</div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-white border-b border-slate-200">
          <tr>
            <th class="text-left p-3">Path</th>
            <th class="text-left p-3">Topic</th>
            <th class="text-right p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topics as $t): ?>
            <tr class="border-b border-slate-200 hover:bg-slate-50">
              <td class="p-3">
                <?= e($t['level_code']) ?> , <?= e($t['module_code']) ?> , <?= e($t['subject_name']) ?>
              </td>
              <td class="p-3"><?= e($t['name']) ?></td>
              <td class="p-3">
                <div class="flex justify-end gap-2">
                  <a class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-100"
                     href="/public/index.php?r=admin_topics&edit_id=<?= (int)$t['id'] ?>">Edit</a>

                  <form method="post" action="/public/index.php?r=admin_topics"
                        onsubmit="return confirm('Delete this topic? This may break question mappings in Phase 4.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                    <button class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-100">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
