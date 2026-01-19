<?php
declare(strict_types=1);

/**
 * Heroicons helper (local SVG files)
 * Folder structure expected:
 *  public/assets/icons/heroicons/24/outline/<name>.svg
 *  public/assets/icons/heroicons/24/solid/<name>.svg
 *
 * Usage:
 *   echo icon('lock-closed', 'h-5 w-5 text-sky-700');                 // outline default
 *   echo icon('users', 'h-4 w-4', 'solid');                           // solid
 *   echo icon('chevron-down', 'h-4 w-4 text-gray-500', 'outline');    // outline
 */

if (!function_exists('hi_svg')) {
    function hi_svg(string $name, string $variant = 'outline', string $class = 'h-5 w-5'): string
    {
        $variant = ($variant === 'solid') ? 'solid' : 'outline';

        // Use the PUBLIC_ROOT constant defined in index.php for a portable path.
        // This avoids hardcoding the 'public' directory name.
        if (!defined('PUBLIC_ROOT')) {
            // Fallback for environments where index.php might not be the entry point (e.g. CLI scripts)
            define('PUBLIC_ROOT', __DIR__ . '/../../public');
        }
        $base = PUBLIC_ROOT . '/assets/icons/heroicons/24/' . $variant . '/';
        $path = $base . $name . '.svg';

        if (!is_file($path)) {
            return '';
        }

        static $cache = [];
        $cacheKey = $variant . ':' . $name . ':' . $class;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $svg = file_get_contents($path);
        if ($svg === false || trim($svg) === '') {
            return $cache[$cacheKey] = '';
        }

        // Remove XML header if present
        $svg = preg_replace('/<\?xml.*?\?>/s', '', $svg) ?? $svg;

        // Inject/replace class attribute on the <svg ...> tag
        if (preg_match('/<svg\b[^>]*\bclass="/i', $svg)) {
            $svg = preg_replace(
                '/<svg\b([^>]*)\bclass="[^"]*"/i',
                '<svg$1 class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"',
                $svg,
                1
            ) ?? $svg;
        } else {
            $svg = preg_replace(
                '/<svg\b([^>]*)>/i',
                '<svg$1 class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true" focusable="false">',
                $svg,
                1
            ) ?? $svg;
        }

        // Ensure basic accessibility attributes exist
        if (!preg_match('/aria-hidden=/i', $svg)) {
            $svg = preg_replace('/<svg\b/i', '<svg aria-hidden="true"', $svg, 1) ?? $svg;
        }
        if (!preg_match('/focusable=/i', $svg)) {
            $svg = preg_replace('/<svg\b/i', '<svg focusable="false"', $svg, 1) ?? $svg;
        }

        return $cache[$cacheKey] = $svg;
    }
}

if (!function_exists('icon')) {
    function icon(string $name, string $class = 'h-5 w-5', string $variant = 'outline'): string
    {
        return hi_svg($name, $variant, $class);
    }
}
