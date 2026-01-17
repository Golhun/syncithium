<?php
declare(strict_types=1);

$title = 'Login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_abort();

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        flash_set('error', 'Email and password are required.');
        redirect(url_for('login'));
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Do not reveal whether the email exists
    if (!$user) {
        flash_set('error', 'Invalid email or password.');
        redirect(url_for('login'));
    }

    // Block disabled accounts
    if (!empty($user['disabled_at'])) {
        flash_set('error', 'This account is disabled. Please contact the administrator.');
        redirect(url_for('login'));
    }

    // Support either password_hash (preferred) or legacy password
    $hash = (string)($user['password_hash'] ?? $user['password'] ?? '');
    if ($hash === '' || !password_verify($pass, $hash)) {
        flash_set('error', 'Invalid email or password.');
        redirect(url_for('login'));
    }

    // Login OK
    login_user((int)$user['id']);

    // First-login password change enforcement
    $mustChange = ((int)($user['must_change_password'] ?? 0) === 1);
    if ($mustChange) {
        flash_set('success', 'Welcome. Please change your password to continue.');
        redirect(url_for('change_password'));
    }

    flash_set('success', 'Welcome back.');
    redirect(url_for('home'));
}

ob_start();
?>
<div class="min-h-[70vh] flex items-center justify-center">
  <div class="w-full max-w-md">
    <div class="mb-6 text-center">
      <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900/60 ring-1 ring-slate-700/40">
        <!-- Lock icon (inline SVG, no dependencies) -->
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M7 11V8a5 5 0 0110 0v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <path d="M7 11h10a2 2 0 012 2v6a2 2 0 01-2 2H7a2 2 0 01-2-2v-6a2 2 0 012-2z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          <path d="M12 14v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </div>

      <h1 class="text-2xl font-semibold tracking-tight text-slate-100">Sign in</h1>
      <p class="mt-1 text-sm text-slate-400">Access your question practice and results.</p>
    </div>

    <div class="rounded-2xl bg-slate-950/30 ring-1 ring-slate-700/40 p-6">
      <form method="post" autocomplete="off" class="space-y-4" x-data="{show:false}">
        <?= csrf_field() ?>

        <div>
          <label class="block text-sm font-medium text-slate-200">Email</label>
          <div class="mt-1 relative">
            <input
              class="w-full rounded-xl bg-slate-900/40 ring-1 ring-slate-700/40 px-3 py-2 text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
              type="email"
              name="email"
              required
              autocomplete="username"
              inputmode="email"
              placeholder="you@example.com"
            >
            <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-500">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 6h16v12H4V6z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
              </svg>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-200">Password</label>
          <div class="mt-1 relative">
            <input
              class="w-full rounded-xl bg-slate-900/40 ring-1 ring-slate-700/40 px-3 py-2 pr-12 text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400"
              :type="show ? 'text' : 'password'"
              name="password"
              required
              autocomplete="current-password"
              placeholder="Your password"
            >
            <button
              type="button"
              class="absolute inset-y-0 right-2 my-auto inline-flex h-9 w-10 items-center justify-center rounded-lg text-slate-300 hover:text-slate-100 hover:bg-slate-800/50 ring-1 ring-transparent hover:ring-slate-700/40 focus:outline-none focus:ring-4 focus:ring-sky-100"
              @click="show = !show"
              :aria-label="show ? 'Hide password' : 'Show password'"
            >
              <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M12 15a3 3 0 100-6 3 3 0 000 6z" stroke="currentColor" stroke-width="1.6"/>
              </svg>
              <svg x-show="show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="display:none;">
                <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                <path d="M2 12s3.5-7 10-7c2.6 0 4.7.9 6.3 2.1M22 12s-3.5 7-10 7c-2.2 0-4.1-.6-5.7-1.5" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
              </svg>
            </button>
          </div>

          <p class="mt-2 text-xs text-slate-400">
            If this is your first login, you will be prompted to change your password.
          </p>
        </div>

        <div class="pt-1 flex items-center gap-3">
          <button
            class="inline-flex items-center justify-center rounded-xl px-4 py-2 font-medium text-white bg-sky-600 hover:bg-sky-500 ring-1 ring-slate-700/40 focus:outline-none focus:ring-4 focus:ring-sky-100"
            type="submit"
          >
            Sign in
          </button>

          <a
            class="inline-flex items-center justify-center rounded-xl px-4 py-2 font-medium text-slate-100 bg-slate-900/40 hover:bg-slate-800/50 ring-1 ring-slate-700/40 focus:outline-none focus:ring-4 focus:ring-sky-100"
            href="<?= e(url_for('home')) ?>"
          >
            Back
          </a>
        </div>
      </form>
    </div>

    <p class="mt-6 text-center text-xs text-slate-500">
      Syncithium is private access only. Contact your administrator if you need an account.
    </p>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
