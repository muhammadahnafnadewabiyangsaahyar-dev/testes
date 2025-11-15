<?php
/**
 * AUTO GENERATE SLIP GAJI - Enhanced Version
 *
 * Script ini dijalankan setiap tanggal 28 untuk generate slip gaji periode sebelumnya
 * Periode: 28 bulan lalu sampai 27 bulan ini
 *
 * Features:
 * - Komponen tetap: gaji pokok, tunjangan makan/transport/jabatan, overwork
 * - Komponen variabel: hutang toko, kasbon, bonus marketing, insentif omset (manual input)
 * - Auto calculation berdasarkan attendance & leave records
 * - Template-based PDF generation
 * - Email & WhatsApp notifications
 *
 * Cron job: 0 2 28 * * /usr/bin/php /path/to/auto_generate_slipgaji.php
 */

require_once 'connect.php';

// Constants - Sesuai aturan bisnis
define('HARI_KERJA_PER_BULAN', 26);
define('BIAYA_OVERWORK_8_JAM', 50000);
define('BIAYA_OVERWORK_PER_JAM', 6250);  // 50000 / 8
define('POTONGAN_TIDAK_HADIR', 50000);   // Untuk shift yang tidak hadir
define('HARI_LIBUR_ADMIN', 0);           // Minggu (0 = Sunday in PHP)
define('JAM_KERJA_MINIMAL', 8);          // Minimal jam kerja untuk overwork

/**
 * Calculate period dates (28th to 27th next month)
 */
function calculatePeriod() {
    $today = new DateTime();
    $current_day = (int)$today->format('d');
    
    // Jika hari ini tanggal 28, hitung periode bulan lalu
    if ($current_day == 28) {
        $periode_akhir = new DateTime();
        $periode_akhir->setDate($today->format('Y'), $today->format('m'), 27);
        
        $periode_awal = clone $periode_akhir;
        $periode_awal->modify('-1 month');
        $periode_awal->setDate($periode_awal->format('Y'), $periode_awal->format('m'), 28);
    } else {
        // Manual run - hitung periode bulan ini
        $periode_akhir = new DateTime();
        $periode_akhir->setDate($today->format('Y'), $today->format('m'), 27);
        
        $periode_awal = clone $periode_akhir;
        $periode_awal->modify('-1 month');
        $periode_awal->setDate($periode_awal->format('Y'), $periode_awal->format('m'), 28);
    }
    
    return [
        'awal' => $periode_awal,
        'akhir' => $periode_akhir,
        'bulan' => (int)$periode_akhir->format('m'),
        'tahun' => (int)$periode_akhir->format('Y')
    ];
}

/**
 * Check if date is Sunday (admin holiday)
 */
function isSunday($date) {
    $datetime = new DateTime($date);
    return $datetime->format('w') == HARI_LIBUR_ADMIN;
}

/**
 * Check if date is national holiday
 */
function isNationalHoliday($pdo, $date) {
    $stmt = $pdo->prepare("SELECT id FROM hari_libur_nasional WHERE tanggal = ?");
    $stmt->execute([$date]);
    return $stmt->rowCount() > 0;
}

/**
 * Get leave status for a date
 * Returns: ['status' => 'approved'/'rejected'/'none', 'jenis' => 'sakit'/'izin'/null]
 */
function getLeaveStatus($pdo, $user_id, $date) {
    $stmt = $pdo->prepare("
        SELECT status, perihal 
        FROM pengajuan_izin 
        WHERE user_id = ? 
        AND ? BETWEEN tanggal_mulai AND tanggal_selesai
        LIMIT 1
    ");
    $stmt->execute([$user_id, $date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return ['status' => 'none', 'jenis' => null];
    }
    
    return [
        'status' => strtolower($result['status']),
        'jenis' => strtolower($result['perihal']) // perihal berisi 'Sakit' atau 'Izin'
    ];
}

/**
 * Check if user has shift assignment on a date
 */
function hasShiftAssignment($pdo, $user_id, $date) {
    $stmt = $pdo->prepare("
        SELECT id, status_konfirmasi, decline_reason 
        FROM shift_assignments 
        WHERE user_id = ? AND tanggal_shift = ?
    ");
    $stmt->execute([$user_id, $date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get attendance record for a date
 */
function getAttendance($pdo, $user_id, $date) {
    $stmt = $pdo->prepare("
        SELECT * FROM absensi 
        WHERE user_id = ? AND tanggal_absensi = ?
    ");
    $stmt->execute([$user_id, $date]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Calculate daily salary logic
 */
function calculateDailyStatus($pdo, $user_id, $date, $is_admin) {
    $result = [
        'status' => 'libur',  // default
        'is_overwork' => false,
        'overwork_hours' => 0,
        'overwork_amount' => 0,
        'potongan' => 0,
        'notes' => ''
    ];
    
    // Check if national holiday
    if (isNationalHoliday($pdo, $date)) {
        // TODO: Logika hari libur nasional belum pasti
        // Sementara treat as libur biasa
        $result['notes'] = 'Hari Libur Nasional - Logika belum final';
        return $result;
    }
    
    // Check if Sunday (admin holiday only)
    if ($is_admin && isSunday($date)) {
        $result['notes'] = 'Hari Minggu (Libur Admin)';
        return $result;
    }
    
    // Get shift assignment
    $shift = hasShiftAssignment($pdo, $user_id, $date);
    $attendance = getAttendance($pdo, $user_id, $date);
    $leave = getLeaveStatus($pdo, $user_id, $date);
    
    // LOGIKA 1 & 2: Bukan jadwal shift
    if (!$shift) {
        if ($attendance) {
            // Ada absen, tapi bukan jadwal shift = OVERWORK
            $result['status'] = 'overwork';
            $result['is_overwork'] = true;
            
            // Hitung jam kerja
            if ($attendance['waktu_masuk'] && $attendance['waktu_keluar']) {
                $masuk = new DateTime($attendance['waktu_masuk']);
                $keluar = new DateTime($attendance['waktu_keluar']);
                $diff = $masuk->diff($keluar);
                $hours = $diff->h + ($diff->days * 24);
                
                // Overwork minimal 8 jam untuk dapat bayaran penuh
                if ($hours >= JAM_KERJA_MINIMAL) {
                    $result['overwork_hours'] = $hours;
                    $result['overwork_amount'] = BIAYA_OVERWORK_8_JAM;
                    
                    // Cek keterlambatan
                    if ($attendance['menit_terlambat'] > 0) {
                        // Potong dari overwork
                        $jam_terlambat = ceil($attendance['menit_terlambat'] / 60);
                        $result['potongan'] = $jam_terlambat * BIAYA_OVERWORK_PER_JAM;
                        $result['overwork_amount'] -= $result['potongan'];
                        $result['notes'] = "Overwork dengan keterlambatan {$attendance['menit_terlambat']} menit";
                    } else {
                        $result['notes'] = "Overwork {$hours} jam";
                    }
                } else {
                    $result['notes'] = "Jam kerja kurang dari 8 jam, tidak dapat bayaran overwork";
                }
            }
        } else {
            // Tidak ada absen, bukan jadwal shift = LIBUR
            $result['status'] = 'libur';
            $result['notes'] = 'Libur (bukan jadwal shift)';
        }
        
        return $result;
    }
    
    // Ada jadwal shift...
    
    // LOGIKA 5: Sakit (tidak potong gaji)
    if ($leave['status'] == 'approved' && $leave['jenis'] == 'sakit') {
        $result['status'] = 'sakit';
        $result['notes'] = 'Sakit (tidak potong gaji)';
        return $result;
    }
    
    // LOGIKA 6: Izin approved (potong gaji)
    if ($leave['status'] == 'approved' && $leave['jenis'] == 'izin') {
        $result['status'] = 'izin_approved';
        $result['potongan'] = POTONGAN_TIDAK_HADIR;
        $result['notes'] = 'Izin (approved) - potong gaji';
        return $result;
    }
    
    // LOGIKA 7: Izin rejected (tidak hadir, potong gaji)
    if ($leave['status'] == 'rejected') {
        $result['status'] = 'tidak_hadir';
        $result['potongan'] = POTONGAN_TIDAK_HADIR;
        $result['notes'] = 'Izin rejected - tidak hadir, potong gaji';
        return $result;
    }
    
    // LOGIKA 4: Ada jadwal shift tapi tidak hadir (potong gaji)
    if (!$attendance) {
        $result['status'] = 'tidak_hadir';
        $result['potongan'] = POTONGAN_TIDAK_HADIR;
        $result['notes'] = 'Tidak hadir (ada jadwal shift) - potong gaji';
        return $result;
    }
    
    // Ada jadwal shift DAN hadir
    $result['status'] = 'hadir';
    
    // Cek keterlambatan untuk potongan tunjangan (existing logic)
    if ($attendance['menit_terlambat'] > 0) {
        $result['notes'] = "Hadir dengan keterlambatan {$attendance['menit_terlambat']} menit";
    } else {
        $result['notes'] = 'Hadir tepat waktu';
    }
    
    return $result;
}

/**
 * Generate salary for one employee
 */
function generateSalaryForEmployee($pdo, $user_id, $periode, $generated_by) {
    try {
        // Get employee data
        $stmt = $pdo->prepare("SELECT * FROM register WHERE id = ?");
        $stmt->execute([$user_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found'];
        }
        
        $is_admin = ($employee['role'] == 'admin');
        
        // Get komponen gaji
        $stmt = $pdo->prepare("SELECT * FROM komponen_gaji WHERE register_id = ?");
        $stmt->execute([$user_id]);
        $komponen = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$komponen) {
            return ['success' => false, 'message' => 'Komponen gaji not found'];
        }
        
        // Initialize counters
        $stats = [
            'hadir' => 0,
            'tidak_hadir' => 0,
            'sakit' => 0,
            'izin_approved' => 0,
            'izin_rejected' => 0,
            'overwork' => 0,
            'libur' => 0,
            'total_overwork_amount' => 0,
            'total_potongan_tidak_hadir' => 0,
            'telat_ringan' => 0,    // 1-19 menit
            'telat_sedang' => 0,    // 20-39 menit
            'telat_berat' => 0      // 40+ menit
        ];
        
        // Loop through each day in period
        $current = clone $periode['awal'];
        while ($current <= $periode['akhir']) {
            $date_str = $current->format('Y-m-d');
            
            // Calculate daily status
            $daily = calculateDailyStatus($pdo, $user_id, $date_str, $is_admin);
            
            // Update counters
            switch ($daily['status']) {
                case 'hadir':
                    $stats['hadir']++;
                    break;
                case 'tidak_hadir':
                    $stats['tidak_hadir']++;
                    $stats['total_potongan_tidak_hadir'] += $daily['potongan'];
                    break;
                case 'sakit':
                    $stats['sakit']++;
                    break;
                case 'izin_approved':
                    $stats['izin_approved']++;
                    $stats['total_potongan_tidak_hadir'] += $daily['potongan'];
                    break;
                case 'izin_rejected':
                    $stats['izin_rejected']++;
                    $stats['total_potongan_tidak_hadir'] += $daily['potongan'];
                    break;
                case 'overwork':
                    $stats['overwork']++;
                    $stats['total_overwork_amount'] += $daily['overwork_amount'];
                    break;
                case 'libur':
                    $stats['libur']++;
                    break;
            }
            
            // Get keterlambatan for tunjangan potongan
            $attendance = getAttendance($pdo, $user_id, $date_str);
            if ($attendance && $attendance['menit_terlambat'] > 0) {
                $menit = $attendance['menit_terlambat'];
                if ($menit >= 1 && $menit <= 19) {
                    $stats['telat_ringan']++;
                } elseif ($menit >= 20 && $menit <= 39) {
                    $stats['telat_sedang']++;
                } elseif ($menit >= 40) {
                    $stats['telat_berat']++;
                }
            }
            
            $current->modify('+1 day');
        }
        
        // Calculate tunjangan potongan (existing logic from slipgaji.php)
        $tunjangan_transport_harian = $komponen['tunjangan_transport'] / HARI_KERJA_PER_BULAN;
        $tunjangan_makan_harian = $komponen['tunjangan_makan'] / HARI_KERJA_PER_BULAN;
        
        $hari_hangus_transport = $stats['telat_sedang'] + $stats['telat_berat'];
        $hari_hangus_makan = $stats['telat_berat'];
        
        $potongan_tunjangan_transport = $hari_hangus_transport * $tunjangan_transport_harian;
        $potongan_tunjangan_makan = $hari_hangus_makan * $tunjangan_makan_harian;
        $potongan_telat_atas_20 = $potongan_tunjangan_transport + $potongan_tunjangan_makan;
        
        $potongan_telat_bawah_20 = $stats['telat_ringan'] * 5000; // Denda Rp 5.000
        
        $tunjangan_transport_aktual = $komponen['tunjangan_transport'] - $potongan_tunjangan_transport;
        $tunjangan_makan_aktual = $komponen['tunjangan_makan'] - $potongan_tunjangan_makan;
        
        // Calculate final salary
        $gaji_pokok = $komponen['gaji_pokok'];
        $tunjangan_jabatan = $komponen['tunjangan_jabatan'];
        $overwork_total = $stats['total_overwork_amount'];
        
        $total_pendapatan = $gaji_pokok + $tunjangan_transport_aktual + $tunjangan_makan_aktual + 
                           $tunjangan_jabatan + $overwork_total;
        
        $total_potongan = $stats['total_potongan_tidak_hadir'] + $potongan_telat_bawah_20 + $potongan_telat_atas_20;
        
        // Note: kasbon, piutang_toko, bonus akan diisi manual oleh admin nanti
        $gaji_bersih = $total_pendapatan - $total_potongan;
        
        // Enhanced insert with additional metadata and PDF generation
        $sql_insert = "
            INSERT INTO riwayat_gaji (
                register_id, periode_bulan, periode_tahun, periode_awal, periode_akhir,
                gaji_pokok_aktual, tunjangan_makan, tunjangan_transportasi, tunjangan_jabatan,
                overwork, bonus_marketing, insentif_omset, total_pendapatan,
                piutang_toko, kasbon, total_potongan, potongan_absen, potongan_tidak_hadir,
                potongan_telat_atas_20, potongan_telat_bawah_20, potongan_telat_40, gaji_bersih,
                jumlah_hadir, jumlah_terlambat, jumlah_absen, jumlah_overwork,
                jumlah_sakit, jumlah_izin_approved, jumlah_izin_rejected, hari_tidak_hadir,
                file_slip_gaji, is_editable, generated_by, generated_at, status_notifikasi
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, 0, 0, ?,
                0, 0, ?, ?, ?,
                ?, ?, 0, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                '', 1, ?, NOW(), 'pending'
            )
        ";
        
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute([
            $user_id, $periode['bulan'], $periode['tahun'],
            $periode['awal']->format('Y-m-d'), $periode['akhir']->format('Y-m-d'),
            $gaji_pokok, $tunjangan_makan_aktual, $tunjangan_transport_aktual, $tunjangan_jabatan,
            $overwork_total, $total_pendapatan,
            $total_potongan, $stats['tidak_hadir'], $stats['total_potongan_tidak_hadir'],
            $potongan_telat_atas_20, $potongan_telat_bawah_20, $gaji_bersih,
            $stats['hadir'], ($stats['telat_ringan'] + $stats['telat_sedang'] + $stats['telat_berat']),
            0, $stats['overwork'],
            $stats['sakit'], $stats['izin_approved'], $stats['izin_rejected'], $stats['tidak_hadir'],
            $generated_by
        ]);

        $salary_id = $pdo->lastInsertId();

        // Generate PDF slip gaji using template
        $pdf_result = generateSalarySlipPDF($pdo, $salary_id, $employee, $periode, $stats, $komponen);

        if ($pdf_result['success']) {
            // Update file path in database
            $stmt_update = $pdo->prepare("UPDATE riwayat_gaji SET file_slip_gaji = ? WHERE id = ?");
            $stmt_update->execute([$pdf_result['file_path'], $salary_id]);
        }
        
        return [
            'success' => true,
            'message' => 'Salary generated successfully',
            'stats' => $stats
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Generate PDF salary slip using template
 */
function generateSalarySlipPDF($pdo, $salary_id, $employee, $periode, $stats, $komponen) {
    try {
        // Check if template exists
        $template_path = 'template_slip_gaji.docx';
        if (!file_exists($template_path)) {
            return ['success' => false, 'message' => 'Template file not found'];
        }

        // Initialize OpenTBS
        $TBS = new clsTinyButStrong;
        $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
        $TBS->LoadTemplate($template_path);

        // Prepare data for template
        $data = prepareSalarySlipData($employee, $periode, $stats, $komponen);

        // Merge data into template
        foreach ($data as $key => $value) {
            $TBS->MergeField($key, $value);
        }

        // Generate filename and path
        $tahun = $periode['tahun'];
        $bulan = str_pad($periode['bulan'], 2, '0', STR_PAD_LEFT);
        $filename = "slip_gaji_{$employee['nama_lengkap']}_{$tahun}{$bulan}_" . time() . '.pdf';

        $folder_path = "uploads/slip_gaji/{$tahun}/{$bulan}/";
        if (!is_dir($folder_path)) {
            mkdir($folder_path, 0777, true);
        }

        $file_path = $folder_path . $filename;

        // Convert to PDF (if OpenTBS supports it) or save as DOCX
        $TBS->Show(OPENTBS_FILE, $file_path);

        return [
            'success' => true,
            'file_path' => $file_path,
            'filename' => $filename
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'PDF generation error: ' . $e->getMessage()
        ];
    }
}

/**
 * Prepare salary slip data for template
 */
function prepareSalarySlipData($employee, $periode, $stats, $komponen) {
    // Calculate final amounts
    $gaji_pokok = $komponen['gaji_pokok'];
    $tunjangan_transport_harian = $komponen['tunjangan_transport'] / HARI_KERJA_PER_BULAN;
    $tunjangan_makan_harian = $komponen['tunjangan_makan'] / HARI_KERJA_PER_BULAN;

    $hari_hangus_transport = $stats['telat_sedang'] + $stats['telat_berat'];
    $hari_hangus_makan = $stats['telat_berat'];

    $potongan_tunjangan_transport = $hari_hangus_transport * $tunjangan_transport_harian;
    $potongan_tunjangan_makan = $hari_hangus_makan * $tunjangan_makan_harian;

    $tunjangan_transport_aktual = $komponen['tunjangan_transport'] - $potongan_tunjangan_transport;
    $tunjangan_makan_aktual = $komponen['tunjangan_makan'] - $potongan_tunjangan_makan;

    $potongan_telat_bawah_20 = $stats['telat_ringan'] * 5000;
    $potongan_telat_atas_20 = $potongan_tunjangan_transport + $potongan_tunjangan_makan;

    $total_pendapatan = $gaji_pokok + $tunjangan_transport_aktual + $tunjangan_makan_aktual +
                       $komponen['tunjangan_jabatan'] + $stats['total_overwork_amount'];

    $total_potongan = $stats['total_potongan_tidak_hadir'] + $potongan_telat_bawah_20 + $potongan_telat_atas_20;
    $gaji_bersih = $total_pendapatan - $total_potongan;

    return [
        // Employee info
        'nama_pegawai' => $employee['nama_lengkap'],
        'posisi' => $employee['posisi'],
        'outlet' => $employee['outlet'],
        'periode' => date('F Y', strtotime($periode['tahun'] . '-' . $periode['bulan'] . '-01')),

        // Fixed components
        'gaji_pokok' => number_format($gaji_pokok, 0, ',', '.'),
        'tunjangan_transport' => number_format($tunjangan_transport_aktual, 0, ',', '.'),
        'tunjangan_makan' => number_format($tunjangan_makan_aktual, 0, ',', '.'),
        'tunjangan_jabatan' => number_format($komponen['tunjangan_jabatan'], 0, ',', '.'),
        'overwork' => number_format($stats['total_overwork_amount'], 0, ',', '.'),

        // Variable components (placeholders for manual input)
        'bonus_marketing' => '0', // Manual input
        'insentif_omset' => '0', // Manual input

        // Deductions
        'potongan_tidak_hadir' => number_format($stats['total_potongan_tidak_hadir'], 0, ',', '.'),
        'potongan_telat_bawah_20' => number_format($potongan_telat_bawah_20, 0, ',', '.'),
        'potongan_telat_atas_20' => number_format($potongan_telat_atas_20, 0, ',', '.'),
        'piutang_toko' => '0', // Manual input
        'kasbon' => '0', // Manual input

        // Totals
        'total_pendapatan' => number_format($total_pendapatan, 0, ',', '.'),
        'total_potongan' => number_format($total_potongan, 0, ',', '.'),
        'gaji_bersih' => number_format($gaji_bersih, 0, ',', '.'),

        // Statistics
        'hari_hadir' => $stats['hadir'],
        'hari_sakit' => $stats['sakit'],
        'hari_izin' => $stats['izin_approved'],
        'hari_tidak_hadir' => $stats['tidak_hadir'],
        'hari_overwork' => $stats['overwork'],
        'hari_libur' => $stats['libur'],

        // Date
        'tanggal_cetak' => date('d F Y'),
        'periode_awal' => $periode['awal']->format('d/m/Y'),
        'periode_akhir' => $periode['akhir']->format('d/m/Y')
    ];
}

// ================== MAIN EXECUTION ==================

echo "=== AUTO GENERATE SLIP GAJI ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Calculate period
    $periode = calculatePeriod();
    echo "Period: {$periode['awal']->format('Y-m-d')} to {$periode['akhir']->format('Y-m-d')}\n";
    echo "Month: {$periode['bulan']}, Year: {$periode['tahun']}\n\n";
    
    // Get all employees (no active column, get all)
    $stmt = $pdo->query("SELECT id, nama_lengkap, role FROM register ORDER BY nama_lengkap");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Total employees: " . count($employees) . "\n\n";
    
    // Create batch record
    $stmt = $pdo->prepare("
        INSERT INTO slip_gaji_batch (
            periode_bulan, periode_tahun, periode_awal, periode_akhir, 
            total_pegawai, generated_by
        ) VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([
        $periode['bulan'], $periode['tahun'],
        $periode['awal']->format('Y-m-d'), $periode['akhir']->format('Y-m-d'),
        count($employees)
    ]);
    $batch_id = $pdo->lastInsertId();
    
    // Generate salary for each employee with enhanced logging
    $success_count = 0;
    $failed_count = 0;
    $generated_slips = [];

    foreach ($employees as $employee) {
        echo "Processing: {$employee['nama_lengkap']} [{$employee['role']}]...";

        $result = generateSalaryForEmployee($pdo, $employee['id'], $periode, 1);

        if ($result['success']) {
            $success_count++;
            $generated_slips[] = [
                'id' => $employee['id'],
                'nama' => $employee['nama_lengkap'],
                'email' => $employee['email'] ?? null,
                'no_whatsapp' => $employee['no_whatsapp'] ?? null
            ];
            echo " ✓ Success\n";
        } else {
            $failed_count++;
            echo " ✗ Failed: {$result['message']}\n";
        }
    }

    // Send bulk notifications after all salaries are generated
    if (!empty($generated_slips)) {
        echo "\nSending notifications...\n";
        sendBulkSalaryNotifications($pdo, $generated_slips, $periode);
    }
    
    // Update batch record
    $stmt = $pdo->prepare("
        UPDATE slip_gaji_batch 
        SET total_generated = ?, total_failed = ? 
        WHERE id = ?
    ");
    $stmt->execute([$success_count, $failed_count, $batch_id]);
    
    echo "\n=== SUMMARY ===\n";
    echo "Success: $success_count\n";
    echo "Failed: $failed_count\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
/**
 * Send bulk salary slip notifications via email and WhatsApp
 */
function sendBulkSalaryNotifications($pdo, $employees, $periode) {
    require_once 'email_helper.php';

    $email_success = 0;
    $email_failed = 0;
    $wa_success = 0;
    $wa_failed = 0;

    $periode_text = date('F Y', strtotime($periode['tahun'] . '-' . $periode['bulan'] . '-01'));

    echo "\n=== SENDING BULK NOTIFICATIONS ===\n";
    echo "Processing " . count($employees) . " employees for periode {$periode_text}\n\n";

    foreach ($employees as $employee) {
        echo "Processing: {$employee['nama']}... ";

        // Get salary record with complete data
        $stmt = $pdo->prepare("
            SELECT rg.*, r.email, r.no_whatsapp, r.nama_lengkap, r.posisi, r.outlet
            FROM riwayat_gaji rg
            JOIN register r ON rg.register_id = r.id
            WHERE rg.register_id = ? AND rg.periode_bulan = ? AND rg.periode_tahun = ?
        ");
        $stmt->execute([$employee['id'], $periode['bulan'], $periode['tahun']]);
        $salary_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$salary_record) {
            echo "✗ No salary record found\n";
            continue;
        }

        // Prepare comprehensive notification data
        $notification_data = [
            'id' => $employee['id'],
            'nama' => $employee['nama'],
            'email' => $salary_record['email'],
            'no_whatsapp' => $salary_record['no_whatsapp'],
            'posisi' => $salary_record['posisi'],
            'outlet' => $salary_record['outlet'],
            'periode' => $periode_text,
            'gaji_bersih' => $salary_record['gaji_bersih'],
            'file_slip_gaji' => $salary_record['file_slip_gaji'],
            'total_pendapatan' => $salary_record['total_pendapatan'],
            'total_potongan' => $salary_record['total_potongan'],
            'salary_id' => $salary_record['id']
        ];

        $notification_sent = false;

        // Send email notification
        if (!empty($notification_data['email'])) {
            $email_result = sendEmailSlipGaji($notification_data, $periode);
            if ($email_result) {
                $email_success++;
                echo "✓ Email ";
                $notification_sent = true;
            } else {
                $email_failed++;
                echo "✗ Email ";
            }
        }

        // Send WhatsApp notification with file attachment
        if (!empty($notification_data['no_whatsapp'])) {
            require_once 'wa_api_helper.php';
            global $pdo;
            $wa_api = new WhatsAppAPI($pdo);

            $salary_data = [
                'periode_bulan' => $periode['bulan'],
                'periode_tahun' => $periode['tahun'],
                'gaji_bersih' => $notification_data['gaji_bersih'],
                'register_id' => $notification_data['id']
            ];

            // Send with file attachment if file exists
            if (!empty($notification_data['file_slip_gaji']) && file_exists($notification_data['file_slip_gaji'])) {
                $wa_result = $wa_api->sendSalarySlip($notification_data['id'], $salary_data, $notification_data['file_slip_gaji']);
            } else {
                $wa_result = $wa_api->sendSalarySlip($notification_data['id'], $salary_data);
            }

            if ($wa_result && isset($wa_result['success']) && $wa_result['success']) {
                $wa_success++;
                echo "✓ WhatsApp ";
                $notification_sent = true;
            } else {
                $wa_failed++;
                echo "✗ WhatsApp ";
            }
        }

        // Update notification status in database
        if ($notification_sent) {
            $stmt_update = $pdo->prepare("
                UPDATE riwayat_gaji
                SET status_notifikasi = 'sent', tanggal_notifikasi = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([$salary_record['id']]);
            echo "✓ Status updated\n";
        } else {
            echo "✗ No notifications sent\n";
        }
    }

    // Send summary notification to HR/Admin
    sendHRNotificationSummary($pdo, [
        'periode' => $periode_text,
        'total_employees' => count($employees),
        'email_success' => $email_success,
        'email_failed' => $email_failed,
        'wa_success' => $wa_success,
        'wa_failed' => $wa_failed
    ], $periode);

    echo "\n=== NOTIFICATION SUMMARY ===\n";
    echo "Total Employees: " . count($employees) . "\n";
    echo "Email: {$email_success} success, {$email_failed} failed\n";
    echo "WhatsApp: {$wa_success} success, {$wa_failed} failed\n";
    echo "HR Summary notification sent\n";
}

/**
 * Send HR summary notification
 */
function sendHRNotificationSummary($pdo, $summary_data, $periode) {
    try {
        // Get HR/Admin emails
        $stmt = $pdo->prepare("SELECT email, nama_lengkap FROM register WHERE role IN ('admin', 'superadmin') AND email IS NOT NULL");
        $stmt->execute();
        $hr_recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($hr_recipients)) {
            echo "No HR recipients found for summary notification\n";
            return false;
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kaori.aplikasi.notif@gmail.com';
        $mail->Password = 'imjq nmeq vyig umgn';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('kaori.aplikasi.notif@gmail.com', 'Sistem KAORI');

        // Add HR recipients
        foreach ($hr_recipients as $hr) {
            $mail->addAddress($hr['email'], $hr['nama_lengkap']);
        }

        $mail->isHTML(true);
        $mail->Subject = "Ringkasan Pengiriman Slip Gaji - {$summary_data['periode']}";

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0; font-size: 24px;'>📊 Ringkasan Slip Gaji</h1>
                <p style='margin: 10px 0 0 0; opacity: 0.9;'>Periode: {$summary_data['periode']}</p>
            </div>

            <div style='background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;'>
                <h2 style='color: #333; margin-bottom: 20px;'>Status Pengiriman Notifikasi</h2>

                <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                        <span><strong>Total Karyawan:</strong></span>
                        <span>{$summary_data['total_employees']}</span>
                    </div>
                    <hr style='border: none; border-top: 1px solid #dee2e6; margin: 15px 0;'>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                        <span><strong>Email Berhasil:</strong></span>
                        <span style='color: #28a745;'>{$summary_data['email_success']}</span>
                    </div>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                        <span><strong>Email Gagal:</strong></span>
                        <span style='color: #dc3545;'>{$summary_data['email_failed']}</span>
                    </div>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                        <span><strong>WhatsApp Berhasil:</strong></span>
                        <span style='color: #28a745;'>{$summary_data['wa_success']}</span>
                    </div>
                    <div style='display: flex; justify-content: space-between; margin-bottom: 10px;'>
                        <span><strong>WhatsApp Gagal:</strong></span>
                        <span style='color: #dc3545;'>{$summary_data['wa_failed']}</span>
                    </div>
                </div>

                <div style='background: #e3f2fd; padding: 15px; border-radius: 6px; margin: 20px 0; border-left: 4px solid #2196F3;'>
                    <p style='margin: 0; color: #0d47a1;'>
                        <strong>💡 Info:</strong> Slip gaji telah digenerate otomatis dan notifikasi telah dikirim ke karyawan.
                        Komponen variabel (bonus, kasbon, dll) masih dapat diedit melalui menu Slip Gaji Management.
                    </p>
                </div>

                <p style='color: #666; font-size: 12px; text-align: center; margin: 20px 0 0 0;'>
                    Email ini dikirim secara otomatis oleh Sistem KAORI Indonesia pada " . date('d/m/Y H:i:s') . "
                </p>
            </div>
        </div>
        ";

        $mail->send();
        echo "✓ HR summary notification sent\n";
        return true;

    } catch (Exception $e) {
        echo "✗ HR summary notification failed: " . $e->getMessage() . "\n";
        return false;
    }
}

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
