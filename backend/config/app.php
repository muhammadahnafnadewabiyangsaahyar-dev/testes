<?php
/**
 * Backend Application Bootstrap
 * HELMEPPO - Backend Layer
 * Konfigurasi aplikasi backend dengan autoloader sederhana dan session management
 */

namespace App;

require_once __DIR__ . '/../vendor/autoload.php';

// Autoloader sederhana untuk kelas-kelas backend
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Konfigurasi CORS untuk API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Konfigurasi error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display errors in production

// Set timezone
date_default_timezone_set('Asia/Makassar');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/database.php';

?>