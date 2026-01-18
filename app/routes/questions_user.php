<?php
declare(strict_types=1);

function timer_seconds_value($v): int {
    if ($v === null) return 0;
    if (is_int($v)) return $v;

    $s = trim((string)$v);
    if ($s === '') return 0;

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

    return (int)$s;
}

function attempt_id_from_request(): int {
    $aid = (int)($_GET['attempt_id'] ?? 0);
    if ($aid <= 0) $aid = (int)($_GET['id'] ?? 0);
    if ($aid <= 0) $aid = (int)($_POST['attempt_id'] ?? 0);
    if ($aid <= 0) $aid = (int)($_POST['id'] ?? 0);
    return $aid;
}

function is_ajax_request(): bool {
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strtolower($xrw) === 'xmlhttprequest') return true;

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return stripos($accept, 'application/json') !== false;
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
            $limit = (int)$numQuestions; // safe literal after clamp
            $sql = "SELECT id
                    FROM questions
                    WHERE status = 'active' AND topic_id IN ($in)
                    ORDER BY RAND()
                    LIMIT $limit";
            $stmt = $db->prepare($sql);
            $stmt->execute($topicIds);
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
                      (:uid, :mode, :timer, UTC_TIMESTAMP(), :total, 0, 0, 0, 'in_progress', UTC_TIMESTAMP(), UTC_TIMESTAMP())
                ");
                $stmt->execute([
                    'uid'   => (int)$user['id'],
                    'mode'  => $scoringMode,
                    'timer' => $timerSeconds,
                    'total' => $totalQuestions,
                ]);

                $attemptId = (int)$db->lastInsertId();

                $ins = $db->prepare("
                    INSERT INTO attempt_questions (attempt_id, question_id, marked_flag, created_at, updated_at)
                    VALUES (:aid, :qid, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                ");
                foreach ($questionIds as $qid) {
                    $ins->execute(['aid' => $attemptId, 'qid' => $qid]);
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

        // Fetch attempt plus elapsed using DB time (prevents PHP/MySQL timezone drift)
        $stmt = $db->prepare("
            SELECT
              a.*,
              GREATEST(0, TIMESTAMPDIFF(SECOND, a.started_at, UTC_TIMESTAMP())) AS elapsed_seconds
            FROM attempts a
            WHERE a.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $attemptId]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt || (int)$attempt['user_id'] !== (int)$user['id']) {
            flash_set('error', 'Attempt not found.');
            redirect('/public/index.php?r=quiz_start');
        }

        // If already submitted, go to result
        if (($attempt['status'] ?? '') === 'submitted' || !empty($attempt['submitted_at'])) {
            redirect('/public/index.php?r=quiz_result&attempt_id=' . $attemptId);
        }

        $timerSeconds = timer_seconds_value($attempt['timer_seconds'] ?? 0);
        $elapsed      = (int)($attempt['elapsed_seconds'] ?? 0);
        $remaining    = $timerSeconds - $elapsed;
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
                // Update selected_option and marked_flag.
                // We expect marked[aqid] to be '0' or '1' for every question.
                $stmtUpdate = $db->prepare("
                    UPDATE attempt_questions
                    SET selected_option = COALESCE(:sel, selected_option),
                        marked_flag     = :marked,
                        updated_at      = UTC_TIMESTAMP()
                    WHERE id = :id AND attempt_id = :aid
                ");

                foreach ($marked as $aqidStr => $markVal) {
                    $aqid = (int)$aqidStr;
                    if ($aqid <= 0) continue;

                    $rawSel = $answers[$aqidStr] ?? null;
                    $opt = null;

                    if ($rawSel !== null) {
                        $tmp = strtoupper(trim((string)$rawSel));
                        if (in_array($tmp, ['A','B','C','D'], true)) $opt = $tmp;
                    }

                    $isMarked = ((string)$markVal === '1') ? 1 : 0;

                    $stmtUpdate->execute([
                        'sel'    => $opt,       // null keeps existing via COALESCE
                        'marked' => $isMarked,
                        'id'     => $aqid,
                        'aid'    => $attemptId,
                    ]);
                }

                if ($submitQuiz) {
                    $stats = ['correct' => 0, 'wrong' => 0, 'score' => 0];

                    $stmtQ = $db->prepare("
                        SELECT aq.id, aq.selected_option, q.correct_option
                        FROM attempt_questions aq
                        JOIN questions q ON q.id = aq.question_id
                        WHERE aq.attempt_id = :aid
                        ORDER BY aq.id ASC
                    ");
                    $stmtQ->execute(['aid' => $attemptId]);
                    $rows = $stmtQ->fetchAll(PDO::FETCH_ASSOC) ?: [];

                    $updateQ = $db->prepare("
                        UPDATE attempt_questions
                        SET is_correct = :is_correct,
                            updated_at = UTC_TIMESTAMP()
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
                            'is_correct' => $isCorrect,
                            'id'         => (int)$row['id'],
                        ]);
                    }

                    $stats['score'] = ((string)$attempt['scoring_mode'] === 'negative')
                        ? ($stats['correct'] - $stats['wrong'])
                        : $stats['correct'];

                    $stmtU = $db->prepare("
                        UPDATE attempts
                        SET status       = 'submitted',
                            submitted_at = UTC_TIMESTAMP(),
                            raw_correct  = :c,
                            raw_wrong    = :w,
                            score        = :s,
                            updated_at   = UTC_TIMESTAMP()
                        WHERE id = :id
                    ");
                    $stmtU->execute([
                        'c'  => $stats['correct'],
                        'w'  => $stats['wrong'],
                        's'  => $stats['score'],
                        'id' => $attemptId,
                    ]);
                }

                $db->commit();

                if ($submitQuiz) {
                    redirect('/public/index.php?r=quiz_result&attempt_id=' . $attemptId);
                }

                // AJAX save should return JSON, not redirect (prevents stuck UI)
                if (is_ajax_request()) {
                    http_json(['ok' => true]);
                }

                flash_set('success', 'Saved.');
                redirect('/public/index.php?r=quiz_take&attempt_id=' . $attemptId);

            } catch (Throwable $e) {
                $db->rollBack();

                if (is_ajax_request()) {
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
        $stmt->execute(['aid' => $attemptId]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        render('quiz/take', [
            'title'            => 'Take Quiz',
            'attempt'          => $attempt,
            'questions'        => $questions,
            'remainingSeconds' => $remaining,
        ]);
    },

    'quiz_result' => function (PDO $db, array $config): void {
        $user = require_login($db);
        $attemptId = attempt_id_from_request();

        if ($attemptId <= 0) {
            flash_set('error', 'Invalid quiz attempt.');
            redirect('/public/index.php?r=quiz_start');
        }

        $stmt = $db->prepare("SELECT * FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
        $stmt->execute(['id' => $attemptId, 'uid' => (int)$user['id']]);
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$attempt) {
            flash_set('error', 'Quiz attempt not found.');
            redirect('/public/index.php?r=quiz_start');
        }

        if (($attempt['status'] ?? '') !== 'submitted' && empty($attempt['submitted_at'])) {
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
            ORDER BY aq.id ASC
        ");
        $stmt->execute(['aid' => $attemptId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byTopic = $bySubject = $byModule = [];

        foreach ($rows as $r) {
            $isCorrect = ((int)($r['is_correct'] ?? 0) === 1);

            $tid = (int)$r['topic_id'];
            if (!isset($byTopic[$tid])) $byTopic[$tid] = ['label' => $r['topic_name'], 'total' => 0, 'correct' => 0];
            $byTopic[$tid]['total']++;
            if ($isCorrect) $byTopic[$tid]['correct']++;

            $sid = (int)$r['subject_id'];
            if (!isset($bySubject[$sid])) $bySubject[$sid] = ['label' => $r['subject_name'], 'total' => 0, 'correct' => 0];
            $bySubject[$sid]['total']++;
            if ($isCorrect) $bySubject[$sid]['correct']++;

            $mid = (int)$r['module_id'];
            if (!isset($byModule[$mid])) $byModule[$mid] = ['label' => $r['module_code'], 'total' => 0, 'correct' => 0];
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
        $stmt->execute(['uid' => (int)$user['id']]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        render('reports/my_reports', [
            'title'   => 'My Reports',
            'user'    => $user,
            'reports' => $reports,
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
        $reason     = trim((string)($_POST['reason'] ?? ''));
        $details    = trim((string)($_POST['details'] ?? ''));

        if ($questionId <= 0 || $reason === '') {
            http_json(['ok' => false, 'error' => 'Question and reason are required.'], 422);
        }

        // Validate attempt_id belongs to user (optional linkage)
        if ($attemptId > 0) {
            $st = $db->prepare("SELECT id FROM attempts WHERE id = :id AND user_id = :uid LIMIT 1");
            $st->execute(['id' => $attemptId, 'uid' => (int)$user['id']]);
            if (!$st->fetch()) $attemptId = 0;
        }

        $stmt = $db->prepare("
            INSERT INTO question_reports (user_id, attempt_id, question_id, reason, details, status, created_at, updated_at)
            VALUES (:uid, :aid, :qid, :reason, :details, 'pending', UTC_TIMESTAMP(), UTC_TIMESTAMP())
        ");
        $stmt->execute([
            'uid'     => (int)$user['id'],
            'aid'     => $attemptId > 0 ? $attemptId : null,
            'qid'     => $questionId,
            'reason'  => $reason,
            'details' => $details !== '' ? $details : null,
        ]);

        http_json(['ok' => true]);
    },

];
