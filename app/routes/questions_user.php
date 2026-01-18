<?php
declare(strict_types=1);

return [

    // ------------------------------------
    // Quiz start (POST only)
    // ------------------------------------
    'quiz_start' => function (PDO $db, array $config): void {
        $user = require_login($db);

        // Do NOT render a missing view. Your quiz start happens from taxonomy_selector.
        if (!is_post()) {
            redirect('/public/index.php?r=taxonomy_selector');
        }

        csrf_verify();

        $topicIdsRaw   = $_POST['topic_ids'] ?? [];
        $numQuestions  = (int)($_POST['num_questions'] ?? 20);
        $scoringModeIn = (string)($_POST['scoring_mode'] ?? 'standard');
        $timerSeconds  = (int)($_POST['timer_seconds'] ?? 1800);

        // Normalise topics
        $topicIds = [];
        foreach ((array)$topicIdsRaw as $tid) {
            $tid = (int)$tid;
            if ($tid > 0) $topicIds[] = $tid;
        }
        $topicIds = array_values(array_unique($topicIds));

        if (count($topicIds) === 0) {
            flash_set('error', 'Select at least one topic for the quiz.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        // Clamp question count
        if ($numQuestions < 1) $numQuestions = 1;
        if ($numQuestions > 200) $numQuestions = 200;

        // Scoring mode
        $scoringMode = ($scoringModeIn === 'negative') ? 'negative' : 'standard';

        // Clamp timer: 30–90 minutes
        if ($timerSeconds < 1800) $timerSeconds = 1800;
        if ($timerSeconds > 5400) $timerSeconds = 5400;

        // Pull candidate questions (LIMIT must be an int literal for some PDO drivers)
        $in = implode(',', array_fill(0, count($topicIds), '?'));
        $limit = (int)$numQuestions;

        $stmt = $db->prepare("
            SELECT id
            FROM questions
            WHERE status = 'active'
              AND topic_id IN ($in)
            ORDER BY RAND()
            LIMIT $limit
        ");
        $stmt->execute($topicIds);
        $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        if (count($questionIds) === 0) {
            flash_set('error', 'No active questions found for the selected topics.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        $totalQuestions = count($questionIds);

        $db->beginTransaction();
        try {
            // Create attempt (expects these columns based on your existing queries)
            $stmtA = $db->prepare("
                INSERT INTO attempts (
                    user_id, scoring_mode, timer_seconds, status,
                    started_at, total_questions,
                    score, raw_correct, raw_wrong,
                    created_at, updated_at
                ) VALUES (
                    :uid, :mode, :timer, 'in_progress',
                    NOW(), :total,
                    0, 0, 0,
                    NOW(), NOW()
                )
            ");
            $stmtA->execute([
                ':uid'   => (int)$user['id'],
                ':mode'  => $scoringMode,
                ':timer' => $timerSeconds,
                ':total' => $totalQuestions,
            ]);

            $attemptId = (int)$db->lastInsertId();

            // Attach questions with position
            $stmtAQ = $db->prepare("
                INSERT INTO attempt_questions (
                    attempt_id, question_id, position, selected_option, marked_flag,
                    is_correct, created_at, updated_at
                ) VALUES (
                    :aid, :qid, :pos, NULL, 0,
                    NULL, NOW(), NOW()
                )
            ");

            $pos = 1;
            foreach ($questionIds as $qid) {
                $stmtAQ->execute([
                    ':aid' => $attemptId,
                    ':qid' => (int)$qid,
                    ':pos' => $pos++,
                ]);
            }

            $db->commit();

            // IMPORTANT: use id consistently
            redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
        } catch (Throwable $e) {
            $db->rollBack();
            flash_set('error', 'Could not start quiz. Please try again.');
            redirect('/public/index.php?r=taxonomy_selector');
        }
    },

    // ------------------------------------
    // Quiz take
    // ------------------------------------
    'quiz_take' => function (PDO $db, array $config): void {
        $user = require_login($db);

        // Accept both id and attempt_id to avoid URL mismatch errors
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

        if (($attempt['status'] ?? '') === 'submitted') {
            redirect('/public/index.php?r=quiz_result&id=' . $attemptId);
        }

        // Remaining time (server truth)
        $timerSeconds = (int)($attempt['timer_seconds'] ?? 0);
        $startedAt    = new DateTime((string)$attempt['started_at']);
        $now          = new DateTime('now');
        $elapsed      = $now->getTimestamp() - $startedAt->getTimestamp();
        $remaining    = $timerSeconds - $elapsed;
        if ($remaining < 0) $remaining = 0;

        // If time finished, auto-submit
        if ($remaining === 0 && !is_post()) {
            redirect('/public/index.php?r=quiz_submit&id=' . $attemptId);
        }

        // Load attempt questions
        $stmtQ = $db->prepare("
            SELECT
              aq.id AS aq_id,
              aq.position,
              aq.selected_option,
              aq.marked_flag,
              q.id AS question_id,
              q.question_text,
              q.option_a, q.option_b, q.option_c, q.option_d,
              t.name AS topic_name
            FROM attempt_questions aq
            JOIN questions q ON q.id = aq.question_id
            JOIN topics t ON t.id = q.topic_id
            WHERE aq.attempt_id = :aid
            ORDER BY aq.position ASC
        ");
        $stmtQ->execute([':aid' => $attemptId]);
        $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // IMPORTANT: render path should match your existing view naming
        // Your quiz_result uses render('user/quiz_result'), so we keep "user/*"
        render('user/quiz_take', [
            'title'            => 'Take Quiz',
            'attempt'          => $attempt,
            'questions'        => $questions,
            'remainingSeconds' => $remaining,
        ]);
    },

    // ------------------------------------
    // Quiz submit
    // ------------------------------------
    'quiz_submit' => function (PDO $db, array $config): void {
        $user = require_login($db);

        $attemptId = (int)($_GET['id'] ?? $_GET['attempt_id'] ?? $_POST['id'] ?? $_POST['attempt_id'] ?? 0);
        if ($attemptId <= 0) {
            flash_set('error', 'Invalid quiz attempt.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
        $stmt->execute([':id' => $attemptId, ':uid' => (int)$user['id']]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            flash_set('error', 'Quiz attempt not found.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        if (($attempt['status'] ?? '') === 'submitted') {
            redirect('/public/index.php?r=quiz_result&id=' . $attemptId);
        }

        // Pull all items in the attempt
        $stmt = $db->prepare("
            SELECT aq.id AS aq_id, aq.selected_option, q.correct_option
            FROM attempt_questions aq
            JOIN questions q ON q.id = aq.question_id
            WHERE aq.attempt_id = :aid
            ORDER BY aq.position ASC
        ");
        $stmt->execute([':aid' => $attemptId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rawCorrect = 0;
        $rawWrong   = 0;

        $db->beginTransaction();
        try {
            $updAQ = $db->prepare("
                UPDATE attempt_questions
                SET is_correct = :isc,
                    updated_at = NOW()
                WHERE id = :id
            ");

            foreach ($rows as $r) {
                $sel = strtoupper(trim((string)($r['selected_option'] ?? '')));
                if (!in_array($sel, ['A','B','C','D'], true)) {
                    $sel = '';
                }

                $correct = strtoupper(trim((string)($r['correct_option'] ?? '')));
                $isCorrect = null;

                if ($sel !== '') {
                    $isCorrect = ($sel === $correct) ? 1 : 0;
                    if ($isCorrect === 1) $rawCorrect++;
                    else $rawWrong++;
                }

                $updAQ->execute([
                    ':isc' => $isCorrect,
                    ':id'  => (int)$r['aq_id'],
                ]);
            }

            $score = ($attempt['scoring_mode'] ?? 'standard') === 'negative'
                ? ($rawCorrect - $rawWrong)
                : $rawCorrect;

            $stmtU = $db->prepare("
                UPDATE attempts
                SET status = 'submitted',
                    submitted_at = NOW(),
                    raw_correct = :c,
                    raw_wrong   = :w,
                    score       = :s,
                    updated_at  = NOW()
                WHERE id = :id
            ");
            $stmtU->execute([
                ':c'  => $rawCorrect,
                ':w'  => $rawWrong,
                ':s'  => $score,
                ':id' => $attemptId,
            ]);

            $db->commit();
            redirect('/public/index.php?r=quiz_result&id=' . $attemptId);
        } catch (Throwable $e) {
            $db->rollBack();
            flash_set('error', 'Could not submit quiz. Please try again.');
            redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
        }
    },

    // ------------------------------------
    // Quiz result
    // ------------------------------------
    'quiz_result' => function (PDO $db, array $config): void {
        $user = require_login($db);

        $attemptId = (int)($_GET['id'] ?? ($_GET['attempt_id'] ?? 0));
        if ($attemptId <= 0) {
            flash_set('error', 'Invalid quiz attempt.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
        $stmt->execute([':id' => $attemptId, ':uid' => (int)$user['id']]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            flash_set('error', 'Quiz attempt not found.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        if (($attempt['status'] ?? '') !== 'submitted') {
            redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
        }

        $stmt = $db->prepare("
            SELECT
              aq.id AS aq_id,
              aq.selected_option,
              aq.is_correct,
              q.id AS question_id,
              q.question_text,
              q.option_a, q.option_b, q.option_c, q.option_d,
              q.correct_option,
              q.explanation,
              t.id   AS topic_id,
              t.name AS topic_name,
              s.id   AS subject_id,
              s.name AS subject_name,
              m.id   AS module_id,
              m.code AS module_code
            FROM attempt_questions aq
            JOIN questions q ON q.id = aq.question_id
            JOIN topics t ON t.id = q.topic_id
            JOIN subjects s ON s.id = t.subject_id
            JOIN modules m ON m.id = s.module_id
            WHERE aq.attempt_id = :aid
            ORDER BY aq.position ASC
        ");
        $stmt->execute([':aid' => $attemptId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Aggregates
        $byTopic   = [];
        $bySubject = [];
        $byModule  = [];

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

    // ------------------------------------
    // Question report create (single definitive route)
    // ------------------------------------
    'question_report_create' => function (PDO $db, array $config): void {
        $user = require_login($db);

        if (!is_post()) {
            redirect('/public/index.php');
        }

        csrf_verify();

        $questionId = (int)($_POST['question_id'] ?? 0);
        $attemptId  = (int)($_POST['attempt_id'] ?? $_POST['id'] ?? 0);
        $reason     = trim((string)($_POST['reason'] ?? ''));
        $details    = trim((string)($_POST['details'] ?? ''));

        $wantsJson = (
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json')) ||
            (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
        );

        if ($questionId <= 0 || $reason === '') {
            if ($wantsJson) {
                http_json(['ok' => false, 'error' => 'Invalid report.'], 422);
            }
            flash_set('error', 'Invalid report.');
            redirect('/public/index.php');
        }

        // Validate attempt belongs to user if provided, otherwise detach
        if ($attemptId > 0) {
            $st = $db->prepare("SELECT id FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
            $st->execute([':id' => $attemptId, ':uid' => (int)$user['id']]);
            if (!$st->fetch()) {
                $attemptId = 0;
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

            if ($wantsJson) {
                http_json(['ok' => true]);
            }

            flash_set('success', 'Report sent. Thank you.');
            redirect('/public/index.php');
        } catch (Throwable $e) {
            if ($wantsJson) {
                http_json(['ok' => false, 'error' => 'Could not submit report.'], 500);
            }
            flash_set('error', 'Could not submit report.');
            redirect('/public/index.php');
        }
    },
];
