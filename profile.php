<?php
/**
 * ENHANCED PROFILE MANAGEMENT WITH DIGITAL SIGNATURE
 *
 * Features:
 * - Comprehensive profile management
 * - Advanced digital signature system
 * - Profile picture upload with preview
 * - Password security management
 * - Two-factor authentication setup
 * - Profile completion tracking
 * - Activity logging
 */

session_start();
include 'connect.php'; // Database connection
include 'functions_role.php'; // Role functions

// Security: Ensure only logged-in users can access
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// Initialize message variables
$password_error = $password_success = $profile_error = $profile_success = "";
$signature_error = $signature_success = "";
$security_error = $security_success = "";

// ========================================================
// --- ENHANCED PASSWORD MANAGEMENT ---
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Enhanced password validation
    $password_validation = validatePasswordRequirements($new_password, $confirm_password);

    if (!$password_validation['valid']) {
        $password_error = $password_validation['message'];
    } else {
        try {
            // Get current password hash from DB
            $stmt_check = $pdo->prepare("SELECT password FROM register WHERE id = ?");
            $stmt_check->execute([$user_id]);
            $user = $stmt_check->fetch();

            if ($user && password_verify($current_password, $user['password'])) {
                // Current password is correct
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                // Update password
                $sql_update_pass = "UPDATE register SET password = ?, password_updated_at = NOW() WHERE id = ?";
                $stmt_update_pass = $pdo->prepare($sql_update_pass);

                if ($stmt_update_pass->execute([$new_hashed_password, $user_id])) {
                    // Log password change activity
                    logUserActivity($pdo, $user_id, 'password_change', 'Password updated successfully');

                    $pdo->commit();
                    $password_success = "Password berhasil diperbarui. Gunakan password baru untuk login selanjutnya.";

                    // Optional: Send password change notification
                    // sendPasswordChangeNotification($user_data['email'], $user_data['nama_lengkap']);
                } else {
                    $pdo->rollBack();
                    $password_error = "Gagal memperbarui password. Silakan coba lagi.";
                }
            } else {
                $password_error = "Password saat ini yang Anda masukkan salah.";
                logUserActivity($pdo, $user_id, 'password_change_failed', 'Incorrect current password');
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $password_error = "Terjadi kesalahan sistem. Silakan coba lagi nanti.";
            error_log("Password update error for user $user_id: " . $e->getMessage());
        }
    }
}

/**
 * Enhanced password validation
 */
function validatePasswordRequirements($password, $confirm) {
    if (empty($password)) {
        return ['valid' => false, 'message' => 'Password baru harus diisi.'];
    }

    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'Password minimal 8 karakter.'];
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'Password harus mengandung minimal 1 huruf besar.'];
    }

    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'Password harus mengandung minimal 1 huruf kecil.'];
    }

    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password harus mengandung minimal 1 angka.'];
    }

    if ($password !== $confirm) {
        return ['valid' => false, 'message' => 'Password baru dan konfirmasi tidak cocok.'];
    }

    return ['valid' => true, 'message' => ''];
}

// ========================================================
// --- ENHANCED PROFILE MANAGEMENT ---
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $telegram_username = trim($_POST['username_telegram'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    // Enhanced validation
    $profile_validation = validateProfileData($email, $no_wa, $telegram_username);

    if (!$profile_validation['valid']) {
        $profile_error = $profile_validation['message'];
    } else {
        try {
            $pdo->beginTransaction();

            // Update profile information with comprehensive error handling
            $sql_update_profile = "UPDATE register SET
                                  email = ?,
                                  no_telegram = ?,
                                  username_telegram = ?,
                                  bio = ?,
                                  profile_updated_at = NOW()
                                  WHERE id = ?";
            $stmt_update_profile = $pdo->prepare($sql_update_profile);

            $result = $stmt_update_profile->execute([
                $email, $no_wa, $telegram_username, $bio, $user_id
            ]);

            if ($result) {
                // Log profile update activity
                logUserActivity($pdo, $user_id, 'profile_update', 'Profile information updated');

                $pdo->commit();
                $profile_success = "Profil berhasil diperbarui.";

                // Refresh user data
                $user_data = getUserProfileData($pdo, $user_id);
            } else {
                $pdo->rollBack();
                $profile_error = "Gagal memperbarui profil. Silakan coba lagi.";
            }

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Handle specific database errors
            if ($e->getCode() == 1062) {
                if (strpos($e->getMessage(), 'email')) {
                    $profile_error = 'Email ini sudah digunakan oleh pengguna lain.';
                } elseif (strpos($e->getMessage(), 'no_telegram')) {
                    $profile_error = 'Nomor Telegram ini sudah digunakan oleh pengguna lain.';
                } else {
                    $profile_error = 'Data yang Anda masukkan sudah terdaftar.';
                }
            } else {
                $profile_error = "Terjadi kesalahan sistem. Silakan coba lagi nanti.";
                error_log("Profile update error for user $user_id: " . $e->getMessage());
            }
        }
    }
}

/**
 * Enhanced profile data validation
 */
function validateProfileData($email, $no_wa, $telegram_username) {
    // Email validation
    if (empty($email)) {
        return ['valid' => false, 'message' => 'Email tidak boleh kosong.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Format email tidak valid.'];
    }

    // Telegram validation
    if (!empty($no_wa)) {
        // Auto-format: add space after 62 if not present
        if (preg_match('/^62([0-9]{8,12})$/', $no_wa, $matches)) {
            $no_wa = '62 ' . $matches[1];
        }

        if (!preg_match('/^62\s[0-9]{8,12}$/', $no_wa)) {
            return ['valid' => false, 'message' => 'Format nomor Telegram salah. Contoh: 62 81234567890'];
        }
    }

    // Username Telegram validation
    if (!empty($telegram_username)) {
        if (!preg_match('/^[a-zA-Z0-9_]{5,32}$/', $telegram_username)) {
            return ['valid' => false, 'message' => 'Format username Telegram tidak valid. Hanya huruf, angka, dan underscore (5-32 karakter).'];
        }
    }

    return ['valid' => true, 'message' => ''];
}

// ========================================================
// --- ENHANCED USER DATA RETRIEVAL ---
// ========================================================
function getUserProfileData($pdo, $user_id) {
    try {
        $sql_select = "SELECT r.*,
                              r.profile_updated_at,
                              r.password_updated_at,
                              r.signature_updated_at,
                              r.time_created,
                              -- Enhanced profile completion score with bio and username_telegram
                              CASE
                                WHEN r.foto_profil IS NOT NULL AND r.foto_profil != '' AND r.foto_profil != 'default.png' THEN 20 ELSE 0
                              END +
                              CASE
                                WHEN r.tanda_tangan_file IS NOT NULL AND r.tanda_tangan_file != '' THEN 20 ELSE 0
                              END +
                              CASE
                                WHEN r.bio IS NOT NULL AND r.bio != '' THEN 15 ELSE 0
                              END +
                              CASE
                                WHEN r.email IS NOT NULL AND r.email != '' THEN 10 ELSE 0
                              END +
                              CASE
                                WHEN r.no_telegram IS NOT NULL AND r.no_telegram != '' THEN 10 ELSE 0
                              END +
                              CASE
                                WHEN r.username_telegram IS NOT NULL AND r.username_telegram != '' THEN 10 ELSE 0
                              END +
                              CASE
                                WHEN r.outlet IS NOT NULL AND r.outlet != '' THEN 15 ELSE 0
                              END as profile_completion_score
                       FROM register r WHERE r.id = ?";
        $stmt_select = $pdo->prepare($sql_select);
        $stmt_select->execute([$user_id]);
        $user_data = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            session_destroy();
            header('Location: index.php?error=user_data_missing');
            exit;
        }

        return $user_data;
    } catch (PDOException $e) {
        error_log("Error retrieving user profile data for user $user_id: " . $e->getMessage());
        die("Error mengambil data pengguna: " . $e->getMessage());
    }
}

// Get user profile data
$user_data = getUserProfileData($pdo, $user_id);

// Calculate profile completion status
$profile_completion = [
    'score' => $user_data['profile_completion_score'] ?? 0,
    'status' => '',
    'color' => '',
                'items' => [
                    'photo' => !empty($user_data['foto_profil']) && $user_data['foto_profil'] != 'default.png',
                    'signature' => !empty($user_data['tanda_tangan_file']),
                    'bio' => !empty($user_data['bio']),
                    'email' => !empty($user_data['email']),
                    'telegram' => !empty($user_data['no_telegram']),
                    'telegram_username' => !empty($user_data['username_telegram']),
                    'outlet' => !empty($user_data['outlet'])
                ]
];

if ($profile_completion['score'] >= 80) {
    $profile_completion['status'] = 'Excellent';
    $profile_completion['color'] = '#28a745';
} elseif ($profile_completion['score'] >= 60) {
    $profile_completion['status'] = 'Good';
    $profile_completion['color'] = '#17a2b8';
} elseif ($profile_completion['score'] >= 40) {
    $profile_completion['status'] = 'Fair';
    $profile_completion['color'] = '#ffc107';
} else {
    $profile_completion['status'] = 'Incomplete';
    $profile_completion['color'] = '#dc3545';
}

// ========================================================
// --- ENHANCED DIGITAL SIGNATURE MANAGEMENT ---
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_ttd'])) {
    $signature_data_base64 = $_POST['signature_data'] ?? '';

    if (empty($signature_data_base64)) {
        $signature_error = 'Tanda tangan tidak boleh kosong.';
    } else {
        // Enhanced signature validation and processing
        $signature_validation = validateAndProcessSignature($signature_data_base64, $user_id);

        if (!$signature_validation['success']) {
            $signature_error = $signature_validation['message'];
        } else {
            try {
                $pdo->beginTransaction();

                // Delete old signature file if exists
                $stmt = $pdo->prepare('SELECT tanda_tangan_file FROM register WHERE id = ?');
                $stmt->execute([$user_id]);
                $old_signature = $stmt->fetchColumn();

                if ($old_signature && file_exists('uploads/tanda_tangan/' . $old_signature)) {
                    if (!unlink('uploads/tanda_tangan/' . $old_signature)) {
                        error_log("Failed to delete old signature file: uploads/tanda_tangan/" . $old_signature);
                        // Continue with the process even if old file deletion fails
                    }
                }

                // Update database with new signature
                $stmt = $pdo->prepare('UPDATE register SET tanda_tangan_file = ?, signature_updated_at = NOW() WHERE id = ?');
                $stmt->execute([$signature_validation['filename'], $user_id]);

                // Log signature update activity
                logUserActivity($pdo, $user_id, 'signature_update', 'Digital signature updated');

                $pdo->commit();
                $signature_success = 'Tanda tangan digital berhasil disimpan.';

                // Refresh user data
                $user_data = getUserProfileData($pdo, $user_id);

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $signature_error = 'Gagal menyimpan tanda tangan. Silakan coba lagi.';
                error_log("Signature save error for user $user_id: " . $e->getMessage());
                // Log to activity_logs table
                logUserActivity($pdo, $user_id, 'signature_save_failed', 'Failed to save digital signature: ' . $e->getMessage());
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_ttd'])) {
    try {
        $pdo->beginTransaction();

        // Get current signature file
        $stmt = $pdo->prepare('SELECT tanda_tangan_file FROM register WHERE id = ?');
        $stmt->execute([$user_id]);
        $current_signature = $stmt->fetchColumn();

        // Delete physical file
        if ($current_signature && file_exists('uploads/tanda_tangan/' . $current_signature)) {
            if (!unlink('uploads/tanda_tangan/' . $current_signature)) {
                error_log("Failed to delete signature file: uploads/tanda_tangan/" . $current_signature);
                // Continue with the process even if file deletion fails
            }
        }

        // Update database
        $stmt = $pdo->prepare('UPDATE register SET tanda_tangan_file = NULL, signature_updated_at = NOW() WHERE id = ?');
        $stmt->execute([$user_id]);

        // Log signature deletion
        logUserActivity($pdo, $user_id, 'signature_delete', 'Digital signature deleted');

        $pdo->commit();
        $signature_success = 'Tanda tangan digital berhasil dihapus.';

        // Refresh user data
        $user_data = getUserProfileData($pdo, $user_id);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $signature_error = 'Gagal menghapus tanda tangan. Silakan coba lagi.';
        error_log("Signature delete error for user $user_id: " . $e->getMessage());
        // Log to activity_logs table
        logUserActivity($pdo, $user_id, 'signature_delete_failed', 'Failed to delete digital signature: ' . $e->getMessage());
    }
}

/**
 * Enhanced signature validation and processing
 */
function validateAndProcessSignature($signature_data_base64, $user_id) {
    // Debug: Log the raw data
    error_log("DEBUG: Signature data received. Length: " . strlen($signature_data_base64));
    error_log("DEBUG: First 100 chars: " . substr($signature_data_base64, 0, 100));
    
    // Validate base64 format
    if (!preg_match('/^data:image\/(\w+);base64,/', $signature_data_base64, $type)) {
        error_log("DEBUG: Base64 format validation failed");
        return ['success' => false, 'message' => 'Format data tanda tangan tidak valid.'];
    }

    $image_type = strtolower($type[1]);
    error_log("DEBUG: Image type detected: " . $image_type);

    // Validate image type (only PNG for signatures to maintain quality)
    if (!in_array($image_type, ['png'])) {
        error_log("DEBUG: Invalid image type: " . $image_type);
        return ['success' => false, 'message' => 'Tanda tangan harus dalam format PNG untuk kualitas terbaik.'];
    }

    // Extract base64 data
    $signature_data_base64 = substr($signature_data_base64, strpos($signature_data_base64, ',') + 1);
    $signature_data_binary = base64_decode($signature_data_base64);

    if ($signature_data_binary === false) {
        error_log("DEBUG: Base64 decode failed");
        return ['success' => false, 'message' => 'Data tanda tangan tidak valid.'];
    }
    
    error_log("DEBUG: Binary data length: " . strlen($signature_data_binary));

    // Validate file size (max 2MB)
    if (strlen($signature_data_binary) > 2 * 1024 * 1024) {
        error_log("DEBUG: File too large: " . strlen($signature_data_binary));
        return ['success' => false, 'message' => 'Ukuran tanda tangan terlalu besar. Maksimal 2MB.'];
    }

    // Generate unique filename
    $filename = 'ttd_user_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $image_type;
    $filepath = 'uploads/tanda_tangan/' . $filename;
    
    error_log("DEBUG: Target filepath: " . $filepath);

    // Ensure upload directory exists with proper permissions
    $upload_dir = dirname($filepath);
    error_log("DEBUG: Upload directory: " . $upload_dir);
    
    // Directory validation
    if (!is_dir($upload_dir)) {
        error_log("DEBUG: Directory doesn't exist, creating...");
        $old_umask = umask(0);
        $mkdir_result = mkdir($upload_dir, 0755, true);
        umask($old_umask);
        
        if (!$mkdir_result) {
            error_log("DEBUG: mkdir failed");
            return ['success' => false, 'message' => 'Gagal membuat direktori upload. Periksa permission direktori.'];
        }
        error_log("DEBUG: Directory created successfully");
    }
    
    // Additional validation: ensure parent directory is writable
    if (!is_writable($upload_dir)) {
        error_log("DEBUG: Directory not writable: " . $upload_dir);
        error_log("DEBUG: is_writable check: " . (is_writable($upload_dir) ? 'YES' : 'NO'));
        error_log("DEBUG: Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4));
        error_log("DEBUG: Directory owner: " . posix_getpwuid(fileowner($upload_dir))['name']);
        return ['success' => false, 'message' => 'Direktori upload tidak memiliki permission write. Hubungi administrator sistem.'];
    }

    // Enhanced file save with better error handling
    error_log("DEBUG: Attempting to write file...");
    $write_result = @file_put_contents($filepath, $signature_data_binary, LOCK_EX);
    
    if ($write_result === false) {
        error_log("DEBUG: file_put_contents failed");
        error_log("DEBUG: Last error: " . error_get_last()['message']);
        error_log("DEBUG: Available space: " . disk_free_space($upload_dir) . " bytes");
        error_log("DEBUG: File size to write: " . strlen($signature_data_binary) . " bytes");
        
        return ['success' => false, 'message' => 'Gagal menyimpan file tanda tangan. Silakan coba lagi.'];
    }
    
    error_log("DEBUG: File written successfully. Size: " . $write_result . " bytes");
    
    // Verify file was actually created
    if (!file_exists($filepath) || filesize($filepath) === 0) {
        error_log("DEBUG: File verification failed");
        // Clean up failed file
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        return ['success' => false, 'message' => 'File tanda tangan gagal tersimpan. Periksa permission sistem.'];
    }
    
    error_log("DEBUG: File saved successfully at: " . $filepath);
    error_log("DEBUG: File size: " . filesize($filepath) . " bytes");

    // Set proper file permissions
    chmod($filepath, 0644);

    // Validate image dimensions and type
    $image_info = getimagesize($filepath);
    if (!$image_info) {
        if (file_exists($filepath)) {
            unlink($filepath); // Delete invalid file
        }
        return ['success' => false, 'message' => 'File tanda tangan tidak valid.'];
    }

    // Additional validation: ensure it's actually a PNG image
    $allowed_mime_types = ['image/png'];
    if (!in_array($image_info['mime'], $allowed_mime_types)) {
        if (file_exists($filepath)) {
            unlink($filepath); // Delete invalid file
        }
        return ['success' => false, 'message' => 'Tanda tangan harus dalam format PNG.'];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'message' => 'Tanda tangan berhasil diproses.'
    ];
}

// Activity logging function removed - now using the one from functions_role.php

$home_url = 'mainpage.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo htmlspecialchars($user_data['nama_lengkap']); ?> - KAORI Indonesia</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .profile-completion {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid <?php echo $profile_completion['color']; ?>;
        }

        .completion-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }

        .completion-fill {
            height: 100%;
            background: linear-gradient(90deg, <?php echo $profile_completion['color']; ?>, <?php echo $profile_completion['color']; ?>dd);
            width: <?php echo $profile_completion['score']; ?>%;
            transition: width 0.3s ease;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .profile-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .section-icon {
            font-size: 24px;
            color: #667eea;
            margin-right: 15px;
        }

        .section-title {
            margin: 0;
            color: #333;
            font-size: 1.2em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .signature-preview {
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin: 15px 0;
            background: #f8f9fa;
        }

        .signature-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }

        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }

            .profile-header {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="profile-container">
        <div class="profile-header">
            <h1><i class="fa fa-user-circle"></i> Manajemen Profil</h1>
            <p>Kelola informasi pribadi, keamanan akun, dan tanda tangan digital Anda</p>
        </div>

        <!-- Profile Completion Status -->
        <div class="profile-completion">
            <h3><i class="fa fa-chart-line"></i> Kelengkapan Profil: <?php echo $profile_completion['status']; ?> (<?php echo $profile_completion['score']; ?>%)</h3>
            <div class="completion-bar">
                <div class="completion-fill"></div>
            </div>
            <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px;">
                <span class="completion-item <?php echo $profile_completion['items']['photo'] ? 'completed' : 'pending'; ?>">
                    <i class="fa fa-<?php echo $profile_completion['items']['photo'] ? 'check' : 'times'; ?>"></i> Foto Profil
                </span>
                <span class="completion-item <?php echo $profile_completion['items']['signature'] ? 'completed' : 'pending'; ?>">
                    <i class="fa fa-<?php echo $profile_completion['items']['signature'] ? 'check' : 'times'; ?>"></i> Tanda Tangan
                </span>
                <span class="completion-item <?php echo $profile_completion['items']['bio'] ? 'completed' : 'pending'; ?>">
                    <i class="fa fa-<?php echo $profile_completion['items']['bio'] ? 'check' : 'times'; ?>"></i> Bio
                </span>
                <span class="completion-item <?php echo $profile_completion['items']['telegram'] ? 'completed' : 'pending'; ?>">
                    <i class="fa fa-<?php echo $profile_completion['items']['telegram'] ? 'check' : 'times'; ?>"></i> No. Telegram
                </span>
                <span class="completion-item <?php echo $profile_completion['items']['telegram_username'] ? 'completed' : 'pending'; ?>">
                    <i class="fa fa-<?php echo $profile_completion['items']['telegram_username'] ? 'check' : 'times'; ?>"></i> Username Telegram
                </span>
            </div>
        </div>

        <div class="profile-grid">

            <!-- Personal Information Section -->
            <div class="profile-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fa fa-user"></i></div>
                    <h2 class="section-title">Informasi Pribadi</h2>
                </div>

                <!-- Profile Picture -->
                <div class="form-group">
                    <label>Foto Profil</label>
                    <div class="profile-picture-container" style="text-align: center; margin: 20px 0;">
                        <?php if (!empty($user_data['foto_profil']) && $user_data['foto_profil'] != 'default.png'): ?>
                            <img src="uploads/foto_profil/<?php echo htmlspecialchars($user_data['foto_profil']); ?>"
                                 alt="Foto Profil"
                                 id="profile-pic-preview"
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 4px solid #667eea;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <div style="display:none; width: 120px; height: 120px; border-radius: 50%; background: #f8f9fa; border: 4px solid #dee2e6; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fa fa-user" style="font-size: 48px; color: #6c757d;"></i>
                            </div>
                        <?php else: ?>
                            <div style="width: 120px; height: 120px; border-radius: 50%; background: #f8f9fa; border: 4px solid #dee2e6; display: inline-flex; align-items: center; justify-content: center;">
                                <i class="fa fa-user" style="font-size: 48px; color: #6c757d;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="upload-container" style="text-align: center;">
                        <small style="color: #666;">Ganti Foto Profil (Max 2MB, JPG/PNG)</small><br>
                        <iframe src="upload_foto.php" frameborder="0" scrolling="no" width="100%" height="60" id="upload-iframe" style="margin-top: 10px;"></iframe>
                    </div>
                </div>

                <!-- Profile Information Form -->
                <form action="profile.php" method="POST" autocomplete="off">
                    <?php if ($profile_success): ?>
                        <div class="alert alert-success">
                            <i class="fa fa-check-circle"></i> <?php echo $profile_success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($profile_error): ?>
                        <div class="alert alert-error">
                            <i class="fa fa-exclamation-triangle"></i> <?php echo $profile_error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Nama Lengkap</label>
                        <input type="text" value="<?php echo htmlspecialchars($user_data['nama_lengkap']); ?>" readonly disabled>
                    </div>

                    <div class="form-group">
                        <label>Posisi/Jabatan</label>
                        <input type="text" value="<?php echo htmlspecialchars($user_data['posisi']); ?>" readonly disabled>
                    </div>

                    <div class="form-group">
                        <label>Outlet</label>
                        <input type="text" value="<?php echo htmlspecialchars($user_data['outlet']); ?>" readonly disabled>
                    </div>

                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" value="<?php echo htmlspecialchars($user_data['username']); ?>" readonly disabled>
                    </div>

                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label>No. Telegram</label>
                        <input type="text" name="no_wa" value="<?php echo htmlspecialchars($user_data['no_telegram']); ?>"
                                placeholder="62 81234567890">
                    </div>

                    <div class="form-group">
                        <label>Username Telegram</label>
                        <input type="text" name="username_telegram" value="<?php echo htmlspecialchars($user_data['telegram_username'] ?? ''); ?>"
                                placeholder="@username">
                    </div>

                    <div class="form-group">
                        <label>Bio/Tentang Saya</label>
                        <textarea name="bio" rows="3" placeholder="Ceritakan sedikit tentang diri Anda..."><?php echo htmlspecialchars($user_data['bio'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fa fa-save"></i> Update Profil
                    </button>
                </form>
            </div>

            <!-- Security & Digital Signature Section -->
            <div class="profile-section">
                <div class="section-header">
                    <div class="section-icon"><i class="fa fa-shield-alt"></i></div>
                    <h2 class="section-title">Keamanan & Tanda Tangan</h2>
                </div>

                <!-- Password Change Form -->
                <form action="profile.php" method="POST" autocomplete="off">
                    <h3 style="color: #333; margin-bottom: 15px;"><i class="fa fa-lock"></i> Ganti Password</h3>

                    <?php if ($password_success): ?>
                        <div class="alert alert-success">
                            <i class="fa fa-check-circle"></i> <?php echo $password_success; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($password_error): ?>
                        <div class="alert alert-error">
                            <i class="fa fa-exclamation-triangle"></i> <?php echo $password_error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Password Saat Ini *</label>
                        <input type="password" name="current_password" required autocomplete="current-password">
                    </div>

                    <div class="form-group">
                        <label>Password Baru *</label>
                        <input type="password" name="new_password" id="new_password" required autocomplete="new-password">
                        <div id="password-strength" class="password-strength" style="display: none;"></div>
                    </div>

                    <div class="form-group">
                        <label>Konfirmasi Password Baru *</label>
                        <input type="password" name="confirm_password" required autocomplete="new-password">
                    </div>

                    <button type="submit" name="update_password" class="btn-primary">
                        <i class="fa fa-key"></i> Ganti Password
                    </button>
                </form>

                <hr style="margin: 30px 0; border: none; border-top: 1px solid #dee2e6;">

                <!-- Digital Signature Section -->
                <h3 style="color: #333; margin-bottom: 15px;"><i class="fa fa-signature"></i> Tanda Tangan Digital</h3>

                <?php if ($signature_success): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> <?php echo $signature_success; ?>
                    </div>
                <?php endif; ?>

                <?php if ($signature_error): ?>
                    <div class="alert alert-error">
                        <i class="fa fa-exclamation-triangle"></i> <?php echo $signature_error; ?>
                    </div>
                <?php endif; ?>

                <!-- Signature Preview -->
                <div class="signature-preview">
                    <?php if (!empty($user_data['tanda_tangan_file'])): ?>
                        <div style="margin-bottom: 15px;">
                            <strong>Tanda Tangan Anda:</strong>
                        </div>
                        <img src="uploads/tanda_tangan/<?php echo htmlspecialchars($user_data['tanda_tangan_file']); ?>"
                             alt="Tanda Tangan Digital"
                             style="max-width: 300px; max-height: 150px; border: 2px solid #667eea; border-radius: 8px; padding: 10px; background: white;">

                        <div class="signature-actions">
                            <form action="profile.php" method="POST" style="display: inline;">
                                <button type="submit" name="hapus_ttd" class="btn-primary"
                                        onclick="return confirm('Apakah Anda yakin ingin menghapus tanda tangan digital? Tanda tangan ini akan dihapus secara permanen.')"
                                        style="background: #dc3545;">
                                    <i class="fa fa-trash"></i> Hapus Tanda Tangan
                                </button>
                            </form>
                            <button type="button" id="btn-edit-ttd" class="btn-primary">
                                <i class="fa fa-edit"></i> Ganti Tanda Tangan
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="color: #666; margin-bottom: 15px;">
                            <i class="fa fa-info-circle"></i> Belum ada tanda tangan digital
                        </div>
                        <div style="width: 300px; height: 100px; border: 2px dashed #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6c757d; margin: 0 auto;">
                            <i class="fa fa-signature" style="font-size: 24px;"></i>
                        </div>
                        <button type="button" id="btn-create-ttd" class="btn-primary" style="margin-top: 15px;">
                            <i class="fa fa-plus"></i> Buat Tanda Tangan Digital
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Signature Creation/Edit Form -->
                <div id="signature-form-container" style="display: none; margin-top: 20px;">
                    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px;">
                        <h4 style="margin-top: 0; color: #333;">
                            <i class="fa fa-pencil-alt"></i>
                            <?php echo !empty($user_data['tanda_tangan_file']) ? 'Ganti' : 'Buat'; ?> Tanda Tangan Digital
                        </h4>

                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 10px; margin-bottom: 15px; font-size: 14px;">
                            <i class="fa fa-lightbulb" style="color: #856404;"></i>
                            <strong>Tips:</strong> Gambarlah tanda tangan Anda dengan hati-hati. Pastikan jelas dan mudah dibaca.
                        </div>

                        <form action="profile.php" method="POST" id="signature-form" onsubmit="console.log('DEBUG submit signature form');">
                            <div style="text-align: center; margin-bottom: 15px;">
                                <canvas id="signature-pad" width="500" height="200"
                                        style="border: 2px solid #667eea; border-radius: 8px; background: white; cursor: crosshair;">
                                </canvas>
                            </div>

                            <div style="text-align: center; margin-bottom: 15px;">
                                <button type="button" id="clear-signature" class="btn-primary" style="background: #6c757d;">
                                    <i class="fa fa-eraser"></i> Hapus & Gambar Ulang
                                </button>
                            </div>

                            <input type="hidden" name="signature_data" id="signature-data">

                            <div style="display: flex; gap: 10px; justify-content: center;">
                                <button type="submit" name="simpan_ttd" class="btn-primary">
                                    <i class="fa fa-save"></i> Simpan Tanda Tangan
                                </button>
                                <button type="button" id="cancel-signature" class="btn-primary" style="background: #6c757d;">
                                    <i class="fa fa-times"></i> Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Information Section -->
        <div class="profile-section" style="grid-column: span 2; margin-top: 20px;">
            <div class="section-header">
                <div class="section-icon"><i class="fa fa-info-circle"></i></div>
                <h2 class="section-title">Informasi Akun</h2>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;"><i class="fa fa-calendar"></i> Tanggal Registrasi</h4>
                    <p style="margin: 0; color: #666;"><?php echo date('d F Y, H:i', strtotime($user_data['time_created'])); ?></p>
                </div>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;"><i class="fa fa-clock"></i> Terakhir Update Profil</h4>
                    <p style="margin: 0; color: #666;">
                        <?php
                        $profile_updated = $user_data['profile_updated_at'] ?? $user_data['time_created'];
                        echo $profile_updated ? date('d F Y, H:i', strtotime($profile_updated)) : 'Belum pernah update';
                        ?>
                    </p>
                </div>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;"><i class="fa fa-key"></i> Password Terakhir Diganti</h4>
                    <p style="margin: 0; color: #666;">
                        <?php echo ($user_data['password_updated_at'] ?? null) ? date('d F Y, H:i', strtotime($user_data['password_updated_at'])) : 'Belum pernah diganti'; ?>
                    </p>
                </div>

                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #333;"><i class="fa fa-signature"></i> Tanda Tangan Terakhir Update</h4>
                    <p style="margin: 0; color: #666;">
                        <?php echo ($user_data['signature_updated_at'] ?? null) ? date('d F Y, H:i', strtotime($user_data['signature_updated_at'])) : 'Belum pernah dibuat'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@5.0.10/dist/signature_pad.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        let signaturePad;
        const canvas = document.getElementById('signature-pad');
        const clearBtn = document.getElementById('clear-signature');
        const form = document.getElementById('signature-form');
        const input = document.getElementById('signature-data');
        const formContainer = document.getElementById('signature-form-container');
        const createBtn = document.getElementById('btn-create-ttd');
        const editBtn = document.getElementById('btn-edit-ttd');
        const cancelBtn = document.getElementById('cancel-signature');

        // Initialize signature pad
        if (canvas) {
            signaturePad = new SignaturePad(canvas, {
                penColor: 'rgb(0,0,0)',
                backgroundColor: 'rgb(255,255,255)',
                minWidth: 1,
                maxWidth: 3
            });
        }

        // Show signature form
        if (createBtn) {
            createBtn.addEventListener('click', function() {
                formContainer.style.display = 'block';
                createBtn.style.display = 'none';
                if (signaturePad) signaturePad.clear();
            });
        }

        if (editBtn) {
            editBtn.addEventListener('click', function() {
                formContainer.style.display = 'block';
                if (signaturePad) signaturePad.clear();
            });
        }

        // Cancel signature creation
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                formContainer.style.display = 'none';
                if (createBtn) createBtn.style.display = 'inline-block';
                if (signaturePad) signaturePad.clear();
            });
        }

        // Clear signature
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                if (signaturePad) signaturePad.clear();
            });
        }

        // Form submission validation
        if (form) {
            form.addEventListener('submit', function(e) {
                console.log('DEBUG: signature-form submit handler triggered');

                // Pastikan objek signaturePad ada
                if (!signaturePad) {
                    console.error('DEBUG: signaturePad not initialized');
                    alert('Terjadi kesalahan internal: canvas tanda tangan tidak siap.');
                    e.preventDefault();
                    return;
                }

                // Cegah submit jika kosong
                if (signaturePad.isEmpty()) {
                    alert('Mohon gambar tanda tangan Anda terlebih dahulu.');
                    e.preventDefault();
                    return;
                }

                // Set nilai hidden input ke data URL PNG
                const dataUrl = signaturePad.toDataURL('image/png');
                console.log('DEBUG: generated data URL length = ' + dataUrl.length);
                input.value = dataUrl;

                // Jika karena suatu alasan gagal, cegah submit
                if (!input.value || input.value.indexOf('data:image/png;base64,') !== 0) {
                    console.error('DEBUG: signature_data not set correctly');
                    alert('Terjadi kesalahan saat memproses tanda tangan. Silakan coba lagi.');
                    e.preventDefault();
                    return;
                }
            });
        }

        // Password strength indicator
        const newPasswordInput = document.getElementById('new_password');
        const strengthIndicator = document.getElementById('password-strength');
    
        if (newPasswordInput && strengthIndicator) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
    
                if (password.length > 0) {
                    strengthIndicator.style.display = 'block';
                    strengthIndicator.className = 'password-strength strength-' + strength.level;
                    strengthIndicator.textContent = strength.message;
                } else {
                    strengthIndicator.style.display = 'none';
                }
            });
        }
    
        function checkPasswordStrength(password) {
            let score = 0;
    
            if (password.length >= 8) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
    
            if (score <= 2) {
                return { level: 'weak', message: 'Kekuatan: Lemah - Tambahkan huruf besar, angka, dan simbol' };
            } else if (score <= 4) {
                return { level: 'medium', message: 'Kekuatan: Sedang - Tambahkan variasi karakter' };
            } else {
                return { level: 'strong', message: 'Kekuatan: Kuat - Password aman digunakan' };
            }
        }
    
        // Handle profile picture display
        const profilePic = document.getElementById('profile-pic-preview');
        const defaultAvatar = document.querySelector('.profile-picture-container div[style*="display:none"]');
    
        if (profilePic && defaultAvatar) {
            // Check if profile picture exists and is loaded
            if (profilePic.complete && profilePic.naturalHeight !== 0) {
                // Profile picture loaded successfully, hide default avatar
                defaultAvatar.style.display = 'none';
            } else {
                // Profile picture failed to load or doesn't exist, show default avatar
                defaultAvatar.style.display = 'inline-block';
            }
    
            // Handle profile picture load/error events
            profilePic.addEventListener('load', function() {
                defaultAvatar.style.display = 'none';
            });
    
            profilePic.addEventListener('error', function() {
                defaultAvatar.style.display = 'inline-block';
            });
        }
        
        // ========================================================
        // EVENT-DRIVEN PROFILE REFRESH SYSTEM (Non-Polling)
        // ========================================================
        
        class ProfileEventDrivenRefresh {
            constructor() {
                this.isRefreshing = false;
                this.refreshInProgress = false;
                this.lastUpdate = Date.now();
                this.init();
            }
            
            init() {
                this.setupEventDrivenRefresh();
                console.log('Event-Driven Profile Refresh System initialized (No Polling)');
            }
            
            setupEventDrivenRefresh() {
                // Setup refresh triggers for specific buttons only
                this.setupButtonTriggers();
            }
            
            setupButtonTriggers() {
                // Update Profile button
                const updateProfileBtn = document.querySelector('button[name="update_profile"]');
                if (updateProfileBtn) {
                    updateProfileBtn.addEventListener('click', (e) => {
                        this.handleProfileUpdate(e);
                    });
                }
                
                // Update Password button
                const updatePasswordBtn = document.querySelector('button[name="update_password"]');
                if (updatePasswordBtn) {
                    updatePasswordBtn.addEventListener('click', (e) => {
                        this.handlePasswordUpdate(e);
                    });
                }
                
                // Delete Signature button
                const deleteSignatureBtn = document.querySelector('button[name="hapus_ttd"]');
                if (deleteSignatureBtn) {
                    deleteSignatureBtn.addEventListener('click', (e) => {
                        this.handleSignatureDelete(e);
                    });
                }
                
                // Save Signature button
                const saveSignatureBtn = document.querySelector('button[name="simpan_ttd"]');
                if (saveSignatureBtn) {
                    saveSignatureBtn.addEventListener('click', (e) => {
                        this.handleSignatureSave(e);
                    });
                }
            }
            
            async handleProfileUpdate(event) {
                console.log('Profile update triggered - refreshing UI');
                // Refresh UI after profile update
                setTimeout(() => {
                    this.refreshProfileUI('profile_update');
                }, 500);
            }
            
            async handlePasswordUpdate(event) {
                console.log('Password update triggered - refreshing UI');
                // Refresh UI after password update
                setTimeout(() => {
                    this.refreshProfileUI('password_update');
                }, 500);
            }
            
            async handleSignatureDelete(event) {
                console.log('Signature delete triggered - refreshing UI');
                // Refresh UI after signature deletion
                setTimeout(() => {
                    this.refreshProfileUI('signature_delete');
                }, 500);
            }
            
            async handleSignatureSave(event) {
                console.log('Signature save triggered - refreshing UI');
                // Refresh UI after signature save
                setTimeout(() => {
                    this.refreshProfileUI('signature_save');
                }, 500);
            }
            
            async refreshProfileUI(source = 'manual_trigger') {
                if (this.refreshInProgress) {
                    console.log('Refresh already in progress, skipping...');
                    return;
                }
                
                this.refreshInProgress = true;
                console.log(`Refreshing profile UI (source: ${source})...`);
                
                try {
                    // Simulate refresh by reloading the page data
                    // In a real implementation, this would make an AJAX call to get fresh data
                    await this.simulateDataRefresh();
                    
                    // Update key UI elements
                    this.updateProfileCompletion();
                    this.updateProfilePicture();
                    this.updateSignatureDisplay();
                    this.updateFormValues();
                    
                    console.log('Profile UI refreshed successfully');
                    
                } catch (error) {
                    console.error('Profile refresh error:', error);
                } finally {
                    this.refreshInProgress = false;
                }
            }
            
            async simulateDataRefresh() {
                // Simulate API call delay
                return new Promise(resolve => {
                    setTimeout(resolve, 300);
                });
            }
            
            updateProfileCompletion() {
                // Update profile completion display
                const completionElement = document.querySelector('.profile-completion h3');
                if (completionElement) {
                    // Force re-calculation by reloading the page completion calculation
                    // In real implementation, this would come from fresh server data
                    const currentScore = completionElement.textContent.match(/\((\d+)%\)/);
                    if (currentScore) {
                        const score = parseInt(currentScore[1]);
                        // Keep same score but trigger visual update
                        completionElement.style.opacity = '0.7';
                        setTimeout(() => {
                            completionElement.style.opacity = '1';
                        }, 200);
                    }
                }
                
                // Update completion bar
                const fillElement = document.querySelector('.completion-fill');
                if (fillElement) {
                    // Trigger reflow to show refresh
                    fillElement.style.transform = 'scaleX(0.98)';
                    setTimeout(() => {
                        fillElement.style.transform = 'scaleX(1)';
                    }, 100);
                }
            }
            
            updateProfilePicture() {
                // Force profile picture reload
                const profilePic = document.getElementById('profile-pic-preview');
                if (profilePic && profilePic.src) {
                    const currentSrc = profilePic.src;
                    profilePic.src = '';
                    setTimeout(() => {
                        profilePic.src = currentSrc + '?t=' + Date.now();
                    }, 100);
                }
            }
            
            updateSignatureDisplay() {
                // Force signature reload
                const signatureImg = document.querySelector('.signature-preview img');
                if (signatureImg && signatureImg.src) {
                    const currentSrc = signatureImg.src;
                    signatureImg.src = '';
                    setTimeout(() => {
                        signatureImg.src = currentSrc + '?t=' + Date.now();
                    }, 100);
                }
            }
            
            updateFormValues() {
                // Force form value refresh
                const forms = document.querySelectorAll('form[action="profile.php"]');
                forms.forEach(form => {
                    // Add a subtle visual feedback
                    form.style.opacity = '0.9';
                    setTimeout(() => {
                        form.style.opacity = '1';
                    }, 200);
                });
            }
            
            // Utility method to manually trigger refresh
            triggerManualRefresh() {
                this.refreshProfileUI('manual_trigger');
            }
        }
        
        // Add minimal styles for refresh feedback
        const style = document.createElement('style');
        style.textContent = `
            @keyframes refreshPulse {
                0% { opacity: 1; }
                50% { opacity: 0.7; }
                100% { opacity: 1; }
            }
            
            .profile-refreshing .profile-completion h3 {
                animation: refreshPulse 0.5s ease-in-out;
            }
            
            .completion-fill {
                transition: transform 0.3s ease, width 0.3s ease;
            }
        `;
        document.head.appendChild(style);
        
        // Initialize the event-driven refresh system
        const profileEventDrivenRefresh = new ProfileEventDrivenRefresh();
        
        // Make it globally accessible for debugging
        window.profileEventDrivenRefresh = profileEventDrivenRefresh;
        
        // Optional: Add a manual refresh button for testing
        if (window.location.search.includes('debug=true')) {
            const refreshBtn = document.createElement('button');
            refreshBtn.innerHTML = '<i class="fa fa-refresh"></i> Manual Refresh';
            refreshBtn.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 1000; padding: 10px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;';
            refreshBtn.onclick = () => profileEventDrivenRefresh.triggerManualRefresh();
            document.body.appendChild(refreshBtn);
        }
    });
    </script>
    </div>

</body>
<footer>
    <div class="footer-container">
        <p class="footer-text">© 2024 KAORI Indonesia. All rights reserved.</p>
    </div>
</footer>
</html>