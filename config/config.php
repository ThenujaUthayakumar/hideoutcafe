<?php
/**
 * The Hide Out Cafe - Application Configuration
 */

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
    }
    session_start();
}

// Error reporting
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// Application Constants
define('APP_NAME', 'The Hide Out Cafe');
define('APP_VERSION', '2.0.0');
define('APP_TAGLINE', 'Artisan Coffee & Bistro - Sri Lanka');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'cafe_pos_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Timezone
date_default_timezone_set('Asia/Colombo');

// Paths
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads');

// Auto-detect Base URL dynamically
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_replace('\\', '/', dirname($currentScript));

$cleanDir = preg_replace('#/(api|includes|assets)$#', '', $scriptDir);
$baseUrl = rtrim($protocol . $host . $cleanDir, '/');
define('BASE_URL', $baseUrl);

// Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_CASHIER', 'cashier');

// Order Types
define('ORDER_DINE_IN', 'dine_in');
define('ORDER_TAKEAWAY', 'takeaway');
define('ORDER_DELIVERY', 'delivery');

// Order Statuses
define('STATUS_PENDING', 'pending');
define('STATUS_PREPARING', 'preparing');
define('STATUS_READY', 'ready');
define('STATUS_COMPLETED', 'completed');
define('STATUS_CANCELLED', 'cancelled');
