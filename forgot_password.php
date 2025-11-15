<?php
session_start();
include 'connect.php';

// Include PHPMailer
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// DEBUG: Tampilkan semua error di browser
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Enable error logging
ini_set('log_errors', 1);
error_log("=== Forgot Password Page Loaded ===");

// Jika user sudah login, redirect ke mainpage
if (isset($_SESSION['user_id'])) {
    header('Location: mainpageuser.php');
    exit;
}

$feedback = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== Reset Password Request Started ===");
    $email = trim($_POST['email'] ?? '');
    error_log("Email submitted: " . $email);
    
    if (empty($email)) {
        $feedback = 'Email harus diisi.';
        error_log("ERROR: Email empty");
    } else {
        // Cek apakah email terdaftar
        $stmt = $pdo->prepare('SELECT id FROM register WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            error_log("User found with ID: " . $user['id']);
            
            // Generate token unik
            $token = bin2hex(random_bytes(32));
            error_log("Token generated: " . substr($token, 0, 10) . "...");
            
            // Simpan token ke tabel reset_password (buat jika belum ada)
            $pdo->prepare('CREATE TABLE IF NOT EXISTS reset_password (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )')->execute();
            
            // Hapus token lama user ini
            $pdo->prepare('DELETE FROM reset_password WHERE user_id = ?')->execute([$user['id']]);
            
            // Simpan token baru dengan expiry 1 jam dari sekarang (menggunakan MySQL NOW())
            $stmt2 = $pdo->prepare('INSERT INTO reset_password (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
            $stmt2->execute([$user['id'], $token]);
            error_log("Token saved to database with 1 hour expiry");
            
            // Generate reset link
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $host = $_SERVER['HTTP_HOST'];
            $base_path = dirname($_SERVER['PHP_SELF']);
            $reset_link = $protocol . $host . $base_path . '/reset_password.php?token=' . $token;
            error_log("Reset link generated: " . $reset_link);
            
            // Kirim email reset password via SMTP
            try {
                // Aktifkan debug PHPMailer dan tampilkan output di halaman
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'kaori.aplikasi.notif@gmail.com'; // Ganti email Anda
                $mail->Password   = 'imjq nmeq vyig umgn'; // Ganti password aplikasi
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                // PHPMailer debug output
                $mail->SMTPDebug = 0; // 2 = verbose output
                $mail->Debugoutput = function($str, $level) {
                    echo "<pre style='color:blue;background:#f0f0f0;border:1px solid #ccc;padding:8px;'>PHPMailer debug: $str</pre>";
                };

                $mail->setFrom('kaori.aplikasi.notif@gmail.com', 'Sistem KAORI');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Reset Password KAORI';
                $mail->Body    = "Klik link berikut untuk reset password Anda:<br><a href='$reset_link'>$reset_link</a><br>Link berlaku 1 jam.";
                $mail->AltBody = "Reset password: $reset_link (berlaku 1 jam)";

                $mail->send();
                $feedback = "Link reset password telah dikirim ke email Anda.";
            } catch (Exception $e) {
                // Tampilkan error PHPMailer dan Exception di halaman
                echo "<div style='color:red;margin-top:20px;'><strong>PHPMailer Error:</strong> " . htmlspecialchars($mail->ErrorInfo) . "<br><strong>Exception:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
                $feedback = 'Gagal mengirim email reset password. Silakan coba lagi.';
            }
        } else {
            error_log("ERROR: Email not found in database");
            $feedback = 'Email tidak ditemukan.';
        }
    }
    error_log("=== Reset Password Request Ended ===");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Password</title>
    <link rel="stylesheet" href="style_modern.css">
</head>
<body>
    <div class="main-title">Lupa Password</div>
    <div class="content-container" style="max-width:400px;margin:auto;">
        <form method="POST" action="">
            <label for="email">Masukkan Email Anda:</label><br>
            <input type="email" name="email" id="email" required style="width:100%;padding:8px;margin:10px 0;">
            <button type="submit" class="btn-approve" style="width:100%;">Kirim Link Reset</button>
        </form>
        <?php if ($feedback): ?>
            <div style="margin-top:20px;color:<?= strpos($feedback,'Link reset')!==false?'green':'red' ?>;">
                <?= $feedback ?>
            </div>
        <?php endif; ?>
        <div style="margin-top:20px;">
            <a href="index.php">Kembali ke Login</a>
        </div>
    </div>
</body>
</html>
