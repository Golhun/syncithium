<?php
declare(strict_types=1);

/**
 * flash.php
 * Compatibility shim.
 * Prefer using helpers.php as the single source of truth.
 */

if (!function_exists('flash_set')) {
    function flash_set(string $key, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        $_SESSION['_flash'][$key] = $message;
    }
}

if (!function_exists('flash_get')) {
    function flash_get(string $key): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            return null;
        }
        if (!array_key_exists($key, $_SESSION['_flash'])) {
            return null;
        }
        $msg = (string)$_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }
}
