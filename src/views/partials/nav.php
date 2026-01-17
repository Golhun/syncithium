<?php
/** @var array $config */
$user = current_user();
$base = rtrim(base_url(), '/');
?>
<div style="border-bottom:1px solid #1f2937;background:#0b1f36;">
  <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:14px;padding-bottom:14px;">
    <div>
      <strong><?= e($config['app']['name'] ?? 'Syncithium') ?></strong>
      <span class="muted" style="margin-left:8px;">Practice questions, get sharper.</span>
    </div>

    <div style="display:flex;gap:10px;align-items:center;">
      <a href="<?= e(url_for('home')) ?>">Home</a>

      <?php if ($user): ?>
        <a href="<?= e(url_for('quiz_start')) ?>">Start Quiz</a>
        <a href="<?= e(url_for('my_attempts')) ?>">My Attempts</a>

        <?php if (is_admin_user($user)): ?>
          <a href="<?= e(url_for('admin_import')) ?>">Import Questions</a>
          <a href="<?= e(url_for('admin_users')) ?>">Users</a>
        <?php endif; ?>

        <a href="<?= e(url_for('change_password')) ?>">Change Password</a>

        <span class="badge">
          <?= e((string)($user['email'] ?? '')) ?>
          <?= is_admin_user($user) ? ' (Admin)' : '' ?>
        </span>

        <a href="<?= e(url_for('logout')) ?>">Sign out</a>
      <?php else: ?>
        <a href="<?= e(url_for('login')) ?>">Sign in</a>
      <?php endif; ?>
    </div>
  </div>
</div>
