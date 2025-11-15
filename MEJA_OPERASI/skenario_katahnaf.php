<?php
/**
 * SKENARIO TEST: KAT AHNAF - NOVEMBER 2025
 * 
 * Detail Skenario:
 * - Mulai kerja: 6 November 2025
 * - Tidak bekerja 28 Okt - 5 Nov: 9 hari (potong gaji)
 * - Gaji pokok: Rp 1.750.000
 * - Tunjangan transport: Rp 15.000/hari
 * - Tunjangan makan: Rp 15.000/hari
 * - Tidak ada tunjangan jabatan
 * 
 * Jadwal Shift:
 * - Senin-Rabu: Shift Pagi
 * - Kamis-Jumat: Shift Siang
 * - Sabtu-Minggu: Libur
 * 
 * Kejadian Khusus:
 * - Tgl 8: Tidak masuk, reschedule ke tanggal 10 (Minggu)
 * - Tgl 10: Kerja (reschedule dari tgl 8)
 * - Tgl 11-12: Overwork shift siang (gantikan Galih Ganji)
 * - Tgl 15: Izin
 * - Tgl 18: Sakit
 * - Tgl 6, 7, 10: Lupa absen (bekerja tapi tidak tercatat)
 * - Tgl 20-21: Tidak hadir tanpa konfirmasi (alpha)
 * - Tgl 23-24: Overwork (gantikan Dot Pikir)
 * 
 * Komponen Tambahan:
 * - Kasbon: Rp 26.000
 * - Piutang toko: Rp 35.000
 * - Insentif toko: Rp 200.000
 * - Bonus marketing: Rp 100.000
 */

require_once 'connect.php';
date_default_timezone_set('Asia/Makassar');

?>
<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Skenario Test: Kat Ahnaf - November 2025</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            line-height: 1.6;
        }
        .container { 
            max-width: 1400px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 40px; 
            text-align: center; 
        }
        .content { padding: 40px; }
        .section { 
            background: #f8f9fa; 
            border-left: 5px solid #667eea; 
            padding: 25px; 
            margin-bottom: 25px; 
            border-radius: 8px;
        }
        .section h3 { color: #667eea; margin-bottom: 15px; }
        .success { background: #d4edda; border-left-color: #28a745; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .info { background: #d1ecf1; border-left-color: #17a2b8; color: #0c5460; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #667eea; color: white; }
        tr:hover { background: #f8f9fa; }
        .summary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 30px; 
            border-radius: 10px; 
            margin-top: 30px;
        }
        code { 
            background: rgba(0,0,0,0.1); 
            padding: 3px 8px; 
            border-radius: 3px; 
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🧪 Skenario Test: Kat Ahnaf</h1>
            <p>Testing Kompleks - November 2025</p>
            <p style='font-size: 0.9em; margin-top: 10px; opacity: 0.8;'>
                <?php echo date('Y-m-d H:i:s'); ?>
            </p>
        </div>
        <div class='content'>

<?php

// Get Kat Ahnaf user
$stmt = $pdo->prepare("SELECT * FROM register WHERE email = ?");
$stmt->execute(['katahnaf@gmail.com']);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<div class='error'><strong>❌ Error:</strong> User Kat Ahnaf tidak ditemukan!</div>";
    exit;
}

$user_id = $user['id'];

echo "<div class='info'>
    <strong>👤 User Ditemukan:</strong><br>
    ID: {$user_id}<br>
    Nama: {$user['nama_lengkap']}<br>
    Email: {$user['email']}
</div>";

// STEP 1: Update Komponen Gaji
echo "<div class='section'>
    <h3>📝 STEP 1: Update Komponen Gaji Kat Ahnaf</h3>";

try {
    $pdo->beginTransaction();
    
    // Update register table
    $stmt = $pdo->prepare("
        UPDATE register 
        SET gaji_pokok = 1750000,
            tunjangan_makan = 15000,
            tunjangan_transport = 15000,
            tunjangan_jabatan = 0
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    
    // Update komponen_gaji table
    $stmt = $pdo->prepare("
        UPDATE komponen_gaji 
        SET gaji_pokok = 1750000,
            tunjangan_makan = 15000,
            tunjangan_transport = 15000,
            tunjangan_jabatan = 0,
            overwork = 50000
        WHERE register_id = ?
    ");
    $stmt->execute([$user_id]);
    
    $pdo->commit();
    
    echo "<div class='success'>✅ Komponen gaji berhasil diupdate:
        <ul style='margin-top: 10px;'>
            <li>Gaji Pokok: Rp 1.750.000</li>
            <li>Tunjangan Makan: Rp 15.000/hari</li>
            <li>Tunjangan Transport: Rp 15.000/hari</li>
            <li>Tunjangan Jabatan: Rp 0</li>
            <li>Overwork: Rp 50.000/hari</li>
        </ul>
    </div>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='error'>❌ Error update komponen gaji: {$e->getMessage()}</div>";
}

echo "</div>";

// STEP 2: Bersihkan data existing
echo "<div class='section'>
    <h3>🧹 STEP 2: Bersihkan Data Existing (November 2025)</h3>";

try {
    $pdo->beginTransaction();
    
    // Delete existing shifts
    $stmt = $pdo->prepare("
        DELETE FROM shift_assignments 
        WHERE user_id = ? 
        AND tanggal_shift >= '2025-11-01' 
        AND tanggal_shift <= '2025-11-30'
    ");
    $stmt->execute([$user_id]);
    $deleted_shifts = $stmt->rowCount();
    
    // Delete existing absensi
    $stmt = $pdo->prepare("
        DELETE FROM absensi 
        WHERE user_id = ? 
        AND tanggal_absensi >= '2025-11-01' 
        AND tanggal_absensi <= '2025-11-30'
    ");
    $stmt->execute([$user_id]);
    $deleted_absensi = $stmt->rowCount();
    
    // Delete existing izin
    $stmt = $pdo->prepare("
        DELETE FROM pengajuan_izin 
        WHERE user_id = ? 
        AND tanggal_mulai >= '2025-11-01' 
        AND tanggal_selesai <= '2025-11-30'
    ");
    $stmt->execute([$user_id]);
    $deleted_izin = $stmt->rowCount();
    
    $pdo->commit();
    
    echo "<div class='success'>✅ Data berhasil dibersihkan:
        <ul style='margin-top: 10px;'>
            <li>Shift: {$deleted_shifts} record</li>
            <li>Absensi: {$deleted_absensi} record</li>
            <li>Izin: {$deleted_izin} record</li>
        </ul>
    </div>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='error'>❌ Error cleanup: {$e->getMessage()}</div>";
}

echo "</div>";

// STEP 3: Buat Shift Schedule
echo "<div class='section'>
    <h3>📅 STEP 3: Buat Jadwal Shift (Mulai 6 November)</h3>";

// Get cabang_id
$stmt = $pdo->query("SELECT id FROM cabang LIMIT 1");
$cabang = $stmt->fetch();
$cabang_id = $cabang['id'];

$shifts_created = 0;
$shift_log = [];

// Define shift schedule mulai tanggal 6
$shift_dates = [
    // Minggu pertama (6-10 Nov)
    '2025-11-06' => 'Pagi',    // Kamis - Pagi (seharusnya Siang, tapi mulai kerja)
    '2025-11-07' => 'Pagi',    // Jumat - Pagi (seharusnya Siang)
    '2025-11-08' => 'Pagi',    // Sabtu - akan reschedule ke 10
    '2025-11-10' => 'Pagi',    // Minggu - reschedule dari tgl 8
    
    // Minggu kedua (11-17 Nov)
    '2025-11-11' => 'Siang',   // Senin - Overwork (harusnya Pagi)
    '2025-11-12' => 'Siang',   // Selasa - Overwork (harusnya Pagi)
    '2025-11-13' => 'Pagi',    // Rabu - Normal
    '2025-11-14' => 'Siang',   // Kamis - Normal
    '2025-11-15' => 'Siang',   // Jumat - Izin (tetap ada shift)
    
    // Minggu ketiga (18-24 Nov)
    '2025-11-18' => 'Pagi',    // Senin - Sakit (tetap ada shift)
    '2025-11-19' => 'Pagi',    // Selasa - Normal
    '2025-11-20' => 'Pagi',    // Rabu - Alpha (tidak hadir tanpa konfirmasi)
    '2025-11-21' => 'Siang',   // Kamis - Alpha
    '2025-11-22' => 'Siang',   // Jumat - Normal
    '2025-11-23' => 'Pagi',    // Sabtu - Overwork (gantikan Dot Pikir)
    '2025-11-24' => 'Pagi',    // Minggu - Overwork
    
    // Minggu keempat (25-30 Nov)
    '2025-11-25' => 'Pagi',    // Senin - Normal
    '2025-11-26' => 'Pagi',    // Selasa - Normal
    '2025-11-27' => 'Pagi',    // Rabu - Normal
    '2025-11-28' => 'Siang',   // Kamis - Normal
    '2025-11-29' => 'Siang',   // Jumat - Normal
];

try {
    foreach ($shift_dates as $date => $shift_type) {
        $stmt = $pdo->prepare("
            INSERT INTO shift_assignments 
            (user_id, cabang_id, tanggal_shift, status_konfirmasi, created_by, created_at)
            VALUES (?, ?, ?, 'confirmed', 1, NOW())
        ");
        $stmt->execute([$user_id, $cabang_id, $date]);
        $shifts_created++;
        $shift_log[] = ['date' => $date, 'shift' => $shift_type, 'status' => 'confirmed'];
    }
    
    echo "<div class='success'>✅ {$shifts_created} shift berhasil dibuat</div>";
    
    echo "<table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Hari</th>
                <th>Shift</th>
                <th>Status</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>";
    
    foreach ($shift_log as $log) {
        $day_name = date('l', strtotime($log['date']));
        $keterangan = '';
        
        if ($log['date'] == '2025-11-08') $keterangan = '⚠️ Reschedule ke tgl 10';
        if ($log['date'] == '2025-11-10') $keterangan = '🔄 Reschedule dari tgl 8';
        if (in_array($log['date'], ['2025-11-11', '2025-11-12'])) $keterangan = '⏰ Overwork (gantikan Galih)';
        if ($log['date'] == '2025-11-15') $keterangan = '📝 Izin';
        if ($log['date'] == '2025-11-18') $keterangan = '🤒 Sakit';
        if (in_array($log['date'], ['2025-11-20', '2025-11-21'])) $keterangan = '❌ Alpha (tidak hadir)';
        if (in_array($log['date'], ['2025-11-23', '2025-11-24'])) $keterangan = '⏰ Overwork (gantikan Dot Pikir)';
        
        echo "<tr>
            <td>{$log['date']}</td>
            <td>{$day_name}</td>
            <td>{$log['shift']}</td>
            <td><span style='color: green;'>{$log['status']}</span></td>
            <td>{$keterangan}</td>
        </tr>";
    }
    
    echo "</tbody></table>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error membuat shift: {$e->getMessage()}</div>";
}

echo "</div>";

// STEP 4: Buat Data Absensi
echo "<div class='section'>
    <h3>⏰ STEP 4: Buat Data Absensi</h3>";

$absensi_log = [];
$dates_lupa_absen = ['2025-11-06', '2025-11-07', '2025-11-10']; // Lupa absen (bekerja tapi tidak tercatat otomatis)
$dates_izin_sakit = ['2025-11-15', '2025-11-18']; // Izin & Sakit (tidak ada absensi)
$dates_alpha = ['2025-11-20', '2025-11-21']; // Alpha (tidak ada absensi)
$dates_tidak_kerja = ['2025-11-08']; // Reschedule, tidak kerja di tanggal ini

// Buat absensi untuk semua tanggal kecuali yang izin, sakit, alpha, dan reschedule
$all_shift_dates = array_keys($shift_dates);

foreach ($all_shift_dates as $date) {
    // Skip jika izin, sakit, alpha, atau reschedule
    if (in_array($date, $dates_izin_sakit) || 
        in_array($date, $dates_alpha) ||
        in_array($date, $dates_tidak_kerja)) {
        continue;
    }
    
    // Tentukan waktu masuk dan keluar berdasarkan shift
    $is_overwork = in_array($date, ['2025-11-11', '2025-11-12', '2025-11-23', '2025-11-24']);
    $is_lupa_absen = in_array($date, $dates_lupa_absen);
    
    if ($shift_dates[$date] == 'Pagi') {
        $waktu_masuk = $date . ' 08:00:00';
        $waktu_keluar = $date . ' 16:00:00';
    } else { // Siang
        $waktu_masuk = $date . ' 13:00:00';
        $waktu_keluar = $date . ' 21:00:00';
    }
    
    // Variasi keterlambatan (random untuk realistis, kecuali lupa absen)
    $menit_terlambat = 0;
    if (!$is_lupa_absen && rand(0, 100) < 20) { // 20% chance terlambat
        $menit_terlambat = rand(5, 30);
        $waktu_masuk = date('Y-m-d H:i:s', strtotime($waktu_masuk . " +{$menit_terlambat} minutes"));
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO absensi 
            (user_id, cabang_id, tanggal_absensi, waktu_masuk, waktu_keluar, 
             menit_terlambat, status_kehadiran)
            VALUES (?, ?, ?, ?, ?, ?, 'Hadir')
        ");
        $stmt->execute([
            $user_id,
            $cabang_id,
            $date,
            $waktu_masuk,
            $waktu_keluar,
            $menit_terlambat
        ]);
        
        $keterangan = '';
        if ($is_lupa_absen) $keterangan = '⚠️ Lupa Absen (input manual)';
        if ($is_overwork) $keterangan .= ($keterangan ? ' | ' : '') . '⏰ Overwork';
        if ($menit_terlambat > 0) $keterangan .= ($keterangan ? ' | ' : '') . "Terlambat {$menit_terlambat} menit";
        
        $absensi_log[] = [
            'date' => $date,
            'in' => $waktu_masuk,
            'out' => $waktu_keluar,
            'late' => $menit_terlambat,
            'status' => 'Hadir',
            'ket' => $keterangan
        ];
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error absensi {$date}: {$e->getMessage()}</div>";
    }
}

echo "<div class='success'>✅ " . count($absensi_log) . " absensi berhasil dibuat</div>";

echo "<table>
    <thead>
        <tr>
            <th>Tanggal</th>
            <th>Waktu Masuk</th>
            <th>Waktu Keluar</th>
            <th>Terlambat</th>
            <th>Status</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>";

foreach ($absensi_log as $log) {
    echo "<tr>
        <td>{$log['date']}</td>
        <td>" . date('H:i', strtotime($log['in'])) . "</td>
        <td>" . date('H:i', strtotime($log['out'])) . "</td>
        <td>{$log['late']} menit</td>
        <td><span style='color: green;'>{$log['status']}</span></td>
        <td>{$log['ket']}</td>
    </tr>";
}

echo "</tbody></table>";

// Info tanggal yang tidak ada absensi
echo "<div class='warning'>
    <strong>ℹ️ Catatan Kehadiran:</strong>
    <ul style='margin-top: 10px;'>
        <li><strong>✅ Lupa Absen (input manual):</strong> 6, 7, 10 November - Sudah ditambahkan sebagai 'Hadir'</li>
        <li><strong>📝 Izin:</strong> 15 November (tidak ada absensi, approved)</li>
        <li><strong>🤒 Sakit:</strong> 18 November (tidak ada absensi, approved)</li>
        <li><strong>❌ Alpha:</strong> 20, 21 November (tidak hadir tanpa konfirmasi, tidak ada absensi)</li>
        <li><strong>🔄 Reschedule:</strong> 8 November (tidak kerja, pindah ke tgl 10)</li>
    </ul>
</div>";

echo "</div>";

// STEP 5: Buat Pengajuan Izin & Sakit
echo "<div class='section'>
    <h3>📝 STEP 5: Buat Pengajuan Izin & Sakit</h3>";

try {
    $pdo->beginTransaction();
    
    // Izin tanggal 15
    $stmt = $pdo->prepare("
        INSERT INTO pengajuan_izin 
        (user_id, perihal, tanggal_mulai, tanggal_selesai, lama_izin, alasan, 
         file_surat, status, tanggal_pengajuan)
        VALUES (?, 'Izin', '2025-11-15', '2025-11-15', 1, 
                'Keperluan keluarga', 'izin_katahnaf_20251115.pdf', 
                'Diterima', '2025-11-14 10:00:00')
    ");
    $stmt->execute([$user_id]);
    
    // Sakit tanggal 18
    $stmt = $pdo->prepare("
        INSERT INTO pengajuan_izin 
        (user_id, perihal, tanggal_mulai, tanggal_selesai, lama_izin, alasan, 
         file_surat, status, tanggal_pengajuan)
        VALUES (?, 'Sakit', '2025-11-18', '2025-11-18', 1, 
                'Demam dan flu', 'sakit_katahnaf_20251118.pdf', 
                'Diterima', '2025-11-17 15:00:00')
    ");
    $stmt->execute([$user_id]);
    
    $pdo->commit();
    
    echo "<div class='success'>✅ Pengajuan izin & sakit berhasil dibuat:
        <ul style='margin-top: 10px;'>
            <li><strong>Izin:</strong> 15 November - Status: Diterima</li>
            <li><strong>Sakit:</strong> 18 November - Status: Diterima</li>
        </ul>
    </div>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='error'>❌ Error membuat izin: {$e->getMessage()}</div>";
}

echo "</div>";

// STEP 6: Summary
echo "<div class='summary'>
    <h2>📊 Summary Skenario Kat Ahnaf - November 2025</h2>
    
    <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin: 20px 0;'>
        <h4>👤 Data Karyawan:</h4>
        <ul style='list-style: none;'>
            <li>Nama: <strong>Kat Ahnaf</strong></li>
            <li>User ID: <strong>{$user_id}</strong></li>
            <li>Gaji Pokok: <strong>Rp 1.750.000</strong></li>
            <li>Tunjangan Makan: <strong>Rp 15.000/hari</strong></li>
            <li>Tunjangan Transport: <strong>Rp 15.000/hari</strong></li>
            <li>Mulai Kerja: <strong>6 November 2025</strong></li>
            <li>Hari Tidak Bekerja (28 Okt - 5 Nov): <strong>9 hari</strong></li>
        </ul>
    </div>
    
    <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin: 20px 0;'>
        <h4>📅 Ringkasan Kehadiran:</h4>
        <ul style='list-style: none;'>
            <li>✅ Hadir dengan absensi: <strong>" . count($absensi_log) . " hari</strong> (termasuk 3 hari lupa absen yang sudah ditambahkan)</li>
            <li>📝 Izin: <strong>1 hari</strong> (15 Nov - approved)</li>
            <li>🤒 Sakit: <strong>1 hari</strong> (18 Nov - approved)</li>
            <li>❌ Alpha (tidak hadir tanpa konfirmasi): <strong>2 hari</strong> (20, 21 Nov)</li>
            <li>⏰ Overwork: <strong>4 hari</strong> (11, 12, 23, 24 Nov)</li>
            <li>🔄 Reschedule: <strong>1x</strong> (tgl 8 → 10)</li>
        </ul>
        <div style='margin-top: 10px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.2);'>
            <small>
                <strong>Catatan:</strong> Hari lupa absen (6, 7, 10 Nov) sudah ditambahkan sebagai 'Hadir' 
                karena karyawan bekerja meskipun lupa mencatat kehadiran.
            </small>
        </div>
    </div>
    
    <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin: 20px 0;'>
        <h4>💰 Komponen Tambahan:</h4>
        <ul style='list-style: none;'>
            <li><strong>Potongan:</strong></li>
            <li style='padding-left: 20px;'>- Kasbon: <span style='color: #ffeb3b;'>Rp 26.000</span></li>
            <li style='padding-left: 20px;'>- Piutang Toko: <span style='color: #ffeb3b;'>Rp 35.000</span></li>
            <li style='margin-top: 10px;'><strong>Bonus:</strong></li>
            <li style='padding-left: 20px;'>- Insentif Toko: <span style='color: #4CAF50;'>Rp 200.000</span></li>
            <li style='padding-left: 20px;'>- Bonus Marketing: <span style='color: #4CAF50;'>Rp 100.000</span></li>
        </ul>
        <div style='margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.3);'>
            <strong>Total Tambahan Potongan:</strong> <span style='color: #ffeb3b;'>Rp 61.000</span><br>
            <strong>Total Bonus:</strong> <span style='color: #4CAF50;'>Rp 300.000</span>
        </div>
    </div>
    
    <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px; margin: 20px 0;'>
        <h4>🎯 Langkah Selanjutnya:</h4>
        <ol style='padding-left: 20px;'>
            <li>Jalankan <code>auto_generate_slipgaji.php</code> untuk generate slip gaji</li>
            <li>Atau tambahkan komponen tambahan manual ke slip gaji:
                <ul style='margin-top: 5px;'>
                    <li>Kasbon: Rp 26.000</li>
                    <li>Piutang Toko: Rp 35.000</li>
                    <li>Insentif Toko: Rp 200.000</li>
                    <li>Bonus Marketing: Rp 100.000</li>
                </ul>
            </li>
            <li>Verifikasi perhitungan:
                <ul style='margin-top: 5px;'>
                    <li>Gaji pokok dipotong 9 hari (28 Okt - 5 Nov)</li>
                    <li>Tunjangan makan & transport dipotong 9 hari</li>
                    <li>Potongan untuk 3 hari lupa absen (tidak tercatat)</li>
                    <li>Potongan untuk 2 hari alpha (20-21 Nov)</li>
                    <li>Bonus overwork untuk 4 hari</li>
                </ul>
            </li>
            <li>Review slip gaji di <code>slip_gaji_management.php</code></li>
            <li>Kirim slip gaji via email</li>
        </ol>
    </div>
    
    <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 8px;'>
        <h4>📝 Catatan Penting:</h4>
        <ul style='padding-left: 20px;'>
            <li>Lupa absen di tgl 6, 7, 10 <strong>sudah ditambahkan sebagai 'Hadir'</strong> (input manual karena karyawan bekerja)</li>
            <li>Alpha di tgl 20-21 akan kena potongan (potong gaji karena tidak hadir tanpa keterangan)</li>
            <li>Overwork di tgl 11, 12, 23, 24 akan dapat bonus Rp 50.000/hari</li>
            <li>Izin & sakit (approved) tidak kena potongan</li>
            <li>Perlu update manual untuk kasbon, piutang, insentif, dan bonus marketing di slip gaji</li>
            <li>Total hari kerja efektif: <strong>" . count($absensi_log) . " hari</strong> (tidak termasuk izin & sakit yang approved)</li>
        </ul>
    </div>
</div>";

?>

        </div>
    </div>
</body>
</html>
