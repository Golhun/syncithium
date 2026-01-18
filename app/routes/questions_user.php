<?php
declare(strict_types=1);

return [

  // ----------------------------
  // Quiz start (GET shows form, POST creates attempt)
  // ----------------------------
  'quiz_start' => function (PDO $db, array $config): void {
    $user = require_login($db);

    if (is_post()) {
      csrf_verify();

      $topicIdsRaw   = $_POST['topic_ids'] ?? [];
      $numQuestions  = (int)($_POST['num_questions'] ?? 20);
      $scoringModeIn = (string)($_POST['scoring_mode'] ?? 'standard');
      $timerSeconds  = (int)($_POST['timer_seconds'] ?? 3600);

      // Normalize topics
      $topicIds = [];
      foreach ((array)$topicIdsRaw as $tid) {
        $tid = (int)$tid;
        if ($tid > 0) $topicIds[] = $tid;
      }
      $topicIds = array_values(array_unique($topicIds));

      if (count($topicIds) === 0) {
        flash_set('error', 'Select at least one topic for the quiz.');
        redirect('/public/index.php?r=quiz_start');
      }

      // Clamp question count
      if ($numQuestions < 1) $numQuestions = 1;
      if ($numQuestions > 100) $numQuestions = 100;

      // Scoring mode
      $scoringMode = ($scoringModeIn === 'negative') ? 'negative' : 'standard';

      // Clamp timer to 30–90 min
      if ($timerSeconds < 1800) $timerSeconds = 1800;
      if ($timerSeconds > 5400) $timerSeconds = 5400;

      // Pull candidate questions
      $in = implode(',', array_fill(0, count($topicIds), '?'));
      $sql = "SELECT id FROM questions WHERE status = 'active' AND topic_id IN ($in) ORDER BY RAND() LIMIT ?";
      $stmt = $db->prepare($sql);
      $params = $topicIds;
      $params[] = $numQuestions;
      $stmt->execute($params);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      if (!$rows) {
        flash_set('error', 'No active questions found for the selected topics.');
        redirect('/public/index.php?r=quiz_start');
      }

      $questionIds = array_map(fn($r) => (int)$r['id'], $rows);
      $totalQuestions = count($questionIds);

      $db->beginTransaction();
      try {
        // Create attempt, explicitly set status and timer_seconds
        $stmt = $db->prepare("
          INSERT INTO attempts
            (user_id, scoring_mode, timer_seconds, status, started_at, total_questions, score, raw_correct, raw_wrong, updated_at)
          VALUES
            (:uid, :mode, :timer, 'in_progress', NOW(), :total, 0, 0, 0, NOW())
        ");
        $stmt->execute([
          ':uid'   => (int)$user['id'],
          ':mode'  => $scoringMode,
          ':timer' => $timerSeconds,
          ':total' => $totalQuestions,
        ]);

        $attemptId = (int)$db->lastInsertId();

        // Attach questions
        $ins = $db->prepare("
          INSERT INTO attempt_questions (attempt_id, question_id, marked_flag, updated_at)
          VALUES (:aid, :qid, 0, NOW())
        ");

        foreach ($questionIds as $qid) {
          $ins->execute([
            ':aid' => $attemptId,
            ':qid' => $qid,
          ]);
        }

        $db->commit();

        // Canonical param is id
        redirect('/public/index.php?r=quiz_take&id=' . $attemptId);

      } catch (Throwable $e) {
        $db->rollBack();
        flash_set('error', 'Could not start quiz. Please try again.');
        redirect('/public/index.php?r=quiz_start');
      }
    }

    // GET
    render('user/quiz_start', [
      'title' => 'Start quiz',
    ]);
  },

  // ----------------------------
  // Quiz take
  // ----------------------------
  'quiz_take' => function (PDO $db, array $config): void {
    $user = require_login($db);

    // Accept both id and attempt_id, but canonical is id
    $attemptId = (int)($_GET['id'] ?? ($_GET['attempt_id'] ?? ($_POST['id'] ?? ($_POST['attempt_id'] ?? 0))));
    if ($attemptId <= 0) {
      flash_set('error', 'Invalid attempt.');
      redirect('/public/index.php?r=taxonomy_selector');
    }

    $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $attemptId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt || (int)$attempt['user_id'] !== (int)$user['id']) {
      flash_set('error', 'Attempt not found.');
      redirect('/public/index.php?r=taxonomy_selector');
    }

    // If already submitted, go to result
    if (($attempt['status'] ?? '') === 'submitted' || !empty($attempt['submitted_at'])) {
      redirect('/public/index.php?r=quiz_result&id=' . $attemptId);
    }

    // Timer math
    $timerSeconds = (int)($attempt['timer_seconds'] ?? 0);

    // Hard guard, if timer_seconds somehow becomes 0, do not auto-submit immediately.
    // Instead, fix the attempt and continue.
    if ($timerSeconds < 1800) {
      $timerSeconds = 3600;
      $fix = $db->prepare("UPDATE attempts SET timer_seconds = :t, updated_at = NOW() WHERE id = :id");
      $fix->execute([':t' => $timerSeconds, ':id' => $attemptId]);
      $attempt['timer_seconds'] = $timerSeconds;
    }

    $startedAtStr = (string)($attempt['started_at'] ?? '');
    $startedAt = new DateTime($startedAtStr);
    $now = new DateTime('now');

    $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();
    $remaining = $timerSeconds - $elapsed;
    if ($remaining < 0) $remaining = 0;

    // Auto-submit only if timer truly expired
    if ($remaining === 0 && !is_post()) {
      redirect('/public/index.php?r=quiz_submit&id=' . $attemptId);
    }

    // POST save answers (without submitting)
    if (is_post()) {
      csrf_verify();

      $answers = $_POST['answers'] ?? [];
      if (!is_array($answers)) $answers = [];

      $marked = $_POST['marked'] ?? [];
      if (!is_array($marked)) $marked = [];

      $submitQuiz = isset($_POST['submit_quiz']) && (string)$_POST['submit_quiz'] === '1';

      $db->beginTransaction();
      try {
        $upd = $db->prepare("
          UPDATE attempt_questions
          SET selected_option = :sel,
              marked_flag     = :marked,
              updated_at      = NOW()
          WHERE id = :aqid AND attempt_id = :aid
        ");

        foreach ($answers as $aqid => $sel) {
          $aqid = (int)$aqid;
          if ($aqid <= 0) continue;

          $opt = strtoupper(trim((string)$sel));
          if (!in_array($opt, ['A','B','C','D'], true)) $opt = null;

          $isMarked = isset($marked[$aqid]) ? 1 : 0;

          $upd->execute([
            ':sel'    => $opt,
            ':marked' => $isMarked,
            ':aqid'   => $aqid,
            ':aid'    => $attemptId,
          ]);
        }

        $db->commit();

        if ($submitQuiz) {
          redirect('/public/index.php?r=quiz_submit&id=' . $attemptId);
        }

        flash_set('success', 'Saved.');
        redirect('/public/index.php?r=quiz_take&id=' . $attemptId);

      } catch (Throwable $e) {
        $db->rollBack();
        flash_set('error', 'Could not save quiz. Please try again.');
        redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
      }
    }

    // GET load questions
    $stmt = $db->prepare("
      SELECT
        aq.id AS aq_id,
        aq.selected_option,
        aq.marked_flag,
        q.id   AS question_id,
        q.question_text,
        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,
        t.name AS topic_name
      FROM attempt_questions aq
      JOIN questions q ON q.id = aq.question_id
      JOIN topics   t  ON t.id = q.topic_id
      WHERE aq.attempt_id = :aid
      ORDER BY aq.id ASC
    ");
    $stmt->execute([':aid' => $attemptId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    render('quiz/take', [
      'title'            => 'Take Quiz',
      'attempt'          => $attempt,
      'questions'        => $questions,
      'remainingSeconds' => $remaining,
    ]);
  },

  // ----------------------------
  // Quiz submit (compute scores, mark submitted)
  // ----------------------------
  'quiz_submit' => function (PDO $db, array $config): void {
    $user = require_login($db);

    $attemptId = (int)($_GET['id'] ?? ($_GET['attempt_id'] ?? ($_POST['id'] ?? ($_POST['attempt_id'] ?? 0))));
    if ($attemptId <= 0) {
      flash_set('error', 'Invalid quiz attempt.');
      redirect('/public/index.php?r=taxonomy_selector');
    }

    $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([
      ':id'  => $attemptId,
      ':uid' => (int)$user['id'],
    ]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
      flash_set('error', 'Quiz attempt not found.');
      redirect('/public/index.php?r=taxonomy_selector');
    }

    // Idempotent: if already submitted, go to result
    if (($attempt['status'] ?? '') === 'submitted' || !empty($attempt['submitted_at'])) {
      redirect('/public/index.php?r=quiz_result&id=' . $attemptId);
    }

    // Compute
    $stmt = $db->prepare("
      SELECT aq.id AS aq_id, aq.selected_option, q.correct_option
      FROM attempt_questions aq
      JOIN questions q ON q.id = aq.question_id
      WHERE aq.attempt_id = :aid
    ");
    $stmt->execute([':aid' => $attemptId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $rawCorrect = 0;
    $rawWrong = 0;

    $db->beginTransaction();
    try {
      $upd = $db->prepare("
        UPDATE attempt_questions
        SET is_correct = :isc, updated_at = NOW()
        WHERE id = :aqid
      ");

      foreach ($rows as $r) {
        $sel = strtoupper(trim((string)($r['selected_option'] ?? '')));
        if (!in_array($sel, ['A','B','C','D'], true)) $sel = '';

        $correctOpt = strtoupper((string)($r['correct_option'] ?? ''));

        $isCorrect = null;
        if ($sel !== '') {
          $isCorrect = ($sel === $correctOpt) ? 1 : 0;
          if ($isCorrect === 1) $rawCorrect++;
          else $rawWrong++;
        }

        $upd->execute([
          ':isc'  => $isCorrect,
          ':aqid' => (int)$r['aq_id'],
        ]);
      }

      $score = ($attempt['scoring_mode'] ?? '') === 'negative'
        ? ($rawCorrect - $rawWrong)
        : $rawCorrect;

      $stmt = $db->prepare("
        UPDATE attempts
        SET status = 'submitted',
            submitted_at = NOW(),
            raw_correct = :rc,
            raw_wrong = :rw,
            score = :sc,
            updated_at = NOW()
        WHERE id = :id
      ");
      $stmt->execute([
        ':rc' => $rawCorrect,
        ':rw' => $rawWrong,
        ':sc' => $score,
        ':id' => $attemptId,
      ]);

      $db->commit();
    } catch (Throwable $e) {
      $db->rollBack();
      flash_set('error', 'Could not submit quiz. Please try again.');
      redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
    }

    redirect('/public/index.php?r=quiz_result&id=' . $attemptId);
  },

  // ----------------------------
  // Quiz result
  // ----------------------------
  'quiz_result' => function (PDO $db, array $config): void {
    $user = require_login($db);

    $attemptId = (int)($_GET['id'] ?? ($_GET['attempt_id'] ?? 0));
    if ($attemptId <= 0) {
      flash_set('error', 'Invalid quiz attempt.');
      redirect('/public/index.php?r=taxonomy_selector');
    }

    $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
    $stmt->execute([
      ':id'  => $attemptId,
      ':uid' => (int)$user['id'],
    ]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt) {
      flash_set('error', 'Quiz attempt not found.');
      redirect('/public/index.php?r=taxonomy_selector');
    }

    // If not submitted, send user back to continue quiz
    if (($attempt['status'] ?? '') !== 'submitted' && empty($attempt['submitted_at'])) {
      redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
    }

    $stmt = $db->prepare("
      SELECT
        aq.id AS aq_id,
        aq.selected_option,
        aq.is_correct,
        q.question_text,
        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,
        q.correct_option,
        q.explanation,
        t.id   AS topic_id,
        t.name AS topic_name,
        s.id   AS subject_id,
        s.name AS subject_name,
        m.id   AS module_id,
        m.code AS module_code,
        l.code AS level_code
      FROM attempt_questions aq
      JOIN questions q ON q.id = aq.question_id
      JOIN topics t   ON t.id = q.topic_id
      JOIN subjects s ON s.id = t.subject_id
      JOIN modules m  ON m.id = s.module_id
      JOIN levels l   ON l.id = m.level_id
      WHERE aq.attempt_id = :aid
      ORDER BY aq.id ASC
    ");
    $stmt->execute([':aid' => $attemptId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $byTopic = [];
    $bySubject = [];
    $byModule = [];

    foreach ($rows as $r) {
      $isCorrect = ((int)($r['is_correct'] ?? 0) === 1);

      $tid = (int)$r['topic_id'];
      if (!isset($byTopic[$tid])) {
        $byTopic[$tid] = ['label' => $r['topic_name'], 'total' => 0, 'correct' => 0];
      }
      $byTopic[$tid]['total']++;
      if ($isCorrect) $byTopic[$tid]['correct']++;

      $sid = (int)$r['subject_id'];
      if (!isset($bySubject[$sid])) {
        $bySubject[$sid] = ['label' => $r['subject_name'], 'total' => 0, 'correct' => 0];
      }
      $bySubject[$sid]['total']++;
      if ($isCorrect) $bySubject[$sid]['correct']++;

      $mid = (int)$r['module_id'];
      if (!isset($byModule[$mid])) {
        $byModule[$mid] = ['label' => $r['module_code'], 'total' => 0, 'correct' => 0];
      }
      $byModule[$mid]['total']++;
      if ($isCorrect) $byModule[$mid]['correct']++;
    }

    render('user/quiz_result', [
      'title'     => 'Quiz result',
      'attempt'   => $attempt,
      'questions' => $rows,
      'byTopic'   => $byTopic,
      'bySubject' => $bySubject,
      'byModule'  => $byModule,
    ]);
  },

  // ----------------------------
  // Create a question report (single definition, JSON-friendly)
  // ----------------------------
  'question_report_create' => function (PDO $db, array $config): void {
    $user = require_login($db);

    if (!is_post()) {
      http_json(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    csrf_verify();

    $questionId = (int)($_POST['question_id'] ?? 0);
    $attemptId  = (int)($_POST['attempt_id'] ?? 0);
    $reason     = trim((string)($_POST['reason'] ?? ''));
    $details    = trim((string)($_POST['details'] ?? ''));

    if ($questionId <= 0 || $reason === '') {
      http_json(['ok' => false, 'error' => 'Invalid report.'], 422);
    }

    // Validate attempt belongs to user if provided
    if ($attemptId > 0) {
      $st = $db->prepare("SELECT id FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
      $st->execute([':id' => $attemptId, ':uid' => (int)$user['id']]);
      if (!$st->fetch()) {
        $attemptId = 0; // detach if not theirs
      }
    }

    try {
      $stmt = $db->prepare("
        INSERT INTO question_reports (user_id, attempt_id, question_id, reason, details, status, created_at, updated_at)
        VALUES (:uid, :aid, :qid, :reason, :details, 'pending', NOW(), NOW())
      ");
      $stmt->execute([
        ':uid'    => (int)$user['id'],
        ':aid'    => $attemptId > 0 ? $attemptId : null,
        ':qid'    => $questionId,
        ':reason' => $reason,
        ':details'=> $details !== '' ? $details : null,
      ]);

      http_json(['ok' => true]);
    } catch (Throwable $e) {
      http_json(['ok' => false, 'error' => 'Could not submit report.'], 500);
    }
  },

  // ----------------------------
  // My reports
  // ----------------------------
  'my_reports' => function (PDO $db, array $config): void {
    $user = require_login($db);

    $stmt = $db->prepare("
      SELECT r.*, q.question_text
      FROM question_reports r
      LEFT JOIN questions q ON q.id = r.question_id
      WHERE r.user_id = :uid
      ORDER BY r.created_at DESC
      LIMIT 200
    ");
    $stmt->execute([':uid' => (int)$user['id']]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    render('reports/my_reports', [
      'title'   => 'My Review Requests',
      'user'    => $user,
      'reports' => $reports,
    ]);
  },

];
