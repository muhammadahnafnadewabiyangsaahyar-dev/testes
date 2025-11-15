<?php
/**
 * Duration Calculator Helper
 * Menghitung durasi kerja dan overwork untuk kompatibilitas free hosting
 * 
 * File ini menggantikan fungsi DATABASE TRIGGER yang tidak didukung di free hosting
 * seperti ByetHost, HostFree, 000webhost, dll.
 * 
 * @package KAORI HR System
 * @version 1.0
 * @date 2024-11-06
 */

// Set timezone untuk konsistensi
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Jakarta');
}

/**
 * Calculate work duration in minutes
 * @param string $waktu_masuk - Format: 'Y-m-d H:i:s'
 * @param string $waktu_keluar - Format: 'Y-m-d H:i:s'
 * @return int Duration in minutes
 */
function calculate_durasi_kerja($waktu_masuk, $waktu_keluar) {
    if (empty($waktu_masuk) || empty($waktu_keluar)) {
        return 0;
    }
    
    $masuk = strtotime($waktu_masuk);
    $keluar = strtotime($waktu_keluar);
    
    if ($masuk === false || $keluar === false) {
        error_log("Invalid time format - Masuk: $waktu_masuk, Keluar: $waktu_keluar");
        return 0;
    }
    
    $durasi_detik = $keluar - $masuk;
    $durasi_menit = round($durasi_detik / 60);
    
    // Validasi: durasi tidak boleh negatif atau lebih dari 24 jam (1440 menit)
    if ($durasi_menit < 0) {
        error_log("Negative duration detected - Masuk: $waktu_masuk, Keluar: $waktu_keluar");
        return 0;
    }
    
    if ($durasi_menit > 1440) {
        error_log("Duration exceeds 24 hours - Masuk: $waktu_masuk, Keluar: $waktu_keluar");
        // Cap at 24 hours
        return 1440;
    }
    
    return (int)$durasi_menit;
}

/**
 * Calculate overwork duration in minutes
 * Overwork = waktu kerja setelah jam keluar shift
 * 
 * @param string $waktu_keluar_actual - Actual checkout time (Y-m-d H:i:s)
 * @param string $tanggal_absensi - Date of attendance (Y-m-d)
 * @param string $jam_keluar_shift - Expected shift end time (HH:mm:ss)
 * @return int Overwork duration in minutes (0 if no overwork)
 */
function calculate_durasi_overwork($waktu_keluar_actual, $tanggal_absensi, $jam_keluar_shift) {
    if (empty($waktu_keluar_actual) || empty($jam_keluar_shift)) {
        return 0;
    }
    
    // Construct expected end time
    $expected_end = $tanggal_absensi . ' ' . $jam_keluar_shift;
    $expected_end_timestamp = strtotime($expected_end);
    $actual_end_timestamp = strtotime($waktu_keluar_actual);
    
    if ($expected_end_timestamp === false || $actual_end_timestamp === false) {
        error_log("Invalid time format for overwork - Expected: $expected_end, Actual: $waktu_keluar_actual");
        return 0;
    }
    
    // Calculate overwork (only if worked longer than expected)
    $overwork_detik = $actual_end_timestamp - $expected_end_timestamp;
    
    if ($overwork_detik <= 0) {
        return 0; // No overwork or left early
    }
    
    $overwork_menit = round($overwork_detik / 60);
    
    // Validasi: overwork maksimal 8 jam (480 menit)
    // Ini untuk mencegah error input atau anomali
    if ($overwork_menit > 480) {
        error_log("Overwork exceeds 8 hours (capped) - Actual: $waktu_keluar_actual, Expected: $expected_end");
        $overwork_menit = 480;
    }
    
    return (int)$overwork_menit;
}

/**
 * Calculate lateness in minutes
 * Lateness = datang terlambat dari jam masuk shift
 * 
 * @param string $waktu_masuk_actual - Actual check-in time (Y-m-d H:i:s)
 * @param string $tanggal_absensi - Date of attendance (Y-m-d)
 * @param string $jam_masuk_shift - Expected shift start time (HH:mm:ss)
 * @return int Lateness in minutes (0 if on time or early)
 */
function calculate_menit_terlambat($waktu_masuk_actual, $tanggal_absensi, $jam_masuk_shift) {
    if (empty($waktu_masuk_actual) || empty($jam_masuk_shift)) {
        return 0;
    }
    
    // Construct expected start time
    $expected_start = $tanggal_absensi . ' ' . $jam_masuk_shift;
    $expected_start_timestamp = strtotime($expected_start);
    $actual_start_timestamp = strtotime($waktu_masuk_actual);
    
    if ($expected_start_timestamp === false || $actual_start_timestamp === false) {
        error_log("Invalid time format for lateness - Expected: $expected_start, Actual: $waktu_masuk_actual");
        return 0;
    }
    
    // Calculate lateness (only if came late)
    $late_detik = $actual_start_timestamp - $expected_start_timestamp;
    
    if ($late_detik <= 0) {
        return 0; // On time or early
    }
    
    $late_menit = round($late_detik / 60);
    
    // Validasi: terlambat maksimal 4 jam (240 menit)
    // Lebih dari itu kemungkinan data error atau alfa
    if ($late_menit > 240) {
        error_log("Lateness exceeds 4 hours - Actual: $waktu_masuk_actual, Expected: $expected_start");
        // Still return actual value for manual review
    }
    
    return (int)$late_menit;
}

/**
 * Get lateness status based on minutes late
 * Sesuai business rules KAORI:
 * - 0 menit = tepat waktu
 * - 1-19 menit = terlambat kurang dari 20 menit (potongan ringan)
 * - >= 20 menit = terlambat lebih dari 20 menit (potongan berat)
 * 
 * @param int $menit_terlambat
 * @return string Status keterlambatan
 */
function get_status_keterlambatan($menit_terlambat) {
    if ($menit_terlambat == 0) {
        return 'tepat waktu';
    } elseif ($menit_terlambat < 20) {
        return 'terlambat kurang dari 20 menit';
    } else {
        return 'terlambat lebih dari 20 menit';
    }
}

/**
 * Calculate all duration metrics at once
 * Fungsi utama yang digunakan untuk menghitung semua durasi sekaligus
 * 
 * @param array $data - Array with keys:
 *   - waktu_masuk (string, Y-m-d H:i:s)
 *   - waktu_keluar (string, Y-m-d H:i:s)
 *   - tanggal_absensi (string, Y-m-d)
 *   - jam_masuk_shift (string, HH:mm:ss)
 *   - jam_keluar_shift (string, HH:mm:ss)
 * 
 * @return array Array with keys:
 *   - durasi_kerja_menit (int)
 *   - durasi_overwork_menit (int)
 *   - menit_terlambat (int)
 *   - status_keterlambatan (string)
 */
function calculate_all_durations($data) {
    $result = [
        'durasi_kerja_menit' => 0,
        'durasi_overwork_menit' => 0,
        'menit_terlambat' => 0,
        'status_keterlambatan' => 'tepat waktu'
    ];
    
    // Calculate work duration (waktu masuk sampai waktu keluar)
    if (!empty($data['waktu_masuk']) && !empty($data['waktu_keluar'])) {
        $result['durasi_kerja_menit'] = calculate_durasi_kerja(
            $data['waktu_masuk'],
            $data['waktu_keluar']
        );
    }
    
    // Calculate overwork (keluar setelah jam shift berakhir)
    if (!empty($data['waktu_keluar']) && 
        !empty($data['tanggal_absensi']) && 
        !empty($data['jam_keluar_shift'])) {
        $result['durasi_overwork_menit'] = calculate_durasi_overwork(
            $data['waktu_keluar'],
            $data['tanggal_absensi'],
            $data['jam_keluar_shift']
        );
    }
    
    // Calculate lateness (masuk setelah jam shift dimulai)
    if (!empty($data['waktu_masuk']) && 
        !empty($data['tanggal_absensi']) && 
        !empty($data['jam_masuk_shift'])) {
        $result['menit_terlambat'] = calculate_menit_terlambat(
            $data['waktu_masuk'],
            $data['tanggal_absensi'],
            $data['jam_masuk_shift']
        );
        
        $result['status_keterlambatan'] = get_status_keterlambatan(
            $result['menit_terlambat']
        );
    }
    
    return $result;
}

/**
 * Format menit to hours and minutes string
 * Helper function untuk display
 * 
 * @param int $menit
 * @return string Format: "X jam Y menit" atau "Y menit"
 */
function format_duration_display($menit) {
    if ($menit == 0) {
        return "0 menit";
    }
    
    $jam = floor($menit / 60);
    $sisa_menit = $menit % 60;
    
    if ($jam > 0 && $sisa_menit > 0) {
        return "$jam jam $sisa_menit menit";
    } elseif ($jam > 0) {
        return "$jam jam";
    } else {
        return "$sisa_menit menit";
    }
}

/**
 * Get potongan gaji for lateness
 * Sesuai business rules KAORI:
 * - Terlambat < 20 menit: Rp 5,000
 * - Terlambat >= 20 menit: Rp 10,000
 * 
 * @param int $menit_terlambat
 * @return int Potongan dalam rupiah
 */
function get_potongan_terlambat($menit_terlambat) {
    if ($menit_terlambat == 0) {
        return 0;
    } elseif ($menit_terlambat < 20) {
        return 5000; // Ringan
    } else {
        return 10000; // Berat
    }
}

/**
 * Calculate overtime pay
 * Sesuai business rules KAORI:
 * - Overwork per jam: (gaji_pokok / 173) * 1.5
 * - 173 = jumlah jam kerja standar per bulan
 * 
 * @param int $durasi_overwork_menit
 * @param int $gaji_pokok
 * @return float Upah lembur
 */
function calculate_upah_overwork($durasi_overwork_menit, $gaji_pokok) {
    if ($durasi_overwork_menit <= 0 || $gaji_pokok <= 0) {
        return 0;
    }
    
    // Convert menit to jam (desimal)
    $jam_overwork = $durasi_overwork_menit / 60.0;
    
    // Upah per jam = gaji_pokok / 173 jam
    $upah_per_jam = $gaji_pokok / 173;
    
    // Upah lembur = upah per jam * 1.5 * jumlah jam
    $upah_lembur = $upah_per_jam * 1.5 * $jam_overwork;
    
    return round($upah_lembur, 2);
}

// ============================================================
// TEST FUNCTIONS (Optional - untuk debugging)
// ============================================================

/**
 * Test all calculation functions
 * Uncomment untuk testing
 */
/*
function test_duration_calculator() {
    echo "=== Testing Duration Calculator ===\n\n";
    
    // Test 1: Normal work hours (08:00 - 17:00)
    $test1 = [
        'waktu_masuk' => '2024-11-06 08:00:00',
        'waktu_keluar' => '2024-11-06 17:00:00',
        'tanggal_absensi' => '2024-11-06',
        'jam_masuk_shift' => '08:00:00',
        'jam_keluar_shift' => '17:00:00'
    ];
    $result1 = calculate_all_durations($test1);
    print_r($result1);
    
    // Test 2: With overwork (08:00 - 19:00, shift ends at 17:00)
    $test2 = [
        'waktu_masuk' => '2024-11-06 08:00:00',
        'waktu_keluar' => '2024-11-06 19:00:00',
        'tanggal_absensi' => '2024-11-06',
        'jam_masuk_shift' => '08:00:00',
        'jam_keluar_shift' => '17:00:00'
    ];
    $result2 = calculate_all_durations($test2);
    print_r($result2);
    
    // Test 3: Late arrival (08:30 - 17:00, shift starts at 08:00)
    $test3 = [
        'waktu_masuk' => '2024-11-06 08:30:00',
        'waktu_keluar' => '2024-11-06 17:00:00',
        'tanggal_absensi' => '2024-11-06',
        'jam_masuk_shift' => '08:00:00',
        'jam_keluar_shift' => '17:00:00'
    ];
    $result3 = calculate_all_durations($test3);
    print_r($result3);
}

// Uncomment to run test
// test_duration_calculator();
*/

?>
