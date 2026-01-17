<div class="max-w-lg mx-auto bg-white border border-slate-200 rounded-xl p-6">
  <h1 class="text-2xl font-semibold mb-2">Request password reset</h1>
  <p class="text-sm text-slate-600 mb-6">Submit your email. Admin will generate a one-time reset token for you.</p>

  <form method="post" action="/public/index.php?r=forgot_password" class="space-y-4">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium mb-1">Email</label>
      <input name="email" type="email" required
        class="w-full rounded-lg border border-slate-300 px-3 py-2">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Note (optional)</label>
      <textarea name="note" rows="3"
        class="w-full rounded-lg border border-slate-300 px-3 py-2"></textarea>
    </div>

    <button class="w-full rounded-lg bg-slate-900 text-white px-4 py-2 hover:opacity-95">
      Submit request
    </button>

    <div class="text-sm mt-3">
      <a class="underline text-slate-700" href="/public/index.php?r=login">Back to login</a>
    </div>
  </form>
</div>
