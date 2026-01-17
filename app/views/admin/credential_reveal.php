<div class="flex items-center justify-between mb-6">
  <div>
    <h1 class="text-2xl font-semibold">One-time credentials</h1>
    <p class="text-sm text-slate-600">Copy these now. Refreshing this page will clear them.</p>
  </div>
  <div class="flex gap-2">
    <a class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100" href="/public/index.php?r=admin_users">Back to users</a>
  </div>
</div>

<?php if (!$reveal): ?>
  <div class="bg-white border border-slate-200 rounded-xl p-6">
    <div class="text-slate-700">No credentials to display.</div>
    <div class="text-sm text-slate-500 mt-2">They may have expired or already been viewed.</div>
  </div>
<?php else: ?>
  <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-4">
    <div class="text-sm text-slate-600">
      <div class="font-semibold text-slate-900 mb-1">Details</div>
      <div><?= e($reveal['label'] ?? 'Credentials') ?></div>
    </div>

    <?php
      $items = $reveal['items'] ?? [];
      $single = $reveal['secret'] ?? null;
      $singleLabel = $reveal['secret_label'] ?? 'Secret';
    ?>

    <?php if ($single !== null): ?>
      <div class="border border-slate-200 rounded-lg p-4">
        <div class="text-sm font-medium mb-2"><?= e($singleLabel) ?></div>
        <div class="flex gap-2 items-center">
          <input readonly id="reveal_single" value="<?= e($single) ?>"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
          <button type="button" class="rounded-lg bg-slate-900 text-white px-3 py-2 hover:opacity-95"
            onclick="copyText('reveal_single')">Copy</button>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($items)): ?>
      <div class="overflow-x-auto border border-slate-200 rounded-lg">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr>
              <th class="text-left p-3">User</th>
              <th class="text-left p-3">Secret</th>
              <th class="text-right p-3">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($items as $i => $row): ?>
              <?php $inputId = "sec_" . $i; ?>
              <tr class="border-b border-slate-200 hover:bg-slate-50">
                <td class="p-3"><?= e($row['email'] ?? 'N/A') ?></td>
                <td class="p-3">
                  <input readonly id="<?= e($inputId) ?>" value="<?= e($row['secret'] ?? '') ?>"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm">
                </td>
                <td class="p-3 text-right">
                  <button type="button" class="rounded-lg bg-slate-900 text-white px-3 py-2 hover:opacity-95"
                    onclick="copyText('<?= e($inputId) ?>')">Copy</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <form method="post" action="/public/index.php?r=admin_credential_reveal_download">
        <?= csrf_field() ?>
        <button class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-100">Download CSV</button>
      </form>
    <?php endif; ?>
  </div>

  <script>
    function copyText(id) {
      const el = document.getElementById(id);
      if (!el) return;
      el.select();
      el.setSelectionRange(0, 99999);
      navigator.clipboard?.writeText(el.value);
      if (window.alertify) alertify.success("Copied");
    }
  </script>
<?php endif; ?>
