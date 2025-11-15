<?php
session_start();
include 'connect.php';
include 'functions_role.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: index.php?error=unauthorized');
    exit;
}

// Generate CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handler POST - Tambah pegawai baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: tambah_pegawai.php?error=' . urlencode('Invalid request. Please try again.'));
        exit;
    }
    
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $posisi = trim($_POST['posisi'] ?? '');
    
    // Auto-detect role dari posisi
    $role = getRoleByPosisiFromDB($pdo, $posisi);
    
    if ($nama === '') {
        header('Location: tambah_pegawai.php?error=' . urlencode('Nama tidak boleh kosong.'));
        exit;
    }
    
    try {
        // Cek duplikat
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pegawai_whitelist WHERE nama_lengkap = ?");
        $stmt->execute([$nama]);
        if ($stmt->fetchColumn() > 0) {
            header('Location: tambah_pegawai.php?error=' . urlencode('Nama sudah ada di whitelist.'));
            exit;
        }
        
        // Insert pegawai baru
        $stmt = $pdo->prepare("INSERT INTO pegawai_whitelist (nama_lengkap, posisi, status_registrasi, role) VALUES (?, ?, 'pending', ?)");
        $stmt->execute([$nama, $posisi !== '' ? $posisi : null, $role]);
        
        header('Location: whitelist.php?success=' . urlencode('Pegawai berhasil ditambahkan ke whitelist.'));
        exit;
    } catch (PDOException $e) {
        header('Location: tambah_pegawai.php?error=' . urlencode('Gagal menambah data: ' . $e->getMessage()));
        exit;
    }
}

// Ambil daftar posisi
$posisi_jabatan = $pdo->query("SELECT nama_posisi FROM posisi_jabatan ORDER BY nama_posisi ASC")->fetchAll(PDO::FETCH_COLUMN);

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Pegawai - Whitelist</title>
    <link rel="stylesheet" href="style_modern.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }
        .btn-primary:hover {
            background-color: #45a049;
        }
        .btn-secondary {
            background-color: #999;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #777;
        }
        .info-box {
            background-color: #e3f2fd;
            padding: 12px;
            border-left: 4px solid #2196F3;
            margin-bottom: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="headercontainer">
    <?php include 'navbar.php'; ?>
</div>
<div class="main-title">Teman KAORI</div>
<div class="subtitle-container">
    <p class="subtitle">Selamat Datang, <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']); ?> [<?= htmlspecialchars($_SESSION['role']); ?>]</p>
</div>
<div class="container">
    <div class="form-container">
        <h1 style="margin-top:0;">➕ Tambah Pegawai Baru</h1>
        
        <?php if ($error): ?>
            <div style="padding:12px; margin-bottom:20px; background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:5px;">
                ✗ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            💡 <strong>Info:</strong> Role pegawai akan otomatis disesuaikan berdasarkan posisi yang dipilih.
        </div>
        
        <form method="post" action="tambah_pegawai.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="nama_lengkap">Nama Lengkap <span style="color:red;">*</span></label>
                <input type="text" name="nama_lengkap" id="nama_lengkap" required 
                       placeholder="Masukkan nama lengkap pegawai">
            </div>
            
            <div class="form-group">
                <label for="posisi">Posisi/Jabatan <span style="color:red;">*</span></label>
                <select name="posisi" id="posisi" required onchange="if(this.value==='__buat_baru__'){window.location='posisi_jabatan.php';}">
                    <option value="">-- Pilih Posisi --</option>
                    <?php foreach ($posisi_jabatan as $posisi): ?>
                        <option value="<?= htmlspecialchars($posisi) ?>"><?= htmlspecialchars($posisi) ?></option>
                    <?php endforeach; ?>
                    <option value="__buat_baru__">+ Buat/Edit Posisi Baru...</option>
                </select>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">💾 Simpan Pegawai</button>
                <a href="whitelist.php" class="btn btn-secondary">❌ Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
// Check nama lengkap via AJAX
document.getElementById('nama_lengkap').addEventListener('blur', function() {
    var nama = this.value.trim();
    if (nama !== '') {
        fetch('whitelist.php?check=1&nama=' + encodeURIComponent(nama))
            .then(response => response.json())
            .then(data => {
                if (data.found) {
                    alert('⚠️ Nama "' + nama + '" sudah ada di whitelist dengan status: ' + data.status);
                    document.getElementById('posisi').value = data.posisi;
                }
            })
            .catch(err => console.error('Error checking nama:', err));
    }
});

// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
</body>
</html>
