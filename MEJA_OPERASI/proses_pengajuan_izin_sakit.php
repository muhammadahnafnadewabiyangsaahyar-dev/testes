<?php
session_start();
require_once 'connect.php';
require_once 'security_helper.php'; // Load security functions

// Start secure session
SecurityHelper::secureSessionStart();

// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=notloggedin');
    exit;
}

// Validate session
if (!SecurityHelper::validateSession()) {
    session_destroy();
    header('Location: index.php?error=sessionexpired');
    exit;
}

$user_id = $_SESSION['user_id'];

// Rate limiting
if (!SecurityHelper::checkRateLimit('izin_sakit_' . $user_id, 5, 300)) {
    header('Location: ajukan_izin_sakit.php?error=toomanyattempts');
    exit;
}

// Validasi POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ajukan_izin_sakit.php?error=invalidmethod');
    exit;
}

// CSRF Token Validation
$csrf_token = $_POST['csrf_token'] ?? '';
if (!SecurityHelper::validateCSRFToken($csrf_token)) {
    SecurityHelper::logSuspiciousActivity($user_id, 'csrf_validation_failed', [
        'action' => 'submit_izin_sakit',
        'ip' => SecurityHelper::getClientIP()
    ]);
    header('Location: ajukan_izin_sakit.php?error=invalidtoken');
    exit;
}

// Ambil data dari form dan sanitize
$perihal = SecurityHelper::sanitizeSQL(trim($_POST['perihal'] ?? ''));
$tanggal_mulai = SecurityHelper::sanitizeSQL(trim($_POST['tanggal_mulai'] ?? ''));
$tanggal_selesai = SecurityHelper::sanitizeSQL(trim($_POST['tanggal_selesai'] ?? ''));
$lama_izin = intval($_POST['lama_izin'] ?? 0);
$alasan = SecurityHelper::sanitizeSQL(trim($_POST['alasan'] ?? ''));

// Validasi field wajib
if (empty($perihal) || empty($tanggal_mulai) || empty($tanggal_selesai) || empty($alasan) || $lama_izin < 1) {
    header('Location: ajukan_izin_sakit.php?error=datakosong');
    exit;
}

// Validasi perihal
if (!in_array($perihal, ['Izin', 'Sakit'])) {
    header('Location: ajukan_izin_sakit.php?error=perihalvalid');
    exit;
}

// Validasi tanggal
$start = new DateTime($tanggal_mulai);
$end = new DateTime($tanggal_selesai);
if ($end < $start) {
    header('Location: ajukan_izin_sakit.php?error=tanggalinvalid');
    exit;
}

// === HANDLE FILE UPLOAD ===
$file_surat = null;
if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
    // Validate file with security helper
    $file_validation = SecurityHelper::validateFileUpload(
        $_FILES['file_surat'],
        ['image/jpeg', 'image/png', 'application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        2097152 // 2MB
    );
    
    if (!$file_validation['valid']) {
        SecurityHelper::logSuspiciousActivity($user_id, 'invalid_file_upload', [
            'errors' => $file_validation['errors'],
            'file_name' => $_FILES['file_surat']['name']
        ]);
        header('Location: ajukan_izin_sakit.php?error=fileinvalid&msg=' . urlencode(implode(', ', $file_validation['errors'])));
        exit;
    }

    $upload_dir = 'uploads/surat_izin/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate safe filename
    $file_surat = SecurityHelper::generateSafeFilename($_FILES['file_surat']['name']);
    $file_path = $upload_dir . $file_surat;

    if (!move_uploaded_file($_FILES['file_surat']['tmp_name'], $file_path)) {
        header('Location: ajukan_izin_sakit.php?error=fileuploadfailed');
        exit;
    }
}

// === HANDLE TANDA TANGAN ===
$tanda_tangan_file = null;

// Cek apakah user sudah punya tanda tangan tersimpan
$sql_ttd = "SELECT tanda_tangan_file FROM register WHERE id = ?";
$stmt_ttd = $pdo->prepare($sql_ttd);
$stmt_ttd->execute([$user_id]);
$user_ttd = $stmt_ttd->fetch(PDO::FETCH_ASSOC);

if (!empty($user_ttd['tanda_tangan_file'])) {
    // Gunakan tanda tangan yang sudah tersimpan
    $tanda_tangan_file = $user_ttd['tanda_tangan_file'];
} else {
    // User harus upload tanda tangan baru
    $signature_data = $_POST['signature_data'] ?? '';
    
    if (empty($signature_data)) {
        header('Location: ajukan_izin_sakit.php?error=ttdkosong');
        exit;
    }

    // Decode base64 signature
    if (preg_match('/^data:image\/(\w+);base64,/', $signature_data, $matches)) {
        $image_type = $matches[1];
        $signature_data = substr($signature_data, strpos($signature_data, ',') + 1);
        $signature_data = base64_decode($signature_data);

        if ($signature_data === false) {
            header('Location: ajukan_izin_sakit.php?error=ttddecodefailed');
            exit;
        }

        $upload_dir_ttd = 'uploads/tanda_tangan/';
        if (!is_dir($upload_dir_ttd)) {
            mkdir($upload_dir_ttd, 0755, true);
        }

        $tanda_tangan_file = 'ttd_' . $user_id . '_' . time() . '.' . $image_type;
        $ttd_path = $upload_dir_ttd . $tanda_tangan_file;

        if (!file_put_contents($ttd_path, $signature_data)) {
            header('Location: ajukan_izin_sakit.php?error=ttdsavefailed');
            exit;
        }

        // Update tanda tangan di register
        $sql_update_ttd = "UPDATE register SET tanda_tangan_file = ? WHERE id = ?";
        $stmt_update_ttd = $pdo->prepare($sql_update_ttd);
        $stmt_update_ttd->execute([$tanda_tangan_file, $user_id]);
    } else {
        header('Location: ajukan_izin_sakit.php?error=ttdformatinvalid');
        exit;
    }
}

// === INSERT KE DATABASE ===
try {
    $sql_insert = "INSERT INTO pengajuan_izin 
                   (user_id, perihal, tanggal_mulai, tanggal_selesai, lama_izin, alasan, 
                    file_surat, tanda_tangan_file, status, tanggal_pengajuan)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', CURDATE())";
    
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        $user_id,
        $perihal,
        $tanggal_mulai,
        $tanggal_selesai,
        $lama_izin,
        $alasan,
        $file_surat,
        $tanda_tangan_file
    ]);

    // Success! Redirect with success message
    header('Location: ajukan_izin_sakit.php?success=1');
    exit;

} catch (PDOException $e) {
    // Log error (in production, log to file instead of showing to user)
    error_log("Error inserting pengajuan izin: " . $e->getMessage());
    header('Location: ajukan_izin_sakit.php?error=gagalsimpan');
    exit;
}
?>
