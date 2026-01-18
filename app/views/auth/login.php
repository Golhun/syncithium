<?php
declare(strict_types=1);

$emailValue = '';
try {
    $emailValue = isset($_POST['email']) ? (string)$_POST['email'] : '';
} catch (Throwable $e) {
    $emailValue = '';
}

if (!function_exists('e')) {
    function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
?>

<div class="min-h-[72vh] flex items-center justify-center px-4">
  <div class="w-full max-w-md" x-data="loginCard()" x-init="init()">

    <div class="bg-white rounded-2xl ring-1 ring-slate-200 overflow-hidden">
      <div class="h-1 bg-gradient-to-r from-sky-600 via-sky-500 to-sky-400"></div>

      <div class="p-6 md:p-8">
        <div class="flex items-start gap-3">
          <div class="h-10 w-10 rounded-xl bg-sky-50 ring-1 ring-sky-100 flex items-center justify-center shrink-0">
            <?= icon('lock-closed', 'h-5 w-5 text-sky-700') ?>
          </div>
          <div class="min-w-0">
            <h1 class="text-xl font-semibold text-slate-900 leading-tight">Sign in</h1>
            <p class="text-sm text-slate-500 mt-1">Continue your quizzes and manage your workspace.</p>
          </div>
        </div>

        <form method="post" action="/public/index.php?r=login" class="mt-6 space-y-4" @submit="submitting=true">
          <?= csrf_field() ?>

          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <?= icon('envelope', 'h-5 w-5 text-slate-400') ?>
              </div>
              <input
                id="email"
                name="email"
                type="email"
                required
                value="<?= e($emailValue) ?>"
                placeholder="you@example.com"
                autocomplete="email"
                class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-3 py-2.5 text-sm
                       focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
              >
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
            <div class="relative">
              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                <?= icon('key', 'h-5 w-5 text-slate-400') ?>
              </div>

              <input
                id="password"
                name="password"
                :type="showPw ? 'text' : 'password'"
                required
                placeholder="Enter your password"
                autocomplete="current-password"
                class="w-full rounded-xl ring-1 ring-slate-200 bg-white pl-10 pr-12 py-2.5 text-sm
                       focus:outline-none focus:ring-4 focus:ring-sky-100 focus:border-sky-400 transition"
              >

              <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-slate-700 transition"
                @click="togglePw()"
                :aria-label="showPw ? 'Hide password' : 'Show password'"
              >
                <span x-show="!showPw"><?= icon('eye', 'h-5 w-5') ?></span>
                <span x-show="showPw" x-cloak><?= icon('eye-slash', 'h-5 w-5') ?></span>
              </button>
            </div>

            <div class="flex items-center justify-between pt-1">
              <a class="text-sm text-slate-600 hover:text-slate-900 underline underline-offset-2 transition"
                 href="/public/index.php?r=forgot_password">
                Forgot password?
              </a>

              <span class="text-xs text-slate-500 inline-flex items-center gap-1">
                <?= icon('information-circle', 'h-4 w-4 text-slate-400') ?>
                No public registration
              </span>
            </div>
          </div>

          <button
            type="submit"
            class="w-full rounded-xl bg-slate-900 text-white py-2.5 text-sm font-semibold
                   hover:opacity-95 active:opacity-90 focus:outline-none focus:ring-4 focus:ring-sky-100
                   transition inline-flex items-center justify-center gap-2"
            :disabled="submitting"
          >
            <span x-show="!submitting" class="inline-flex items-center gap-2">
              Sign in
              <?= icon('arrow-right', 'h-4 w-4 text-white') ?>
            </span>

            <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
              Signing in...
              <span class="h-4 w-4 rounded-full border-2 border-white/40 border-t-white animate-spin"></span>
            </span>
          </button>

          <p class="text-xs text-slate-500 flex items-start gap-2">
            <span class="mt-0.5"><?= icon('sparkles', 'h-4 w-4 text-sky-600') ?></span>
            <span>If you need access, contact an admin. Admins can also take quizzes as normal users.</span>
          </p>
        </form>

        <div class="mt-6 pt-5 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
          <span class="inline-flex items-center gap-2">
            <?= icon('globe-alt', 'h-4 w-4 text-slate-400') ?>
            Syncithium
          </span>
          <span class="inline-flex items-center gap-2">
            <?= icon('lock-closed', 'h-4 w-4 text-slate-400') ?>
            Session protected
          </span>
        </div>
      </div>
    </div>

    <div class="mt-4 text-center text-xs text-slate-500">
      Tip: Use a password manager, your future self will thank you.
    </div>
  </div>
</div>

<style>
@keyframes fadeInUp {
  0% { opacity: 0; transform: translateY(10px); }
  100% { opacity: 1; transform: translateY(0); }
}
</style>

<script>
function loginCard() {
  return {
    showPw: false,
    submitting: false,
    init() {
      requestAnimationFrame(() => {
        const surface = this.$root.querySelector('.rounded-2xl');
        if (!surface) return;
        surface.classList.add('animate-[fadeInUp_.18s_ease-out_1]');
      });
    },
    togglePw() { this.showPw = !this.showPw; }
  }
}
</script>
