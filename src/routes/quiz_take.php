<?php
require_auth($config);
$title = 'Take test';
$base = base_url($config);
$pdo = db_connect($config['db']);
$user = current_user();

$errors = [];

function build_question_query(array $filters): array {
    $where = [];
    $params = [];

    if (!empty($filters['subject'])) {
        $where[] = 'subject = :subject';
        $params[':subject'] = $filters['subject'];
    }
    if (!empty($filters['topic'])) {
        $where[] = 'topic = :topic';
        $params[':topic'] = $filters['topic'];
    }

    $sql = 'SELECT id FROM questions';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY RAND() LIMIT :limit';

    return [$sql, $params];
}

// Step 1: handle creation when quiz_start posts here
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $subject = trim((string)($_POST['subject'] ?? ''));
    $topic = trim((string)($_POST['topic'] ?? ''));
    $limit = (int)($_POST['limit'] ?? 10);
    $minutes = (int)($_POST['minutes'] ?? 0);

    if ($limit < 5) $limit = 5;
    if ($limit > 100) $limit = 100;
    if ($minutes < 0) $minutes = 0;
    if ($minutes > 240) $minutes = 240;

    // fetch random question IDs
    [$sql, $params] = build_question_query([
        'subject' => $subject,
        'topic' => $topic,
    ]);

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $qids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($qids) < 1) {
        flash_set('error', 'No questions found for the selected subject/topic. Ask your Admin to import more questions.');
        redirect($base . '/index.php?r=quiz_start');
    }

    // Create attempt
    $pdo->beginTransaction();
    try {
        $endsAt = null;
        if ($minutes > 0) {
            $endsAt = (new DateTimeImmutable())->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s');
        }

        $stmt = $pdo->prepare('INSERT INTO attempts (user_id, subject_filter, topic_filter, total_questions, time_limit_minutes, ends_at) VALUES (:uid, :subj, :topic, :total, :minutes, :ends)');
        $stmt->execute([
            ':uid' => (int)$user['id'],
            ':subj' => $subject !== '' ? $subject : null,
            ':topic' => $topic !== '' ? $topic : null,
            ':total' => (int)count($qids),
            ':minutes' => $minutes > 0 ? $minutes : null,
            ':ends' => $endsAt,
        ]);

        $attemptId = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO attempt_questions (attempt_id, question_id, sort_order) VALUES (:aid, :qid, :ord)');
        $order = 1;
        foreach ($qids as $qid) {
            $ins->execute([':aid' => $attemptId, ':qid' => (int)$qid, ':ord' => $order]);
            $order++;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    redirect($base . '/index.php?r=quiz_take&attempt_id=' . $attemptId);
}

// Step 2: render attempt
$attemptId = (int)($_GET['attempt_id'] ?? 0);
if ($attemptId <= 0) {
    redirect($base . '/index.php?r=quiz_start');
}

$stmt = $pdo->prepare('SELECT * FROM attempts WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $attemptId, ':uid' => (int)$user['id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    http_response_code(404);
    $title = 'Not found';
    ob_start();
    echo '<h1>Attempt not found</h1>';
    $content = ob_get_clean();
    require __DIR__ . '/../views/layout.php';
    exit;
}

if (!empty($attempt['completed_at'])) {
    redirect($base . '/index.php?r=results&attempt_id=' . $attemptId);
}

// Time limit check (soft enforcement)
$timeWarning = null;
if (!empty($attempt['ends_at'])) {
    $now = new DateTimeImmutable();
    $ends = new DateTimeImmutable($attempt['ends_at']);
    if ($now > $ends) {
        $timeWarning = 'Time limit has passed. Submit now so the system can mark what you have done.';
    } else {
        $minsLeft = (int)ceil(($ends->getTimestamp() - $now->getTimestamp()) / 60);
        $timeWarning = 'Time left: ' . $minsLeft . ' minute(s).';
    }
}

$q = $pdo->prepare('SELECT q.* FROM attempt_questions aq JOIN questions q ON q.id = aq.question_id WHERE aq.attempt_id = :aid ORDER BY aq.sort_order ASC');
$q->execute([':aid' => $attemptId]);
$questions = $q->fetchAll();

ob_start();
?>
  <h1>Test in progress</h1>
  <p class="muted">Answer all you can. Submit when done.</p>

  <?php if ($timeWarning): ?>
    <div class="flash flash-info"><?= e($timeWarning) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e($base) ?>/index.php?r=quiz_submit">
    <?= csrf_field() ?>
    <input type="hidden" name="attempt_id" value="<?= (int)$attemptId ?>">

    <?php foreach ($questions as $idx => $qq): ?>
      <div class="card" style="margin-bottom:12px;">
        <div class="muted" style="margin-bottom:6px;">Q<?= $idx + 1 ?>. <?= e($qq['subject']) ?><?= $qq['topic'] ? ' , ' . e($qq['topic']) : '' ?></div>
        <div style="font-weight:700;margin-bottom:10px;">
          <?= e($qq['question_text']) ?>
        </div>

        <?php
          $qid = (int)$qq['id'];
          $name = 'answer[' . $qid . ']';
        ?>
        <label style="display:block;margin:6px 0;"><input type="radio" name="<?= e($name) ?>" value="A"> A. <?= e($qq['option_a']) ?></label>
        <label style="display:block;margin:6px 0;"><input type="radio" name="<?= e($name) ?>" value="B"> B. <?= e($qq['option_b']) ?></label>
        <label style="display:block;margin:6px 0;"><input type="radio" name="<?= e($name) ?>" value="C"> C. <?= e($qq['option_c']) ?></label>
        <label style="display:block;margin:6px 0;"><input type="radio" name="<?= e($name) ?>" value="D"> D. <?= e($qq['option_d']) ?></label>
      </div>
    <?php endforeach; ?>

    <button class="btn" type="submit">Submit test</button>
    <a class="btn btn-secondary" href="<?= e($base) ?>/index.php?r=my_attempts">Exit without submitting</a>
  </form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
