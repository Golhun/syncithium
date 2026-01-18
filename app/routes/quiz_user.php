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

    // CSRF check (expects csrf_field() to post the right token name)
    csrf_verify();

    // Topics
    $topicIds = $_POST['topic_ids'] ?? [];
    if (!is_array($topicIds)) {
        $topicIds = [];
    }

    $topicIds = array_values(array_unique(array_filter(
        array_map(static fn($v) => (int)$v, $topicIds),
        static fn($v) => $v > 0
    )));

    if (count($topicIds) === 0) {
        flash_set('error', 'Select at least one topic.');
        redirect('/public/index.php?r=taxonomy_selector');
    }

    // Options
    $numQuestions = (int)($_POST['num_questions'] ?? 20);
    if ($numQuestions < 1)   { $numQuestions = 1; }
    if ($numQuestions > 200) { $numQuestions = 200; }

    $scoringMode = (string)($_POST['scoring_mode'] ?? 'standard');
    if (!in_array($scoringMode, ['standard', 'negative'], true)) {
        $scoringMode = 'standard';
    }

    $timerSeconds = (int)($_POST['timer_seconds'] ?? 3600);
    $allowedTimers = [1800, 2700, 3600, 5400];
    if (!in_array($timerSeconds, $allowedTimers, true)) {
        $timerSeconds = 3600;
    }

    // Fetch random questions for selected topics
    // Important: use a validated integer in LIMIT to avoid PDO driver issues.
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

    // Create attempt + attempt_questions
    $totalQuestions = count($questionIds);

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
            ':total' => $totalQuestions,
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

];
