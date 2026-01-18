<?php declare(strict_types=1); ?>
<div class="max-w-6xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">My Review Requests</h1>
    <a class="px-3 py-2 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-sm"
       href="/public/index.php?r=taxonomy_selector">Back to Topics</a>
  </div>

  <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-xs text-gray-600">
        <tr>
          <th class="text-left px-4 py-3">Date</th>
          <th class="text-left px-4 py-3">Status</th>
          <th class="text-left px-4 py-3">Question</th>
          <th class="text-left px-4 py-3">Your message</th>
          <th class="text-left px-4 py-3">Admin notes</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($reports)): ?>
        <tr><td class="px-4 py-4 text-gray-500" colspan="5">No reports yet.</td></tr>
      <?php else: ?>
        <?php foreach ($reports as $r): ?>
          <tr class="border-t border-gray-200 align-top">
            <td class="px-4 py-3 text-xs text-gray-500"><?= htmlspecialchars((string)$r['created_at']) ?></td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 rounded-full text-xs border border-gray-200">
                <?= htmlspecialchars((string)$r['status']) ?>
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-gray-700"><?= htmlspecialchars(mb_strimwidth((string)($r['question_text'] ?? ''), 0, 80, '...')) ?></td>
            <td class="px-4 py-3 text-xs text-gray-700"><?= nl2br(htmlspecialchars((string)$r['message'])) ?></td>
            <td class="px-4 py-3 text-xs text-gray-700"><?= nl2br(htmlspecialchars((string)($r['admin_notes'] ?? ''))) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
