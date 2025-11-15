<?php
/**
 * Attendance Helper Class
 * HELMEPPO - Backend Layer
 * Helper untuk validasi absensi, cek shift, dan logika lupa absen
 */

namespace App\Helper;

class AbsenHelper {
    
    /**
     * Calculate distance between 2 GPS coordinates (Haversine Formula)
     */
    public static function hitungJarak($lat1, $lon1, $lat2, $lon2) {
        // Earth radius in meters
        $earthRadius = 6371000;
        
        // Convert degrees to radians
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $lon1Rad = deg2rad($lon1);
        $lon2Rad = deg2rad($lon2);
        
        // Haversine formula
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;
        
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        $distance = $earthRadius * $c; // in meters
        
        return round($distance, 2); // Return distance in meters, rounded
    }

    /**
     * Get today's attendance status
     */
    public static function getAbsenStatusToday($pdo, $user_id) {
        $today = date('Y-m-d');
        $sql = "SELECT waktu_masuk, waktu_keluar FROM absensi WHERE user_id = ? AND DATE(tanggal_absensi) = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $today]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $status = ['masuk' => false, 'keluar' => false];
        
        if ($row) {
            if (!empty($row['waktu_masuk'])) $status['masuk'] = true;
            if (!empty($row['waktu_keluar'])) $status['keluar'] = true;
        }
        
        return $status;
    }

    /**
     * Validate User Shift for Regular Users
     */
    public static function validateUserShift($pdo, $user_id) {
        $current_time = date('H:i:s');
        $current_date = date('Y-m-d');
        
        // Check if user has shift at current time
        $sql = "SELECT sa.*, c.nama_cabang, c.latitude as branch_lat, c.longitude as branch_lng
                FROM shift_assignments sa
                JOIN register u ON sa.user_id = u.id
                JOIN cabang c ON sa.cabang_id = c.id
                WHERE sa.user_id = ?
                AND sa.tanggal_shift = ?
                AND c.jam_masuk <= ?
                AND c.jam_keluar >= ?
                AND sa.status_konfirmasi = 'confirmed'
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $current_date, $current_time, $current_time]);
        $shift = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$shift) {
            return [
                'valid' => false,
                'message' => 'kamu tidak memiliki shift, pergilah berlibur, pacaran, atau ajukan lembur.',
                'code' => 'NO_SHIFT'
            ];
        }
        
        return [
            'valid' => true,
            'shift' => $shift,
            'message' => 'Shift ditemukan: ' . $shift['nama_cabang'] . ' (' . $shift['jam_mulai'] . ' - ' . $shift['jam_selesai'] . ')'
        ];
    }

    /**
     * Validate Outlet Location for Regular Users
     */
    public static function validateUserLocation($pdo, $user_id, $latitude, $longitude) {
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        
        // Get active user shift
        $sql = "SELECT sa.*, c.nama_cabang, c.latitude as branch_lat, c.longitude as branch_lng
                FROM shift_assignments sa
                JOIN register u ON sa.user_id = u.id
                JOIN cabang c ON sa.cabang_id = c.id
                WHERE sa.user_id = ?
                AND sa.tanggal_shift = ?
                AND c.jam_masuk <= ?
                AND c.jam_keluar >= ?
                AND sa.status_konfirmasi = 'confirmed'
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $current_date, $current_time, $current_time]);
        $shift = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$shift) {
            return [
                'valid' => false,
                'message' => 'Tidak ada shift aktif untuk validasi lokasi.',
                'code' => 'NO_SHIFT'
            ];
        }
        
        // Calculate distance between user location and outlet location
        $distance = self::hitungJarak($latitude, $longitude, $shift['branch_lat'], $shift['branch_lng']);
        
        // Tolerance 50 meters for regular users
        $max_distance = 50;
        
        if ($distance > $max_distance) {
            return [
                'valid' => false,
                'message' => 'kamu salah outlet silakan pindah outlet atau hubungi admin',
                'code' => 'WRONG_LOCATION',
                'expected_location' => $shift['nama_cabang'],
                'distance' => $distance
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Lokasi sesuai dengan outlet: ' . $shift['nama_cabang'],
            'branch' => $shift['nama_cabang'],
            'distance' => $distance
        ];
    }

    /**
     * Validate Admin Location (Unlimited - Can be Remote)
     */
    public static function validateAdminLocation($latitude, $longitude) {
        // Admin and superadmin can check in from anywhere (remote work)
        // No location restrictions for admin
        return [
            'valid' => true,
            'message' => 'Admin/Superadmin - Boleh remote dari mana saja',
            'branch' => 'Remote Work',
            'distance' => 0
        ];
    }

    /**
     * Validate Time for Admin/Superadmin
     */
    public static function validateAdminTime($role) {
        // Only applies for admin and superadmin
        if (!in_array($role, ['admin', 'superadmin'])) {
            return ['valid' => true, 'message' => 'Validasi waktu tidak diperlukan untuk role ini'];
        }
        
        $current_time = date('H:i:s');
        $current_hour = (int)date('H');
        
        // Check if time is in range 00:00 - 06:59
        if ($current_hour >= 0 && $current_hour <= 6) {
            return [
                'valid' => false,
                'message' => 'kamu terlalu rajin, silakan absen di jam 07:00 - 23:59',
                'code' => 'TOO_EARLY'
            ];
        }
        
        return ['valid' => true, 'message' => 'Waktu valid untuk absensi'];
    }

    /**
     * Validate Day for Admin/Superadmin
     */
    public static function validateAdminDay($role) {
        // Only applies for admin and superadmin
        if (!in_array($role, ['admin', 'superadmin'])) {
            return ['valid' => true, 'message' => 'Validasi hari tidak diperlukan untuk role ini'];
        }
        
        $current_day = date('w'); // 0 = Sunday, 1 = Monday, etc
        
        // Check if it's Sunday (0)
        if ($current_day == 0) {
            return [
                'valid' => false,
                'message' => 'kamu terlalu rajin, berliburlah sedikit',
                'code' => 'SUNDAY'
            ];
        }
        
        return ['valid' => true, 'message' => 'Hari valid untuk absensi'];
    }

    /**
     * Validate Check Out Time (Maximum 23:59:59)
     */
    public static function validateAbsenKeluarTime() {
        $current_time = date('H:i:s');
        $current_hour = (int)date('H');
        
        // Check out maximum at 23:59:59
        if ($current_hour >= 0 && $current_hour <= 6) {
            return [
                'valid' => false,
                'message' => 'Absen keluar maksimal jam 23:59:59. Silakan absen keluar sebelum jam 00:00.',
                'code' => 'TOO_LATE_FOR_CHECKOUT'
            ];
        }
        
        return ['valid' => true, 'message' => 'Waktu absen keluar valid'];
    }

    /**
     * Complete Validation for Attendance
     */
    public static function validateAbsensiConditions($pdo, $user_id, $user_role, $latitude = null, $longitude = null, $tipe_absen = null) {
        $errors = [];
        $warnings = [];
        
        // 1. Special validation for check out
        if ($tipe_absen === 'keluar') {
            $checkout_check = self::validateAbsenKeluarTime();
            if (!$checkout_check['valid']) {
                $errors[] = $checkout_check['message'];
            }
        }
        
        // 2. Validation for Admin/Superadmin (ACTIVE ACCORDING TO REQUIREMENTS)
        if (in_array($user_role, ['admin', 'superadmin'])) {
            // ADMIN TIME VALIDATION - 00:00-06:59 CANNOT CHECK IN
            $time_check = self::validateAdminTime($user_role);
            if (!$time_check['valid']) {
                $errors[] = $time_check['message'];
            }
            
            // ADMIN DAY VALIDATION - SUNDAY CANNOT CHECK IN
            $day_check = self::validateAdminDay($user_role);
            if (!$day_check['valid']) {
                $errors[] = $day_check['message'];
            }
            
            // Admin location is unlimited (can be remote)
            if ($latitude !== null && $longitude !== null) {
                $location_check = self::validateAdminLocation($latitude, $longitude);
                if (!$location_check['valid']) {
                    $errors[] = $location_check['message'];
                }
            }
        }
        
        // 3. Validation for Regular Users
        if ($user_role == 'user') {
            // Check shift
            $shift_check = self::validateUserShift($pdo, $user_id);
            if (!$shift_check['valid']) {
                $errors[] = $shift_check['message'];
            }
            
            // Check location if coordinates available
            if ($latitude !== null && $longitude !== null && empty($errors)) {
                $location_check = self::validateUserLocation($pdo, $user_id, $latitude, $longitude);
                if (!$location_check['valid']) {
                    $errors[] = $location_check['message'];
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'message' => empty($errors) ? 'Semua validasi berhasil' : implode('; ', $errors)
        ];
    }

    /**
     * Handle Forgot Check In (Marked Present with Notes)
     */
    public static function handleLupaAbsen($pdo, $user_id, $tipe_absen, $waktu_seharusnya) {
        try {
            $today = date('Y-m-d');
            $current_time = date('Y-m-d H:i:s');
            
            // Check if attendance record already exists today
            $sql = "SELECT * FROM absensi WHERE user_id = ? AND DATE(tanggal_absensi) = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $today]);
            $existing_absen = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existing_absen) {
                // Update existing record
                if ($tipe_absen === 'masuk' && empty($existing_absen['waktu_masuk'])) {
                    $update_sql = "UPDATE absensi SET 
                                  waktu_masuk = ?, 
                                  status_lokasi = 'Valid',
                                  catatan_lupa_absen = CONCAT(COALESCE(catatan_lupa_absen, ''), '|Masuk lupa absen pada: ', ?)
                                  WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([$current_time, $current_time, $existing_absen['id']]);
                    
                    return ['success' => true, 'message' => 'Lupa absen masuk dicatat sebagai hadir dengan catatan'];
                    
                } elseif ($tipe_absen === 'keluar' && empty($existing_absen['waktu_keluar'])) {
                    $update_sql = "UPDATE absensi SET 
                                  waktu_keluar = ?, 
                                  status_lokasi = 'Valid',
                                  catatan_lupa_absen = CONCAT(COALESCE(catatan_lupa_absen, ''), '|Keluar lupa absen pada: ', ?)
                                  WHERE id = ?";
                    $update_stmt = $pdo->prepare($update_sql);
                    $update_stmt->execute([$current_time, $current_time, $existing_absen['id']]);
                    
                    return ['success' => true, 'message' => 'Lupa absen keluar dicatat sebagai hadir dengan catatan'];
                }
            } else {
                // Create new record for forgot check in
                $insert_sql = "INSERT INTO absensi (user_id, tanggal_absensi, waktu_masuk, waktu_keluar, status_lokasi, catatan_lupa_absen) 
                              VALUES (?, ?, ?, ?, ?, ?)";
                $insert_stmt = $pdo->prepare($insert_sql);
                
                $masuk_time = ($tipe_absen === 'masuk') ? $current_time : null;
                $keluar_time = ($tipe_absen === 'keluar') ? $current_time : null;
                $catatan = "Lupa absen {$tipe_absen} pada: {$current_time}, Seharusnya: {$waktu_seharusnya}";
                
                $insert_stmt->execute([$user_id, $today, $masuk_time, $keluar_time, "Lupa Absen - Hadir dengan Catatan", $catatan]);
                
                return ['success' => true, 'message' => 'Lupa absen dicatat sebagai hadir dengan catatan'];
            }
            
            return ['success' => false, 'message' => 'Tidak dapat memproses lupa absen'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Terjadi error saat memproses lupa absen'];
        }
    }

    /**
     * Flexible Admin/Superadmin Validation
     */
    public static function validateAdminFlexibleTime($user_role) {
        // Admin and superadmin can check in anytime and anywhere
        // Except 00:00-06:00 (already validated in validateAdminTime)
        return ['valid' => true, 'message' => 'Admin/Superadmin - Aturan fleksibel'];
    }

    /**
     * Check if User is Admin/Superadmin
     */
    public static function isAdminUser($user_role) {
        return in_array($user_role, ['admin', 'superadmin']);
    }

    /**
     * Handle Tardiness Status for Admin
     */
    public static function getAdminTardinessStatus($waktu_masuk, $jam_masuk_shift) {
        // Admin and superadmin cannot be subjected to tardiness status
        return [
            'status' => 'admin_fleksibel',
            'message' => 'Admin/Superadmin - Tidak ada status terlambat',
            'menit_terlambat' => 0,
            'potongan' => 'tidak ada'
        ];
    }
}