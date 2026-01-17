<?php
declare(strict_types=1);

/**
 * Returns the base URL for the application.
 * If APP_URL is set in config, it will use it.
 * Otherwise it will infer from the current request.
 */
function base_url(string $path = ''): string
{
    $config = $GLOBALS['config'] ?? [];

    $appUrl = $config['app']['url'] ?? '';
    if (is_string($appUrl) && trim($appUrl) !== '') {
        $base = rtrim($appUrl, '/');
    } else {
        // Infer from current request
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // If app is in a subfolder, infer it
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        $base = $scheme . '://' . $host . ($basePath === '' ? '' : $basePath);
    }

    $path = ltrim($path, '/');
    return $path === '' ? $base . '/' : $base . '/' . $path;
}
