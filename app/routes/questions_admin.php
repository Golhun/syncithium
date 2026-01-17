<?php
declare(strict_types=1);

return [

  // -------------------------
  // Admin: Questions list
  // -------------------------
  'admin_questions' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    // POST actions: toggle status
    if (is_post()) {
      csrf_verify();
      $action = (string)($_POST['action'] ?? '');
      $qid = (int)($_POST['question_id'] ?? 0);

      if ($qid <= 0) {
        flash_set('error', 'Invalid question.');
        redirect('/public/index.php?r=admin_questions');
      }

      if ($action === 'toggle_status') {
        $stmt = $db->prepare("SELECT status FROM questions WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $qid]);
        $q = $stmt->fetch();
        if (!$q) {
          flash_set('error', 'Question not found.');
          redirect('/public/index.php?r=admin_questions');
        }

        $newStatus = ($q['status'] === 'active') ? 'inactive' : 'active';

        $stmt = $db->prepare("UPDATE questions SET status = :s WHERE id = :id");
        $stmt->execute([':s' => $newStatus, ':id' => $qid]);

        audit_log_event($db, (int)$admin['id'], 'QUESTION_STATUS_TOGGLE', 'questions', $qid, [
          'status' => $newStatus
        ]);

        flash_set('success', 'Status updated.');
        redirect('/public/index.php?r=admin_questions');
      }

      flash_set('error', 'Unknown action.');
      redirect('/public/index.php?r=admin_questions');
    }

    // Filters
    $status = strtolower(trim((string)($_GET['status'] ?? '')));
    if (!in_array($status, ['active','inactive',''], true)) $status = '';

    $qtext = trim((string)($_GET['q'] ?? ''));

    $topicId = (int)($_GET['topic_id'] ?? 0);

    $where = [];
    $params = [];

    if ($status !== '') {
      $where[] = "q.status = :st";
      $params[':st'] = $status;
    }
    if ($qtext !== '') {
      $where[] = "q.question_text LIKE :qt";
      $params[':qt'] = '%' . $qtext . '%';
    }
    if ($topicId > 0) {
      $where[] = "q.topic_id = :tid";
      $params[':tid'] = $topicId;
    }

    $whereSql = (count($where) > 0) ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $db->prepare("
      SELECT
        q.*,
        t.name AS topic_name,
        s.name AS subject_name,
        m.code AS module_code,
        l.code AS level_code
      FROM questions q
      JOIN topics t   ON t.id = q.topic_id
      JOIN subjects s ON s.id = t.subject_id
      JOIN modules m  ON m.id = s.module_id
      JOIN levels l   ON l.id = m.level_id
      {$whereSql}
      ORDER BY q.created_at DESC
      LIMIT 200
    ");
    $stmt->execute($params);
    $questions = $stmt->fetchAll() ?: [];

    render('admin/questions_index', [
      'title' => 'Question Bank',
      'admin' => $admin,
      'questions' => $questions,
      'filters' => [
        'status' => $status,
        'q' => $qtext,
        'topic_id' => $topicId,
      ],
    ]);
  },

  // -------------------------
  // Admin: Question edit
  // -------------------------
  'admin_question_edit' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
      flash_set('error', 'Invalid question.');
      redirect('/public/index.php?r=admin_questions');
    }

    $stmt = $db->prepare("SELECT * FROM questions WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $question = $stmt->fetch();

    if (!$question) {
      flash_set('error', 'Question not found.');
      redirect('/public/index.php?r=admin_questions');
    }

    if (is_post()) {
      csrf_verify();

      $qt = q_norm((string)($_POST['question_text'] ?? ''));
      $a  = q_norm((string)($_POST['option_a'] ?? ''));
      $b  = q_norm((string)($_POST['option_b'] ?? ''));
      $c  = q_norm((string)($_POST['option_c'] ?? ''));
      $d  = q_norm((string)($_POST['option_d'] ?? ''));
      $co = q_correct_option((string)($_POST['correct_option'] ?? ''));
      $ex = trim((string)($_POST['explanation'] ?? ''));
      $st = q_status((string)($_POST['status'] ?? 'active'));

      if ($qt === '' || $a === '' || $b === '' || $c === '' || $d === '') {
        flash_set('error', 'Question and all options are required.');
        redirect('/public/index.php?r=admin_question_edit&id=' . $id);
      }
      if (!$co) {
        flash_set('error', 'Correct option must be A, B, C, or D.');
        redirect('/public/index.php?r=admin_question_edit&id=' . $id);
      }

      $hash = q_hash($qt, $a, $b, $c, $d);
      $topicId = (int)$question['topic_id'];

      // Prevent duplicates under same topic (excluding this question)
      $stmt = $db->prepare("
        SELECT id FROM questions
        WHERE topic_id = :tid AND question_hash = :h AND id <> :id
        LIMIT 1
      ");
      $stmt->execute([':tid' => $topicId, ':h' => $hash, ':id' => $id]);
      if ($stmt->fetch()) {
        flash_set('error', 'Duplicate detected under this topic. Update aborted.');
        redirect('/public/index.php?r=admin_question_edit&id=' . $id);
      }

      $stmt = $db->prepare("
        UPDATE questions
        SET question_text = :qt,
            option_a = :a,
            option_b = :b,
            option_c = :c,
            option_d = :d,
            correct_option = :co,
            explanation = :ex,
            status = :st,
            question_hash = :h
        WHERE id = :id
      ");
      $stmt->execute([
        ':qt' => $qt,
        ':a' => $a, ':b' => $b, ':c' => $c, ':d' => $d,
        ':co' => $co,
        ':ex' => ($ex === '' ? null : $ex),
        ':st' => $st,
        ':h'  => $hash,
        ':id' => $id
      ]);

      audit_log_event($db, (int)$admin['id'], 'QUESTION_UPDATE', 'questions', $id);

      flash_set('success', 'Question updated.');
      redirect('/public/index.php?r=admin_question_edit&id=' . $id);
    }

    render('admin/question_edit', [
      'title' => 'Edit Question',
      'question' => $question,
    ]);
  },

  // -------------------------
  // Admin: Questions import (CSV)
  // -------------------------
  'admin_questions_import' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    $results = [];
    $summary = ['created' => 0, 'duplicate' => 0, 'error' => 0, 'skipped' => 0];

    if (is_post()) {
      csrf_verify();

      $chosenTopicId = (int)($_POST['topic_id'] ?? 0);

      if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        flash_set('error', 'Upload a CSV file.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      $fh = fopen($_FILES['csv']['tmp_name'], 'rb');
      if (!$fh) {
        flash_set('error', 'Could not read uploaded CSV.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      $header = fgetcsv($fh);
      if (!$header) {
        fclose($fh);
        flash_set('error', 'CSV appears empty.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

      $idx = function(string $key) use ($header) {
        $i = array_search($key, $header, true);
        return ($i === false) ? -1 : (int)$i;
      };

      $hasTaxonomy =
        $idx('level_code') >= 0 &&
        $idx('module_code') >= 0 &&
        $idx('subject_name') >= 0 &&
        $idx('topic_name') >= 0;

      $reqCols = ['question_text','option_a','option_b','option_c','option_d','correct_option'];
      foreach ($reqCols as $c) {
        if ($idx($c) < 0) {
          fclose($fh);
          flash_set('error', 'CSV header must include: ' . implode(',', $reqCols) . ' (plus optional explanation,status, and optional taxonomy columns).');
          redirect('/public/index.php?r=admin_questions_import');
        }
      }

      if (!$hasTaxonomy && $chosenTopicId <= 0) {
        fclose($fh);
        flash_set('error', 'Choose a target topic, or include taxonomy columns in the CSV.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      $ins = $db->prepare("
        INSERT INTO questions
          (topic_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, status, question_hash)
        VALUES
          (:tid, :qt, :a, :b, :c, :d, :co, :ex, :st, :h)
      ");

      $lineNo = 1; // header line already read
      while (($row = fgetcsv($fh)) !== false) {
        $lineNo++;

        // Skip blank lines
        $joined = implode('', $row);
        if (trim($joined) === '') { $summary['skipped']++; continue; }

        $topicId = $chosenTopicId;

        if ($hasTaxonomy) {
          $lc = trim((string)($row[$idx('level_code')] ?? ''));
          $mc = trim((string)($row[$idx('module_code')] ?? ''));
          $sn = trim((string)($row[$idx('subject_name')] ?? ''));
          $tn = trim((string)($row[$idx('topic_name')] ?? ''));

          $topicId = q_resolve_topic_id($db, $lc, $mc, $sn, $tn);
          if ($topicId <= 0) {
            $summary['error']++;
            $results[] = ['line' => $lineNo, 'status' => 'error', 'note' => 'Taxonomy not found for row'];
            continue;
          }
        }

        $qt = q_norm((string)($row[$idx('question_text')] ?? ''));
        $a  = q_norm((string)($row[$idx('option_a')] ?? ''));
        $b  = q_norm((string)($row[$idx('option_b')] ?? ''));
        $c  = q_norm((string)($row[$idx('option_c')] ?? ''));
        $d  = q_norm((string)($row[$idx('option_d')] ?? ''));
        $co = q_correct_option((string)($row[$idx('correct_option')] ?? ''));

        $ex = '';
        if ($idx('explanation') >= 0) $ex = trim((string)($row[$idx('explanation')] ?? ''));

        $st = 'active';
        if ($idx('status') >= 0) $st = q_status((string)($row[$idx('status')] ?? 'active'));

        if ($qt === '' || $a === '' || $b === '' || $c === '' || $d === '' || !$co) {
          $summary['error']++;
          $results[] = ['line' => $lineNo, 'status' => 'error', 'note' => 'Missing required fields or invalid correct_option'];
          continue;
        }

        $h = q_hash($qt, $a, $b, $c, $d);

        try {
          $ins->execute([
            ':tid' => $topicId,
            ':qt'  => $qt,
            ':a'   => $a, ':b' => $b, ':c' => $c, ':d' => $d,
            ':co'  => $co,
            ':ex'  => ($ex === '' ? null : $ex),
            ':st'  => $st,
            ':h'   => $h,
          ]);

          $newId = (int)$db->lastInsertId();
          audit_log_event($db, (int)$admin['id'], 'QUESTION_CREATE', 'questions', $newId, [
            'topic_id' => $topicId,
            'source' => 'csv_import'
          ]);

          $summary['created']++;
          $results[] = ['line' => $lineNo, 'status' => 'created', 'note' => 'OK'];
        } catch (PDOException $e) {
          // Duplicate key -> treat as duplicate
          $code = (string)$e->getCode();
          if ($code === '23000') {
            $summary['duplicate']++;
            $results[] = ['line' => $lineNo, 'status' => 'duplicate', 'note' => 'Hash duplicate under topic'];
          } else {
            $summary['error']++;
            $results[] = ['line' => $lineNo, 'status' => 'error', 'note' => 'DB error'];
          }
        }
      }

      fclose($fh);

      audit_log_event($db, (int)$admin['id'], 'QUESTION_IMPORT', 'questions', null, $summary);

      flash_set('success', "Import complete. Created: {$summary['created']}, Duplicates: {$summary['duplicate']}, Errors: {$summary['error']}.");
    }

    render('admin/questions_import', [
      'title' => 'Import Questions',
      'results' => $results,
      'summary' => $summary,
    ]);
  },

];
