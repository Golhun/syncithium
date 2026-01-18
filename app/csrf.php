<?php
declare(strict_types=1);

function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_field(): string {
  return '<input type="hidden" name="csrf_token" value="'.e(csrf_token()).'">';
}

function csrf_verify(): void {
    // 1. Only run verify on POST requests
    if (!is_post()) return;

    // 2. Retrieve tokens
    $sent = $_POST['csrf_token'] ?? '';
    $real = $_SESSION['csrf_token'] ?? '';

    // 3. Check if tokens match
    if (!$sent || !$real || !hash_equals($real, $sent)) {
        
        // --- PATCH START ---
        
        // Check if this is an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($isAjax) {
            // Return JSON payload with 419 status for JS to handle
            http_json(['ok' => false, 'error' => 'Session expired. Refresh and try again.'], 419);
            exit; // Ensure script stops here
        }

        // Standard Request: Flash error and Redirect
        flash_set('error', 'Session expired. Please try again.');
        redirect('/public/index.php');
        exit; // Ensure script stops here
        
        // --- PATCH END ---
    }
}
