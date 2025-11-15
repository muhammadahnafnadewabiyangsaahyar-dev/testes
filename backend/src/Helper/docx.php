<?php
// 1. Increase memory limit for document processing
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300); // 5 minutes

// 2. Start session & Include required files
session_start();
include_once('tbs/tbs_class.php');
include_once('tbs/tbs_plugin_opentbs.php');
include 'connect.php'; // Database connection

// 3. Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. PENJAGA GERBANG: Pastikan Pengguna Sudah Login
if (!isset($_SESSION['user_id'])) {
    // Jika tidak ada ID sesi, arahkan ke login
    header('Location: index.php?error=notloggedin'); 
    exit;
}
$user_id_session = $_SESSION['user_id'];

// 3. Ambil Data dari Formulir POST (Gunakan KEY YANG BENAR)
// Support both direct POST and query string parameters
$perihal_form = $_POST['perihal'] ?? $_GET['perihal'] ?? '';
$tanggal_mulai_form = $_POST['tanggal_izin'] ?? $_GET['tanggal_izin'] ?? ''; // Key: tanggal_izin
$tanggal_selesai_form = $_POST['tanggal_selesai'] ?? $_GET['tanggal_selesai'] ?? '';
$lama_izin_form = $_POST['lama_izin'] ?? $_GET['lama_izin'] ?? 0;       // Key: lama_izin
$alasan_form = $_POST['alasan_izin'] ?? $_GET['alasan_izin'] ?? '';     // Key: alasan_izin
$signature_data_form = $_POST['signature_data'] ?? $_GET['signature_data'] ?? ''; // Data tanda tangan dari canvas

// 4. Ambil Data Pegawai Lengkap dari Database (PDO)
$user_data = null;
$sql_user = "SELECT * FROM register WHERE id = ?";
$stmt_user = $pdo->prepare($sql_user);
$stmt_user->execute([$user_id_session]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    header('Location: index.php?error=penggunatidakditemukan');
    exit;
}

// 5. Validasi field wajib
if (empty($perihal_form) || empty($tanggal_mulai_form) || empty($tanggal_selesai_form) || empty($lama_izin_form) || empty($alasan_form)) {
    header('Location: suratizin.php?error=datakosong');
    exit;
}

// 6. Validasi tanda tangan: wajib sudah ada di profil ATAU dikirim dari form
if (empty($user_data['tanda_tangan_file']) && empty($signature_data_form)) {
    header('Location: suratizin.php?error=ttdkosong');
    exit;
}

// 7. Siapkan Variabel Lain untuk Template
$tanggal_hari_ini = date('j F Y');
$tanda_tangan_dir = 'uploads/tanda_tangan/';

// Jika belum ada tanda tangan tersimpan, simpan dari form
if (empty($user_data['tanda_tangan_file']) && !empty($signature_data_form)) {
    // Simpan tanda tangan dari form ke database dan file
    $nama_file_ttd_final = saveSignatureFromForm($signature_data_form, $user_id_session, $pdo);
    if (!$nama_file_ttd_final) {
        header('Location: suratizin.php?error=gagalsimpanttd');
        exit;
    }
} else {
    $nama_file_ttd_final = $user_data['tanda_tangan_file'];
}

$path_ttd_untuk_word = $tanda_tangan_dir . $nama_file_ttd_final;

// 8. Inisialisasi OpenTBS
$TBS = new clsTinyButStrong;
$TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);

// 9. Enhanced Template Loading with Validation
$template_file = 'template.docx';
if (!file_exists($template_file)) {
    log_error("Template file not found", ['template_file' => $template_file]);
    header('Location: suratizin.php?error=template_not_found');
    exit;
}

// Validate template file integrity
if (filesize($template_file) < 1024) { // Template should be at least 1KB
    log_error("Template file too small or corrupted", [
        'template_file' => $template_file,
        'file_size' => filesize($template_file)
    ]);
    header('Location: suratizin.php?error=template_corrupted');
    exit;
}

// Verify template file is actually a Word document
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $template_file);
finfo_close($finfo);

if (strpos($mime_type, 'zip') === false && strpos($mime_type, 'vnd.openxmlformats-officedocument') === false) {
    log_error("Template file is not a valid Word document", [
        'template_file' => $template_file,
        'mime_type' => $mime_type
    ]);
    header('Location: suratizin.php?error=template_invalid_format');
    exit;
}

try {
    // Set TBS to be more forgiving with templates and file operations
    $TBS->SetOption('opentbs_zip', 'auto');
    $TBS->SetOption('opentbs_verbose', 0);
    
    // CRITICAL: Enable NoErr property to prevent script termination on file overwrite errors
    $TBS->SetOption('opentbs_noerr', true);
    
    // Additional OpenTBS options for robust file handling
    $TBS->SetOption('opentbs_zip', 'auto');
    $TBS->SetOption('opentbs_tpl_allownew', true);
    $TBS->SetOption('opentbs_tpl_allownewpages', true);
    
    error_log("OpenTBS configured with NoErr=true for robust file handling");
    
    $TBS->LoadTemplate($template_file);
    error_log("Template loaded successfully: " . $template_file);
    
} catch (Exception $e) {
    log_error("Failed to load template", [
        'template_file' => $template_file,
        'error' => $e->getMessage(),
        'file_size' => filesize($template_file)
    ]);
    header('Location: suratizin.php?error=template_error');
    exit;
}

// 10. Enhanced Data Merging with Auto-formatting
// Auto-capitalize first letter of each word for perihal
$perihal_formatted = ucwords(strtolower($perihal_form));

// Auto-lowercase alasan
$alasan_formatted = strtolower($alasan_form);

// Format dates to Indonesian format
$tanggal_mulai_formatted = date('d F Y', strtotime($tanggal_mulai_form));
$tanggal_selesai_formatted = date('d F Y', strtotime($tanggal_selesai_form));

// Generate unique document number
$tahun_bulan = date('Ym');
$nomor_urut = str_pad($user_id_session . date('dHis'), 6, '0', STR_PAD_LEFT);
$nomor_surat = "IZIN{$tahun_bulan}{$nomor_urut}";

// Gabungkan Data (MergeField) dengan enhanced formatting
$TBS->MergeField('date', $tanggal_hari_ini);
$TBS->MergeField('perihal', $perihal_formatted);
$TBS->MergeField('nama_panjang', $user_data['nama_lengkap']);
$TBS->MergeField('number', $user_data['id']);
$TBS->MergeField('nomor_surat', $nomor_surat);
$TBS->MergeField('cabang', $user_data['outlet']);
$TBS->MergeField('posisi', $user_data['posisi']);
$TBS->MergeField('subjek', $alasan_formatted);
$TBS->MergeField('hari', $lama_izin_form);
$TBS->MergeField('tanggal_mulai', $tanggal_mulai_formatted);
$TBS->MergeField('tanggal_izin', $tanggal_mulai_formatted);
$TBS->MergeField('tanggal_selesai', $tanggal_selesai_formatted);
$TBS->MergeField('nama_panjang2', $user_data['nama_lengkap']);

// 11. Masukkan Gambar Tanda Tangan
if (file_exists($path_ttd_untuk_word)) {
    $TBS->PlugIn(OPENTBS_CHANGE_PICTURE, 'ttd', $path_ttd_untuk_word);
} else {
    $TBS->MergeField('ttd', '(Tanda Tangan Gagal Dimuat)');
}

// 12. Enhanced File Management with Corruption Prevention & Unique Naming
$folder_surat_izin = "uploads/surat_izin/";

// Enhanced directory creation with proper error handling
if (!is_dir($folder_surat_izin)) {
    if (!mkdir($folder_surat_izin, 0755, true)) {
        log_error("Failed to create directory", ['directory' => $folder_surat_izin]);
        header('Location: suratizin.php?error=gagalbikinfolder');
        exit;
    }
}

// Ensure directory is writable (try multiple permission levels)
if (!is_writable($folder_surat_izin)) {
    $permissions_to_try = [0755, 0775, 0777];
    $permission_fixed = false;
    
    foreach ($permissions_to_try as $perm) {
        if (chmod($folder_surat_izin, $perm)) {
            error_log("Directory permission set to: " . decoct($perm) . " for " . $folder_surat_izin);
            $permission_fixed = true;
            break;
        }
    }
    
    if (!$permission_fixed) {
        log_error("Directory not writable after trying multiple permissions", ['directory' => $folder_surat_izin]);
        header('Location: suratizin.php?error=permission_denied');
        exit;
    }
}

// Generate UNIQUE filename to prevent conflicts
do {
    $unique_id = uniqid('', true); // Microsecond-based unique ID
    $nama_file_surat = "surat_izin_{$nomor_surat}_{$unique_id}.docx";
    $path_simpan_surat = $folder_surat_izin . $nama_file_surat;
} while (file_exists($path_simpan_surat));

error_log("Generated unique filename: " . $nama_file_surat);

// Check disk space (minimum 10MB free)
$free_space = disk_free_space($folder_surat_izin);
if ($free_space < 10 * 1024 * 1024) {
    log_error("Insufficient disk space", ['free_space' => $free_space, 'required' => 10 * 1024 * 1024]);
    header('Location: suratizin.php?error=insufficient_space');
    exit;
}

// CRITICAL: Pre-validate before OpenTBS Show() operation
error_log("Pre-validating before OpenTBS Show() operation");

// Check if target directory is truly writable
if (!is_writable(dirname($path_simpan_surat))) {
    $dir_perms = substr(sprintf('%o', fileperms(dirname($path_simpan_surat))), -4);
    error_log("Target directory not writable: " . dirname($path_simpan_surat) . " (perms: $dir_perms)");
    header('Location: suratizin.php?error=directory_not_writable');
    exit;
}

// Check if file already exists and try to remove it to prevent conflicts
if (file_exists($path_simpan_surat)) {
    error_log("File already exists, attempting to remove: " . $path_simpan_surat);
    if (unlink($path_simpan_surat)) {
        error_log("Existing file removed successfully: " . $path_simpan_surat);
    } else {
        error_log("WARNING: Could not remove existing file: " . $path_simpan_surat);
        // Generate new unique filename as fallback
        do {
            $unique_id = uniqid('', true);
            $nama_file_surat_backup = "surat_izin_{$nomor_surat}_{$unique_id}_backup.docx";
            $path_simpan_surat_backup = $folder_surat_izin . $nama_file_surat_backup;
        } while (file_exists($path_simpan_surat_backup));
        $path_simpan_surat = $path_simpan_surat_backup;
        error_log("Using backup filename: " . $nama_file_surat_backup);
    }
}

// Check disk space
$free_space = disk_free_space($folder_surat_izin);
if ($free_space < 10 * 1024 * 1024) { // 10MB minimum
    log_error("Insufficient disk space", ['free_space' => $free_space, 'required' => 10 * 1024 * 1024]);
    header('Location: suratizin.php?error=insufficient_space');
    exit;
}

// Save Word document with comprehensive error handling
try {
    // Clear any previous output
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set memory limit explicitly for this operation
    $old_memory_limit = ini_get('memory_limit');
    ini_set('memory_limit', '256M'); // Increased for safety
    
    error_log("About to call OpenTBS Show() with path: " . $path_simpan_surat);
    error_log("Template fields merged: " . count($TBS->GetVarList()) . " variables");
    
    // CRITICAL: This is where the actual DOCX generation happens
    $TBS->Show(OPENTBS_FILE, $path_simpan_surat);
    
    // Restore memory limit
    ini_set('memory_limit', $old_memory_limit);
    
    error_log("OpenTBS Show() completed successfully");
    
} catch (Exception $e) {
    // Restore memory limit in case of error
    ini_set('memory_limit', $old_memory_limit);
    
    $error_msg = "Document generation failed: " . $e->getMessage();
    $error_msg .= " | File: " . $path_simpan_surat;
    $error_msg .= " | Memory: " . memory_get_usage(true);
    
    error_log($error_msg);
    log_error("Failed to save Word document", [
        'error' => $e->getMessage(),
        'file_path' => $path_simpan_surat,
        'user_id' => $user_id_session,
        'memory_usage' => memory_get_usage(true)
    ]);
    header('Location: suratizin.php?error=gagalsimpansurat');
    exit;
}

// Verify file was created and is not corrupted
if (!file_exists($path_simpan_surat)) {
    log_error("File was not created", ['file_path' => $path_simpan_surat]);
    header('Location: suratizin.php?error=gagalsimpansurat');
    exit;
}

// Validate file integrity
$file_size = filesize($path_simpan_surat);
if ($file_size < 1024) { // Word document should be at least 1KB
    log_error("Generated file is too small (corrupted)", [
        'file_path' => $path_simpan_surat,
        'file_size' => $file_size
    ]);
    unlink($path_simpan_surat); // Remove corrupted file
    header('Location: suratizin.php?error=document_corrupted');
    exit;
}

// Verify file is a valid Word document by checking ZIP structure
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $path_simpan_surat);
finfo_close($finfo);

if (strpos($mime_type, 'zip') === false && strpos($mime_type, 'vnd.openxmlformats-officedocument') === false) {
    log_error("Generated file is not a valid Word document", [
        'file_path' => $path_simpan_surat,
        'mime_type' => $mime_type,
        'file_size' => $file_size
    ]);
    unlink($path_simpan_surat); // Remove corrupted file
    header('Location: suratizin.php?error=document_invalid');
    exit;
}

// Set proper file permissions
chmod($path_simpan_surat, 0644);

// 13. Enhanced Database Storage with Additional Metadata
$sql_insert = "INSERT INTO pengajuan_izin (
    user_id, perihal, tanggal_mulai, tanggal_selesai, lama_izin, alasan,
    file_surat, tanda_tangan_file, status, tanggal_pengajuan, jenis_izin, outlet, posisi
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?, ?)";

$stmt_insert = $pdo->prepare($sql_insert);
$stmt_insert->execute([
    $user_id_session,
    $perihal_formatted, // Use formatted version
    $tanggal_mulai_form,
    $tanggal_selesai_form,
    $lama_izin_form,
    $alasan_formatted, // Use formatted version
    $nama_file_surat,
    $nama_file_ttd_final,
    determineJenisIzin($perihal_form),
    $user_data['outlet'],
    $user_data['posisi']
]);

if ($stmt_insert) {
    // 14. Enhanced Notification System with Email & WhatsApp
    require_once __DIR__ . '/email_helper.php';

    $pengajuan_id = $pdo->lastInsertId();

    $izin_data = [
        'id' => $pengajuan_id,
        'nomor_surat' => $nomor_surat,
        'tanggal_mulai' => $tanggal_mulai_formatted,
        'tanggal_selesai' => $tanggal_selesai_formatted,
        'durasi_hari' => $lama_izin_form,
        'alasan' => $alasan_formatted,
        'jenis_izin' => determineJenisIzin($perihal_form),
        'perihal' => $perihal_formatted,
        'file_surat' => $nama_file_surat
    ];

    // Send email notification to HR and supervisors
    $email_sent = sendEmailIzinBaru($izin_data, $user_data, $pdo);

    // Send Telegram notification to HR and supervisors
    require_once __DIR__ . '/telegram_helper.php';
    $telegram_sent = sendTelegramIzinBaru($izin_data, $user_data, $pdo);

    // Create notification status message untuk email dan telegram
    $notification_status = '';
    if ($email_sent && $telegram_sent) {
        $notification_status = 'Email dan Telegram berhasil dikirim';
    } elseif ($email_sent && !$telegram_sent) {
        $notification_status = 'Email berhasil, Telegram gagal dikirim';
    } elseif (!$email_sent && $telegram_sent) {
        $notification_status = 'Email gagal, Telegram berhasil dikirim';
    } else {
        $notification_status = 'Email dan Telegram gagal dikirim';
    }

    // Log notification results dengan detail
    error_log("Notifikasi izin #{$pengajuan_id} ({$nomor_surat}): {$notification_status}");
    error_log("Detail: Email=" . ($email_sent ? 'BERHASIL' : 'GAGAL') . ", Telegram=" . ($telegram_sent ? 'BERHASIL' : 'GAGAL'));

    // Log activity
    logUserActivity($pdo, $user_id_session, 'leave_request_submitted', "Leave request submitted: $nomor_surat");

    // Success redirect with notification status
    $redirect_status = 'sukses';
    if ($email_sent && $telegram_sent) {
        $redirect_status = 'sukses_email_telegram';
    } elseif ($email_sent) {
        $redirect_status = 'sukses_email';
    } elseif ($telegram_sent) {
        $redirect_status = 'sukses_telegram';
    }

    header('Location: suratizin.php?status=' . $redirect_status);
    exit;
} else {
    if (file_exists($path_simpan_surat)) unlink($path_simpan_surat);
    header('Location: suratizin.php?error=gagalinsertdb');
    exit;
}

/**
 * Helper function to save signature from form data
 */
function saveSignatureFromForm($signature_data_base64, $user_id, $pdo) {
    // Validate base64 format
    if (!preg_match('/^data:image\/(\w+);base64,/', $signature_data_base64, $type)) {
        error_log("Invalid base64 format for signature");
        return false;
    }

    $image_type = strtolower($type[1]);

    // Validate image type (only PNG for signatures)
    if (!in_array($image_type, ['png'])) {
        error_log("Invalid image type: $image_type - only PNG allowed for signatures");
        return false;
    }

    // Extract base64 data
    $signature_data_base64 = substr($signature_data_base64, strpos($signature_data_base64, ',') + 1);
    $signature_data_binary = base64_decode($signature_data_base64);

    if ($signature_data_binary === false) {
        error_log("Failed to decode base64 data");
        return false;
    }

    // Validate file size (max 2MB)
    if (strlen($signature_data_binary) > 2 * 1024 * 1024) {
        error_log("File size too large: " . strlen($signature_data_binary));
        return false;
    }

    // Generate unique filename
    $filename = 'ttd_user_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $image_type;
    $filepath = 'uploads/tanda_tangan/' . $filename;

    // Ensure upload directory exists
    $upload_dir = dirname($filepath);
    error_log("Checking directory: $upload_dir");
    if (!is_dir($upload_dir)) {
        error_log("Directory does not exist, creating: $upload_dir");
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create directory: $upload_dir");
            return false;
        }
        error_log("Directory created successfully: $upload_dir");
    } else {
        error_log("Directory already exists: $upload_dir");
    }

    // Ensure directory is writable
    if (!is_writable($upload_dir)) {
        error_log("Directory not writable, attempting chmod: $upload_dir");
        if (!chmod($upload_dir, 0777)) {
            error_log("Failed to make directory writable: $upload_dir");
            return false;
        }
        error_log("Directory chmod successful: $upload_dir");
    } else {
        error_log("Directory is writable: $upload_dir");
    }

    // Additional check: verify parent directory permissions
    $parent_dir = dirname($upload_dir);
    if (!is_writable($parent_dir)) {
        error_log("Parent directory not writable: $parent_dir");
        if (!chmod($parent_dir, 0777)) {
            error_log("Failed to make parent directory writable: $parent_dir");
            return false;
        }
        error_log("Parent directory chmod successful: $parent_dir");
    }

    // Save file
    error_log("Attempting to save file: $filepath");
    if (!file_put_contents($filepath, $signature_data_binary)) {
        $error = error_get_last();
        $error_message = isset($error['message']) ? $error['message'] : 'Unknown error';
        error_log("Failed to save file: $filepath - " . $error_message);
        error_log("File size: " . strlen($signature_data_binary) . " bytes");
        error_log("Directory permissions: " . substr(sprintf('%o', fileperms($upload_dir)), -4));
        return false;
    }
    error_log("File saved successfully: $filepath");

    // Set proper file permissions
    chmod($filepath, 0644);

    // Validate image dimensions and type
    $image_info = getimagesize($filepath);
    if (!$image_info) {
        if (file_exists($filepath)) {
            unlink($filepath); // Delete invalid file
        }
        error_log("Invalid image file: $filepath");
        return false;
    }

    // Additional validation: ensure it's actually a PNG image
    $allowed_mime_types = ['image/png'];
    if (!in_array($image_info['mime'], $allowed_mime_types)) {
        if (file_exists($filepath)) {
            unlink($filepath); // Delete invalid file
        }
        error_log("Invalid MIME type: {$image_info['mime']} for file: $filepath");
        return false;
    }

    // Save to database
    try {
        $stmt = $pdo->prepare('UPDATE register SET tanda_tangan_file = ?, signature_updated_at = NOW() WHERE id = ?');
        $result = $stmt->execute([$filename, $user_id]);
        if ($result) {
            error_log("Signature saved successfully: $filename for user $user_id");
            // Log activity
            logUserActivity($pdo, $user_id, 'signature_save', 'Digital signature saved for leave request');
            return $filename;
        } else {
            error_log("Database update failed for user $user_id");
            if (file_exists($filepath)) {
                unlink($filepath); // Delete file if database update fails
            }
            return false;
        }
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        if (file_exists($filepath)) {
            unlink($filepath); // Delete file if database update fails
        }
        return false;
    }
}

/**
 * Helper function to determine jenis izin from perihal
 */
function determineJenisIzin($perihal) {
    $perihal_lower = strtolower($perihal);

    if (strpos($perihal_lower, 'sakit') !== false) {
        return 'Sakit';
    } elseif (strpos($perihal_lower, 'izin') !== false) {
        return 'Izin';
    } elseif (strpos($perihal_lower, 'cuti') !== false) {
        return 'Cuti';
    } else {
        return 'Izin'; // Default
    }
}

?>