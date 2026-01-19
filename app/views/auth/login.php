<?php
declare(strict_types=1);

/**
 * Login view (two-step: verify email, then show password)
 *
 * Change requested:
 * - Remove the “card” container
 * - Make the login layout fuse into the page
 * - Place the artwork on the actual page (top-right), scaled up, blended
 *
 * Assumes:
 * - csrf_field()
 * - e()
 * - icon($name, $class = 'h-5 w-5', $variant = 'outline')
 *
 * Image:
 * - Put PNG at: /public/assets/img/sync-heart.png
 */

$emailValue = '';
try {
  $emailValue = isset($_POST['email']) ? (string)$_POST['email'] : '';
} catch (Throwable $t) {
  $emailValue = '';
}
?>

<div class="w-full">
  <!-- Content -->
  <div class="relative max-w-6xl mx-auto px-4">
    <div class="grid lg:grid-cols-2 items-center gap-10 py-10">

      <!-- Left: Brand / context -->
      <div class="max-w-xl">


        <h1 class="mt-4 text-3xl sm:text-4xl font-semibold text-slate-900 leading-tight">
          Welcome back to <span class="text-sky-700">Syncithium</span>
        </h1>

        <p class="mt-3 text-slate-600 max-w-lg">
          Continue your learning journey.
        </p>
      </div>

      <!-- Right: Form (no card, fused into page) -->
      <div id="loginRoot" class="w-full max-w-md lg:justify-self-end opacity-0 translate-y-2">
        <form id="loginForm" method="post" action="/public/index.php?r=login" class="space-y-5" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="stage" id="stage" value="email">

          <!-- Form heading -->
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h2 class="text-xl font-semibold text-slate-900">Sign in</h2>
            </div>
          </div>

          <!-- Email -->
          <div class="space-y-1.5">

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
                class="w-full rounded-2xl ring-1 ring-slate-200 bg-white/80 backdrop-blur pl-10 pr-10 py-3 text-sm
                       focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
              />

              <div id="emailOkIcon" class="hidden absolute inset-y-0 right-0 items-center pr-3 text-emerald-600">
                <?= icon('check-circle', 'h-5 w-5', 'solid') ?>
              </div>
            </div>

            <div id="emailMsg" class="text-xs text-slate-500 flex items-start gap-2 min-h-[18px] transition-opacity duration-200" aria-live="polite"></div>

            <button type="button" id="changeEmailBtn"
                    class="hidden text-xs font-semibold text-slate-700 hover:text-slate-900 underline underline-offset-2 transition">
              Change email
            </button>
          </div>

          <!-- Password -->
          <div id="passwordWrap" class="hidden space-y-1.5">

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
                class="w-full rounded-2xl ring-1 ring-slate-200 bg-white/80 backdrop-blur pl-10 pr-12 py-3 text-sm
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
                Admin-provisioned
              </span>
            </div>
          </div>

          <!-- Primary action -->
          <button
            type="submit"
            id="primaryBtn"
            class="w-full rounded-2xl bg-slate-900 text-white py-3 text-sm font-semibold
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

          <p class="text-xs text-slate-500">
            If you need access, kindly contact an admin.
          </p>
        </form>
      </div>

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
  const root = document.getElementById('loginRoot');
  const form = document.getElementById('loginForm');

  const stage = document.getElementById('stage');
  const emailEl = document.getElementById('email');
  const emailMsg = document.getElementById('emailMsg');
  const emailOkIcon = document.getElementById('emailOkIcon');
  const changeEmailBtn = document.getElementById('changeEmailBtn');

  const pwWrap = document.getElementById('passwordWrap');
  const passwordEl = document.getElementById('password');

  const togglePwBtn = document.getElementById('togglePwBtn');
  const pwEyeOn = document.getElementById('pwEyeOn');
  const pwEyeOff = document.getElementById('pwEyeOff');

  const primaryBtn = document.getElementById('primaryBtn');
  const btnText = document.getElementById('btnText');
  const btnIcon = document.getElementById('btnIcon');
  const btnSpinner = document.getElementById('btnSpinner');

  // Entrance animation
  requestAnimationFrame(() => {
    root.classList.add('animate-fadeInUp');
    root.classList.remove('opacity-0', 'translate-y-2');
  });

  // Default: hide password until verified (JS-enabled behavior)
  let verified = false;

  function setLoading(on) {
    primaryBtn.disabled = !!on;
    btnSpinner.classList.toggle('hidden', !on);
    btnIcon.classList.toggle('hidden', !!on);
  }

  function setEmailMessage(text, type) {
    emailMsg.style.opacity = '0';
    const base = 'inline-flex items-start gap-2';
    const color = (type === 'error') ? 'text-rose-600' : (type === 'ok') ? 'text-emerald-700' : 'text-slate-500';
    const icon = (type === 'error')
      ? `<?= str_replace(["\n","\r"], '', icon('exclamation-circle', 'h-4 w-4 text-rose-500 mt-0.5', 'solid')) ?>`
      : (type === 'ok')
        ? `<?= str_replace(["\n","\r"], '', icon('check-circle', 'h-4 w-4 text-emerald-600 mt-0.5', 'solid')) ?>`
        : `<?= str_replace(["\n","\r"], '', icon('information-circle', 'h-4 w-4 text-slate-400 mt-0.5', 'outline')) ?>`;

    emailMsg.className = base + ' ' + color;
    emailMsg.innerHTML = icon + '<span>' + escapeHtml(text) + '</span>';
    requestAnimationFrame(() => { emailMsg.style.opacity = '1'; });
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (m) => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
  }

  function hidePasswordUI() {
    pwWrap.classList.add('hidden');
    passwordEl.required = false;
    btnText.textContent = 'Continue';
    stage.value = 'email';
    emailOkIcon.classList.add('hidden');
    changeEmailBtn.classList.add('hidden');
  }

  function showPasswordUI() {
    pwWrap.classList.remove('hidden');
    pwWrap.classList.add('animate-slideDown');
    passwordEl.required = true;
    btnText.textContent = 'Sign in';
    stage.value = 'password';
    emailOkIcon.classList.remove('hidden');
    changeEmailBtn.classList.remove('hidden');
    passwordEl.focus();
  }

  function lockEmail(lock) {
    emailEl.readOnly = !!lock;
    emailEl.classList.toggle('bg-slate-50', !!lock);
    emailEl.classList.toggle('cursor-not-allowed', !!lock);
  }

  changeEmailBtn.addEventListener('click', () => {
    verified = false;
    lockEmail(false);
    hidePasswordUI();
    emailEl.focus();
    setEmailMessage('Enter your email to continue.', 'info');
  });

  togglePwBtn.addEventListener('click', () => {
    const isPw = passwordEl.type === 'password';
    passwordEl.type = isPw ? 'text' : 'password';
    pwEyeOn.classList.toggle('hidden', !isPw);
    pwEyeOff.classList.toggle('hidden', isPw);
    togglePwBtn.setAttribute('aria-label', isPw ? 'Hide password' : 'Show password');
  });

  form.addEventListener('submit', async (ev) => {
    // If already verified, let the form submit normally to the backend
    if (verified) return; 
    
    ev.preventDefault();

    const value = (email.value || '').trim();
    if (!value) {
      setEmailMessage('Please enter your email.', 'error');
      emailEl.focus();
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

  setEmailMessage('Enter your email to continue.', 'info');
})();
</script>
