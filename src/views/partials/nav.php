<?php
/** @var array $config */
$user = current_user();
$base = base_url($config);
?>
<div style="border-bottom:1px solid #1f2937;background:#0b1f36;">
  <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:14px;padding-bottom:14px;">
    <div>
      <strong><?= e($config['app']['name'] ?? 'Syncithium') ?></strong>
      <span class="muted" style="margin-left:8px;">Practice questions, get sharper.</span>
    </div>
    <div style="display:flex;gap:10px;align-items:center;">
      <a href="<?= e($base) ?>/index.php">Home</a>
      <?php if ($user): ?>
        <a href="<?= e($base) ?>/index.php?r=quiz_start">Start Quiz</a>
        <a href="<?= e($base) ?>/index.php?r=my_attempts">My Attempts</a>
        <?php if (!empty($user['is_admin'])): ?>
          <a href="<?= e($base) ?>/index.php?r=admin_import">Import Questions</a>
        <?php endif; ?>
        <span class="badge"><?= e($user['name']) ?><?= !empty($user['is_admin']) ? ' (Admin)' : '' ?></span>
        <a href="<?= e($base) ?>/index.php?r=logout">Sign out</a>
      <?php else: ?>
        <a href="<?= e($base) ?>/index.php?r=login">Sign in</a>
        <a href="<?= e($base) ?>/index.php?r=register">Create account</a>
      <?php endif; ?>
    </div>
  </div>
</div>
