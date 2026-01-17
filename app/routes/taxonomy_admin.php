<?php
declare(strict_types=1);

return [

  // =========================
  // Phase 3: Taxonomy Admin
  // =========================

  'admin_levels' => function(PDO $db, array $config): void {
    $admin = require_admin($db);

    $editId = (int)($_GET['edit_id'] ?? 0);
    $edit = null;

    if ($editId > 0) {
      $stmt = $db->prepare("SELECT * FROM levels WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $editId]);
      $edit = $stmt->fetch();
    }

    if (is_post()) {
      csrf_verify();

      $action = (string)($_POST['action'] ?? '');
      $code   = trim((string)($_POST['code'] ?? ''));
      $name   = trim((string)($_POST['name'] ?? ''));

      if ($action === 'create') {
        if ($code === '') {
          flash_set('error', 'Level code is required.');
          redirect('/public/index.php?r=admin_levels');
        }

        try {
          $stmt = $db->prepare("INSERT INTO levels (code, name) VALUES (:code, :name)");
          $stmt->execute([
            ':code' => $code,
            ':name' => ($name === '' ? null : $name),
          ]);

          $newId = (int)$db->lastInsertId();
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_LEVEL_CREATE', 'levels', $newId, ['code' => $code]);

          flash_set('success', 'Level created.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not create level. Code may already exist.');
        }

        redirect('/public/index.php?r=admin_levels');
      }

      if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0 || $code === '') {
          flash_set('error', 'Invalid update request.');
          redirect('/public/index.php?r=admin_levels');
        }

        try {
          $stmt = $db->prepare("UPDATE levels SET code = :code, name = :name WHERE id = :id");
          $stmt->execute([
            ':code' => $code,
            ':name' => ($name === '' ? null : $name),
            ':id'   => $id,
          ]);

          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_LEVEL_UPDATE', 'levels', $id, ['code' => $code]);

          flash_set('success', 'Level updated.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not update level. Code may already exist.');
        }

        redirect('/public/index.php?r=admin_levels');
      }

      if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
          flash_set('error', 'Invalid delete request.');
          redirect('/public/index.php?r=admin_levels');
        }

        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM modules WHERE level_id = :id");
        $stmt->execute([':id' => $id]);
        $c = (int)($stmt->fetch()['c'] ?? 0);

        if ($c > 0) {
          flash_set('error', 'Cannot delete. This level has modules.');
          redirect('/public/index.php?r=admin_levels');
        }

        $stmt = $db->prepare("DELETE FROM levels WHERE id = :id");
        $stmt->execute([':id' => $id]);

        audit_log_event($db, (int)$admin['id'], 'TAXONOMY_LEVEL_DELETE', 'levels', $id);

        flash_set('success', 'Level deleted.');
        redirect('/public/index.php?r=admin_levels');
      }

      flash_set('error', 'Unknown action.');
      redirect('/public/index.php?r=admin_levels');
    }

    $stmt = $db->prepare("SELECT * FROM levels ORDER BY CAST(code AS UNSIGNED), code");
    $stmt->execute();
    $levels = $stmt->fetchAll();

    render('admin/levels', [
      'title'  => 'Taxonomy: Levels',
      'levels' => $levels,
      'edit'   => $edit
    ]);
  },


  'admin_modules' => function(PDO $db, array $config): void {
    $admin = require_admin($db);

    $stmt = $db->prepare("SELECT * FROM levels ORDER BY CAST(code AS UNSIGNED), code");
    $stmt->execute();
    $levels = $stmt->fetchAll();

    $editId = (int)($_GET['edit_id'] ?? 0);
    $edit = null;

    if ($editId > 0) {
      $stmt = $db->prepare("SELECT * FROM modules WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $editId]);
      $edit = $stmt->fetch();
    }

    if (is_post()) {
      csrf_verify();

      $action  = (string)($_POST['action'] ?? '');
      $levelId = (int)($_POST['level_id'] ?? 0);
      $code    = trim((string)($_POST['code'] ?? ''));
      $name    = trim((string)($_POST['name'] ?? ''));

      if ($action === 'create') {
        if ($levelId <= 0 || $code === '') {
          flash_set('error', 'Level and module code are required.');
          redirect('/public/index.php?r=admin_modules');
        }

        try {
          $stmt = $db->prepare("INSERT INTO modules (level_id, code, name) VALUES (:lid, :code, :name)");
          $stmt->execute([
            ':lid'  => $levelId,
            ':code' => $code,
            ':name' => ($name === '' ? null : $name),
          ]);

          $newId = (int)$db->lastInsertId();
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_MODULE_CREATE', 'modules', $newId, [
            'code' => $code,
            'level_id' => $levelId
          ]);

          flash_set('success', 'Module created.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not create module. Code may already exist under this level.');
        }

        redirect('/public/index.php?r=admin_modules');
      }

      if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0 || $levelId <= 0 || $code === '') {
          flash_set('error', 'Invalid update request.');
          redirect('/public/index.php?r=admin_modules');
        }

        try {
          $stmt = $db->prepare("UPDATE modules SET level_id = :lid, code = :code, name = :name WHERE id = :id");
          $stmt->execute([
            ':lid'  => $levelId,
            ':code' => $code,
            ':name' => ($name === '' ? null : $name),
            ':id'   => $id,
          ]);

          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_MODULE_UPDATE', 'modules', $id, [
            'code' => $code,
            'level_id' => $levelId
          ]);

          flash_set('success', 'Module updated.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not update module. Code may already exist under this level.');
        }

        redirect('/public/index.php?r=admin_modules');
      }

      if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
          flash_set('error', 'Invalid delete request.');
          redirect('/public/index.php?r=admin_modules');
        }

        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM subjects WHERE module_id = :id");
        $stmt->execute([':id' => $id]);
        $c = (int)($stmt->fetch()['c'] ?? 0);

        if ($c > 0) {
          flash_set('error', 'Cannot delete. This module has subjects.');
          redirect('/public/index.php?r=admin_modules');
        }

        $stmt = $db->prepare("DELETE FROM modules WHERE id = :id");
        $stmt->execute([':id' => $id]);

        audit_log_event($db, (int)$admin['id'], 'TAXONOMY_MODULE_DELETE', 'modules', $id);

        flash_set('success', 'Module deleted.');
        redirect('/public/index.php?r=admin_modules');
      }

      flash_set('error', 'Unknown action.');
      redirect('/public/index.php?r=admin_modules');
    }

    $stmt = $db->prepare("
      SELECT m.*, l.code AS level_code
      FROM modules m
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll();

    render('admin/modules', [
      'title'  => 'Taxonomy: Modules',
      'levels' => $levels,
      'modules'=> $modules,
      'edit'   => $edit,
    ]);
  },


  'admin_subjects' => function(PDO $db, array $config): void {
    $admin = require_admin($db);

    $stmt = $db->prepare("
      SELECT m.id, m.code, l.code AS level_code
      FROM modules m
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code
    ");
    $stmt->execute();
    $modules = $stmt->fetchAll();

    $editId = (int)($_GET['edit_id'] ?? 0);
    $edit = null;

    if ($editId > 0) {
      $stmt = $db->prepare("SELECT * FROM subjects WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $editId]);
      $edit = $stmt->fetch();
    }

    if (is_post()) {
      csrf_verify();

      $action   = (string)($_POST['action'] ?? '');
      $moduleId = (int)($_POST['module_id'] ?? 0);
      $name     = trim((string)($_POST['name'] ?? ''));

      if ($action === 'create') {
        if ($moduleId <= 0 || $name === '') {
          flash_set('error', 'Module and subject name are required.');
          redirect('/public/index.php?r=admin_subjects');
        }

        try {
          $stmt = $db->prepare("INSERT INTO subjects (module_id, name) VALUES (:mid, :name)");
          $stmt->execute([':mid' => $moduleId, ':name' => $name]);

          $newId = (int)$db->lastInsertId();
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_SUBJECT_CREATE', 'subjects', $newId, [
            'name' => $name,
            'module_id' => $moduleId
          ]);

          flash_set('success', 'Subject created.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not create subject. Name may already exist under this module.');
        }

        redirect('/public/index.php?r=admin_subjects');
      }

      if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0 || $moduleId <= 0 || $name === '') {
          flash_set('error', 'Invalid update request.');
          redirect('/public/index.php?r=admin_subjects');
        }

        try {
          $stmt = $db->prepare("UPDATE subjects SET module_id = :mid, name = :name WHERE id = :id");
          $stmt->execute([':mid' => $moduleId, ':name' => $name, ':id' => $id]);

          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_SUBJECT_UPDATE', 'subjects', $id, [
            'name' => $name,
            'module_id' => $moduleId
          ]);

          flash_set('success', 'Subject updated.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not update subject. Name may already exist under this module.');
        }

        redirect('/public/index.php?r=admin_subjects');
      }

      if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
          flash_set('error', 'Invalid delete request.');
          redirect('/public/index.php?r=admin_subjects');
        }

        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM topics WHERE subject_id = :id");
        $stmt->execute([':id' => $id]);
        $c = (int)($stmt->fetch()['c'] ?? 0);

        if ($c > 0) {
          flash_set('error', 'Cannot delete. This subject has topics.');
          redirect('/public/index.php?r=admin_subjects');
        }

        $stmt = $db->prepare("DELETE FROM subjects WHERE id = :id");
        $stmt->execute([':id' => $id]);

        audit_log_event($db, (int)$admin['id'], 'TAXONOMY_SUBJECT_DELETE', 'subjects', $id);

        flash_set('success', 'Subject deleted.');
        redirect('/public/index.php?r=admin_subjects');
      }

      flash_set('error', 'Unknown action.');
      redirect('/public/index.php?r=admin_subjects');
    }

    $stmt = $db->prepare("
      SELECT s.*, m.code AS module_code, l.code AS level_code
      FROM subjects s
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code, s.name
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll();

    render('admin/subjects', [
      'title'    => 'Taxonomy: Subjects',
      'modules'  => $modules,
      'subjects' => $subjects,
      'edit'     => $edit,
    ]);
  },


  'admin_topics' => function(PDO $db, array $config): void {
    $admin = require_admin($db);

    $stmt = $db->prepare("
      SELECT s.id, s.name AS subject_name, m.code AS module_code, l.code AS level_code
      FROM subjects s
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code, s.name
    ");
    $stmt->execute();
    $subjects = $stmt->fetchAll();

    $editId = (int)($_GET['edit_id'] ?? 0);
    $edit = null;

    if ($editId > 0) {
      $stmt = $db->prepare("SELECT * FROM topics WHERE id = :id LIMIT 1");
      $stmt->execute([':id' => $editId]);
      $edit = $stmt->fetch();
    }

    if (is_post()) {
      csrf_verify();

      $action    = (string)($_POST['action'] ?? '');
      $subjectId = (int)($_POST['subject_id'] ?? 0);
      $name      = trim((string)($_POST['name'] ?? ''));

      if ($action === 'create') {
        if ($subjectId <= 0 || $name === '') {
          flash_set('error', 'Subject and topic name are required.');
          redirect('/public/index.php?r=admin_topics');
        }

        try {
          $stmt = $db->prepare("INSERT INTO topics (subject_id, name) VALUES (:sid, :name)");
          $stmt->execute([':sid' => $subjectId, ':name' => $name]);

          $newId = (int)$db->lastInsertId();
          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_TOPIC_CREATE', 'topics', $newId, [
            'name' => $name,
            'subject_id' => $subjectId
          ]);

          flash_set('success', 'Topic created.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not create topic. Name may already exist under this subject.');
        }

        redirect('/public/index.php?r=admin_topics');
      }

      if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0 || $subjectId <= 0 || $name === '') {
          flash_set('error', 'Invalid update request.');
          redirect('/public/index.php?r=admin_topics');
        }

        try {
          $stmt = $db->prepare("UPDATE topics SET subject_id = :sid, name = :name WHERE id = :id");
          $stmt->execute([':sid' => $subjectId, ':name' => $name, ':id' => $id]);

          audit_log_event($db, (int)$admin['id'], 'TAXONOMY_TOPIC_UPDATE', 'topics', $id, [
            'name' => $name,
            'subject_id' => $subjectId
          ]);

          flash_set('success', 'Topic updated.');
        } catch (PDOException $e) {
          flash_set('error', 'Could not update topic. Name may already exist under this subject.');
        }

        redirect('/public/index.php?r=admin_topics');
      }

      if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
          flash_set('error', 'Invalid delete request.');
          redirect('/public/index.php?r=admin_topics');
        }

        $stmt = $db->prepare("DELETE FROM topics WHERE id = :id");
        $stmt->execute([':id' => $id]);

        audit_log_event($db, (int)$admin['id'], 'TAXONOMY_TOPIC_DELETE', 'topics', $id);

        flash_set('success', 'Topic deleted.');
        redirect('/public/index.php?r=admin_topics');
      }

      flash_set('error', 'Unknown action.');
      redirect('/public/index.php?r=admin_topics');
    }

    $stmt = $db->prepare("
      SELECT t.*, s.name AS subject_name, m.code AS module_code, l.code AS level_code
      FROM topics t
      JOIN subjects s ON s.id = t.subject_id
      JOIN modules m ON m.id = s.module_id
      JOIN levels l ON l.id = m.level_id
      ORDER BY CAST(l.code AS UNSIGNED), l.code, m.code, s.name, t.name
    ");
    $stmt->execute();
    $topics = $stmt->fetchAll();

    render('admin/topics', [
      'title'    => 'Taxonomy: Topics',
      'subjects' => $subjects,
      'topics'   => $topics,
      'edit'     => $edit,
    ]);
  },


  'admin_taxonomy_import' => function(PDO $db, array $config): void {
    $admin = require_admin($db);

    $results = [];

    if (is_post()) {
      csrf_verify();

      if (!isset($_FILES['csv']) || !is_uploaded_file($_FILES['csv']['tmp_name'])) {
        flash_set('error', 'Upload a CSV file.');
        redirect('/public/index.php?r=admin_taxonomy_import');
      }

      $content = file_get_contents($_FILES['csv']['tmp_name']) ?: '';
      $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];

      $header = [];
      if (count($lines) > 0) {
        $header = str_getcsv((string)array_shift($lines));
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);
      }

      $idx = [
        'level_code'    => array_search('level_code', $header, true),
        'module_code'   => array_search('module_code', $header, true),
        'subject_name'  => array_search('subject_name', $header, true),
        'topic_name'    => array_search('topic_name', $header, true),
      ];

      foreach ($idx as $k => $v) {
        if ($v === false) {
          flash_set('error', 'CSV header must include: level_code,module_code,subject_name,topic_name');
          redirect('/public/index.php?r=admin_taxonomy_import');
        }
      }

      $db->beginTransaction();

      try {
        foreach ($lines as $lineNo => $line) {
          if (trim((string)$line) === '') continue;

          $row = str_getcsv((string)$line);

          $levelCode   = trim((string)($row[$idx['level_code']] ?? ''));
          $moduleCode  = trim((string)($row[$idx['module_code']] ?? ''));
          $subjectName = trim((string)($row[$idx['subject_name']] ?? ''));
          $topicName   = trim((string)($row[$idx['topic_name']] ?? ''));

          if ($levelCode === '' || $moduleCode === '' || $subjectName === '' || $topicName === '') {
            $results[] = ['line' => $lineNo + 2, 'status' => 'skipped', 'note' => 'Missing required columns'];
            continue;
          }

          // Level
          $stmt = $db->prepare("SELECT id FROM levels WHERE code = :c LIMIT 1");
          $stmt->execute([':c' => $levelCode]);
          $level = $stmt->fetch();

          if (!$level) {
            $stmt = $db->prepare("INSERT INTO levels (code) VALUES (:c)");
            $stmt->execute([':c' => $levelCode]);
            $levelId = (int)$db->lastInsertId();

            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_LEVEL_CREATE', 'levels', $levelId, [
              'code' => $levelCode,
              'source' => 'import'
            ]);
          } else {
            $levelId = (int)$level['id'];
          }

          // Module
          $stmt = $db->prepare("SELECT id FROM modules WHERE level_id = :lid AND code = :c LIMIT 1");
          $stmt->execute([':lid' => $levelId, ':c' => $moduleCode]);
          $module = $stmt->fetch();

          if (!$module) {
            $stmt = $db->prepare("INSERT INTO modules (level_id, code) VALUES (:lid, :c)");
            $stmt->execute([':lid' => $levelId, ':c' => $moduleCode]);
            $moduleId = (int)$db->lastInsertId();

            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_MODULE_CREATE', 'modules', $moduleId, [
              'code' => $moduleCode,
              'level_id' => $levelId,
              'source' => 'import'
            ]);
          } else {
            $moduleId = (int)$module['id'];
          }

          // Subject
          $stmt = $db->prepare("SELECT id FROM subjects WHERE module_id = :mid AND name = :n LIMIT 1");
          $stmt->execute([':mid' => $moduleId, ':n' => $subjectName]);
          $subject = $stmt->fetch();

          if (!$subject) {
            $stmt = $db->prepare("INSERT INTO subjects (module_id, name) VALUES (:mid, :n)");
            $stmt->execute([':mid' => $moduleId, ':n' => $subjectName]);
            $subjectId = (int)$db->lastInsertId();

            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_SUBJECT_CREATE', 'subjects', $subjectId, [
              'name' => $subjectName,
              'module_id' => $moduleId,
              'source' => 'import'
            ]);
          } else {
            $subjectId = (int)$subject['id'];
          }

          // Topic
          $stmt = $db->prepare("SELECT id FROM topics WHERE subject_id = :sid AND name = :n LIMIT 1");
          $stmt->execute([':sid' => $subjectId, ':n' => $topicName]);
          $topic = $stmt->fetch();

          if (!$topic) {
            $stmt = $db->prepare("INSERT INTO topics (subject_id, name) VALUES (:sid, :n)");
            $stmt->execute([':sid' => $subjectId, ':n' => $topicName]);
            $topicId = (int)$db->lastInsertId();

            audit_log_event($db, (int)$admin['id'], 'TAXONOMY_TOPIC_CREATE', 'topics', $topicId, [
              'name' => $topicName,
              'subject_id' => $subjectId,
              'source' => 'import'
            ]);

            $results[] = ['line' => $lineNo + 2, 'status' => 'created', 'note' => 'OK'];
          } else {
            $results[] = ['line' => $lineNo + 2, 'status' => 'exists', 'note' => 'No change'];
          }
        }

        $db->commit();
        flash_set('success', 'Import complete.');
      } catch (Throwable $e) {
        $db->rollBack();
        flash_set('error', 'Import failed. Please validate your CSV and try again.');
      }
    }

    render('admin/taxonomy_import', [
      'title' => 'Taxonomy Import',
      'results' => $results,
    ]);
  },

];
