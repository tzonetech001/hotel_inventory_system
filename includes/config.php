<?php
session_start();

// Database Configuration
define('DB_HOST','localhost');
define('DB_USER','root');
define('DB_PASS','');
define('DB_NAME','hotel_inventory_system');

// Application Configuration
define('APP_NAME','Hotel Inventory System');

// Dynamic APP_URL - inabadilika kulingana na environment
if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) || isset($_SERVER['HTTP_HOST'])) {
    $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
    define('APP_URL', $protocol . '://' . $host . '/hotel_inventory_system');
} else {
    define('APP_URL','http://localhost/hotel_inventory_system');
}

// Timezone
date_default_timezone_set('Africa/Dar_es_Salaam');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors',1);
?>