<?php
require_auth($config);
$title = 'My attempts';
$base = base_url($config);
$pdo = db_connect($config['db']);
$user = current_user();

$stmt = $pdo->prepare('SELECT * FROM attempts WHERE user_id = :uid ORDER BY id DESC LIMIT 50');
$stmt->execute([':uid' => (int)$user['id']]);
$attempts = $stmt->fetchAll();

ob_start();
?>
  <h1>My attempts</h1>
  <p class="muted">Latest 50 attempts. Click to review details.</p>

  <?php if (empty($attempts)): ?>
    <div class="card">
      <p>No attempts yet.</p>
      <p><a class="btn" href="<?= e($base) ?>/index.php?r=quiz_start">Start your first test</a></p>
    </div>
  <?php else: ?>
    <div class="card">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Created</th>
            <th>Questions</th>
            <th>Score</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attempts as $a):
            $score = (int)($a['score'] ?? 0);
            $total = (int)($a['total_questions'] ?? 0);
            $percent = ($total > 0) ? round(($score / $total) * 100, 1) : 0;
            $status = $a['status'] ?? 'in_progress';
          ?>
            <tr>
              <td><a href="<?= e($base) ?>/index.php?r=results&attempt_id=<?= e((string)$a['id']) ?>"><?= e((string)$a['id']) ?></a></td>
              <td><?= e($a['created_at'] ?? '') ?></td>
              <td><?= e((string)$total) ?></td>
              <td><?= e((string)$score) ?>/<?= e((string)$total) ?> (<?= e((string)$percent) ?>%)</td>
              <td><?= e($status) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
