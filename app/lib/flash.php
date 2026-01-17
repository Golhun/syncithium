<?php
// Add these helpers alongside your existing flash helpers.

function reveal_set(array $payload): void {
  $_SESSION['reveal'] = [
    'payload' => $payload,
    'created_at' => time(),
  ];
}

function reveal_take(): ?array {
  if (empty($_SESSION['reveal']['payload'])) return null;

  // Optional TTL, 5 minutes
  if (!empty($_SESSION['reveal']['created_at']) && (time() - (int)$_SESSION['reveal']['created_at'] > 300)) {
    unset($_SESSION['reveal']);
    return null;
  }

  $p = $_SESSION['reveal']['payload'];
  unset($_SESSION['reveal']);
  return $p;
}
