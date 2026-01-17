<?php
declare(strict_types=1);

require_admin();

$title = 'Admin: Users';

$created_users = [];   // [['email' => ..., 'temp_password' => ..., 'is_admin' => 0/1]]
$bulk_summary  = null; // ['created' => int, 'skipped' => int]
$token_result  = null; // ['email' => ..., 'token' => ..., 'expires_at' => ...]
$reset_result  = null; // ['email' => ..., 'temp_password' => ...]
$errors = [];

/**
 * Generate a reasonable temporary password.
 * Avoids ambiguous characters and produces something users can type.
 */
function generate_temp_password(int $length = 12): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#%';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}

/**
 * Normalize and validate email.
 */
function normalize_email(string $email): ?string
{
    $email = strtolower(trim($email));
    if ($email === '') return null;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
    return $email;
}

/**
 * Create a user if not exists. Returns array result.
 */
function admin_create_user(string $email, bool $isAdmin, ?string $tempPassword = null): array
{
    $pdo = db();

    $tempPassword = $tempPassword ?: generate_temp_password(12);
    $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

    // Insert with must_change_password=1
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, is_admin, must_change_password, created_at)
        VALUES (:email, :ph, :is_admin, 1, :created_at)
    ");
    $stmt->execute([
        ':email' => $email,
        ':ph' => $hash,
        ':is_admin' => $isAdmin ? 1 : 0,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);

    return [
        'email' => $email,
        'temp_password' => $tempPassword,
        'is_admin' => $isAdmin ? 1 : 0,
    ];
}

/**
 * Generate a password reset token for a user.
 * Stores sha256(token) in DB so the raw token is never stored.
 */
function admin_generate_reset_token(int $userId, int $ttlMinutes = 1440): array
{
    $pdo = db();

    $token = bin2hex(random_bytes(24)); // ~48 chars
    $tokenHash = hash('sha256', $token);

    $expiresAt = (new DateTimeImmutable('now'))
        ->modify('+' . $ttlMinutes . ' minutes')
        ->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        UPDATE users
           SET reset_token_hash = :h,
               reset_token_created_at = :ca,
               reset_token_expires_at = :ea
         WHERE id = :id
         LIMIT 1
    ");
    $stmt->execute([
        ':h' => $tokenHash,
        ':ca' => date('Y-m-d H:i:s'),
        ':ea' => $expiresAt,
        ':id' => $userId,
    ]);

    $emailStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
    $emailStmt->execute([':id' => $userId]);
    $email = (string)($emailStmt->fetchColumn() ?: '');

    return [
        'email' => $email,
        'token' => $token,
        'expires_at' => $expiresAt,
    ];
}

/**
 * Reset user password to a new temp password and force password change on login.
 * Also clears any reset token.
 */
function admin_reset_temp_password(int $userId): array
{
    $pdo = db();

    $tempPassword = generate_temp_password(12);
    $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users
           SET password_hash = :ph,
               must_change_password = 1,
               reset_token_hash = NULL,
               reset_token_expires_at = NULL,
               reset_token_created_at = NULL
         WHERE id = :id
         LIMIT 1
    ");
    $stmt->execute([
        ':ph' => $hash,
        ':id' => $userId,
    ]);

    $emailStmt = $pdo->prepare("SELECT email FROM users WHERE id = :id LIMIT 1");
    $emailStmt->execute([':id' => $userId]);
    $email = (string)($emailStmt->fetchColumn() ?: '');

    return [
        'email' => $email,
        'temp_password' => $tempPassword,
    ];
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'create_single') {
            $email = normalize_email((string)($_POST['email'] ?? ''));
            $isAdmin = !empty($_POST['is_admin']);

            if (!$email) {
                $errors[] = 'Provide a valid email address.';
            } else {
                // Check exists
                $chk = db()->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
                $chk->execute([':e' => $email]);
                $exists = $chk->fetchColumn();

                if ($exists) {
                    $errors[] = 'User already exists: ' . $email;
                } else {
                    $created_users[] = admin_create_user($email, $isAdmin);
                    flash_set('success', 'User created.');
                }
            }
        }

        if ($action === 'create_bulk') {
            $raw = (string)($_POST['bulk_emails'] ?? '');
            $isAdmin = !empty($_POST['bulk_is_admin']);

            $parts = preg_split('/[\r\n,; ]+/', $raw) ?: [];
            $emails = [];
            foreach ($parts as $p) {
                $e = normalize_email($p);
                if ($e) $emails[$e] = true;
            }
            $emails = array_keys($emails);

            if (!$emails) {
                $errors[] = 'Paste at least one valid email address.';
            } else {
                $created = 0;
                $skipped = 0;

                $pdo = db();
                $pdo->beginTransaction();

                try {
                    foreach ($emails as $email) {
                        $chk = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
                        $chk->execute([':e' => $email]);
                        $exists = $chk->fetchColumn();

                        if ($exists) {
                            $skipped++;
                            continue;
                        }

                        $row = admin_create_user($email, $isAdmin);
                        $created_users[] = $row;
                        $created++;
                    }

                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }

                $bulk_summary = ['created' => $created, 'skipped' => $skipped];
                flash_set('success', 'Bulk user creation completed.');
            }
        }

        if ($action === 'gen_reset_token') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                $errors[] = 'Invalid user.';
            } else {
                $token_result = admin_generate_reset_token($userId, 1440); // 24 hours
                flash_set('success', 'Reset token generated.');
            }
        }

        if ($action === 'reset_temp_password') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                $errors[] = 'Invalid user.';
            } else {
                $reset_result = admin_reset_temp_password($userId);
                flash_set('success', 'Temporary password set.');
            }
        }

        if (!$errors) {
            // Continue to render the page with results shown below
        }
    } catch (Throwable $e) {
        $errors[] = 'Operation failed: ' . $e->getMessage();
    }
}

// Load users list
$users = db()->query("
    SELECT id, email,
           COALESCE(is_admin, 0) AS is_admin,
           COALESCE(must_change_password, 0) AS must_change_password,
           reset_token_expires_at,
           created_at
      FROM users
  ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>
  <h1>Users</h1>
  <p class="muted">Registration is disabled. Admins create users and share temporary passwords. Users must change password on first login.</p>

  <?php if ($errors): ?>
    <div class="card" style="border-left:4px solid #ef4444; margin-bottom:12px;">
      <p><strong>Fix the following:</strong></p>
      <ul>
        <?php foreach ($errors as $err): ?>
          <li><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($bulk_summary): ?>
    <div class="card" style="margin-bottom:12px;">
      <p><strong>Bulk summary</strong></p>
      <p>Created: <?= (int)$bulk_summary['created'] ?>, Skipped (already existed): <?= (int)$bulk_summary['skipped'] ?></p>
    </div>
  <?php endif; ?>

  <?php if ($created_users): ?>
    <div class="card" style="margin-bottom:12px;">
      <p><strong>Copy these credentials now</strong></p>
      <p class="muted">Passwords are shown once here. Store them securely, then share individually.</p>
      <table class="table">
        <thead>
          <tr>
            <th>Email</th>
            <th>Temp password</th>
            <th>Admin</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($created_users as $cu): ?>
            <tr>
              <td><?= e($cu['email']) ?></td>
              <td><code><?= e($cu['temp_password']) ?></code></td>
              <td><?= !empty($cu['is_admin']) ? 'Yes' : 'No' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($reset_result): ?>
    <div class="card" style="margin-bottom:12px;">
      <p><strong>Temporary password reset</strong></p>
      <p>Email: <code><?= e($reset_result['email']) ?></code></p>
      <p>Temp password: <code><?= e($reset_result['temp_password']) ?></code></p>
      <p class="muted">User will be forced to change it at next login.</p>
    </div>
  <?php endif; ?>

  <?php if ($token_result): ?>
    <div class="card" style="margin-bottom:12px;">
      <p><strong>Password reset token generated</strong></p>
      <p>Email: <code><?= e($token_result['email']) ?></code></p>
      <p>Token: <code><?= e($token_result['token']) ?></code></p>
      <p>Expires at: <code><?= e($token_result['expires_at']) ?></code></p>
      <p class="muted">User can reset here:</p>
      <p>
        <a class="btn btn-secondary" href="<?= e(url_for('reset_password', ['email' => $token_result['email'], 'token' => $token_result['token']])) ?>">
          Open reset page
        </a>
      </p>
    </div>
  <?php endif; ?>

  <div class="grid grid-2" style="margin-bottom:16px;">
    <div class="card">
      <h2 style="margin-top:0;">Create single user</h2>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_single">

        <label>Email</label>
        <input type="email" name="email" required>

        <div style="height:10px;"></div>

        <label>
          <input type="checkbox" name="is_admin" value="1">
          Make admin
        </label>

        <div style="height:14px;"></div>

        <button class="btn" type="submit">Create</button>
      </form>
    </div>

    <div class="card">
      <h2 style="margin-top:0;">Bulk create users</h2>
      <p class="muted">Paste emails separated by new lines, commas, or spaces.</p>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_bulk">

        <label>Emails</label>
        <textarea name="bulk_emails" rows="8" required></textarea>

        <div style="height:10px;"></div>

        <label>
          <input type="checkbox" name="bulk_is_admin" value="1">
          Make all admins
        </label>

        <div style="height:14px;"></div>

        <button class="btn" type="submit">Bulk create</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h2 style="margin-top:0;">Existing users</h2>

    <table class="table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Email</th>
          <th>Admin</th>
          <th>Must change password</th>
          <th>Reset token expires</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= e((string)$u['email']) ?></td>
            <td><?= !empty($u['is_admin']) ? 'Yes' : 'No' ?></td>
            <td><?= !empty($u['must_change_password']) ? 'Yes' : 'No' ?></td>
            <td><?= e((string)($u['reset_token_expires_at'] ?? '')) ?></td>
            <td>
              <form method="post" style="display:inline-block; margin-right:8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="gen_reset_token">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-secondary" type="submit">Generate reset token</button>
              </form>

              <form method="post" style="display:inline-block;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_temp_password">
                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-danger" type="submit">Reset temp password</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
