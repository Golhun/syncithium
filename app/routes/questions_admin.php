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

      if ($action === 'bulk_delete') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids) && !empty($ids)) {
          $ids = array_map('intval', $ids);
          // Filter out invalid IDs
          $ids = array_filter($ids, fn($i) => $i > 0);
          if (!empty($ids)) {
            $inQuery = implode(',', $ids);
            $db->exec("DELETE FROM questions WHERE id IN ($inQuery)");
            audit_log_event($db, (int)$admin['id'], 'QUESTION_BULK_DELETE', 'questions', 0, ['count' => count($ids)]);
            flash_set('success', count($ids) . ' questions deleted.');
          }
        }
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
      LIMIT 1000
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

    $stmt = $db->prepare("
      SELECT q.*, t.name AS topic_name, s.name AS subject_name, m.code AS module_code, l.code AS level_code
      FROM questions q
      JOIN topics t ON t.id = q.topic_id
      JOIN subjects s ON s.id = t.subject_id
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      WHERE q.id = :id
      LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
      flash_set('error', 'Question not found.');
      redirect('/public/index.php?r=admin_questions');
    }

    if (is_post()) {
      csrf_verify();

      $qt = trim((string)($_POST['question_text'] ?? ''));
      $a  = trim((string)($_POST['option_a'] ?? ''));
      $b  = trim((string)($_POST['option_b'] ?? ''));
      $c  = trim((string)($_POST['option_c'] ?? ''));
      $d  = trim((string)($_POST['option_d'] ?? ''));

      $coRaw = strtoupper(trim((string)($_POST['correct_option'] ?? '')));
      $co = in_array($coRaw, ['A','B','C','D'], true) ? $coRaw : '';

      $ex = trim((string)($_POST['explanation'] ?? ''));
      $st = (strtolower(trim((string)($_POST['status'] ?? ''))) === 'inactive') ? 'inactive' : 'active';

      if ($qt === '' || $a === '' || $b === '' || $c === '' || $d === '') {
        flash_set('error', 'Question and all options are required.');
        redirect('/public/index.php?r=admin_question_edit&id=' . $id);
      }
      if (!$co) {
        flash_set('error', 'Correct option must be A, B, C, or D.');
        redirect('/public/index.php?r=admin_question_edit&id=' . $id);
      }

      // Hash for duplicates (topic scoped)
      $hash = sha1(mb_strtolower(trim($qt . '|' . $a . '|' . $b . '|' . $c . '|' . $d)));
      $topicId = (int)$row['topic_id'];

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

    render('admin/questions_edit', [
      'title' => 'Edit Question',
      'row' => $row,
    ]);
  },

  // -------------------------
  // Admin: Question delete
  // -------------------------
  'admin_question_delete' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    $id = (int)($_POST['id'] ?? 0);

    if (is_post()) {
      csrf_verify();

      if ($id <= 0) {
        flash_set('error', 'Invalid question ID.');
        redirect('/public/index.php?r=admin_questions');
      }

      // Confirm question exists before attempting deletion
      $stmt = $db->prepare("SELECT id FROM questions WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $id]);
      if (!$stmt->fetch()) {
        flash_set('error', 'Question not found.');
        redirect('/public/index.php?r=admin_questions');
      }

      $stmt = $db->prepare("DELETE FROM questions WHERE id = :id");
      $stmt->execute([':id' => $id]);

      audit_log_event($db, (int)$admin['id'], 'QUESTION_DELETE', 'questions', $id);

      flash_set('success', 'Question deleted.');
      redirect('/public/index.php?r=admin_questions');
    }

    flash_set('error', 'Invalid request.');
    redirect('/public/index.php?r=admin_questions');

  },

  // -------------------------
  // Admin: Download import template (CSV)
  // Strictly matches DB taxonomy using IDs
  // -------------------------
  'admin_questions_import_template' => function (PDO $db, array $config): void {
    $admin = require_admin($db);

    $levelId   = (int)($_GET['level_id'] ?? 0);
    $moduleId  = (int)($_GET['module_id'] ?? 0);
    $subjectId = (int)($_GET['subject_id'] ?? 0);
    $topicId   = (int)($_GET['topic_id'] ?? 0);
    $scope     = strtolower(trim((string)($_GET['scope'] ?? 'subject'))); // subject|topic
    if (!in_array($scope, ['subject','topic'], true)) $scope = 'subject';

    if ($scope === 'topic' && $topicId <= 0) {
      flash_set('error', 'Select a topic to generate a topic-only template.');
      redirect('/public/index.php?r=admin_questions_import');
    }
    if ($scope === 'subject' && $subjectId <= 0) {
      flash_set('error', 'Select a subject to generate a template.');
      redirect('/public/index.php?r=admin_questions_import');
    }

    // Fetch rows to include
    if ($scope === 'topic') {
      $stmt = $db->prepare("
        SELECT
          l.id AS level_id, l.code AS level_code,
          m.id AS module_id, m.code AS module_code,
          s.id AS subject_id, s.name AS subject_name,
          t.id AS topic_id, t.name AS topic_name
        FROM topics t
        JOIN subjects s ON s.id = t.subject_id
        JOIN modules m  ON m.id = s.module_id
        JOIN levels l   ON l.id = m.level_id
        WHERE t.id = :tid
        LIMIT 1
      ");
      $stmt->execute([':tid' => $topicId]);
      $rows = $stmt->fetchAll() ?: [];
    } else {
      $stmt = $db->prepare("
        SELECT
          l.id AS level_id, l.code AS level_code,
          m.id AS module_id, m.code AS module_code,
          s.id AS subject_id, s.name AS subject_name,
          t.id AS topic_id, t.name AS topic_name
        FROM topics t
        JOIN subjects s ON s.id = t.subject_id
        JOIN modules m  ON m.id = s.module_id
        JOIN levels l   ON l.id = m.level_id
        WHERE s.id = :sid
        ORDER BY t.name ASC
      ");
      $stmt->execute([':sid' => $subjectId]);
      $rows = $stmt->fetchAll() ?: [];
    }

    if (empty($rows)) {
      flash_set('error', 'No matching taxonomy found to generate a template.');
      redirect('/public/index.php?r=admin_questions_import');
    }

    // Optional: validate hierarchy if passed
    $first = $rows[0];
    if ($levelId > 0 && (int)$first['level_id'] !== $levelId) {
      flash_set('error', 'Selected level does not match the chosen subject/topic.');
      redirect('/public/index.php?r=admin_questions_import');
    }
    if ($moduleId > 0 && (int)$first['module_id'] !== $moduleId) {
      flash_set('error', 'Selected module does not match the chosen subject/topic.');
      redirect('/public/index.php?r=admin_questions_import');
    }
    if ($subjectId > 0 && (int)$first['subject_id'] !== $subjectId) {
      flash_set('error', 'Selected subject does not match the chosen topic.');
      redirect('/public/index.php?r=admin_questions_import');
    }

    $filename = 'questions_template_L' . ($first['level_code'] ?? 'X')
      . '_M' . ($first['module_code'] ?? 'X')
      . '_S' . preg_replace('/[^a-zA-Z0-9]+/', '-', (string)($first['subject_name'] ?? 'subject'))
      . '_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    // Header columns: IDs + names, then question fields
    $header = [
      'level_id','level_code',
      'module_id','module_code',
      'subject_id','subject_name',
      'topic_id','topic_name',
      'question_text','option_a','option_b','option_c','option_d',
      'correct_option','explanation','status'
    ];
    fputcsv($out, $header);

    // Prefilled taxonomy rows, blank question fields
    foreach ($rows as $r) {
      fputcsv($out, [
        (string)$r['level_id'], (string)$r['level_code'],
        (string)$r['module_id'], (string)$r['module_code'],
        (string)$r['subject_id'], (string)$r['subject_name'],
        (string)$r['topic_id'], (string)$r['topic_name'],
        '', '', '', '', '',
        '', '', ''
      ]);
    }

    fclose($out);
    exit;
  },

  // -------------------------
  // Admin: Questions import (CSV)
  // Improved: strict taxonomy by default, uses topic_id when present
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

      // Optional fallback topic if CSV does not contain topic_id
      $fallbackTopicId = (int)($_POST['topic_id'] ?? 0);
      if ($fallbackTopicId <= 0) {
        $fallbackTopicId = null;
      }

      // Allow creating missing taxonomy only if explicitly requested (default off)
      $allowCreateTaxonomy = ((int)($_POST['allow_create_taxonomy'] ?? 0) === 1);

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

      // HEADER PARSING
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

      $get = function (array $rawRow, string $name) use ($idx): string {
        $key = strtolower($name);
        if (!array_key_exists($key, $idx)) return '';
        $pos = $idx[$key];
        return isset($rawRow[$pos]) ? trim((string)$rawRow[$pos]) : '';
      };

      // Resolver: topic_id first, then (optional) strict lookup by taxonomy names/codes,
      // and only creates missing items if allow_create_taxonomy=1.
      $resolveTopicId = function (array $row) use ($db, $fallbackTopicId, $allowCreateTaxonomy): ?int {
        // 1) Preferred: topic_id from CSV (strict, DB-matched)
        $topicId = (int)($row['topic_id'] ?? 0);
        if ($topicId > 0) {
          $stmt = $db->prepare("SELECT id FROM topics WHERE id = :id LIMIT 1");
          $stmt->execute([':id' => $topicId]);
          return $stmt->fetch() ? $topicId : null;
        }

        // 2) If no topic_id, try taxonomy mapping by code/name (strict lookup by default)
        $levelCode   = trim((string)($row['level_code'] ?? ''));
        $moduleCode  = trim((string)($row['module_code'] ?? ''));
        $subjectName = trim((string)($row['subject_name'] ?? ''));
        $topicName   = trim((string)($row['topic_name'] ?? ''));

        $hasTaxonomy =
          $levelCode !== '' ||
          $moduleCode !== '' ||
          $subjectName !== '' ||
          $topicName !== '';

        if (!$hasTaxonomy) {
          return $fallbackTopicId;
        }

        if ($topicName === '') {
          return $fallbackTopicId ?: null;
        }

        // Lookups
        $stmt = $db->prepare("SELECT id FROM levels WHERE code = :c LIMIT 1");
        $stmt->execute([':c' => $levelCode]);
        $level = $stmt->fetch();
        if (!$level) {
          if (!$allowCreateTaxonomy) return null;
          $stmt = $db->prepare("INSERT INTO levels (code, name) VALUES (:c, :name)");
          $stmt->execute([':c' => $levelCode, ':name' => null]);
          $levelId = (int)$db->lastInsertId();
        } else {
          $levelId = (int)$level['id'];
        }

        $stmt = $db->prepare("SELECT id FROM modules WHERE level_id = :lid AND code = :code LIMIT 1");
        $stmt->execute([':lid' => $levelId, ':code' => $moduleCode]);
        $module = $stmt->fetch();
        if (!$module) {
          if (!$allowCreateTaxonomy) return null;
          $stmt = $db->prepare("INSERT INTO modules (level_id, code, name) VALUES (:lid, :code, :name)");
          $stmt->execute([':lid' => $levelId, ':code' => $moduleCode, ':name' => null]);
          $moduleId = (int)$db->lastInsertId();
        } else {
          $moduleId = (int)$module['id'];
        }

        $stmt = $db->prepare("SELECT id FROM subjects WHERE module_id = :mid AND name = :name LIMIT 1");
        $stmt->execute([':mid' => $moduleId, ':name' => $subjectName]);
        $subject = $stmt->fetch();
        if (!$subject) {
          if (!$allowCreateTaxonomy) return null;
          $stmt = $db->prepare("INSERT INTO subjects (module_id, name) VALUES (:mid, :name)");
          $stmt->execute([':mid' => $moduleId, ':name' => $subjectName]);
          $subjectId = (int)$db->lastInsertId();
        } else {
          $subjectId = (int)$subject['id'];
        }

        $stmt = $db->prepare("SELECT id FROM topics WHERE subject_id = :sid AND name = :name LIMIT 1");
        $stmt->execute([':sid' => $subjectId, ':name' => $topicName]);
        $topic = $stmt->fetch();
        if ($topic) return (int)$topic['id'];

        if (!$allowCreateTaxonomy) return null;

        $stmt = $db->prepare("INSERT INTO topics (subject_id, name) VALUES (:sid, :name)");
        $stmt->execute([':sid' => $subjectId, ':name' => $topicName]);
        return (int)$db->lastInsertId();
      };

      // IMPORT LOOP
      $db->beginTransaction();
      try {
        foreach ($lines as $lineNo => $line) {
          if (trim($line) === '') {
            $summary['skipped']++;
            $results[] = ['line' => $lineNo + 2, 'status' => 'skipped', 'note' => 'Blank line'];
            continue;
          }

          $raw = str_getcsv($line, $delimiter);

          $row = [
            // IDs (preferred for strict matching)
            'topic_id' => $get($raw, 'topic_id'),

            // Optional taxonomy labels/codes (fallback mapping only)
            'level_code'   => $get($raw, 'level_code'),
            'module_code'  => $get($raw, 'module_code'),
            'subject_name' => $get($raw, 'subject_name'),
            'topic_name'   => $get($raw, 'topic_name'),

            // Question fields
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
          $expl = $row['explanation'];
          $stRaw = $row['status'];

          // Template-safe skip: if question fields are all blank, ignore row even if taxonomy is filled
          $allQuestionFieldsBlank =
            trim($qt) === '' &&
            trim($oa) === '' &&
            trim($ob) === '' &&
            trim($oc) === '' &&
            trim($od) === '' &&
            trim($correct) === '' &&
            trim($expl) === '' &&
            trim($stRaw) === '';

          if ($allQuestionFieldsBlank) {
            $summary['skipped']++;
            $results[] = ['line' => $lineNo + 2, 'status' => 'skipped', 'note' => 'Template/blank row'];
            continue;
          }

          // Required fields
          if ($qt === '' || $oa === '' || $ob === '' || $oc === '' || $od === '') {
            $summary['error']++;
            $results[] = ['line' => $lineNo + 2, 'status' => 'error', 'note' => 'Missing required fields (question or options)'];
            continue;
          }

          // Correct option validation
          if (!in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            $summary['error']++;
            $results[] = ['line' => $lineNo + 2, 'status' => 'error', 'note' => 'Invalid correct_option (must be A, B, C or D)'];
            continue;
          }

          // Normalise status
          $status = ($stRaw === 'inactive') ? 'inactive' : 'active';

          // Resolve topic
          $topicId = $resolveTopicId($row);
          if (!$topicId) {
            $summary['error']++;
            $results[] = [
              'line'   => $lineNo + 2,
              'status' => 'error',
              'note'   => $allowCreateTaxonomy
                ? 'Topic mapping failed. Check taxonomy values or IDs.'
                : 'Topic mapping failed. Use the generated template so topic_id matches your DB (recommended).'
            ];
            continue;
          }

          // Hash for duplicates (topic scoped)
          $hashInput = mb_strtolower(trim($qt . '|' . $oa . '|' . $ob . '|' . $oc . '|' . $od));
          $hash      = sha1($hashInput);

          $stmt = $db->prepare("SELECT id FROM questions WHERE topic_id = :tid AND question_hash = :hash LIMIT 1");
          $stmt->execute([':tid' => $topicId, ':hash' => $hash]);
          if ($stmt->fetch()) {
            $summary['duplicate']++;
            $results[] = ['line' => $lineNo + 2, 'status' => 'duplicate', 'note' => 'Question already exists under this topic'];
            continue;
          }

          // Insert
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
            ':explanation'    => ($expl === '' ? null : $expl),
            ':status'         => $status,
            ':hash'           => $hash,
          ]);

          $summary['created']++;
          $results[] = ['line' => $lineNo + 2, 'status' => 'created', 'note' => 'OK'];
        }

        $db->commit();
        flash_set('success', 'Question import completed.');
      } catch (Throwable $e) {
        $db->rollback();
        $summary['error'] = max(1, $summary['error']);
        $results[] = ['line' => -1, 'status' => 'exception', 'note' => $e->getMessage()];
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
