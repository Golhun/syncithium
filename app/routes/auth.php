<?php
declare(strict_types=1);

/**
 * Authentication and password flows.
 *
 * Routes:
 *  - login
 *  - logout
 *  - force_password_change
 *  - password_reset
 *  - forgot_password
 */

return [

    // =========================
    // Login
    // =========================
    'login' => function (PDO $db, array $config): void {
        if (is_post()) {
            csrf_verify();
            $email    = (string)($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if (attempt_login($db, $email, $password, $config)) {
                $u = current_user($db);

                if ($u && (int)$u['must_change_password'] === 1) {
                    redirect('/public/index.php?r=force_password_change');
                }

                if ($u) {
                    redirect_after_auth($u);
                }

                // Extremely defensive fallback
                logout();
                flash_set('error', 'Sign-in failed. Please try again.');
                redirect('/public/index.php?r=login');
            } else {
                // Avoid leaking whether account exists / locked / disabled
                flash_set('error', 'Sign-in failed. Please try again.');
            }
        }

        render('auth/login', ['title' => 'Sign in']);
    },

    // =========================
    // Logout
    // =========================
    'logout' => function (PDO $db, array $config): void {
        logout();
        flash_set('success', 'Signed out.');
        redirect('/public/index.php?r=login');
    },

    // =========================
    // Forced password change on first login
    // =========================
    'force_password_change' => function (PDO $db, array $config): void {
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
                $stmt = $db->prepare(
                    "UPDATE users
                     SET password_hash = :h,
                         must_change_password = 0
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':h'  => $hash,
                    ':id' => (int)$u['id'],
                ]);

                // Audit: user completed forced password change (self)
                audit_log_event(
                    $db,
                    (int)$u['id'],
                    'USER_PASSWORD_CHANGE',
                    'users',
                    (int)$u['id'],
                    ['flow' => 'forced_first_login']
                );

                flash_set('success', 'Password updated. Welcome.');

                $u2 = current_user($db);
                if ($u2) {
                    redirect_after_auth($u2);
                }

                redirect('/public/index.php?r=login');
            }
        }

        render('auth/force_password_change', [
            'title' => 'Set new password',
            'user'  => $u,
        ]);
    },

    // =========================
    // Token-based password reset (user enters token + new password)
    // =========================
    'password_reset' => function (PDO $db, array $config): void {
        if (is_post()) {
            csrf_verify();

            $token = trim((string)($_POST['token'] ?? ''));
            $p1    = (string)($_POST['password'] ?? '');
            $p2    = (string)($_POST['password_confirm'] ?? '');

            $minLen = (int)($config['security']['password_min_len'] ?? 10);

            if ($token === '') {
                flash_set('error', 'Token is required.');
                redirect('/public/index.php?r=password_reset');
            }
            if (strlen($p1) < $minLen) {
                flash_set('error', "Password must be at least {$minLen} characters.");
                redirect('/public/index.php?r=password_reset');
            }
            if ($p1 !== $p2) {
                flash_set('error', 'Passwords do not match.');
                redirect('/public/index.php?r=password_reset');
            }

            // Same hashing logic as token generation
            if (function_exists('reset_token_hash')) {
                $th = reset_token_hash($token, $config);
            } else {
                $th = hash('sha256', $token);
            }

            $stmt = $db->prepare(
                "SELECT pr.id AS pr_id,
                        pr.user_id,
                        pr.expires_at,
                        pr.used_at,
                        u.disabled_at
                 FROM password_resets pr
                 JOIN users u ON u.id = pr.user_id
                 WHERE pr.token_hash = :th
                 LIMIT 1"
            );
            $stmt->execute([':th' => $th]);
            $row = $stmt->fetch();

            if (
                !$row ||
                !empty($row['used_at']) ||
                strtotime((string)$row['expires_at']) <= time() ||
                !empty($row['disabled_at'])
            ) {
                flash_set('error', 'Token is invalid or expired.');
                redirect('/public/index.php?r=password_reset');
            }

            $hash = password_hash($p1, PASSWORD_DEFAULT);

            $stmt = $db->prepare(
                "UPDATE users
                 SET password_hash = :h,
                     must_change_password = 0,
                     failed_attempts = 0,
                     last_failed_at = NULL,
                     lockout_until = NULL
                 WHERE id = :uid"
            );
            $stmt->execute([
                ':h'   => $hash,
                ':uid' => (int)$row['user_id'],
            ]);

            $stmt = $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => (int)$row['pr_id']]);

            audit_log_event(
                $db,
                null,
                'PASSWORD_RESET_TOKEN_USED',
                'users',
                (int)$row['user_id']
            );

            flash_set('success', 'Password updated. You can sign in now.');
            redirect('/public/index.php?r=login');
        }

        render('auth/password_reset', ['title' => 'Reset password']);
    },

    // =========================
    // Public: Forgot password (creates request record)
    // =========================
    'forgot_password' => function (PDO $db, array $config): void {
        // No login required for this route.

        if (is_post()) {
            csrf_verify();

            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $note  = trim((string)($_POST['note'] ?? ''));

            // Always respond generically to avoid account enumeration.
            $userId = null;
            if ($email !== '') {
                $stmt = $db->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
                $stmt->execute([':e' => $email]);
                $u = $stmt->fetch();
                if ($u) {
                    $userId = (int)$u['id'];
                }
            }

            $stmt = $db->prepare(
                "INSERT INTO password_reset_requests (user_id, email, note, status, request_ip, user_agent)
                 VALUES (:uid, :email, :note, 'open', :ip, :ua)"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':email' => ($email === '' ? 'unknown' : $email),
                ':note'  => ($note === '' ? null : $note),
                ':ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'    => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);

            // Generic message to the user
            flash_set(
                'success',
                'If an account exists for that email, your request has been recorded. Contact your admin for the reset token.'
            );
            redirect('/public/index.php?r=login');
        }

        render('auth/forgot_password', ['title' => 'Request Password Reset']);
    },

];
