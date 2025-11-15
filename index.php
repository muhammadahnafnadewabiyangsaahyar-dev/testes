<?php
// ========================================================
// --- SESSION CHECK: Redirect jika sudah login ---
// ========================================================
session_start();
require_once 'security_helper.php';

// Cek jika user sudah login dan session masih valid
if (isset($_SESSION['user_id']) && SecurityHelper::validateSession()) {
    // Redirect ke mainpage sesuai role
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] === 'admin') {
            header('Location: mainpage.php');
        } else {
            header('Location: mainpage.php');
        }
        exit;
    } else {
        // Fallback jika role tidak ada
        header('Location: mainpage.php');
        exit;
    }
}

// Jika session tidak valid, destroy session
if (isset($_SESSION['user_id']) && !SecurityHelper::validateSession()) {
    session_unset();
    session_destroy();
    session_start(); // Start fresh session
}

// ========================================================
// --- LOGIKA REGISTRASI & KONEKSI DB ---
// ========================================================
include 'connect.php'; // WAJIB ADA untuk koneksi DB (PDO)

$errors = []; // Array untuk menampung semua error
$form_data = []; // Array untuk "sticky form"
$registration_attempted = false; // Penanda untuk JS

// --- 1. PROSES JIKA ADA SUBMIT REGISTRASI ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {

    log_info("=== REGISTRATION SUBMIT START ===", ['post_data' => $_POST]);

    $registration_attempted = true; // Tandai bahwa ada percobaan daftar

    // Ambil dan bersihkan data, simpan ke $form_data untuk sticky form
    // (Sanitasi dasar, htmlspecialchars akan digunakan saat output)
    $form_data['nama_panjang'] = $_POST['nama_panjang'] ?? '';
    $form_data['posisi'] = $_POST['posisi'] ?? '';
    $form_data['outlet'] = $_POST['outlet'] ?? '';
    $form_data['no_wa'] = $_POST['no_wa'] ?? '';
    $form_data['email'] = $_POST['email'] ?? '';
    $form_data['username'] = $_POST['username'] ?? '';
    $form_data['password'] = $_POST['password'] ?? '';
    $form_data['confirm_password'] = $_POST['confirm_password'] ?? '';

    log_debug("Form data captured", $form_data);

    // --- 2. VALIDASI PER-FIELD ---
    if (empty($form_data['nama_panjang'])) $errors['nama_panjang'] = 'Nama Lengkap harus diisi.';
    if (empty($form_data['posisi'])) $errors['posisi'] = 'Posisi harus dipilih.';
    if (empty($form_data['outlet'])) $errors['outlet'] = 'Outlet harus dipilih.';

    // Validasi No. Telegram: field kosong ATAU hanya berisi '62 ' atau '62' dianggap kosong
    $no_wa_cleaned = trim($form_data['no_wa']);
    if (empty($no_wa_cleaned) || $no_wa_cleaned === '62' || $no_wa_cleaned === '62 ') {
        $errors['no_wa'] = 'No. Telegram harus diisi.';
    } elseif (!preg_match('/^62\s[0-9]{8,12}$/', $no_wa_cleaned)) {
        $errors['no_wa'] = 'Format salah (Contoh: 62 81234567890).';
    }

    if (empty($form_data['email'])) {
        $errors['email'] = 'Email harus diisi.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Format email tidak valid.';
    }
    if (empty($form_data['username'])) $errors['username'] = 'Username harus diisi.';
    if (empty($form_data['password'])) $errors['password'] = 'Password harus diisi.';
    if (empty($form_data['confirm_password'])) {
        $errors['confirm_password'] = 'Konfirmasi password harus diisi.';
    } elseif ($form_data['password'] !== $form_data['confirm_password']) {
        $errors['confirm_password'] = 'Password dan Konfirmasi tidak cocok.';
    }

    log_warn("Validation errors found", ['errors' => $errors, 'form_data' => $form_data]);

    // --- 3. WHITELIST-BASED REGISTRATION SECURITY ---
    if (empty($errors)) {
        error_log("✅ No validation errors, checking whitelist security...");

        try {
            // Cek apakah nama ada di whitelist
            $sql_cek_whitelist = "SELECT * FROM pegawai_whitelist WHERE nama_lengkap = ? AND status_registrasi = 'pending'";
            $stmt_cek = $pdo->prepare($sql_cek_whitelist);
            $stmt_cek->execute([$form_data['nama_panjang']]);
            $whitelist_data = $stmt_cek->fetch(PDO::FETCH_ASSOC);

            if (!$whitelist_data) {
                // Cek apakah nama sudah terdaftar
                $sql_cek_registered = "SELECT id FROM register WHERE nama_lengkap = ?";
                $stmt_registered = $pdo->prepare($sql_cek_registered);
                $stmt_registered->execute([$form_data['nama_panjang']]);

                if ($stmt_registered->fetch()) {
                    $errors['nama_panjang'] = 'Error! Nama ini sudah terdaftar. Silakan login.';
                } else {
                    $errors['nama_panjang'] = 'Error! Nama Anda belum terdaftar di whitelist. Silakan hubungi HR/Admin untuk ditambahkan.';
                }
            } else {
                log_info("Whitelist check passed - proceeding with secure registration", ['nama' => $form_data['nama_panjang']]);

                // --- 4. SECURE REGISTRATION PROCESS ---
                $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);

                // Get role from whitelist or default to 'user'
                $role = $whitelist_data['role'] ?? 'user';
                $posisi = $whitelist_data['posisi'] ?? $form_data['posisi'];

                // Start secure transaction
                $pdo->beginTransaction();

                try {
                    // 1. Insert into register table
                    $sql_register = "INSERT INTO register
                                   (nama_lengkap, posisi, outlet, no_telegram, email, password, username, role, time_created)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt_register = $pdo->prepare($sql_register);
                    $stmt_register->execute([
                        $form_data['nama_panjang'],
                        $posisi,
                        $form_data['outlet'],
                        $form_data['no_wa'],
                        $form_data['email'],
                        $hashed_password,
                        $form_data['username'],
                        $role
                    ]);

                    $new_user_id = $pdo->lastInsertId();

                    // 2. Update whitelist status to 'terdaftar'
                    $sql_update_whitelist = "UPDATE pegawai_whitelist SET status_registrasi = 'terdaftar' WHERE nama_lengkap = ?";
                    $stmt_update = $pdo->prepare($sql_update_whitelist);
                    $stmt_update->execute([$form_data['nama_panjang']]);

                    // 3. Auto-sync salary components if available
                    if (!empty($whitelist_data['gaji_pokok']) || !empty($whitelist_data['tunjangan_transport'])) {
                        $sql_salary = "INSERT INTO komponen_gaji
                                     (register_id, jabatan, gaji_pokok, tunjangan_transport, tunjangan_makan,
                                      overwork, tunjangan_jabatan)
                                     VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt_salary = $pdo->prepare($sql_salary);
                        $stmt_salary->execute([
                            $new_user_id,
                            $posisi,
                            $whitelist_data['gaji_pokok'] ?? 0,
                            $whitelist_data['tunjangan_transport'] ?? 0,
                            $whitelist_data['tunjangan_makan'] ?? 0,
                            $whitelist_data['overwork'] ?? 0,
                            $whitelist_data['tunjangan_jabatan'] ?? 0
                        ]);
                    }

                    // Commit transaction
                    $pdo->commit();

                    // Log successful registration
                    log_info("SECURE REGISTRATION SUCCESS", [
                        'user_name' => $form_data['nama_panjang'],
                        'user_id' => $new_user_id,
                        'email' => $form_data['email'],
                        'role' => $role
                    ]);

                    // Send welcome notification (placeholder for future WA integration)
                    // sendWelcomeNotification($form_data['no_wa'], $form_data['nama_panjang']);

                    header("Location: index.php?status=register_success");
                    exit();

                } catch (PDOException $e) {
                    $pdo->rollBack();

                    // Handle specific database errors
                    if ($e->getCode() == 1062) { // Duplicate entry
                        $error_msg = $e->getMessage();
                        if (strpos($error_msg, 'username') !== false) {
                            $errors['username'] = 'Username sudah digunakan.';
                        } elseif (strpos($error_msg, 'email') !== false) {
                            $errors['email'] = 'Email sudah digunakan.';
                        } elseif (strpos($error_msg, 'no_whatsapp') !== false) {
                            $errors['no_wa'] = 'Nomor Telegram sudah digunakan.';
                        } else {
                            $errors['general'] = 'Data sudah terdaftar di sistem.';
                        }
                    } else {
                        $errors['general'] = 'Registrasi gagal. Silakan coba lagi.';
                    }

                    log_error("REGISTRATION FAILED", [
                        'error' => $e->getMessage(),
                        'user_name' => $form_data['nama_panjang'],
                        'email' => $form_data['email']
                    ]);
                }
            }

        } catch (PDOException $e) {
            $errors['general'] = 'Sistem sedang mengalami gangguan. Silakan coba lagi nanti.';
            error_log("❌ WHITELIST CHECK ERROR: " . $e->getMessage());
        }
    }
} // --- AKHIR BLOK REGISTRASI ---


// ========================================================
// --- AMBIL DATA DROPDOWN (setelah logika POST) ---
// ========================================================
// Menggunakan try-catch untuk mengambil data
$daftar_posisi = [];
$daftar_cabang = [];

try {
    // Log: Ambil data dropdown
    error_log("=== FETCHING DROPDOWN DATA FOR REGISTRATION ===");
    
    // Ambil Daftar Posisi (dari tabel baru 'posisi_jabatan')
    $sql_posisi = "SELECT nama_posisi FROM posisi_jabatan ORDER BY nama_posisi ASC";
    $daftar_posisi = $pdo->query($sql_posisi)->fetchAll(PDO::FETCH_COLUMN);
    error_log("Posisi fetched: " . count($daftar_posisi) . " items");
    error_log("Posisi list: " . print_r($daftar_posisi, true));

    // Ambil Daftar Cabang (dari tabel 'cabang_outlet')
    // Pada halaman registrasi, selalu sembunyikan KAORI HQ terlebih dahulu
    // KAORI HQ akan ditampilkan melalui JavaScript jika user adalah admin/superadmin
    $sql_cabang = "SELECT nama_cabang FROM cabang_outlet WHERE nama_cabang != 'KAORI HQ' ORDER BY nama_cabang ASC";
    $daftar_cabang = $pdo->query($sql_cabang)->fetchAll(PDO::FETCH_COLUMN);
    error_log("Cabang fetched: " . count($daftar_cabang) . " items");
    
    error_log("=== END FETCHING DROPDOWN DATA ===");

} catch (PDOException $e) {
    // Jika tabel tidak ada, set error general
    $errors['general'] = "Gagal memuat data formulir. Database belum siap. (" . $e->getMessage() . ")";
    log_error("Failed to fetch dropdown data", ['error' => $e->getMessage()]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="style_modern.css">
    <title>Login & Register</title>
</head>
<body>
    <div class="headercontainer">
    <img class="logo" src="logo.png" alt="Logo">
    </div>

    <!-- Enhanced Whitelist Check Modal -->
    <div class="container" id="check-whitelist" style="display: none;">
        <h1 class="form-title">🔐 Verifikasi Whitelist</h1>
        <div class="security-notice" style="background: #e8f4fd; border: 1px solid #2196F3; border-radius: 8px; padding: 15px; margin-bottom: 20px;">
            <h3 style="color: #1565C0; margin: 0 0 10px 0;"><i class="fa fa-shield-alt"></i> Sistem Keamanan</h3>
            <p style="margin: 0; color: #1565C0; font-size: 14px;">
                Hanya karyawan yang terdaftar di whitelist yang dapat mendaftar.
                Pastikan nama lengkap Anda sudah ditambahkan oleh HR/Admin.
            </p>
        </div>

        <form id="whitelistForm" autocomplete="off">
            <div class="input-group">
                <i class="fa fa-user-check"></i>
                <input type="text" name="whitelist_nama" id="whitelist_nama" placeholder="Masukkan Nama Lengkap" required>
                <label for="whitelist_nama">Nama Lengkap (sesuai KTP)</label>
            </div>
            <button type="submit" class="btn" id="checkBtn">
                <i class="fa fa-search"></i> Cek Status Whitelist
            </button>
        </form>

        <div id="whitelist-result" style="margin-top: 20px; display: none;">
            <div class="result-card" id="result-content"></div>
        </div>

        <div id="registration-ready" style="display: none; margin-top: 20px;">
            <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 15px; text-align: center;">
                <i class="fa fa-check-circle" style="color: #155724; font-size: 24px;"></i>
                <h3 style="color: #155724; margin: 10px 0;">Whitelist Terverifikasi!</h3>
                <p style="color: #155724; margin: 0;">Anda dapat melanjutkan proses registrasi.</p>
            </div>
            <button id="lanjutDaftarBtn" class="btn" style="margin-top: 15px; width: 100%;">
                <i class="fa fa-arrow-right"></i> Lanjutkan Registrasi
            </button>
        </div>

        <div class="links" style="margin-top: 20px;">
            <button id="backToLoginBtn" class="btn-secondary">
                <i class="fa fa-arrow-left"></i> Kembali ke Login
            </button>
            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                <small style="color: #666;">
                    <i class="fa fa-info-circle"></i>
                    Belum terdaftar? Hubungi HR/Admin untuk ditambahkan ke whitelist.
                </small>
            </div>
        </div>
    </div>

    <!-- Form Registrasi (hanya muncul setelah whitelist valid) -->
    <div class="container" id="signup" style="display:none;">
        <h1 class="form-title">Daftar</h1>
        <form method="POST" action="index.php" autocomplete="off" id="signupForm">
            <!-- Nama & Posisi readonly -->
            <div class="input-group">
                <i class="fa fa-user"></i>
                <input type="text" name="nama_panjang" id="signup_nama" placeholder="Nama Lengkap" readonly required value="<?php echo htmlspecialchars($form_data['nama_panjang'] ?? ''); ?>">
                <label for="signup_nama">Nama Lengkap</label>
            </div>
            <div class="input-group">
                <i class="fa fa-briefcase"></i>
                <input type="text" name="posisi" id="signup_posisi" placeholder="Posisi" readonly required value="<?php echo htmlspecialchars($form_data['posisi'] ?? ''); ?>">
                <label for="signup_posisi">Jabatan</label>
            </div>
            <!-- Field lain seperti biasa -->
            <div class="input-group">
                <i class="fa fa-location-dot"></i>
                <select name="outlet" id="outlet" required class="<?php echo isset($errors['outlet']) ? 'input-error' : ''; ?>">
                    <option value="" <?php echo empty($form_data['outlet']) ? 'selected' : ''; ?>>-- Pilih Outlet --</option>
                    <?php foreach ($daftar_cabang as $cabang): ?>
                        <option value="<?php echo htmlspecialchars($cabang); ?>" <?php echo ($form_data['outlet'] ?? '') === $cabang ? 'selected' : ''; ?>><?php echo htmlspecialchars($cabang); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="outlet">Outlet</label>
            </div>
            <div class="input-group">
                <i class="fa fa-brands fa-telegram"></i>
                <input type="tel" name="no_wa" id="no_wa" placeholder="62 8123xxxxxxx" autocomplete="off" required
                       pattern="62\s[0-9]{8,12}"
                       title="Format: 62 diikuti spasi dan 8-12 digit angka (Contoh: 62 81234567890)"
                       value="<?php echo isset($form_data['no_wa']) ? htmlspecialchars($form_data['no_wa']) : ''; ?>"
                       class="<?php echo isset($errors['no_wa']) ? 'input-error' : ''; ?>">
                <label for="no_wa">No Telegram</label>
            </div>
            <div class="input-group">
                <i class="fa fa-envelope"></i>
                <input type="email" name="email" placeholder="Email" autocomplete="off" required
                       value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                       class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>">
                <label for="email">Email</label>
            </div>
            <div class="input-group">
                <i class="fa fa-user"></i>
                <input type="text" name="username" placeholder="Username" autocomplete="off" required
                       value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                       class="<?php echo isset($errors['username']) ? 'input-error' : ''; ?>">
                <label for="username">Username</label>
            </div>
            <div class="input-group">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" placeholder="Password" autocomplete="new-password" required
                       class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                <label for="password">Password</label>
            </div>
            <div class="input-group">
                <i class="fa fa-lock"></i>
                <input type="password" name="confirm_password" placeholder="Confirm Password" autocomplete="new-password" required
                       class="<?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>">
                <label for="confirm_password">Confirm Password</label>
            </div>
            <?php if (!empty($errors)): ?>
                <div class="error-list" style="color:red;margin-bottom:10px;">
                    <ul style="margin:0;padding-left:18px;">
                        <?php foreach ($errors as $field => $msg): ?>
                            <li><?= htmlspecialchars($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <button type="submit" name="register" class="btn">Daftar</button>
        </form>
        <div class="links">
            <button id="backToWhitelistBtn">Kembali ke Cek Whitelist</button>
        </div>
    </div>

    <!-- Login Form tetap -->
    <div class="container" id="login">
        <h1 class="form-title">Masuk</h1>
        <form method="POST" action="login.php" autocomplete="off">
            <?php 
            // Logika untuk menampilkan error dari login.php
            if (isset($_GET['error'])) {
                $error_message = '';
                switch ($_GET['error']) {
                    case 'invalidpassword':
                        $error_message = 'Password yang Anda masukkan salah.';
                        break;
                    case 'usernotfound':
                        $error_message = 'Username tidak ditemukan.';
                        break;
                    case 'emptyfields':
                        $error_message = 'Username dan Password harus diisi.';
                        break;
                    case 'dberror':
                        $error_message = 'Terjadi masalah pada database. Hubungi admin.';
                        break;
                    case 'toomanyattempts':
                        $error_message = 'Terlalu banyak percobaan login. Silakan tunggu 15 menit dan coba lagi.';
                        break;
                    case 'sessionexpired':
                        $error_message = 'Sesi Anda telah berakhir. Silakan login kembali.';
                        break;
                    case 'notloggedin':
                        $error_message = 'Anda harus login terlebih dahulu.';
                        break;
                }
                if ($error_message) {
                    echo '<p class="general-error">' . htmlspecialchars($error_message) . '</p>';
                }
            }
            ?>
            <div class="input-group">
                <i class="fa fa-user"></i>
                <input type="text" name="username" placeholder="Username" autocomplete="off" required>
                <label for="username">Username</label>
            </div>
            <div class="input-group">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" placeholder="Password" autocomplete="new-password" required>
                <label for="password">Password</label>
            </div>
            <div class="recover">
                <a href="forgot_password.php">Lupa Password?</a>
            </div>
            <button type="submit" name="login" class="btn">Masuk</button>
        </form>
        <p class="or">-----Atau-----</p>
        <div class="links">
            <p>Belum Punya Akun?</p>
            <button id="signupbutton">Daftar</button>
        </div>
    </div>
    <script src="script.js"></script>
    <script>
    // Enhanced Whitelist Check with Real-time Validation
    document.addEventListener('DOMContentLoaded', function() {
        const whitelistForm = document.getElementById('whitelistForm');
        const checkBtn = document.getElementById('checkBtn');
        const resultDiv = document.getElementById('whitelist-result');
        const resultContent = document.getElementById('result-content');
        const registrationReady = document.getElementById('registration-ready');
        const lanjutBtn = document.getElementById('lanjutDaftarBtn');

        if (whitelistForm) {
            whitelistForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const nama = document.getElementById('whitelist_nama').value.trim();
                if (!nama) return;

                // Show loading state
                checkBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memverifikasi...';
                checkBtn.disabled = true;

                try {
                    const response = await fetch(`whitelist.php?check=1&nama=${encodeURIComponent(nama)}`);
                    const data = await response.json();

                    resultDiv.style.display = 'block';

                    if (data.found) {
                        // Name found in whitelist
                        resultContent.innerHTML = `
                            <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 15px;">
                                <i class="fa fa-check-circle" style="color: #155724; font-size: 24px;"></i>
                                <h3 style="color: #155724; margin: 10px 0;">Whitelist Ditemukan!</h3>
                                <div style="color: #155724;">
                                    <p><strong>Nama:</strong> ${data.nama_lengkap}</p>
                                    <p><strong>Posisi:</strong> ${data.posisi}</p>
                                    <p><strong>Status:</strong> <span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;">${data.status}</span></p>
                                </div>
                            </div>
                        `;

                        // Show registration button
                        registrationReady.style.display = 'block';

                        // Store data for registration form
                        lanjutBtn.onclick = function() {
                            showRegistrationForm(data);
                        };

                    } else {
                        // Name not found
                        resultContent.innerHTML = `
                            <div style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; padding: 15px;">
                                <i class="fa fa-times-circle" style="color: #721c24; font-size: 24px;"></i>
                                <h3 style="color: #721c24; margin: 10px 0;">Nama Tidak Ditemukan</h3>
                                <p style="color: #721c24; margin: 0;">
                                    Nama "${nama}" belum terdaftar di whitelist. Silakan hubungi HR/Admin untuk ditambahkan.
                                </p>
                            </div>
                        `;
                        registrationReady.style.display = 'none';
                    }

                } catch (error) {
                    resultContent.innerHTML = `
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px;">
                            <i class="fa fa-exclamation-triangle" style="color: #856404; font-size: 24px;"></i>
                            <h3 style="color: #856404; margin: 10px 0;">Error</h3>
                            <p style="color: #856404; margin: 0;">Terjadi kesalahan saat memverifikasi. Silakan coba lagi.</p>
                        </div>
                    `;
                    registrationReady.style.display = 'none';
                }

                // Reset button
                checkBtn.innerHTML = '<i class="fa fa-search"></i> Cek Status Whitelist';
                checkBtn.disabled = false;
            });
        }

        // Function to show registration form with pre-filled data
        function showRegistrationForm(whitelistData) {
            // Hide whitelist check
            document.getElementById('check-whitelist').style.display = 'none';

            // Show registration form
            document.getElementById('signup').style.display = 'block';

            // Pre-fill form data
            document.getElementById('signup_nama').value = whitelistData.nama_lengkap;
            document.getElementById('signup_posisi').value = whitelistData.posisi;

            // Jika role adalah admin atau superadmin, tambahkan opsi KAORI HQ ke dropdown outlet
            if (whitelistData.role && (whitelistData.role === 'admin' || whitelistData.role === 'superadmin')) {
                const outletSelect = document.getElementById('outlet');
                if (outletSelect) {
                    // Cek apakah KAORI HQ sudah ada
                    let kaoriHqExists = false;
                    for (let i = 0; i < outletSelect.options.length; i++) {
                        if (outletSelect.options[i].value === 'KAORI HQ') {
                            kaoriHqExists = true;
                            break;
                        }
                    }

                    // Jika belum ada, tambahkan
                    if (!kaoriHqExists) {
                        const option = document.createElement('option');
                        option.value = 'KAORI HQ';
                        option.textContent = 'KAORI HQ';
                        outletSelect.appendChild(option);
                    }
                }
            }

            // Update form title
            const formTitle = document.querySelector('#signup .form-title');
            if (formTitle) {
                formTitle.innerHTML = '<i class="fa fa-user-plus"></i> Lengkapi Registrasi';
            }
        }
    });
    </script>
    <script>
// Autofill dan kunci prefix '62 ' hanya saat user fokus
document.addEventListener('DOMContentLoaded', function() {
    var noWaInput = document.getElementById('no_wa');
    if (noWaInput) {
        // Set prefix hanya saat fokus pertama kali (jika kosong)
        noWaInput.addEventListener('focus', function() {
            if (this.value === '' || this.value === '62') {
                this.value = '62 ';
                // Set cursor position setelah prefix
                setTimeout(() => {
                    this.setSelectionRange(3, 3);
                }, 0);
            }
        });

        noWaInput.addEventListener('keydown', function(e) {
            // Prevent deleting prefix
            if ((this.selectionStart <= 3 && (e.key === 'Backspace' || e.key === 'Delete')) ||
                (this.selectionStart < 3 && e.key.length === 1)) {
                e.preventDefault();
                this.setSelectionRange(3, 3);
            }
        });

        noWaInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedText = (e.clipboardData || window.clipboardData).getData('text');
            // Bersihkan dan format ulang
            const cleaned = pastedText.replace(/[^0-9]/g, '');
            if (cleaned.length >= 8) {
                this.value = '62 ' + cleaned.substring(0, 12);
            }
        });
    }
});
</script>
    <?php
    // Debug minimal untuk kasus registrasi tidak tersimpan
    if ($registration_attempted) {
        error_log("DEBUG_REGISTER: attempted=" . ($registration_attempted ? '1' : '0') . ", errors=" . json_encode($errors));
    }

    // --- Pastikan form registrasi tetap terbuka jika ada error setelah submit ---
    if ($registration_attempted && !empty($errors)) {
        echo "
        <script>
            document.getElementById('login').style.display = 'none';
            document.getElementById('signup').style.display = 'block';
            document.getElementById('check-whitelist').style.display = 'none';
        </script>
        ";
    }
    ?>
</body>
</html>