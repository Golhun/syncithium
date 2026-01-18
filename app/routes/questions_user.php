<?php
declare(strict_types=1);

return [

    // -------------------------
    // Quiz: start (configure attempt)
    // -------------------------
    'quiz_start' => function (PDO $db, array $config): void {
        $user = require_login($db);

        // Only accept POST from taxonomy_selector
        if (!is_post()) {
            redirect('/public/index.php?r=taxonomy_selector');
        }

        csrf_verify();

        $topicIds = $_POST['topic_ids'] ?? [];
        if (!is_array($topicIds)) $topicIds = [];

        $topicIds = array_values(array_unique(array_filter(
            array_map(static fn($v) => (int)$v, $topicIds),
            static fn($v) => $v > 0
        )));

        if (count($topicIds) === 0) {
            flash_set('error', 'Select at least one topic.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        $numQuestions = (int)($_POST['num_questions'] ?? 20);
        if ($numQuestions < 1) $numQuestions = 1;
        if ($numQuestions > 200) $numQuestions = 200;

        $scoringMode = (string)($_POST['scoring_mode'] ?? 'standard');
        if (!in_array($scoringMode, ['standard', 'negative'], true)) {
            $scoringMode = 'standard';
        }

        $timerSeconds = (int)($_POST['timer_seconds'] ?? 3600);
        $allowedTimers = [1800, 2700, 3600, 5400];
        if (!in_array($timerSeconds, $allowedTimers, true)) {
            $timerSeconds = 3600;
        }

        // Fetch random questions
        $placeholders = implode(',', array_fill(0, count($topicIds), '?'));
        $limit = (int)$numQuestions;

        $stmt = $db->prepare("
            SELECT id
            FROM questions
            WHERE status = 'active'
              AND topic_id IN ($placeholders)
            ORDER BY RAND()
            LIMIT $limit
        ");
        $stmt->execute($topicIds);
        $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];

        if (count($questionIds) === 0) {
            flash_set('error', 'No active questions found for the selected topics.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT INTO attempts (
                    user_id,
                    scoring_mode,
                    timer_seconds,
                    status,
                    started_at,
                    created_at,
                    updated_at,
                    total_questions
                ) VALUES (
                    :uid,
                    :mode,
                    :timer,
                    'in_progress',
                    NOW(),
                    NOW(),
                    NOW(),
                    :total
                )
            ");
            $stmt->execute([
                ':uid'   => (int)$user['id'],
                ':mode'  => $scoringMode,
                ':timer' => $timerSeconds,
                ':total' => count($questionIds),
            ]);

            $attemptId = (int)$db->lastInsertId();

            $stmtQ = $db->prepare("
                INSERT INTO attempt_questions (
                    attempt_id,
                    question_id,
                    position,
                    created_at,
                    updated_at
                ) VALUES (
                    :aid,
                    :qid,
                    :pos,
                    NOW(),
                    NOW()
                )
            ");

            $pos = 1;
            foreach ($questionIds as $qid) {
                $stmtQ->execute([
                    ':aid' => $attemptId,
                    ':qid' => (int)$qid,
                    ':pos' => $pos++,
                ]);
            }

            $db->commit();
            redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
        } catch (Throwable $e) {
            $db->rollBack();
            flash_set('error', 'Could not create quiz: ' . $e->getMessage());
            redirect('/public/index.php?r=taxonomy_selector');
        }
    },

    // -------------------------
    // Quiz: take (answer + timer)
    // -------------------------
    'quiz_take' => function (PDO $db, array $config): void {
        $user = require_login($db);

        $attemptId = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($attemptId <= 0) {
            flash_set('error', 'Invalid attempt.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $attemptId]);
        $attempt = $stmt->fetch();

        if (!$attempt || (int)$attempt['user_id'] !== (int)$user['id']) {
            flash_set('error', 'Attempt not found.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        if (($attempt['status'] ?? '') === 'submitted') {
            redirect('/public/index.php?r=quiz_review&id=' . $attemptId);
        }

        // Remaining time
        $timerSeconds = (int)$attempt['timer_seconds'];
        $startedAt = new DateTime((string)$attempt['started_at']);
        $now = new DateTime('now');
        $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();
        $remaining = $timerSeconds - $elapsed;
        if ($remaining < 0) $remaining = 0;

        // POST: save answers and submit
        if (is_post()) {
            csrf_verify();

            $answers = $_POST['answers'] ?? [];
            if (!is_array($answers)) $answers = [];

            $marked = $_POST['marked'] ?? [];
            if (!is_array($marked)) $marked = [];

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
                    if (!in_array($opt, ['A', 'B', 'C', 'D'], true)) $opt = null;

                    $isMarked = isset($marked[$aqid]) ? 1 : 0;

                    $stmtUpdate->execute([
                        ':sel'    => $opt,
                        ':marked' => $isMarked,
                        ':id'     => $aqid,
                        ':aid'    => $attemptId,
                    ]);
                }

                // Score
                $stats = ['correct' => 0, 'wrong' => 0, 'score' => 0];

                $stmtQ = $db->prepare("
                    SELECT aq.id, aq.selected_option, q.correct_option
                    FROM attempt_questions aq
                    JOIN questions q ON q.id = aq.question_id
                    WHERE aq.attempt_id = :aid
                    ORDER BY aq.position ASC
                ");
                $stmtQ->execute([':aid' => $attemptId]);
                $rows = $stmtQ->fetchAll() ?: [];

                $updateCorrect = $db->prepare("
                    UPDATE attempt_questions
                    SET is_correct = :is_correct,
                        updated_at = NOW()
                    WHERE id = :id
                ");

                foreach ($rows as $row) {
                    $sel = strtoupper(trim((string)($row['selected_option'] ?? '')));
                    if (!in_array($sel, ['A', 'B', 'C', 'D'], true)) $sel = null;

                    $isCorrect = null;
                    if ($sel !== null) {
                        $isCorrect = ($sel === strtoupper((string)$row['correct_option'])) ? 1 : 0;
                    }

                    if ($isCorrect === 1) $stats['correct']++;
                    if ($isCorrect === 0) $stats['wrong']++;

                    $updateCorrect->execute([
                        ':is_correct' => $isCorrect,
                        ':id' => (int)$row['id'],
                    ]);
                }

                $mode = (string)($attempt['scoring_mode'] ?? 'standard');
                $stats['score'] = ($mode === 'negative')
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

                $db->commit();
                redirect('/public/index.php?r=quiz_review&id=' . $attemptId);
            } catch (Throwable $e) {
                $db->rollBack();
                flash_set('error', 'Could not submit quiz: ' . $e->getMessage());
                redirect('/public/index.php?r=quiz_take&id=' . $attemptId);
            }
        }

        // GET: load questions for display
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
        $questions = $stmt->fetchAll() ?: [];

        render('quiz/take', [
            'title'            => 'Take Quiz',
            'attempt'          => $attempt,
            'questions'        => $questions,
            'remainingSeconds' => $remaining,
        ]);
    },

    // -------------------------
    // Quiz: review
    // -------------------------
    'quiz_review' => function (PDO $db, array $config): void {
        $user = require_login($db);

        $attemptId = (int)($_GET['id'] ?? 0);
        if ($attemptId <= 0) {
            flash_set('error', 'Invalid attempt.');
            redirect('/public/index.php?r=taxonomy_selector');
        }

        $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $attemptId]);
        $attempt = $stmt->fetch();

        if (!$attempt || (int)$attempt['user_id'] !== (int)$user['id']) {
            flash_set('error', 'Attempt not found.');
            redirect('/public/index.php?r=taxonomy_selector');
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
              t.name     AS topic_name,
              s.name     AS subject_name,
              m.code     AS module_code,
              l.code     AS level_code
            FROM attempt_questions aq
            JOIN questions q ON q.id = aq.question_id
            JOIN topics   t  ON t.id = q.topic_id
            JOIN subjects s  ON s.id = t.subject_id
            JOIN modules  m  ON m.id = s.module_id
            JOIN levels   l  ON l.id = m.level_id
            WHERE aq.attempt_id = :aid
            ORDER BY aq.position ASC
        ");
        $stmt->execute([':aid' => $attemptId]);
        $questions = $stmt->fetchAll() ?: [];

        $stmt = $db->prepare("
            SELECT
              t.id       AS topic_id,
              t.name     AS topic_name,
              s.name     AS subject_name,
              m.code     AS module_code,
              l.code     AS level_code,
              SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) AS correct_count,
              SUM(CASE WHEN aq.is_correct = 0 THEN 1 ELSE 0 END) AS wrong_count,
              COUNT(*) AS total
            FROM attempt_questions aq
            JOIN questions q ON q.id = aq.question_id
            JOIN topics   t  ON t.id = q.topic_id
            JOIN subjects s  ON s.id = t.subject_id
            JOIN modules  m  ON m.id = s.module_id
            JOIN levels   l  ON l.id = m.level_id
            WHERE aq.attempt_id = :aid
            GROUP BY t.id, t.name, s.name, m.code, l.code
            ORDER BY l.code, m.code, s.name, t.name
        ");
        $stmt->execute([':aid' => $attemptId]);
        $topicStats = $stmt->fetchAll() ?: [];

        render('quiz/review', [
            'title'      => 'Quiz Review',
            'attempt'    => $attempt,
            'questions'  => $questions,
            'topicStats' => $topicStats,
        ]);
    },

];
