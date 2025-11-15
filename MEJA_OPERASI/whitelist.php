<?php
// Start output buffering untuk memastikan redirect berjalan lancar
ob_start();

// Endpoint AJAX cek whitelist (harus di paling atas, sebelum session_start)
if (isset($_GET['check']) && isset($_GET['nama'])) {
    include 'connect.php';
    $nama = trim($_GET['nama']);
    $stmt = $pdo->prepare("SELECT nama_lengkap, posisi, role, status_registrasi FROM pegawai_whitelist WHERE nama_lengkap = ?");
    $stmt->execute([$nama]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['status_registrasi'] !== 'terdaftar') {
        ob_end_clean();
        echo json_encode([
            'found' => true,
            'nama_lengkap' => $row['nama_lengkap'],
            'posisi' => $row['posisi'],
            'role' => $row['role'] ?? 'user',
            'status' => $row['status_registrasi']
        ]);
    } else {
        ob_end_clean();
        echo json_encode(['found' => false]);
    }
    exit;
}

session_start();
include 'connect.php';
include 'functions_role.php'; // Central role function

if (!isset($_SESSION['user_id']) || !isAdminOrSuperadmin($_SESSION['role'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// Generate CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handler hapus via GET (dengan CSRF protection + CASCADE DELETE)
if (isset($_GET['hapus_nama']) && isset($_GET['csrf'])) {
    if ($_GET['csrf'] === $_SESSION['csrf_token']) {
        $hapus_nama = trim($_GET['hapus_nama']);
        if ($hapus_nama !== '') {
            try {
                // Mulai transaction untuk atomic operation
                $pdo->beginTransaction();
                
                // 1. Ambil data user untuk hapus file terkait
                $stmt = $pdo->prepare("SELECT id, foto_profil, tanda_tangan_file FROM register WHERE nama_lengkap = ?");
                $stmt->execute([$hapus_nama]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 2. Hapus file foto profil jika ada
                if ($user_data && !empty($user_data['foto_profil']) && $user_data['foto_profil'] != 'default.png') {
                    $foto_path = 'uploads/foto_profil/' . $user_data['foto_profil'];
                    if (file_exists($foto_path)) @unlink($foto_path);
                }
                
                // 3. Hapus file tanda tangan jika ada
                if ($user_data && !empty($user_data['tanda_tangan_file'])) {
                    $ttd_path = 'uploads/tanda_tangan/' . $user_data['tanda_tangan_file'];
                    if (file_exists($ttd_path)) @unlink($ttd_path);
                }
                
                // 4. Hapus dari tabel register (akun user)
                if ($user_data) {
                    $stmt = $pdo->prepare("DELETE FROM register WHERE nama_lengkap = ?");
                    $stmt->execute([$hapus_nama]);
                }
                
                // 5. Hapus dari tabel pegawai_whitelist
                $stmt = $pdo->prepare("DELETE FROM pegawai_whitelist WHERE nama_lengkap = ?");
                $stmt->execute([$hapus_nama]);
                
                // 6. Hapus dari tabel komponen_gaji jika ada
                $stmt = $pdo->prepare("DELETE FROM komponen_gaji WHERE jabatan = ?");
                $stmt->execute([$hapus_nama]);
                
                // Commit transaction
                $pdo->commit();
                
                $message = $user_data 
                    ? 'Pegawai dan akun berhasil dihapus.' 
                    : 'Pegawai berhasil dihapus dari whitelist.';
                header('Location: whitelist.php?success=' . urlencode($message));
                exit;
            } catch (PDOException $e) {
                // Rollback jika error
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                header('Location: whitelist.php?error=' . urlencode('Gagal menghapus pegawai: ' . $e->getMessage()));
                exit;
            }
        } else {
            header('Location: whitelist.php?error=' . urlencode('Nama pegawai tidak valid.'));
            exit;
        }
    } else {
        header('Location: whitelist.php?error=' . urlencode('Invalid CSRF token.'));
        exit;
    }
}

// Tangkap feedback dari URL (PRG Pattern)
$success = '';
$error = '';
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enhanced Debug logging
    error_log("=== WHITELIST POST RECEIVED ===");
    error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST keys: " . implode(', ', array_keys($_POST)));
    error_log("POST action buttons: " . (isset($_POST['import']) ? 'IMPORT ' : '') . (isset($_POST['edit']) ? 'EDIT ' : '') . (isset($_POST['tambah']) ? 'TAMBAH' : ''));
    error_log("POST csrf_token: " . (isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 20) . '... (length: ' . strlen($_POST['csrf_token']) . ')' : 'NOT SET'));
    error_log("SESSION csrf_token: " . (isset($_SESSION['csrf_token']) ? substr($_SESSION['csrf_token'], 0, 20) . '... (length: ' . strlen($_SESSION['csrf_token']) . ')' : 'NOT SET'));
    error_log("Token match: " . ((isset($_POST['csrf_token']) && isset($_SESSION['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) ? 'YES' : 'NO'));
    
    // Validasi CSRF token untuk mencegah duplicate submission
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("❌ CSRF validation failed in whitelist.php!");
        error_log("Reason: " . (!isset($_POST['csrf_token']) ? 'Token not posted' : 'Token mismatch'));
        error_log("Redirecting with error...");
        header('Location: whitelist.php?error=' . urlencode('Invalid request. Please try again.'));
        exit;
    }
    
    error_log("✅ CSRF validation passed in whitelist.php");
    
    // JANGAN regenerate token di sini - biarkan token tetap sama selama session
    // Token hanya akan berubah saat user logout atau session expire
    
    // All POST handlers removed - use dedicated pages:
    // - tambah_pegawai.php for adding new employees
    // - edit_pegawai.php for editing existing employees
    // - import_whitelist.php for importing data
    
    if (isset($_POST['hapus'])) {
        // Handler hapus pegawai
        error_log("HAPUS HANDLER: hapus=" . (isset($_POST['hapus']) ? $_POST['hapus'] : 'NOT SET'));
        error_log("HAPUS HANDLER: hapus_nama=" . ($_POST['hapus_nama'] ?? 'NOT SET'));
        
        $hapus_nama = trim($_POST['hapus_nama'] ?? '');
        if ($hapus_nama === '') {
            error_log("HAPUS HANDLER: Nama kosong, redirecting dengan error");
            header('Location: whitelist.php?error=' . urlencode('Nama pegawai tidak valid atau kosong.'));
            exit;
        }
        try {
            error_log("HAPUS HANDLER: Mencoba hapus: " . $hapus_nama);
            $stmt = $pdo->prepare("DELETE FROM pegawai_whitelist WHERE nama_lengkap = ?");
            $stmt->execute([$hapus_nama]);
            error_log("HAPUS HANDLER: Berhasil hapus, redirecting dengan success");
            header('Location: whitelist.php?success=' . urlencode('Pegawai berhasil dihapus dari whitelist.'));
            exit;
        } catch (PDOException $e) {
            error_log("HAPUS HANDLER: Gagal hapus: " . $e->getMessage());
            header('Location: whitelist.php?error=' . urlencode('Gagal menghapus pegawai: ' . $e->getMessage()));
            exit;
        }
    } else {
        // Catch-all untuk POST yang tidak dikenali
        error_log("CATCH-ALL: POST tidak dikenali!");
        error_log("CATCH-ALL: isset edit=" . (isset($_POST['edit']) ? 'YES' : 'NO'));
        error_log("CATCH-ALL: isset hapus=" . (isset($_POST['hapus']) ? 'YES' : 'NO'));
        header('Location: whitelist.php?error=' . urlencode('Invalid request. Please try again.'));
        exit;
    }
}

// Tambahkan handler PHP untuk tambah posisi baru
if (isset($_POST['buat_posisi']) && !empty($_POST['nama_posisi_baru'])) {
    $nama_posisi_baru = trim($_POST['nama_posisi_baru']);
    if ($nama_posisi_baru !== '') {
        // Cek duplikat
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM posisi_jabatan WHERE nama_posisi = ?");
        $stmt->execute([$nama_posisi_baru]);
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO posisi_jabatan (nama_posisi) VALUES (?)");
            $stmt->execute([$nama_posisi_baru]);
            header('Location: whitelist.php?success=' . urlencode('Posisi baru berhasil ditambahkan.'));
            exit;
        } else {
            header('Location: whitelist.php?error=' . urlencode('Posisi sudah ada.'));
            exit;
        }
    }
}

// Ambil data whitelist + gaji (prioritas dari komponen_gaji, fallback ke whitelist)
$data = $pdo->query("
    SELECT
        pw.nama_lengkap,
        pw.posisi,
        pw.role,
        pw.status_registrasi,
        kg.jabatan,
        COALESCE(kg.gaji_pokok, pw.gaji_pokok) as gaji_pokok,
        COALESCE(kg.tunjangan_transport, pw.tunjangan_transport) as tunjangan_transport,
        COALESCE(kg.tunjangan_makan, pw.tunjangan_makan) as tunjangan_makan,
        COALESCE(kg.overwork, pw.overwork) as overwork,
        COALESCE(kg.tunjangan_jabatan, pw.tunjangan_jabatan) as tunjangan_jabatan,
        0 as bonus_marketing,
        0 as insentif_omset,
        r.id as register_id
    FROM pegawai_whitelist pw
    LEFT JOIN register r ON pw.nama_lengkap = r.nama_lengkap
    LEFT JOIN komponen_gaji kg ON r.id = kg.register_id
    ORDER BY pw.nama_lengkap ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar posisi jabatan
$posisi_jabatan = $pdo->query("SELECT nama_posisi FROM posisi_jabatan ORDER BY nama_posisi ASC")->fetchAll(PDO::FETCH_COLUMN);

// Function untuk format Rupiah
function formatRupiah($angka) {
    if ($angka === null || $angka === '') {
        return '-';
    }
    // Jika 0, tampilkan Rp 0 (bukan tanda -)
    if ($angka == 0) {
        return 'Rp 0';
    }
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whitelist Pegawai</title>
    <link rel="stylesheet" href="style_modern.css">
    <style>
        /* Style untuk search box */
        #searchInput {
            width: 100%;
            max-width: 500px;
            padding: 10px 15px;
            font-size: 14px;
            border: 2px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s;
        }
        #searchInput:focus {
            outline: none;
            border-color: #4CAF50;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
        }
        /* Highlight untuk baris tabel */
        .data-row:hover {
            background-color: #f5f5f5;
        }
        /* Style untuk tabel yang lebih rapi */
        #whitelistTable {
            width: 100%;
            border-collapse: collapse;
        }
        #whitelistTable th {
            background-color: #4CAF50;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
        }
        #whitelistTable td {
            padding: 10px 8px;
            border-bottom: 1px solid #ddd;
        }
        /* Format untuk nilai Rupiah */
        td[data-currency="true"] {
            text-align: right;
            font-family: 'Courier New', monospace;
            color: #2e7d32;
            font-weight: 500;
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
    <h1>Whitelist Pegawai</h1>
    <?php if ($success): ?>
        <div style="padding:12px; margin-bottom:20px; background-color:#d4edda; color:#155724; border:1px solid #c3e6cb; border-radius:5px;">
            ✓ <?= $success ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="padding:12px; margin-bottom:20px; background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb; border-radius:5px;">
            ✗ <?= $error ?>
        </div>
    <?php endif; ?>
    <!-- Action Buttons -->
    <div style="display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap;">
        <a href="tambah_pegawai.php" style="display:inline-block; padding:12px 24px; background-color:#4CAF50; color:white; text-decoration:none; border-radius:5px; font-weight:bold;">
            ➕ Tambah Pegawai
        </a>
        <a href="import_whitelist.php" style="display:inline-block; padding:12px 24px; background-color:#2196F3; color:white; text-decoration:none; border-radius:5px; font-weight:bold;">
            📥 Import Data
        </a>
        <a href="posisi_jabatan.php" style="display:inline-block; padding:12px 24px; background-color:#FF9800; color:white; text-decoration:none; border-radius:5px; font-weight:bold;">
            🏢 Kelola Posisi
        </a>
    </div>
    <h2>Daftar Whitelist</h2>
    <!-- Filter/Search Box -->
    <div style="margin-bottom: 15px;">
        <input type="text" id="searchInput" placeholder="🔍 Cari di semua kolom..." 
               style="width: 100%; max-width: 500px; padding: 10px; font-size: 14px; border: 2px solid #ddd; border-radius: 5px;">
    </div>
    <table border="1" cellpadding="6" cellspacing="0" id="whitelistTable">
        <thead>
            <tr>
                <th>Nama Lengkap</th>
                <th>Posisi</th>
                <th>Role</th>
                <th>Status Registrasi</th>
                <th>Gaji Pokok</th>
                <th>Tunjangan Transport</th>
                <th>Tunjangan Makan</th>
                <th>Overwork</th>
                <th>Tunjangan Jabatan</th>
                <th>Bonus Marketing</th>
                <th>Insentif Omset</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data as $row): ?>
        <tr class="data-row">
            <td><?= htmlspecialchars($row['nama_lengkap']) ?></td>
            <td><?= htmlspecialchars($row['posisi']) ?></td>
            <td><?= htmlspecialchars($row['role'] ?? 'user') ?></td>
            <td><?= htmlspecialchars($row['status_registrasi']) ?></td>
            <td><?= formatRupiah($row['gaji_pokok']) ?></td>
            <td><?= formatRupiah($row['tunjangan_transport']) ?></td>
            <td><?= formatRupiah($row['tunjangan_makan']) ?></td>
            <td><?= formatRupiah($row['overwork']) ?></td>
            <td><?= formatRupiah($row['tunjangan_jabatan']) ?></td>
            <td><?= formatRupiah($row['bonus_marketing']) ?></td>
            <td><?= formatRupiah($row['insentif_omset']) ?></td>
            <td>
                <a href="edit_pegawai.php?nama=<?=urlencode($row['nama_lengkap'])?>"
                   style="color:#2196F3; text-decoration:none; font-weight:bold;">✏️ Edit</a> |
                <a href="whitelist.php?hapus_nama=<?=urlencode($row['nama_lengkap'])?>&csrf=<?=$_SESSION['csrf_token']?>"
                   onclick="return confirm('Yakin hapus pegawai <?=htmlspecialchars($row['nama_lengkap'])?>?\n\nData yang akan dihapus:\n- Data whitelist\n- Akun user (jika sudah terdaftar)\n- Komponen gaji\n- File foto dan tanda tangan');"
                   style="color:red; text-decoration:none; font-weight:bold;">🗑️ Hapus</a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<script>
// Prevent form resubmission on refresh
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

// ===========================================
// FILTER/SEARCH FUNCTIONALITY
// ===========================================
document.getElementById('searchInput').addEventListener('keyup', function() {
    var searchValue = this.value.toLowerCase().trim();
    var table = document.getElementById('whitelistTable');
    var rows = table.getElementsByClassName('data-row');
    var visibleCount = 0;
    
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var cells = row.getElementsByTagName('td');
        var found = false;
        
        // Search in all cells except the last one (Aksi column)
        for (var j = 0; j < cells.length - 1; j++) {
            var cellText = cells[j].textContent || cells[j].innerText;
            if (cellText.toLowerCase().indexOf(searchValue) > -1) {
                found = true;
                break;
            }
        }
        
        if (found || searchValue === '') {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Optional: Show message if no results found
    var noResultsRow = document.getElementById('noResultsRow');
    if (noResultsRow) {
        noResultsRow.remove();
    }
    
    if (visibleCount === 0 && searchValue !== '') {
        var tbody = table.getElementsByTagName('tbody')[0];
        var newRow = tbody.insertRow();
        newRow.id = 'noResultsRow';
        var cell = newRow.insertCell(0);
        cell.colSpan = 12;
        cell.innerHTML = '<em style="color: #999;">Tidak ada data yang cocok dengan pencarian "' + searchValue + '"</em>';
        cell.style.textAlign = 'center';
        cell.style.padding = '20px';
    }
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>
