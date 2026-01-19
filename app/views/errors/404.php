<?php
declare(strict_types=1);
/**
 * @var int $code
 * @var string $title
 * @var string $message
 * @var string $joke
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($code ?? '404') ?> - <?= e($title ?? 'Not Found') ?></title>
    <link rel="stylesheet" href="/public/assets/css/tailwind.min.css">
</head>
<body class="bg-slate-50">
    <main class="min-h-screen w-full flex flex-col justify-center items-center px-6 py-12">
        <div class="max-w-lg text-center">
            <div class="inline-flex items-center justify-center h-16 w-16 rounded-2xl bg-sky-100 ring-4 ring-sky-50 mb-6">
                <?= icon('question-mark-circle', 'h-8 w-8 text-sky-600', 'solid') ?>
            </div>
            <h1 class="text-6xl font-bold text-slate-800 tracking-tighter"><?= e($code) ?></h1>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900"><?= e($title) ?></h2>
            <p class="mt-4 text-slate-600">
                <?= e($joke) ?>
            </p>
            <p class="mt-2 text-xs text-slate-500">
                (<?= e($message) ?>)
            </p>
            <div class="mt-8">
                <a href="/public/index.php"
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-semibold
                          hover:opacity-95 active:opacity-90 transition focus:outline-none focus:ring-4 focus:ring-sky-100">
                    <?= icon('home', 'h-4 w-4 text-white', 'solid') ?>
                    <span>Return to Homepage</span>
                </a>
            </div>
        </div>
    </main>
</body>
</html>