<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Modules</h1>
    <p class="text-sm text-slate-600">Linked to levels. Example: GEM 201 under level 200.</p>
  </div>
  <div class="flex gap-2">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_levels">Levels</a>
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_subjects">Subjects</a>
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_topics">Topics</a>
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_taxonomy_import">CSV Import</a>
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_users">Users</a>
  </div>
</div>

<div class="grid md:grid-cols-2 gap-4">
  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="font-semibold mb-3"><?= $edit ? 'Edit module' : 'Create module' ?></h2>

    <form method="post" action="/public/index.php?r=admin_modules" class="space-y-4">
      <?= csrf_field() ?>
      <?php if ($edit): ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
      <?php else: ?>
        <input type="hidden" name="action" value="create">
      <?php endif; ?>

      <div>
        <label class="block text-sm font-medium mb-1">Level</label>
        <select name="level_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2">
          <option value="">Select level</option>
          <?php foreach ($levels as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= ($edit && (int)$edit['level_id'] === (int)$l['id']) ? 'selected' : '' ?>>
              <?= e($l['code']) ?><?= $l['name'] ? (' , ' . e($l['name'])) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Module code</label>
        <input name="code" required value="<?= e($edit['code'] ?? '') ?>"
          class="w-full rounded-lg border border-slate-300 px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Name (optional)</label>
        <input name="name" value="<?= e($edit['name'] ?? '') ?>"
          class="w-full rounded-lg border border-slate-300 px-3 py-2">
      </div>

      <div class="flex gap-2">
        <button class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95">
          <?= $edit ? 'Save changes' : 'Create' ?>
        </button>
        <?php if ($edit): ?>
          <a class="rounded-lg border border-slate-300 px-4 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_modules">Cancel</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
    <div class="p-4 border-b border-slate-200 bg-slate-50">
      <div class="font-semibold">Existing modules</div>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-white border-b border-slate-200">
          <tr>
            <th class="text-left p-3">Level</th>
            <th class="text-left p-3">Code</th>
            <th class="text-left p-3">Name</th>
            <th class="text-right p-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($modules as $m): ?>
            <tr class="border-b border-slate-200 hover:bg-slate-50">
              <td class="p-3"><?= e($m['level_code']) ?></td>
              <td class="p-3"><?= e($m['code']) ?></td>
              <td class="p-3"><?= e((string)($m['name'] ?? '')) ?></td>
              <td class="p-3">
                <div class="flex justify-end gap-2">
                  <a class="rounded-lg border border-slate-300 px-3 py-1.5 hover:bg-slate-100"
                     href="/public/index.php?r=admin_modules&edit_id=<?= (int)$m['id'] ?>">Edit</a>

                  <form method="post" action="/public/index.php?r=admin_modules"
                        onsubmit="return confirm('Delete this module? Only allowed if it has no subjects.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
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
