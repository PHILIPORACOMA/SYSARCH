<?php
// Database Configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'sysarchstudents');

// Environment Settings
define('DEBUG_MODE', true); // Set to false in production

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session Settings
define('SESSION_TIMEOUT', 7200); // 2 hours
?>