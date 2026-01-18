<?php
declare(strict_types=1);

return [

  // GET /public/index.php?r=api_login_email_check&email=you@example.com
  'api_login_email_check' => function (PDO $db, array $config): void {
    header('Content-Type: application/json; charset=utf-8');

    $email = trim((string)($_GET['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      echo json_encode(['ok' => false, 'message' => 'Enter a valid email.'], JSON_UNESCAPED_SLASHES);
      exit;
    }

    $stmt = $db->prepare("SELECT id, disabled_at FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$u) {
      echo json_encode(['ok' => true, 'exists' => false], JSON_UNESCAPED_SLASHES);
      exit;
    }

    $disabled = !empty($u['disabled_at']);

    echo json_encode([
      'ok' => true,
      'exists' => true,
      'disabled' => $disabled,
    ], JSON_UNESCAPED_SLASHES);
    exit;
  },

];
