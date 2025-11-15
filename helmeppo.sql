-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Waktu pembuatan: 12 Nov 2025 pada 17.09
-- Versi server: 10.4.28-MariaDB
-- Versi PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kaoriapp`
--

DELIMITER $$
--
-- Prosedur
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_calculate_attendance_status` (IN `p_user_id` INT, IN `p_tanggal` DATE, IN `p_jam_masuk` TIME, IN `p_shift_start_time` TIME, OUT `p_status` VARCHAR(50), OUT `p_late_minutes` INT)   BEGIN
    DECLARE v_late_minutes_val INT DEFAULT 0;
    
    -- Calculate late minutes
    IF p_jam_masuk > p_shift_start_time THEN
        SET v_late_minutes_val = TIMESTAMPDIFF(MINUTE, p_shift_start_time, p_jam_masuk);
    END IF;
    
    -- Determine status based on PLANT.MD rules
    IF v_late_minutes_val = 0 THEN
        SET p_status = 'hadir';
    ELSEIF v_late_minutes_val < 20 THEN
        SET p_status = 'terlambat_tanpa_potongan';
    ELSEIF v_late_minutes_val < 40 THEN
        SET p_status = 'terlambat_dengan_potongan';
    ELSE
        SET p_status = 'terlambat_dengan_potongan';
    END IF;
    
    SET p_late_minutes = v_late_minutes_val;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generate_monthly_payroll` (IN `p_user_id` INT, IN `p_bulan` TINYINT, IN `p_tahun` SMALLINT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_exit INT DEFAULT 0;
    DECLARE v_position_id INT;
    DECLARE v_gaji_pokok DECIMAL(12,2);
    DECLARE v_tunjangan_makan DECIMAL(8,2);
    DECLARE v_tunjangan_transport DECIMAL(8,2);
    DECLARE v_tunjangan_jabatan DECIMAL(10,2);
    DECLARE v_days_present INT DEFAULT 0;
    DECLARE v_days_absent INT DEFAULT 0;
    DECLARE v_days_late INT DEFAULT 0;
    DECLARE v_total_late_minutes INT DEFAULT 0;
    DECLARE v_total_overwork_hours DECIMAL(6,2) DEFAULT 0;
    DECLARE v_overwork_bonus DECIMAL(10,2) DEFAULT 0;
    DECLARE v_late_deduction DECIMAL(10,2) DEFAULT 0;
    DECLARE v_late_fee_per_minute DECIMAL(6,2) DEFAULT 0;
    DECLARE v_absent_penalty DECIMAL(10,2) DEFAULT 0;
    DECLARE v_working_days_per_month TINYINT DEFAULT 26;

    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            p_message = MESSAGE_TEXT;
        SET p_success = FALSE;
        SET v_exit = 1;
    END;
    
    IF v_exit = 0 THEN
        -- Get user position details
        SELECT position_id INTO v_position_id
        FROM users WHERE id = p_user_id;
        
        IF v_position_id IS NOT NULL THEN
            SELECT 
                gaji_pokok,
                tunjangan_makan_per_hari,
                tunjangan_transportasi_per_hari,
                tunjangan_jabatan,
                late_fee_per_minute,
                absent_penalty,
                working_days_per_month
            INTO v_gaji_pokok, v_tunjangan_makan, v_tunjangan_transport, v_tunjangan_jabatan,
                 v_late_fee_per_minute, v_absent_penalty, v_working_days_per_month
            FROM positions WHERE id = v_position_id;
            
            -- Calculate attendance summary
            SELECT 
                COUNT(CASE WHEN status IN ('hadir', 'terlambat_tanpa_potongan', 'terlambat_dengan_potongan') THEN 1 END),
                COUNT(CASE WHEN status = 'tidak_hadir' THEN 1 END),
                COUNT(CASE WHEN status IN ('terlambat_tanpa_potongan', 'terlambat_dengan_potongan') THEN 1 END),
                SUM(CASE WHEN status IN ('hadir', 'terlambat_tanpa_potongan', 'terlambat_dengan_potongan') THEN terlambat_menit ELSE 0 END),
                SUM(CASE WHEN status IN ('hadir', 'terlambat_tanpa_potongan', 'terlambat_dengan_potongan') AND overwork_status = 'disetujui' THEN overwork_jam ELSE 0 END)
            INTO v_days_present, v_days_absent, v_days_late, v_total_late_minutes, v_total_overwork_hours
            FROM attendance
            WHERE user_id = p_user_id 
              AND MONTH(tanggal) = p_bulan 
              AND YEAR(tanggal) = p_tahun;
              
            -- Get total approved overwork bonus
            SELECT SUM(total_overwork_payment)
            INTO v_overwork_bonus
            FROM overwork_requests
            WHERE user_id = p_user_id
              AND MONTH(tanggal) = p_bulan
              AND YEAR(tanggal) = p_tahun
              AND status = 'disetujui';

            -- Calculate totals
            SET v_tunjangan_makan = v_days_present * v_tunjangan_makan;
            SET v_tunjangan_transport = v_days_present * v_tunjangan_transport;
            SET v_late_deduction = v_total_late_minutes * v_late_fee_per_minute;
            
            -- Insert or Update payroll record
            INSERT INTO payroll_records (
                user_id, bulan, tahun,
                gaji_pokok, tunjangan_makan_total, tunjangan_transportasi_total, tunjangan_jabatan,
                overwork_bonus, late_deduction, days_present, days_absent, days_late, 
                total_late_minutes, total_overwork_hours, status
            ) VALUES (
                p_user_id, p_bulan, p_tahun,
                v_gaji_pokok, v_tunjangan_makan, v_tunjangan_transport, v_tunjangan_jabatan,
                COALESCE(v_overwork_bonus, 0), v_late_deduction, v_days_present, v_days_absent, v_days_late, 
                v_total_late_minutes, COALESCE(v_total_overwork_hours, 0), 'draft'
            )
            ON DUPLICATE KEY UPDATE
                gaji_pokok = v_gaji_pokok,
                tunjangan_makan_total = v_tunjangan_makan,
                tunjangan_transportasi_total = v_tunjangan_transport,
                tunjangan_jabatan = v_tunjangan_jabatan,
                overwork_bonus = COALESCE(v_overwork_bonus, 0),
                late_deduction = v_late_deduction,
                days_present = v_days_present,
                days_absent = v_days_absent,
                days_late = v_days_late,
                total_late_minutes = v_total_late_minutes,
                total_overwork_hours = COALESCE(v_total_overwork_hours, 0),
                status = 'draft',
                updated_at = CURRENT_TIMESTAMP;
            
            SET p_success = TRUE;
            SET p_message = 'Payroll generated successfully';
        ELSE
            SET p_success = FALSE;
            SET p_message = 'User not found';
        END IF;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL COMMENT 'Unique attendance identifier',
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke user',
  `shift_assignment_id` int(11) DEFAULT NULL COMMENT 'Referensi ke shift assignment',
  `tanggal` date NOT NULL COMMENT 'Tanggal attendance',
  `jam_masuk` timestamp NULL DEFAULT NULL COMMENT 'Waktu actual masuk',
  `jam_keluar` timestamp NULL DEFAULT NULL COMMENT 'Waktu actual keluar',
  `latitude_masuk` decimal(10,8) DEFAULT NULL COMMENT 'Latitude saat masuk',
  `longitude_masuk` decimal(11,8) DEFAULT NULL COMMENT 'Longitude saat masuk',
  `latitude_keluar` decimal(10,8) DEFAULT NULL COMMENT 'Latitude saat keluar',
  `longitude_keluar` decimal(11,8) DEFAULT NULL COMMENT 'Longitude saat keluar',
  `foto_masuk` varchar(255) DEFAULT NULL COMMENT 'Path foto saat masuk',
  `foto_keluar` varchar(255) DEFAULT NULL COMMENT 'Path foto saat keluar',
  `status` enum('hadir','belum_memenuhi_kriteria','tidak_hadir','terlambat_tanpa_potongan','terlambat_dengan_potongan','izin','sakit') DEFAULT 'belum_memenuhi_kriteria' COMMENT 'Status attendance sesuai PLANT.MD',
  `terlambat_menit` int(11) DEFAULT 0 COMMENT 'Menit keterlambatan dari jam shift',
  `overwork_jam` decimal(4,2) DEFAULT 0.00 COMMENT 'Jam overwork yang dikerjakan',
  `overwork_status` enum('pending','disetujui','ditolak') DEFAULT NULL COMMENT 'Status approval overwork',
  `total_jam_kerja` decimal(4,2) DEFAULT 0.00 COMMENT 'Total jam kerja actual',
  `status_lokasi` enum('Valid','Tidak Valid') DEFAULT NULL COMMENT 'Status validasi lokasi GPS',
  `validation_notes` text DEFAULT NULL COMMENT 'Catatan validasi dan review',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu update'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Attendance dengan 7 status types sesuai PLANT.MD requirements';

--
-- Trigger `attendance`
--
DELIMITER $$
CREATE TRIGGER `tr_attendance_updated_at` BEFORE UPDATE ON `attendance` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `queue_id` int(11) DEFAULT NULL COMMENT 'Referensi ke email_queue (nullable untuk direct send)',
  `recipient_email` varchar(100) NOT NULL COMMENT 'Email yang dikirim',
  `recipient_name` varchar(200) DEFAULT NULL COMMENT 'Nama recipient',
  `subject` varchar(300) NOT NULL COMMENT 'Email subject',
  `template_used` varchar(100) DEFAULT NULL COMMENT 'Template name yang digunakan',
  `status` enum('delivered','bounced','complained','opened','clicked','failed') NOT NULL COMMENT 'Delivery status',
  `provider_message_id` varchar(200) DEFAULT NULL COMMENT 'Message ID dari email provider',
  `provider_response` text DEFAULT NULL COMMENT 'Response dari email provider',
  `opened_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu email dibuka',
  `clicked_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu link diklik',
  `bounced_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu bounce',
  `error_details` text DEFAULT NULL COMMENT 'Detail error jika ada',
  `user_id` int(11) DEFAULT NULL COMMENT 'User terkait (jika ada)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Comprehensive email delivery tracking';

-- --------------------------------------------------------

--
-- Struktur dari tabel `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `recipient_email` varchar(100) NOT NULL COMMENT 'Email recipient',
  `recipient_name` varchar(200) DEFAULT NULL COMMENT 'Nama recipient',
  `subject` varchar(300) NOT NULL COMMENT 'Email subject',
  `body_html` longtext DEFAULT NULL COMMENT 'HTML email body',
  `body_text` longtext DEFAULT NULL COMMENT 'Plain text email body',
  `template_id` int(11) DEFAULT NULL COMMENT 'Referensi ke email_templates',
  `priority` enum('high','normal','low') DEFAULT 'normal' COMMENT 'Priority email',
  `status` enum('pending','sending','sent','failed','cancelled') DEFAULT 'pending' COMMENT 'Status pengiriman',
  `attempts` int(11) DEFAULT 0 COMMENT 'Jumlah percobaan pengiriman',
  `scheduled_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu terjadwal pengiriman',
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu berhasil dikirim',
  `error_message` text DEFAULT NULL COMMENT 'Pesan error jika gagal',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID yang membuat email (nullable untuk system emails)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Email queue management untuk async sending';

--
-- Trigger `email_queue`
--
DELIMITER $$
CREATE TRIGGER `tr_email_queue_process` AFTER INSERT ON `email_queue` FOR EACH ROW BEGIN
    -- Auto-trigger untuk high priority emails
    IF NEW.priority = 'high' AND NEW.status = 'pending' THEN
        UPDATE email_queue 
        SET status = 'sending', updated_at = CURRENT_TIMESTAMP 
        WHERE id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL COMMENT 'Unique template identifier',
  `subject` varchar(300) NOT NULL COMMENT 'Email subject',
  `body_html` longtext DEFAULT NULL COMMENT 'HTML email body',
  `body_text` longtext DEFAULT NULL COMMENT 'Plain text email body',
  `variables` longtext DEFAULT NULL COMMENT 'JSON array of available variables',
  `template_type` enum('notification','reminder','report','system') NOT NULL COMMENT 'Type template',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Status template aktif/nonaktif',
  `created_by` int(11) NOT NULL COMMENT 'User ID yang membuat template',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Email template management system';

-- --------------------------------------------------------

--
-- Struktur dari tabel `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(11) NOT NULL COMMENT 'Unique leave identifier',
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke user',
  `jenis` enum('izin','sakit') NOT NULL COMMENT 'Jenis leave',
  `tanggal_mulai` date NOT NULL COMMENT 'Tanggal mulai leave',
  `tanggal_selesai` date NOT NULL COMMENT 'Tanggal selesai leave',
  `perfihal` varchar(100) NOT NULL COMMENT 'Perihal leave (auto uppercase)',
  `alasan_izin` text DEFAULT NULL COMMENT 'Alasan leave (auto lowercase)',
  `surat_dokter` varchar(255) DEFAULT NULL COMMENT 'Path surat dokter untuk sakit',
  `medical_certificate_url` varchar(255) DEFAULT NULL COMMENT 'URL medical certificate',
  `status` enum('pending','disetujui','ditolak','expired') DEFAULT 'pending' COMMENT 'Status approval',
  `shift_assignment_id` int(11) DEFAULT NULL COMMENT 'Referensi shift assignment yang terkait',
  `disetujui_oleh` int(11) DEFAULT NULL COMMENT 'User ID yang menyetujui',
  `catatan_admin` text DEFAULT NULL COMMENT 'Catatan admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu update',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu approval',
  `expired_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu expired'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Leave request management untuk izin dan sakit';

--
-- Trigger `leave_requests`
--
DELIMITER $$
CREATE TRIGGER `tr_leave_requests_shift_assignment` BEFORE INSERT ON `leave_requests` FOR EACH ROW BEGIN
    DECLARE v_shift_assignment_id INT;
    
    -- Find a relevant shift assignment within the leave dates
    SELECT sa.id INTO v_shift_assignment_id
    FROM shift_assignments sa
    WHERE sa.user_id = NEW.user_id 
      AND sa.tanggal BETWEEN NEW.tanggal_mulai AND NEW.tanggal_selesai
      AND sa.status IN ('d_assign', 'disetujui', 'completed') -- Added 'completed' and removed 'dikonfirmasi' (not in ENUM)
    ORDER BY sa.tanggal ASC
    LIMIT 1;
    
    IF v_shift_assignment_id IS NOT NULL THEN
        SET NEW.shift_assignment_id = v_shift_assignment_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_leave_requests_updated_at` BEFORE UPDATE ON `leave_requests` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `notification_logs`
--

CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL COMMENT 'Unique notification identifier',
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke user',
  `notification_type` enum('shift_assigned','shift_confirmed','leave_submitted','overwork_request','payroll_ready','shift_reminder','attendance_alert','system_announcement') NOT NULL COMMENT 'Jenis notifikasi',
  `title` varchar(200) NOT NULL COMMENT 'Judul notifikasi',
  `message_summary` varchar(255) DEFAULT NULL COMMENT 'Ringkasan pesan untuk logging',
  `related_id` int(11) DEFAULT NULL COMMENT 'ID related record (shift_id, leave_id, dll)',
  `related_type` enum('shift_assignment','leave_request','overwork_request','payroll') DEFAULT NULL COMMENT 'Tipe related record',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu dikirim',
  `status` enum('sent','failed','pending','read') DEFAULT 'sent' COMMENT 'Status pengiriman',
  `failure_reason` text DEFAULT NULL COMMENT 'Alasan kegagalan pengiriman',
  `telegram_message_id` varchar(100) DEFAULT NULL COMMENT 'Message ID dari Telegram API',
  `telegram_chat_id` varchar(50) DEFAULT NULL COMMENT 'Chat ID Telegram'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Telegram notification audit trail';

--
-- Trigger `notification_logs`
--
DELIMITER $$
CREATE TRIGGER `tr_notification_auto_telegram` AFTER INSERT ON `notification_logs` FOR EACH ROW BEGIN
    DECLARE v_user_telegram_id VARCHAR(50);
    
    -- Auto send Telegram notification jika enabled
    SELECT telegram_id INTO v_user_telegram_id 
    FROM users 
    WHERE id = NEW.user_id AND telegram_id IS NOT NULL;
    
    IF v_user_telegram_id IS NOT NULL THEN
        INSERT INTO notification_logs (user_id, notification_type, title, message_summary, telegram_chat_id, status)
        VALUES (NEW.user_id, CONCAT('telegram_', NEW.notification_type), NEW.title, NEW.message_summary, v_user_telegram_id, 'pending');
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `outlets`
--

CREATE TABLE `outlets` (
  `id` int(11) NOT NULL COMMENT 'Unique outlet identifier',
  `nama_outlet` varchar(50) NOT NULL COMMENT 'Nama lengkap outlet/cabang',
  `kode_outlet` varchar(10) NOT NULL COMMENT 'Kode singkat outlet untuk referensi',
  `jam_masuk_shift_1` time NOT NULL DEFAULT '07:00:00' COMMENT 'Jam masuk shift pagi',
  `jam_keluar_shift_1` time NOT NULL DEFAULT '15:00:00' COMMENT 'Jam keluar shift pagi',
  `jam_masuk_shift_2` time NOT NULL DEFAULT '13:00:00' COMMENT 'Jam masuk shift middle',
  `jam_keluar_shift_2` time NOT NULL DEFAULT '21:00:00' COMMENT 'Jam keluar shift middle',
  `jam_masuk_shift_3` time NOT NULL DEFAULT '15:00:00' COMMENT 'Jam masuk shift sore',
  `jam_keluar_shift_3` time NOT NULL DEFAULT '23:00:00' COMMENT 'Jam keluar shift sore',
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Koordinat latitude untuk attendance geofencing',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Koordinat longitude untuk attendance geofencing',
  `geofence_radius` int(11) DEFAULT 50 COMMENT 'Radius geofence dalam meter untuk validasi lokasi',
  `is_remote_access` tinyint(1) DEFAULT 0 COMMENT 'Akses remote untuk admin/outlet khusus',
  `attendance_early_start_minutes` int(11) DEFAULT 60 COMMENT 'Menit sebelum shift start untuk early check-in',
  `attendance_late_end_minutes` int(11) DEFAULT 15 COMMENT 'Menit setelah shift end untuk late check-in',
  `is_attendance_flexible` tinyint(1) DEFAULT 0 COMMENT 'Flexible attendance untuk admin khusus',
  `status` enum('aktif','tidak_aktif') DEFAULT 'aktif' COMMENT 'Status operasional outlet',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan record',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu update terakhir'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master outlet dengan flexible shift configuration dan GPS';

--
-- Dumping data untuk tabel `outlets`
--

INSERT INTO `outlets` (`id`, `nama_outlet`, `kode_outlet`, `jam_masuk_shift_1`, `jam_keluar_shift_1`, `jam_masuk_shift_2`, `jam_keluar_shift_2`, `jam_masuk_shift_3`, `jam_keluar_shift_3`, `latitude`, `longitude`, `geofence_radius`, `is_remote_access`, `attendance_early_start_minutes`, `attendance_late_end_minutes`, `is_attendance_flexible`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Adhyaksa', 'ADH', '07:00:00', '15:00:00', '12:00:00', '20:00:00', '15:00:00', '23:00:00', -5.16039705, 119.44607614, 50, 0, 60, 15, 0, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(2, 'BTP', 'BTP', '08:00:00', '15:00:00', '13:00:00', '21:00:00', '15:00:00', '23:00:00', -5.12957150, 119.50036078, 50, 0, 60, 15, 0, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(3, 'Citraland', 'CIT', '07:00:00', '15:00:00', '13:00:00', '21:00:00', '15:00:00', '23:00:00', -5.17994582, 119.46337357, 50, 0, 60, 15, 0, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(4, 'Kaori HQ', 'KHQ', '07:00:00', '23:59:59', '07:00:00', '23:59:59', '07:00:00', '23:59:59', 0.00000000, 0.00000000, 999999999, 1, 60, 15, 0, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09');

--
-- Trigger `outlets`
--
DELIMITER $$
CREATE TRIGGER `tr_outlets_updated_at` BEFORE UPDATE ON `outlets` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `overwork_requests`
--

CREATE TABLE `overwork_requests` (
  `id` int(11) NOT NULL COMMENT 'Unique overwork identifier',
  `attendance_id` int(11) NOT NULL COMMENT 'Referensi ke attendance record',
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke user',
  `tanggal` date NOT NULL COMMENT 'Tanggal overwork',
  `jam_overwork` decimal(4,2) NOT NULL COMMENT 'Jam overwork yang diminta (max 8 jam)',
  `alasan` text DEFAULT NULL COMMENT 'Alasan dan detail overwork',
  `status` enum('pending','disetujui','ditolak') DEFAULT 'pending' COMMENT 'Status approval',
  `disetujui_oleh` int(11) DEFAULT NULL COMMENT 'User ID yang menyetujui',
  `catatan_admin` text DEFAULT NULL COMMENT 'Catatan admin saat approval',
  `disetujui_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu persetujuan',
  `overwork_rate` decimal(8,2) DEFAULT 6250.00 COMMENT 'Rate per jam (6250 untuk semua posisi)',
  `total_overwork_payment` decimal(10,2) DEFAULT 0.00 COMMENT 'Total payment overwork',
  `request_created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu request dibuat',
  `response_time_hours` decimal(4,2) DEFAULT NULL COMMENT 'Response time dalam jam'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Overtime request management dengan calculation';

--
-- Trigger `overwork_requests`
--
DELIMITER $$
CREATE TRIGGER `tr_overwork_requests_calculate_payment` BEFORE INSERT ON `overwork_requests` FOR EACH ROW BEGIN
    SET NEW.total_overwork_payment = NEW.jam_overwork * NEW.overwork_rate;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_overwork_requests_calculate_payment_update` BEFORE UPDATE ON `overwork_requests` FOR EACH ROW BEGIN
    SET NEW.total_overwork_payment = NEW.jam_overwork * NEW.overwork_rate;
    
    -- Calculate response time if approved
    IF NEW.status = 'disetujui' AND NEW.disetujui_at IS NOT NULL AND OLD.status != 'disetujui' THEN
        SET NEW.response_time_hours = TIMESTAMPDIFF(HOUR, NEW.request_created_at, NEW.disetujui_at);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `payroll_records`
--

CREATE TABLE `payroll_records` (
  `id` int(11) NOT NULL COMMENT 'Unique payroll record identifier',
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke user',
  `bulan` tinyint(4) NOT NULL COMMENT 'Bulan payroll',
  `tahun` smallint(6) NOT NULL COMMENT 'Tahun payroll',
  `gaji_pokok` decimal(12,2) NOT NULL COMMENT 'Gaji pokok bulanan',
  `tunjangan_makan_total` decimal(10,2) NOT NULL COMMENT 'Total tunjangan makan',
  `tunjangan_transportasi_total` decimal(10,2) NOT NULL COMMENT 'Total tunjangan transport',
  `tunjangan_jabatan` decimal(10,2) NOT NULL COMMENT 'Total tunjangan jabatan',
  `hutang_toko` decimal(10,2) DEFAULT 0.00 COMMENT 'Hutang toko',
  `kasbon` decimal(10,2) DEFAULT 0.00 COMMENT 'Kasbon',
  `bonus_marketing` decimal(10,2) DEFAULT 0.00 COMMENT 'Bonus marketing',
  `insentif_omset` decimal(10,2) DEFAULT 0.00 COMMENT 'Insentif omset',
  `overwork_bonus` decimal(10,2) DEFAULT 0.00 COMMENT 'Total bonus overwork',
  `late_deduction` decimal(10,2) DEFAULT 0.00 COMMENT 'Potongan keterlambatan',
  `total_gaji` decimal(12,2) NOT NULL COMMENT 'Total gaji bruto',
  `total_potongan` decimal(10,2) DEFAULT 0.00 COMMENT 'Total potongan',
  `gaji_bersih` decimal(12,2) NOT NULL COMMENT 'Gaji bersih setelah potongan',
  `days_present` int(11) DEFAULT 0 COMMENT 'Jumlah hari hadir',
  `days_absent` int(11) DEFAULT 0 COMMENT 'Jumlah hari tidak hadir',
  `days_late` int(11) DEFAULT 0 COMMENT 'Jumlah hari terlambat',
  `total_late_minutes` int(11) DEFAULT 0 COMMENT 'Total menit terlambat',
  `total_overwork_hours` decimal(6,2) DEFAULT 0.00 COMMENT 'Total jam overwork',
  `attendance_breakdown` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Breakdown detail attendance per hari' CHECK (json_valid(`attendance_breakdown`)),
  `calculation_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Detail perhitungan untuk audit' CHECK (json_valid(`calculation_details`)),
  `status` enum('draft','selesai','dibayar') DEFAULT 'draft' COMMENT 'Status payroll',
  `slip_gaji_path` varchar(255) DEFAULT NULL COMMENT 'Path file slip gaji PDF',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan',
  `reviewed_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu review',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu pembayaran',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan record',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu update terakhir'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Final payroll archive dengan complete calculation';

--
-- Trigger `payroll_records`
--
DELIMITER $$
CREATE TRIGGER `tr_payroll_records_calculate_gaji` BEFORE INSERT ON `payroll_records` FOR EACH ROW BEGIN
    SET NEW.total_gaji = NEW.gaji_pokok + NEW.tunjangan_makan_total +
                        NEW.tunjangan_transportasi_total + NEW.tunjangan_jabatan +
                        NEW.overwork_bonus + NEW.hutang_toko + NEW.kasbon +
                        NEW.bonus_marketing + NEW.insentif_omset;
    
    SET NEW.total_potongan = NEW.late_deduction;
    SET NEW.gaji_bersih = NEW.total_gaji - NEW.total_potongan;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_payroll_records_calculate_gaji_update` BEFORE UPDATE ON `payroll_records` FOR EACH ROW BEGIN
    SET NEW.total_gaji = NEW.gaji_pokok + NEW.tunjangan_makan_total +
                        NEW.tunjangan_transportasi_total + NEW.tunjangan_jabatan +
                        NEW.overwork_bonus + NEW.hutang_toko + NEW.kasbon +
                        NEW.bonus_marketing + NEW.insentif_omset;
    
    SET NEW.total_potongan = NEW.late_deduction;
    SET NEW.gaji_bersih = NEW.total_gaji - NEW.total_potongan;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_payroll_records_updated_at` BEFORE UPDATE ON `payroll_records` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `payroll_temp`
--

CREATE TABLE `payroll_temp` (
  `id` int(11) NOT NULL COMMENT 'Unique payroll temp identifier',
  `position_id` int(11) NOT NULL COMMENT 'Referensi ke posisi',
  `bulan` tinyint(4) NOT NULL COMMENT 'Bulan payroll',
  `tahun` smallint(6) NOT NULL COMMENT 'Tahun payroll',
  `hutang_toko` decimal(10,2) DEFAULT 0.00 COMMENT 'Hutang ke toko',
  `kasbon` decimal(10,2) DEFAULT 0.00 COMMENT 'Kasbon/pinjaman',
  `bonus_marketing` decimal(10,2) DEFAULT 0.00 COMMENT 'Bonus marketing',
  `insentif_omset` decimal(10,2) DEFAULT 0.00 COMMENT 'Insentif omset',
  `total_variable` decimal(12,2) DEFAULT 0.00 COMMENT 'Total variable components',
  `status` enum('generated','finance_input','ready_for_users','archived') DEFAULT 'generated' COMMENT 'Status payroll processing',
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu generate',
  `finance_input_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu input finance',
  `ready_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu ready untuk users',
  `archive_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu archive',
  `generated_by` int(11) NOT NULL COMMENT 'User ID yang generate',
  `finance_reviewed_by` int(11) DEFAULT NULL COMMENT 'User ID yang review finance'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Temporary payroll components untuk input variable';

--
-- Trigger `payroll_temp`
--
DELIMITER $$
CREATE TRIGGER `tr_payroll_temp_calculate_variable` BEFORE INSERT ON `payroll_temp` FOR EACH ROW BEGIN
    SET NEW.total_variable = NEW.hutang_toko + NEW.kasbon + NEW.bonus_marketing + NEW.insentif_omset;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_payroll_temp_calculate_variable_update` BEFORE UPDATE ON `payroll_temp` FOR EACH ROW BEGIN
    SET NEW.total_variable = NEW.hutang_toko + NEW.kasbon + NEW.bonus_marketing + NEW.insentif_omset;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `tr_payroll_temp_status_update` BEFORE UPDATE ON `payroll_temp` FOR EACH ROW BEGIN
    IF NEW.status != OLD.status THEN
        CASE NEW.status
            WHEN 'finance_input' THEN
                SET NEW.finance_input_at = CURRENT_TIMESTAMP;
            WHEN 'ready_for_users' THEN
                SET NEW.ready_at = CURRENT_TIMESTAMP;
            WHEN 'archived' THEN
                SET NEW.archive_at = CURRENT_TIMESTAMP;
            ELSE
                -- Do nothing for 'generated' or other statuses
                BEGIN END;
        END CASE;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL COMMENT 'Unique position identifier',
  `nama_posisi` varchar(50) NOT NULL COMMENT 'Nama posisi/jabatan',
  `level` enum('superadmin','admin','user') NOT NULL COMMENT 'Level akses dan wewenang',
  `deskripsi` text DEFAULT NULL COMMENT 'Deskripsi detail posisi dan tanggung jawab',
  `gaji_pokok` decimal(12,2) NOT NULL COMMENT 'Gaji pokok per bulan',
  `tunjangan_makan_per_hari` decimal(8,2) NOT NULL COMMENT 'Tunjangan makan per hari kerja',
  `tunjangan_transportasi_per_hari` decimal(8,2) NOT NULL COMMENT 'Tunjangan transport per hari kerja',
  `tunjangan_jabatan` decimal(10,2) DEFAULT 0.00 COMMENT 'Tunjangan jabatan per bulan',
  `overtime_rate_per_jam` decimal(8,2) DEFAULT 6250.00 COMMENT 'Rate overtime per jam (50000/8 jam)',
  `late_fee_per_minute` decimal(6,2) DEFAULT 500.00 COMMENT 'Denda keterlambatan per menit',
  `absent_penalty` decimal(10,2) DEFAULT 50000.00 COMMENT 'Denda tidak hadir per hari',
  `working_days_per_month` tinyint(4) DEFAULT 26 COMMENT 'Jumlah hari kerja standar per bulan',
  `status` enum('aktif','tidak_aktif') DEFAULT 'aktif' COMMENT 'Status posisi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu update terakhir'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master posisi dengan base salary components dan calculation rules';

--
-- Dumping data untuk tabel `positions`
--

INSERT INTO `positions` (`id`, `nama_posisi`, `level`, `deskripsi`, `gaji_pokok`, `tunjangan_makan_per_hari`, `tunjangan_transportasi_per_hari`, `tunjangan_jabatan`, `overtime_rate_per_jam`, `late_fee_per_minute`, `absent_penalty`, `working_days_per_month`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Finance', 'superadmin', 'Manajemen keuangan - Finance', 10000000.00, 0.00, 0.00, 0.00, 0.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(2, 'Owner', 'superadmin', 'Pemilik usaha - Owner', 10000000.00, 0.00, 0.00, 0.00, 0.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(3, 'HR', 'admin', 'Manajemen sumber daya manusia - HR', 1750000.00, 13462.00, 11538.00, 0.00, 6250.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(4, 'Akuntan', 'admin', 'Manajemen akuntansi - Akuntan', 1750000.00, 23077.00, 25000.00, 0.00, 6250.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(5, 'SCM', 'admin', 'Supply Chain Manager - SCM', 1750000.00, 23077.00, 25000.00, 0.00, 6250.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(6, 'Marketing', 'admin', 'Manajemen pemasaran - Marketing', 1750000.00, 23077.00, 25000.00, 0.00, 6250.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(7, 'Kepala Toko', 'admin', 'Kepala operasional toko - Kepala Toko', 1750000.00, 7692.00, 7692.00, 0.00, 6250.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(8, 'Barista', 'user', 'Karyawan barista - Barista', 1500000.00, 7692.00, 7692.00, 250000.00, 6250.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(9, 'Kitchen', 'user', 'Karyawan dapur - Kitchen', 1500000.00, 7692.00, 7692.00, 0.00, 6250.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(10, 'Server', 'user', 'Karyawan pelayanan - Server', 1300000.00, 7692.00, 7692.00, 0.00, 6250.00, 500.00, 50000.00, 26, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09');

--
-- Trigger `positions`
--
DELIMITER $$
CREATE TRIGGER `tr_positions_updated_at` BEFORE UPDATE ON `positions` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `shifts`
--

CREATE TABLE `shifts` (
  `id` int(11) NOT NULL COMMENT 'Unique shift identifier',
  `outlet_id` int(11) NOT NULL COMMENT 'Referensi ke outlet',
  `nama_shift` varchar(50) NOT NULL COMMENT 'Nama descriptive shift',
  `shift_number` int(11) NOT NULL COMMENT 'Nomor shift untuk mapping ke outlets.jam_masuk_shift_X',
  `jam_masuk` time NOT NULL COMMENT 'Jam masuk shift',
  `jam_keluar` time NOT NULL COMMENT 'Jam keluar shift',
  `estimated_hours` decimal(4,2) DEFAULT 8.00 COMMENT 'Estimasi durasi jam kerja',
  `break_duration` int(11) DEFAULT 60 COMMENT 'Durasi break dalam menit',
  `status` enum('aktif','tidak_aktif') DEFAULT 'aktif' COMMENT 'Status shift',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu update'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master shift types dengan dynamic configuration';

--
-- Dumping data untuk tabel `shifts`
--

INSERT INTO `shifts` (`id`, `outlet_id`, `nama_shift`, `shift_number`, `jam_masuk`, `jam_keluar`, `estimated_hours`, `break_duration`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Shift Pagi', 1, '07:00:00', '15:00:00', 8.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(2, 1, 'Shift Middle', 2, '12:00:00', '20:00:00', 8.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(3, 1, 'Shift Sore', 3, '15:00:00', '23:00:00', 8.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(4, 2, 'Shift Pagi', 1, '08:00:00', '15:00:00', 7.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(5, 2, 'Shift Middle', 2, '13:00:00', '21:00:00', 8.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(6, 2, 'Shift Sore', 3, '15:00:00', '23:00:00', 8.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(7, 3, 'Shift Pagi', 1, '07:00:00', '15:00:00', 8.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(8, 3, 'Shift Middle', 2, '13:00:00', '21:00:00', 8.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09'),
(9, 3, 'Shift Sore', 3, '15:00:00', '23:00:00', 8.00, 60, 'aktif', '2025-11-12 13:17:09', '2025-11-12 13:17:09');

--
-- Trigger `shifts`
--
DELIMITER $$
CREATE TRIGGER `tr_shifts_updated_at` BEFORE UPDATE ON `shifts` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `shift_assignments`
--

CREATE TABLE `shift_assignments` (
  `id` int(11) NOT NULL COMMENT 'Unique assignment identifier',
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke user/karyawan',
  `shift_id` int(11) NOT NULL COMMENT 'Referensi ke shift',
  `tanggal` date NOT NULL COMMENT 'Tanggal shift',
  `outlet_id` int(11) NOT NULL COMMENT 'Referensi ke outlet',
  `status` enum('d_assign','disetujui','reschedule','sakit','izin','ditolak','completed') DEFAULT 'd_assign' COMMENT 'Status workflow shift assignment',
  `dibuat_oleh` int(11) NOT NULL COMMENT 'User ID yang membuat assignment',
  `disetujui_oleh` int(11) DEFAULT NULL COMMENT 'User ID yang menyetujui',
  `catatan` text DEFAULT NULL COMMENT 'Catatan dan instruksi shift',
  `reschedule_reason` text DEFAULT NULL COMMENT 'Alasan reschedule jika ada',
  `auto_lock_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu auto-lock setelah disetujui',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu update',
  `telegram_user_id` varchar(50) DEFAULT NULL,
  `telegram_confirmed_at` timestamp NULL DEFAULT NULL,
  `telegram_message_id` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Shift assignment dengan simplified 7-status workflow';

--
-- Trigger `shift_assignments`
--
DELIMITER $$
CREATE TRIGGER `tr_shift_assignments_updated_at` BEFORE UPDATE ON `shift_assignments` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `telegram_bots`
--

CREATE TABLE `telegram_bots` (
  `id` int(11) NOT NULL,
  `bot_token` varchar(255) NOT NULL COMMENT 'Bot Token from @BotFather',
  `bot_username` varchar(100) NOT NULL COMMENT 'Username bot (@username)',
  `bot_name` varchar(200) NOT NULL COMMENT 'Nama bot',
  `webhook_url` varchar(500) DEFAULT NULL COMMENT 'Webhook URL untuk receive updates',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Status bot aktif/nonaktif',
  `created_by` int(11) NOT NULL COMMENT 'User ID yang membuat bot',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Master Telegram Bot Management';

-- --------------------------------------------------------

--
-- Struktur dari tabel `telegram_commands`
--

CREATE TABLE `telegram_commands` (
  `id` int(11) NOT NULL,
  `command_name` varchar(100) NOT NULL COMMENT 'Command name tanpa slash (/start, /help, dll)',
  `description` text NOT NULL COMMENT 'Deskripsi command',
  `required_role` enum('superadmin','admin','user','guest') DEFAULT 'user' COMMENT 'Role minimum untuk command',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Status command aktif/nonaktif',
  `help_text` text DEFAULT NULL COMMENT 'Help text untuk command',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Master Telegram Bot Commands';

-- --------------------------------------------------------

--
-- Struktur dari tabel `telegram_conversations`
--

CREATE TABLE `telegram_conversations` (
  `id` int(11) NOT NULL,
  `telegram_user_id` varchar(50) NOT NULL COMMENT 'Telegram user ID (chat_id)',
  `user_id` int(11) DEFAULT NULL COMMENT 'Link ke users table',
  `bot_id` int(11) NOT NULL COMMENT 'Referensi ke telegram_bots',
  `conversation_state` varchar(50) DEFAULT 'idle' COMMENT 'State machine untuk conversation',
  `context_data` longtext DEFAULT NULL COMMENT 'JSON context data untuk conversation',
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Telegram conversation state management';

-- --------------------------------------------------------

--
-- Struktur dari tabel `telegram_file_shares`
--

CREATE TABLE `telegram_file_shares` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` enum('attendance_photo','leave_document','payroll_slip','certificate') NOT NULL,
  `telegram_file_id` varchar(200) DEFAULT NULL,
  `shared_via` enum('telegram_bot','email','manual') DEFAULT 'telegram_bot',
  `download_count` int(11) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `is_secure` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Telegram file sharing audit trail';

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL COMMENT 'Unique user identifier',
  `username` varchar(50) NOT NULL COMMENT 'Username untuk login',
  `email` varchar(100) NOT NULL COMMENT 'Email address untuk login dan komunikasi',
  `password` varchar(255) NOT NULL COMMENT 'Hashed password',
  `nama_lengkap` varchar(100) NOT NULL COMMENT 'Nama lengkap user',
  `position_id` int(11) NOT NULL COMMENT 'Referensi ke posisi',
  `outlet_id` int(11) NOT NULL COMMENT 'Referensi ke outlet/cabang',
  `nomor_telegram` varchar(20) DEFAULT NULL COMMENT 'Nomor Telegram untuk notifikasi',
  `telegram_id` varchar(50) DEFAULT NULL COMMENT 'Telegram Bot ID untuk personal messages',
  `foto_profil` varchar(255) DEFAULT NULL COMMENT 'Path foto profil',
  `tanda_tangan` varchar(255) DEFAULT NULL COMMENT 'Path tanda tangan digital',
  `whitelist_email` varchar(100) NOT NULL COMMENT 'Email yang terdaftar dalam whitelist',
  `registration_token` varchar(100) DEFAULT NULL COMMENT 'Token untuk registrasi',
  `registration_expires_at` timestamp NULL DEFAULT NULL COMMENT 'Token expiration time',
  `registration_status` enum('pending','registered') DEFAULT 'pending' COMMENT 'Status registrasi',
  `registered_from_whitelist_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu registrasi berhasil dari whitelist',
  `status` enum('aktif','tidak_aktif','suspended') DEFAULT 'aktif' COMMENT 'Status user account',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu login terakhir',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu pembuatan account',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Waktu update terakhir'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Consolidated user management (users + profiles + whitelist + telegram)';

--
-- Trigger `users`
--
DELIMITER $$
CREATE TRIGGER `tr_users_updated_at` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `user_notification_preferences`
--

CREATE TABLE `user_notification_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Referensi ke users',
  `notification_type` varchar(100) NOT NULL COMMENT 'Tipe notifikasi',
  `channel` enum('email','telegram','both','none') DEFAULT 'both' COMMENT 'Channel notifikasi',
  `is_enabled` tinyint(1) DEFAULT 1 COMMENT 'Status aktif/nonaktif',
  `custom_settings` longtext DEFAULT NULL COMMENT 'JSON custom settings per user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='User notification preferences management';

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_attendance_summary`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_attendance_summary` (
`user_id` int(11)
,`nama_lengkap` varchar(100)
,`nama_outlet` varchar(50)
,`nama_posisi` varchar(50)
,`tahun` int(4)
,`bulan` int(2)
,`total_hadir` bigint(21)
,`total_terlambat_tanpa_potongan` bigint(21)
,`total_terlambat_dengan_potongan` bigint(21)
,`total_izin` bigint(21)
,`total_sakit` bigint(21)
,`total_tidak_hadir` bigint(21)
,`total_overwork_jam` decimal(26,2)
,`total_terlambat_menit` decimal(32,0)
,`total_hari_kerja` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_leave_requests_detail`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_leave_requests_detail` (
`id` int(11)
,`user_id` int(11)
,`nama_lengkap` varchar(100)
,`jenis` enum('izin','sakit')
,`tanggal_mulai` date
,`tanggal_selesai` date
,`perfihal` varchar(100)
,`alasan_izin` text
,`status` enum('pending','disetujui','ditolak','expired')
,`surat_dokter` varchar(255)
,`created_at` timestamp
,`approved_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_overwork_summary`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_overwork_summary` (
`user_id` int(11)
,`nama_lengkap` varchar(100)
,`tanggal` date
,`jam_overwork` decimal(4,2)
,`alasan` text
,`status` enum('pending','disetujui','ditolak')
,`overwork_rate` decimal(8,2)
,`total_overwork_payment` decimal(10,2)
,`request_created_at` timestamp
,`disetujui_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_payroll_detail`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_payroll_detail` (
`id` int(11)
,`user_id` int(11)
,`nama_pegawai` varchar(100)
,`nama_outlet` varchar(50)
,`nama_posisi` varchar(50)
,`bulan` tinyint(4)
,`tahun` smallint(6)
,`status` enum('draft','selesai','dibayar')
,`total_gaji` decimal(12,2)
,`total_potongan` decimal(10,2)
,`gaji_bersih` decimal(12,2)
,`days_present` int(11)
,`days_absent` int(11)
,`days_late` int(11)
,`overwork_bonus` decimal(10,2)
,`late_deduction` decimal(10,2)
,`generated_at` timestamp
,`paid_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_shift_assignments_detail`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_shift_assignments_detail` (
`id` int(11)
,`user_id` int(11)
,`nama_user` varchar(100)
,`shift_id` int(11)
,`nama_shift` varchar(50)
,`tanggal` date
,`outlet_id` int(11)
,`nama_outlet` varchar(50)
,`jam_masuk` time
,`jam_keluar` time
,`status` enum('d_assign','disetujui','reschedule','sakit','izin','ditolak','completed')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in struktur untuk tampilan `v_users_detail`
-- (Lihat di bawah untuk tampilan aktual)
--
CREATE TABLE `v_users_detail` (
`id` int(11)
,`username` varchar(50)
,`email` varchar(100)
,`nama_lengkap` varchar(100)
,`status_user` enum('aktif','tidak_aktif','suspended')
,`nomor_telegram` varchar(20)
,`telegram_id` varchar(50)
,`nama_posisi` varchar(50)
,`level` enum('superadmin','admin','user')
,`gaji_pokok` decimal(12,2)
,`overtime_rate_per_jam` decimal(8,2)
,`nama_outlet` varchar(50)
,`kode_outlet` varchar(10)
,`created_at` timestamp
,`last_login_at` timestamp
,`registration_status` enum('pending','registered')
);

-- --------------------------------------------------------

--
-- Struktur untuk view `v_attendance_summary`
--
DROP TABLE IF EXISTS `v_attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_attendance_summary`  AS SELECT `u`.`id` AS `user_id`, `u`.`nama_lengkap` AS `nama_lengkap`, `o`.`nama_outlet` AS `nama_outlet`, `p`.`nama_posisi` AS `nama_posisi`, year(`a`.`tanggal`) AS `tahun`, month(`a`.`tanggal`) AS `bulan`, count(case when `a`.`status` = 'hadir' then 1 end) AS `total_hadir`, count(case when `a`.`status` = 'terlambat_tanpa_potongan' then 1 end) AS `total_terlambat_tanpa_potongan`, count(case when `a`.`status` = 'terlambat_dengan_potongan' then 1 end) AS `total_terlambat_dengan_potongan`, count(case when `a`.`status` = 'izin' then 1 end) AS `total_izin`, count(case when `a`.`status` = 'sakit' then 1 end) AS `total_sakit`, count(case when `a`.`status` = 'tidak_hadir' then 1 end) AS `total_tidak_hadir`, sum(`a`.`overwork_jam`) AS `total_overwork_jam`, sum(`a`.`terlambat_menit`) AS `total_terlambat_menit`, count(0) AS `total_hari_kerja` FROM (((`users` `u` join `positions` `p` on(`u`.`position_id` = `p`.`id`)) join `outlets` `o` on(`u`.`outlet_id` = `o`.`id`)) join `attendance` `a` on(`u`.`id` = `a`.`user_id`)) GROUP BY `u`.`id`, year(`a`.`tanggal`), month(`a`.`tanggal`) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `v_leave_requests_detail`
--
DROP TABLE IF EXISTS `v_leave_requests_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_leave_requests_detail`  AS SELECT `lr`.`id` AS `id`, `lr`.`user_id` AS `user_id`, `u`.`nama_lengkap` AS `nama_lengkap`, `lr`.`jenis` AS `jenis`, `lr`.`tanggal_mulai` AS `tanggal_mulai`, `lr`.`tanggal_selesai` AS `tanggal_selesai`, `lr`.`perfihal` AS `perfihal`, `lr`.`alasan_izin` AS `alasan_izin`, `lr`.`status` AS `status`, `lr`.`surat_dokter` AS `surat_dokter`, `lr`.`created_at` AS `created_at`, `lr`.`approved_at` AS `approved_at` FROM (`leave_requests` `lr` join `users` `u` on(`lr`.`user_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `v_overwork_summary`
--
DROP TABLE IF EXISTS `v_overwork_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_overwork_summary`  AS SELECT `o`.`user_id` AS `user_id`, `u`.`nama_lengkap` AS `nama_lengkap`, `o`.`tanggal` AS `tanggal`, `o`.`jam_overwork` AS `jam_overwork`, `o`.`alasan` AS `alasan`, `o`.`status` AS `status`, `o`.`overwork_rate` AS `overwork_rate`, `o`.`total_overwork_payment` AS `total_overwork_payment`, `o`.`request_created_at` AS `request_created_at`, `o`.`disetujui_at` AS `disetujui_at` FROM (`overwork_requests` `o` join `users` `u` on(`o`.`user_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `v_payroll_detail`
--
DROP TABLE IF EXISTS `v_payroll_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_payroll_detail`  AS SELECT `pr`.`id` AS `id`, `pr`.`user_id` AS `user_id`, `u`.`nama_lengkap` AS `nama_pegawai`, `o`.`nama_outlet` AS `nama_outlet`, `p`.`nama_posisi` AS `nama_posisi`, `pr`.`bulan` AS `bulan`, `pr`.`tahun` AS `tahun`, `pr`.`status` AS `status`, `pr`.`total_gaji` AS `total_gaji`, `pr`.`total_potongan` AS `total_potongan`, `pr`.`gaji_bersih` AS `gaji_bersih`, `pr`.`days_present` AS `days_present`, `pr`.`days_absent` AS `days_absent`, `pr`.`days_late` AS `days_late`, `pr`.`overwork_bonus` AS `overwork_bonus`, `pr`.`late_deduction` AS `late_deduction`, `pr`.`generated_at` AS `generated_at`, `pr`.`paid_at` AS `paid_at` FROM (((`payroll_records` `pr` join `users` `u` on(`pr`.`user_id` = `u`.`id`)) join `positions` `p` on(`u`.`position_id` = `p`.`id`)) join `outlets` `o` on(`u`.`outlet_id` = `o`.`id`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `v_shift_assignments_detail`
--
DROP TABLE IF EXISTS `v_shift_assignments_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_shift_assignments_detail`  AS SELECT `sa`.`id` AS `id`, `sa`.`user_id` AS `user_id`, `u`.`nama_lengkap` AS `nama_user`, `sa`.`shift_id` AS `shift_id`, `s`.`nama_shift` AS `nama_shift`, `sa`.`tanggal` AS `tanggal`, `sa`.`outlet_id` AS `outlet_id`, `o`.`nama_outlet` AS `nama_outlet`, `s`.`jam_masuk` AS `jam_masuk`, `s`.`jam_keluar` AS `jam_keluar`, `sa`.`status` AS `status`, `sa`.`created_at` AS `created_at` FROM (((`shift_assignments` `sa` join `users` `u` on(`sa`.`user_id` = `u`.`id`)) join `shifts` `s` on(`sa`.`shift_id` = `s`.`id`)) join `outlets` `o` on(`sa`.`outlet_id` = `o`.`id`)) ;

-- --------------------------------------------------------

--
-- Struktur untuk view `v_users_detail`
--
DROP TABLE IF EXISTS `v_users_detail`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_users_detail`  AS SELECT `u`.`id` AS `id`, `u`.`username` AS `username`, `u`.`email` AS `email`, `u`.`nama_lengkap` AS `nama_lengkap`, `u`.`status` AS `status_user`, `u`.`nomor_telegram` AS `nomor_telegram`, `u`.`telegram_id` AS `telegram_id`, `p`.`nama_posisi` AS `nama_posisi`, `p`.`level` AS `level`, `p`.`gaji_pokok` AS `gaji_pokok`, `p`.`overtime_rate_per_jam` AS `overtime_rate_per_jam`, `o`.`nama_outlet` AS `nama_outlet`, `o`.`kode_outlet` AS `kode_outlet`, `u`.`created_at` AS `created_at`, `u`.`last_login_at` AS `last_login_at`, `u`.`registration_status` AS `registration_status` FROM ((`users` `u` join `positions` `p` on(`u`.`position_id` = `p`.`id`)) join `outlets` `o` on(`u`.`outlet_id` = `o`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`tanggal`),
  ADD KEY `idx_attendance_user_date` (`user_id`,`tanggal`),
  ADD KEY `idx_attendance_tanggal_status` (`tanggal`,`status`),
  ADD KEY `idx_attendance_overwork` (`overwork_status`),
  ADD KEY `idx_attendance_late` (`terlambat_menit`),
  ADD KEY `idx_attendance_shift_assignment` (`shift_assignment_id`),
  ADD KEY `idx_attendance_location` (`latitude_masuk`,`longitude_masuk`),
  ADD KEY `idx_attendance_user_date_status` (`user_id`,`tanggal`,`status`);

--
-- Indeks untuk tabel `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_logs_queue` (`queue_id`),
  ADD KEY `idx_email_logs_recipient` (`recipient_email`),
  ADD KEY `idx_email_logs_status` (`status`),
  ADD KEY `idx_email_logs_user` (`user_id`),
  ADD KEY `idx_email_logs_created` (`created_at`);

--
-- Indeks untuk tabel `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_queue_status` (`status`),
  ADD KEY `idx_email_queue_scheduled` (`scheduled_at`),
  ADD KEY `idx_email_queue_priority` (`priority`),
  ADD KEY `idx_email_queue_recipient` (`recipient_email`),
  ADD KEY `idx_email_queue_template` (`template_id`),
  ADD KEY `fk_email_queue_created_by` (`created_by`);

--
-- Indeks untuk tabel `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_template_name` (`template_name`),
  ADD KEY `idx_template_type` (`template_type`),
  ADD KEY `idx_template_active` (`is_active`),
  ADD KEY `fk_email_templates_created_by` (`created_by`);

--
-- Indeks untuk tabel `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_leave_requests_user_date` (`user_id`,`tanggal_mulai`,`tanggal_selesai`),
  ADD KEY `idx_leave_requests_status` (`status`),
  ADD KEY `idx_leave_requests_type` (`jenis`),
  ADD KEY `idx_leave_requests_approval` (`disetujui_oleh`,`approved_at`),
  ADD KEY `idx_leave_requests_expiry` (`expired_at`),
  ADD KEY `idx_leave_requests_shift_assignment` (`shift_assignment_id`),
  ADD KEY `idx_leave_requests_type_status_dates` (`jenis`,`status`,`tanggal_mulai`,`tanggal_selesai`);

--
-- Indeks untuk tabel `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_logs_user` (`user_id`),
  ADD KEY `idx_notification_logs_type` (`notification_type`),
  ADD KEY `idx_notification_logs_date` (`sent_at`),
  ADD KEY `idx_notification_logs_status` (`status`),
  ADD KEY `idx_notification_logs_related` (`related_type`,`related_id`),
  ADD KEY `idx_notification_logs_telegram` (`telegram_message_id`,`telegram_chat_id`),
  ADD KEY `idx_notification_logs_type_status_scheduled` (`notification_type`,`status`,`sent_at`);

--
-- Indeks untuk tabel `outlets`
--
ALTER TABLE `outlets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_outlet` (`nama_outlet`),
  ADD UNIQUE KEY `kode_outlet` (`kode_outlet`),
  ADD KEY `idx_outlets_status` (`status`),
  ADD KEY `idx_outlets_name` (`nama_outlet`),
  ADD KEY `idx_outlets_code` (`kode_outlet`),
  ADD KEY `idx_outlets_attendance_config` (`attendance_early_start_minutes`,`attendance_late_end_minutes`),
  ADD KEY `idx_outlets_location` (`latitude`,`longitude`);

--
-- Indeks untuk tabel `overwork_requests`
--
ALTER TABLE `overwork_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_overwork_requests_user_status` (`user_id`,`status`),
  ADD KEY `idx_overwork_requests_date` (`tanggal`),
  ADD KEY `idx_overwork_requests_attendance` (`attendance_id`),
  ADD KEY `idx_overwork_requests_approval` (`disetujui_oleh`,`disetujui_at`),
  ADD KEY `idx_overwork_requests_response_time` (`response_time_hours`),
  ADD KEY `idx_overwork_requests_payment` (`total_overwork_payment`),
  ADD KEY `idx_overwork_requests_type_status_date` (`user_id`,`status`,`tanggal`);

--
-- Indeks untuk tabel `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_month` (`user_id`,`bulan`,`tahun`),
  ADD KEY `idx_payroll_records_user_period` (`user_id`,`bulan`,`tahun`),
  ADD KEY `idx_payroll_records_status` (`status`),
  ADD KEY `idx_payroll_records_payment` (`paid_at`),
  ADD KEY `idx_payroll_records_generation` (`generated_at`),
  ADD KEY `idx_payroll_records_gaji_bersih` (`gaji_bersih`),
  ADD KEY `idx_payroll_records_attendance` (`days_present`,`days_late`),
  ADD KEY `idx_payroll_records_status_period` (`status`,`bulan`,`tahun`);

--
-- Indeks untuk tabel `payroll_temp`
--
ALTER TABLE `payroll_temp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_position_period` (`position_id`,`bulan`,`tahun`),
  ADD KEY `idx_payroll_temp_status` (`status`),
  ADD KEY `idx_payroll_temp_period` (`bulan`,`tahun`),
  ADD KEY `idx_payroll_temp_generated_by` (`generated_by`),
  ADD KEY `idx_payroll_temp_archive` (`archive_at`),
  ADD KEY `idx_payroll_temp_finance_review` (`finance_reviewed_by`);

--
-- Indeks untuk tabel `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_posisi` (`nama_posisi`),
  ADD KEY `idx_positions_level` (`level`),
  ADD KEY `idx_positions_name` (`nama_posisi`),
  ADD KEY `idx_positions_status` (`status`),
  ADD KEY `idx_positions_base_salary` (`gaji_pokok`,`tunjangan_makan_per_hari`);

--
-- Indeks untuk tabel `shifts`
--
ALTER TABLE `shifts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_outlet_shift` (`outlet_id`,`shift_number`),
  ADD KEY `idx_shifts_outlet` (`outlet_id`),
  ADD KEY `idx_shifts_number` (`shift_number`),
  ADD KEY `idx_shifts_status` (`status`),
  ADD KEY `idx_shifts_name` (`nama_shift`),
  ADD KEY `idx_shifts_times` (`jam_masuk`,`jam_keluar`);

--
-- Indeks untuk tabel `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date_shift` (`user_id`,`tanggal`,`shift_id`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `disetujui_oleh` (`disetujui_oleh`),
  ADD KEY `idx_shift_assignments_user_date` (`user_id`,`tanggal`),
  ADD KEY `idx_shift_assignments_date_outlet` (`tanggal`,`outlet_id`),
  ADD KEY `idx_shift_assignments_status` (`status`),
  ADD KEY `idx_shift_assignments_approval` (`dibuat_oleh`,`disetujui_oleh`),
  ADD KEY `idx_shift_assignments_shift` (`shift_id`),
  ADD KEY `idx_shift_assignments_date_outlet_status` (`tanggal`,`outlet_id`,`status`);

--
-- Indeks untuk tabel `telegram_bots`
--
ALTER TABLE `telegram_bots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`bot_username`),
  ADD KEY `idx_bot_active` (`is_active`),
  ADD KEY `fk_telegram_bots_created_by` (`created_by`);

--
-- Indeks untuk tabel `telegram_commands`
--
ALTER TABLE `telegram_commands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_command` (`command_name`),
  ADD KEY `idx_command_role` (`required_role`),
  ADD KEY `idx_command_active` (`is_active`);

--
-- Indeks untuk tabel `telegram_conversations`
--
ALTER TABLE `telegram_conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_telegram_user_bot` (`telegram_user_id`,`bot_id`),
  ADD KEY `idx_conversation_user` (`user_id`),
  ADD KEY `idx_conversation_state` (`conversation_state`),
  ADD KEY `idx_conversation_activity` (`last_activity`),
  ADD KEY `fk_conversations_bot` (`bot_id`);

--
-- Indeks untuk tabel `telegram_file_shares`
--
ALTER TABLE `telegram_file_shares`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_shares_user` (`user_id`),
  ADD KEY `idx_file_shares_type` (`file_type`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `outlet_id` (`outlet_id`),
  ADD KEY `idx_users_auth` (`username`,`email`),
  ADD KEY `idx_users_whitelist` (`whitelist_email`),
  ADD KEY `idx_users_position_outlet` (`position_id`,`outlet_id`),
  ADD KEY `idx_users_status` (`status`),
  ADD KEY `idx_users_telegram` (`telegram_id`),
  ADD KEY `idx_users_registration` (`registration_status`),
  ADD KEY `idx_users_last_login` (`last_login_at`);

--
-- Indeks untuk tabel `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_notification` (`user_id`,`notification_type`),
  ADD KEY `idx_preferences_channel` (`channel`),
  ADD KEY `idx_preferences_enabled` (`is_enabled`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique attendance identifier';

--
-- AUTO_INCREMENT untuk tabel `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique leave identifier';

--
-- AUTO_INCREMENT untuk tabel `notification_logs`
--
ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique notification identifier';

--
-- AUTO_INCREMENT untuk tabel `outlets`
--
ALTER TABLE `outlets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique outlet identifier', AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `overwork_requests`
--
ALTER TABLE `overwork_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique overwork identifier';

--
-- AUTO_INCREMENT untuk tabel `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique payroll record identifier';

--
-- AUTO_INCREMENT untuk tabel `payroll_temp`
--
ALTER TABLE `payroll_temp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique payroll temp identifier';

--
-- AUTO_INCREMENT untuk tabel `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique position identifier', AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT untuk tabel `shifts`
--
ALTER TABLE `shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique shift identifier', AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT untuk tabel `shift_assignments`
--
ALTER TABLE `shift_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique assignment identifier';

--
-- AUTO_INCREMENT untuk tabel `telegram_bots`
--
ALTER TABLE `telegram_bots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `telegram_commands`
--
ALTER TABLE `telegram_commands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `telegram_conversations`
--
ALTER TABLE `telegram_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `telegram_file_shares`
--
ALTER TABLE `telegram_file_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Unique user identifier';

--
-- AUTO_INCREMENT untuk tabel `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`shift_assignment_id`) REFERENCES `shift_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `fk_email_logs_queue` FOREIGN KEY (`queue_id`) REFERENCES `email_queue` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `email_queue`
--
ALTER TABLE `email_queue`
  ADD CONSTRAINT `fk_email_queue_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_queue_template` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `email_templates`
--
ALTER TABLE `email_templates`
  ADD CONSTRAINT `fk_email_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`shift_assignment_id`) REFERENCES `shift_assignments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `leave_requests_ibfk_3` FOREIGN KEY (`disetujui_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `notification_logs`
--
ALTER TABLE `notification_logs`
  ADD CONSTRAINT `notification_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `overwork_requests`
--
ALTER TABLE `overwork_requests`
  ADD CONSTRAINT `overwork_requests_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `overwork_requests_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `overwork_requests_ibfk_3` FOREIGN KEY (`disetujui_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `payroll_temp`
--
ALTER TABLE `payroll_temp`
  ADD CONSTRAINT `payroll_temp_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `payroll_temp_ibfk_2` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `payroll_temp_ibfk_3` FOREIGN KEY (`finance_reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `shifts`
--
ALTER TABLE `shifts`
  ADD CONSTRAINT `shifts_ibfk_1` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `shift_assignments`
--
ALTER TABLE `shift_assignments`
  ADD CONSTRAINT `shift_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `shift_assignments_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `shift_assignments_ibfk_3` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `shift_assignments_ibfk_4` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `shift_assignments_ibfk_5` FOREIGN KEY (`disetujui_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `telegram_bots`
--
ALTER TABLE `telegram_bots`
  ADD CONSTRAINT `fk_telegram_bots_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `telegram_conversations`
--
ALTER TABLE `telegram_conversations`
  ADD CONSTRAINT `fk_conversations_bot` FOREIGN KEY (`bot_id`) REFERENCES `telegram_bots` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `telegram_file_shares`
--
ALTER TABLE `telegram_file_shares`
  ADD CONSTRAINT `fk_file_shares_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`outlet_id`) REFERENCES `outlets` (`id`) ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `user_notification_preferences`
--
ALTER TABLE `user_notification_preferences`
  ADD CONSTRAINT `fk_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
