<?php
/**
 * Frontend Application Entry Point
 * 
 * Entry point utama untuk aplikasi HR Kaori yang sudah di-refactor
 * Mengelola routing dan dependency injection
 * 
 * @author Tim Pengembang Kaori HR
 * @version 1.0.0
 */

// Start session
session_start();

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Import classes
use KaoriHR\Controllers\AuthController;
use KaoriHR\Services\DatabaseService;
use KaoriHR\Services\AuthenticationService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Initialize logger
$logger = new Logger('kaori_hr');
$logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::INFO));

try {
    // Initialize services
    $databaseService = new DatabaseService($logger);
    $authService = new AuthenticationService($databaseService, $logger);
    $authController = new AuthController($authService, $logger);
    
    // Handle different request types
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // Remove query string and leading slash
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = ltrim($path, '/');
    
    // Route handling
    switch ($path) {
        case 'login':
            if ($requestMethod === 'POST') {
                $authController->login();
            }
            break;
            
        case 'logout':
            $authController->logout();
            break;
            
        case 'verify-whitelist':
            if ($requestMethod === 'GET') {
                $authController->verifyWhitelist();
            }
            break;
            
        case 'register':
            if ($requestMethod === 'POST') {
                $authController->register();
            }
            break;
            
        case '':
        case 'index.php':
        default:
            // Main application entry - show auth page
            $dropdownData = $authController->getDropdownData();
            
            // Include the main auth template
            include __DIR__ . '/templates/auth/auth-main.php';
            break;
    }
    
} catch (\Exception $e) {
    $logger->error("Application error", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Show error page
    http_response_code(500);
    echo "<h1>Terjadi Kesalahan Sistem</h1>";
    echo "<p>Silakan hubungi administrator sistem.</p>";
    echo "<p><small>Error ID: " . time() . "</small></p>";
}