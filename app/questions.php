<?php
declare(strict_types=1);

function q_norm(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
  return $s;
}

function q_correct_option(string $v): ?string {
  $v = strtoupper(trim($v));
  if ($v === '') return null;

  // Accept A/B/C/D
  if (in_array($v, ['A','B','C','D'], true)) return $v;

  // Accept 1-4
  if (in_array($v, ['1','2','3','4'], true)) {
    return ['1'=>'A','2'=>'B','3'=>'C','4'=>'D'][$v] ?? null;
  }

  return null;
}

function q_status(string $v): string {
  $v = strtolower(trim($v));
  return in_array($v, ['active','inactive'], true) ? $v : 'active';
}

function q_hash(string $question, string $a, string $b, string $c, string $d): string {
  // Hash ignores correct option on purpose, same stem/options is considered duplicate
  $payload = implode('||', [
    mb_strtolower(q_norm($question)),
    mb_strtolower(q_norm($a)),
    mb_strtolower(q_norm($b)),
    mb_strtolower(q_norm($c)),
    mb_strtolower(q_norm($d)),
  ]);
  return hash('sha256', $payload);
}

/**
 * Resolve topic_id using taxonomy columns.
 * Returns topic_id or 0 if not found.
 */
function q_resolve_topic_id(PDO $db, string $levelCode, string $moduleCode, string $subjectName, string $topicName): int {
  $stmt = $db->prepare("
    SELECT t.id
    FROM topics t
    JOIN subjects s ON s.id = t.subject_id
    JOIN modules m ON m.id = s.module_id
    JOIN levels  l ON l.id = m.level_id
    WHERE l.code = :lc AND m.code = :mc AND s.name = :sn AND t.name = :tn
    LIMIT 1
  ");
  $stmt->execute([
    ':lc' => trim($levelCode),
    ':mc' => trim($moduleCode),
    ':sn' => trim($subjectName),
    ':tn' => trim($topicName),
  ]);
  $row = $stmt->fetch();
  return $row ? (int)$row['id'] : 0;
}
