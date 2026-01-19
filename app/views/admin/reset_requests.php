<?php
declare(strict_types=1);

/** @var array $requests */

?>
<div class="flex items-start justify-between gap-4 mb-6">
  <div>
    <h1 class="text-2xl font-semibold">Password Reset Requests</h1>
    <p class="text-sm text-slate-600 mt-1">
      Review and act on user-initiated password reset requests. Approving a request generates a temporary password.
    </p>
  </div>
</div>

<?php if (empty($requests)): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-6 text-center text-sm text-gray-500">
        There are no open reset requests.
    </div>
<?php else: ?>
    <div class="overflow-x-auto bg-white rounded-xl border border-gray-200">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-2 text-left font-semibold text-gray-700">Requested email</th>
                <th class="px-4 py-2 text-left font-semibold text-gray-700">Matched user</th>
                <th class="px-4 py-2 text-left font-semibold text-gray-700">Note</th>
                <th class="px-4 py-2 text-left font-semibold text-gray-700">IP</th>
                <th class="px-4 py-2 text-left font-semibold text-gray-700">Status</th>
                <th class="px-4 py-2 text-left font-semibold text-gray-700">Created</th>
                <th class="px-4 py-2 text-left font-semibold text-gray-700">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr class="border-b border-gray-100">
                    <td class="px-4 py-2">
                        <?= e((string)($r['email'] ?? '')) ?>
                    </td>
                    <td class="px-4 py-2">
                        <?= e((string)($r['user_email'] ?? '')) ?>
                    </td>
                    <td class="px-4 py-2 max-w-xs">
                        <span class="block truncate" title="<?= e((string)($r['note'] ?? '')) ?>">
                            <?= e((string)($r['note'] ?? '')) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500">
                        <?= e((string)($r['request_ip'] ?? '')) ?>
                    </td>
                    <td class="px-4 py-2">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs
                                     <?= $r['status'] === 'open' ? 'bg-yellow-50 text-yellow-700 border border-yellow-200' : 'bg-gray-50 text-gray-600 border border-gray-200' ?>">
                            <?= htmlspecialchars((string)$r['status']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2 text-xs text-gray-500">
                        <?= e((string)($r['created_at'] ?? '')) ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <form method="post" action="/public/index.php?r=admin_reset_requests_action">
                                <input type="hidden" name="csrf_token"
                                       value="<?= e((string)csrf_token()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" name="action" value="reject"
                                        class="px-3 py-2 rounded-xl border border-gray-200 text-xs hover:bg-gray-50">
                                    Reject
                                </button>
                            </form>

                            <form method="post" action="/public/index.php?r=admin_reset_requests_action">
                                <input type="hidden" name="csrf_token"
                                       value="<?= e((string)csrf_token()) ?>">
                                <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" name="action" value="approve_reset"
                                        class="px-3 py-2 rounded-xl bg-sky-600 text-white text-xs hover:bg-sky-700">
                                    Approve & Reset Password
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
