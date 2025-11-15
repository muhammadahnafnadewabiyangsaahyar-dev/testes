<?php
session_start();

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. PENJAGA GERBANG: Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=notloggedin');
    exit;
}
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';
$nama_pengguna = $_SESSION['nama_lengkap'] ?? $_SESSION['username']; // Ambil nama untuk sapaan

// 2. Muat Koneksi
include 'connect.php';
include 'absen_helper.php';

// 3. VALIDASI AWAL - CEK SEMUA SYARAT SEBELUM TAMPIL HALAMAN
$validation_result = validateAbsensiConditions($pdo, $user_id, $user_role);
$can_access_camera = $validation_result['valid'];

// Jika ada error validasi, tampilkan pesan dan stop proses
if (!$can_access_camera) {
    $error_messages = $validation_result['errors'];
    $main_error = implode(' ', $error_messages); // Gabungkan semua pesan error
}

// Cek status absen hari ini (hanya jika validasi berhasil)
if ($can_access_camera) {
    $absen_status = getAbsenStatusToday($pdo, $user_id);
    $tipe_default = 'masuk';
    $label_default = 'Absen Masuk';
    if ($absen_status['masuk'] && !$absen_status['keluar']) {
        $tipe_default = 'keluar';
        $label_default = 'Absen Keluar';
    } elseif ($absen_status['masuk'] && $absen_status['keluar']) {
        $tipe_default = 'done';
        $label_default = 'Absensi Selesai';
    }
} else {
    $absen_status = ['masuk' => false, 'keluar' => false];
    $tipe_default = 'masuk';
    $label_default = 'Absen Masuk';
}

$home_url = 'mainpage.php'; // Unified page for both admin and user
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Harian</title>
    <link rel="stylesheet" href="assets/css/style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <div class="headercontainer">
        <?php include 'navbar.php'; ?>
    </div>
    <div class="main-title">Absensi Harian</div>
    <div class="subtitle-container">
        <p class="subtitle">Selamat Datang, <?php echo htmlspecialchars($nama_pengguna); ?></p>
    </div>

    <div class="content-container" style="text-align: center;">
        
        <?php if (!$can_access_camera): ?>
            <!-- TAMPILKAN PESAN ERROR VALIDASI -->
            <div style="background: #FFEBEE; border: 1px solid #F44336; border-radius: 8px; padding: 20px; margin: 20px auto; max-width: 600px; text-align: center;">
                <h3 style="margin: 0 0 16px 0; color: #D32F2F; font-size: 18px;">
                    <i class="fas fa-exclamation-triangle"></i> Tidak Dapat Melakukan Absensi
                </h3>
                <p style="margin: 0; font-size: 16px; color: #D32F2F; font-weight: bold;">
                    <?php echo htmlspecialchars($main_error); ?>
                </p>
                <div style="margin-top: 16px;">
                    <a href="<?php echo $home_url; ?>" class="btn" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px;">
                        Kembali ke Beranda
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- TAMPILKAN INTERFACE ABSENSI NORMAL -->
            <p>Siap untuk absensi. Sistem akan memverifikasi lokasi Anda dan membuka kamera untuk foto.</p>

            <!-- INFO BOX: Fitur Absen Keluar Berulang -->
            <div style="background: #E8F4FD; border: 1px solid #90CAF9; border-radius: 8px; padding: 12px 16px; margin: 16px auto; max-width: 600px; text-align: left;">
                <p style="margin: 0; font-size: 14px; color: #1565C0;">
                    <strong>ℹ️ Info Penting:</strong> Jika Anda tidak sengaja melakukan absen keluar terlalu awal,
                    <strong>Anda dapat absen keluar lagi</strong> untuk memperbarui waktu keluar Anda.
                    Waktu keluar terakhir yang akan dicatat dalam sistem.
                </p>
            </div>

            <!-- VALIDATION STATUS BOX -->
            <div id="validation-status-box" style="background: #FFF3E0; border: 1px solid #FFB74D; border-radius: 8px; padding: 12px 16px; margin: 16px auto; max-width: 600px; text-align: left; display: block;">
                <h4 style="margin: 0 0 8px 0; color: #E65100; font-size: 14px;">
                    <i class="fas fa-check-circle"></i> Status Validasi
                </h4>
                <p id="validation-status-text" style="margin: 0; font-size: 13px; color: #E65100;">
                    ✓ Semua validasi berhasil. Silakan izinkan akses lokasi dan kamera.
                </p>
            </div>

            <!-- LOCATION STATUS BOX -->
            <div id="location-status-box" style="background: #FFF3E0; border: 1px solid #FFB74D; border-radius: 8px; padding: 12px 16px; margin: 16px auto; max-width: 600px; text-align: left; display: none;">
                <h4 style="margin: 0 0 8px 0; color: #E65100; font-size: 14px;">
                    <i class="fas fa-map-marker-alt"></i> Status Lokasi
                </h4>
                <p id="location-status-text" style="margin: 0; font-size: 13px; color: #E65100;">
                    Memverifikasi lokasi Anda...
                </p>
            </div>

<video id="kamera-preview" autoplay playsinline muted style="border: 1px solid #ccc;"></video>
<canvas id="kamera-canvas" width="640" height="480" style="display: none; border: 1px solid #ccc;"></canvas>

<p id="status-lokasi" class="status-message" style="color: orange;">Meminta izin akses lokasi...</p>

        <form id="form-absensi" method="POST" action="proses_absensi.php">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="latitude" id="input-latitude">
            <input type="hidden" name="longitude" id="input-longitude">
            <input type="hidden" name="foto_absensi_base64" id="input-foto-base64">
            <input type="hidden" name="tipe_absen" id="input-tipe-absen">

            <div style="display: flex; gap: 16px; justify-content: center; margin-top: 16px;">
                <button type="button" id="btn-absen-masuk" data-status="<?php
                    if ($tipe_default === 'masuk') {
                        echo 'belum_masuk';
                    } elseif ($tipe_default === 'keluar') {
                        echo 'sudah_masuk';
                    } else {
                        echo 'sudah_keluar';
                    }
                ?>" class="btn-absen" disabled>Absen Masuk</button>
                <button type="button" id="btn-absen-keluar" class="btn-absen" disabled>Absen Keluar</button>
            </div>
        </form>
        <!-- MODAL KONFIRMASI LEMBUR -->
        <div id="modal-lembur" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); align-items:center; justify-content:center;">
            <div style="background:#fff; padding:32px 24px; border-radius:12px; max-width:90vw; width:350px; margin:auto; box-shadow:0 4px 24px rgba(0,0,0,0.2); text-align:center;">
                <h3 style="margin-bottom:16px;">Apakah kamu melakukan overwork/lembur hari ini?</h3>
                <div style="display:flex; gap:16px; justify-content:center; margin-top:24px;">
                    <button id="btn-lembur-ya" style="padding:8px 24px; background:#007bff; color:#fff; border:none; border-radius:6px;">Ya</button>
                    <button id="btn-lembur-tidak" style="padding:8px 24px; background:#ccc; color:#333; border:none; border-radius:6px;">Tidak</button>
                </div>
            </div>
        </div>
            <?php if(isset($_GET['error'])): ?>
               <p class="error-message">Error: <?php echo htmlspecialchars($_GET['error']); ?>
               <?php if(isset($_GET['msg'])) echo '- ' . htmlspecialchars($_GET['msg']); ?>
               <?php if(isset($_GET['code'])) echo '(Code: ' . htmlspecialchars($_GET['code']) . ')'; ?>
               </p>
           <?php elseif(isset($_GET['status'])): ?>
                <p class="success-message">Status: <?php echo htmlspecialchars($_GET['status']); ?></p>
           <?php endif; ?>
        <?php endif; ?>
    </div>

<footer>
    <div class="footer-container">
        <p class="footer-text">© 2024 KAORI Indonesia. All rights reserved.</p>
        <p class="footer-text">Follow us on:</p>
        <div class="social-icons">
            <i class="fa fa-brands fa-instagram footer-link"></i>
        </div>
    </div>
</footer>
</body>
<script src="assets/js/script_absen.js" defer></script>
</html>