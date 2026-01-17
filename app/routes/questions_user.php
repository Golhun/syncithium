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

        if (!empty($attempt['submitted_at'])) {
            redirect('/public/index.php?r=quiz_result&attempt_id=' . $attemptId);
        }

        // Timer logic (server side)
        $started   = strtotime((string)$attempt['started_at']);
        $elapsed   = max(0, time() - $started);
        $remaining = max(0, (int)$attempt['timer_seconds'] - $elapsed);

        if ($remaining <= 0) {
            // Time is over, force submission
            redirect('/public/index.php?r=quiz_submit&attempt_id=' . $attemptId);
        }

        // Load questions for this attempt
        $stmt = $db->prepare("
            SELECT
              aq.id AS aq_id,
              aq.selected_option,
              aq.marked_flag,
              q.id AS question_id,
              q.question_text,
              q.option_a,
              q.option_b,
              q.option_c,
              q.option_d
            FROM attempt_questions aq
            JOIN questions q ON q.id = aq.question_id
            WHERE aq.attempt_id = :aid
            ORDER BY aq.id
        ");
        $stmt->execute([':aid' => $attemptId]);
        $attemptQuestions = $stmt->fetchAll() ?: [];

        render('user/quiz_take', [
            'title'            => 'Quiz in progress',
            'attempt'          => $attempt,
            'attemptQuestions' => $attemptQuestions,
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

];
