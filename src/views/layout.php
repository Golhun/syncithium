<?php
declare(strict_types=1);

/** @var array $config */
/** @var string $title */
/** @var string $content */

$user = current_user();
$appName = (string)(app_config('app.name', 'Syncithium'));

$base = rtrim(base_url(), '/');

// Absolute path to /public for loading local SVG icons
$publicRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';

/**
 * Inline a Heroicon SVG from local folders.
 *
 * Supports several common Heroicons folder layouts.
 * Usage:
 *   <?= heroicon('academic-cap', 'outline', 'w-5 h-5 text-sky-400') ?>
 */
function heroicon(string $name, string $variant = 'outline', string $class = 'w-5 h-5'): string
{
    $variant = strtolower($variant) === 'solid' ? 'solid' : 'outline';

    // Candidate paths for different Heroicons layouts
    // Adjust/add candidates if your folder differs.
    $candidates = [
        // v2 common
        "/assets/icons/heroicons/24/{$variant}/{$name}.svg",
        "/assets/icons/heroicons/20/{$variant}/{$name}.svg",
        // simplified custom
        "/assets/icons/heroicons/{$variant}/{$name}.svg",
        // if you kept original v1 style names
        "/assets/icons/heroicons/{$variant}/{$name}.svg",
    ];

    $publicRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';

    $svg = null;
    foreach ($candidates as $rel) {
        $abs = $publicRoot . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            $svg = file_get_contents($abs);
            break;
        }
    }

    if (!$svg) {
        // Fail quietly (avoid breaking page rendering)
        return '';
    }

    // Inject class into the first <svg ...> tag
    $svg = preg_replace(
        '/<svg\b(?![^>]*\bclass=)([^>]*)>/i',
        '<svg$1 class="' . htmlspecialchars($class, ENT_QUOTES) . '" aria-hidden="true">',
        $svg,
        1
    );

    // If SVG already has class, append our classes
    if (!str_contains($svg, 'aria-hidden=')) {
        $svg = preg_replace('/<svg\b([^>]*)>/i', '<svg$1 aria-hidden="true">', $svg, 1);
    }
    $svg = preg_replace(
        '/class="([^"]*)"/i',
        'class="$1 ' . htmlspecialchars($class, ENT_QUOTES) . '"',
        $svg,
        1
    );

    return $svg;
}

?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | <?= e($appName) ?></title>

  <!-- Tailwind output (local). Ensure this file exists. -->
  <link rel="stylesheet" href="<?= e($base) ?>/assets/css/app.css">

  <!-- Alpine (local) -->
  <script defer src="<?= e($base) ?>/assets/js/alpine.min.js"></script>

  <!-- Small progressive enhancement, avoid FOUC for Alpine -->
  <style>[x-cloak]{display:none!important}</style>
</head>

<body class="h-full bg-slate-950 text-slate-100">
  <div class="min-h-full">

    <?php require __DIR__ . '/partials/nav.php'; ?>

    <main class="mx-auto w-full max-w-6xl px-4 sm:px-6 lg:px-8 py-6">
      <?php if ($msg = flash_get('success')): ?>
        <div
          class="mb-4 rounded-xl border border-emerald-700/30 bg-emerald-500/10 px-4 py-3 text-emerald-200
                 transition duration-200 ease-out"
          role="status"
        >
          <div class="flex items-start gap-3">
            <div class="mt-0.5">
              <?= heroicon('check-circle', 'solid', 'w-5 h-5 text-emerald-300') ?>
            </div>
            <div class="leading-6">
              <?= e($msg) ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($msg = flash_get('error')): ?>
        <div
          class="mb-4 rounded-xl border border-rose-700/30 bg-rose-500/10 px-4 py-3 text-rose-200
                 transition duration-200 ease-out"
          role="alert"
        >
          <div class="flex items-start gap-3">
            <div class="mt-0.5">
              <?= heroicon('exclamation-triangle', 'solid', 'w-5 h-5 text-rose-300') ?>
            </div>
            <div class="leading-6">
              <?= e($msg) ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Page content -->
      <section class="rounded-2xl border border-slate-800 bg-slate-900/40 p-4 sm:p-6 shadow-sm">
        <?= $content ?>
      </section>

      <footer class="mt-8 text-xs text-slate-400">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
          <div>
            <span class="font-medium text-slate-300"><?= e($appName) ?></span>
            <span class="text-slate-500">, quiz practice and review</span>
          </div>
          <div class="text-slate-500">
            <?= $user ? ('Signed in as ' . e((string)($user['email'] ?? ''))) : 'Not signed in' ?>
          </div>
        </div>
      </footer>
    </main>

  </div>
</body>
</html>
