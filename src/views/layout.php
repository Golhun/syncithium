<?php
/** @var array $config */
/** @var string $title */
/** @var string $content */
$user = current_user();
$base = rtrim(base_url(), '/');
$appName = (string)(app_config('app.name', 'Syncithium'));

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> | <?= e($appName) ?></title>

  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; margin: 0; background: #0b1220; color: #e5e7eb; }
    a { color: #38bdf8; text-decoration: none; }
    a:hover { text-decoration: underline; }
    .container { max-width: 960px; margin: 0 auto; padding: 24px; }
    .card { background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 16px; }
    .grid { display: grid; gap: 16px; }
    .grid-2 { grid-template-columns: 1fr; }
    @media (min-width: 860px){ .grid-2 { grid-template-columns: 1fr 1fr; } }
    .btn { display: inline-block; padding: 10px 14px; border-radius: 10px; border: 1px solid #1f2937; background: #0b3a60; color: #fff; cursor: pointer; }
    .btn:hover { filter: brightness(1.08); }
    .btn-secondary { background: #0b1f36; }
    .btn-danger { background: #7f1d1d; }
    input, select, textarea { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid #1f2937; background: #0b1220; color: #e5e7eb; }
    label { display: block; margin-bottom: 6px; color: #cbd5e1; }
    .row { display: flex; gap: 12px; }
    .row > div { flex: 1; }
    .muted { color: #94a3b8; }
    .flash { border-radius: 12px; padding: 12px; margin-bottom: 12px; border: 1px solid #1f2937; }
    .flash-success { background: rgba(34,197,94,0.12); }
    .flash-error { background: rgba(239,68,68,0.12); }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 10px 8px; border-bottom: 1px solid #1f2937; text-align: left; vertical-align: top; }
    .badge { display: inline-block; padding: 3px 8px; border: 1px solid #1f2937; border-radius: 999px; font-size: 12px; }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/nav.php'; ?>
  <div class="container">
    <?php if ($msg = flash_get('success')): ?>
      <div class="flash flash-success"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($msg = flash_get('error')): ?>
      <div class="flash flash-error"><?= e($msg) ?></div>
    <?php endif; ?>

    <?= $content ?>
  </div>
</body>
</html>
