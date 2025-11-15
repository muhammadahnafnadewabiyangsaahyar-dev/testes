<?php
session_start();
include 'connect.php';
include 'absen_helper.php';
include 'security_helper.php'; // Load security functions
header('Content-Type: application/json');

// ========================================================
// LOGGING FUNCTION for debugging
// ========================================================
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

// 1. Keamanan Awal: Cek Login
if (!isset($_SESSION['user_id'])) {
    log_absen("❌ Not logged in");
    send_json(['status'=>'error','message'=>'Not logged in']);
}
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user'; // Ambil role user

log_absen("🚀 ABSEN PROCESS START", [
    'user_id' => $user_id,
    'user_role' => $user_role,
    'request_method' => $_SERVER['REQUEST_METHOD']
]);

$home_url = 'mainpage.php'; // Unified page for both admin and user

// 2. Proses hanya jika metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_absen("❌ Invalid method", ['method' => $_SERVER['REQUEST_METHOD']]);
    send_json(['status'=>'error','message'=>'Invalid method']);
}

// 2.1. Validasi CSRF Token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    log_absen("❌ CSRF validation failed", [
        'post_token' => isset($_POST['csrf_token']) ? 'present' : 'missing',
        'session_token' => isset($_SESSION['csrf_token']) ? 'present' : 'missing'
    ]);
    send_json(['status'=>'error','message'=>'Invalid request token. Please refresh the page and try again.']);
}

// 2.5. Rate Limiting (Prevent spam absensi)
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

// Reset counter jika sudah lewat 1 jam
if ($current_time - $_SESSION[$rate_limit_window_key] > 3600) {
    $_SESSION[$rate_limit_window_key] = $current_time;
    $_SESSION[$rate_limit_count_key] = 0;
    log_absen("🕐 Rate limit counter reset (1 hour passed)");
}

// Check last attempt time (minimum 10 detik interval)
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

// Check attempt count (max 10 per jam)
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

// 3. Ambil Data dari POST
$latitude_pengguna = $_POST['latitude'] ?? null;
$longitude_pengguna = $_POST['longitude'] ?? null;
$foto_base64 = $_POST['foto_absensi_base64'] ?? '';
$tipe_absen = $_POST['tipe_absen'] ?? ''; // 'masuk' atau 'keluar'
$waktu_absen_sekarang_ts = strtotime(date('H:i:s')); 

log_absen("📥 POST DATA received", [
    'latitude' => $latitude_pengguna,
    'longitude' => $longitude_pengguna,
    'tipe_absen' => $tipe_absen,
    'has_foto' => !empty($foto_base64) ? 'YES' : 'NO',
    'foto_size' => strlen($foto_base64)
]);

// 4. Validasi Input Awal
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

// 4.5. Validasi ukuran foto base64 (max 5MB)
if (!empty($foto_base64)) {
    $foto_size_bytes = strlen($foto_base64) * 0.75; // Rough estimate for base64
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if ($foto_size_bytes > $max_size) {
        send_json(['status'=>'error','message'=>'Ukuran foto terlalu besar (maksimal 5MB). Silakan coba lagi.']);
    }
}

// ========================================================
// 4.7. SECURITY CHECKS
// ========================================================

// A. Detect Mock Location (if additional params provided from client)
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

/* ========================================================
// 4.6. VALIDASI JAM ABSEN (07:00 - 23:59) - SEMENTARA DIKOMENTARI UNTUK TESTING
// ADMIN: SKIP validasi jam (flexible 24/7)
// USER: Wajib mengikuti jam operacional
// ========================================================
if ($user_role !== 'admin') {
    // Hanya validasi untuk USER biasa
    $jam_sekarang = date('H:i:s');
    $jam_minimal = '07:00:00';
    $jam_maksimal = '23:59:59';

    // Convert to timestamp for more reliable comparison
    $jam_sekarang_ts = strtotime($jam_sekarang);
    $jam_minimal_ts = strtotime($jam_minimal);
    $jam_maksimal_ts = strtotime($jam_maksimal);

    $is_within_hours = ($jam_sekarang_ts >= $jam_minimal_ts && $jam_sekarang_ts <= $jam_maksimal_ts);

    log_absen("⏰ Time validation (USER MODE)", [
        'current_time' => $jam_sekarang,
        'current_time_ts' => $jam_sekarang_ts,
        'min_time' => $jam_minimal,
        'min_time_ts' => $jam_minimal_ts,
        'max_time' => $jam_maksimal,
        'max_time_ts' => $jam_maksimal_ts,
        'is_valid' => $is_within_hours,
        'user_id' => $user_id,
        'user_role' => $user_role
    ]);

    if (!$is_within_hours) {
        log_absen("❌ Time validation FAILED - REJECTED", [
            'current_time' => $jam_sekarang,
            'user_id' => $user_id,
            'user_role' => $user_role,
            'reason' => 'Outside operational hours (07:00-23:59)'
        ]);
        
        send_json([
            'status' => 'error',
            'message' => 'Absensi hanya dapat dilakukan antara jam 07:00 - 23:59. Waktu sekarang: ' . date('H:i') . '. Silakan coba lagi saat jam operacional.'
        ]);
    }

    log_absen("✅ Time validation PASSED (USER)", ['current_time' => $jam_sekarang]);
} else {
    // Admin: SKIP time validation (flexible 24/7)
    log_absen("✅ Time validation SKIPPED (ADMIN - Flexible Hours)", [
        'user_role' => $user_role,
        'current_time' => date('H:i:s'),
        'note' => 'Admin can work anytime'
    ]);
}
*/
// ===== SEMENTARA DIKOMENTARI UNTUK TESTING - VALIDASI WAKTU DIMATIKAN =====
// Tujuan: Testing apakah data absen bisa masuk database dan tampil di rekap
log_absen("⚠️ TIME VALIDATION SKIPPED FOR TESTING", [
    'user_role' => $user_role,
    'current_time' => date('H:i:s'),
    'note' => 'Time validation disabled for testing purposes'
]);
// ========================================================


try {
    // ========================================================
    // LOGIKA KHUSUS ADMIN vs USER
    // ========================================================
    $is_admin = in_array($user_role, ['admin', 'superadmin']);
    $status_lokasi = 'Valid'; // Default
    $shift_terpilih = null;
    $jam_masuk_cabang_ini_str = null;
    
    log_absen("👤 USER ROLE CHECK", [
        'is_admin' => $is_admin ? 'YES' : 'NO',
        'user_role' => $user_role
    ]);
    
    if ($is_admin) {
        // ========================================================
        // ADMIN: Tidak perlu validasi lokasi dan shift
        // Admin bisa absen dari mana saja (remote), tidak terikat shift
        // Admin menggunakan cabang "Kaori HQ" dengan jam kerja flexible (00:00-23:59)
        // ========================================================
        log_absen("👑 ADMIN MODE ACTIVATED - Skip location validation");
        
        // Ambil cabang "Kaori HQ" khusus untuk admin/remote workers
        // Cabang ini memiliki radius sangat besar (auto-accept) dan jam flexible
        $sql_admin_branch = "SELECT id, nama_cabang, nama_shift, latitude, longitude, radius_meter, jam_masuk, jam_keluar 
                            FROM cabang 
                            WHERE nama_cabang = 'Kaori HQ' OR nama_shift = 'Flexible' 
                            LIMIT 1";
        $admin_branch = $pdo->query($sql_admin_branch)->fetch();
        
        // Fallback: jika cabang Kaori HQ belum dibuat, gunakan cabang manapun (backward compatibility)
        if (!$admin_branch) {
            log_absen("⚠️ Kaori HQ branch not found - using fallback");
            $admin_branch = $pdo->query("SELECT id, nama_cabang, nama_shift, latitude, longitude, radius_meter, jam_masuk, jam_keluar FROM cabang LIMIT 1")->fetch();
        }
        
        if (!$admin_branch) {
            log_absen("❌ No branch data found for admin");
            send_json(['status'=>'error','message'=>'Data cabang tidak ditemukan. Hubungi admin untuk setup cabang "Kaori HQ".']);
        }
        
        log_absen("✅ Admin branch assigned: " . $admin_branch['nama_cabang'], [
            'branch_id' => $admin_branch['id'],
            'branch_name' => $admin_branch['nama_cabang'],
            'shift_name' => $admin_branch['nama_shift'],
            'jam_masuk' => $admin_branch['jam_masuk'],
            'jam_keluar' => $admin_branch['jam_keluar'],
            'radius' => $admin_branch['radius_meter']
        ]);
        
        $shift_terpilih = $admin_branch;
        $status_lokasi = 'Valid';
        $jam_masuk_cabang_ini_str = $admin_branch['jam_masuk']; // Referensi saja (admin flexible)
        
        log_absen("✅ Admin mode: Location validation bypassed", [
            'status_lokasi' => $status_lokasi,
            'shift_id' => $shift_terpilih['id'],
            'note' => 'Admin can work remotely from anywhere'
        ]);
        
    } else {
        // ========================================================
        // USER: Validasi lokasi dan shift (LOGIKA EXISTING)
        // ========================================================
        log_absen("👤 USER MODE - Location validation required");
        
        // --- 5. Ambil Data SEMUA Cabang (TERMASUK JAM SHIFT) ---
        $sql_all_branches = "SELECT id, nama_cabang, nama_shift, latitude, longitude, radius_meter, jam_masuk, jam_keluar FROM cabang";
        $all_branches = $pdo->query($sql_all_branches)->fetchAll();

        if (empty($all_branches)) {
            log_absen("❌ No branches found in database");
            send_json(['status'=>'error','message'=>'Data cabang tidak ada']);
        }
        
        log_absen("📍 Branches loaded", ['total_branches' => count($all_branches)]);

        // --- BLOK 1: Cari SEMUA LOKASI VALID ---
        $cabang_valid_berdasarkan_lokasi = []; 
        foreach ($all_branches as $cabang) {
            $jarak = haversineGreatCircleDistance($latitude_pengguna, $longitude_pengguna, $cabang['latitude'], $cabang['longitude']);
            if ($jarak !== false && $jarak <= (int)$cabang['radius_meter']) {
                $cabang_valid_berdasarkan_lokasi[] = $cabang;
            }
        }

        if (empty($cabang_valid_berdasarkan_lokasi)) {
            send_json(['status'=>'error','message'=>'Lokasi tidak sah. Anda harus berada di area cabang untuk melakukan absensi.']);
        }
        
        // --- BLOK 2: Cari SHIFT TERBAIK dari Lokasi Valid ---
        $selisih_terkecil = PHP_INT_MAX; 
        $waktu_absen_key = ($tipe_absen == 'masuk') ? 'jam_masuk' : 'jam_keluar';

        foreach ($cabang_valid_berdasarkan_lokasi as $cabang) {
            $jam_shift_ts = strtotime($cabang[$waktu_absen_key]);
            $selisih = abs($waktu_absen_sekarang_ts - $jam_shift_ts);

            if ($selisih < $selisih_terkecil) {
                $selisih_terkecil = $selisih;
                $shift_terpilih = $cabang;
            }
        }
        
        if ($shift_terpilih === null) {
            // Fallback jika terjadi error, ambil yang pertama
            $shift_terpilih = $cabang_valid_berdasarkan_lokasi[0];
        }

        $status_lokasi = 'Valid';
        $jam_masuk_cabang_ini_str = $shift_terpilih['jam_masuk'];
        
        log_absen("✅ User location validated", [
            'status_lokasi' => $status_lokasi,
            'shift_id' => $shift_terpilih['id'],
            'shift_name' => $shift_terpilih['nama_shift'] ?? 'N/A',
            'branch' => $shift_terpilih['nama_cabang'] ?? 'N/A'
        ]);
    }
    // ========================================================
    
    // FIX: Define tanggal_hari_ini di awal (dipakai untuk nama file foto)
    $tanggal_hari_ini = date('Y-m-d');
    
    // --- 7. Proses dan Simpan Foto (Hanya saat absen masuk) ---
    $nama_file_foto = null;
    if ($tipe_absen == 'masuk') {
        // Dapatkan nama user untuk nama file
        $stmt_user = $pdo->prepare("SELECT nama_lengkap FROM register WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
        $nama_user = $user_data ? $user_data['nama_lengkap'] : 'user_' . $user_id;
        $nama_user = strtolower(str_replace(' ', '_', $nama_user)); // sanitasi nama

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

            // Format nama file: foto_absen_masuk_nama_user_tanggal_bulan_tahun_jam_menit_detik
            $waktu_sekarang = date('d_m_Y_H_i_s');
            $nama_file_foto = "foto_absen_masuk_{$nama_user}_{$waktu_sekarang}." . ($type == 'jpeg' ? 'jpg' : $type);
            
            // Struktur folder: uploads/absensi/foto_masuk/[nama user]/
            $folder_masuk = "uploads/absensi/foto_masuk/{$nama_user}/";
            
            // Buat folder jika belum ada dengan error handling
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

    // --- 8. Simpan Catatan Absensi ke Database ---
    // ($tanggal_hari_ini sudah didefinisikan di atas)

    if ($tipe_absen == 'masuk') {
        
        // ========================================================
        // FIX: Hitung Keterlambatan dengan Logika Berbeda untuk ADMIN vs USER
        // ========================================================
        $menit_terlambat = 0;
        $status_keterlambatan = 'tepat waktu';
        $potongan_tunjangan = 'tidak ada'; // Field baru untuk tracking potongan
        
        if ($is_admin) {
            // ========================================================
            // ADMIN/SUPERADMIN: Tidak ada keterlambatan, tidak ada potongan
            // Admin/superadmin tidak terikat shift, jadi selalu "tepat waktu"
            // ========================================================
            $menit_terlambat = 0;
            $status_keterlambatan = 'tepat waktu';
            $potongan_tunjangan = 'tidak ada';
            
        } else {
            // ========================================================
            // USER: Hitung keterlambatan dengan 3 Level Konsekuensi + Validasi Range
            // ========================================================
            $jam_masuk_ts = strtotime($jam_masuk_cabang_ini_str);
            $selisih_detik = $waktu_absen_sekarang_ts - $jam_masuk_ts;
            $selisih_menit = floor($selisih_detik / 60);
            
            // PERBAIKAN: Validasi range waktu absen yang masuk akal
            // Toleransi: Max 2 jam sebelum shift dan max 12 jam setelah shift
            $toleransi_awal_detik = -2 * 60 * 60; // 2 jam sebelum shift masih OK
            $toleransi_akhir_detik = 12 * 60 * 60; // 12 jam setelah shift masih OK
            
            if ($selisih_detik < $toleransi_awal_detik) {
                // User absen TERLALU AWAL (lebih dari 2 jam sebelum shift)
                // Ini dianggap INVALID - mungkin absen di hari sebelumnya
                $menit_terlambat = abs($selisih_menit);
                $status_keterlambatan = 'di luar shift';
                $potongan_tunjangan = 'tidak ada'; // Tidak ada potongan, tapi perlu review admin
                
            } elseif ($selisih_detik > $toleransi_akhir_detik) {
                // User absen TERLALU TERLAMBAT (lebih dari 12 jam setelah shift)
                // Ini dianggap INVALID atau alpha
                $menit_terlambat = ceil($selisih_detik / 60);
                $status_keterlambatan = 'di luar shift';
                $potongan_tunjangan = 'tidak ada'; // Tidak ada potongan, tapi perlu review admin
                
            } elseif ($selisih_detik > 0) { 
                // User terlambat (dalam range yang wajar)
                $menit_terlambat = ceil($selisih_detik / 60);
                
                if ($menit_terlambat > 0 && $menit_terlambat < 20) {
                    // Level 1: Terlambat 1-19 menit
                    $status_keterlambatan = 'terlambat kurang dari 20 menit';
                    $potongan_tunjangan = 'tunjangan makan';
                    
                } elseif ($menit_terlambat >= 20) {
                    // Level 2: Terlambat 20+ menit
                    $status_keterlambatan = 'terlambat lebih dari 20 menit';
                    $potongan_tunjangan = 'tunjangan makan dan transport';
                }
            } else {
                // Tepat waktu atau datang lebih awal (tapi dalam range toleransi 2 jam)
                $menit_terlambat = 0;
                $status_keterlambatan = 'tepat waktu';
                $potongan_tunjangan = 'tidak ada';
            }
        }
        // ========================================================
        
        // FIX: RE-ENABLE Cek duplikat absen masuk (CRITICAL!)
        $sql_cek = "SELECT id, waktu_keluar FROM absensi 
                    WHERE user_id = ? AND DATE(tanggal_absensi) = ? 
                    ORDER BY id DESC LIMIT 1";
        $stmt_cek = $pdo->prepare($sql_cek);
        $stmt_cek->execute([$user_id, $tanggal_hari_ini]);
        $existing_absen = $stmt_cek->fetch();
        
        if ($existing_absen) {
            // Sudah ada record absen hari ini
            if (empty($existing_absen['waktu_keluar'])) {
                // Masih ada absen masuk yang belum keluar
                if ($nama_file_foto && file_exists($path_simpan_foto)) {
                    unlink($path_simpan_foto); // Hapus foto yang baru diupload
                }
                send_json(['status'=>'error','message'=>'Anda sudah absen masuk hari ini. Silakan lakukan absen keluar.']);
            } else {
                // Sudah absen masuk DAN keluar (absensi lengkap)
                if ($nama_file_foto && file_exists($path_simpan_foto)) {
                    unlink($path_simpan_foto);
                }
                send_json(['status'=>'error','message'=>'Absensi hari ini sudah selesai (masuk & keluar).']);
            }
        }

        // INSERT data absen masuk 
        // FIX: Gunakan foto_absen_masuk dan latitude/longitude_absen_masuk
        $sql_insert = "INSERT INTO absensi 
                       (user_id, waktu_masuk, status_lokasi, latitude_absen_masuk, longitude_absen_masuk, foto_absen_masuk, tanggal_absensi, menit_terlambat, status_keterlambatan, potongan_tunjangan) 
                       VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            $user_id, $status_lokasi, $latitude_pengguna, $longitude_pengguna, 
            $nama_file_foto, $tanggal_hari_ini, $menit_terlambat, $status_keterlambatan, $potongan_tunjangan
        ]);
        log_absen("✅ ABSEN MASUK SUCCESS", ['absen_id' => $pdo->lastInsertId()]);
        send_json(['status'=>'success','next'=>'keluar']);
    } elseif ($tipe_absen == 'keluar') {
        
        // ========================================================
        // FIX: ALLOW MULTIPLE ABSEN KELUAR (UPDATE TERAKHIR)
        // User bisa absen keluar berulang kali untuk update waktu keluar
        // Ini mencegah user dihitung tidak hadir jika tidak sengaja absen keluar terlalu cepat
        // ========================================================
        
        // Cek apakah sudah absen masuk hari ini (TIDAK PEDULI sudah absen keluar atau belum)
        $sql_cek_keluar = "SELECT id, waktu_keluar FROM absensi 
                           WHERE user_id = ? AND tanggal_absensi = ? 
                           ORDER BY id DESC LIMIT 1";
        $stmt_cek_keluar = $pdo->prepare($sql_cek_keluar);
        $stmt_cek_keluar->execute([$user_id, $tanggal_hari_ini]);
        $data_absen_masuk = $stmt_cek_keluar->fetch();

        if (!$data_absen_masuk) {
            send_json(['status'=>'error','message'=>'Belum absen masuk hari ini. Silakan absen masuk terlebih dahulu.']);
        }
        
        $absen_id_yang_diupdate = $data_absen_masuk['id'];
        $sudah_absen_keluar_sebelumnya = !empty($data_absen_masuk['waktu_keluar']);

        // --- Ambil data shift user hari ini ---
        $sql_shift = "SELECT nama_cabang, nama_shift, jam_keluar FROM cabang WHERE id = ?";
        $stmt_shift = $pdo->prepare($sql_shift);
        $stmt_shift->execute([$shift_terpilih['id']]);
        $shift = $stmt_shift->fetch();
        $jam_keluar_shift = $shift ? $shift['jam_keluar'] : null;

        log_absen("📍 Check overtime", [
            'shift_id' => $shift_terpilih['id'],
            'branch' => $shift['nama_cabang'] ?? 'N/A',
            'shift_name' => $shift['nama_shift'] ?? 'N/A',
            'scheduled_end' => $jam_keluar_shift,
            'actual_time' => date('H:i:s')
        ]);

        $waktu_keluar_sekarang = date('H:i:s');
        $is_overwork = false;
        if ($jam_keluar_shift && $waktu_keluar_sekarang > $jam_keluar_shift) {
            $is_overwork = true;
            log_absen("⚠️ OVERTIME DETECTED", [
                'scheduled_end' => $jam_keluar_shift,
                'actual_end' => $waktu_keluar_sekarang
            ]);
        }

        // --- Simpan foto absen keluar jika ada ---
        $nama_file_foto_keluar = null;
        if (!empty($foto_base64)) {
            // Dapatkan nama user untuk nama file
            $stmt_user = $pdo->prepare("SELECT nama_lengkap FROM register WHERE id = ?");
            $stmt_user->execute([$user_id]);
            $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $nama_user = $user_data ? $user_data['nama_lengkap'] : 'user_' . $user_id;
            $nama_user = strtolower(str_replace(' ', '_', $nama_user)); // sanitasi nama

            if (preg_match('/^data:image\/(\w+);base64,/', $foto_base64, $type)) {
                $data_gambar_base64 = substr($foto_base64, strpos($foto_base64, ',') + 1);
                $type = strtolower($type[1]);
                if (!in_array($type, ['jpg', 'jpeg', 'png'])) {
                    send_json(['status'=>'error','message'=>'Tipe foto keluar tidak valid']);
                }
                $data_gambar_biner = base64_decode($data_gambar_base64);
                if ($data_gambar_biner === false) {
                    send_json(['status'=>'error','message'=>'Gagal decode foto keluar']);
                }
                // Format nama file: foto_absen_keluar_nama_user_tanggal_bulan_tahun_jam_menit_detik
                $waktu_sekarang = date('d_m_Y_H_i_s');
                $nama_file_foto_keluar = "foto_absen_keluar_{$nama_user}_{$waktu_sekarang}." . ($type == 'jpeg' ? 'jpg' : $type);
                
                // Struktur folder: uploads/absensi/foto_keluar/[nama user]/
                $folder_keluar = "uploads/absensi/foto_keluar/{$nama_user}/";
                
                // Buat folder jika belum ada dengan error handling
                if (!is_dir($folder_keluar)) {
                    if (!mkdir($folder_keluar, 0755, true)) {
                        log_absen("❌ Failed to create folder keluar", ['folder' => $folder_keluar, 'user_id' => $user_id]);
                        send_json(['status'=>'error','message'=>'Gagal membuat folder penyimpanan foto keluar']);
                    }
                    log_absen("✅ Folder keluar created", ['folder' => $folder_keluar]);
                }
                
                $path_simpan_foto_keluar = $folder_keluar . $nama_file_foto_keluar;
                if (!file_put_contents($path_simpan_foto_keluar, $data_gambar_biner)) {
                    log_absen("❌ Failed to save photo keluar", ['path' => $path_simpan_foto_keluar, 'folder_exists' => is_dir($folder_keluar), 'writable' => is_writable($folder_keluar)]);
                    send_json(['status'=>'error','message'=>'Gagal simpan foto keluar']);
                }
                
                // FIX: Update kolom foto_absen_keluar, latitude_absen_keluar, longitude_absen_keluar
                $sql_update_foto = "UPDATE absensi
                                   SET foto_absen_keluar = ?,
                                       latitude_absen_keluar = ?,
                                       longitude_absen_keluar = ?
                                   WHERE id = ?";
                $stmt_update_foto = $pdo->prepare($sql_update_foto);
                $stmt_update_foto->execute([
                    $nama_file_foto_keluar,
                    $latitude_pengguna,
                    $longitude_pengguna,
                    $absen_id_yang_diupdate
                ]);
                log_absen("✅ Foto & lokasi keluar saved", [
                    'foto' => $nama_file_foto_keluar,
                    'lat' => $latitude_pengguna,
                    'lng' => $longitude_pengguna
                ]);
            }
        }

        // UPDATE waktu_keluar dan hitung durasi kerja + overwork
        // Get shift information from shift_assignments
        $sql_shift = "SELECT c.jam_masuk, c.jam_keluar, c.id as cabang_id
                      FROM shift_assignments sa
                      JOIN cabang c ON sa.cabang_id = c.id
                      WHERE sa.user_id = ? AND sa.tanggal_shift = ?
                      AND sa.status_konfirmasi = 'confirmed'
                      LIMIT 1";
        $stmt_shift = $pdo->prepare($sql_shift);
        $stmt_shift->execute([$user_id, $tanggal_hari_ini]);
        $shift_info = $stmt_shift->fetch(PDO::FETCH_ASSOC);
        
        // Jika tidak ada shift assignment yang confirmed, gunakan data dari absen masuk atau cabang default
        if (!$shift_info) {
            // Gunakan data dari absen masuk yang sudah ada
            $sql_absen_masuk = "SELECT * FROM absensi WHERE id = ?";
            $stmt_absen_masuk = $pdo->prepare($sql_absen_masuk);
            $stmt_absen_masuk->execute([$absen_id_yang_diupdate]);
            $absen_data = $stmt_absen_masuk->fetch(PDO::FETCH_ASSOC);
            
            if ($absen_data && $absen_data['jam_masuk_shift']) {
                $shift_info = [
                    'jam_masuk' => $absen_data['jam_masuk_shift'],
                    'jam_keluar' => $absen_data['jam_keluar_shift'],
                    'cabang_id' => $absen_data['cabang_id']
                ];
            } else {
                // Fallback: ambil cabang pertama yang aktif
                $sql_default = "SELECT c.jam_masuk, c.jam_keluar, c.id as cabang_id
                               FROM cabang c
                               WHERE c.is_active = 1
                               LIMIT 1";
                $stmt_default = $pdo->prepare($sql_default);
                $stmt_default->execute();
                $shift_info = $stmt_default->fetch(PDO::FETCH_ASSOC);
            }
        }
        
        // Prepare shift data for UPDATE
        $jam_masuk_shift = $shift_info ? $shift_info['jam_masuk'] : null;
        $jam_keluar_shift = $shift_info ? $shift_info['jam_keluar'] : null;
        $cabang_id = $shift_info ? $shift_info['cabang_id'] : null;
        
        log_absen("📊 Shift Info", [
            'jam_masuk_shift' => $jam_masuk_shift,
            'jam_keluar_shift' => $jam_keluar_shift,
            'cabang_id' => $cabang_id
        ]);

        // IMPORTANT: Hanya update status_lembur jika belum dikonfirmasi (masih Pending/Not Applicable)
        // Jangan overwrite jika sudah Approved/Rejected oleh admin
        $sql_check_status = "SELECT status_lembur FROM absensi WHERE id = ?";
        $stmt_check = $pdo->prepare($sql_check_status);
        $stmt_check->execute([$absen_id_yang_diupdate]);
        $current_status = $stmt_check->fetchColumn();
        
        // Tentukan status lembur baru
        $status_lembur_baru = $is_overwork ? 'Pending' : 'Not Applicable';
        
        // Hanya update status_lembur jika:
        // 1. Status saat ini adalah 'Pending' atau 'Not Applicable' (belum dikonfirmasi/diproses admin)
        // 2. Status bukan 'Approved' atau 'Rejected' (sudah diproses admin, jangan ubah!)
        if (in_array($current_status, ['Pending', 'Not Applicable'])) {
            $sql_update = "UPDATE absensi SET 
                waktu_keluar = NOW(), 
                status_lembur = ?,
                cabang_id = ?,
                jam_masuk_shift = ?,
                jam_keluar_shift = ?
                WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                $status_lembur_baru, 
                $cabang_id,
                $jam_masuk_shift,
                $jam_keluar_shift,
                $absen_id_yang_diupdate
            ]);
            
            log_absen("✅ Status lembur updated", [
                'old_status' => $current_status,
                'new_status' => $status_lembur_baru,
                'reason' => 'Status belum dikonfirmasi, safe to update'
            ]);
        } else {
            // Status sudah Approved/Rejected, hanya update waktu keluar saja
            $sql_update = "UPDATE absensi SET 
                waktu_keluar = NOW(),
                cabang_id = ?,
                jam_masuk_shift = ?,
                jam_keluar_shift = ?
                WHERE id = ?";
            $stmt_update = $pdo->prepare($sql_update);
            $stmt_update->execute([
                $cabang_id,
                $jam_masuk_shift,
                $jam_keluar_shift,
                $absen_id_yang_diupdate
            ]);
            
            log_absen("⚠️ Status lembur NOT updated", [
                'current_status' => $current_status,
                'would_be' => $status_lembur_baru,
                'reason' => 'Status sudah diproses admin, tidak boleh diubah'
            ]);
        }
        
        // Pesan yang berbeda jika ini update (absen keluar berulang)
        if ($sudah_absen_keluar_sebelumnya) {
            if ($is_overwork) {
                send_json([
                    'status'=>'success',
                    'next'=>'konfirmasi_lembur',
                    'absen_id'=>$absen_id_yang_diupdate,
                    'message'=>'✓ Waktu keluar berhasil diperbarui (overwork terdeteksi)'
                ]);
            } else {
                send_json([
                    'status'=>'success',
                    'next'=>'done',
                    'absen_id'=>$absen_id_yang_diupdate,
                    'message'=>'✓ Waktu keluar berhasil diperbarui'
                ]);
            }
        } else {
            // Absen keluar pertama kali (normal flow)
            if ($is_overwork) {
                send_json(['status'=>'success','next'=>'konfirmasi_lembur','absen_id'=>$absen_id_yang_diupdate]);
            } else {
                send_json(['status'=>'success','next'=>'done','absen_id'=>$absen_id_yang_diupdate]);
            }
        }
    }

} catch (PDOException $e) {
    // Log detail error untuk debugging
    log_absen("💥 PDO EXCEPTION", [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'user_id' => $user_id,
        'tipe_absen' => $tipe_absen ?? 'N/A'
    ]);
    
    $error_log_file = 'logs/absensi_errors.log';
    // if (!is_dir('logs')) mkdir('logs', 0755, true); // DISABLED FOR TESTING - Permission issues
    
    // $log_message = date('Y-m-d H:i:s') . " | User ID: $user_id | Tipe: " . ($tipe_absen ?? 'N/A') . " | Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine() . "\n";
    // file_put_contents($error_log_file, $log_message, FILE_APPEND); // DISABLED FOR TESTING - Permission issues
    
    // Simpan ke database log (jika table ada)
    try {
        $stmt_log = $pdo->prepare("INSERT INTO absensi_error_log (user_id, error_type, error_message, error_details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([
            $user_id,
            'DB_ERROR',
            'Database error during absensi',
            $e->getMessage(),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $log_error) {
        log_absen("⚠️ Failed to log error to database", ['log_error' => $log_error->getMessage()]);
    }
    
    // Return user-friendly message (jangan expose detail error)
    send_json([
        'status'=>'error',
        'message'=>'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi admin jika masalah berlanjut.',
        'error_code' => 'ERR_' . date('YmdHis')
    ]);
} catch (Exception $e) {
    // Catch all other exceptions
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

function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371000) {
    $latFrom = deg2rad($latitudeFrom);
    $lonFrom = deg2rad($longitudeFrom);
    $latTo = deg2rad($latitudeTo);
    $lonTo = deg2rad($longitudeTo);
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    return $angle * $earthRadius;
}
?>