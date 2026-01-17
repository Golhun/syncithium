<?php
declare(strict_types=1);

return [

  'login' => function(PDO $db, array $config): void {
    if (is_post()) {
      csrf_verify();
      $email = (string)($_POST['email'] ?? '');
      $password = (string)($_POST['password'] ?? '');

      if (attempt_login($db, $email, $password, $config)) {
        $u = current_user($db);

        if ($u && (int)$u['must_change_password'] === 1) {
          redirect('/public/index.php?r=force_password_change');
        }

        if ($u) {
          redirect_after_auth($u);
        }

        logout();
        flash_set('error', 'Sign-in failed. Please try again.');
        redirect('/public/index.php?r=login');
      }

      flash_set('error', 'Sign-in failed. Please try again.');
    }

    render('auth/login', ['title' => 'Sign in']);
  },

  'logout' => function(PDO $db, array $config): void {
    logout();
    flash_set('success', 'Signed out.');
    redirect('/public/index.php?r=login');
  },

  'force_password_change' => function(PDO $db, array $config): void {
    $u = require_login($db);

    if ((int)$u['must_change_password'] !== 1) {
      redirect_after_auth($u);
    }

    if (is_post()) {
      csrf_verify();
      $p1 = (string)($_POST['password'] ?? '');
      $p2 = (string)($_POST['password_confirm'] ?? '');

      $minLen = (int)($config['security']['password_min_len'] ?? 10);

      if (strlen($p1) < $minLen) {
        flash_set('error', "Password must be at least {$minLen} characters.");
      } elseif ($p1 !== $p2) {
        flash_set('error', 'Passwords do not match.');
      } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE users SET password_hash = :h, must_change_password = 0 WHERE id = :id");
        $stmt->execute([':h' => $hash, ':id' => (int)$u['id']]);

        audit_log_event($db, (int)$u['id'], 'USER_PASSWORD_CHANGE', 'users', (int)$u['id'], [
          'flow' => 'forced_first_login'
        ]);

        flash_set('success', 'Password updated. Welcome.');
        $u2 = current_user($db);
        if ($u2) redirect_after_auth($u2);

        redirect('/public/index.php?r=login');
      }
    }

    render('auth/force_password_change', ['title' => 'Set new password', 'user' => $u]);
  },

];
