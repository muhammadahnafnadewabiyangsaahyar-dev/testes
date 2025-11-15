<?php
session_start();
// 1. Sertakan Library & Koneksi
include_once('tbs/tbs_class.php'); 
include_once('tbs/tbs_plugin_opentbs.php'); 
include 'connect.php'; 

// Fungsi bantu getNamaBulan (jika belum ada)
function getNamaBulan($bulan) {
    $namaBulan = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
    return $namaBulan[(int)$bulan] ?? 'Bulan?';
}

// 2. Keamanan: Cek Login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=notloggedin');
    exit();
} 
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// 3. Ambil & Validasi Parameter GET
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die('ID slip gaji tidak valid.');
}
$riwayat_gaji_id = (int)$_GET['id'];
// $format = $_GET['format'] ?? 'docx'; // Format PDF belum didukung

// 4. Ambil Data Detail Slip Gaji dari Database (PDO)
$sql = "SELECT rg.*, r.nama_lengkap, r.posisi FROM riwayat_gaji rg JOIN register r ON rg.user_id = r.id WHERE rg.id = ?";
$params = [$riwayat_gaji_id];
if ($user_role != 'admin') {
    $sql .= " AND rg.user_id = ?";
    $params[] = $user_id;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$slip_data = $stmt->fetch(PDO::FETCH_ASSOC);

// 5. Cek Jika Data Slip Ditemukan
if (!$slip_data) {
    die('Data slip gaji tidak ditemukan atau Anda tidak berhak mengaksesnya.');
}

// --- Mulai Proses OpenTBS ---

// 6. Inisialisasi OpenTBS
$TBS = new clsTinyButStrong;
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

// 7. Muat Template
$template_file = 'template_slip_gaji.docx'; // Pastikan path dan nama file benar
if (!file_exists($template_file)) { die("Error: File template '{$template_file}' tidak ditemukan."); }
$TBS->LoadTemplate($template_file, OPENTBS_ALREADY_UTF8); 

// 8. Siapkan Variabel untuk di-Merge 
$nama_karyawan = $slip_data['nama_lengkap'];
$posisi = $slip_data['posisi'];
$bulan = getNamaBulan($slip_data['periode_bulan']); 
$tahun = $slip_data['periode_tahun'];
$periode = $bulan . ' ' . $tahun;
// Gunakan Null Coalescing (?? 0) untuk memastikan nilai default jika NULL di DB
$gaji_pokok = number_format($slip_data['gaji_pokok_aktual'] ?? 0, 0, ',', '.'); 
$tunjangan_makan = number_format($slip_data['tunjangan_makan'] ?? 0, 0, ',', '.');
$tunjangan_transportasi = number_format($slip_data['tunjangan_transportasi'] ?? 0, 0, ',', '.'); // Sesuaikan nama kolom jika beda
$tunjangan_jabatan = number_format($slip_data['tunjangan_jabatan'] ?? 0, 0, ',', '.'); // Sesuaikan nama kolom jika beda
$overwork = number_format($slip_data['overwork'] ?? 0, 0, ',', '.'); // Sesuaikan nama kolom jika beda
$piutang_toko = number_format($slip_data['piutang_toko'] ?? 0, 0, ',', '.'); // Sesuaikan nama kolom jika beda
$kasbon = number_format($slip_data['kasbon'] ?? 0, 0, ',', '.'); // Sesuaikan nama kolom jika beda
$potongan_absen = number_format($slip_data['potongan_absen'] ?? 0, 0, ',', '.'); // Sesuaikan nama kolom jika beda
$potongan_telat_atas = number_format($slip_data['potongan_telat_atas_20'] ?? 0, 0, ',', '.'); // Sesuaikan nama kolom jika beda
$potongan_telat_bawah = number_format($slip_data['potongan_telat_bawah_20'] ?? 0, 0, ',', '.'); // Sesuaikan nama kolom jika beda
$gaji_bersih = number_format($slip_data['gaji_bersih'] ?? 0, 0, ',', '.');
$tanggal_cetak = date('d F Y'); // Tanggal hari ini

// 9. Gabungkan Data (MergeField) - Ganti 'nama_placeholder' sesuai template Anda
$TBS->MergeField('nama_lengkap', $nama_karyawan); // Jika placeholder [nama_lengkap]
$TBS->MergeField('posisi', $posisi);             // Jika placeholder [posisi]
$TBS->MergeField('month', $bulan);               // Jika placeholder [month]
$TBS->MergeField('year', $tahun);                // Jika placeholder [year]
$TBS->MergeField('gaji_pokok', $gaji_pokok);
$TBS->MergeField('tunjangan_makan', $tunjangan_makan);
$TBS->MergeField('tunjangan_transportasi', $tunjangan_transportasi);
$TBS->MergeField('tunjangan_jabatan', $tunjangan_jabatan);
$TBS->MergeField('overwork', $overwork);
$TBS->MergeField('piutang_toko', $piutang_toko);
$TBS->MergeField('kasbon', $kasbon);
$TBS->MergeField('absen', $potongan_absen); // Placeholder [absen] ?
$TBS->MergeField('keterlambatan_di_atas_20_menit', $potongan_telat_atas);
$TBS->MergeField('keterlambatan_di_bawah_20_menit', $potongan_telat_bawah);
$TBS->MergeField('gaji_bersih', $gaji_bersih);
$TBS->MergeField('date', $tanggal_cetak);         // Placeholder [date] ?

// 10. Masukkan Gambar Tanda Tangan Finance
$path_ttd_finance = 'uploads/tanda_tangan/ttd_agung_basir.png'; // Ganti path jika perlu
if (file_exists($path_ttd_finance)) {
    $TBS->PlugIn(OPENTBS_CHANGE_PICTURE, 'ttd', $path_ttd_finance); // Placeholder Alt Text 'ttd'
} else {
    error_log("File TTD Finance tidak ditemukan: " . $path_ttd_finance);
}

// 11. Generate Output DOCX dan Simpan ke uploads/slip_gaji/
$folder_slip_gaji = 'uploads/slip_gaji/';
if (!is_dir($folder_slip_gaji)) {
    mkdir($folder_slip_gaji, 0777, true);
}
$nama_file_download = 'Slip_Gaji_' . preg_replace('/[^A-Za-z0-9_\-]/', '', $nama_karyawan) . '_' . $periode . '_' . time() . '.docx';
$path_simpan_slip = $folder_slip_gaji . $nama_file_download;
$TBS->Show(OPENTBS_FILE, $path_simpan_slip); // Simpan ke file

// 12. Download ke user
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $nama_file_download . '"');
readfile($path_simpan_slip);
exit;
?>