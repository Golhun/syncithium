<?php
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
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo e($title ?? 'Error'); ?> (<?php echo e($code ?? ''); ?>)</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:40px}
    .wrap{max-width:760px;margin:0 auto;background:#fff;padding:28px;border-radius:8px;box-shadow:0 6px 18px rgba(2,6,23,.08); text-align: center;}
    h1{margin:0 0 8px;font-size:22px}
    p{margin:6px 0;color:#334155}
    .code{font-weight:700;color:#ef4444; font-size: 4rem; display: block; margin-bottom: 1rem;}
    .joke{margin-top:18px;color:#64748b;font-style:italic}
    a { display: inline-block; margin-top: 2rem; background: #0f172a; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; }
  </style>
</head>
<body>
  <div class="wrap">
    <span class="code"><?php echo e($code ?? ''); ?></span>
    <h1><?php echo e($title ?? 'Error'); ?></h1>
    <p><?php echo e($message ?? 'An unexpected error occurred.'); ?></p>
    <p class="joke"><?php echo e($joke ?? 'Please contact the site administrator.'); ?></p>
    <a href="/public/index.php">Return to Homepage</a>
  </div>
</body>
</html>