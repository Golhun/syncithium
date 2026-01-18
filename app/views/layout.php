<?php
declare(strict_types=1);

/** @var string $title */
$title = $title ?? 'Syncithium';

// Current user (if any)
$u = null;
try {
    if (isset($db) && $db instanceof PDO) {
        $u = current_user($db);
    }
} catch (Throwable $e) {
    $u = null;
}

// Flash messages (may be a single assoc array OR a list)
$flashRaw = function_exists('flash_take') ? flash_take() : null;
$flashes = [];

if (is_array($flashRaw)) {
    // Case A: single assoc message
    if (array_key_exists('type', $flashRaw) && array_key_exists('message', $flashRaw)) {
        $flashes = [$flashRaw];
    } else {
        // Case B: list of messages
        $flashes = $flashRaw;
    }
}

// Normalise and keep only valid items
$flashes = array_values(array_filter($flashes, static function ($m): bool {
    return is_array($m) && isset($m['message']) && (string)$m['message'] !== '';
}));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>

    <!-- Tailwind + app CSS (local) -->
    <link rel="stylesheet" href="/public/assets/css/tailwind.min.css">
    <link rel="stylesheet" href="/public/assets/css/app.css">

    <!-- Alertify CSS (local) -->
    <link rel="stylesheet" href="/public/assets/css/alertify.min.css">

    <!-- Alpine (local) -->
    <script src="/public/assets/js/alpine.min.js" defer></script>
</head>
<body class="bg-gray-50 text-gray-900">

<header class="border-b border-gray-200 bg-white">
    <div class="max-w-6xl mx-auto px-6 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/public/index.php" class="font-semibold text-sm">Syncithium</a>
            <?php if ($u): ?>
                <span class="text-xs text-gray-500">
                    Signed in as <?= htmlspecialchars((string)$u['email']) ?>
                    (<?= htmlspecialchars((string)($u['role'] ?? 'user')) ?>)
                </span>
            <?php endif; ?>
        </div>

        <?php if ($u): ?>
            <nav class="flex items-center gap-2 text-sm">
                <?php if (($u['role'] ?? 'user') === 'admin'): ?>
                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=admin_users">Users</a>

                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=admin_levels">Taxonomy</a>

                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=admin_questions">Questions</a>

                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=admin_reset_requests">Reset requests</a>
                <?php else: ?>
                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=taxonomy_selector">Topics</a>
                <?php endif; ?>

                <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                   href="/public/index.php?r=logout">Sign out</a>
            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-6">
    <?php require $view_file; ?>
</main>

<!-- Alertify (local) -->
<script src="/public/assets/js/alertify.min.js"></script>

<?php if (!empty($flashes)): ?>
    <script>
        (function () {
            const items = <?= json_encode($flashes, JSON_UNESCAPED_SLASHES) ?>;
            if (!Array.isArray(items)) return;

            items.forEach(function (it) {
                const type = String(it.type || 'info');
                const msg  = String(it.message || '');
                if (!msg) return;

                if (type === 'success') alertify.success(msg);
                else if (type === 'error') alertify.error(msg);
                else alertify.message(msg);
            });
        })();
    </script>
<?php endif; ?>

</body>
</html>
