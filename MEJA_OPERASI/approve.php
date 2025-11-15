<?php
session_start();
// Muat koneksi DB (Ini sekarang membuat variabel $pdo)
include 'connect.php'; 

// Keamanan: Pastikan hanya admin atau superadmin yang bisa akses
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// Hanya posisi tertentu yang boleh approve
$allowed_positions = ['HR', 'Finance', 'Owner', 'superadmin'];
if (!isset($_SESSION['posisi'])) {
    if (isset($_SESSION['user_id'])) {
        $stmt_pos = $pdo->prepare("SELECT posisi FROM register WHERE id = ?");
        $stmt_pos->execute([$_SESSION['user_id']]);
        $row_pos = $stmt_pos->fetch(PDO::FETCH_ASSOC);
        if ($row_pos && !empty($row_pos['posisi'])) {
            $_SESSION['posisi'] = $row_pos['posisi'];
        }
    }
}
$user_posisi = isset($_SESSION['posisi']) ? strtolower(trim($_SESSION['posisi'])) : '';
$allowed_positions_normalized = array_map(function($p) { return strtolower(trim($p)); }, $allowed_positions);
if (!in_array($user_posisi, $allowed_positions_normalized)) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// ========================================================
// --- BLOK PERBAIKAN: Gunakan PDO untuk mengambil data ---
// ========================================================
$daftar_izin = []; // Inisialisasi array

try {
    // Ambil data izin yang masih 'Pending'
    $sql_select = "SELECT p.id, r.nama_lengkap, p.perihal, p.tanggal_mulai, p.tanggal_selesai, p.lama_izin, p.alasan, p.file_surat, p.tanda_tangan_file 
                   FROM pengajuan_izin p
                   JOIN register r ON p.user_id = r.id
                   WHERE p.status = 'Pending'
                   ORDER BY p.tanggal_pengajuan ASC";

    // 1. Gunakan $pdo->query() untuk eksekusi
    $stmt = $pdo->query($sql_select);

    // 2. Ambil semua data sekaligus ke dalam array
    $daftar_izin = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Tangani error jika query gagal
    die("Error mengambil data: " . $e->getMessage());
}
// ========================================================

$home_url = 'mainpageadmin.php'; // Admin pasti ke mainpageadmin
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persetujuan Surat Izin - Admin</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="main-title">Teman KAORI</div>
    <div class="subtitle-container">
        <p class="subtitle">Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']); ?> [<?php echo htmlspecialchars($_SESSION['role']); ?>]</p>
    </div>

    <div class="content-container">
        <h2>Persetujuan Surat Izin</h2>
        <p>Di bawah ini adalah daftar pengajuan surat izin yang menunggu persetujuan.</p>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nama Karyawan</th>
                        <th>Perihal</th>
                        <th>Tanggal Izin</th>
                        <th>Lama (Hari)</th>
                        <th>Alasan</th>
                        <th>File Surat (DOCX)</th>
                        <th>Tanda Tangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daftar_izin)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">Tidak ada pengajuan surat izin yang menunggu persetujuan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($daftar_izin as $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($data['perihal']); ?></td>
                                <td><?php echo htmlspecialchars($data['tanggal_mulai'] . ' s/d ' . $data['tanggal_selesai']); ?></td>
                                <td><?php echo htmlspecialchars($data['lama_izin']); ?></td>
                                <td style="max-width: 200px; white-space: pre-wrap;"><?php echo htmlspecialchars($data['alasan']); ?></td>
                                <td>
                                    <a href="uploads/surat_izin/<?php echo htmlspecialchars($data['file_surat']); ?>" class="link-surat" download target="_blank">
                                        Download Surat
                                    </a>
                                </td>
                                <td>
                                    <?php if (!empty($data['tanda_tangan_file'])): ?>
                                        <img src="uploads/tanda_tangan/<?php echo htmlspecialchars($data['tanda_tangan_file']); ?>" alt="TTD" style="max-width: 100px; border: 1px solid #ccc;">
                                    <?php else: ?>
                                        (Tidak ada TTD)
                                    <?php endif; ?>
                                </td>
                                
                                <td class="action-buttons">
                                    <form action="proses_approve.php" method="POST" style="display: block;">
                                        <input type="hidden" name="pengajuan_id" value="<?php echo $data['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn-approve">Setujui</button>
                                    </form>
                                    <form action="proses_approve.php" method="POST" style="display: block; margin-top: 5px;">
                                        <input type="hidden" name="pengajuan_id" value="<?php echo $data['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn-reject">Tolak</button>
                                    </form>
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
<?php
// 4. Hapus mysqli_close($conn); karena PDO tidak membutuhkannya
?>