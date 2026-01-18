<?php
declare(strict_types=1);

/**
 * Login view (two-step: verify email, then show password)
 *
 * Assumes:
 * - csrf_field()
 * - e()
 * - icon($name, $class = 'h-5 w-5', $variant = 'outline')
 */

$emailValue = '';
try {
  $emailValue = isset($_POST['email']) ? (string)$_POST['email'] : '';
} catch (Throwable $t) {
  $emailValue = '';
}
?>

<div class="min-h-[72vh] flex items-center justify-center px-4">
  <div class="w-full max-w-md">

    <div id="loginCard" class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden opacity-0 translate-y-2">
      <div class="h-1 bg-gradient-to-r from-sky-600 via-sky-500 to-sky-400"></div>

      <div class="p-6 md:p-8">
        <!-- Header -->
        <div class="flex items-start justify-between gap-4">
          <div class="flex items-center gap-3 min-w-0">
            <div class="h-10 w-10 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center shrink-0">
              <?= icon('lock-closed', 'h-5 w-5 text-sky-700', 'solid') ?>
            </div>
            <div class="min-w-0">
              <h1 class="text-xl font-semibold text-slate-900">Sign in</h1>
              <p class="text-sm text-slate-500 mt-1">Enter your email to continue.</p>
            </div>
          </div>

          <span class="hidden sm:inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs bg-white ring-1 ring-slate-200 text-slate-600">
            <?= icon('shield-check', 'h-4 w-4 text-slate-500', 'outline') ?>
            Secure
          </span>
        </div>

        <div class="mt-6 border-t border-slate-200"></div>

        <!-- Form -->
        <form id="loginForm" method="post" action="/public/index.php?r=login" class="mt-6 space-y-4">
          <?= csrf_field() ?>

          <input type="hidden" name="stage" id="stage" value="email">

          <!-- Email -->
          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-slate-700" for="email">Email</label>

            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <?= icon('envelope', 'h-5 w-5 text-slate-400', 'outline') ?>
              </div>

              <input
                id="email"
                name="email"
                type="email"
                required
                value="<?= e($emailValue) ?>"
                placeholder="you@example.com"
                autocomplete="email"
                class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-10 py-2.5 text-sm
                       focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
              />

              <!-- Verified check -->
              <div id="emailOkIcon" class="hidden absolute inset-y-0 right-0 items-center pr-3 text-emerald-600">
                <?= icon('check-circle', 'h-5 w-5', 'solid') ?>
              </div>
            </div>

            <!-- Status line -->
            <div id="emailMsg" class="text-xs text-slate-500 flex items-start gap-2 min-h-[18px]" aria-live="polite"></div>

            <!-- Change email -->
            <button type="button" id="changeEmailBtn"
                    class="hidden text-xs font-semibold text-slate-700 hover:text-slate-900 underline underline-offset-2 transition">
              Change email
            </button>
          </div>

          <!-- Password (hidden until email verified, but visible if JS disabled) -->
          <div id="passwordWrap" class="space-y-1.5">
            <label class="block text-sm font-medium text-slate-700" for="password">Password</label>

            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <?= icon('key', 'h-5 w-5 text-slate-400', 'outline') ?>
              </div>

              <input
                id="password"
                name="password"
                type="password"
                placeholder="Enter your password"
                autocomplete="current-password"
                class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-12 py-2.5 text-sm
                       focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
              />

              <button type="button" id="togglePwBtn"
                      class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-slate-700 transition"
                      aria-label="Show password">
                <span id="pwEyeOn"><?= icon('eye', 'h-5 w-5', 'outline') ?></span>
                <span id="pwEyeOff" class="hidden"><?= icon('eye-slash', 'h-5 w-5', 'outline') ?></span>
              </button>
            </div>

            <div class="flex items-center justify-between pt-1">
              <a class="text-sm text-slate-600 hover:text-slate-900 underline underline-offset-2 transition"
                 href="/public/index.php?r=forgot_password">
                Forgot password?
              </a>

              <span class="text-xs text-slate-500 inline-flex items-center gap-1">
                <?= icon('information-circle', 'h-4 w-4 text-slate-400', 'outline') ?>
                No public registration
              </span>
            </div>
          </div>

          <!-- Primary action -->
          <button
            type="submit"
            id="primaryBtn"
            class="w-full rounded-xl bg-slate-900 text-white py-2.5 text-sm font-semibold
                   hover:opacity-95 active:opacity-90
                   focus:outline-none focus:ring-4 focus:ring-sky-100 transition relative overflow-hidden"
          >
            <span class="inline-flex items-center justify-center gap-2">
              <span id="btnText">Continue</span>
              <span id="btnIcon" class="inline-flex"><?= icon('arrow-right', 'h-4 w-4 text-white', 'solid') ?></span>
              <span id="btnSpinner" class="hidden">
                <span class="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
              </span>
            </span>
          </button>

          <p class="text-xs text-slate-500 mt-2">
            If you need access, contact an admin.
          </p>
        </form>
      </div>

      <div class="px-6 md:px-8 pb-5">
        <div class="flex items-center justify-between text-xs text-slate-500">
          <span class="inline-flex items-center gap-2">
            <?= icon('globe-alt', 'h-4 w-4 text-slate-400', 'outline') ?>
            Syncithium
          </span>
          <span class="inline-flex items-center gap-2">
            <?= icon('lock-closed', 'h-4 w-4 text-slate-400', 'outline') ?>
            Session protected
          </span>
        </div>
      </div>
    </div>

    <div class="mt-4 text-center text-xs text-slate-500">
      Tip: Use a password manager. Your future self will thank you.
    </div>
  </div>
</div>

<style>
  @keyframes fadeInUp {
    0% { opacity: 0; transform: translateY(10px); }
    100% { opacity: 1; transform: translateY(0); }
  }
  @keyframes slideDown {
    0% { opacity: 0; transform: translateY(-6px); max-height: 0; }
    100% { opacity: 1; transform: translateY(0); max-height: 240px; }
  }
  .animate-fadeInUp { animation: fadeInUp .22s ease-out 1 forwards; }
  .animate-slideDown { animation: slideDown .18s ease-out 1 forwards; }
</style>

<script>
(function () {
  // Elements
  const card = document.getElementById('loginCard');
  const form = document.getElementById('loginForm');

  const stage = document.getElementById('stage');
  const email = document.getElementById('email');
  const emailMsg = document.getElementById('emailMsg');
  const emailOkIcon = document.getElementById('emailOkIcon');
  const changeEmailBtn = document.getElementById('changeEmailBtn');

  const pwWrap = document.getElementById('passwordWrap');
  const password = document.getElementById('password');

  const togglePwBtn = document.getElementById('togglePwBtn');
  const pwEyeOn = document.getElementById('pwEyeOn');
  const pwEyeOff = document.getElementById('pwEyeOff');

  const primaryBtn = document.getElementById('primaryBtn');
  const btnText = document.getElementById('btnText');
  const btnIcon = document.getElementById('btnIcon');
  const btnSpinner = document.getElementById('btnSpinner');

  // Entrance animation
  requestAnimationFrame(() => {
    card.classList.add('animate-fadeInUp');
    card.classList.remove('opacity-0', 'translate-y-2');
  });

  // Default: hide password until verified (JS-enabled behavior)
  let verified = false;
  hidePasswordUI();

  function setLoading(on) {
    primaryBtn.disabled = !!on;
    btnSpinner.classList.toggle('hidden', !on);
    btnIcon.classList.toggle('hidden', !!on);
  }

  function setEmailMessage(text, type) {
    // type: 'ok' | 'error' | 'info'
    const base = 'inline-flex items-start gap-2';
    const color = (type === 'error') ? 'text-rose-600' : (type === 'ok') ? 'text-emerald-700' : 'text-slate-500';
    const icon = (type === 'error')
      ? `<?= str_replace(["\n","\r"], '', icon('exclamation-circle', 'h-4 w-4 text-rose-500 mt-0.5', 'solid')) ?>`
      : (type === 'ok')
        ? `<?= str_replace(["\n","\r"], '', icon('check-circle', 'h-4 w-4 text-emerald-600 mt-0.5', 'solid')) ?>`
        : `<?= str_replace(["\n","\r"], '', icon('information-circle', 'h-4 w-4 text-slate-400 mt-0.5', 'outline')) ?>`;

    emailMsg.className = base + ' ' + color;
    emailMsg.innerHTML = icon + '<span>' + escapeHtml(text) + '</span>';
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (m) => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }

  function hidePasswordUI() {
    // If JS is running, we control visibility
    pwWrap.classList.add('hidden');
    password.required = false;
    btnText.textContent = 'Continue';
    stage.value = 'email';
    emailOkIcon.classList.add('hidden');
    changeEmailBtn.classList.add('hidden');
  }

  function showPasswordUI() {
    pwWrap.classList.remove('hidden');
    pwWrap.classList.add('animate-slideDown');
    password.required = true;
    btnText.textContent = 'Sign in';
    stage.value = 'password';
    emailOkIcon.classList.remove('hidden');
    changeEmailBtn.classList.remove('hidden');
    password.focus();
  }

  function lockEmail(lock) {
    email.readOnly = !!lock;
    email.classList.toggle('bg-slate-50', !!lock);
    email.classList.toggle('cursor-not-allowed', !!lock);
  }

  changeEmailBtn.addEventListener('click', () => {
    verified = false;
    lockEmail(false);
    hidePasswordUI();
    email.focus();
    setEmailMessage('Enter your email to continue.', 'info');
  });

  // Toggle password visibility
  togglePwBtn.addEventListener('click', () => {
    const isPw = password.type === 'password';
    password.type = isPw ? 'text' : 'password';
    pwEyeOn.classList.toggle('hidden', !isPw);
    pwEyeOff.classList.toggle('hidden', isPw);
    togglePwBtn.setAttribute('aria-label', isPw ? 'Hide password' : 'Show password');
  });

  // Submit handler: Step 1 verifies email, Step 2 submits to server
  form.addEventListener('submit', async (ev) => {
    if (verified) return; // proceed with normal POST login

    ev.preventDefault();

    const value = (email.value || '').trim();
    if (!value) {
      setEmailMessage('Please enter your email.', 'error');
      email.focus();
      return;
    }

    setLoading(true);
    setEmailMessage('Checking your email...', 'info');

    try {
      const url = '/public/index.php?r=api_login_email_check&email=' + encodeURIComponent(value);
      const res = await fetch(url, { method: 'GET', headers: { 'Accept': 'application/json' } });
      const data = await res.json().catch(() => ({}));

      if (!data || data.ok !== true) {
        setEmailMessage('Could not verify email. Please try again.', 'error');
        setLoading(false);
        return;
      }

      if (data.exists !== true) {
        setEmailMessage('Email not found. Contact an admin for access.', 'error');
        setLoading(false);
        return;
      }

      if (data.disabled === true) {
        setEmailMessage('This account is disabled. Contact an admin.', 'error');
        setLoading(false);
        return;
      }

      // Verified
      verified = true;
      lockEmail(true);
      setEmailMessage('Email verified. Please enter your password.', 'ok');
      showPasswordUI();

    } catch (e) {
      setEmailMessage('Network error. Please try again.', 'error');
    } finally {
      setLoading(false);
    }
  });

  // Initial hint
  setEmailMessage('Enter your email to continue.', 'info');
})();
</script>
