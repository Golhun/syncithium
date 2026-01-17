<?php
$title = 'Home';

$base = base_url($config);
$user = current_user();

ob_start();
?>
  <h1>Practice smarter with Syncithium</h1>
  <p class="muted">This app is built for answering questions, tracking attempts, and reviewing weak areas.</p>

  <?php if (!$user): ?>
    <div class="card">
      <p><strong>Start here</strong></p>
      <p><a class="btn" href="<?= e($base) ?>/index.php?r=register">Create an account</a> <a class="btn secondary" href="<?= e($base) ?>/index.php?r=login">Sign in</a></p>
      <p class="muted">Note: The first registered user becomes an Admin and can import questions.</p>
    </div>
  <?php else: ?>
    <div class="grid">
      <div class="card">
        <h2 style="margin-top:0;">Start a practice test</h2>
        <p class="muted">Pick a subject/topic and number of questions, then run the test.</p>
        <p><a class="btn" href="<?= e($base) ?>/index.php?r=quiz_start">Create a test</a></p>
      </div>
      <div class="card">
        <h2 style="margin-top:0;">View your attempts</h2>
        <p class="muted">See scores, review wrong answers, and repeat topics.</p>
        <p><a class="btn secondary" href="<?= e($base) ?>/index.php?r=my_attempts">My attempts</a></p>
      </div>
      <?php if (!empty($user['is_admin'])): ?>
      <div class="card">
        <h2 style="margin-top:0;">Admin: Import questions</h2>
        <p class="muted">Upload a CSV question bank for everyone to use.</p>
        <p><a class="btn secondary" href="<?= e($base) ?>/index.php?r=admin_import">Import CSV</a></p>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
