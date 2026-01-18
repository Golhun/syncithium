<?php
declare(strict_types=1);

function timer_seconds_value($v): int {
    // Handles INT seconds or MySQL TIME strings like "01:00:00"
    if ($v === null) return 0;

    if (is_int($v)) return $v;

    $s = trim((string)$v);
    if ($s === '') return 0;

    // numeric string
    if (ctype_digit($s)) return (int)$s;

    // HH:MM:SS or MM:SS
    if (preg_match('/^\d{1,3}:\d{2}(:\d{2})?$/', $s)) {
        $parts = array_map('intval', explode(':', $s));
        if (count($parts) === 2) {
            [$m, $sec] = $parts;
            return ($m * 60) + $sec;
        }
        [$h, $m, $sec] = $parts;
        return ($h * 3600) + ($m * 60) + $sec;
    }

    // fallback
    return (int)$s;
}

function attempt_id_from_request(): int {
    // Backward compatible: accept both attempt_id and id
    $aid = (int)($_GET['attempt_id'] ?? 0);
    if ($aid <= 0) $aid = (int)($_GET['id'] ?? 0);
    if ($aid <= 0) $aid = (int)($_POST['attempt_id'] ?? 0);
    if ($aid <= 0) $aid = (int)($_POST['id'] ?? 0);
    return $aid;
}

function is_xhr(): bool {
    $h = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return strtolower($h) === 'xmlhttprequest';
}

return [

    'quiz_start' => function (PDO $db, array $config): void {
        $user = require_login($db);

        if (is_post()) {
            csrf_verify();

            $topicIdsRaw   = $_POST['topic_ids'] ?? [];
            $numQuestions  = (int)($_POST['num_questions'] ?? 20);
            $scoringModeIn = (string)($_POST['scoring_mode'] ?? 'standard');
            $timerIn       = $_POST['timer_seconds'] ?? 3600;

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

            $scoringMode = ($scoringModeIn === 'negative') ? 'negative' : 'standard';

            // timer: accept INT seconds or TIME-ish input, clamp to 30–90 mins
            $timerSeconds = timer_seconds_value($timerIn);
            if ($timerSeconds < 1800) $timerSeconds = 1800;
            if ($timerSeconds > 5400) $timerSeconds = 5400;

            // Pull candidate questions
            $in = implode(',', array_fill(0, count($topicIds), '?'));
            $sql = "SELECT id FROM questions WHERE status = 'active' AND topic_id IN ($in) ORDER BY RAND() LIMIT ?";
            $stmt = $db->prepare($sql);
            $params = $topicIds;
            $params[] = $numQuestions;
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (!$rows) {
                flash_set('error', 'No active questions found for the selected topics.');
                redirect('/public/index.php?r=quiz_start');
            }

            $questionIds    = array_map(fn($r) => (int)$r['id'], $rows);
            $totalQuestions = count($questionIds);

            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    INSERT INTO attempts
                      (user_id, scoring_mode, timer_seconds, started_at, total_questions, score, raw_correct, raw_wrong, status, created_at, updated_at)
                    VALUES
                      (:uid, :mode, :timer, NOW(), :total, 0, 0, 0, 'in_progress', NOW(), NOW())
                ");
                $stmt->execute([
                    ':uid'   => (int)$user['id'],
                    ':mode'  => $scoringMode,
                    ':timer' => $timerSeconds,
                    ':total' => $totalQuestions,
                ]);

                $attemptId = (int)$db->lastInsertId();

                $ins = $db->prepare("
                    INSERT INTO attempt_questions (attempt_id, question_id, marked_flag, created_at, updated_at)
                    VALUES (:aid, :qid, 0, NOW(), NOW())
                ");
                foreach ($questionIds as $qid) {
                    $ins->execute([':aid' => $attemptId, ':qid' => $qid]);
                }

                $db->commit();

                redirect('/public/index.php?r=quiz_take&attempt_id=' . $attemptId);
            } catch (Throwable $e) {
                $db->rollBack();
                flash_set('error', 'Could not start quiz. Please try again.');
                redirect('/public/index.php?r=quiz_start');
            }
        }

        render('user/quiz_start', ['title' => 'Start quiz']);
    },

    'quiz_take' => function (PDO $db, array $config): void {
        $user = require_login($db);
        $attemptId = attempt_id_from_request();

        if ($attemptId <= 0) {
            flash_set('error', 'Invalid attempt.');
            redirect('/public/index.php?r=quiz_start');
        }

        $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $attemptId]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt || (int)$attempt['user_id'] !== (int)$user['id']) {
            flash_set('error', 'Attempt not found.');
            redirect('/public/index.php?r=quiz_start');
        }

        // If already submitted, go to result
        if (($attempt['status'] ?? '') === 'submitted' || !empty($attempt['submitted_at'])) {
            redirect('/public/index.php?r=quiz_result&attempt_id=' . $attemptId);
        }

        // Timer: compute elapsed using MySQL to avoid PHP/MySQL timezone drift
        $timerSeconds = timer_seconds_value($attempt['timer_seconds'] ?? 0);

        $st = $db->prepare("
            SELECT
              GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, NOW())) AS elapsed
            FROM attempts
            WHERE id = :id
            LIMIT 1
        ");
        $st->execute([':id' => $attemptId]);
        $elapsed = (int)($st->fetchColumn() ?? 0);

        $remaining = $timerSeconds - $elapsed;
        if ($remaining < 0) $remaining = 0;

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
                    if (!in_array($opt, ['A','B','C','D'], true)) $opt = null;

                    $isMarked = isset($marked[$aqid]) ? 1 : 0;

                    $stmtUpdate->execute([
                        ':sel'    => $opt,
                        ':marked' => $isMarked,
                        ':id'     => $aqid,
                        ':aid'    => $attemptId,
                    ]);
                }

                if ($submitQuiz) {
                    $stats = ['correct'=>0, 'wrong'=>0, 'score'=>0];

                $stmtQ = $db->prepare("
                    SELECT aq.id, aq.selected_option, q.correct_option
                    FROM attempt_questions aq
                    JOIN questions q ON q.id = aq.question_id
                    WHERE aq.attempt_id = ? 
                    ORDER BY aq.id ASC
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
                        if (!in_array($sel, ['A','B','C','D'], true)) $sel = '';

                        $correctOpt = strtoupper((string)$row['correct_option']);
                        $isCorrect = null;

                        if ($sel !== '') {
                            $isCorrect = ($sel === $correctOpt) ? 1 : 0;
                            if ($isCorrect === 1) $stats['correct']++;
                            else $stats['wrong']++;
                        }

                        $updateQ->execute([
                            ':is_correct' => $isCorrect,
                            ':id'         => (int)$row['id'],
                        ]);
                    }

                    $stats['score'] = ((string)$attempt['scoring_mode'] === 'negative')
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

                if (is_xhr()) {
                    http_json(['ok' => true, 'submitted' => $submitQuiz, 'remaining' => $remaining]);
                }

                if ($submitQuiz) {
                    redirect('/public/index.php?r=quiz_result&attempt_id=' . $attemptId);
                }

                flash_set('success', 'Saved.');
                redirect('/public/index.php?r=quiz_take&attempt_id=' . $attemptId);

            } catch (Throwable $e) {
                $db->rollBack();

                if (is_xhr()) {
                    http_json(['ok' => false, 'error' => $e->getMessage()], 500);
                }

                flash_set('error', 'Could not save quiz: ' . $e->getMessage());
                redirect('/public/index.php?r=quiz_take&attempt_id=' . $attemptId);
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
              q.explanation,
              t.name AS topic_name,
              t.id   AS topic_id,
              s.name AS subject_name,
              m.code AS module_code,
              l.code AS level_code
            FROM attempt_questions aq
            JOIN questions q ON q.id = aq.question_id
            JOIN topics   t  ON t.id = q.topic_id
            JOIN subjects s  ON s.id = t.subject_id
            JOIN modules  m  ON m.id = s.module_id
            JOIN levels   l  ON l.id = m.level_id
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

    // quiz_result, my_reports, question_report_create unchanged...
];
