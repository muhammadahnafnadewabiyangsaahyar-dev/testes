<?php
// Global error handler for fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => "[FATAL] $errstr in $errfile line $errline"]);
    exit;
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => "[SHUTDOWN] {$error['message']} in {$error['file']} line {$error['line']}"]);
        exit;
    }
});

// Debug: Start script
header('Content-Type: application/json');
echo json_encode(['debug' => 'proses_approve.php started']);

session_start();
header('Content-Type: application/json'); // Respons selalu JSON

require_once __DIR__ . '/vendor/autoload.php'; // Use require_once to prevent double inclusion

// --- Keamanan ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Akses ditolak (Bukan Admin).']);
    exit;
}

// --- Muat Dependensi & Koneksi ---
include 'connect.php'; 
require_once __DIR__ . '/email_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Validasi Input ---
if (!isset($_POST['pengajuan_id']) || !isset($_POST['action']) || !is_numeric($_POST['pengajuan_id'])) {
    echo json_encode(['success' => false, 'message' => 'Data permintaan tidak valid.']);
    exit;
}

$pengajuan_id = (int)$_POST['pengajuan_id'];
$action = $_POST['action'];
$new_status = '';
$email_subject = '';
$email_body_base = ''; // Pesan dasar untuk email
$wa_message_base = ''; // Pesan dasar untuk WA

// --- Tentukan Status Baru & Pesan Notifikasi ---
// PENTING: Status database dan email harus konsisten!
if ($action == 'approve') {
    $new_status = 'Diterima'; // Status di database
    $email_status = 'Disetujui'; // Status di email (user-friendly)
    $email_subject = "Pengajuan Izin Anda Disetujui (ID: #{$pengajuan_id})";
    $email_body_base = "Pengajuan izin Anda dengan ID #{$pengajuan_id} telah disetujui oleh admin.";
    $wa_message_base = "Info KAORI: Pengajuan izin Anda ID #{$pengajuan_id} telah disetujui.";
} elseif ($action == 'reject') {
    $new_status = 'Ditolak'; // Status di database
    $email_status = 'Ditolak'; // Status di email
    $email_subject = "Pengajuan Izin Anda Ditolak (ID: #{$pengajuan_id})";
    $email_body_base = "Mohon maaf, pengajuan izin Anda dengan ID #{$pengajuan_id} ditolak.";
    $wa_message_base = "Info KAORI: Mohon maaf, pengajuan izin Anda ID #{$pengajuan_id} ditolak.";
} else {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
    exit;
}

// === UPDATE DATABASE (PDO) ===
$pdo->beginTransaction();

try {
    $sql_update = "UPDATE pengajuan_izin SET status = ? WHERE id = ? AND status = 'Pending'"; // Update hanya jika masih pending
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$new_status, $pengajuan_id]);
    // Cek apakah ada baris yang terpengaruh (mencegah update ganda)
    if ($stmt_update->rowCount() == 0) {
         throw new Exception("Pengajuan tidak ditemukan atau sudah diproses.");
    }

    // === AMBIL INFO USER DAN PENGAJUAN UNTUK NOTIFIKASI ===
    $sql_detail = "SELECT 
                        p.id, p.perihal, p.tanggal_mulai, p.tanggal_selesai, p.lama_izin, p.alasan, p.status,
                        r.id as user_id, r.email, r.no_whatsapp, r.nama_lengkap, r.posisi
                   FROM pengajuan_izin p 
                   JOIN register r ON p.user_id = r.id 
                   WHERE p.id = ?";
    $stmt_detail = $pdo->prepare($sql_detail);
    $stmt_detail->execute([$pengajuan_id]);
    $detail_data = $stmt_detail->fetch(PDO::FETCH_ASSOC);
    
    if (!$detail_data) {
        throw new Exception("Gagal mengambil detail pengajuan untuk notifikasi.");
    }
    
    // Pisahkan data izin dan user
    $izin_data = [
        'id' => $detail_data['id'],
        'tanggal_mulai' => $detail_data['tanggal_mulai'],
        'tanggal_selesai' => $detail_data['tanggal_selesai'],
        'durasi_hari' => $detail_data['lama_izin'],
        'alasan' => $detail_data['alasan'],
        'jenis_izin' => $detail_data['perihal'],
        'status' => $new_status
    ];
    
    $user_data = [
        'id' => $detail_data['user_id'],
        'email' => $detail_data['email'],
        'no_whatsapp' => $detail_data['no_whatsapp'],
        'nama_lengkap' => $detail_data['nama_lengkap'],
        'posisi' => $detail_data['posisi']
    ];
    
    // Ambil data approver (admin yang melakukan approve)
    $approver_data = [
        'id' => $_SESSION['user_id'],
        'nama_lengkap' => $_SESSION['nama_lengkap'] ?? 'Admin'
    ];
    
    // === BUAT RECORD ABSENSI OTOMATIS UNTUK IZIN/SAKIT YANG DISETUJUI ===
    if ($action == 'approve') {
        $start = new DateTime($izin_data['tanggal_mulai']);
        $end = new DateTime($izin_data['tanggal_selesai']);
        $end->modify('+1 day'); // Include end date
        
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        
        foreach ($period as $date) {
            // Skip Sunday (day 7)
            if ($date->format('N') == 7) continue;
            
            $tanggal = $date->format('Y-m-d');
            $status = $izin_data['jenis_izin']; // 'Izin' atau 'Sakit'
            
            // Insert or update absensi
            $sql_absensi = "INSERT INTO absensi 
                           (user_id, tanggal_absensi, status_kehadiran, waktu_masuk, waktu_keluar, 
                            menit_terlambat, status_keterlambatan)
                           VALUES (?, ?, ?, NULL, NULL, 0, ?)
                           ON DUPLICATE KEY UPDATE 
                           status_kehadiran = VALUES(status_kehadiran),
                           status_keterlambatan = VALUES(status_keterlambatan)";
            $stmt_absensi = $pdo->prepare($sql_absensi);
            $stmt_absensi->execute([$user_data['id'], $tanggal, $status, $status]);
        }
        
        error_log("✅ Record absensi otomatis dibuat untuk {$izin_data['jenis_izin']} user {$user_data['nama_lengkap']}");
    }
    
    // === KIRIM NOTIFIKASI EMAIL MENGGUNAKAN HELPER FUNCTION ===
    // PENTING: Gunakan $email_status (user-friendly) bukan $new_status (database)
    $email_sent = sendEmailIzinStatus($izin_data, $user_data, $email_status, '', $approver_data);
    
    if ($email_sent) {
        error_log("✅ Email notifikasi status berhasil dikirim ke " . $user_data['email']);
    } else {
        error_log("⚠️ Email notifikasi status gagal dikirim (update status tetap berhasil)");
        // Tidak perlu throw exception, karena yang penting update status berhasil
    }

    // Jika semua berhasil sampai sini
    $pdo->commit(); // Konfirmasi semua perubahan database
    echo json_encode(['success' => true, 'message' => 'Status berhasil diubah dan notifikasi dijadwalkan.']);

} catch (Exception $e) {
    $pdo->rollBack(); // Batalkan perubahan database jika ada error
    error_log("Proses Approve Error [ID: {$pengajuan_id}]: " . $e->getMessage()); // Catat error di log server
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}
?>