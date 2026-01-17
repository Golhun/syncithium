<?php
declare(strict_types=1);

function redirect_after_auth(array $user): void {
  if (($user['role'] ?? 'user') === 'admin') {
    redirect('/public/index.php?r=admin_users');
  }
  redirect('/public/index.php?r=taxonomy_selector');
}
