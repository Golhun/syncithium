<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Bulk upload users</h1>
    <p class="text-sm text-slate-600">Upload CSV or paste emails. Temp passwords are displayed only once.</p>
  </div>
  <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100"
     href="/public/index.php?r=admin_users">Back</a>
</div>

<div class="grid md:grid-cols-2 gap-4">

  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="font-semibold mb-3">Paste emails</h2>
    <form method="post" action="/public/index.php?r=admin_users_bulk" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="textarea">

      <div>
        <label class="block text-sm font-medium mb-1">Role for imported users</label>
        <select name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2">
          <option value="user">user</option>
          <option value="admin">admin</option>
        </select>
      </div>

      <textarea name="emails" rows="10" placeholder="one email per line, or comma-separated"
        class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>

      <button class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95">
        Process
      </button>
    </form>
  </div>

  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <h2 class="font-semibold mb-3">Upload CSV</h2>
    <form method="post" enctype="multipart/form-data" action="/public/index.php?r=admin_users_bulk" class="space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="mode" value="csv">

      <div>
        <label class="block text-sm font-medium mb-1">Role for imported users</label>
        <select name="role" class="w-full rounded-lg border border-slate-300 px-3 py-2">
          <option value="user">user</option>
          <option value="admin">admin</option>
        </select>
      </div>

      <input type="file" name="csv" accept=".csv,text/csv"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 bg-white">

      <p class="text-xs text-slate-600">CSV can be a single column of emails, or any mix. We extract anything that looks like an email.</p>

      <button class="rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95">
        Upload and process
      </button>
    </form>
  </div>

</div>

<?php if (!empty($results)): ?>
  <div class="bg-white border border-slate-200 rounded-xl overflow-hidden mt-6">
    <div class="p-4 border-b border-slate-200 bg-slate-50">
      <h3 class="font-semibold">Results</h3>
      <p class="text-xs text-slate-600">Copy temp passwords now. They will not be shown again.</p>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-white border-b border-slate-200">
          <tr>
            <th class="text-left p-3">Email</th>
            <th class="text-left p-3">Status</th>
            <th class="text-left p-3">Temp password</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($results as $r): ?>
            <tr class="border-b border-slate-200 hover:bg-slate-50">
              <td class="p-3"><?= e($r['email']) ?></td>
              <td class="p-3"><?= e($r['status']) ?></td>
              <td class="p-3">
                <?php if (!empty($r['temp'])): ?>
                  <code class="bg-slate-50 border border-slate-200 px-2 py-1 rounded"><?= e($r['temp']) ?></code>
                <?php else: ?>
                  <span class="text-slate-400">-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
