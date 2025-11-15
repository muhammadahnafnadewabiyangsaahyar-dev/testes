<?php
session_start();
include 'connect.php'; // Menggunakan file connect.php baru (PDO)
require_once 'security_helper.php'; // Load security functions

// Cek jika sudah login, redirect ke mainpage
if (isset($_SESSION['user_id']) && SecurityHelper::validateSession()) {
    $redirect = ($_SESSION['role'] === 'admin') ? 'mainpage.php' : 'mainpage.php';
    header("Location: $redirect");
    exit();
}

// 1. Hanya proses jika method POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $username = SecurityHelper::sanitizeSQL($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Don't sanitize password (will be hashed)

    // Rate limiting: Max 5 attempts per 15 menit per IP
    $client_ip = SecurityHelper::getClientIP();
    if (!SecurityHelper::checkRateLimit('login_' . $client_ip, 5, 900)) {
        SecurityHelper::logSuspiciousActivity(0, 'login_rate_limit_exceeded', [
            'ip' => $client_ip,
            'username' => $username
        ]);
        header('Location: index.php?error=toomanyattempts');
        exit();
    }

    // 2. Validasi input
    if (empty($username) || empty($password)) {
        // Alihkan kembali dengan error, jangan gunakan die()
        header('Location: index.php?error=emptyfields');
        exit();
    }

    try {
        // 3. Cek apakah username ada di database
        $sql = "SELECT * FROM register WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $row = $stmt->fetch(); // Gunakan fetch() karena username itu unik

        // 4. Verifikasi User dan Password
        if ($row && password_verify($password, $row['password'])) {
            // Login berhasil
            
            // --- SECURE SESSION INITIALIZATION ---
            SecurityHelper::secureSessionStart();
            
            // Regenerasi ID session setelah login berhasil (prevent session fixation)
            session_regenerate_id(true);
            
            // Set session data
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
            $_SESSION['last_activity'] = time(); // For session timeout
            $_SESSION['ip_address'] = SecurityHelper::getClientIP(); // Optional: for IP validation
            $_SESSION['initiated'] = true;
            
            // Generate CSRF token for this session
            SecurityHelper::generateCSRFToken();
            
            // Log successful login
            SecurityHelper::logSuspiciousActivity($row['id'], 'login_success', [
                'username' => $username,
                'ip' => SecurityHelper::getClientIP(),
                'role' => $row['role']
            ]);
            // ---------------------------------------------

            // 5. Redirect berdasarkan role
            if ($_SESSION['role'] == 'admin') {
                header("Location: mainpage.php");
            } else {
                header("Location: mainpage.php");
            }
            exit();

        } elseif ($row) {
            // Username ditemukan, tapi password salah
            SecurityHelper::logSuspiciousActivity($row['id'], 'login_failed_wrong_password', [
                'username' => $username,
                'ip' => SecurityHelper::getClientIP()
            ]);
            header('Location: index.php?error=invalidpassword');
            exit();
        } else {
            // Username tidak ditemukan
            SecurityHelper::logSuspiciousActivity(0, 'login_failed_user_not_found', [
                'username' => $username,
                'ip' => SecurityHelper::getClientIP()
            ]);
            header('Location: index.php?error=usernotfound');
            exit();
        }

    } catch (PDOException $e) {
        // Tangani error database
        error_log("Login Gagal: " . $e->getMessage());
        header('Location: index.php?error=dberror');
        exit();
    }

} else {
    // Jika ada yang mencoba mengakses login.php secara langsung
    header('Location: index.php');
    exit();
}
?>