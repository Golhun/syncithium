<?php
declare(strict_types=1);

/**
 * Expected variables:
 * - $reports : array
 * - $status  : string
 */
$reports = (isset($reports) && is_array($reports)) ? $reports : [];
$status  = (string)($status ?? 'open');

function e2(string $v): string {
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$tabs = [
  'open'      => 'Open',
  'in_review' => 'In Review',
  'resolved'  => 'Resolved',
  'rejected'  => 'Rejected',
  'all'       => 'All',
];
?>
<div class="max-w-6xl mx-auto">
  <div class="flex items-start justify-between gap-4 mb-4">
    <div>
      <h1 class="text-xl font-semibold">Question Reports</h1>
      <p class="text-sm text-gray-500">
        Review reported questions, add admin notes, and update status.
      </p>
    </div>

    <a class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50"
       href="/public/index.php?r=admin_users">Back to Users</a>
  </div>

  <div class="flex flex-wrap gap-2 mb-4">
    <?php foreach ($tabs as $k => $label): ?>
      <a
        class="px-3 py-2 rounded-lg border border-gray-200 <?= ($status === $k) ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-50' ?>"
        href="/public/index.php?r=admin_question_reports&status=<?= e2($k) ?>"
      >
        <?= e2($label) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left p-3">Date</th>
          <th class="text-left p-3">Reporter</th>
          <th class="text-left p-3">Reason</th>
          <th class="text-left p-3">Question</th>
          <th class="text-left p-3">Details</th>
          <th class="text-left p-3">Status</th>
          <th class="text-left p-3">Admin Action</th>
        </tr>
      </thead>

      <tbody>
      <?php if (count($reports) === 0): ?>
        <tr class="border-t border-gray-200">
          <td class="p-3 text-gray-600" colspan="7">No reports found for this filter.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($reports as $r): ?>
          <?php
            $id = (int)($r['id'] ?? 0);
            $created = (string)($r['created_at'] ?? '');
            $email = (string)($r['email'] ?? $r['user_email'] ?? '');
            $reason = (string)($r['reason'] ?? $r['report_type'] ?? '');
            $qtext = (string)($r['question_text'] ?? '');
            $details = (string)($r['details'] ?? $r['message'] ?? '');
            $st = (string)($r['status'] ?? 'open');
            $adminNotes = (string)($r['admin_notes'] ?? '');
          ?>
          <tr class="border-t border-gray-200 align-top">
            <td class="p-3 whitespace-nowrap"><?= e2($created) ?></td>
            <td class="p-3"><?= e2($email) ?></td>
            <td class="p-3"><?= e2($reason) ?></td>
            <td class="p-3">
              <div class="max-w-md whitespace-pre-wrap"><?= e2($qtext) ?></div>
            </td>
            <td class="p-3">
              <div class="max-w-md whitespace-pre-wrap text-gray-700"><?= e2($details) ?></div>
            </td>
            <td class="p-3 whitespace-nowrap"><?= e2($st) ?></td>
            <td class="p-3">
              <form method="post" action="/public/index.php?r=admin_question_report_update" class="space-y-2">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">

                <select class="w-full px-3 py-2 rounded-lg border border-gray-200" name="status">
                  <?php foreach (['open','in_review','resolved','rejected'] as $opt): ?>
                    <option value="<?= e2($opt) ?>" <?= ($st === $opt) ? 'selected' : '' ?>>
                      <?= e2($tabs[$opt] ?? $opt) ?>
                    </option>
                  <?php endforeach; ?>
                </select>

                <textarea
                  class="w-full px-3 py-2 rounded-lg border border-gray-200"
                  rows="3"
                  name="admin_notes"
                  placeholder="Admin notes (optional)"
                ><?= e2($adminNotes) ?></textarea>

                <button class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50" type="submit">
                  Update
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
