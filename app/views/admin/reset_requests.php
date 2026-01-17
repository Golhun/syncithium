<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Password reset requests</h1>
    <p class="text-sm text-slate-600">Users must request first, admin generates token from here.</p>
  </div>
  <div class="flex gap-2">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_users">Users</a>
  </div>
</div>

<div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
  <div class="p-4 border-b border-slate-200 bg-slate-50">
    <div class="font-semibold">Open requests</div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-white border-b border-slate-200">
        <tr>
          <th class="text-left p-3">Requested at</th>
          <th class="text-left p-3">Email</th>
          <th class="text-left p-3">Note</th>
          <th class="text-right p-3">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($requests as $r): ?>
          <tr class="border-b border-slate-200 hover:bg-slate-50">
            <td class="p-3"><?= e($r['created_at']) ?></td>
            <td class="p-3"><?= e($r['email']) ?></td>
            <td class="p-3"><?= e((string)($r['note'] ?? '')) ?></td>
            <td class="p-3">
              <div class="flex justify-end gap-2">
                <form method="post" action="/public/index.php?r=admin_reset_requests_action">
                  <?= csrf_field() ?>
                  <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="generate_token">
                  <button class="rounded-lg bg-slate-900 text-white px-3 py-2 hover:opacity-95">Generate token</button>
                </form>

                <form method="post" action="/public/index.php?r=admin_reset_requests_action"
                      onsubmit="return confirm('Reject this request?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100">Reject</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($requests)): ?>
          <tr>
            <td colspan="4" class="p-6 text-slate-600">No open requests.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
