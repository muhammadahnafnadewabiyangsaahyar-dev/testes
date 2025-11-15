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

// Get nama from URL
$nama_lengkap = $_GET['nama'] ?? '';
if ($nama_lengkap === '') {
    header('Location: whitelist.php?error=' . urlencode('Nama pegawai tidak valid.'));
    exit;
}

// Handler POST - Update pegawai
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: edit_pegawai.php?nama=' . urlencode($nama_lengkap) . '&error=' . urlencode('Invalid request.'));
        exit;
    }
    
    $old_nama = trim($_POST['old_nama_lengkap'] ?? '');
    $nama = trim($_POST['nama_lengkap'] ?? '');
    $posisi = trim($_POST['posisi'] ?? '');
    
    // Auto-detect role dari posisi
    $role = getRoleByPosisiFromDB($pdo, $posisi);
    
    // Komponen gaji
    $gaji_pokok = $_POST['gaji_pokok'] !== '' ? floatval($_POST['gaji_pokok']) : 0;
    $tunjangan_transport = $_POST['tunjangan_transport'] !== '' ? floatval($_POST['tunjangan_transport']) : 0;
    $tunjangan_makan = $_POST['tunjangan_makan'] !== '' ? floatval($_POST['tunjangan_makan']) : 0;
    $overwork = $_POST['overwork'] !== '' ? floatval($_POST['overwork']) : 0;
    $tunjangan_jabatan = $_POST['tunjangan_jabatan'] !== '' ? floatval($_POST['tunjangan_jabatan']) : 0;
    $bonus_marketing = $_POST['bonus_marketing'] !== '' ? floatval($_POST['bonus_marketing']) : 0;
    $insentif_omset = $_POST['insentif_omset'] !== '' ? floatval($_POST['insentif_omset']) : 0;
    
    try {
        // Mulai transaction untuk atomic operation
        $pdo->beginTransaction();
        
        // 1. Update pegawai_whitelist
        $stmt = $pdo->prepare("UPDATE pegawai_whitelist SET nama_lengkap=?, posisi=?, role=? WHERE nama_lengkap=?");
        $stmt->execute([$nama, $posisi, $role, $old_nama]);
        
        // 2. Update nama di tabel register jika nama berubah
        if ($old_nama !== $nama) {
            $stmt = $pdo->prepare("UPDATE register SET nama_lengkap=? WHERE nama_lengkap=?");
            $stmt->execute([$nama, $old_nama]);
        }
        
        // 3. Lookup register_id
        $stmt = $pdo->prepare("SELECT id FROM register WHERE nama_lengkap=? LIMIT 1");
        $stmt->execute([$nama]);
        $register_id = $stmt->fetchColumn();
        
        if ($register_id) {
            // 4. Cek apakah sudah ada komponen_gaji
            $stmt = $pdo->prepare("SELECT id FROM komponen_gaji WHERE register_id=?");
            $stmt->execute([$register_id]);
            $id_gaji = $stmt->fetchColumn();
            
            if ($id_gaji) {
                // Update komponen_gaji
                $stmt = $pdo->prepare("UPDATE komponen_gaji SET jabatan=?, gaji_pokok=?, tunjangan_transport=?, tunjangan_makan=?, overwork=?, tunjangan_jabatan=?, bonus_marketing=?, insentif_omset=? WHERE id=?");
                $stmt->execute([$posisi, $gaji_pokok, $tunjangan_transport, $tunjangan_makan, $overwork, $tunjangan_jabatan, $bonus_marketing, $insentif_omset, $id_gaji]);
            } else {
                // Insert baru komponen_gaji
                $stmt = $pdo->prepare("INSERT INTO komponen_gaji (register_id, jabatan, gaji_pokok, tunjangan_transport, tunjangan_makan, overwork, tunjangan_jabatan, bonus_marketing, insentif_omset) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$register_id, $posisi, $gaji_pokok, $tunjangan_transport, $tunjangan_makan, $overwork, $tunjangan_jabatan, $bonus_marketing, $insentif_omset]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        header('Location: whitelist.php?success=' . urlencode('Data pegawai berhasil diupdate.'));
        exit;
    } catch (Exception $e) {
        // Rollback jika error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header('Location: edit_pegawai.php?nama=' . urlencode($nama_lengkap) . '&error=' . urlencode('Gagal update: ' . $e->getMessage()));
        exit;
    }
}

// Ambil data pegawai (prioritas gaji dari komponen_gaji, fallback ke whitelist)
$stmt = $pdo->prepare("
    SELECT 
        pw.nama_lengkap, 
        pw.posisi, 
        pw.role, 
        pw.status_registrasi, 
        COALESCE(kg.gaji_pokok, pw.gaji_pokok) as gaji_pokok,
        COALESCE(kg.tunjangan_transport, pw.tunjangan_transport) as tunjangan_transport,
        COALESCE(kg.tunjangan_makan, pw.tunjangan_makan) as tunjangan_makan,
        COALESCE(kg.overwork, pw.overwork) as overwork,
        COALESCE(kg.tunjangan_jabatan, pw.tunjangan_jabatan) as tunjangan_jabatan,
        COALESCE(kg.bonus_marketing, 0) as bonus_marketing,
        COALESCE(kg.insentif_omset, 0) as insentif_omset,
        r.id as register_id
    FROM pegawai_whitelist pw 
    LEFT JOIN register r ON pw.nama_lengkap = r.nama_lengkap
    LEFT JOIN komponen_gaji kg ON r.id = kg.register_id
    WHERE pw.nama_lengkap = ?
");
$stmt->execute([$nama_lengkap]);
$pegawai = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pegawai) {
    header('Location: whitelist.php?error=' . urlencode('Pegawai tidak ditemukan.'));
    exit;
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
    <title>Edit Pegawai - Whitelist</title>
    <link rel="stylesheet" href="style_modern.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            font-size: 14px;
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
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin: 30px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #4CAF50;
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
            background-color: #fff3cd;
            padding: 12px;
            border-left: 4px solid #ffc107;
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
        <h1 style="margin-top:0;">✏️ Edit Data Pegawai</h1>
        
        <?php if ($error): ?>
            <div style="padding:12px; margin-bottom:20px; background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:5px;">
                ✗ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!$pegawai['register_id']): ?>
            <div class="info-box">
                ⚠️ <strong>Perhatian:</strong> Pegawai ini belum terdaftar di sistem (belum punya akun). Komponen gaji hanya bisa diisi setelah pegawai membuat akun.
            </div>
        <?php endif; ?>
        
        <form method="post" action="edit_pegawai.php?nama=<?= urlencode($nama_lengkap) ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="old_nama_lengkap" value="<?= htmlspecialchars($pegawai['nama_lengkap']) ?>">
            
            <div class="section-title">📋 Data Utama</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="nama_lengkap">Nama Lengkap <span style="color:red;">*</span></label>
                    <input type="text" name="nama_lengkap" id="nama_lengkap" required 
                           value="<?= htmlspecialchars($pegawai['nama_lengkap']) ?>">
                </div>
                
                <div class="form-group">
                    <label for="posisi">Posisi/Jabatan <span style="color:red;">*</span></label>
                    <select name="posisi" id="posisi" required onchange="if(this.value==='__buat_baru__'){window.location='posisi_jabatan.php';}">
                        <option value="">-- Pilih Posisi --</option>
                        <?php foreach ($posisi_jabatan as $posisi): ?>
                            <option value="<?= htmlspecialchars($posisi) ?>" <?= ($pegawai['posisi'] == $posisi ? 'selected' : '') ?>>
                                <?= htmlspecialchars($posisi) ?>
                            </option>
                        <?php endforeach; ?>
                        <option value="__buat_baru__">+ Buat/Edit Posisi Baru...</option>
                    </select>
                    <small style="color:#666; display:block; margin-top:5px;">
                        💡 Role akan otomatis disesuaikan dengan posisi
                    </small>
                </div>
            </div>
            
            <div class="section-title">💰 Komponen Gaji</div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="gaji_pokok">Gaji Pokok (Rp)</label>
                    <input type="number" step="0.01" name="gaji_pokok" id="gaji_pokok"
                           value="<?= $pegawai['gaji_pokok'] ?? '' ?>" placeholder="0">
                </div>
                
                <div class="form-group">
                    <label for="tunjangan_transport">Tunjangan Transport (Rp)</label>
                    <input type="number" step="0.01" name="tunjangan_transport"
                           value="<?= $pegawai['tunjangan_transport'] ?? '' ?>" placeholder="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="tunjangan_makan">Tunjangan Makan (Rp)</label>
                    <input type="number" step="0.01" name="tunjangan_makan"
                           value="<?= $pegawai['tunjangan_makan'] ?? '' ?>" placeholder="0">
                </div>
                
                <div class="form-group">
                    <label for="overwork">Overwork (Rp)</label>
                    <input type="number" step="0.01" name="overwork"
                           value="<?= $pegawai['overwork'] ?? '' ?>" placeholder="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="tunjangan_jabatan">Tunjangan Jabatan (Rp)</label>
                    <input type="number" step="0.01" name="tunjangan_jabatan"
                           value="<?= $pegawai['tunjangan_jabatan'] ?? '' ?>" placeholder="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="bonus_marketing">Bonus Marketing (Rp)</label>
                    <input type="number" step="0.01" name="bonus_marketing"
                           value="<?= $pegawai['bonus_marketing'] ?? '' ?>" placeholder="0">
                </div>
                
                <div class="form-group">
                    <label for="insentif_omset">Insentif Omset (Rp)</label>
                    <input type="number" step="0.01" name="insentif_omset"
                           value="<?= $pegawai['insentif_omset'] ?? '' ?>" placeholder="0">
                </div>
            </div>
            
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
                <a href="whitelist.php" class="btn btn-secondary">❌ Batal</a>
            </div>
        </form>
    </div>
</div>

<script>
// Prevent form resubmission
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}
</script>
</body>
</html>
