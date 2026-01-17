<?php
require_auth($config);
$title = 'Results';
$base = base_url($config);
$pdo = db_connect($config['db']);
$user = current_user();

$attemptId = (int)($_GET['attempt_id'] ?? 0);
if ($attemptId <= 0) {
    redirect($base . '/index.php?r=my_attempts');
}

$stmt = $pdo->prepare('SELECT * FROM attempts WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $attemptId, ':uid' => (int)$user['id']]);
$attempt = $stmt->fetch();
if (!$attempt) {
    flash_set('error', 'Attempt not found.');
    redirect($base . '/index.php?r=my_attempts');
}

$qaStmt = $pdo->prepare(
    'SELECT aq.sort_order, q.id AS question_id, q.subject, q.topic, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option,
            aa.selected_option
     FROM attempt_questions aq
     JOIN questions q ON q.id = aq.question_id
     LEFT JOIN attempt_answers aa ON aa.attempt_id = aq.attempt_id AND aa.question_id = aq.question_id
     WHERE aq.attempt_id = :aid
     ORDER BY aq.sort_order ASC'
);
$qaStmt->execute([':aid' => $attemptId]);
$rows = $qaStmt->fetchAll();

$score = (int)($attempt['score'] ?? 0);
$total = (int)($attempt['total_questions'] ?? count($rows));
$percent = $total > 0 ? round(($score / $total) * 100, 1) : 0;

ob_start();
?>
  <h1>Results</h1>

  <div class="card">
    <p><strong>Score:</strong> <?= e((string)$score) ?> / <?= e((string)$total) ?> (<?= e((string)$percent) ?>%)</p>
    <p class="muted">Started: <?= e($attempt['started_at'] ?? '') ?>, Completed: <?= e($attempt['completed_at'] ?? 'Not completed') ?></p>
    <p><a class="btn" href="<?= e($base) ?>/index.php?r=quiz_start">Start another test</a>
       <a class="btn secondary" href="<?= e($base) ?>/index.php?r=my_attempts">My attempts</a></p>
  </div>

  <h2>Review</h2>
  <?php foreach ($rows as $i => $r):
      $selected = $r['selected_option'] ?? '';
      $correct = $r['correct_option'] ?? '';
      $isCorrect = ($selected !== '' && $selected === $correct);
      $statusClass = $isCorrect ? 'ok' : 'bad';
  ?>
    <div class="card">
      <p><strong>Q<?= e((string)($i+1)) ?>.</strong> <?= e($r['question_text'] ?? '') ?></p>
      <p class="muted"><?= e($r['subject'] ?? '') ?><?= ($r['topic'] ? ' , ' . e($r['topic']) : '') ?></p>
      <div class="grid2">
        <div>
          <div class="pill <?= e($statusClass) ?>">Your answer: <?= e($selected ?: 'No answer') ?></div>
          <p class="muted" style="margin-top:8px;">
            A. <?= e($r['option_a'] ?? '') ?><br>
            B. <?= e($r['option_b'] ?? '') ?><br>
            C. <?= e($r['option_c'] ?? '') ?><br>
            D. <?= e($r['option_d'] ?? '') ?>
          </p>
        </div>
        <div>
          <div class="pill" style="background:#1f2937;">Correct answer: <?= e($correct ?: '-') ?></div>
          <?php if (!$isCorrect): ?>
            <p class="muted" style="margin-top:8px;">Tip: review this topic, then attempt another randomized set to test retention.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
