<?php
/**
 * Helper Script: Calculate Status Kehadiran
 * 
 * Script ini digunakan untuk menghitung status kehadiran berdasarkan jam keluar vs jam shift
 * 
 * LOGIKA:
 * - ADMIN: Status "Hadir" jika kerja >= 8 jam, "Tidak Hadir" jika < 8 jam atau belum absen keluar
 * - USER: Status berdasarkan jam keluar vs jam shift cabang
 *   - "Hadir" jika waktu_keluar >= jam_keluar_shift
 *   - "Tidak Hadir" jika waktu_keluar < jam_keluar_shift
 *   - "Tidak Hadir" jika belum absen keluar (waktu_keluar NULL)
 * 
 * Script ini bisa dipanggil:
 * 1. Via cron job (setiap hari untuk update status kehadiran)
 * 2. Di dalam view_absensi.php atau rekapabsen.php (real-time calculation)
 */

// NOTE: Tidak perlu require_once 'connect.php' di sini
// karena file ini di-include setelah connect.php sudah dimuat di file utama

/**
 * Hitung status kehadiran untuk satu record absensi dengan 7 jenis status kompleks
 *
 * @param array $absensi_record - Record absensi dengan field: waktu_masuk, waktu_keluar, user_id, tanggal_absensi, menit_terlambat, status_kehadiran
 * @param PDO $pdo - Database connection
 * @return string - Status kehadiran sesuai spesifikasi:
 *                 "Hadir", "Belum Memenuhi Kriteria", "Tidak Hadir",
 *                 "Terlambat Tanpa Potongan", "Terlambat Dengan Potongan",
 *                 "Izin", "Sakit"
 */
function hitungStatusKehadiran($absensi_record, $pdo) {
    // === 1. CEK IZIN/SAKIT DARI DATABASE ===
    // Jika sudah ada status izin/sakit dari tabel lain, prioritaskan itu
    if (isset($absensi_record['status_kehadiran'])) {
        if ($absensi_record['status_kehadiran'] === 'Izin') {
            return 'Izin';
        }
        if ($absensi_record['status_kehadiran'] === 'Sakit') {
            return 'Sakit';
        }
    }

    // === 2. CEK PENGJUAN IZIN/SAKIT ===
    // Cek apakah ada pengajuan izin/sakit yang disetujui untuk tanggal ini
    $stmt_izin = $pdo->prepare("
        SELECT status, perihal
        FROM pengajuan_izin
        WHERE user_id = ? AND ? BETWEEN tanggal_mulai AND tanggal_selesai AND status = 'Diterima'
        LIMIT 1
    ");
    $stmt_izin->execute([$absensi_record['user_id'], $absensi_record['tanggal_absensi']]);
    $izin_record = $stmt_izin->fetch();

    if ($izin_record) {
        // Cek jenis izin dari perihal
        $perihal_lower = strtolower($izin_record['perihal']);
        if (strpos($perihal_lower, 'sakit') !== false) {
            return 'Sakit';
        } else {
            return 'Izin';
        }
    }

    // === 3. DETEKSI LUPA ABSEN PULANG ===
    if (empty($absensi_record['waktu_keluar'])) {
        $tanggal_absensi = $absensi_record['tanggal_absensi'];
        $today = date('Y-m-d');

        // Jika tanggal absensi < hari ini (sudah melewati 23:59), berarti lupa absen pulang
        if ($tanggal_absensi < $today) {
            return 'Lupa Absen Pulang';
        }

        // Jika masih hari ini, status masih "Belum Absen Keluar"
        return 'Belum Absen Keluar';
    }

    // === 4. AMBIL DATA USER DAN SHIFT ===
    $stmt_user = $pdo->prepare("SELECT role, outlet FROM register WHERE id = ?");
    $stmt_user->execute([$absensi_record['user_id']]);
    $user = $stmt_user->fetch();

    if (!$user) {
        return 'Data User Tidak Ditemukan';
    }

    $is_admin = in_array($user['role'], ['admin', 'superadmin']);
    $user_outlet = $user['outlet'];

    // === 5. LOGIKA BERDASARKAN ROLE ===
    if ($is_admin) {
        // ========================================================
        // LOGIKA ADMIN: Minimal 7 jam kerja, Fleksibel
        // ========================================================
        $waktu_masuk = strtotime($absensi_record['waktu_masuk']);
        $waktu_keluar = strtotime($absensi_record['waktu_keluar']);
        $durasi_kerja_detik = $waktu_keluar - $waktu_masuk;
        $durasi_kerja_jam = $durasi_kerja_detik / 3600;

        // Admin bisa absen kapan saja di rentang 07:00-23:59
        $jam_masuk = date('H:i', $waktu_masuk);
        $jam_keluar = date('H:i', $waktu_keluar);

        if ($jam_masuk < '07:00' || $jam_keluar > '23:59') {
            return 'Tidak Hadir';
        }

        // Minimal 7 jam kerja untuk "Hadir" (lebih fleksibel)
        if ($durasi_kerja_jam >= 7) {
            return 'Hadir';
        } elseif ($durasi_kerja_jam >= 4) {
            // 4-7 jam: "Belum Memenuhi Kriteria" (bukan "Tidak Hadir")
            return 'Belum Memenuhi Kriteria';
        } else {
            return 'Tidak Hadir';
        }

    } else {
        // ========================================================
        // LOGIKA USER: Berdasarkan shift dan keterlambatan
        // ========================================================

        // Ambil shift user untuk tanggal tersebut
        $stmt_shift = $pdo->prepare("
            SELECT c.jam_masuk, c.jam_keluar, c.nama_shift
            FROM shift_assignments sa
            JOIN cabang c ON sa.cabang_id = c.id
            WHERE sa.user_id = ? AND sa.tanggal_shift = ? AND sa.status_konfirmasi = 'approved'
            LIMIT 1
        ");
        $stmt_shift->execute([$absensi_record['user_id'], $absensi_record['tanggal_absensi']]);
        $shift = $stmt_shift->fetch();

        if (!$shift) {
            // Jika tidak ada shift yang disetujui, cek apakah ada shift pending/declined
            $stmt_shift_pending = $pdo->prepare("
                SELECT c.jam_masuk, c.jam_keluar, c.nama_shift
                FROM shift_assignments sa
                JOIN cabang c ON sa.cabang_id = c.id
                WHERE sa.user_id = ? AND sa.tanggal_shift = ?
                LIMIT 1
            ");
            $stmt_shift_pending->execute([$absensi_record['user_id'], $absensi_record['tanggal_absensi']]);
            $shift = $stmt_shift_pending->fetch();

            if (!$shift) {
                // Fallback ke shift default cabang user
                $stmt_default_shift = $pdo->prepare("
                    SELECT jam_masuk, jam_keluar, nama_shift
                    FROM cabang
                    WHERE nama_cabang = ?
                    LIMIT 1
                ");
                $stmt_default_shift->execute([$user_outlet]);
                $shift = $stmt_default_shift->fetch();
            }
        }

        if (!$shift) {
            return 'Data Shift Tidak Ditemukan';
        }

        $jam_masuk_shift = $shift['jam_masuk'];
        $jam_keluar_shift = $shift['jam_keluar'];
        $nama_shift = $shift['nama_shift'];

        // Konversi waktu ke menit untuk perhitungan
        $waktu_masuk = strtotime($absensi_record['waktu_masuk']);
        $waktu_keluar = strtotime($absensi_record['waktu_keluar']);
        $shift_masuk = strtotime($absensi_record['tanggal_absensi'] . ' ' . $jam_masuk_shift);
        $shift_keluar = strtotime($absensi_record['tanggal_absensi'] . ' ' . $jam_keluar_shift);

        // Hitung keterlambatan (dalam menit)
        $menit_terlambat = max(0, ($waktu_masuk - $shift_masuk) / 60);

        // Cek apakah sudah absen sesuai shift
        $durasi_kerja_detik = $waktu_keluar - $waktu_masuk;
        $durasi_kerja_jam = $durasi_kerja_detik / 3600;

        // Minimal 1 jam kerja untuk dihitung hadir (sesuai shift)
        if ($durasi_kerja_jam < 1) {
            return 'Tidak Hadir';
        }

        // === LOGIKA TERLAMBAT ===
        if ($menit_terlambat > 0) {
            if ($menit_terlambat <= 20) {
                // Terlambat tanpa potongan (≤20 menit)
                return 'Terlambat Tanpa Potongan';
            } elseif ($menit_terlambat <= 59) {
                // Terlambat dengan potongan tunjangan transport (20-59 menit)
                return 'Terlambat Dengan Potongan';
            } else {
                // Terlambat ≥60 menit
                if ($nama_shift === 'sore') {
                    // Shift sore: potong gaji jika terlambat ≥60 menit
                    return 'Terlambat Dengan Potongan';
                } else {
                    // Shift pagi/middle: potong tunjangan makan + transport
                    return 'Terlambat Dengan Potongan';
                }
            }
        }

        // === LOGIKA HADIR ===
        // Cek apakah durasi kerja sudah memadai
        $durasi_kerja_detik = $waktu_keluar - $waktu_masuk;
        $durasi_kerja_jam = $durasi_kerja_detik / 3600;

        // Minimal 6 jam kerja untuk user (lebih fleksibel)
        if ($durasi_kerja_jam >= 6) {
            return 'Hadir';
        } elseif ($durasi_kerja_jam >= 3) {
            // 3-6 jam: "Belum Memenuhi Kriteria" (bukan "Tidak Hadir")
            return 'Belum Memenuhi Kriteria';
        } else {
            return 'Tidak Hadir';
        }
    }
}

/**
 * Update status kehadiran untuk semua absensi di database dengan logika 7 status kompleks
 * Gunakan ini untuk batch update (contoh: via cron job)
 *
 * @param PDO $pdo - Database connection
 * @param string $tanggal - Tanggal untuk update (format: Y-m-d). Default: hari ini
 * @return array - Hasil update dengan count success/failed dan breakdown per status
 */
function updateAllStatusKehadiran($pdo, $tanggal = null) {
    if ($tanggal === null) {
        $tanggal = date('Y-m-d');
    }

    // Ambil semua absensi untuk tanggal tersebut dengan data lengkap
    $stmt = $pdo->prepare("
        SELECT
            a.id, a.user_id, a.waktu_masuk, a.waktu_keluar, a.tanggal_absensi,
            a.menit_terlambat, a.status_kehadiran, a.status_lembur
        FROM absensi a
        WHERE DATE(a.tanggal_absensi) = ?
        ORDER BY a.user_id, a.waktu_masuk
    ");
    $stmt->execute([$tanggal]);
    $absensi_list = $stmt->fetchAll();

    $success_count = 0;
    $failed_count = 0;
    $status_breakdown = [
        'Hadir' => 0,
        'Belum Memenuhi Kriteria' => 0,
        'Tidak Hadir' => 0,
        'Terlambat Tanpa Potongan' => 0,
        'Terlambat Dengan Potongan' => 0,
        'Izin' => 0,
        'Sakit' => 0,
        'Belum Absen Keluar' => 0,
        'Lupa Absen Pulang' => 0
    ];

    foreach ($absensi_list as $absensi) {
        $status_kehadiran = hitungStatusKehadiran($absensi, $pdo);

        // Hitung potongan gaji berdasarkan status
        $potongan_gaji = calculatePotonganGaji($status_kehadiran, $absensi, $pdo);
        $potongan_tunjangan = calculatePotonganTunjangan($status_kehadiran, $absensi, $pdo);

        // Update ke database dengan semua field
        try {
            $stmt_update = $pdo->prepare("
                UPDATE absensi SET
                    status_kehadiran = ?,
                    potongan_tunjangan = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([
                $status_kehadiran,
                $potongan_tunjangan,
                $absensi['id']
            ]);

            $success_count++;

            // Count status breakdown
            if (isset($status_breakdown[$status_kehadiran])) {
                $status_breakdown[$status_kehadiran]++;
            }

        } catch (PDOException $e) {
            // Log error untuk debugging
            error_log("Failed to update attendance status for ID {$absensi['id']}: " . $e->getMessage());
            $failed_count++;
        }
    }

    return [
        'success' => $success_count,
        'failed' => $failed_count,
        'tanggal' => $tanggal,
        'status_breakdown' => $status_breakdown,
        'total_processed' => count($absensi_list)
    ];
}

/**
 * Calculate potongan gaji berdasarkan status kehadiran
 */
function calculatePotonganGaji($status, $absensi_record, $pdo) {
    switch ($status) {
        case 'Tidak Hadir':
            return 50000; // Potong gaji pokok per hari

        case 'Terlambat Dengan Potongan':
            // Cek apakah overwork
            if (isset($absensi_record['status_lembur']) && $absensi_record['status_lembur'] === 'Approved') {
                // Jika overwork, potong gaji jika terlambat >=60 menit
                $menit_terlambat = $absensi_record['menit_terlambat'] ?? 0;
                if ($menit_terlambat >= 60) {
                    return 25000; // Potong separuh dari gaji normal
                }
            }
            return 0; // Sudah dipotong tunjangan

        default:
            return 0;
    }
}

/**
 * Calculate potongan tunjangan berdasarkan status kehadiran
 */
function calculatePotonganTunjangan($status, $absensi_record, $pdo) {
    switch ($status) {
        case 'Terlambat Dengan Potongan':
            $menit_terlambat = $absensi_record['menit_terlambat'] ?? 0;

            if ($menit_terlambat >= 20) {
                return 'tunjangan makan dan transport';
            }
            return 'tidak ada';

        case 'Izin':
        case 'Sakit':
            return 'tidak ada'; // Izin dan sakit tidak dipotong tunjangan

        default:
            return 'tidak ada';
    }
}

// ========================================================
// CLI Execution (jika script dipanggil langsung via command line)
// Gunakan __FILE__ dan get_included_files() untuk deteksi
// ========================================================
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    // Script dipanggil langsung via CLI, bukan di-include
    // Load connect.php hanya untuk CLI mode
    if (!isset($pdo)) {
        require_once 'connect.php';
    }
    
    // Ambil tanggal dari argument atau gunakan hari ini
    $tanggal = $argv[1] ?? date('Y-m-d');
    
    echo "Updating status kehadiran untuk tanggal: $tanggal\n";
    
    $result = updateAllStatusKehadiran($pdo, $tanggal);
    
    echo "Success: {$result['success']}, Failed: {$result['failed']}\n";
    echo "Done!\n";
}
// NOTE: Closing tag dihilangkan untuk mencegah whitespace output (PSR standard)
