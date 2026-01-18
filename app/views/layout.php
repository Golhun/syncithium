<?php
declare(strict_types=1);

/** @var string $title */
/** @var string $view_file */

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

$isAdmin = $u && (($u['role'] ?? 'user') === 'admin');

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

$currentR = isset($_GET['r']) ? (string)$_GET['r'] : '';


// --- Nav item renderer ---
function nav_item(string $href, string $label, string $iconName, bool $active = false): string
{
    $activeClass = $active
        ? 'bg-sky-600 text-white border-sky-600'
        : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50';

    $icon = $active
        ? hi_svg($iconName, 'solid', 'h-4 w-4')
        : hi_svg($iconName, 'outline', 'h-4 w-4');

    $html = '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"'
          . ' class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border text-sm transition ' . $activeClass . '">'
          . $icon
          . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
          . '</a>';

    return $html;
}

function badge(string $text, string $tone = 'gray'): string
{
    $tone = in_array($tone, ['gray','sky','emerald','amber','rose'], true) ? $tone : 'gray';

    $map = [
        'gray'    => 'bg-gray-50 text-gray-700 ring-gray-200',
        'sky'     => 'bg-sky-50 text-sky-800 ring-sky-200',
        'emerald' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        'amber'   => 'bg-amber-50 text-amber-900 ring-amber-200',
        'rose'    => 'bg-rose-50 text-rose-800 ring-rose-200',
    ];

    return '<span class="inline-flex items-center px-2 py-1 text-xs rounded-lg ring-1 ' . $map[$tone] . '">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '</span>';
}

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
    <style>[x-cloak]{display:none !important;}</style>
    <script src="/public/assets/js/alpine.min.js" defer></script>
</head>

<body class="bg-gray-50 text-gray-900">
<header class="border-b border-gray-200 bg-white">
    <div class="max-w-6xl mx-auto px-6 py-3">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <a href="/public/index.php" class="inline-flex items-center gap-2 font-semibold text-sm whitespace-nowrap">
                    <?= hi_svg('squares-2x2', 'solid', 'h-5 w-5 text-sky-600') ?>
                    <span>Syncithium</span>
                </a>

                <?php if ($u): ?>
                    <div class="hidden md:flex items-center gap-2 min-w-0">
                        <span class="text-xs text-gray-500 truncate">
                            <?= htmlspecialchars((string)$u['email']) ?>
                        </span>
                        <?= badge(($u['role'] ?? 'user') === 'admin' ? 'Admin' : 'User', ($u['role'] ?? 'user') === 'admin' ? 'sky' : 'gray') ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($u): ?>
                <div class="flex items-center gap-3">
                    <!-- Quick actions (always visible) -->
                    <?php if ($isAdmin): ?>
                        <!-- Admin can also take quizzes like a normal user -->
                        <a href="/public/index.php?r=taxonomy_selector"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm transition">
                            <?= hi_svg('play', 'outline', 'h-4 w-4') ?>
                            <span class="hidden sm:inline">Take Quiz</span>
                            <span class="sm:hidden">Quiz</span>
                        </a>
                    <?php endif; ?>

                    <!-- User menu -->
                    <div x-data="{ open:false }" class="relative">
                        <button type="button"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 bg-white hover:bg-gray-50 text-sm transition"
                                @click="open = !open"
                                :aria-expanded="open ? 'true' : 'false'">
                            <?= hi_svg('user-circle', 'outline', 'h-5 w-5') ?>
                            <span class="hidden md:inline">Menu</span>
                            <?= hi_svg('chevron-down', 'outline', 'h-4 w-4 text-gray-500') ?>
                        </button>

                        <div x-cloak x-show="open" @click.outside="open=false"
                             class="absolute right-0 mt-2 w-72 rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden z-50">
                            <div class="p-3 border-b border-gray-200">
                                <div class="text-sm font-semibold truncate"><?= htmlspecialchars((string)$u['email']) ?></div>
                                <div class="mt-1">
                                    <?= badge(($u['role'] ?? 'user') === 'admin' ? 'Administrator access' : 'Standard access', ($u['role'] ?? 'user') === 'admin' ? 'sky' : 'gray') ?>
                                </div>
                            </div>

                            <div class="p-2 space-y-1">
                                <?php if ($isAdmin): ?>
                                    <a class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 text-sm"
                                       href="/public/index.php?r=admin_users">
                                        <?= hi_svg('users', 'outline', 'h-4 w-4') ?>
                                        <span>Admin, Users</span>
                                    </a>

                                    <a class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 text-sm"
                                       href="/public/index.php?r=admin_levels">
                                        <?= hi_svg('adjustments-horizontal', 'outline', 'h-4 w-4') ?>
                                        <span>Admin, Taxonomy</span>
                                    </a>

                                    <a class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 text-sm"
                                       href="/public/index.php?r=admin_questions">
                                        <?= hi_svg('document-text', 'outline', 'h-4 w-4') ?>
                                        <span>Admin, Questions</span>
                                    </a>

                                    <a class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 text-sm"
                                       href="/public/index.php?r=admin_question_reports">
                                        <?= hi_svg('flag', 'outline', 'h-4 w-4') ?>
                                        <span>Admin, Reports</span>
                                    </a>

                                    <a class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 text-sm"
                                       href="/public/index.php?r=admin_reset_requests">
                                        <?= hi_svg('key', 'outline', 'h-4 w-4') ?>
                                        <span>Admin, Reset requests</span>
                                    </a>

                                    <div class="my-2 border-t border-gray-200"></div>
                                <?php else: ?>
                                    <a class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 text-sm"
                                       href="/public/index.php?r=taxonomy_selector">
                                        <?= hi_svg('play', 'outline', 'h-4 w-4') ?>
                                        <span>Take Quiz</span>
                                    </a>

                                    <a class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 text-sm"
                                       href="/public/index.php?r=my_reports">
                                        <?= hi_svg('flag', 'outline', 'h-4 w-4') ?>
                                        <span>My Reports</span>
                                    </a>

                                    <div class="my-2 border-t border-gray-200"></div>
                                <?php endif; ?>

                                <a class="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-gray-50 text-sm"
                                   href="/public/index.php?r=logout">
                                    <?= hi_svg('arrow-right-on-rectangle', 'outline', 'h-4 w-4') ?>
                                    <span>Sign out</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($u): ?>
            <!-- Secondary navigation row (quick tabs) -->
            <div class="mt-3 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 flex-wrap">
                    <?php if ($isAdmin): ?>
                        <?= nav_item('/public/index.php?r=admin_users', 'Users', 'users', $currentR === 'admin_users') ?>
                        <?= nav_item('/public/index.php?r=admin_levels', 'Taxonomy', 'adjustments-horizontal', $currentR === 'admin_levels') ?>
                        <?= nav_item('/public/index.php?r=admin_questions', 'Questions', 'document-text', $currentR === 'admin_questions') ?>
                        <?= nav_item('/public/index.php?r=admin_question_reports', 'Reports', 'flag', $currentR === 'admin_question_reports') ?>
                        <?= nav_item('/public/index.php?r=admin_reset_requests', 'Reset requests', 'key', $currentR === 'admin_reset_requests') ?>
                    <?php else: ?>
                        <?= nav_item('/public/index.php?r=taxonomy_selector', 'Topics', 'squares-2x2', $currentR === 'taxonomy_selector') ?>
                        <?= nav_item('/public/index.php?r=my_reports', 'My Reports', 'flag', $currentR === 'my_reports') ?>
                    <?php endif; ?>
                </div>

                <?php if ($isAdmin): ?>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <?= hi_svg('information-circle', 'outline', 'h-4 w-4 text-gray-400') ?>
                        <span>Admins can manage content and also take quizzes as normal users.</span>
                    </div>
                <?php endif; ?>
            </div>
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
