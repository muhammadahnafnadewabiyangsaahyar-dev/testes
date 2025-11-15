<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem HR Kaori - Aplikasi Manajemen Sumber Daya Manusia">
    <title>Login & Registrasi - Sistem HR Kaori</title>
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/forms.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body>
    <!-- Header dengan Logo -->
    <div class="header-container">
        <img class="logo" src="assets/images/logo.png" alt="Logo Kaori HR">
    </div>

    <!-- Main Container -->
    <div class="main-container">
        
        <!-- Whitelist Verification Section -->
        <div class="auth-section" id="whitelist-section" style="display: none;">
            <div class="auth-card">
                <h1 class="form-title">
                    <i class="fas fa-shield-alt"></i>
                    Verifikasi Whitelist
                </h1>
                
                <div class="security-notice">
                    <h3>
                        <i class="fas fa-shield-alt"></i>
                        Sistem Keamanan
                    </h3>
                    <p>
                        Hanya karyawan yang terdaftar di whitelist yang dapat mendaftar.
                        Pastikan nama lengkap Anda sudah ditambahkan oleh HR/Admin.
                    </p>
                </div>

                <form id="whitelistForm" class="auth-form" autocomplete="off">
                    <div class="input-group">
                        <i class="fas fa-user-check"></i>
                        <input type="text" 
                               name="whitelist_nama" 
                               id="whitelist_nama" 
                               placeholder="Masukkan Nama Lengkap" 
                               required>
                        <label for="whitelist_nama">Nama Lengkap (sesuai KTP)</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="checkBtn">
                        <i class="fas fa-search"></i>
                        <span class="btn-text">Cek Status Whitelist</span>
                        <span class="btn-loading" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                            Memverifikasi...
                        </span>
                    </button>
                </form>

                <div id="whitelist-result" class="result-container" style="display: none;">
                    <div class="result-card" id="result-content"></div>
                </div>

                <div id="registration-ready" class="registration-ready" style="display: none;">
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <h3>Whitelist Terverifikasi!</h3>
                        <p>Anda dapat melanjutkan proses registrasi.</p>
                    </div>
                    <button id="continueRegistrationBtn" class="btn btn-success">
                        <i class="fas fa-arrow-right"></i>
                        Lanjutkan Registrasi
                    </button>
                </div>

                <div class="auth-links">
                    <button id="backToLoginBtn" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Login
                    </button>
                    
                    <div class="help-info">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Belum terdaftar? Hubungi HR/Admin untuk ditambahkan ke whitelist.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Form Section -->
        <div class="auth-section" id="registration-section" style="display: none;">
            <div class="auth-card">
                <h1 class="form-title">
                    <i class="fas fa-user-plus"></i>
                    <span id="registrationTitle">Daftar</span>
                </h1>
                
                <form method="POST" action="register" class="auth-form" autocomplete="off" id="registrationForm">
                    <!-- Pre-filled Fields (Readonly) -->
                    <div class="input-group readonly">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               name="nama_panjang" 
                               id="signup_nama" 
                               placeholder="Nama Lengkap" 
                               readonly 
                               required 
                               value="<?php echo htmlspecialchars($form_data['nama_panjang'] ?? ''); ?>">
                        <label for="signup_nama">Nama Lengkap</label>
                    </div>
                    
                    <div class="input-group readonly">
                        <i class="fas fa-briefcase"></i>
                        <input type="text" 
                               name="posisi" 
                               id="signup_posisi" 
                               placeholder="Posisi" 
                               readonly 
                               required 
                               value="<?php echo htmlspecialchars($form_data['posisi'] ?? ''); ?>">
                        <label for="signup_posisi">Jabatan</label>
                    </div>
                    
                    <!-- Editable Fields -->
                    <div class="input-group">
                        <i class="fas fa-location-dot"></i>
                        <select name="outlet" 
                                id="outlet" 
                                required 
                                class="<?php echo isset($errors['outlet']) ? 'input-error' : ''; ?>">
                            <option value="">-- Pilih Outlet --</option>
                            <?php foreach ($dropdownData['outlets'] as $outlet): ?>
                                <option value="<?php echo htmlspecialchars($outlet); ?>" 
                                        <?php echo ($form_data['outlet'] ?? '') === $outlet ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($outlet); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="outlet">Outlet</label>
                    </div>
                    
                    <div class="input-group">
                        <i class="fab fa-telegram"></i>
                        <input type="tel" 
                               name="no_wa" 
                               id="no_wa" 
                               placeholder="62 8123xxxxxxx" 
                               autocomplete="off" 
                               required
                               pattern="62\s[0-9]{8,12}"
                               title="Format: 62 diikuti spasi dan 8-12 digit angka (Contoh: 62 81234567890)"
                               value="<?php echo isset($form_data['no_wa']) ? htmlspecialchars($form_data['no_wa']) : ''; ?>"
                               class="<?php echo isset($errors['no_wa']) ? 'input-error' : ''; ?>">
                        <label for="no_wa">No Telegram</label>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" 
                               name="email" 
                               placeholder="Email" 
                               autocomplete="off" 
                               required
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>"
                               class="<?php echo isset($errors['email']) ? 'input-error' : ''; ?>">
                        <label for="email">Email</label>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               name="username" 
                               placeholder="Username" 
                               autocomplete="off" 
                               required
                               value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>"
                               class="<?php echo isset($errors['username']) ? 'input-error' : ''; ?>">
                        <label for="username">Username</label>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               name="password" 
                               placeholder="Password" 
                               autocomplete="new-password" 
                               required
                               class="<?php echo isset($errors['password']) ? 'input-error' : ''; ?>">
                        <label for="password">Password</label>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               name="confirm_password" 
                               placeholder="Konfirmasi Password" 
                               autocomplete="new-password" 
                               required
                               class="<?php echo isset($errors['confirm_password']) ? 'input-error' : ''; ?>">
                        <label for="confirm_password">Konfirmasi Password</label>
                    </div>
                    
                    <!-- Error Display -->
                    <?php if (!empty($errors)): ?>
                        <div class="error-list">
                            <ul>
                                <?php foreach ($errors as $field => $msg): ?>
                                    <li><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" name="register" class="btn btn-success">
                        <i class="fas fa-user-plus"></i>
                        Daftar
                    </button>
                </form>
                
                <div class="auth-links">
                    <button id="backToWhitelistBtn" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Cek Whitelist
                    </button>
                </div>
            </div>
        </div>

        <!-- Login Form Section -->
        <div class="auth-section" id="login-section">
            <div class="auth-card">
                <h1 class="form-title">
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk
                </h1>
                
                <!-- Login Error Messages -->
                <?php if (isset($_GET['error'])): ?>
                    <?php
                    $errorMessages = [
                        'invalidpassword' => 'Password yang Anda masukkan salah.',
                        'usernotfound' => 'Username tidak ditemukan.',
                        'emptyfields' => 'Username dan Password harus diisi.',
                        'dberror' => 'Terjadi masalah pada database. Hubungi admin.',
                        'toomanyattempts' => 'Terlalu banyak percobaan login. Silakan tunggu 15 menit dan coba lagi.',
                        'sessionexpired' => 'Sesi Anda telah berakhir. Silakan login kembali.',
                        'notloggedin' => 'Anda harus login terlebih dahulu.'
                    ];
                    
                    $errorMessage = $errorMessages[$_GET['error']] ?? 'Terjadi kesalahan yang tidak diketahui.';
                    ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($errorMessage); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Success Messages -->
                <?php if (isset($_GET['status'])): ?>
                    <?php if ($_GET['status'] === 'register_success'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Registrasi berhasil! Silakan login dengan akun Anda.
                        </div>
                    <?php elseif ($_GET['status'] === 'register_error'): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            Registrasi gagal. Silakan periksa data Anda dan coba lagi.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <form method="POST" action="login" class="auth-form" autocomplete="off">
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               name="username" 
                               placeholder="Username" 
                               autocomplete="off" 
                               required>
                        <label for="username">Username</label>
                    </div>
                    
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               name="password" 
                               placeholder="Password" 
                               autocomplete="new-password" 
                               required>
                        <label for="password">Password</label>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="forgot-password">Lupa Password?</a>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i>
                        Masuk
                    </button>
                </form>
                
                <div class="divider">
                    <span>-----Atau-----</span>
                </div>
                
                <div class="auth-links">
                    <p>Belum Punya Akun?</p>
                    <button id="startRegistrationBtn" class="btn btn-success">
                        <i class="fas fa-user-plus"></i>
                        Daftar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Assets -->
    <script src="assets/js/utils.js"></script>
    <script src="assets/js/auth.js"></script>
    <script src="assets/js/forms.js"></script>
    <script src="assets/js/whitelist.js"></script>
</body>
</html>