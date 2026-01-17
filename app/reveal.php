<?php
declare(strict_types=1);

/**
 * One-time secret reveal helper.
 *
 * Stores a secret in the session for a single subsequent page view,
 * then clears it automatically when retrieved.
 *
 * Use for: temp passwords, reset tokens.
 */

function reveal_set(array $payload): void {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    // Session should already be active via init_session(), but stay defensive.
    session_start();
  }

  // Normalize to a strict shape to avoid accidental huge payloads
  $data = [
    'label'        => (string)($payload['label'] ?? 'Secret'),
    'secret_label' => (string)($payload['secret_label'] ?? 'Secret'),
    'secret'       => (string)($payload['secret'] ?? ''),
    'meta'         => is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
    'created_at'   => date('c'),
  ];

  $_SESSION['reveal_once'] = $data;
}

function reveal_take(): ?array {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }

  if (empty($_SESSION['reveal_once']) || !is_array($_SESSION['reveal_once'])) {
    return null;
  }

  $data = $_SESSION['reveal_once'];
  unset($_SESSION['reveal_once']); // one-time behavior

  // Safety: never return if secret missing
  if (!isset($data['secret']) || (string)$data['secret'] === '') {
    return null;
  }

  return $data;
}
