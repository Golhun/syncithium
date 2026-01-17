<?php
declare(strict_types=1);


/**
 * One-time reveal helper for temp passwords / reset tokens.
 * Uses a 5-minute TTL by default.
 */

if (!function_exists('reveal_set')) {
    function reveal_set(array $payload): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['reveal'] = [
            'payload'    => $payload,
            'created_at' => time(),
        ];
    }
}

if (!function_exists('reveal_take')) {
    function reveal_take(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['reveal']['payload'])) {
            return null;
        }

        // TTL 5 minutes
        $created = (int)($_SESSION['reveal']['created_at'] ?? 0);
        if ($created > 0 && (time() - $created) > 300) {
            unset($_SESSION['reveal']);
            return null;
        }

        $payload = $_SESSION['reveal']['payload'];
        unset($_SESSION['reveal']);

        return $payload;
    }
}
