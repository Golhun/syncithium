<?php
declare(strict_types=1);

/**
 * Authentication and password flows.
 *
 * Routes:
 *  - login
 *  - logout
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

];
