<?php
session_start();
include 'connect.php';

// Cek apakah user adalah superadmin
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

// Proses import database
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $confirmation = $_POST['confirmation_text'] ?? '';
    
    if (strtoupper($confirmation) === 'HAPUS SEMUA DATA') {
        try {
            // Path ke file SQL
            $sql_file = __DIR__ . '/aplikasi.sql';
            
            if (!file_exists($sql_file)) {
                throw new Exception("File aplikasi.sql tidak ditemukan!");
            }
            
            // Baca file SQL
            $sql_content = file_get_contents($sql_file);
            
            if ($sql_content === false) {
                throw new Exception("Gagal membaca file aplikasi.sql!");
            }
            
            // Log aktivitas import
            error_log("🚨 IMPORT DATABASE DIMULAI oleh user: " . $_SESSION['username']);
            error_log("📅 Waktu: " . date('Y-m-d H:i:s'));
            
            // Nonaktifkan foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            
            // Split query dan eksekusi satu per satu
            $queries = array_filter(
                array_map('trim', 
                    explode(';', $sql_content)
                ),
                function($query) {
                    return !empty($query) && 
                           !preg_match('/^\/\*.*\*\/$/s', $query) && 
                           !preg_match('/^--/', $query);
                }
            );
            
            $success_count = 0;
            $error_count = 0;
            
            foreach ($queries as $query) {
                $query = trim($query);
                if (!empty($query)) {
                    if ($conn->query($query) === TRUE) {
                        $success_count++;
                    } else {
                        $error_count++;
                        error_log("❌ Error query: " . $conn->error);
                    }
                }
            }
            
            // Aktifkan kembali foreign key checks
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            
            error_log("✅ IMPORT SELESAI - Sukses: $success_count, Error: $error_count");
            
            $message = "✅ Database berhasil di-import! ($success_count query berhasil, $error_count error)";
            $message_type = 'success';
            
        } catch (Exception $e) {
            error_log("❌ ERROR IMPORT: " . $e->getMessage());
            $message = "❌ Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "❌ Konfirmasi salah! Ketik 'HAPUS SEMUA DATA' untuk melanjutkan.";
        $message_type = 'error';
    }
}

// Hitung jumlah data yang akan terhapus
$stats = [];
try {
    $stats['users'] = $conn->query("SELECT COUNT(*) as count FROM register")->fetch_assoc()['count'];
    $stats['absensi'] = $conn->query("SELECT COUNT(*) as count FROM absensi")->fetch_assoc()['count'];
    $stats['whitelist'] = $conn->query("SELECT COUNT(*) as count FROM pegawai_whitelist")->fetch_assoc()['count'];
    $stats['izin'] = $conn->query("SELECT COUNT(*) as count FROM pengajuan_izin")->fetch_assoc()['count'];
} catch (Exception $e) {
    $stats = ['users' => 0, 'absensi' => 0, 'whitelist' => 0, 'izin' => 0];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Database - WARNING</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        
        .warning-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .warning-icon {
            font-size: 80px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        
        h1 {
            color: #dc2626;
            font-size: 28px;
            margin: 20px 0 10px;
        }
        
        .subtitle {
            color: #666;
            font-size: 16px;
        }
        
        .warning-box {
            background: #fee;
            border: 2px solid #dc2626;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .warning-box h3 {
            color: #dc2626;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-list {
            list-style: none;
            padding: 0;
        }
        
        .warning-list li {
            padding: 10px 0;
            border-bottom: 1px solid #fcc;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-list li:last-child {
            border-bottom: none;
        }
        
        .stats-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .stats-box h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            font-weight: 600;
            color: #555;
        }
        
        .stat-value {
            font-weight: bold;
            color: #dc2626;
        }
        
        .confirmation-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .confirmation-box label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }
        
        .confirmation-box input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
            text-transform: uppercase;
        }
        
        .confirmation-box input:focus {
            outline: none;
            border-color: #dc2626;
        }
        
        .confirmation-box p {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
            text-align: center;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        
        .btn-danger:hover:not(:disabled) {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        
        .btn-danger:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(107, 114, 128, 0.3);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            border: 2px solid #10b981;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee;
            border: 2px solid #dc2626;
            color: #991b1b;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="warning-header">
            <div class="warning-icon">⚠️</div>
            <h1>PERINGATAN KERAS!</h1>
            <p class="subtitle">Import Database akan Menghapus SEMUA Data</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <span style="font-size: 20px;"><?php echo $message_type === 'success' ? '✅' : '❌'; ?></span>
                <span><?php echo $message; ?></span>
            </div>
        <?php endif; ?>

        <div class="warning-box">
            <h3>
                <span>🚨</span>
                <span>Yang Akan Terjadi:</span>
            </h3>
            <ul class="warning-list">
                <li>
                    <span>❌</span>
                    <span>Semua user yang baru terdaftar akan <strong>TERHAPUS</strong></span>
                </li>
                <li>
                    <span>❌</span>
                    <span>Semua data absensi akan <strong>TERHAPUS</strong></span>
                </li>
                <li>
                    <span>❌</span>
                    <span>Semua data izin akan <strong>TERHAPUS</strong></span>
                </li>
                <li>
                    <span>❌</span>
                    <span>Database akan kembali ke kondisi <strong>BACKUP LAMA</strong></span>
                </li>
                <li>
                    <span>⚠️</span>
                    <span>Proses ini <strong>TIDAK BISA DIBATALKAN</strong></span>
                </li>
            </ul>
        </div>

        <div class="stats-box">
            <h3>📊 Data yang Akan Terhapus:</h3>
            <div class="stat-item">
                <span class="stat-label">👥 Total User Terdaftar</span>
                <span class="stat-value"><?php echo $stats['users']; ?> user</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">📋 Total Data Absensi</span>
                <span class="stat-value"><?php echo $stats['absensi']; ?> record</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">📝 Total Data Whitelist</span>
                <span class="stat-value"><?php echo $stats['whitelist']; ?> pegawai</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">📄 Total Pengajuan Izin</span>
                <span class="stat-value"><?php echo $stats['izin']; ?> izin</span>
            </div>
        </div>

        <form method="POST" id="importForm">
            <div class="confirmation-box">
                <label for="confirmation_text">
                    💬 Ketik <strong>"HAPUS SEMUA DATA"</strong> untuk melanjutkan:
                </label>
                <input 
                    type="text" 
                    id="confirmation_text" 
                    name="confirmation_text" 
                    placeholder="Ketik: HAPUS SEMUA DATA"
                    required
                    autocomplete="off"
                    oninput="checkConfirmation()"
                >
                <p>⚠️ Harus ketik persis seperti di atas (huruf besar semua)</p>
            </div>

            <div class="button-group">
                <a href="mainpageadmin.php" class="btn btn-secondary">
                    <span>🔙</span>
                    <span>Batal</span>
                </a>
                <button type="submit" name="confirm_import" class="btn btn-danger" id="importBtn" disabled>
                    <span>⚠️</span>
                    <span>Import Database</span>
                </button>
            </div>
        </form>

        <div class="back-link">
            <p style="margin-bottom: 10px;">💡 <strong>Rekomendasi:</strong></p>
            <p>Jika ingin backup data saat ini, gunakan:</p>
            <a href="#" onclick="runBackup(); return false;">📦 Backup Database Dulu</a>
        </div>
    </div>

    <script>
        function checkConfirmation() {
            const input = document.getElementById('confirmation_text');
            const btn = document.getElementById('importBtn');
            
            if (input.value === 'HAPUS SEMUA DATA') {
                btn.disabled = false;
                btn.style.cursor = 'pointer';
            } else {
                btn.disabled = true;
                btn.style.cursor = 'not-allowed';
            }
        }

        function runBackup() {
            if (confirm('Buka terminal dan jalankan script backup?')) {
                alert('Silakan jalankan:\n\n./backup_database.sh\n\ndi terminal Anda.');
            }
        }

        // Konfirmasi sekali lagi sebelum submit
        document.getElementById('importForm').addEventListener('submit', function(e) {
            const confirmed = confirm(
                '🚨 KONFIRMASI TERAKHIR 🚨\n\n' +
                'Anda yakin ingin menghapus SEMUA data dan import ulang database?\n\n' +
                'Data yang terhapus TIDAK BISA dikembalikan!\n\n' +
                'Klik OK untuk melanjutkan, atau Cancel untuk membatalkan.'
            );
            
            if (!confirmed) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
