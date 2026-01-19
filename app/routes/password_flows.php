<?php
declare(strict_types=1);

return [

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
    // Public: Forgot password (creates request record)
    // =========================
    'forgot_password' => function (PDO $db, array $config): void {
        if (is_post()) {
            csrf_verify();

            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $note  = trim((string)($_POST['note'] ?? ''));

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

            flash_set(
                'success',
                'If an account exists for that email, your request has been recorded. Contact your admin for your temporary password.'
            );
            redirect('/public/index.php?r=login');
        }

        render('auth/forgot_password', ['title' => 'Request Password Reset']);
    },
];