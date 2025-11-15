<?php
/**
 * Script untuk memperbaiki status kehadiran yang bermasalah di database
 * Masalah: Seseorang yang bekerja 7 jam 24 menit malah dapet status "Tidak Hadir"
 * 
 * Logika Baru:
 * - ADMIN: >= 7 jam = "Hadir", 4-7 jam = "Belum Memenuhi Kriteria", < 4 jam = "Tidak Hadir"
 * - USER: >= 6 jam = "Hadir", 3-6 jam = "Belum Memenuhi Kriteria", < 3 jam = "Tidak Hadir"
 */

require_once 'connect.php';
include 'calculate_status_kehadiran.php';

echo "=== FIX REKAP ABSEN STATUS ===\n";
echo "Memulai perbaikan status kehadiran...\n\n";

// Ambil semua absensi yang memiliki waktu masuk dan keluar
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        r.role,
        r.outlet
    FROM absensi a
    LEFT JOIN register r ON a.user_id = r.id
    WHERE a.waktu_masuk IS NOT NULL 
    AND a.waktu_keluar IS NOT NULL
    ORDER BY a.tanggal_absensi DESC, a.user_id
");

$stmt->execute();
$all_absensi = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_records = count($all_absensi);
$fixed_count = 0;
$no_change_count = 0;
$error_count = 0;

echo "Total record yang akan dicek: $total_records\n\n";

foreach ($all_absensi as $absensi) {
    try {
        // Hitung status baru menggunakan logika yang sudah diperbaiki
        $new_status = hitungStatusKehadiran($absensi, $pdo);
        $old_status = $absensi['status_kehadiran'] ?? 'NULL';
        
        // Jika status berubah, update database
        if ($new_status !== $old_status) {
            // Update status kehadiran
            $stmt_update = $pdo->prepare("
                UPDATE absensi 
                SET status_kehadiran = ?, 
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt_update->execute([$new_status, $absensi['id']]);
            
            $fixed_count++;
            
            // Tampilkan info perubahan
            $waktu_masuk = date('H:i', strtotime($absensi['waktu_masuk']));
            $waktu_keluar = date('H:i', strtotime($absensi['waktu_keluar']));
            $waktu_masuk_timestamp = strtotime($absensi['waktu_masuk']);
            $waktu_keluar_timestamp = strtotime($absensi['waktu_keluar']);
            $durasi_jam = round(($waktu_keluar_timestamp - $waktu_masuk_timestamp) / 3600, 2);
            
            echo "🔄 FIXED [ID: {$absensi['id']}] User: {$absensi['user_id']} ({$absensi['role']})\n";
            echo "   📅 {$absensi['tanggal_absensi']} | 🕐 {$waktu_masuk} - {$waktu_keluar} ({$durasi_jam} jam)\n";
            echo "   ❌ Old: '$old_status' → ✅ New: '$new_status'\n\n";
            
        } else {
            $no_change_count++;
        }
        
    } catch (Exception $e) {
        $error_count++;
        echo "❌ ERROR [ID: {$absensi['id']}]: " . $e->getMessage() . "\n";
    }
}

echo "=== HASIL PERBAIKAN ===\n";
echo "✅ Fixed: $fixed_count records\n";
echo "⏭️  No change: $no_change_count records\n";
echo "❌ Errors: $error_count records\n";
echo "📊 Total processed: $total_records records\n\n";

// Jalankan updateAllStatusKehadiran untuk memastikan semua status terkini
echo "Menjalankan updateAllStatusKehadiran untuk konsistensi...\n";
$result = updateAllStatusKehadiran($pdo);

echo "=== UPDATE BATCH SELESAI ===\n";
echo "✅ Success: {$result['success']}\n";
echo "❌ Failed: {$result['failed']}\n";
echo "📊 Total: {$result['total_processed']}\n\n";

echo "Status breakdown:\n";
foreach ($result['status_breakdown'] as $status => $count) {
    if ($count > 0) {
        echo "  - $status: $count\n";
    }
}

echo "\n🎉 Perbaikan selesai! Silakan cek rekapabsen.php untuk melihat perubahan.\n";
?>