<?php
require_auth($config);
$title = 'Start a test';
$base = base_url($config);
$pdo = db_connect($config['db']);

$subjects = $pdo->query('SELECT DISTINCT subject FROM questions ORDER BY subject')->fetchAll(PDO::FETCH_COLUMN);

$selected_subject = trim((string)($_GET['subject'] ?? ''));
$topics = [];
if ($selected_subject !== '') {
    $stmt = $pdo->prepare('SELECT DISTINCT topic FROM questions WHERE subject = :s AND topic IS NOT NULL AND topic <> "" ORDER BY topic');
    $stmt->execute([':s' => $selected_subject]);
    $topics = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $subject = trim((string)($_POST['subject'] ?? ''));
    $topic = trim((string)($_POST['topic'] ?? ''));
    $count = (int)($_POST['count'] ?? 20);

    if ($count < 5 || $count > 100) $errors[] = 'Choose between 5 and 100 questions.';

    $where = '1=1';
    $params = [];
    if ($subject !== '' && $subject !== 'ALL') {
        $where .= ' AND subject = :subject';
        $params[':subject'] = $subject;
    }
    if ($topic !== '' && $topic !== 'ALL') {
        $where .= ' AND topic = :topic';
        $params[':topic'] = $topic;
    }

    $stmt = $pdo->prepare("SELECT id FROM questions WHERE {$where}");
    $stmt->execute($params);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ids) === 0) {
        $errors[] = 'No questions found for the selected filters.';
    } else {
        shuffle($ids);
        $picked = array_slice($ids, 0, min($count, count($ids)));

        // Create attempt
        $user = current_user();
        $stmt = $pdo->prepare('INSERT INTO attempts (user_id, subject_filter, topic_filter, total_questions) VALUES (:uid, :sf, :tf, :tq)');
        $stmt->execute([
            ':uid' => (int)$user['id'],
            ':sf' => ($subject === 'ALL' || $subject === '') ? null : $subject,
            ':tf' => ($topic === 'ALL' || $topic === '') ? null : $topic,
            ':tq' => count($picked),
        ]);
        $attempt_id = (int)$pdo->lastInsertId();

        $_SESSION['quiz'] = [
            'attempt_id' => $attempt_id,
            'question_ids' => $picked,
            'started_at' => time(),
        ];

        redirect($base . '/index.php?r=quiz_take');
    }
}

ob_start();
?>
  <h1>Start a practice test</h1>
  <p class="muted">Pick a subject and topic, choose the number of questions, then start.</p>

  <?php if (!$subjects): ?>
    <div class="card" style="border-left:4px solid #f59e0b;">
      <p><strong>No questions found.</strong></p>
      <p class="muted">Ask an Admin to import questions first.</p>
      <?php if (!empty(current_user()['is_admin'])): ?>
        <p><a class="btn" href="<?= e($base) ?>/index.php?r=admin_import">Import questions</a></p>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <?php if ($errors): ?>
      <div class="card" style="border-left:4px solid #ef4444;">
        <p><strong>Fix the following:</strong></p>
        <ul>
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card">
      <form method="post">
        <?= csrf_field() ?>

        <label>Subject
          <select class="input" name="subject" onchange="window.location='<?= e($base) ?>/index.php?r=quiz_start&subject='+encodeURIComponent(this.value)">
            <option value="ALL">All subjects</option>
            <?php foreach ($subjects as $s): ?>
              <option value="<?= e($s) ?>" <?= ($selected_subject === $s ? 'selected' : '') ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Topic
          <select class="input" name="topic">
            <option value="ALL">All topics</option>
            <?php foreach ($topics as $t): ?>
              <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label>Number of questions
          <input class="input" type="number" name="count" min="5" max="100" value="20" required>
        </label>

        <div style="margin-top:12px;">
          <button class="btn" type="submit">Start test</button>
          <a class="btn secondary" href="<?= e($base) ?>/index.php?r=home">Back</a>
        </div>
      </form>
    </div>

  <?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
