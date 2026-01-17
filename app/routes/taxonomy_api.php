<?php
declare(strict_types=1);

return [

  'taxonomy_selector' => function(PDO $db, array $config): void {
    $u = require_login($db);
    $stmt = $db->prepare("SELECT * FROM levels ORDER BY CAST(code AS UNSIGNED), code");
    $stmt->execute();
    $levels = $stmt->fetchAll();

    render('user/taxonomy_selector', [
      'title' => 'Choose Topics',
      'levels' => $levels,
      'user' => $u,
    ]);
  },

  'api_modules' => function(PDO $db, array $config): void {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $levelId = (int)($_GET['level_id'] ?? 0);
    if ($levelId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, code, name FROM modules WHERE level_id = :lid ORDER BY code");
    $stmt->execute([':lid' => $levelId]);

    $out = [];
    foreach ($stmt->fetchAll() as $m) {
      $label = $m['code'] . ($m['name'] ? (' , ' . $m['name']) : '');
      $out[] = ['id' => (int)$m['id'], 'label' => $label];
    }

    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  },

  'api_subjects' => function(PDO $db, array $config): void {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $moduleId = (int)($_GET['module_id'] ?? 0);
    if ($moduleId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, name FROM subjects WHERE module_id = :mid ORDER BY name");
    $stmt->execute([':mid' => $moduleId]);

    $out = array_map(fn($s) => ['id' => (int)$s['id'], 'label' => $s['name']], $stmt->fetchAll());
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  },

  'api_topics' => function(PDO $db, array $config): void {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $subjectId = (int)($_GET['subject_id'] ?? 0);
    if ($subjectId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, name FROM topics WHERE subject_id = :sid ORDER BY name");
    $stmt->execute([':sid' => $subjectId]);

    $out = array_map(fn($t) => ['id' => (int)$t['id'], 'label' => $t['name']], $stmt->fetchAll());
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  },

];
