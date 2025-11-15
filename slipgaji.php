<?php
/**
 * Slip Gaji - User View Only
 * This page is for regular users (non-admin) to view their salary history.
 * Admins should use slip_gaji_management.php for full management features.
 */
session_start();
require_once 'connect.php';

// Fungsi bantu untuk mendapatkan nama bulan
function getNamaBulan($bulan) {
    $namaBulan = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 
                  7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
    return $namaBulan[(int)$bulan] ?? 'Bulan?';
}

// 1. Keamanan: Cek Login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=notloggedin');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'];

// 2. Redirect admin to management page
if ($current_user_role === 'admin') {
    header('Location: slip_gaji_management.php');
    exit;
}

// 3. For regular users, only show their own salary history
$user_id_to_view = $current_user_id;

// 4. Get user's salary history
$sql_riwayat = "
    SELECT rg.*, r.nama_lengkap 
    FROM riwayat_gaji rg 
    JOIN register r ON rg.register_id = r.id 
    WHERE rg.register_id = ? 
    ORDER BY rg.periode_tahun DESC, rg.periode_bulan DESC
";
$stmt_riwayat = $pdo->prepare($sql_riwayat);
$stmt_riwayat->execute([$user_id_to_view]);
$riwayat_gaji = $stmt_riwayat->fetchAll(PDO::FETCH_ASSOC);

// 5. Get user details
$stmt_user = $pdo->prepare("SELECT nama_lengkap FROM register WHERE id = ?");
$stmt_user->execute([$current_user_id]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
$user_name = $user_data['nama_lengkap'] ?? $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Slip Gaji</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .info-notice {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-notice h3 {
            margin-top: 0;
            color: #1976D2;
        }
        .salary-summary {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .salary-summary h3 {
            margin-top: 0;
            color: #333;
        }
        .btn-download {
            background: #4CAF50;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
            display: inline-block;
            transition: background 0.3s;
        }
        .btn-download:hover {
            background: #45a049;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    </div>
    <div class="main-title">Teman KAORI</div>
    <div class="subtitle-container">
        <p class="subtitle">Selamat Datang, <?php echo htmlspecialchars($user_name); ?></p>
    </div>

    <div class="content-container">
        <h2><i class="fas fa-receipt"></i> Riwayat Slip Gaji Saya</h2>
        
        <div class="info-notice">
            <h3><i class="fas fa-info-circle"></i> Informasi</h3>
            <p>
                • Slip gaji otomatis di-generate setiap tanggal 28 untuk periode 26 hari kerja sebelumnya.<br>
                • Jika ada pertanyaan tentang komponen gaji, silakan hubungi HR atau Admin.<br>
                • Slip gaji mencakup: Gaji Pokok, Tunjangan, Overwork, serta Potongan (keterlambatan, absensi, dll).
            </p>
        </div>

        <?php if (!empty($riwayat_gaji)): 
            $latest = $riwayat_gaji[0];
        ?>
        <div class="salary-summary">
            <h3><i class="fas fa-chart-line"></i> Slip Gaji Terakhir</h3>
            <p>
                <strong>Periode:</strong> <?php echo getNamaBulan($latest['periode_bulan']) . ' ' . $latest['periode_tahun']; ?><br>
                <strong>Gaji Bersih (THP):</strong> <span style="color: #4CAF50; font-size: 1.2em; font-weight: bold;">Rp <?php echo number_format($latest['gaji_bersih'], 0, ',', '.'); ?></span>
            </p>
        </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Gaji Bersih (THP)</th>
                        <th>Hadir</th>
                        <th>Telat</th>
                        <th>Tidak Hadir</th>
                        <th>Overwork</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($riwayat_gaji)): ?>
                        <tr>
                            <td colspan="7" class="no-data">
                                <i class="fas fa-inbox" style="font-size: 3em; color: #ccc; display: block; margin-bottom: 10px;"></i>
                                <p>Belum ada riwayat slip gaji.</p>
                                <p style="font-size: 0.9em; color: #999;">Slip gaji akan muncul setelah periode gajian pertama Anda.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($riwayat_gaji as $riwayat): ?>
                            <tr>
                                <td><strong><?php echo getNamaBulan($riwayat['periode_bulan']) . ' ' . $riwayat['periode_tahun']; ?></strong></td>
                                <td style="color: #4CAF50; font-weight: bold;">Rp <?php echo number_format($riwayat['gaji_bersih'], 0, ',', '.'); ?></td>
                                <td><?php echo $riwayat['jumlah_hadir']; ?> hari</td>
                                <td><?php echo $riwayat['jumlah_terlambat']; ?> kali</td>
                                <td><?php echo $riwayat['jumlah_tidak_hadir']; ?> hari</td>
                                <td>Rp <?php echo number_format($riwayat['overwork'], 0, ',', '.'); ?></td>
                                <td>
                                    <?php if (!empty($riwayat['file_slip_gaji'])): ?>
                                        <a href="<?php echo htmlspecialchars($riwayat['file_slip_gaji']); ?>" 
                                           class="btn-download" 
                                           download>
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 0.9em;">Tidak tersedia</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
<footer>
    <div class="footer-container">
        <p class="footer-text">© 2024 KAORI Indonesia. All rights reserved.</p>
    </div>
</footer>
</html>