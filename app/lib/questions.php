<?php
declare(strict_types=1);

/**
 * Normalise the correct_option value from CSV into A/B/C/D.
 *
 * Accepts:
 *   - A, B, C, D (any case)
 *   - 1, 2, 3, 4  → mapped to A, B, C, D
 * Returns '' if it cannot be normalised, so the caller can treat it as an error.
 */
function q_norm(string $raw): string
{
    $v = strtoupper(trim($raw));

    // Direct A–D
    if (in_array($v, ['A', 'B', 'C', 'D'], true)) {
        return $v;
    }

    // 1–4 → A–D
    if (in_array($v, ['1', '2', '3', '4'], true)) {
        $map = [
            '1' => 'A',
            '2' => 'B',
            '3' => 'C',
            '4' => 'D',
        ];
        return $map[$v] ?? '';
    }

    // Unknown / invalid
    return '';
}

/**
 * Normalise question status from CSV.
 * Defaults to 'active' if missing or invalid.
 */
function q_status_norm(?string $raw): string
{
    $v = strtolower(trim((string)$raw));
    if (in_array($v, ['active', 'inactive'], true)) {
        return $v;
    }
    return 'active';
}

/**
 * Resolve or create a topic_id based on CSV taxonomy OR fall back to a given topic.
 *
 * Usage patterns supported (to be safe with whatever you already wired):
 *
 * 1) q_resolve_topic_id($db, $levelCode, $moduleCode, $subjectName, $topicName, $fallbackTopicId?)
 * 2) q_resolve_topic_id($db, $rowArray, $fallbackTopicId?)
 *    where $rowArray has keys: level_code, module_code, subject_name, topic_name
 *
 * Returns:
 *   - int topic_id  if it can be resolved or created
 *   - null          if nothing can be resolved and no fallback is available
 */
function q_resolve_topic_id($db, ...$args): ?int
{
    // Defensive: require a DB-like object with prepare()
    if (!is_object($db) || !method_exists($db, 'prepare')) {
        return null;
    }

    $levelCode   = '';
    $moduleCode  = '';
    $subjectName = '';
    $topicName   = '';
    $fallbackTopicId = null;

    // Pattern 1: q_resolve_topic_id($db, $rowArray, $fallback?)
    if (count($args) >= 1 && is_array($args[0]) && (isset($args[0]['level_code']) || isset($args[0]['topic_name']))) {
        $row = $args[0];

        $levelCode   = trim((string)($row['level_code']   ?? ''));
        $moduleCode  = trim((string)($row['module_code']  ?? ''));
        $subjectName = trim((string)($row['subject_name'] ?? ''));
        $topicName   = trim((string)($row['topic_name']   ?? ''));

        if (isset($args[1]) && $args[1] !== '' && $args[1] !== null) {
            $fallbackTopicId = (int)$args[1];
        }
    }
    // Pattern 2: q_resolve_topic_id($db, $levelCode, $moduleCode, $subjectName, $topicName, $fallback?)
    elseif (count($args) >= 4) {
        $levelCode   = trim((string)$args[0]);
        $moduleCode  = trim((string)$args[1]);
        $subjectName = trim((string)$args[2]);
        $topicName   = trim((string)$args[3]);

        if (isset($args[4]) && $args[4] !== '' && $args[4] !== null) {
            $fallbackTopicId = (int)$args[4];
        }
    }
    // Anything else → not enough info, just fall back if present.
    else {
        if (isset($args[0]) && $args[0] !== '' && $args[0] !== null) {
            $fallbackTopicId = (int)$args[0];
        }
    }

    // If no taxonomy information, use fallback topic if provided.
    if ($levelCode === '' && $moduleCode === '' && $subjectName === '' && $topicName === '') {
        return $fallbackTopicId;
    }

    // If topic_name is missing, we cannot reliably create a topic; use fallback.
    if ($topicName === '') {
        return $fallbackTopicId;
    }

    try {
        // 1) Level
        $stmt = $db->prepare("SELECT id FROM levels WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $levelCode]);
        $level = $stmt->fetch();

        if ($level) {
            $levelId = (int)$level['id'];
        } else {
            // If level_code is empty, we can still create a generic one,
            // but it is cleaner to require something. Here we continue with an empty code.
            $stmt = $db->prepare("INSERT INTO levels (code, name) VALUES (:code, :name)");
            $stmt->execute([':code' => ($levelCode === '' ? 'UNSPECIFIED' : $levelCode), ':name' => null]);
            $levelId = (int)$db->lastInsertId();
        }

        // 2) Module (per level)
        $modCode = ($moduleCode === '' ? 'UNSPECIFIED' : $moduleCode);
        $stmt = $db->prepare("SELECT id FROM modules WHERE level_id = :lid AND code = :code LIMIT 1");
        $stmt->execute([':lid' => $levelId, ':code' => $modCode]);
        $module = $stmt->fetch();

        if ($module) {
            $moduleId = (int)$module['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO modules (level_id, code, name) VALUES (:lid, :code, :name)");
            $stmt->execute([
                ':lid'  => $levelId,
                ':code' => $modCode,
                ':name' => ($subjectName === '' ? null : $subjectName),
            ]);
            $moduleId = (int)$db->lastInsertId();
        }

        // 3) Subject (per module)
        $subName = ($subjectName === '' ? 'General' : $subjectName);
        $stmt = $db->prepare("SELECT id FROM subjects WHERE module_id = :mid AND name = :name LIMIT 1");
        $stmt->execute([':mid' => $moduleId, ':name' => $subName]);
        $subject = $stmt->fetch();

        if ($subject) {
            $subjectId = (int)$subject['id'];
        } else {
            $stmt = $db->prepare("INSERT INTO subjects (module_id, name) VALUES (:mid, :name)");
            $stmt->execute([':mid' => $moduleId, ':name' => $subName]);
            $subjectId = (int)$db->lastInsertId();
        }

        // 4) Topic (per subject)
        $stmt = $db->prepare("SELECT id FROM topics WHERE subject_id = :sid AND name = :name LIMIT 1");
        $stmt->execute([':sid' => $subjectId, ':name' => $topicName]);
        $topic = $stmt->fetch();

        if ($topic) {
            return (int)$topic['id'];
        }

        $stmt = $db->prepare("INSERT INTO topics (subject_id, name) VALUES (:sid, :name)");
        $stmt->execute([':sid' => $subjectId, ':name' => $topicName]);
        return (int)$db->lastInsertId();
    } catch (Throwable $e) {
        // If anything fails, at worst fall back to the provided topic id (if any).
        return $fallbackTopicId;
    }
}


/**
 * Helper to extract and normalise the correct option from CSV data.
 *
 * Flexible usage so we do not have to touch routes:
 *
 * 1) q_correct_option('A')
 * 2) q_correct_option('1')       // → A
 * 3) q_correct_option($row)      // expects $row['correct_option']
 * 4) q_correct_option($row, 'correct_option')
 * 5) q_correct_option($row, 5)   // if using numeric index in a CSV row array
 */
function q_correct_option(...$args): string
{
    if (count($args) === 0) {
        return '';
    }

    $first = $args[0];

    // Case: row array
    if (is_array($first)) {
        $row = $first;

        // If a key/index is provided explicitly
        if (isset($args[1])) {
            $key = $args[1];

            if (is_string($key) && array_key_exists($key, $row)) {
                return q_norm((string)$row[$key]);
            }

            if (is_int($key) && array_key_exists($key, $row)) {
                return q_norm((string)$row[$key]);
            }
        }

        // Fallback: look for the 'correct_option' key itself
        if (array_key_exists('correct_option', $row)) {
            return q_norm((string)$row['correct_option']);
        }

        // Nothing usable
        return '';
    }

    // Case: scalar value
    return q_norm((string)$first);
}
