<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['role']);

// --- 1. Tentukan URL Default ---
// Jika tidak login, $mainpage_url mengarah ke index.
// Jika login, akan ditimpa ke mainpage.php di bawah.
$mainpage_url = 'index.php';

// --- 2. Inisialisasi semua variabel link ---
// Ini untuk mencegah error "undefined variable"
$profile_url = null;
$surat_url = null;
$absen_url = null;
$rekapabsen_url = null;
$slipgaji_url = null;
$shift_confirmation_url = null;
$approvesurat_url = null; // Link ini ada di logika PHP Anda tapi tidak dipakai
$approvelembur_url = null;
$view_user_url = null;
$view_absensi_url = null;
$shift_management_url = null;
$kalender_url = null;
$whitelist_url = null;

// --- 3. Tetapkan URL jika user sudah login ---
if ($is_logged_in) {
    
    // URL Halaman Utama untuk yang sudah login
    $mainpage_url = 'mainpage.php';

    // --- Link untuk SEMUA user yang login (User & Admin) ---
    $profile_url = 'profile.php';
    $surat_url = 'suratizin.php'; // Updated: Form baru untuk izin/sakit
    $absen_url = 'absen.php';
    $rekapabsen_url = 'rekapabsen.php';
    $shift_confirmation_url = 'shift_confirmation.php'; // Konfirmasi shift untuk semua user
    
    // --- Slip Gaji: Different URLs based on role ---
    if ($_SESSION['role'] == 'admin') {
        // Admin menggunakan halaman management (full features)
        $slipgaji_url = 'slip_gaji_management.php';
    } else {
        // User biasa menggunakan halaman view-only
        $slipgaji_url = 'slipgaji.php';
    }

    // --- Link yang HANYA dimiliki ADMIN ---
    if (in_array($_SESSION['role'], ['admin', 'superadmin'])) {
        $approvesurat_url = 'approve.php'; // Link untuk approve surat izin
        $approvelembur_url = 'approve_lembur.php';
        $view_user_url = 'view_user.php';
        $view_absensi_url = 'view_absensi.php';
        $whitelist_url = 'whitelist.php'; // Tambahkan whitelist
        $shift_management_url = 'shift_management.php'; // Kelola shift untuk admin (table mode)
        $kalender_url = 'kalender.php'; // Kelola shift dengan calendar view
    }
}
?>

<div class="headercontainer">
    <img class="logo" src="logo.png" alt="Logo">
    <div class="nav-links">

        <a href="<?php echo $mainpage_url; ?>" class="home">🏠 Home</a>

        <?php if ($is_logged_in): ?>

            <a href="<?php echo $profile_url; ?>" class="profile">👤 Profile</a>
            <a href="<?php echo $surat_url; ?>" class="surat">📄 Ajukan Izin/Sakit</a>
            <a href="<?php echo $absen_url; ?>" class="absensi">📍 Absensi</a>
            <a href="<?php echo $rekapabsen_url; ?>" class="rekapabsen">📊 Rekap Absensi</a>
            <a href="<?php echo $slipgaji_url; ?>" class="slipgaji">💰 Slip Gaji</a>
            <a href="<?php echo $shift_confirmation_url; ?>" class="shift-confirmation">✅ Konfirmasi Shift</a>
            <a href="jadwal_shift.php" class="jadwalshift">📅 Jadwal Shift</a>

            <?php if (in_array($_SESSION['role'], ['admin', 'superadmin'])): ?>
                <div class="nav-dropdown">
                    <button class="nav-dropdown-btn">⚙️ Admin Panel ▼</button>
                    <div class="nav-dropdown-content">
                        <a href="kalender.php" class="shift-calendar">📅 Kalender Shift</a>
                        <a href="<?php echo $kalender_url; ?>" class="shift-calendar">📋 Table Shift</a>
                        <a href="<?php echo $approvesurat_url; ?>" class="surat">📄 Approve Surat</a>
                        <a href="<?php echo $view_user_url; ?>" class="viewusers">👥 Daftar Pengguna</a>
                        <a href="<?php echo $view_absensi_url; ?>" class="viewabsensi">📊 Daftar Absensi</a>
                        <a href="<?php echo $approvelembur_url; ?>" class="lembur">⏰ Approve Lembur</a>
                        <a href="<?php echo $whitelist_url; ?>" class="whitelist">📋 Whitelist</a>
                        <a href="posisi_jabatan.php" class="posisi">🏢 Kelola Posisi</a>
                        <a href="overview.php" class="overview">📈 Overview Kinerja</a>
                    </div>
                </div>
            <?php endif; ?>

            <a href="logout.php" class="logout">🚪 Logout</a>

        <?php endif; ?>

    </div>
</div>

<style>
.nav-dropdown {
    position: relative;
    display: inline-block;
}

.nav-dropdown-btn {
    background: #667eea;
    color: white;
    padding: 8px 12px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: background 0.3s;
}

.nav-dropdown-btn:hover {
    background: #5568d3;
}

.nav-dropdown-content {
    display: none;
    position: absolute;
    background-color: white;
    min-width: 200px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.2);
    z-index: 1000;
    border-radius: 4px;
    top: 100%;
    left: 0;
    margin-top: 2px;
}

.nav-dropdown-content a {
    color: #333;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    border-bottom: 1px solid #eee;
    transition: background 0.3s;
}

.nav-dropdown-content a:last-child {
    border-bottom: none;
}

.nav-dropdown-content a:hover {
    background-color: #f8f9fa;
}

.nav-dropdown:hover .nav-dropdown-content {
    display: block;
}

.nav-dropdown-btn:focus + .nav-dropdown-content,
.nav-dropdown-content:focus-within {
    display: block;
}
</style>