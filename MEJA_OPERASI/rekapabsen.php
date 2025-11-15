<?php
// Mulai output buffering untuk mencegah output yang tidak diinginkan
ob_start();

session_start();
include 'connect.php'; // Pastikan nama file koneksi Anda benar

// Define flag untuk prevent CLI code execution di helper file
define('INCLUDED_FROM_WEB', true);
include 'calculate_status_kehadiran.php'; // Helper untuk hitung status kehadiran

// 1. Cek Login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=notloggedin');
    exit;
}
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? $_SESSION['username'];

// 2. Siapkan variabel untuk hasil query
$daftar_absensi = [];
$sql = ""; // Inisialisasi string SQL

// 3. Query data absensi dengan JOIN ke cabang untuk mendapatkan jam shift
// Asumsi: Semua user menggunakan cabang dengan id = 1 (sesuaikan jika berbeda)
$sql = "SELECT 
            a.*,
            c.jam_masuk,
            c.jam_keluar
        FROM absensi a
        LEFT JOIN cabang c ON c.id = 1
        LEFT JOIN register r ON a.user_id = r.id
        WHERE a.user_id = ? 
        ORDER BY a.tanggal_absensi DESC, a.waktu_masuk DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$daftar_absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Hitung status kehadiran untuk setiap record (real-time calculation)
foreach ($daftar_absensi as &$absen) {
    $absen['status_kehadiran_calculated'] = hitungStatusKehadiran($absen, $pdo);
}
// Tidak perlu tutup $pdo di sini
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <title>Rekap Absensi</title>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="main-title">Teman KAORI</div>
    <div class="subtitle-container">
        <p class="subtitle">Selamat Datang, <?php echo htmlspecialchars($nama_pengguna); ?> [<?php echo htmlspecialchars($user_role); ?>]</p>
    </div>
    <div class="content-container">
        <h2>Rekap Absensi</h2>
        <table class="rekap-table">
            <thead>
                <tr>
                    <th>Tanggal Absensi</th>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Status Lokasi</th>
                    <th>Foto Absen Masuk</th>
                    <th>Foto Absen Keluar</th>
                    <th>Status Keterlambatan</th>
                    <th>Potongan Tunjangan</th>
                    <th>Status Kehadiran</th>
                    <th>Status Overwork</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($daftar_absensi)): ?>
                    <tr>
                        <td colspan="10">Tidak ada data absensi.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($daftar_absensi as $absen): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($absen['tanggal_absensi']); ?></td>
                            <td><?php echo htmlspecialchars(date('H:i', strtotime($absen['waktu_masuk']))); ?></td>
                            <td><?php echo htmlspecialchars($absen['waktu_keluar'] ? date('H:i', strtotime($absen['waktu_keluar'])) : '-'); ?></td>
                            <td><?php echo htmlspecialchars($absen['status_lokasi']); ?></td>
                            <!-- Foto Absen Masuk -->
                            <td>
                                <?php
                                $foto_masuk = $absen['foto_absen_masuk'] ?? '';
                                if (!empty($foto_masuk)) {
                                    // Fix: Ambil nama user yang tepat dan sanitize untuk folder
                                    $nama_user_query = "SELECT nama_lengkap FROM register WHERE id = ?";
                                    $stmt_nama = $pdo->prepare($nama_user_query);
                                    $stmt_nama->execute([$absen['user_id']]);
                                    $user_data = $stmt_nama->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($user_data) {
                                        $nama_user = strtolower(str_replace(' ', '_', $user_data['nama_lengkap']));
                                        $path_foto_masuk = 'uploads/absensi/foto_masuk/' . $nama_user . '/' . $foto_masuk;
                                        
                                        if (file_exists($path_foto_masuk)) {
                                            echo '<a href="' . $path_foto_masuk . '" target="_blank">';
                                            echo '<img src="' . $path_foto_masuk . '" alt="Foto Absen Masuk" class="foto-absen" style="max-width: 60px; height: auto; cursor: pointer;">';
                                            echo '</a>';
                                        } else {
                                            echo '<span style="color: #999;">(File tidak ditemukan)</span>';
                                            echo '<br><small style="color: #999;">Path: ' . htmlspecialchars($path_foto_masuk) . '</small>';
                                        }
                                    } else {
                                        echo '<span style="color: #999;">(Data user tidak ditemukan)</span>';
                                    }
                                } else {
                                    echo '<span style="color: #999;">-</span>';
                                }
                                ?>
                            </td>
                            <!-- Foto Absen Keluar -->
                            <td>
                                <?php
                                $foto_keluar = $absen['foto_absen_keluar'] ?? '';
                                if (!empty($foto_keluar)) {
                                    // Fix: Ambil nama user yang tepat dan sanitize untuk folder
                                    $nama_user_query = "SELECT nama_lengkap FROM register WHERE id = ?";
                                    $stmt_nama = $pdo->prepare($nama_user_query);
                                    $stmt_nama->execute([$absen['user_id']]);
                                    $user_data = $stmt_nama->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($user_data) {
                                        $nama_user = strtolower(str_replace(' ', '_', $user_data['nama_lengkap']));
                                        $path_foto_keluar = 'uploads/absensi/foto_keluar/' . $nama_user . '/' . $foto_keluar;
                                        
                                        if (file_exists($path_foto_keluar)) {
                                            echo '<a href="' . $path_foto_keluar . '" target="_blank">';
                                            echo '<img src="' . $path_foto_keluar . '" alt="Foto Absen Keluar" class="foto-absen" style="max-width: 60px; height: auto; cursor: pointer;">';
                                            echo '</a>';
                                        } else {
                                            echo '<span style="color: #999;">(File tidak ditemukan)</span>';
                                            echo '<br><small style="color: #999;">Path: ' . htmlspecialchars($path_foto_keluar) . '</small>';
                                        }
                                    } else {
                                        echo '<span style="color: #999;">(Data user tidak ditemukan)</span>';
                                    }
                                } else {
                                    echo '<span style="color: #999;">-</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Status Keterlambatan dengan detail dan warna
                                $menit_terlambat = isset($absen['menit_terlambat']) ? (int)$absen['menit_terlambat'] : 0;
                                $status_ket = $absen['status_keterlambatan'] ?? 'tepat waktu';
                                
                                if ($status_ket == 'di luar shift') {
                                    // Absen di luar range shift
                                    echo '<span style="color: purple; font-weight: bold;">⚠ DI LUAR SHIFT</span><br>';
                                    echo '<small style="color: gray;">(Absen ' . abs($menit_terlambat) . ' menit dari jam shift)</small><br>';
                                    echo '<small style="color: red;">Silakan hubungi admin untuk klarifikasi</small>';
                                } elseif ($menit_terlambat == 0 || $status_ket == 'tepat waktu') {
                                    echo '<span style="color: green; font-weight: bold;">✓ Tepat Waktu</span>';
                                } elseif ($menit_terlambat > 0 && $menit_terlambat < 20) {
                                    // Level 1: Terlambat tapi tidak ada hukuman
                                    $jam = floor($menit_terlambat / 60);
                                    $menit = $menit_terlambat % 60;
                                    $format = ($jam > 0) ? $jam . ' jam ' . $menit . ' menit' : $menit . ' menit';
                                    echo '<span style="color: orange; font-weight: bold;">⚠ Terlambat ' . $format . '</span><br>';
                                    echo '<small style="color: gray;">(Tidak ada hukuman)</small>';
                                } elseif ($menit_terlambat >= 20 && $menit_terlambat < 40) {
                                    // Level 2: Terlambat 20-39 menit
                                    $jam = floor($menit_terlambat / 60);
                                    $menit = $menit_terlambat % 60;
                                    $format = ($jam > 0) ? $jam . ' jam ' . $menit . ' menit' : $menit . ' menit';
                                    echo '<span style="color: #FF6B35; font-weight: bold;">⚠ Terlambat ' . $format . '</span>';
                                } else {
                                    // Level 3: Terlambat 40+ menit
                                    $jam = floor($menit_terlambat / 60);
                                    $menit = $menit_terlambat % 60;
                                    $format = ($jam > 0) ? $jam . ' jam ' . $menit . ' menit' : $menit . ' menit';
                                    echo '<span style="color: red; font-weight: bold;">✗ Terlambat ' . $format . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Potongan Tunjangan
                                $potongan = $absen['potongan_tunjangan'] ?? 'tidak ada';
                                if ($potongan == 'tidak ada') {
                                    echo '<span style="color: green;">-</span>';
                                } elseif ($potongan == 'tunjangan makan') {
                                    echo '<span style="color: #FF6B35; font-weight: bold;">🍽️ Tunjangan Makan</span>';
                                } else {
                                    echo '<span style="color: red; font-weight: bold;">🍽️ Makan<br>🚗 Transport</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // ========================================================
                                // STATUS KEHADIRAN - Gunakan fungsi helper untuk konsistensi
                                // ========================================================
                                $status_kehadiran = $absen['status_kehadiran_calculated'];
                                
                                // Cek apakah user ini admin
                                $is_admin_user = ($user_role === 'admin');
                                
                                if ($status_kehadiran == 'Hadir') {
                                    if ($is_admin_user) {
                                        // Admin: Tampilkan info durasi kerja dalam format jam menit
                                        $waktu_masuk = strtotime($absen['waktu_masuk']);
                                        $waktu_keluar = strtotime($absen['waktu_keluar']);
                                        $durasi_detik = $waktu_keluar - $waktu_masuk;
                                        $durasi_jam = floor($durasi_detik / 3600);
                                        $durasi_menit = floor(($durasi_detik % 3600) / 60);
                                        
                                        $format_durasi = '';
                                        if ($durasi_jam > 0) {
                                            $format_durasi .= $durasi_jam . ' jam';
                                        }
                                        if ($durasi_menit > 0) {
                                            $format_durasi .= ($durasi_jam > 0 ? ' ' : '') . $durasi_menit . ' menit';
                                        }
                                        if (empty($format_durasi)) {
                                            $format_durasi = '0 menit';
                                        }
                                        
                                        echo '<span style="color: green; font-weight: bold;">✓ Hadir</span><br>';
                                        echo '<small style="color: green;">(Kerja: ' . $format_durasi . ')</small>';
                                    } else {
                                        echo '<span style="color: green; font-weight: bold;">✓ Hadir</span><br>';
                                        echo '<small style="color: green;">(Memenuhi jam kerja)</small>';
                                    }
                                    
                                } elseif ($status_kehadiran == 'Tidak Hadir') {
                                    if ($is_admin_user) {
                                        // Admin: Tampilkan info durasi kerja dalam format jam menit
                                        $waktu_masuk = strtotime($absen['waktu_masuk']);
                                        $waktu_keluar = strtotime($absen['waktu_keluar']);
                                        $durasi_detik = $waktu_keluar - $waktu_masuk;
                                        $durasi_jam = floor($durasi_detik / 3600);
                                        $durasi_menit = floor(($durasi_detik % 3600) / 60);
                                        
                                        $format_durasi = '';
                                        if ($durasi_jam > 0) {
                                            $format_durasi .= $durasi_jam . ' jam';
                                        }
                                        if ($durasi_menit > 0) {
                                            $format_durasi .= ($durasi_jam > 0 ? ' ' : '') . $durasi_menit . ' menit';
                                        }
                                        if (empty($format_durasi)) {
                                            $format_durasi = '0 menit';
                                        }
                                        
                                        echo '<span style="color: red; font-weight: bold;">❌ Belum Memenuhi Kriteria</span><br>';
                                        echo '<small style="color: red;">(Kerja: ' . $format_durasi . ' - Minimal 8 jam)</small>';
                                    } else {
                                        $jam_keluar_shift = $absen['jam_keluar'] ?? null;
                                        $waktu_keluar_user = $absen['waktu_keluar'] ?? null;
                                        if (!empty($waktu_keluar_user) && !empty($jam_keluar_shift)) {
                                            $jam_keluar_only = date('H:i:s', strtotime($waktu_keluar_user));
                                            $selisih_detik = strtotime($jam_keluar_shift) - strtotime($jam_keluar_only);
                                            $selisih_jam = floor($selisih_detik / 3600);
                                            $selisih_menit = floor(($selisih_detik % 3600) / 60);
                                            
                                            $format_selisih = '';
                                            if ($selisih_jam > 0) {
                                                $format_selisih .= $selisih_jam . ' jam';
                                            }
                                            if ($selisih_menit > 0) {
                                                $format_selisih .= ($selisih_jam > 0 ? ' ' : '') . $selisih_menit . ' menit';
                                            }
                                            if (empty($format_selisih)) {
                                                $format_selisih = '0 menit';
                                            }
                                            
                                            echo '<span style="color: red; font-weight: bold;">❌ Belum Memenuhi Kriteria</span><br>';
                                            echo '<small style="color: red;">(Pulang ' . $format_selisih . ' lebih awal dari shift)</small>';
                                        } else {
                                            echo '<span style="color: red; font-weight: bold;">❌ Belum Memenuhi Kriteria</span>';
                                        }
                                    }
                                    
                                } elseif ($status_kehadiran == 'Belum Absen Keluar') {
                                    echo '<span style="color: orange; font-weight: bold;">⚠ Belum Absen Keluar</span><br>';
                                    echo '<small style="color: gray;">(Status kehadiran pending)</small>';
                                    
                                } elseif ($status_kehadiran == 'Lupa Absen Pulang') {
                                    echo '<span style="color: #ff6b6b; font-weight: bold;"><i class="fa fa-user-clock"></i> Lupa Absen Pulang</span><br>';
                                    echo '<small style="color: #ff6b6b;">(Dihitung hadir dengan catatan)</small>';
                                    
                                } else {
                                    // Fallback untuk status lain
                                    echo '<span style="color: gray;">' . htmlspecialchars($status_kehadiran) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                // Status Overwork: Berdasarkan status_lembur
                                if ($absen['status_lembur'] === 'Pending' || $absen['status_lembur'] === 'Approved') {
                                    echo '<span style="color:orange;font-weight:bold;">Overwork</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
<footer>
    <div class="footer-container">
        <p class="footer-text">© 2024 KAORI Indonesia. All rights reserved.</p>
        <p class="footer-text">Follow us on:</p>
        <div class="social-icons">
            <i class="fa fa-brands fa-instagram footer-link"></i>
        </div>
    </div>
</footer>
</html>
<?php
// Flush output buffer dan kirim ke browser
ob_end_flush();
// Catatan: Tidak ada closing tag ?> untuk menghindari output tak diinginkan