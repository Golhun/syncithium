<?php
declare(strict_types=1);

/** @var string $title */
$title = $title ?? 'Syncithium';

// Get current user if possible
$u = null;
try {
    if (isset($db) && $db instanceof PDO) {
        $u = current_user($db);
    }
} catch (Throwable $e) {
    $u = null;
}

// Flash message (may be null)
$flash = function_exists('flash_take') ? flash_take() : null;
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
                       href="/public/index.php?r=admin_users">
                        Users
                    </a>

                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=admin_levels">
                        Taxonomy
                    </a>

                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=admin_questions">
                        Questions
                    </a>

                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=admin_reset_requests">
                        Reset requests
                    </a>

                    <!-- NEW: admin can also start quiz -->
                    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
                       href="/public/index.php?r=quiz_start">
                        Start quiz
                    </a>
                <?php else: ?>
    <a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
       href="/public/index.php?r=quiz_start">
        Start quiz
    </a>
<?php endif; ?>

<a class="px-3 py-2 rounded-lg hover:bg-gray-50 border border-gray-200"
   href="/public/index.php?r=logout">
    Sign out
</a>

            </nav>
        <?php endif; ?>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-6">
    <?php require $view_file; ?>
</main>

<!-- Alpine (local) -->
<script src="/public/assets/js/alpine.min.js" defer></script>

<!-- Alertify (local) -->
<script src="/public/assets/js/alertify.min.js"></script>

<?php if (!empty($flash) && is_array($flash)): ?>
    <script>
        (function () {
            const type = <?= json_encode((string)($flash['type'] ?? 'info')) ?>;
            const msg  = <?= json_encode((string)($flash['message'] ?? '')) ?>;
            if (!msg) return;

            if (type === 'success') alertify.success(msg);
            else if (type === 'error') alertify.error(msg);
            else alertify.message(msg);
        })();
    </script>
<?php endif; ?>

</body>
</html>
