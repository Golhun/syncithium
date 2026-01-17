<?php
$flashes = flash_get_all();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Syncithium') ?></title>

  <!-- Tailwind output (local build). If not present yet, app.css still gives basic readability. -->
  <link rel="stylesheet" href="/public/assets/css/tailwind.min.css">
  <link rel="stylesheet" href="/public/assets/css/app.css">

  <!-- Alertify (local) -->
  <link rel="stylesheet" href="/public/assets/css/alertify.min.css">
  <link rel="stylesheet" href="/public/assets/css/alertify.default.min.css">
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">

  <div class="max-w-5xl mx-auto p-4 md:p-8">
    <?php require $view_file; ?>
  </div>

  <script src="/public/assets/js/alertify.min.js"></script>
  <script src="/public/assets/js/alpine.min.js" defer></script>
  <script src="/public/assets/js/app.js"></script>

  <script>
    window.__FLASH__ = <?= json_encode($flashes, JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script>
    (function () {
      const items = window.__FLASH__ || [];
      if (!window.alertify) return;
      items.forEach(({type, message}) => {
        if (type === 'success') alertify.success(message);
        else if (type === 'error') alertify.error(message);
        else alertify.message(message);
      });
    })();
  </script>
</body>
</html>
