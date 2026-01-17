<?php
declare(strict_types=1);

/**
 * User-facing taxonomy routes:
 *  - taxonomy_selector
 *  - api_levels
 *  - api_modules
 *  - api_subjects
 *  - api_topics
 */

return [

    // Main selector screen for users (can later become quiz start UI)
    'taxonomy_selector' => function (PDO $db, array $config): void {
        $u = require_login($db);

        // We only preload levels here; modules/subjects/topics are fetched via APIs.
        $stmt = $db->prepare("SELECT * FROM levels ORDER BY CAST(code AS UNSIGNED), code");
        $stmt->execute();
        $levels = $stmt->fetchAll() ?: [];

        render('user/taxonomy_selector', [
            'title'  => 'Choose Topics',
            'levels' => $levels,
            'user'   => $u,
        ]);
    },

    // Levels JSON
    'api_levels' => function (PDO $db, array $config): void {
        require_login($db);
        header('Content-Type: application/json; charset=utf-8');

        $stmt = $db->prepare("SELECT id, code, name FROM levels ORDER BY CAST(code AS UNSIGNED), code");
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];

        $out = [];
        foreach ($rows as $l) {
            $label = $l['code'];
            if (!empty($l['name'])) {
                $label .= ' - ' . $l['name'];
            }
            $out[] = [
                'id'    => (int)$l['id'],
                'label' => $label,
            ];
        }

        echo json_encode($out, JSON_UNESCAPED_SLASHES);
        exit;
    },

    // Modules JSON
    'api_modules' => function (PDO $db, array $config): void {
        require_login($db);
        header('Content-Type: application/json; charset=utf-8');

        $levelId = (int)($_GET['level_id'] ?? 0);
        if ($levelId <= 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("SELECT id, code, name FROM modules WHERE level_id = :lid ORDER BY code");
        $stmt->execute([':lid' => $levelId]);
        $rows = $stmt->fetchAll() ?: [];

        $out = [];
        foreach ($rows as $m) {
            $label = $m['code'];
            if (!empty($m['name'])) {
                $label .= ' - ' . $m['name'];
            }
            $out[] = [
                'id'    => (int)$m['id'],
                'label' => $label,
            ];
        }

        echo json_encode($out, JSON_UNESCAPED_SLASHES);
        exit;
    },

    // Subjects JSON
    'api_subjects' => function (PDO $db, array $config): void {
        require_login($db);
        header('Content-Type: application/json; charset=utf-8');

        $moduleId = (int)($_GET['module_id'] ?? 0);
        if ($moduleId <= 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("SELECT id, name FROM subjects WHERE module_id = :mid ORDER BY name");
        $stmt->execute([':mid' => $moduleId]);
        $rows = $stmt->fetchAll() ?: [];

        $out = array_map(static function ($s) {
            return [
                'id'    => (int)$s['id'],
                'label' => (string)$s['name'],
            ];
        }, $rows);

        echo json_encode($out, JSON_UNESCAPED_SLASHES);
        exit;
    },

    // Topics JSON
    'api_topics' => function (PDO $db, array $config): void {
        require_login($db);
        header('Content-Type: application/json; charset=utf-8');

        $subjectId = (int)($_GET['subject_id'] ?? 0);
        if ($subjectId <= 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("SELECT id, name FROM topics WHERE subject_id = :sid ORDER BY name");
        $stmt->execute([':sid' => $subjectId]);
        $rows = $stmt->fetchAll() ?: [];

        $out = array_map(static function ($t) {
            return [
                'id'    => (int)$t['id'],
                'label' => (string)$t['name'],
            ];
        }, $rows);

        echo json_encode($out, JSON_UNESCAPED_SLASHES);
        exit;
    },

];
