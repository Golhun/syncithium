<?php

function base_url(array $config): string {
    $base = rtrim((string)($config['app']['base_url'] ?? ''), '/');
    return $base;
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash_set(string $key, string $message): void {
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string {
    if (!isset($_SESSION['flash'][$key])) return null;
    $msg = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function require_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo 'Method Not Allowed';
        exit;
    }
}

function now_dt(): string {
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}
