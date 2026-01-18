<?php
declare(strict_types=1);

return [

    // -------------------------
    // Topic selector + quiz start (single step)
    // -------------------------
    'taxonomy_selector' => function (PDO $db, array $config): void {
        $user = require_login($db);

        // Optional quick preset ?preset=gem201
        $presetLevelId  = null;
        $presetModuleId = null;

        $preset = (string)($_GET['preset'] ?? '');
        if ($preset === 'gem201') {
            // Find Level "200"
            $stmt = $db->prepare("SELECT id FROM levels WHERE code = :code LIMIT 1");
            $stmt->execute([':code' => '200']);
            if ($row = $stmt->fetch()) {
                $presetLevelId = (int)$row['id'];

                // Find Module "GEM 201" within that level
                $stmt = $db->prepare("
                    SELECT id
                    FROM modules
                    WHERE level_id = :lid AND code = :code
                    LIMIT 1
                ");
                $stmt->execute([
                    ':lid'  => $presetLevelId,
                    ':code' => 'GEM 201',
                ]);
                if ($m = $stmt->fetch()) {
                    $presetModuleId = (int)$m['id'];
                }
            }
        }

        // No POST handling here: the form on this page posts directly to quiz_start
        render('user/taxonomy_selector', [
            'title'          => 'Start Quiz',
            'user'           => $user,
            'presetLevelId'  => $presetLevelId,
            'presetModuleId' => $presetModuleId,
        ]);
    },

    // -------------------------
    // API: levels
    // -------------------------
    'api_levels' => function (PDO $db, array $config): void {
        require_login($db);
        header('Content-Type: application/json; charset=utf-8');

        $stmt = $db->query("SELECT id, code, name FROM levels ORDER BY CAST(code AS UNSIGNED), code");
        $rows = $stmt->fetchAll() ?: [];

        $out = array_map(static function (array $r): array {
            $label = trim((string)$r['code'] . ' ' . (string)($r['name'] ?? ''));
            return [
                'id'    => (int)$r['id'],
                'label' => $label,
            ];
        }, $rows);

        echo json_encode($out);
        exit;
    },

    // -------------------------
    // API: modules by level
    // -------------------------
    'api_modules' => function (PDO $db, array $config): void {
        require_login($db);
        header('Content-Type: application/json; charset=utf-8');

        $levelId = (int)($_GET['level_id'] ?? 0);
        if ($levelId <= 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, code, name
            FROM modules
            WHERE level_id = :lid
            ORDER BY code ASC, name ASC
        ");
        $stmt->execute([':lid' => $levelId]);
        $rows = $stmt->fetchAll() ?: [];

        $out = array_map(static function (array $r): array {
            $label = trim((string)$r['code'] . ' ' . (string)($r['name'] ?? ''));
            return [
                'id'    => (int)$r['id'],
                'label' => $label,
            ];
        }, $rows);

        echo json_encode($out);
        exit;
    },

    // -------------------------
    // API: subjects by module
    // -------------------------
    'api_subjects' => function (PDO $db, array $config): void {
        require_login($db);
        header('Content-Type: application/json; charset=utf-8');

        $moduleId = (int)($_GET['module_id'] ?? 0);
        if ($moduleId <= 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, name
            FROM subjects
            WHERE module_id = :mid
            ORDER BY name ASC
        ");
        $stmt->execute([':mid' => $moduleId]);
        $rows = $stmt->fetchAll() ?: [];

        $out = array_map(static function (array $r): array {
            return [
                'id'    => (int)$r['id'],
                'label' => (string)$r['name'],
            ];
        }, $rows);

        echo json_encode($out);
        exit;
    },

    // -------------------------
    // API: topics by subject
    // -------------------------
    'api_topics' => function (PDO $db, array $config): void {
        require_login($db);
        header('Content-Type: application/json; charset=utf-8');

        $subjectId = (int)($_GET['subject_id'] ?? 0);
        if ($subjectId <= 0) {
            echo json_encode([]);
            exit;
        }

        $stmt = $db->prepare("
            SELECT id, name
            FROM topics
            WHERE subject_id = :sid
            ORDER BY name ASC
        ");
        $stmt->execute([':sid' => $subjectId]);
        $rows = $stmt->fetchAll() ?: [];

        $out = array_map(static function (array $r): array {
            return [
                'id'    => (int)$r['id'],
                'label' => (string)$r['name'],
            ];
        }, $rows);

        echo json_encode($out);
        exit;
    },

];
