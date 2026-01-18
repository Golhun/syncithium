<?php
declare(strict_types=1);

return [

    // ------------------------------------
    // Quiz start: select topics, size, mode
    // ------------------------------------
    'quiz_start' => function (PDO $db, array $config): void {
        $user = require_login($db);

        // We will use existing /api_levels, /api_modules, /api_subjects, /api_topics
        // so we do not have to preload levels here, but you can if you want.

        if (is_post()) {
            csrf_verify();

            $topicIdsRaw   = $_POST['topic_ids'] ?? [];
            $numQuestions  = (int)($_POST['num_questions'] ?? 20);
            $scoringModeIn = (string)($_POST['scoring_mode'] ?? 'standard');
            $timerSeconds  = (int)($_POST['timer_seconds'] ?? 1800);

            // Normalise topics
            $topicIds = [];
            foreach ((array)$topicIdsRaw as $tid) {
                $tid = (int)$tid;
                if ($tid > 0) {
                    $topicIds[] = $tid;
                }
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

            // Clamp timer: 30–90 minutes
            if ($timerSeconds < 1800) $timerSeconds = 1800;
            if ($timerSeconds > 5400) $timerSeconds = 5400;

            // Pull candidate questions from selected topics
            $in = implode(',', array_fill(0, count($topicIds), '?'));
            $sql = "SELECT id FROM questions WHERE status = 'active' AND topic_id IN ($in) ORDER BY RAND() LIMIT ?";
            $stmt = $db->prepare($sql);
            $params = $topicIds;
            $params[] = $numQuestions;
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            if (!$rows) {
                flash_set('error', 'No active questions found for the selected topics.');
                redirect('/public/index.php?r=quiz_start');
            }

            $questionIds    = array_map(fn($r) => (int)$r['id'], $rows);
            $totalQuestions = count($questionIds);

            $db->beginTransaction();
            try {
                // Create attempt
                $stmt = $db->prepare("
                    INSERT INTO attempts
                      (user_id, scoring_mode, timer_seconds, started_at, total_questions, score, raw_correct, raw_wrong)
                    VALUES
                      (:uid, :mode, :timer, NOW(), :total, 0, 0, 0)
                ");
                $stmt->execute([
                    ':uid'   => (int)$user['id'],
                    ':mode'  => $scoringMode,
                    ':timer' => $timerSeconds,
                    ':total' => $totalQuestions,
                ]);

                $attemptId = (int)$db->lastInsertId();

                // Attach questions to attempt
                $stmt = $db->prepare("
                    INSERT INTO attempt_questions (attempt_id, question_id, marked_flag)
                    VALUES (:aid, :qid, 0)
                ");
                foreach ($questionIds as $qid) {
                    $stmt->execute([
                        ':aid' => $attemptId,
                        ':qid' => $qid,
                    ]);
                }

                $db->commit();
                redirect('/public/index.php?r=quiz_take&attempt_id=' . $attemptId);
            } catch (Throwable $e) {
                $db->rollBack();
                flash_set('error', 'Could not start quiz. Please try again.');
                redirect('/public/index.php?r=quiz_start');
            }
        }

        render('user/quiz_start', [
            'title' => 'Start quiz',
        ]);
    },

    // ------------------------------------
    // Quiz take: show questions + timer
    // ------------------------------------
'quiz_take' => function (PDO $db, array $config): void {
    $user = require_login($db);

    $attemptId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
    if ($attemptId <= 0) {
        flash_set('error', 'Invalid attempt.');
        redirect('/public/index.php');
    }

    $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $attemptId]);
    $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$attempt || (int)$attempt['user_id'] !== (int)$user['id']) {
        flash_set('error', 'Attempt not found.');
        redirect('/public/index.php');
    }

    if (($attempt['status'] ?? '') === 'submitted') {
        redirect('/public/index.php?r=quiz_review&id=' . $attemptId);
    }

    // Remaining time
    $timerSeconds = (int)$attempt['timer_seconds'];
    $startedAt    = new DateTime((string)$attempt['started_at']);
    $now          = new DateTime('now');
    $elapsed      = $now->getTimestamp() - $startedAt->getTimestamp();
    $remaining    = $timerSeconds - $elapsed;
    if ($remaining < 0) $remaining = 0;

    $wantsJson = (
        (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json')) ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
    );

    // Auto-finalise if time over on GET
    if ($remaining === 0 && ($attempt['status'] ?? '') === 'in_progress' && !is_post()) {
        // Submit as if user clicked submit
        $_POST['submit_quiz'] = '1';
    }

    if (is_post()) {
        csrf_verify();

        $answers = $_POST['answers'] ?? [];
        if (!is_array($answers)) $answers = [];

        $marked = $_POST['marked'] ?? [];
        if (!is_array($marked)) $marked = [];

        $submitQuiz = isset($_POST['submit_quiz']) && (string)$_POST['submit_quiz'] === '1';

        $db->beginTransaction();
        try {
            $stmtUpdate = $db->prepare("
                UPDATE attempt_questions
                SET selected_option = :sel,
                    marked_flag     = :marked,
                    updated_at      = NOW()
                WHERE id = :id AND attempt_id = :aid
            ");

            foreach ($answers as $aqid => $sel) {
                $aqid = (int)$aqid;
                if ($aqid <= 0) continue;

                $opt = strtoupper(trim((string)$sel));
                if (!in_array($opt, ['A','B','C','D'], true)) {
                    $opt = null;
                }

                $isMarked = isset($marked[$aqid]) ? 1 : 0;

                $stmtUpdate->execute([
                    ':sel'    => $opt,
                    ':marked' => $isMarked,
                    ':id'     => $aqid,
                    ':aid'    => $attemptId,
                ]);
            }

            if ($submitQuiz) {
                // Compute scores
                $stats = ['correct'=>0, 'wrong'=>0, 'total'=>(int)$attempt['total_questions'], 'score'=>0];

                $stmtQ = $db->prepare("
                    SELECT aq.id, aq.selected_option, q.correct_option
                    FROM attempt_questions aq
                    JOIN questions q ON q.id = aq.question_id
                    WHERE aq.attempt_id = :aid
                    ORDER BY aq.position ASC
                ");
                $stmtQ->execute([':aid' => $attemptId]);
                $rows = $stmtQ->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $updateQ = $db->prepare("
                    UPDATE attempt_questions
                    SET is_correct = :is_correct,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                foreach ($rows as $row) {
                    $sel = strtoupper(trim((string)($row['selected_option'] ?? '')));
                    if (!in_array($sel, ['A','B','C','D'], true)) $sel = null;

                    $isCorrect = null;
                    if ($sel !== null) {
                        $isCorrect = ($sel === strtoupper((string)$row['correct_option'])) ? 1 : 0;
                    }

                    if ($isCorrect === 1) $stats['correct']++;
                    elseif ($isCorrect === 0) $stats['wrong']++;

                    $updateQ->execute([
                        ':is_correct' => $isCorrect,
                        ':id'         => (int)$row['id'],
                    ]);
                }

                $stats['score'] = ($attempt['scoring_mode'] === 'negative')
                    ? ($stats['correct'] - $stats['wrong'])
                    : $stats['correct'];

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
                    ':c'  => $stats['correct'],
                    ':w'  => $stats['wrong'],
                    ':s'  => $stats['score'],
                    ':id' => $attemptId,
                ]);
            }

            $db->commit();

            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => true,
                    'submitted' => $submitQuiz,
                    'remaining' => $remaining,
                ]);
                return;
            }

            if ($submitQuiz) {
                redirect('/public/index.php?r=quiz_review&id=' . $attemptId);
            } else {
                flash_set('success', 'Saved.');
                redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
            }
        } catch (Throwable $e) {
            $db->rollBack();

            if ($wantsJson) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
                return;
            }

            flash_set('error', 'Could not save quiz: ' . $e->getMessage());
            redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
        }
    }

    // GET: load questions
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
          q.correct_option,
          t.name AS topic_name
        FROM attempt_questions aq
        JOIN questions q ON q.id = aq.question_id
        JOIN topics   t  ON t.id = q.topic_id
        WHERE aq.attempt_id = :aid
        ORDER BY aq.position ASC
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


    // ------------------------------------
    // Quiz submit (manual or auto)
    // ------------------------------------
    'quiz_submit' => function (PDO $db, array $config): void {
        $user      = require_login($db);
        $attemptId = (int)($_GET['attempt_id'] ?? ($_POST['attempt_id'] ?? 0));

        if ($attemptId <= 0) {
            flash_set('error', 'Invalid quiz attempt.');
            redirect('/public/index.php?r=quiz_start');
        }

        $stmt = $db->prepare("
            SELECT * FROM attempts
            WHERE id = :id AND user_id = :uid
            LIMIT 1
        ");
        $stmt->execute([
            ':id'  => $attemptId,
            ':uid' => (int)$user['id'],
        ]);
        $attempt = $stmt->fetch();

        if (!$attempt) {
            flash_set('error', 'Quiz attempt not found.');
            redirect('/public/index.php?r=quiz_start');
        }

        if (!empty($attempt['submitted_at'])) {
            redirect('/public/index.php?r=quiz_result&attempt_id=' . $attemptId);
        }

        // Only check CSRF on explicit POST; auto-submit via GET is allowed for expiry
        if (is_post()) {
            csrf_verify();
        }

        // Pull all items in the attempt
        $stmt = $db->prepare("
            SELECT aq.id AS aq_id,
                   aq.question_id,
                   q.correct_option
            FROM attempt_questions aq
            JOIN questions q ON q.id = aq.question_id
            WHERE aq.attempt_id = :aid
        ");
        $stmt->execute([':aid' => $attemptId]);
        $rows = $stmt->fetchAll() ?: [];

        // Parse submitted answers (if any)
        $selected = [];
        $marked   = [];

        if (is_post()) {
            foreach ($_POST['answer'] ?? [] as $aqIdStr => $opt) {
                $aqId = (int)$aqIdStr;
                $opt  = strtoupper(trim((string)$opt));
                if (in_array($opt, ['A','B','C','D'], true)) {
                    $selected[$aqId] = $opt;
                }
            }

            foreach ($_POST['marked'] ?? [] as $aqIdStr => $flag) {
                $aqId = (int)$aqIdStr;
                $marked[$aqId] = 1;
            }
        }

        $rawCorrect = 0;
        $rawWrong   = 0;

        $db->beginTransaction();
        try {
            $update = $db->prepare("
                UPDATE attempt_questions
                SET selected_option = :sel,
                    is_correct      = :isc,
                    marked_flag     = :mark
                WHERE id = :id
            ");

            foreach ($rows as $r) {
                $aqId   = (int)$r['aq_id'];
                $sel    = $selected[$aqId] ?? null;
                $mark   = isset($marked[$aqId]) ? 1 : 0;
                $correctOption = strtoupper((string)$r['correct_option']);

                $isCorrect = null;
                if ($sel !== null) {
                    $isCorrect = ($sel === $correctOption) ? 1 : 0;
                    if ($isCorrect === 1) {
                        $rawCorrect++;
                    } else {
                        $rawWrong++;
                    }
                }

                $update->execute([
                    ':sel'  => $sel,
                    ':isc'  => $isCorrect,
                    ':mark' => $mark,
                    ':id'   => $aqId,
                ]);
            }

            // Compute final score
            $score = (int)$rawCorrect;
            if ((string)$attempt['scoring_mode'] === 'negative') {
                $score = $rawCorrect - $rawWrong;
            }

            $stmt = $db->prepare("
                UPDATE attempts
                SET submitted_at = NOW(),
                    raw_correct  = :rc,
                    raw_wrong    = :rw,
                    score        = :sc
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
            redirect('/public/index.php?r=quiz_take&attempt_id=' . $attemptId);
        }

        redirect('/public/index.php?r=quiz_result&attempt_id=' . $attemptId);
    },

    // ------------------------------------
    // Quiz result + review
    // ------------------------------------
    'quiz_result' => function (PDO $db, array $config): void {
        $user      = require_login($db);
        $attemptId = (int)($_GET['attempt_id'] ?? 0);

        if ($attemptId <= 0) {
            flash_set('error', 'Invalid quiz attempt.');
            redirect('/public/index.php?r=quiz_start');
        }

        $stmt = $db->prepare("
            SELECT * FROM attempts
            WHERE id = :id AND user_id = :uid
            LIMIT 1
        ");
        $stmt->execute([
            ':id'  => $attemptId,
            ':uid' => (int)$user['id'],
        ]);
        $attempt = $stmt->fetch();

        if (!$attempt) {
            flash_set('error', 'Quiz attempt not found.');
            redirect('/public/index.php?r=quiz_start');
        }

        if (empty($attempt['submitted_at'])) {
            redirect('/public/index.php?r=quiz_take&attempt_id=' . $attemptId);
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
            ORDER BY aq.id
        ");
        $stmt->execute([':aid' => $attemptId]);
        $rows = $stmt->fetchAll() ?: [];

        // Aggregates: topic, subject, module
        $byTopic   = [];
        $bySubject = [];
        $byModule  = [];

        foreach ($rows as $r) {
            $isCorrect = ((int)($r['is_correct'] ?? 0) === 1);

            $tid = (int)$r['topic_id'];
            if (!isset($byTopic[$tid])) {
                $byTopic[$tid] = [
                    'label'   => $r['topic_name'],
                    'total'   => 0,
                    'correct' => 0,
                ];
            }
            $byTopic[$tid]['total']++;
            if ($isCorrect) $byTopic[$tid]['correct']++;

            $sid = (int)$r['subject_id'];
            if (!isset($bySubject[$sid])) {
                $bySubject[$sid] = [
                    'label'   => $r['subject_name'],
                    'total'   => 0,
                    'correct' => 0,
                ];
            }
            $bySubject[$sid]['total']++;
            if ($isCorrect) $bySubject[$sid]['correct']++;

            $mid = (int)$r['module_id'];
            if (!isset($byModule[$mid])) {
                $byModule[$mid] = [
                    'label'   => $r['module_code'],
                    'total'   => 0,
                    'correct' => 0,
                ];
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

    'question_report_create' => function (PDO $db, array $config): void {
    $user = require_login($db);

    if (!is_post()) {
        http_json(['ok' => false, 'error' => 'Method not allowed'], 405);
    }

    csrf_verify();

    $questionId = (int)($_POST['question_id'] ?? 0);
    $attemptId  = (int)($_POST['attempt_id'] ?? 0);
    $type       = (string)($_POST['report_type'] ?? 'issue');
    $message    = trim((string)($_POST['message'] ?? ''));

    if ($questionId <= 0 || $message === '') {
        http_json(['ok' => false, 'error' => 'Question and message are required.'], 422);
    }

    if (!in_array($type, ['issue','answer_dispute','other'], true)) {
        $type = 'issue';
    }

    // Optional: validate attempt belongs to user if attempt_id provided
    if ($attemptId > 0) {
        $st = $db->prepare("SELECT id FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
        $st->execute([':id' => $attemptId, ':uid' => (int)$user['id']]);
        if (!$st->fetch()) {
            $attemptId = 0; // do not fail hard, just detach
        }
    }

    $stmt = $db->prepare("
        INSERT INTO question_reports (user_id, question_id, attempt_id, report_type, message, status, created_at, updated_at)
        VALUES (:uid, :qid, :aid, :type, :msg, 'open', NOW(), NOW())
    ");
    $stmt->execute([
        ':uid'  => (int)$user['id'],
        ':qid'  => $questionId,
        ':aid'  => ($attemptId > 0 ? $attemptId : null),
        ':type' => $type,
        ':msg'  => $message,
    ]);

    http_json(['ok' => true]);
},

'my_reports' => function (PDO $db, array $config): void {
    $user = require_login($db);

    $stmt = $db->prepare("
        SELECT
          r.*,
          q.question_text
        FROM question_reports r
        LEFT JOIN questions q ON q.id = r.question_id
        WHERE r.user_id = :uid
        ORDER BY r.created_at DESC
        LIMIT 200
    ");
    $stmt->execute([':uid' => (int)$user['id']]);
    $reports = $stmt->fetchAll() ?: [];

    render('reports/my_reports', [
        'title' => 'My Review Requests',
        'user' => $user,
        'reports' => $reports,
    ]);
},

'question_report_create' => function (PDO $db, array $config): void {
    $user = require_login($db);

    if (!is_post()) {
        redirect('/public/index.php');
    }

    csrf_verify();

    $questionId = (int)($_POST['question_id'] ?? 0);
    $attemptId  = (int)($_POST['attempt_id'] ?? 0);
    $reason     = trim((string)($_POST['reason'] ?? ''));
    $details    = trim((string)($_POST['details'] ?? ''));

    $wantsJson = (
        (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string)$_SERVER['HTTP_ACCEPT'], 'application/json')) ||
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')
    );

    if ($questionId <= 0 || $reason === '') {
        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false, 'error'=>'Invalid report.']);
            return;
        }
        flash_set('error', 'Invalid report.');
        redirect('/public/index.php');
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO question_reports (user_id, attempt_id, question_id, reason, details, status, created_at, updated_at)
            VALUES (:uid, :aid, :qid, :reason, :details, 'pending', NOW(), NOW())
        ");
        $stmt->execute([
            ':uid' => (int)$user['id'],
            ':aid' => $attemptId > 0 ? $attemptId : null,
            ':qid' => $questionId,
            ':reason' => $reason,
            ':details' => $details !== '' ? $details : null,
        ]);

        if ($wantsJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>true]);
            return;
        }

        flash_set('success', 'Report sent. Thank you.');
        redirect('/public/index.php');
    } catch (Throwable $e) {
        if ($wantsJson) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok'=>false, 'error'=>'Could not submit report.']);
            return;
        }
        flash_set('error', 'Could not submit report.');
        redirect('/public/index.php');
    }
},



];
