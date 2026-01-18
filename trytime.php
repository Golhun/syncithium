<?php
date_default_timezone_set('UTC');
// trytime.php
echo "php_now=" . date('Y-m-d H:i:s') . PHP_EOL;
echo "php_tz=" . date_default_timezone_get() . PHP_EOL;
