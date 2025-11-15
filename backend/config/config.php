<?php
/**
 * General Configuration
 * HELMEPPO - Backend Layer
 * Konfigurasi umum aplikasi, environment, base URL, dan settings
 */

namespace App;

// Environment configuration
define('APP_ENV', 'development'); // development, production
define('APP_DEBUG', true);

// Base URL configuration
define('BASE_URL', 'http://localhost/HELMEPPO');
define('API_BASE_URL', BASE_URL . '/backend/public/api');

// Application settings
define('APP_NAME', 'HELMEPPO');
define('APP_VERSION', '1.0.0');

// Timezone
define('DEFAULT_TIMEZONE', 'Asia/Makassar');

// Session configuration
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'helmeppo_session');

// File upload settings
define('UPLOAD_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// API response constants
define('API_SUCCESS', 'success');
define('API_ERROR', 'error');
define('API_WARNING', 'warning');

// Log levels
define('LOG_INFO', 'info');
define('LOG_WARNING', 'warning');
define('LOG_ERROR', 'error');
define('LOG_DEBUG', 'debug');

// Security constants
define('CSRF_TOKEN_NAME', 'csrf_token');
define('PASSWORD_MIN_LENGTH', 6);

// Default pagination
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// Time formats
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('TIME_FORMAT', 'H:i');

// Security helper function
function generateCSRFToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCSRFToken($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

?>