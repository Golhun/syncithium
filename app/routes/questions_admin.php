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
  'admin_questions_import' => function (PDO $db, array $config) {
    $admin = require_admin($db);

    $results = [];
    $summary = [
      'created'   => 0,
      'duplicate' => 0,
      'error'     => 0,
      'skipped'   => 0,
    ];

    if (is_post()) {
      csrf_verify();

      // Optional: fallback topic from the dropdown if NO taxonomy columns are used
      $fallbackTopicId = (int)($_POST['topic_id'] ?? 0);
      if ($fallbackTopicId <= 0) {
        $fallbackTopicId = null;
      }

      if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        flash_set('error', 'Upload a CSV file.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      $content = file_get_contents($_FILES['csv']['tmp_name']) ?: '';
      $lines   = preg_split('/\r\n|\n|\r/', $content) ?: [];

      if (count($lines) === 0) {
        flash_set('error', 'CSV appears to be empty.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      // ------------- HEADER PARSING -------------
      $headerLine = array_shift($lines);

      // Detect delimiter: default comma, but if no comma and we see a tab, treat as TSV
      $delimiter = ',';
      if (strpos($headerLine, ',') === false && strpos($headerLine, "\t") !== false) {
        $delimiter = "\t";
      }

      $header = str_getcsv($headerLine, $delimiter);
      $header = array_map('trim', $header);

      // Map lowercased header names → index
      $idx = [];
      foreach ($header as $i => $name) {
        if ($name === '') continue;
        $idx[strtolower($name)] = $i;
      }

      // Helper: get field by header name
      $get = function (array $rawRow, string $name) use ($idx): string {
        $key = strtolower($name);
        if (!array_key_exists($key, $idx)) {
          return '';
        }
        $pos = $idx[$key];
        return isset($rawRow[$pos]) ? trim((string)$rawRow[$pos]) : '';
      };

      // ------------- TAXONOMY RESOLVER (INLINE) -------------
      $resolveTopicId = function (array $row) use ($db, $fallbackTopicId): ?int {
        $levelCode   = trim((string)($row['level_code'] ?? ''));
        $moduleCode  = trim((string)($row['module_code'] ?? ''));
        $subjectName = trim((string)($row['subject_name'] ?? ''));
        $topicName   = trim((string)($row['topic_name'] ?? ''));

        $hasCsvTaxonomy =
          $levelCode   !== '' ||
          $moduleCode  !== '' ||
          $subjectName !== '' ||
          $topicName   !== '';

        // If no taxonomy in CSV, fall back to UI topic selection
        if (!$hasCsvTaxonomy) {
          return $fallbackTopicId;
        }

        // If we have taxonomy but no topic name, we cannot map safely
        if ($topicName === '') {
          return $fallbackTopicId ?: null;
        }

        // Level
        $stmt = $db->prepare("SELECT id FROM levels WHERE code = :c LIMIT 1");
        $stmt->execute([':c' => $levelCode]);
        $level = $stmt->fetch();
        if ($level) {
          $levelId = (int)$level['id'];
        } else {
          $stmt = $db->prepare("INSERT INTO levels (code, name) VALUES (:c, :name)");
          $stmt->execute([':c' => $levelCode, ':name' => null]);
          $levelId = (int)$db->lastInsertId();
        }

        // Module
        $stmt = $db->prepare("SELECT id FROM modules WHERE level_id = :lid AND code = :code LIMIT 1");
        $stmt->execute([':lid' => $levelId, ':code' => $moduleCode]);
        $module = $stmt->fetch();
        if ($module) {
          $moduleId = (int)$module['id'];
        } else {
          $stmt = $db->prepare("INSERT INTO modules (level_id, code, name) VALUES (:lid, :code, :name)");
          $stmt->execute([':lid' => $levelId, ':code' => $moduleCode, ':name' => null]);
          $moduleId = (int)$db->lastInsertId();
        }

        // Subject
        $stmt = $db->prepare("SELECT id FROM subjects WHERE module_id = :mid AND name = :name LIMIT 1");
        $stmt->execute([':mid' => $moduleId, ':name' => $subjectName]);
        $subject = $stmt->fetch();
        if ($subject) {
          $subjectId = (int)$subject['id'];
        } else {
          $stmt = $db->prepare("INSERT INTO subjects (module_id, name) VALUES (:mid, :name)");
          $stmt->execute([':mid' => $moduleId, ':name' => $subjectName]);
          $subjectId = (int)$db->lastInsertId();
        }

        // Topic
        $stmt = $db->prepare("SELECT id FROM topics WHERE subject_id = :sid AND name = :name LIMIT 1");
        $stmt->execute([':sid' => $subjectId, ':name' => $topicName]);
        $topic = $stmt->fetch();
        if ($topic) {
          return (int)$topic['id'];
        }

        $stmt = $db->prepare("INSERT INTO topics (subject_id, name) VALUES (:sid, :name)");
        $stmt->execute([':sid' => $subjectId, ':name' => $topicName]);
        return (int)$db->lastInsertId();
      };

      // ------------- IMPORT LOOP -------------
      $db->beginTransaction();
      try {
        foreach ($lines as $lineNo => $line) {
          if (trim($line) === '') {
            $summary['skipped']++;
            $results[] = [
              'line'   => $lineNo + 2,
              'status' => 'skipped',
              'note'   => 'Blank line',
            ];
            continue;
          }

          $raw = str_getcsv($line, $delimiter);

          // Build logical row
          $row = [
            'level_code'   => $get($raw, 'level_code'),
            'module_code'  => $get($raw, 'module_code'),
            'subject_name' => $get($raw, 'subject_name'),
            'topic_name'   => $get($raw, 'topic_name'),

            'question_text'  => $get($raw, 'question_text'),
            'option_a'       => $get($raw, 'option_a'),
            'option_b'       => $get($raw, 'option_b'),
            'option_c'       => $get($raw, 'option_c'),
            'option_d'       => $get($raw, 'option_d'),
            'correct_option' => strtoupper($get($raw, 'correct_option')),
            'explanation'    => $get($raw, 'explanation'),
            'status'         => strtolower($get($raw, 'status')),
          ];

          $qt = $row['question_text'];
          $oa = $row['option_a'];
          $ob = $row['option_b'];
          $oc = $row['option_c'];
          $od = $row['option_d'];
          $correct = $row['correct_option'];

          // Basic required fields check
          if ($qt === '' || $oa === '' || $ob === '' || $oc === '' || $od === '') {
            $summary['error']++;
            $results[] = [
              'line'   => $lineNo + 2,
              'status' => 'error',
              'note'   => 'Missing required fields (question or options)',
            ];
            continue;
          }

          // Validate correct option strictly as A–D
          if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            $summary['error']++;
            $results[] = [
              'line'   => $lineNo + 2,
              'status' => 'error',
              'note'   => 'Invalid correct_option (must be A, B, C or D)',
            ];
            continue;
          }

          // Normalise status
          $status = $row['status'];
          if ($status !== 'inactive') {
            $status = 'active';
          }

          // Resolve topic_id using CSV taxonomy or fallback
          $topicId = $resolveTopicId($row);
          if (!$topicId) {
            $summary['error']++;
            $results[] = [
              'line'   => $lineNo + 2,
              'status' => 'error',
              'note'   => 'No topic mapping (CSV taxonomy missing and no topic selected)',
            ];
            continue;
          }

          // Compute hash for duplicate control, aligned with uq_questions_topic_hash
          $hashInput = mb_strtolower(trim($qt . '|' . $oa . '|' . $ob . '|' . $oc . '|' . $od));
          $hash      = sha1($hashInput);

          // Duplicate check: same topic + same question_hash
          $stmt = $db->prepare("SELECT id FROM questions WHERE topic_id = :tid AND question_hash = :hash LIMIT 1");
          $stmt->execute([
            ':tid'  => $topicId,
            ':hash' => $hash,
          ]);
          $existing = $stmt->fetch();

          if ($existing) {
            $summary['duplicate']++;
            $results[] = [
              'line'   => $lineNo + 2,
              'status' => 'duplicate',
              'note'   => 'Question already exists under this topic',
            ];
            continue;
          }

          // Insert into questions, including question_hash
          $stmt = $db->prepare("
            INSERT INTO questions (
              topic_id,
              question_text,
              option_a,
              option_b,
              option_c,
              option_d,
              correct_option,
              explanation,
              status,
              question_hash,
              created_at,
              updated_at
            ) VALUES (
              :topic_id,
              :question_text,
              :option_a,
              :option_b,
              :option_c,
              :option_d,
              :correct_option,
              :explanation,
              :status,
              :hash,
              NOW(),
              NOW()
            )
          ");

          $stmt->execute([
            ':topic_id'       => $topicId,
            ':question_text'  => $qt,
            ':option_a'       => $oa,
            ':option_b'       => $ob,
            ':option_c'       => $oc,
            ':option_d'       => $od,
            ':correct_option' => $correct,
            ':explanation'    => ($row['explanation'] === '' ? null : $row['explanation']),
            ':status'         => $status,
            ':hash'           => $hash,
          ]);

          $summary['created']++;
          $results[] = [
            'line'   => $lineNo + 2,
            'status' => 'created',
            'note'   => 'OK',
          ];
        }

        $db->commit();
        flash_set('success', 'Question import completed.');
      } catch (Throwable $e) {
        $db->rollback();
        $summary['error'] = max(1, $summary['error']);
        $results[] = [
          'line'   => -1,
          'status' => 'exception',
          'note'   => $e->getMessage(),
        ];
        flash_set('error', 'Import failed: ' . $e->getMessage());
      }
    }

    render('admin/questions_import', [
      'title'   => 'Import Questions',
      'results' => $results,
      'summary' => $summary,
    ]);
  },

];
