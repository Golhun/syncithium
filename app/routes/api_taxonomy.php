<?php
declare(strict_types=1);

return [

  'api_modules' => function (PDO $db, array $config): void {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $levelId = (int)($_GET['level_id'] ?? 0);
    if ($levelId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, code, name FROM modules WHERE level_id = :lid ORDER BY code");
    $stmt->execute([':lid' => $levelId]);

    $out = [];
    foreach (($stmt->fetchAll() ?: []) as $m) {
      $label = $m['code'] . (!empty($m['name']) ? (' , ' . $m['name']) : '');
      $out[] = ['id' => (int)$m['id'], 'label' => $label];
    }
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  },

  'api_subjects' => function (PDO $db, array $config): void {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $moduleId = (int)($_GET['module_id'] ?? 0);
    if ($moduleId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, name FROM subjects WHERE module_id = :mid ORDER BY name");
    $stmt->execute([':mid' => $moduleId]);

    $rows = $stmt->fetchAll() ?: [];
    $out = array_map(fn($s) => ['id' => (int)$s['id'], 'label' => (string)$s['name']], $rows);

    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  },

  'api_topics' => function (PDO $db, array $config): void {
    require_login($db);
    header('Content-Type: application/json; charset=utf-8');

    $subjectId = (int)($_GET['subject_id'] ?? 0);
    if ($subjectId <= 0) { echo json_encode([]); exit; }

    $stmt = $db->prepare("SELECT id, name FROM topics WHERE subject_id = :sid ORDER BY name");
    $stmt->execute([':sid' => $subjectId]);

    $rows = $stmt->fetchAll() ?: [];
    $out = array_map(fn($t) => ['id' => (int)$t['id'], 'label' => (string)$t['name']], $rows);

    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
  },

];
