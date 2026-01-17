<?php
// src/helpers.php

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function now_dt(): string
{
    return date('Y-m-d H:i:s');
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function random_temp_password(int $length = 12): string
{
    // readable + strong enough for temporary use
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    $out = '';
    for ($i = 0; $i < $length; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $out;
}
