<?php
declare(strict_types=1);

function normalize_question_text(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
  return mb_strtolower($s);
}

function question_hash_for(int $topicId, string $questionText): string {
  return hash('sha256', $topicId . '|' . normalize_question_text($questionText));
}

function clean_cell(string $s): string {
  $s = trim($s);
  // Keep data clean for DB and UI rendering
  $s = str_replace(["\r\n", "\r"], "\n", $s);
  return $s;
}

function parse_correct_option(string $v): ?string {
  $v = strtoupper(trim($v));
  if (in_array($v, ['A','B','C','D'], true)) return $v;

  // allow "option_a" etc
  if ($v === 'OPTION_A') return 'A';
  if ($v === 'OPTION_B') return 'B';
  if ($v === 'OPTION_C') return 'C';
  if ($v === 'OPTION_D') return 'D';

  return null;
}

return [

  // List + search
  'admin_questions' => function(PDO $db, array $config): void {
    $admin = require_admin($db);

    $q = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));
    if (!in_array($status, ['', 'active', 'inactive'], true)) $status = '';

    $sql = "
      SELECT q.id, q.topic_id, q.question_text, q.correct_option, q.status, q.updated_at,
             t.name AS topic_name,
             s.name AS subject_name,
             m.code AS module_code,
             l.code AS level_code
      FROM questions q
      JOIN topics t ON t.id = q.topic_id
      JOIN subjects s ON s.id = t.subject_id
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      WHERE 1=1
    ";

    $params = [];

    if ($status !== '') {
      $sql .= " AND q.status = :status";
      $params[':status'] = $status;
    }

    if ($q !== '') {
      $sql .= " AND (q.question_text LIKE :q OR t.name LIKE :q OR s.name LIKE :q OR m.code LIKE :q OR l.code LIKE :q)";
      $params[':q'] = "%{$q}%";
    }

    $sql .= " ORDER BY q.updated_at DESC LIMIT 300";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    render('admin/questions_index', [
      'title' => 'Question Bank',
      'admin' => $admin,
      'rows' => $rows,
      'q' => $q,
      'status' => $status,
    ]);
  },

  // Import
  'admin_questions_import' => function(PDO $db, array $config): void {
    $admin = require_admin($db);

    // Preload topics for "select topic then import" mode (optional UX)
    $stmt = $db->prepare("
      SELECT t.id, t.name AS topic_name, s.name AS subject_name, m.code AS module_code, l.code AS level_code
      FROM topics t
      JOIN subjects s ON s.id = t.subject_id
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code, s.name, t.name
      LIMIT 2000
    ");
    $stmt->execute();
    $topics = $stmt->fetchAll();

    $results = [];
    $summary = null;

    if (is_post()) {
      csrf_verify();

      $mode = (string)($_POST['mode'] ?? 'taxonomy_in_csv'); // taxonomy_in_csv | select_topic
      $selectedTopicId = (int)($_POST['topic_id'] ?? 0);

      if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        flash_set('error', 'Upload a CSV file.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      // Basic file upload safety: enforce size and extension
      $maxBytes = (int)($config['uploads']['max_csv_bytes'] ?? (2 * 1024 * 1024));
      if ((int)($_FILES['csv']['size'] ?? 0) > $maxBytes) {
        flash_set('error', 'CSV is too large.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      $name = (string)($_FILES['csv']['name'] ?? '');
      if (!preg_match('/\.csv$/i', $name)) {
        flash_set('error', 'Only .csv files are allowed.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      if ($mode === 'select_topic' && $selectedTopicId <= 0) {
        flash_set('error', 'Select a Topic for this import.');
        redirect('/public/index.php?r=admin_questions_import');
      }

      $fh = fopen($_FILES['csv']['tmp_name'], 'rb');
      if ($fh === false) {
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

      $idx = fn(string $col) => array_search($col, $header, true);

      $neededCols = ['question_text','option_a','option_b','option_c','option_d','correct_option'];
      foreach ($neededCols as $c) {
        if ($idx($c) === false) {
          fclose($fh);
          flash_set('error', 'CSV header must include: question_text, option_a, option_b, option_c, option_d, correct_option');
          redirect('/public/index.php?r=admin_questions_import');
        }
      }

      $hasTaxonomy = (
        $idx('level_code') !== false &&
        $idx('module_code') !== false &&
        $idx('subject_name') !== false &&
        $idx('topic_name') !== false
      );

      if ($mode === 'taxonomy_in_csv' && !$hasTaxonomy) {
        fclose($fh);
        flash_set('error', 'For taxonomy-in-CSV mode, include columns: level_code, module_code, subject_name, topic_name');
        redirect('/public/index.php?r=admin_questions_import');
      }

      $created = 0;
      $duplicates = 0;
      $skipped = 0;

      $db->beginTransaction();

      try {
        while (($row = fgetcsv($fh)) !== false) {
          // Skip blank lines
          if (count($row) === 1 && trim((string)$row[0]) === '') continue;

          $questionText = clean_cell((string)($row[$idx('question_text')] ?? ''));
          $a = clean_cell((string)($row[$idx('option_a')] ?? ''));
          $b = clean_cell((string)($row[$idx('option_b')] ?? ''));
          $c = clean_cell((string)($row[$idx('option_c')] ?? ''));
          $d = clean_cell((string)($row[$idx('option_d')] ?? ''));
          $correctRaw = (string)($row[$idx('correct_option')] ?? '');
          $correct = parse_correct_option($correctRaw);
          $explanation = $idx('explanation') !== false ? clean_cell((string)($row[$idx('explanation')] ?? '')) : '';
          $status = $idx('status') !== false ? strtolower(trim((string)($row[$idx('status')] ?? 'active'))) : 'active';
          if (!in_array($status, ['active','inactive'], true)) $status = 'active';

          if ($questionText === '' || $a === '' || $b === '' || $c === '' || $d === '' || !$correct) {
            $skipped++;
            continue;
          }

          // Determine topic_id
          $topicId = $selectedTopicId;

          if ($mode === 'taxonomy_in_csv') {
            $levelCode = trim((string)($row[$idx('level_code')] ?? ''));
            $moduleCode = trim((string)($row[$idx('module_code')] ?? ''));
            $subjectName = trim((string)($row[$idx('subject_name')] ?? ''));
            $topicName = trim((string)($row[$idx('topic_name')] ?? ''));

            if ($levelCode === '' || $moduleCode === '' || $subjectName === '' || $topicName === '') {
              $skipped++;
              continue;
            }

            // resolve topic via joins
            $stmt = $db->prepare("
              SELECT t.id
              FROM topics t
              JOIN subjects s ON s.id = t.subject_id
              JOIN modules m ON m.id = s.module_id
              JOIN levels l ON l.id = m.level_id
              WHERE l.code = :lc AND m.code = :mc AND s.name = :sn AND t.name = :tn
              LIMIT 1
            ");
            $stmt->execute([
              ':lc' => $levelCode,
              ':mc' => $moduleCode,
              ':sn' => $subjectName,
              ':tn' => $topicName,
            ]);
            $t = $stmt->fetch();
            if (!$t) {
              $skipped++;
              continue;
            }
            $topicId = (int)$t['id'];
          }

          $qh = question_hash_for($topicId, $questionText);

          try {
            $stmt = $db->prepare("
              INSERT INTO questions
                (topic_id, question_hash, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, status)
              VALUES
                (:tid, :qh, :qt, :a, :b, :c, :d, :co, :ex, :st)
            ");
            $stmt->execute([
              ':tid' => $topicId,
              ':qh'  => $qh,
              ':qt'  => $questionText,
              ':a'   => $a,
              ':b'   => $b,
              ':c'   => $c,
              ':d'   => $d,
              ':co'  => $correct,
              ':ex'  => ($explanation === '' ? null : $explanation),
              ':st'  => $status,
            ]);

            $newId = (int)$db->lastInsertId();
            audit_log_event($db, (int)$admin['id'], 'QUESTION_CREATE', 'questions', $newId, [
              'topic_id' => $topicId,
              'mode' => $mode
            ]);

            $created++;
          } catch (PDOException $e) {
            // duplicate key (topic_id, question_hash)
            $duplicates++;
          }
        }

        fclose($fh);
        $db->commit();

        $summary = [
          'created' => $created,
          'duplicates' => $duplicates,
          'skipped' => $skipped
        ];

        flash_set('success', "Import complete. Created {$created}, duplicates {$duplicates}, skipped {$skipped}.");
      } catch (Throwable $e) {
        fclose($fh);
        $db->rollBack();
        flash_set('error', 'Import failed. Please validate your CSV and try again.');
      }
    }

    render('admin/questions_import', [
      'title' => 'Import Questions',
      'topics' => $topics,
      'results' => $results,
      'summary' => $summary,
    ]);
  },

  // Edit
  'admin_questions_edit' => function(PDO $db, array $config): void {
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

      $qt = clean_cell((string)($_POST['question_text'] ?? ''));
      $a  = clean_cell((string)($_POST['option_a'] ?? ''));
      $b  = clean_cell((string)($_POST['option_b'] ?? ''));
      $c  = clean_cell((string)($_POST['option_c'] ?? ''));
      $d  = clean_cell((string)($_POST['option_d'] ?? ''));
      $co = parse_correct_option((string)($_POST['correct_option'] ?? ''));
      $ex = clean_cell((string)($_POST['explanation'] ?? ''));
      $st = strtolower(trim((string)($_POST['status'] ?? 'active')));
      if (!in_array($st, ['active','inactive'], true)) $st = 'active';

      if ($qt === '' || $a === '' || $b === '' || $c === '' || $d === '' || !$co) {
        flash_set('error', 'All fields except explanation are required, and correct option must be A, B, C, or D.');
        redirect('/public/index.php?r=admin_questions_edit&id=' . $id);
      }

      $topicId = (int)$row['topic_id'];
      $qh = question_hash_for($topicId, $qt);

      try {
        $stmt = $db->prepare("
          UPDATE questions
          SET question_hash = :qh,
              question_text = :qt,
              option_a = :a,
              option_b = :b,
              option_c = :c,
              option_d = :d,
              correct_option = :co,
              explanation = :ex,
              status = :st
          WHERE id = :id
        ");
        $stmt->execute([
          ':qh' => $qh,
          ':qt' => $qt,
          ':a'  => $a,
          ':b'  => $b,
          ':c'  => $c,
          ':d'  => $d,
          ':co' => $co,
          ':ex' => ($ex === '' ? null : $ex),
          ':st' => $st,
          ':id' => $id
        ]);

        audit_log_event($db, (int)$admin['id'], 'QUESTION_UPDATE', 'questions', $id, [
          'topic_id' => $topicId
        ]);

        flash_set('success', 'Question updated.');
      } catch (PDOException $e) {
        flash_set('error', 'Update failed. This may duplicate an existing question under the same topic.');
      }

      redirect('/public/index.php?r=admin_questions_edit&id=' . $id);
    }

    render('admin/questions_edit', [
      'title' => 'Edit Question',
      'row' => $row
    ]);
  },

];
