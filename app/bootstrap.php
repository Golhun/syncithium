<?php
declare(strict_types=1);

date_default_timezone_set('UTC'); // or 'Africa/Accra'

$config = require __DIR__ . '/config.php';

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/helpers/icons.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/helpers/http.php';
require_once __DIR__ . '/helpers/auth_redirect.php';

// Flash + reveal helpers (already added earlier)
require_once __DIR__ . '/lib/flash.php';

// NEW: question helpers (q_norm, q_status_norm)
require_once __DIR__ . '/lib/questions.php';

init_session($config);
$db = db($config);


/**
 * Helper to get normalised status from CSV row or value.
 *
 * Usage:
 *  - q_status('active')
 *  - q_status('inactive')
 *  - q_status($row)                  // uses $row['status'] if present
 *  - q_status($row, 'status')        // explicit key
 *  - q_status($row, 7)               // numeric index
 */
function q_status(...$args): string
{
    if (count($args) === 0) {
        return q_status_norm(null);
    }

    $first = $args[0];

    // Case: row array
    if (is_array($first)) {
        $row = $first;

        // If explicit key/index given
        if (isset($args[1])) {
            $key = $args[1];

            if (is_string($key) && array_key_exists($key, $row)) {
                return q_status_norm((string)$row[$key]);
            }

            if (is_int($key) && array_key_exists($key, $row)) {
                return q_status_norm((string)$row[$key]);
            }
        }

        // Fallback: look for 'status'
        if (array_key_exists('status', $row)) {
            return q_status_norm((string)$row['status']);
        }

        // No status provided in row
        return q_status_norm(null);
    }

    // Scalar value
    return q_status_norm((string)$first);
}
