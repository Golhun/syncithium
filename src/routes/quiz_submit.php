<?php
require_auth($config);
$base = base_url($config);
$pdo = db_connect($config['db']);
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect($base . '/index.php?r=quiz_start');
}

csrf_verify_or_abort();

$attemptId = (int)($_POST['attempt_id'] ?? 0);
if ($attemptId <= 0) {
    redirect($base . '/index.php?r=quiz_start');
}

$stmt = $pdo->prepare('SELECT * FROM attempts WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $attemptId, ':uid' => (int)$user['id']]);
$attempt = $stmt->fetch();
if (!$attempt) {
    http_response_code(404);
    echo 'Attempt not found.';
    exit;
}
if (!empty($attempt['completed_at'])) {
    redirect($base . '/index.php?r=results&attempt_id=' . $attemptId);
}

$q = $pdo->prepare('SELECT q.id, q.correct_option FROM attempt_questions aq JOIN questions q ON q.id = aq.question_id WHERE aq.attempt_id = :aid');
$q->execute([':aid' => $attemptId]);
$rows = $q->fetchAll();

$answers = $_POST['answer'] ?? [];
if (!is_array($answers)) $answers = [];

$total = count($rows);
$correct = 0;

$pdo->beginTransaction();
try {
    $ins = $pdo->prepare('INSERT INTO attempt_answers (attempt_id, question_id, selected_option, is_correct) VALUES (:aid, :qid, :sel, :ok)');

    foreach ($rows as $r) {
        $qid = (int)$r['id'];
        $right = strtoupper(trim((string)$r['correct_option']));
        $sel = strtoupper(trim((string)($answers[$qid] ?? '')));
        if (!in_array($sel, ['A','B','C','D'], true)) {
            $sel = null;
        }
        $ok = ($sel !== null && $sel === $right) ? 1 : 0;
        if ($ok === 1) $correct++;

        $ins->execute([
            ':aid' => $attemptId,
            ':qid' => $qid,
            ':sel' => $sel,
            ':ok' => $ok,
        ]);
    }

    $score = $total > 0 ? (int)round(($correct / $total) * 100) : 0;
    $upd = $pdo->prepare('UPDATE attempts SET score = :score, correct_count = :correct, completed_at = NOW() WHERE id = :id');
    $upd->execute([':score' => $score, ':correct' => $correct, ':id' => $attemptId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

redirect($base . '/index.php?r=results&attempt_id=' . $attemptId);
