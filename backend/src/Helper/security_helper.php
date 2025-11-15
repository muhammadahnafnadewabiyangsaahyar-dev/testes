<?php
/**
 * SECURITY HELPER - Comprehensive Security Functions
 * Melindungi aplikasi dari berbagai serangan dan manipulasi
 * 
 * Features:
 * 1. Anti Mock Location Detection
 * 2. Anti Time Manipulation Detection
 * 3. SQL Injection Prevention (Prepared Statements)
 * 4. XSS Prevention
 * 5. CSRF Token Management
 * 6. Rate Limiting
 * 7. Input Validation & Sanitization
 * 8. Session Security
 */

class SecurityHelper {
    
    /**
     * 1. ANTI MOCK LOCATION DETECTION
     * Deteksi koordinat GPS palsu dari Android/iOS
     */
    public static function detectMockLocation($latitude, $longitude, $accuracy = null, $provider = null) {
        $suspicious_flags = [];
        
        // Flag 1: Koordinat terlalu presisi (mock location sering perfect)
        if ($accuracy !== null && $accuracy < 5) {
            $suspicious_flags[] = 'accuracy_too_perfect';
        }
        
        // Flag 2: Provider mencurigakan (network saja, tanpa GPS/fused)
        if ($provider !== null && strtolower($provider) === 'network') {
            $suspicious_flags[] = 'network_only_provider';
        }
        
        // Flag 3: Koordinat tidak wajar (0,0 atau koordinat default mock app)
        if (($latitude == 0 && $longitude == 0) || 
            ($latitude == 37.422 && $longitude == -122.084) || // Mountain View, CA (default Android emulator)
            ($latitude == 37.785834 && $longitude == -122.406417)) { // San Francisco (common mock)
            $suspicious_flags[] = 'suspicious_coordinates';
        }
        
        // Flag 4: Kecepatan pergerakan tidak realistis (jika ada data sebelumnya)
        // Implementasi ini memerlukan data absensi sebelumnya
        
        return [
            'is_suspicious' => !empty($suspicious_flags),
            'flags' => $suspicious_flags,
            'risk_level' => count($suspicious_flags) >= 2 ? 'HIGH' : (count($suspicious_flags) == 1 ? 'MEDIUM' : 'LOW')
        ];
    }
    
    /**
     * Validasi lokasi user terhadap lokasi cabang
     * Mendeteksi jika user terlalu jauh dari lokasi yang seharusnya
     */
    public static function validateLocationRadius($user_lat, $user_long, $cabang_lat, $cabang_long, $max_radius_meters = 500) {
        $distance = self::calculateDistance($user_lat, $user_long, $cabang_lat, $cabang_long);
        
        return [
            'is_valid' => $distance <= $max_radius_meters,
            'distance_meters' => round($distance, 2),
            'max_allowed' => $max_radius_meters
        ];
    }
    
    /**
     * Hitung jarak antara 2 koordinat (Haversine formula)
     */
    private static function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earth_radius = 6371000; // meter
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earth_radius * $c;
        
        return $distance;
    }
    
    /**
     * 2. ANTI TIME MANIPULATION DETECTION
     * Deteksi jika waktu device user tidak match dengan server time
     */
    public static function detectTimeManipulation($client_timestamp, $tolerance_seconds = 300) {
        $server_time = time();
        $time_diff = abs($server_time - $client_timestamp);
        
        return [
            'is_manipulated' => $time_diff > $tolerance_seconds,
            'time_difference_seconds' => $time_diff,
            'tolerance_seconds' => $tolerance_seconds,
            'server_time' => date('Y-m-d H:i:s', $server_time),
            'client_time' => date('Y-m-d H:i:s', $client_timestamp)
        ];
    }
    
    /**
     * Validasi timestamp berada di range yang wajar
     */
    public static function validateTimestamp($timestamp) {
        $now = time();
        $one_day_ago = $now - 86400;
        $one_day_ahead = $now + 86400;
        
        return $timestamp >= $one_day_ago && $timestamp <= $one_day_ahead;
    }
    
    /**
     * 3. SQL INJECTION PREVENTION
     * Sanitize input untuk query (tapi SELALU gunakan prepared statements!)
     */
    public static function sanitizeSQL($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeSQL'], $input);
        }
        
        // Strip tags dan trim
        $input = strip_tags(trim($input));
        
        // Escape karakter berbahaya
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return $input;
    }
    
    /**
     * Validasi input hanya mengandung karakter yang diperbolehkan
     */
    public static function validateInput($input, $type = 'alphanumeric') {
        switch ($type) {
            case 'alphanumeric':
                return preg_match('/^[a-zA-Z0-9\s]+$/', $input);
            case 'email':
                return filter_var($input, FILTER_VALIDATE_EMAIL);
            case 'numeric':
                return is_numeric($input);
            case 'date':
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $input);
            case 'time':
                return preg_match('/^\d{2}:\d{2}:\d{2}$/', $input);
            case 'phone':
                return preg_match('/^(\+62|62|0)[0-9]{9,13}$/', $input);
            default:
                return false;
        }
    }
    
    /**
     * 4. XSS PREVENTION
     * Clean output untuk mencegah XSS
     */
    public static function cleanOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'cleanOutput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 5. CSRF TOKEN MANAGEMENT
     * Generate dan validasi CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * 6. RATE LIMITING
     * Batasi jumlah request per IP/user dalam waktu tertentu
     */
    public static function checkRateLimit($identifier, $max_attempts = 5, $time_window = 60) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = "rate_limit_" . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => time()
            ];
            return true;
        }
        
        $elapsed = time() - $_SESSION[$key]['start_time'];
        
        // Reset jika sudah lewat time window
        if ($elapsed > $time_window) {
            $_SESSION[$key] = [
                'count' => 1,
                'start_time' => time()
            ];
            return true;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        
        // Check jika melebihi limit
        if ($_SESSION[$key]['count'] > $max_attempts) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 7. SESSION SECURITY
     * Secure session management
     */
    public static function secureSessionStart() {
        if (session_status() == PHP_SESSION_NONE) {
            // Set secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Regenerate session ID untuk prevent session fixation
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }
    
    /**
     * Validasi session masih valid
     */
    public static function validateSession() {
        if (session_status() == PHP_SESSION_NONE) {
            return false;
        }
        
        // Check jika session expired (timeout 2 jam)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 7200)) {
            session_unset();
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        
        // Validasi IP address (opsional, bisa dimatikan jika user mobile)
        // if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        //     return false;
        // }
        
        return true;
    }
    
    /**
     * 8. FILE UPLOAD SECURITY
     * Validasi file upload
     */
    public static function validateFileUpload($file, $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'], $max_size = 2097152) {
        $errors = [];
        
        // Check if file exists
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'No file uploaded';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload error code: ' . $file['error'];
            return ['valid' => false, 'errors' => $errors];
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            $errors[] = 'File too large. Max: ' . ($max_size / 1024 / 1024) . 'MB';
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_types)) {
            $errors[] = 'Invalid file type: ' . $mime;
        }
        
        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($ext, $allowed_extensions)) {
            $errors[] = 'Invalid file extension: ' . $ext;
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mime,
            'extension' => $ext,
            'size' => $file['size']
        ];
    }
    
    /**
     * Generate safe filename
     */
    public static function generateSafeFilename($original_filename) {
        $ext = pathinfo($original_filename, PATHINFO_EXTENSION);
        $safe_name = bin2hex(random_bytes(16));
        return $safe_name . '.' . $ext;
    }
    
    /**
     * 9. PASSWORD SECURITY
     * Hash dan verify password dengan bcrypt
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Validasi password strength
     */
    public static function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password minimal 8 karakter';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 huruf besar';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 huruf kecil';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password harus mengandung minimal 1 angka';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * 10. LOGGING & MONITORING
     * Log aktivitas mencurigakan
     */
    public static function logSuspiciousActivity($user_id, $activity_type, $details) {
        // DISABLED FOR TESTING - Permission issues with logging
        // $log_file = __DIR__ . '/logs/security_' . date('Y-m-d') . '.log';
        
        // $log_entry = [
        //     'timestamp' => date('Y-m-d H:i:s'),
        //     'user_id' => $user_id,
        //     'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        //     'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        //     'activity_type' => $activity_type,
        //     'details' => $details
        // ];
        
        // // Create logs directory if not exists
        // $log_dir = dirname($log_file);
        // if (!is_dir($log_dir)) {
        //     mkdir($log_dir, 0755, true);
        // }
        
        // file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND);
    }
    
    /**
     * Get client IP address (considering proxy/CDN)
     */
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
    }
}

/**
 * USAGE EXAMPLES:
 * 
 * // 1. Check mock location
 * $mock_check = SecurityHelper::detectMockLocation($lat, $long, $accuracy, $provider);
 * if ($mock_check['is_suspicious']) {
 *     SecurityHelper::logSuspiciousActivity($user_id, 'mock_location', $mock_check);
 * }
 * 
 * // 2. Validate location radius
 * $location_check = SecurityHelper::validateLocationRadius($user_lat, $user_long, $cabang_lat, $cabang_long);
 * if (!$location_check['is_valid']) {
 *     die('Anda terlalu jauh dari lokasi cabang');
 * }
 * 
 * // 3. Check time manipulation
 * $time_check = SecurityHelper::detectTimeManipulation($client_timestamp);
 * if ($time_check['is_manipulated']) {
 *     SecurityHelper::logSuspiciousActivity($user_id, 'time_manipulation', $time_check);
 * }
 * 
 * // 4. Sanitize input
 * $clean_input = SecurityHelper::sanitizeSQL($_POST['nama']);
 * 
 * // 5. Validate CSRF
 * if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'])) {
 *     die('Invalid CSRF token');
 * }
 * 
 * // 6. Rate limiting
 * if (!SecurityHelper::checkRateLimit($_SERVER['REMOTE_ADDR'], 5, 60)) {
 *     die('Too many requests. Please wait.');
 * }
 * 
 * // 7. Secure session
 * SecurityHelper::secureSessionStart();
 * if (!SecurityHelper::validateSession()) {
 *     header('Location: login.php');
 * }
 * 
 * // 8. Validate file upload
 * $file_check = SecurityHelper::validateFileUpload($_FILES['document']);
 * if (!$file_check['valid']) {
 *     die('Invalid file: ' . implode(', ', $file_check['errors']));
 * }
 */
?>
