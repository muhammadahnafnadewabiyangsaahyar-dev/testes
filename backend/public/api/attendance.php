<?php
/**
 * Attendance Processing API Endpoint
 * HELMEPPO - Backend Layer
 * API untuk memproses absensi masuk/keluar dengan validasi lengkap
 */

// Load backend bootstrap
require_once __DIR__ . '/../../config/app.php';

use App\Helper\AbsenHelper;
use App\Helper\SecurityHelper;

session_start();
header('Content-Type: application/json');

// Logging function for debugging
function log_absen($message, $data = []) {
    // DISABLED FOR TESTING - Permission issues with logging
    // $timestamp = date('Y-m-d H:i:s');
    // $log_message = "[$timestamp] $message";
    // if (!empty($data)) {
    //     $log_message .= " | DATA: " . json_encode($data);
    // }
    // error_log($log_message);
}

function send_json($arr) {
    // Log error messages for debugging
    if (isset($arr['status']) && $arr['status'] === 'error') {
        log_absen("❌ ABSEN ERROR", $arr);
    }
    echo json_encode($arr);
    exit();
}

// 1. Security Check: Login verification
if (!isset($_SESSION['user_id'])) {
    log_absen("❌ Not logged in");
    send_json(['status'=>'error','message'=>'Not logged in']);
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user'; // Get user role

log_absen("🚀 ABSEN PROCESS START", [
    'user_id' => $user_id,
    'user_role' => $user_role,
    'request_method' => $_SERVER['REQUEST_METHOD']
]);

// 2. Process only if POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_absen("❌ Invalid method", ['method' => $_SERVER['REQUEST_METHOD']]);
    send_json(['status'=>'error','message'=>'Invalid method']);
}

// 2.1. CSRF Token Validation
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    log_absen("❌ CSRF validation failed", [
        'post_token' => isset($_POST['csrf_token']) ? 'present' : 'missing',
        'session_token' => isset($_SESSION['csrf_token']) ? 'present' : 'missing'
    ]);
    send_json(['status'=>'error','message'=>'Invalid request token. Please refresh the page and try again.']);
}

// 2.5. Rate Limiting (Prevent spam attendance)
$current_time = time();
$rate_limit_key = 'absen_last_attempt_' . $user_id;
$rate_limit_count_key = 'absen_attempt_count_' . $user_id;
$rate_limit_window_key = 'absen_window_start_' . $user_id;

// Initialize window
if (!isset($_SESSION[$rate_limit_window_key])) {
    $_SESSION[$rate_limit_window_key] = $current_time;
    $_SESSION[$rate_limit_count_key] = 0;
    log_absen("🕐 Rate limit window initialized");
}

// Reset counter if 1 hour has passed
if ($current_time - $_SESSION[$rate_limit_window_key] > 3600) {
    $_SESSION[$rate_limit_window_key] = $current_time;
    $_SESSION[$rate_limit_count_key] = 0;
    log_absen("🕐 Rate limit counter reset (1 hour passed)");
}

// Check last attempt time (minimum 10 seconds interval)
if (isset($_SESSION[$rate_limit_key])) {
    $time_diff = $current_time - $_SESSION[$rate_limit_key];
    if ($time_diff < 10) {
        $remaining = 10 - $time_diff;
        log_absen("⏰ Rate limit: Too fast", [
            'time_diff' => $time_diff,
            'remaining' => $remaining
        ]);
        send_json(['status'=>'error','message'=>'Mohon tunggu ' . $remaining . ' detik sebelum mencoba lagi.']);
    }
}

// Check attempt count (max 10 per hour)
$_SESSION[$rate_limit_count_key] = ($_SESSION[$rate_limit_count_key] ?? 0) + 1;
log_absen("📊 Rate limit check", [
    'attempt_count' => $_SESSION[$rate_limit_count_key],
    'max_attempts' => 10
]);

if ($_SESSION[$rate_limit_count_key] > 10) {
    log_absen("❌ Rate limit exceeded", [
        'attempt_count' => $_SESSION[$rate_limit_count_key]
    ]);
    send_json(['status'=>'error','message'=>'Terlalu banyak percobaan absensi. Silakan coba lagi dalam 1 jam atau hubungi admin.']);
}

$_SESSION[$rate_limit_key] = $current_time;

// 3. Get POST data
$latitude_pengguna = $_POST['latitude'] ?? null;
$longitude_pengguna = $_POST['longitude'] ?? null;
$foto_base64 = $_POST['foto_absensi_base64'] ?? '';
$tipe_absen = $_POST['tipe_absen'] ?? ''; // 'masuk' or 'keluar'
$waktu_absen_sekarang_ts = strtotime(date('H:i:s')); 

log_absen("📥 POST DATA received", [
    'latitude' => $latitude_pengguna,
    'longitude' => $longitude_pengguna,
    'tipe_absen' => $tipe_absen,
    'has_foto' => !empty($foto_base64) ? 'YES' : 'NO',
    'foto_size' => strlen($foto_base64)
]);

// 4. Initial Input Validation
if ($latitude_pengguna === null || $longitude_pengguna === null || empty($tipe_absen) || !in_array($tipe_absen, ['masuk', 'keluar'])) {
    log_absen("❌ Data tidak lengkap", [
        'lat_null' => $latitude_pengguna === null,
        'lng_null' => $longitude_pengguna === null,
        'tipe_empty' => empty($tipe_absen),
        'tipe_invalid' => !in_array($tipe_absen, ['masuk', 'keluar'])
    ]);
    send_json(['status'=>'error','message'=>'Data tidak lengkap']);
}

if ($tipe_absen == 'masuk' && empty($foto_base64)) {
    log_absen("❌ Foto wajib untuk absen masuk");
    send_json(['status'=>'error','message'=>'Foto wajib untuk absen masuk']);
}

// 4.5. Validate base64 photo size (max 5MB)
if (!empty($foto_base64)) {
    $foto_size_bytes = strlen($foto_base64) * 0.75; // Rough estimate for base64
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($foto_size_bytes > $max_size) {
        send_json(['status'=>'error','message'=>'Ukuran foto terlalu besar (maksimal 5MB). Silakan coba lagi.']);
    }
}

// 4.7. Security Checks
$accuracy = $_POST['accuracy'] ?? null;
$provider = $_POST['provider'] ?? null;

if ($accuracy !== null || $provider !== null) {
    $mock_check = SecurityHelper::detectMockLocation($latitude_pengguna, $longitude_pengguna, $accuracy, $provider);
    
    log_absen("🔒 Mock Location Check", $mock_check);
    
    if ($mock_check['is_suspicious'] && $mock_check['risk_level'] === 'HIGH') {
        SecurityHelper::logSuspiciousActivity($user_id, 'possible_mock_location', [
            'latitude' => $latitude_pengguna,
            'longitude' => $longitude_pengguna,
            'accuracy' => $accuracy,
            'provider' => $provider,
            'flags' => $mock_check['flags']
        ]);
        
        log_absen("❌ Mock location detected - HIGH RISK", $mock_check);
        send_json([
            'status' => 'error',
            'message' => 'Lokasi terdeteksi mencurigakan. Pastikan Anda menggunakan GPS asli dan tidak menggunakan aplikasi mock location.'
        ]);
    }
}

// B. Detect Time Manipulation (if client sends timestamp)
$client_timestamp = $_POST['client_timestamp'] ?? time();

$time_check = SecurityHelper::detectTimeManipulation($client_timestamp);

log_absen("🔒 Time Manipulation Check", $time_check);

if ($time_check['is_manipulated']) {
    SecurityHelper::logSuspiciousActivity($user_id, 'time_manipulation_detected', [
        'server_time' => $time_check['server_time'],
        'client_time' => $time_check['client_time'],
        'difference' => $time_check['time_difference_seconds']
    ]);
    
    log_absen("⚠️ Time manipulation detected", $time_check);
    send_json([
        'status' => 'error',
        'message' => 'Waktu perangkat Anda tidak sinkron dengan server. Pastikan waktu perangkat sudah benar atau hubungi admin.'
    ]);
}

// C. Sanitize all input data
$latitude_pengguna = SecurityHelper::sanitizeSQL($latitude_pengguna);
$longitude_pengguna = SecurityHelper::sanitizeSQL($longitude_pengguna);
$tipe_absen = SecurityHelper::sanitizeSQL($tipe_absen);

log_absen("✅ Security checks passed");

// Use new validation system from AbsenHelper
$validation_result = AbsenHelper::validateAbsensiConditions($GLOBALS['pdo'], $user_id, $user_role, $latitude_pengguna, $longitude_pengguna, $tipe_absen);

if (!$validation_result['valid']) {
    send_json([
        'status' => 'error',
        'message' => $validation_result['message'],
        'errors' => $validation_result['errors']
    ]);
}

try {
    // Define today's date for photo filename
    $tanggal_hari_ini = date('Y-m-d');
    
    // 7. Process and Save Photo (Only for check-in)
    $nama_file_foto = null;
    if ($tipe_absen == 'masuk') {
        // Get user name for filename
        $stmt_user = $GLOBALS['pdo']->prepare("SELECT nama_lengkap FROM register WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $user_data = $stmt_user->fetch(\PDO::FETCH_ASSOC);
        $nama_user = $user_data ? $user_data['nama_lengkap'] : 'user_' . $user_id;
        $nama_user = strtolower(str_replace(' ', '_', $nama_user)); // sanitize name

        if (preg_match('/^data:image\/(\w+);base64,/', $foto_base64, $type)) {
            $data_gambar_base64 = substr($foto_base64, strpos($foto_base64, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
                 send_json(['status'=>'error','message'=>'Tipe foto tidak valid']);
            }
            $data_gambar_biner = base64_decode($data_gambar_base64);
            if ($data_gambar_biner === false) {
                 send_json(['status'=>'error','message'=>'Gagal decode foto']);
            }

            // Filename format: foto_absen_masuk_nama_user_date_time
            $waktu_sekarang = date('d_m_Y_H_i_s');
            $nama_file_foto = "foto_absen_masuk_{$nama_user}_{$waktu_sekarang}." . ($type == 'jpeg' ? 'jpg' : $type);
            
            // Folder structure: uploads/absensi/foto_masuk/[user_name]/
            $folder_masuk = "uploads/absensi/foto_masuk/{$nama_user}/";
            
            // Create folder if doesn't exist
            if (!is_dir($folder_masuk)) {
                if (!mkdir($folder_masuk, 0755, true)) {
                    log_absen("❌ Failed to create folder", ['folder' => $folder_masuk, 'user_id' => $user_id]);
                    send_json(['status'=>'error','message'=>'Gagal membuat folder penyimpanan foto']);
                }
                log_absen("✅ Folder created", ['folder' => $folder_masuk]);
            }
            
            $path_simpan_foto = $folder_masuk . $nama_file_foto;

            if (!file_put_contents($path_simpan_foto, $data_gambar_biner)) {
                log_absen("❌ Failed to save photo", ['path' => $path_simpan_foto, 'folder_exists' => is_dir($folder_masuk), 'writable' => is_writable($folder_masuk)]);
                send_json(['status'=>'error','message'=>'Gagal simpan foto']);
            }
            log_absen("✅ Photo saved successfully", ['filename' => $nama_file_foto, 'path' => $path_simpan_foto]);
        } else {
            send_json(['status'=>'error','message'=>'Format foto tidak valid']);
        }
    }

    // Use helper methods for attendance processing
    if ($tipe_absen == 'masuk') {
        // Check for duplicate check-in
        $absen_status = AbsenHelper::getAbsenStatusToday($GLOBALS['pdo'], $user_id);
        if ($absen_status['masuk']) {
            if ($nama_file_foto && file_exists($path_simpan_foto)) {
                unlink($path_simpan_foto); // Delete newly uploaded photo
            }
            send_json(['status'=>'error','message'=>'Anda sudah absen masuk hari ini. Silakan lakukan absen keluar.']);
        }

        // Get tardiness status
        $tardiness_status = AbsenHelper::getAdminTardinessStatus(date('H:i:s'), '09:00:00'); // Default shift time
        
        // INSERT attendance record
        $sql_insert = "INSERT INTO absensi 
                       (user_id, waktu_masuk, status_lokasi, latitude_absen_masuk, longitude_absen_masuk, foto_absen_masuk, tanggal_absensi, menit_terlambat, status_keterlambatan, potongan_tunjangan) 
                       VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $GLOBALS['pdo']->prepare($sql_insert);
        $stmt_insert->execute([
            $user_id, 'Valid', $latitude_pengguna, $longitude_pengguna, 
            $nama_file_foto, $tanggal_hari_ini, $tardiness_status['menit_terlambat'], $tardiness_status['status'], $tardiness_status['potongan']
        ]);
        log_absen("✅ ABSEN MASUK SUCCESS", ['absen_id' => $GLOBALS['pdo']->lastInsertId()]);
        send_json(['status'=>'success','next'=>'keluar']);
        
    } elseif ($tipe_absen == 'keluar') {
        // Check if check-in exists today
        $absen_status = AbsenHelper::getAbsenStatusToday($GLOBALS['pdo'], $user_id);
        if (!$absen_status['masuk']) {
            send_json(['status'=>'error','message'=>'Belum absen masuk hari ini. Silakan absen masuk terlebih dahulu.']);
        }

        // Update check-out time
        $sql_update = "UPDATE absensi SET waktu_keluar = NOW() WHERE user_id = ? AND DATE(tanggal_absensi) = ?";
        $stmt_update = $GLOBALS['pdo']->prepare($sql_update);
        $stmt_update->execute([$user_id, $tanggal_hari_ini]);
        
        log_absen("✅ ABSEN KELUAR SUCCESS");
        send_json(['status'=>'success','next'=>'done']);
    }

} catch (\PDOException $e) {
    log_absen("💥 PDO EXCEPTION", [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'user_id' => $user_id,
        'tipe_absen' => $tipe_absen ?? 'N/A'
    ]);
    
    // Return user-friendly message
    send_json([
        'status'=>'error',
        'message'=>'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi admin jika masalah berlanjut.',
        'error_code' => 'ERR_' . date('YmdHis')
    ]);
} catch (\Exception $e) {
    log_absen("💥 GENERAL EXCEPTION", [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'user_id' => $user_id,
        'tipe_absen' => $tipe_absen ?? 'N/A'
    ]);
    
    send_json([
        'status'=>'error',
        'message'=>'Terjadi kesalahan tak terduga. Silakan hubungi admin.',
        'error_code' => 'ERR_GEN_' . date('YmdHis')
    ]);
}
?>