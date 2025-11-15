<?php
// Script untuk membersihkan data tanda tangan, log absen, dan surat izin
session_start();
include 'connect.php';

// Pastikan hanya superadmin yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: index.php?error=unauthorized');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_clean'])) {
    try {
        $pdo->beginTransaction();

        // 1. Hapus data tanda tangan dari tabel register
        $stmt1 = $pdo->prepare("UPDATE register SET tanda_tangan_file = NULL");
        $stmt1->execute();
        $signature_count = $stmt1->rowCount();

        // 2. Hapus semua data absensi
        $stmt2 = $pdo->prepare("DELETE FROM absensi");
        $stmt2->execute();
        $absensi_count = $stmt2->rowCount();

        // 3. Hapus semua data pengajuan izin
        $stmt3 = $pdo->prepare("DELETE FROM pengajuan_izin");
        $stmt3->execute();
        $izin_count = $stmt3->rowCount();

        $pdo->commit();

        $message = "✅ Data berhasil dibersihkan:<br>" .
                  "- Tanda tangan: $signature_count record<br>" .
                  "- Log absensi: $absensi_count record<br>" .
                  "- Surat izin: $izin_count record";

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ Error: " . $e->getMessage();
    }
}

// Hitung total data sebelum cleanup
try {
    $signature_total = $pdo->query("SELECT COUNT(*) FROM register WHERE tanda_tangan_file IS NOT NULL")->fetchColumn();
    $absensi_total = $pdo->query("SELECT COUNT(*) FROM absensi")->fetchColumn();
    $izin_total = $pdo->query("SELECT COUNT(*) FROM pengajuan_izin")->fetchColumn();
} catch (Exception $e) {
    $signature_total = $absensi_total = $izin_total = 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clean Database - KAORI HR</title>
    <link rel="stylesheet" href="style_modern.css">
    <style>
        .clean-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .data-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .data-summary h3 {
            margin-top: 0;
            color: #495057;
        }
        .confirm-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .confirm-btn:hover {
            background: #c82333;
        }
        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-left: 10px;
        }
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="clean-container">
        <h1>🧹 Clean Database</h1>
        <p>Halaman ini digunakan untuk membersihkan data tanda tangan, log absensi, dan surat izin dari database.</p>

        <?php if ($message): ?>
            <div class="success-message">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="warning-box">
            <strong>⚠️ PERINGATAN:</strong> Tindakan ini akan menghapus semua data berikut secara permanen:
            <ul>
                <li>Data tanda tangan dari profil pengguna</li>
                <li>Semua log absensi (masuk/keluar)</li>
                <li>Semua data pengajuan izin/surat izin</li>
            </ul>
            <strong>Data yang dihapus tidak dapat dikembalikan!</strong>
        </div>

        <div class="data-summary">
            <h3>📊 Ringkasan Data Saat Ini</h3>
            <ul>
                <li><strong>Tanda tangan:</strong> <?php echo $signature_total; ?> record</li>
                <li><strong>Log absensi:</strong> <?php echo $absensi_total; ?> record</li>
                <li><strong>Surat izin:</strong> <?php echo $izin_total; ?> record</li>
            </ul>
        </div>

        <?php if ($signature_total > 0 || $absensi_total > 0 || $izin_total > 0): ?>
            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus semua data ini? Tindakan ini tidak dapat dibatalkan!')">
                <input type="hidden" name="confirm_clean" value="1">
                <button type="submit" class="confirm-btn">🗑️ Hapus Semua Data</button>
                <a href="mainpage.php" class="cancel-btn">❌ Batal</a>
            </form>
        <?php else: ?>
            <p style="color: #6c757d; font-style: italic;">Tidak ada data yang perlu dibersihkan.</p>
            <a href="mainpage.php" class="cancel-btn">← Kembali ke Dashboard</a>
        <?php endif; ?>
    </div>

    <script>
        // Auto refresh data summary every 5 seconds
        setInterval(function() {
            location.reload();
        }, 5000);
    </script>
</body>
</html>