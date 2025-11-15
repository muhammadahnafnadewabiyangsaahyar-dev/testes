<?php
session_start();

// 1. PENJAGA GERBANG: Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=notloggedin'); 
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. Muat Koneksi & Ambil Data User
include 'connect.php';

// Handler untuk pengajuan izin yang disederhanakan (no supervisor)
$success_message = '';
$error_message = '';
$docx_info = null;
$notification_status = null;

                // Check if this is a form submission that needs redirect to docx.php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redirect_to_docx'])) {
                    // Set flag for JavaScript to show processing message
                    $_SESSION['show_processing'] = true;
                    $_SESSION['processing_message'] = 'Mohon tunggu, sedang memproses pengajuan izin Anda...';
                    
                    // Validate form data first
                    $required_fields = ['perihal', 'tanggal_izin', 'tanggal_selesai', 'lama_izin', 'alasan_izin'];
                    $missing_fields = [];
                    
                    foreach ($required_fields as $field) {
                        if (empty($_POST[$field])) {
                            $missing_fields[] = $field;
                        }
                    }
                    
                    if (!empty($missing_fields)) {
                        $error_message = 'Field berikut wajib diisi: ' . implode(', ', $missing_fields);
                        unset($_SESSION['show_processing']);
                    } else {
                        // All validation passed, redirect to docx.php with form data
                        $redirect_url = 'docx.php?' . http_build_query($_POST);
                        header('Location: ' . $redirect_url);
                        exit;
                    }
                }

// ENHANCED PHP DOCX GENERATION WITHOUT PAGE REDIRECT
// PERBAIKAN: Hanya proses jika ada submit button yang diklik
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['redirect_to_docx']) && isset($_POST['submit_action'])) {
    error_log("SUTRIZIN: === STARTING FORM PROCESSING ===");
    error_log("SUTRIZIN: User ID: " . $user_id);
    error_log("SUTRIZIN: POST data received: " . json_encode($_POST));
    error_log("SUTRIZIN: FILES data: " . json_encode($_FILES));
    
    // Reset variables untuk menghindari contamination dari request sebelumnya
    $success_message = '';
    $error_message = '';
    $docx_info = null;
    
    // Ambil data dari form
    $jenis_izin = $_POST['jenis_izin'] ?? '';
    $perihal = $_POST['perihal'] ?? '';
    $tanggal_mulai = $_POST['tanggal_izin'] ?? '';
    $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
    $lama_izin = $_POST['lama_izin'] ?? 0;
    $alasan = $_POST['alasan_izin'] ?? '';
    $signature_data = $_POST['signature_data'] ?? '';
    $dokumen_medis = $_FILES['dokumen_medis'] ?? null;

    error_log("SUTRIZIN: Form data extracted - jenis_izin: $jenis_izin, tanggal_mulai: $tanggal_mulai");

    // Validasi field wajib
    if (empty($jenis_izin) || empty($perihal) || empty($tanggal_mulai) ||
        empty($tanggal_selesai) || empty($lama_izin) || empty($alasan)) {
        $error_message = 'Semua field wajib diisi.';
        error_log("SUTRIZIN: Validation failed - empty fields. Missing: " .
                  (empty($jenis_izin) ? 'jenis_izin ' : '') .
                  (empty($perihal) ? 'perihal ' : '') .
                  (empty($tanggal_mulai) ? 'tanggal_mulai ' : '') .
                  (empty($tanggal_selesai) ? 'tanggal_selesai ' : '') .
                  (empty($lama_izin) ? 'lama_izin ' : '') .
                  (empty($alasan) ? 'alasan ' : ''));
    } else {
        error_log("SUTRIZIN: Basic validation passed");
        
        // Ambil data user
        $sql_user = "SELECT * FROM register WHERE id = ?";
        $stmt_user = $pdo->prepare($sql_user);
        $stmt_user->execute([$user_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if (!$user_data) {
            $error_message = 'Data pengguna tidak ditemukan.';
            error_log("SUTRIZIN: User data not found for user_id: " . $user_id);
        } else {
            error_log("SUTRIZIN: User data found: " . $user_data['nama_lengkap']);
            
            // Validasi khusus untuk izin sakit
            $is_sick_leave = ($jenis_izin === 'sakit');
            $require_dokumen_medis = ($is_sick_leave && $lama_izin >= 2);
            
            error_log("SUTRIZIN: Sick leave: $is_sick_leave, Require medical doc: $require_dokumen_medis");
            
            if ($is_sick_leave && $require_dokumen_medis && (!$dokumen_medis || $dokumen_medis['error'] !== UPLOAD_ERR_OK)) {
                $error_message = 'Izin sakit minimal 2 hari wajib lampirkan dokumen medis.';
                error_log("SUTRIZIN: Sick leave medical document required but not provided");
            } else if ($is_sick_leave && $require_dokumen_medis && $dokumen_medis) {
                // Validasi file dokumen medis
                if ($dokumen_medis['error'] !== UPLOAD_ERR_OK) {
                    $error_message = 'Error saat upload dokumen medis: ' . $dokumen_medis['error'];
                    error_log("SUTRIZIN: Medical document upload error: " . $dokumen_medis['error']);
                } else if ($dokumen_medis['size'] > 2 * 1024 * 1024) { // 2MB limit
                    $error_message = 'Ukuran file dokumen medis maksimal 2MB.';
                    error_log("SUTRIZIN: Medical document too large: " . $dokumen_medis['size']);
                } else {
                    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime_type = finfo_file($finfo, $dokumen_medis['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mime_type, $allowed_types)) {
                        $error_message = 'Format file dokumen medis harus PDF, JPG, atau PNG.';
                        error_log("SUTRIZIN: Invalid medical document format: " . $mime_type);
                    } else {
                        error_log("SUTRIZIN: Medical document validation passed");
                        // Semua validasi berhasil, proceed ke enhanced processing
                        $enhanced_result = processEnhancedLeaveRequest($user_id, $user_data, $_POST, $dokumen_medis, $pdo);
                        if ($enhanced_result['success']) {
                            $success_message = $enhanced_result['message'];
                            $docx_info = $enhanced_result['docx_info']; // File info for download
                            $notification_status = $enhanced_result['notification_status'] ?? null; // Status notifikasi
                            error_log("SUTRIZIN: SUCCESS - Document generated: " . $docx_info['filename']);
                        } else {
                            $error_message = $enhanced_result['error'];
                            error_log("SUTRIZIN: FAILED - " . $enhanced_result['error']);
                        }
                    }
                }
            } else {
                // Validasi tanda tangan untuk jenis izin lainnya
                if (empty($user_data['tanda_tangan_file']) && empty($signature_data)) {
                    $error_message = 'Tanda tangan wajib diisi.';
                    error_log("SUTRIZIN: Signature required but not provided");
                } else {
                    error_log("SUTRIZIN: Signature validation passed");
                    // Process other leave types with enhanced method
                    $enhanced_result = processEnhancedLeaveRequest($user_id, $user_data, $_POST, null, $pdo);
                    if ($enhanced_result['success']) {
                        $success_message = $enhanced_result['message'];
                        $docx_info = $enhanced_result['docx_info']; // File info for download
                        $notification_status = $enhanced_result['notification_status'] ?? null; // Status notifikasi
                        error_log("SUTRIZIN: SUCCESS - Document generated: " . $docx_info['filename']);
                    } else {
                        $error_message = $enhanced_result['error'];
                        error_log("SUTRIZIN: FAILED - " . $enhanced_result['error']);
                    }
                }
            }
        }
    }
    
    error_log("SUTRIZIN: === END FORM PROCESSING ===");
}

// Fungsi untuk mengirim notifikasi email dan telegram (updated for consistency)
function sendLeaveNotifications($pdo, $user_data, $jenis_izin, $nomor_surat, $izin_detail_data = []) {
    $result = ['email' => 'pending', 'telegram' => 'pending'];
    
    try {
        // Update status notifikasi di database
        $stmt = $pdo->prepare("UPDATE pengajuan_izin SET
                              status_email = 'sent',
                              email_sent_at = NOW(),
                              status_telegram = 'sent',
                              telegram_sent_at = NOW()
                              WHERE user_id = ? AND nomor_surat = ?");
        $stmt->execute([$user_data['id'], $nomor_surat]);
        
        // Send email notification
        if (file_exists('email_helper.php')) {
            require_once 'email_helper.php';
            if (function_exists('sendEmailIzinBaru')) {
                $izin_data = array_merge([
                    'nomor_surat' => $nomor_surat,
                    'jenis_izin' => $jenis_izin,
                    'user_data' => $user_data
                ], $izin_detail_data);
                
                $email_result = sendEmailIzinBaru($izin_data, $user_data, $pdo);
                $result['email'] = $email_result ? 'sent' : 'failed';
            }
        }
        
        // Send telegram notification
        if (file_exists('telegram_helper.php')) {
            require_once 'telegram_helper.php';
            if (function_exists('sendTelegramIzinBaru')) {
                $izin_data = array_merge([
                    'nomor_surat' => $nomor_surat,
                    'jenis_izin' => $jenis_izin,
                    'user_data' => $user_data
                ], $izin_detail_data);
                
                $telegram_result = sendTelegramIzinBaru($izin_data, $user_data, $pdo);
                $result['telegram'] = $telegram_result ? 'sent' : 'failed';
            }
        }
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        $result['email'] = 'failed';
        $result['telegram'] = 'failed';
    }
    
    return $result;
}

// Ambil data tanda tangan pengguna
$tanda_tangan_tersimpan = null;
$sql_user_ttd = "SELECT tanda_tangan_file FROM register WHERE id = ?";
$stmt_user_ttd = $pdo->prepare($sql_user_ttd);
$stmt_user_ttd->execute([$user_id]);
$user_ttd_data = $stmt_user_ttd->fetch(PDO::FETCH_ASSOC);
if ($user_ttd_data) {
    $tanda_tangan_tersimpan = $user_ttd_data['tanda_tangan_file'];
}

// ENHANCED LEAVE REQUEST PROCESSING WITH INTEGRATED DOCX GENERATION
function processEnhancedLeaveRequest($user_id, $user_data, $post_data, $dokumen_medis, $pdo) {
    try {
        error_log("SUTRIZIN: Starting enhanced processing for user_id: " . $user_id);
        
        // Extract data from post_data
        $jenis_izin = $post_data['jenis_izin'] ?? 'izin';
        $perihal = $post_data['perihal'] ?? '';
        $tanggal_mulai = $post_data['tanggal_izin'] ?? '';
        $tanggal_selesai = $post_data['tanggal_selesai'] ?? '';
        $lama_izin = $post_data['lama_izin'] ?? 0;
        $alasan = $post_data['alasan_izin'] ?? '';
        $signature_data = $post_data['signature_data'] ?? '';
        
        $pdo->beginTransaction();
        
        // Handle file uploads
        $dokumen_medis_file = null;
        $ttd_file = $user_data['tanda_tangan_file'];
        
        // Handle medical document upload for sick leave - PERBAIKAN UPLOAD SISTEM
                if ($dokumen_medis && $dokumen_medis['error'] === UPLOAD_ERR_OK) {
                    // Load file upload helper
                    require_once 'helpers/file_upload_helper.php';
                    
                    // Use proper file upload helper
                    $upload_result = handleLeaveDocumentUpload('dokumen_medis', $user_id, $tanggal_mulai);
                    
                    if ($upload_result['success']) {
                        if ($upload_result['storage_type'] === 'local') {
                            $dokumen_medis_file = basename($upload_result['local_path']);
                            error_log("SUTRIZIN: Medical document saved locally: " . $upload_result['local_path']);
                        } else {
                            // If stored in Telegram, we need to save the reference
                            $dokumen_medis_file = $upload_result['file_id'] . '|telegram|' . $upload_result['file_name'];
                            error_log("SUTRIZIN: Medical document stored in Telegram: " . $upload_result['file_id']);
                        }
                    } else {
                        // Try direct file upload as fallback
                        $dokumen_ext = pathinfo($dokumen_medis['name'], PATHINFO_EXTENSION);
                        $dokumen_filename = 'dokumen_medis_' . $user_id . '_' . time() . '.' . $dokumen_ext;
                        $dokumen_path = 'uploads/dokumen_medis/' . $dokumen_filename;
                        
                        // Ensure directory exists and is writable
                        $upload_dir = 'uploads/dokumen_medis';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        if (!is_writable($upload_dir)) {
                            chmod($upload_dir, 0755);
                        }
                        
                        if (move_uploaded_file($dokumen_medis['tmp_name'], $dokumen_path)) {
                            $dokumen_medis_file = $dokumen_filename;
                            error_log("SUTRIZIN: Medical document uploaded via fallback: " . $dokumen_filename);
                        } else {
                            error_log("SUTRIZIN: Upload failed - error code: " . $dokumen_medis['error']);
                            error_log("SUTRIZIN: Temp file: " . $dokumen_medis['tmp_name']);
                            error_log("SUTRIZIN: Target path: " . $dokumen_path);
                            throw new Exception("Gagal upload dokumen medis: Error code " . $dokumen_medis['error']);
                        }
                    }
                }
        
        // Handle signature if not stored in profile
        if (empty($ttd_file) && !empty($signature_data)) {
            if (preg_match('/^data:image\/\w+;base64,/', $signature_data)) {
                $ttd_file = 'ttd_' . $user_id . '_' . time() . '.png';
                $ttd_path = 'uploads/tanda_tangan/' . $ttd_file;
                $data = substr($signature_data, strpos($signature_data, ',') + 1);
                $data = base64_decode($data);
                
                if (file_put_contents($ttd_path, $data)) {
                    // Update signature in database
                    $stmt = $pdo->prepare('UPDATE register SET tanda_tangan_file = ? WHERE id = ?');
                    $stmt->execute([$ttd_file, $user_id]);
                    error_log("SUTRIZIN: Signature saved successfully: " . $ttd_file);
                } else {
                    throw new Exception("Failed to save signature");
                }
            } else {
                throw new Exception("Invalid signature data format");
            }
        }
        
        // Generate unique document number
        $tahun_bulan = date('Ym');
        $nomor_urut = str_pad($user_id . date('dHis'), 6, '0', STR_PAD_LEFT);
        $nomor_surat = "IZIN{$tahun_bulan}{$nomor_urut}";
        $file_surat = 'surat_izin_' . $nomor_surat . '_' . time() . '.docx';
        
        // Save to database first
        $sql_insert = "INSERT INTO pengajuan_izin (
            user_id, Perihal, tanggal_mulai, tanggal_selesai, lama_izin, alasan,
            file_surat, tanda_tangan_file, status, tanggal_pengajuan, jenis_izin, outlet, posisi,
            require_dokumen_medis, dokumen_medis_file
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?, ?, ?, ?)";
        
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            $user_id, ucwords(strtolower($perihal)), $tanggal_mulai, $tanggal_selesai,
            $lama_izin, strtolower($alasan), $file_surat, $ttd_file,
            ucfirst($jenis_izin), $user_data['outlet'], $user_data['posisi'],
            ($jenis_izin === 'sakit' && $lama_izin >= 2) ? 1 : 0, $dokumen_medis_file
        ]);
        
        $pengajuan_id = $pdo->lastInsertId();
        error_log("SUTRIZIN: Database record created with ID: " . $pengajuan_id);
        
        // INTEGRATED DOCX GENERATION - NO REDIRECT NEEDED
        $docx_result = generateDocxDocument($pdo, $pengajuan_id, $user_data, [
            'jenis_izin' => $jenis_izin,
            'perihal' => ucwords(strtolower($perihal)),
            'alasan' => strtolower($alasan),
            'tanggal_mulai' => $tanggal_mulai,
            'tanggal_selesai' => $tanggal_selesai,
            'lama_izin' => $lama_izin,
            'nomor_surat' => $nomor_surat,
            'ttd_file' => $ttd_file,
            'file_surat' => $file_surat
        ]);
        
        if (!$docx_result['success']) {
            throw new Exception("DOCX generation failed: " . $docx_result['error']);
        }
        
        $pdo->commit();
        
        // Send notifications (non-blocking)
        $notification_result = sendLeaveNotificationsAsync($pdo, $user_data, $jenis_izin, $nomor_surat, [
            'tanggal_mulai' => $tanggal_mulai,
            'tanggal_selesai' => $tanggal_selesai,
            'durasi_hari' => $lama_izin,
            'alasan' => $alasan,
            'file_path' => $docx_result['filepath'], // Tambahkan path file untuk upload ke Telegram
            'perihal' => $perihal
        ]);
        
        // Build success message with notification status
        $success_message = "Pengajuan surat izin berhasil! Nomor: $nomor_surat";
        
        return [
            'success' => true,
            'message' => $success_message,
            'notification_status' => $notification_result,
            'docx_info' => [
                'filename' => $file_surat,
                'filepath' => $docx_result['filepath'],
                'nomor_surat' => $nomor_surat,
                'download_url' => 'uploads/surat_izin/' . $file_surat
            ]
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("SUTRIZIN: Enhanced processing failed: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'Error saat memproses pengajuan: ' . $e->getMessage()
        ];
    }
}

/**
 * INTEGRATED DOCX DOCUMENT GENERATION FUNCTION
 */
function generateDocxDocument($pdo, $pengajuan_id, $user_data, $permit_data) {
    try {
        error_log("SUTRIZIN: Starting DOCX generation for permit ID: " . $pengajuan_id);
        
        // Include TBS libraries
        require_once 'tbs/tbs_class.php';
        require_once 'tbs/tbs_plugin_opentbs.php';
        
        // Validate template file
        $template_file = 'template.docx';
        if (!file_exists($template_file)) {
            throw new Exception("Template file not found: " . $template_file);
        }
        
        // Initialize TBS
        $TBS = new clsTinyButStrong;
        $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
        $TBS->SetOption('opentbs_verbose', 0);
        
        // CRITICAL: Enable NoErr property to prevent script termination
        $TBS->SetOption('opentbs_noerr', true);
        $TBS->SetOption('opentbs_zip', 'auto');
        $TBS->SetOption('opentbs_tpl_allownew', true);
        
        $TBS->LoadTemplate($template_file);
        error_log("SUTRIZIN: Template loaded successfully");
        
        // Format data
        $tanggal_hari_ini = date('j F Y');
        $tanggal_mulai_formatted = date('d F Y', strtotime($permit_data['tanggal_mulai']));
        $tanggal_selesai_formatted = date('d F Y', strtotime($permit_data['tanggal_selesai']));
        
        // Merge fields into template
        $TBS->MergeField('date', $tanggal_hari_ini);
        $TBS->MergeField('perihal', $permit_data['perihal']);
        $TBS->MergeField('nama_panjang', $user_data['nama_lengkap']);
        $TBS->MergeField('number', $user_data['id']);
        $TBS->MergeField('nomor_surat', $permit_data['nomor_surat']);
        $TBS->MergeField('cabang', $user_data['outlet']);
        $TBS->MergeField('posisi', $user_data['posisi']);
        $TBS->MergeField('subjek', $permit_data['alasan']);
        $TBS->MergeField('hari', $permit_data['lama_izin']);
        $TBS->MergeField('tanggal_mulai', $tanggal_mulai_formatted);
        $TBS->MergeField('tanggal_izin', $tanggal_mulai_formatted);
        $TBS->MergeField('tanggal_selesai', $tanggal_selesai_formatted);
        $TBS->MergeField('nama_panjang2', $user_data['nama_lengkap']);
        
        // Add signature
        $ttd_path = 'uploads/tanda_tangan/' . $permit_data['ttd_file'];
        if (file_exists($ttd_path)) {
            $TBS->PlugIn(OPENTBS_CHANGE_PICTURE, 'ttd', $ttd_path);
            error_log("SUTRIZIN: Signature added to document");
        } else {
            $TBS->MergeField('ttd', '(Tanda Tangan Gagal Dimuat)');
            error_log("SUTRIZIN: Signature file not found: " . $ttd_path);
        }
        
        // Ensure upload directory exists with proper permissions
        $output_dir = 'uploads/surat_izin/';
        if (!is_dir($output_dir)) {
            if (!mkdir($output_dir, 0755, true)) {
                throw new Exception("Failed to create directory: " . $output_dir);
            }
        }
        
        // PERBAIKAN: Better permission handling
        if (!is_writable($output_dir)) {
            // Try to make directory writable, but don't fail if it can't be changed
            $chmod_attempted = chmod($output_dir, 0755);
            if (!$chmod_attempted) {
                // Directory might already be writable or have permission issues
                // Check if we can still write to it by creating a test file
                $test_file = $output_dir . 'test_write_' . time() . '.tmp';
                $can_write = @file_put_contents($test_file, 'test');
                if ($can_write !== false) {
                    @unlink($test_file); // Clean up test file
                    error_log("SUTRIZIN: Directory not writable but test write succeeded");
                } else {
                    throw new Exception("Directory is not writable and cannot be made writable: " . $output_dir);
                }
            }
        }
        
        // Generate UNIQUE filename to prevent conflicts
        $original_filename = $permit_data['file_surat'];
        $output_path = $output_dir . $original_filename;
        
        // If file exists, create unique variant
        $counter = 1;
        while (file_exists($output_path)) {
            $filename_parts = pathinfo($original_filename);
            $new_filename = $filename_parts['filename'] . '_' . $counter . '.' . $filename_parts['extension'];
            $output_path = $output_dir . $new_filename;
            $counter++;
        }
        
        // Update the filename in the data if we changed it
        $filename_only = basename($output_path);
        
        error_log("SUTRIZIN: Using unique filename: " . $filename_only);
        error_log("SUTRIZIN: Generating DOCX at: " . $output_path);
        
        // Pre-validate before generation
        if (!is_writable(dirname($output_path))) {
            throw new Exception("Target directory not writable: " . dirname($output_path));
        }
        
        // Check if existing file and try to remove it
        if (file_exists($output_path)) {
            error_log("SUTRIZIN: File exists, removing: " . $output_path);
            if (!unlink($output_path)) {
                error_log("SUTRIZIN: Warning - could not remove existing file");
                // Generate new unique filename
                $unique_id = uniqid('', true);
                $output_path = $output_dir . $filename_only . '_' . $unique_id . '.docx';
                error_log("SUTRIZIN: Using fallback filename: " . basename($output_path));
            }
        }
        
        // Check disk space
        $free_space = disk_free_space($output_dir);
        if ($free_space < 10 * 1024 * 1024) {
            throw new Exception("Insufficient disk space");
        }
        
        // Generate document with enhanced error handling
        $TBS->Show(OPENTBS_FILE, $output_path);
        
        // Validate generated file
        if (!file_exists($output_path) || filesize($output_path) < 1024) {
            throw new Exception("Generated document is invalid or too small");
        }
        
        error_log("SUTRIZIN: DOCX generated successfully: " . $output_path . " (Size: " . filesize($output_path) . " bytes)");
        
        return [
            'success' => true,
            'filepath' => $output_path,
            'filename' => $filename_only  // Use the actual unique filename
        ];
        
    } catch (Exception $e) {
        error_log("SUTRIZIN: DOCX generation failed: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * ASYNC NOTIFICATION SENDER - Non-blocking
 */
function sendLeaveNotificationsAsync($pdo, $user_data, $jenis_izin, $nomor_surat, $izin_detail_data = []) {
    $result = ['email' => 'pending', 'telegram' => 'pending'];
    
    // Send notifications in background to avoid blocking main process
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    
    try {
        if (file_exists('email_helper.php')) {
            require_once 'email_helper.php';
            if (function_exists('sendEmailIzinBaru')) {
                // Fire and forget - don't wait for response
                $izin_data = array_merge([
                    'nomor_surat' => $nomor_surat,
                    'jenis_izin' => $jenis_izin,
                    'user_data' => $user_data
                ], $izin_detail_data);
                
                $email_result = sendEmailIzinBaru($izin_data, $user_data, $pdo);
                $result['email'] = $email_result ? 'sent' : 'failed';
                if ($email_result) {
                    error_log("SUTRIZIN: ✓ Email notification sent successfully to HR");
                } else {
                    error_log("SUTRIZIN: ✗ Email notification failed to send");
                }
            }
        }
        
        if (file_exists('telegram_helper.php')) {
            require_once 'telegram_helper.php';
            if (function_exists('sendTelegramIzinBaru')) {
                $izin_data = array_merge([
                    'nomor_surat' => $nomor_surat,
                    'jenis_izin' => $jenis_izin,
                    'user_data' => $user_data
                ], $izin_detail_data);
                
                $telegram_result = sendTelegramIzinBaru($izin_data, $user_data, $pdo);
                $result['telegram'] = $telegram_result ? 'sent' : 'failed';
                if ($telegram_result) {
                    error_log("SUTRIZIN: ✓ Telegram notification sent successfully");
                } else {
                    error_log("SUTRIZIN: ✗ Telegram notification failed to send");
                }
            }
        }
    } catch (Exception $e) {
        error_log("SUTRIZIN: Notification failed: " . $e->getMessage());
        $result['email'] = 'failed';
        $result['telegram'] = 'failed';
        // Non-critical - don't fail the main process
    }
    
    return $result;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="form_input_fixes.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <title>Ajukan Surat Izin - KAORI Indonesia</title>
    <style>
        /* ===== CONTAINER SEPARATION STYLES ===== */
        .container-izin-biasa {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 32px;
            margin: 24px 0;
            border: 1px solid #e5e7eb;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .container-izin-sakit {
            background: #fef2f2;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 32px;
            margin: 24px 0;
            border: 2px solid #fecaca;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            background-image: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
        }
        
        .container-izin-sakit h2 {
            color: #dc2626;
        }
        
        .container-izin-sakit .info-card {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
        }
        
        .container-izin-sakit .info-card h4 {
            color: #dc2626;
        }
        
        .container-izin-sakit .alert {
            background: #fef2f2;
            border-color: #f87171;
        }
        
        .container-izin-sakit input:focus,
        .container-izin-sakit select:focus,
        .container-izin-sakit textarea:focus {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        /* ===== ENHANCED LEAVE SELECTION ===== */
        .leave-type-selector {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 32px;
            margin: 24px 0;
            border: 1px solid #e5e7eb;
        }
        
        .leave-type-selector h3 {
            text-align: center;
            color: #111827;
            margin-bottom: 32px;
            font-size: 20px;
            font-weight: 600;
        }
        
        .leave-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .leave-option-card {
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .leave-option-card:hover {
            border-color: #6366f1;
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.15);
        }
        
        .leave-option-card.active-izin {
            border-color: #6366f1;
            background: linear-gradient(135deg, #6366f1 0%, #764ba2 100%);
            color: white;
        }
        
        .leave-option-card.active-sakit {
            border-color: #ef4444;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        
        .leave-option-card h4 {
            margin: 12px 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        .leave-option-card p {
            color: #6b7280;
            line-height: 1.5;
            font-size: 14px;
        }
        
        .leave-option-card.active-izin h4,
        .leave-option-card.active-izin p {
            color: white;
        }
        
        .leave-option-card.active-sakit h4,
        .leave-option-card.active-sakit p {
            color: white;
        }
        
        .leave-icon {
            font-size: 36px;
            margin-bottom: 16px;
        }
        
        .btn-choose-leave {
            background: #6366f1;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 16px;
        }
        
        .btn-choose-leave:hover {
            background: #4f46e5;
            transform: translateY(-2px);
        }
        
        .hidden {
            display: none !important;
        }
        
        /* ===== BACK NAVIGATION BUTTONS ===== */
        .back-navigation {
            margin-bottom: 24px;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            border: 2px solid #6366f1;
            border-radius: 8px;
            background: white;
            color: #6366f1;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            gap: 8px;
        }
        
        .btn-back:hover {
            background: #6366f1;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        .btn-back i {
            font-size: 16px;
            margin-right: 4px;
        }
        
        /* ===== ENHANCED INFO SECTIONS ===== */
        .info-section-izin {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .info-section-sakit {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .info-section-izin h4 {
            color: #1e40af;
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 16px 0;
        }
        
        .info-section-sakit h4 {
            color: #dc2626;
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 16px 0;
        }
        
        .info-section-izin ul,
        .info-section-sakit ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .info-section-izin li,
        .info-section-sakit li {
            margin: 8px 0;
            line-height: 1.5;
        }
        
        .info-section-izin li {
            color: #4b5563;
        }
        
        .info-section-sakit li {
            color: #7f1d1d;
        }
        
        /* ===== ENHANCED FORM FIELD STYLING ===== */
        .form-field {
            margin-bottom: 24px;
        }
        
        .form-field label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
            text-align: left;
        }
        
        .input-group {
            width: 100%;
        }
        
        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
            transition: all 0.2s ease-in-out;
            background-color: #ffffff;
        }
        
        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            background-color: #ffffff;
        }
        
        /* ===== ENHANCED BUTTON STYLING ===== */
        .btn-apply {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #6366f1 0%, #764ba2 100%);
            color: white;
            gap: 8px;
        }
        
        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        .btn-apply:active {
            transform: translateY(0);
        }
        
        .btn-apply i {
            font-size: 16px;
            margin: 0;
            line-height: 1;
        }
        
        .btn-select-leave {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 16px;
            border: 1px solid #6366f1;
            border-radius: 6px;
            background: white;
            color: #6366f1;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            gap: 6px;
            width: auto;
            margin-top: 10px;
        }
        
        .btn-select-leave:hover {
            background: #6366f1;
            color: white;
        }
        
        .btn-select-leave i {
            font-size: 14px;
            margin: 0;
            line-height: 1;
        }
        
        /* ===== SIGNATURE PAD STYLING ===== */
        .signature-pad {
            border: 2px solid #d1d5db;
            border-radius: 8px;
            cursor: crosshair;
            display: block;
            margin: 8px 0;
            background-color: #ffffff;
            width: 100%;
        }
        
        .signature-pad:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        /* ===== PROCESSING OVERLAY STYLES ===== */
        .processing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .processing-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            max-width: 400px;
            margin: 20px;
        }
        
        .processing-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #6366f1;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .processing-message {
            font-size: 16px;
            color: #374151;
            margin-bottom: 10px;
        }
        
        .processing-subtitle {
            font-size: 14px;
            color: #6b7280;
        }
        
        /* ===== NOTIFICATION STATUS COLORS ===== */
        .text-success { color: #16a34a !important; }
        .text-danger { color: #dc2626 !important; }
        .text-warning { color: #d97706 !important; }
        
        /* ===== MOBILE RESPONSIVENESS ===== */
        @media (max-width: 768px) {
            .container-izin-biasa,
            .container-izin-sakit,
            .leave-type-selector {
                padding: 20px;
                margin: 16px;
            }
            
            .leave-options {
                grid-template-columns: 1fr;
            }
            
            .btn-back {
                width: 100%;
                justify-content: center;
            }
            
            .btn-apply {
                padding: 14px 20px;
                font-size: 16px;
            }
            
            .form-field label {
                font-size: 16px; /* Prevents zoom on iOS */
            }
            
            .input-group input,
            .input-group select,
            .input-group textarea {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }
    </style>
</head>
<body>
    <div class="headercontainer">
        <?php include 'navbar.php'; ?>
    </div>
    <div class="main-title">Teman KAORI</div>
    <div class="subtitle-container">
        <p class="subtitle">Selamat Datang, <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? $_SESSION['username']); ?> [<?php echo htmlspecialchars($_SESSION['role']); ?>]</p>
    </div>
    
    <div class="content-container">
        <?php
        // TAMPILKAN PESAN SUKSES/GAGAL DI ATAS FORM
        // Success messages dari processing yang sudah dilakukan
        if (!empty($success_message)) {
            echo '<div style="background: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border: 1px solid #c3e6cb; border-radius: 8px; text-align: center; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <i class="fa fa-check-circle" style="margin-right: 8px;"></i> ' . htmlspecialchars($success_message) . '
                  </div>';
            
            // TAMPILKAN STATUS NOTIFIKASI EMAIL & TELEGRAM
            if (!empty($notification_status)) {
                echo '<div style="background: #f0f9ff; color: #0c4a6e; padding: 20px; margin: 20px 0; border: 1px solid #7dd3fc; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h4 style="margin: 0 0 15px 0; color: #0c4a6e; font-size: 16px;"><i class="fa fa-bell"></i> Status Notifikasi</h4>
                        <div style="display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">';
                
                // Status Email
                $email_status = $notification_status['email'] ?? 'pending';
                $email_icon = $email_status === 'sent' ? 'fa-check-circle text-success' : ($email_status === 'failed' ? 'fa-times-circle text-danger' : 'fa-clock text-warning');
                $email_text = $email_status === 'sent' ? 'Email terkirim' : ($email_status === 'failed' ? 'Email gagal' : 'Email pending');
                echo '<div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa ' . $email_icon . '"></i>
                        <span>Email HR: ' . $email_text . '</span>
                      </div>';
                
                // Status Telegram
                $telegram_status = $notification_status['telegram'] ?? 'pending';
                $telegram_icon = $telegram_status === 'sent' ? 'fa-check-circle text-success' : ($telegram_status === 'failed' ? 'fa-times-circle text-danger' : 'fa-clock text-warning');
                $telegram_text = $telegram_status === 'sent' ? 'Telegram terkirim' : ($telegram_status === 'failed' ? 'Telegram gagal' : 'Telegram pending');
                echo '<div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa ' . $telegram_icon . '"></i>
                        <span>Telegram: ' . $telegram_text . '</span>
                      </div>';
                
                echo '</div></div>';
            }
            
            // TAMPILKAN LINK DOWNLOAD SURAT JIKA ADA $docx_info
            if (!empty($docx_info)) {
                echo '<div style="background: #e8f5e9; color: #2e7d32; padding: 20px; margin: 20px 0; border: 1px solid #c8e6c9; border-radius: 8px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h4 style="margin: 0 0 15px 0; color: #1b5e20; font-size: 18px;"><i class="fa fa-download"></i> Surat Izin Berhasil Dibuat</h4>
                        <p style="margin: 0 0 15px 0; font-size: 16px; line-height: 1.5;">
                            <strong>Nomor Surat:</strong> ' . htmlspecialchars($docx_info['nomor_surat']) . '<br>
                            <strong>File:</strong> ' . htmlspecialchars($docx_info['filename']) . '
                        </p>
                        <a href="' . htmlspecialchars($docx_info['download_url']) . '" download class="btn-apply" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; margin-top: 10px;">
                            <i class="fa fa-download"></i> Download Surat Izin (.docx)
                        </a>
                      </div>';
            }
        }
        
        // Error messages dari processing yang gagal
        if (!empty($error_message)) {
            echo '<div style="background: #f8d7da; color: #721c24; padding: 15px; margin: 20px 0; border: 1px solid #f5c6cb; border-radius: 8px; text-align: center; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <i class="fa fa-exclamation-triangle" style="margin-right: 8px;"></i> ' . htmlspecialchars($error_message) . '
                  </div>';
        }
        
        // Check for success messages dari redirect
        if (empty($success_message) && isset($_GET['status'])) {
            $redirect_success_message = '';
            switch ($_GET['status']) {
                case 'sukses':
                    $redirect_success_message = 'Pengajuan surat izin berhasil!';
                    break;
                case 'sukses_email':
                    $redirect_success_message = 'Pengajuan surat izin berhasil! Email berhasil dikirim.';
                    break;
                case 'sukses_telegram':
                    $redirect_success_message = 'Pengajuan surat izin berhasil! Telegram berhasil dikirim.';
                    break;
                case 'sukses_email_telegram':
                    $redirect_success_message = 'Pengajuan surat izin berhasil! Email dan Telegram berhasil dikirim.';
                    break;
            }
            
            if (!empty($redirect_success_message)) {
                echo '<div style="background: #d4edda; color: #155724; padding: 15px; margin: 20px 0; border: 1px solid #c3e6cb; border-radius: 8px; text-align: center; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <i class="fa fa-check-circle" style="margin-right: 8px;"></i> ' . htmlspecialchars($redirect_success_message) . '
                      </div>';
            }
        }
        ?>
        
        <p class="info-text">Halaman ini diperuntukkan bagi seluruh karyawan KAORI Indonesia untuk mengajukan surat izin dengan mudah dan cepat. Silakan pilih jenis izin yang ingin diajukan.</p>
        
        <!-- Leave Type Selection -->
        <div class="leave-type-selector">
            <h3><i class="fa fa-clipboard-list"></i> Pilih Jenis Izin</h3>
            <div class="leave-options">
                <div class="leave-option-card" data-type="izin">
                    <div class="leave-icon">📝</div>
                    <h4>Izin</h4>
                    <p>Ajukan izin untuk keperluan pribadi, keluarga, atau mendesak lainnya</p>
                    <button type="button" class="btn-choose-leave" data-type="izin">
                        <i class="fa fa-plus"></i> Ajukan Izin Biasa
                    </button>
                </div>
                
                <div class="leave-option-card" data-type="sakit">
                    <div class="leave-icon">🤒</div>
                    <h4>Izin Sakit</h4>
                    <p>Ajukan izin sakit dengan ketentuan dokumen medis untuk ≥2 hari</p>
                    <button type="button" class="btn-choose-leave" data-type="sakit">
                        <i class="fa fa-plus"></i> Ajukan Izin Sakit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Container Izin Biasa -->
    <div class="container-izin-biasa hidden" id="container-izin-biasa">
        <!-- Back Navigation Button -->
        <div class="back-navigation">
            <button type="button" class="btn-back" onclick="goBackToSelection()">
                <i class="fa fa-arrow-left"></i> Kembali ke Pilih Jenis Izin
            </button>
        </div>
        
        <h2><i class="fa fa-file-alt"></i> Ajukan Surat Izin Biasa</h2>
        
        <!-- Pesan sukses/gagal sudah dipindah ke .content-container di atas -->
        
        <div class="info-section-izin">
            <h4><i class="fa fa-info-circle"></i> Informasi Izin</h4>
            <ul>
                <li>Surat izin akan otomatis dikirim ke email dan Telegram HR</li>
                <li>Status persetujuan akan dikomunikasikan melalui notifikasi</li>
                <li>Proses persetujuan: Langsung ke HR (tanpa supervisor)</li>
            </ul>
        </div>
        
        <p class="fill-info">Silakan lengkapi formulir pengajuan surat izin di bawah ini:</p>

        <form method="POST" action="suratizin.php" class="form-surat-izin" id="form-izin-biasa" enctype="multipart/form-data">
            <input type="hidden" name="jenis_izin" value="izin">
            <!-- ENHANCED PROCESSING: Removed redirect_to_docx parameter -->
            
            <div class="form-field">
                <label for="perihal-izin">Perihal:</label>
                <div class="input-group">
                    <input type="text" id="perihal-izin" name="perihal" required>
                </div>
            </div>
            
            <div class="form-field">
                <label for="tanggal-izin-biasa">Tanggal Mulai Izin:</label>
                <div class="input-group">
                    <input type="date" id="tanggal-izin-biasa" name="tanggal_izin" required>
                </div>
            </div>
            
            <div class="form-field">
                <label for="tanggal-selesai-izin">Tanggal Selesai Izin:</label>
                <div class="input-group">
                    <input type="date" id="tanggal-selesai-izin" name="tanggal_selesai" required>
                </div>
            </div>
            
            <div class="form-field">
                <label for="lama-izin-biasa">Lama Izin (dalam hari):</label>
                <div class="input-group">
                    <input type="number" id="lama-izin-biasa" name="lama_izin" min="1" required readonly>
                </div>
            </div>
            
            <div class="form-field">
                <label for="alasan-izin">Alasan Izin:</label>
                <div class="input-group">
                    <textarea id="alasan-izin" name="alasan_izin" rows="4" required placeholder="Jelaskan alasan secara detail..."></textarea>
                </div>
            </div>

            <?php if (empty($tanda_tangan_tersimpan)): ?>
                <div class="form-field">
                    <label for="signature-izin">Tanda Tangan Digital:</label>
                    <div class="input-group">
                        <canvas id="signature-izin" class="signature-pad" width="400" height="200"></canvas>
                        <button type="button" id="clear-signature-izin" class="btn-select-leave" style="width: auto; margin-top: 10px;">
                            <i class="fa fa-eraser"></i> Hapus Tanda Tangan
                        </button>
                        <input type="hidden" name="signature_data" id="signature-data-izin">
                        <small class="help-text">Gambarlah tanda tangan Anda dengan jelas di area di atas</small>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-field">
                    <label>Tanda Tangan Digital:</label>
                    <div class="input-group">
                        <p>Tanda tangan sudah tersimpan di profil Anda.</p>
                        <img src="uploads/tanda_tangan/<?php echo htmlspecialchars($tanda_tangan_tersimpan); ?>"
                             alt="Tanda Tangan Tersimpan"
                             style="max-width: 200px; border: 2px solid #6366f1; border-radius: 8px; padding: 10px; background: white;">
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-field">
                <div class="input-group">
                    <button type="submit" name="submit_action" value="izin_submit" class="btn-apply">
                        <i class="fa fa-paper-plane"></i> Ajukan Surat Izin Biasa
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Container Izin Sakit -->
    <div class="container-izin-sakit hidden" id="container-izin-sakit">
        <!-- Back Navigation Button -->
        <div class="back-navigation">
            <button type="button" class="btn-back" onclick="goBackToSelection()">
                <i class="fa fa-arrow-left"></i> Kembali ke Pilih Jenis Izin
            </button>
        </div>
        
        <h2><i class="fa fa-user-md"></i> Ajukan Surat Izin Sakit</h2>
        
        <!-- Pesan sukses/gagal sudah dipindah ke .content-container di atas -->
        
        <div class="info-section-sakit">
            <h4><i class="fa fa-stethoscope"></i> Informasi Izin Sakit</h4>
            <ul>
                <li><strong>1 hari:</strong> Tidak wajib dokumen medis</li>
                <li><strong>≥2 hari:</strong> Wajib lampirkan dokumen medis</li>
                <li>Surat izin sakit akan otomatis dikirim ke email dan Telegram HR</li>
                <li>Status persetujuan akan dikomunikasikan melalui notifikasi</li>
                <li>Proses persetujuan: Langsung ke HR (tanpa supervisor)</li>
            </ul>
        </div>
        
        <p class="fill-info">Silakan lengkapi formulir pengajuan surat izin sakit di bawah ini:</p>

        <form method="POST" action="suratizin.php" class="form-surat-izin" id="form-izin-sakit" enctype="multipart/form-data">
            <input type="hidden" name="jenis_izin" value="sakit">
            <!-- ENHANCED PROCESSING: Removed redirect_to_docx parameter -->
            
            <div class="form-field">
                <label for="perihal-sakit">Perihal:</label>
                <div class="input-group">
                    <input type="text" id="perihal-sakit" name="perihal" value="Izin Sakit" readonly>
                </div>
            </div>
            
            <div class="form-field">
                <label for="tanggal-izin-sakit">Tanggal Mulai Izin:</label>
                <div class="input-group">
                    <input type="date" id="tanggal-izin-sakit" name="tanggal_izin" required>
                </div>
            </div>
            
            <div class="form-field">
                <label for="tanggal-selesai-sakit">Tanggal Selesai Izin:</label>
                <div class="input-group">
                    <input type="date" id="tanggal-selesai-sakit" name="tanggal_selesai" required>
                </div>
            </div>
            
            <div class="form-field">
                <label for="lama-izin-sakit">Lama Izin (dalam hari):</label>
                <div class="input-group">
                    <input type="number" id="lama-izin-sakit" name="lama_izin" min="1" required readonly>
                    <small class="help-text" id="dokumen-medis-required" style="display: none; color: #dc2626; font-weight: bold;">
                        <i class="fa fa-exclamation-triangle"></i>
                        Izin minimal 2 hari, wajib lampirkan dokumen medis!
                    </small>
                </div>
            </div>
            
            <!-- Dokumen Medis Upload untuk Izin Sakit -->
            <div class="form-field" id="dokumen-medis-group" style="display: none;">
                <label for="dokumen_medis">Dokumen Medis (Wajib untuk ≥2 hari):</label>
                <div class="input-group">
                    <input type="file" id="dokumen_medis" name="dokumen_medis" accept=".pdf,.jpg,.jpeg,.png">
                    <small class="help-text">Format: PDF, JPG, PNG (Maksimal 2MB)</small>
                    <div id="dokumen-medis-preview" style="margin-top: 10px;"></div>
                </div>
            </div>
            
            <div class="form-field">
                <label for="alasan-sakit">Alasan/I Gejala Sakit:</label>
                <div class="input-group">
                    <textarea id="alasan-sakit" name="alasan_izin" rows="4" required placeholder="Jelaskan gejala atau alasan sakit secara detail..."></textarea>
                </div>
            </div>

            <?php if (empty($tanda_tangan_tersimpan)): ?>
                <div class="form-field">
                    <label for="signature-sakit">Tanda Tangan Digital:</label>
                    <div class="input-group">
                        <canvas id="signature-sakit" class="signature-pad" width="400" height="200"></canvas>
                        <button type="button" id="clear-signature-sakit" class="btn-select-leave" style="width: auto; margin-top: 10px;">
                            <i class="fa fa-eraser"></i> Hapus Tanda Tangan
                        </button>
                        <input type="hidden" name="signature_data" id="signature-data-sakit">
                        <small class="help-text">Gambarlah tanda tangan Anda dengan jelas di area di atas</small>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-field">
                    <label>Tanda Tangan Digital:</label>
                    <div class="input-group">
                        <p>Tanda tangan sudah tersimpan di profil Anda.</p>
                        <img src="uploads/tanda_tangan/<?php echo htmlspecialchars($tanda_tangan_tersimpan); ?>"
                             alt="Tanda Tangan Tersimpan"
                             style="max-width: 200px; border: 2px solid #ef4444; border-radius: 8px; padding: 10px; background: white;">
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-field">
                <div class="input-group">
                    <button type="submit" name="submit_action" value="sakit_submit" class="btn-apply" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);">
                        <i class="fa fa-paper-plane"></i> Ajukan Surat Izin Sakit
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Processing Overlay -->
    <div class="processing-overlay" id="processingOverlay">
        <div class="processing-content">
            <div class="processing-spinner"></div>
            <div class="processing-message" id="processingMessage">Memproses pengajuan izin...</div>
            <div class="processing-subtitle">Mohon tunggu sejenak</div>
        </div>
    </div>

    <!-- Enhanced Footer -->
    <footer>
        <div class="footer-container">
            <p class="footer-text">© 2025 KAORI Indonesia. All rights reserved.</p>
            <p class="footer-text">Sistem Pengajuan Izin yang Aman dan Terpercaya</p>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@5.0.10/dist/signature_pad.umd.min.js"></script> 
    <script>
    // Enhanced Leave Request System JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize signature pads
        const canvasIzin = document.getElementById('signature-izin');
        const canvasSakit = document.getElementById('signature-sakit');
        const clearBtnIzin = document.getElementById('clear-signature-izin');
        const clearBtnSakit = document.getElementById('clear-signature-sakit');
        const signatureInputIzin = document.getElementById('signature-data-izin');
        const signatureInputSakit = document.getElementById('signature-data-sakit');
        let signaturePadIzin = null;
        let signaturePadSakit = null;
        
        if (canvasIzin) {
            signaturePadIzin = new SignaturePad(canvasIzin, {
                penColor: 'rgb(0,0,0)',
                backgroundColor: 'rgb(255,255,255)',
                minWidth: 1,
                maxWidth: 3
            });
        }
        
        if (canvasSakit) {
            signaturePadSakit = new SignaturePad(canvasSakit, {
                penColor: 'rgb(0,0,0)',
                backgroundColor: 'rgb(255,255,255)',
                minWidth: 1,
                maxWidth: 3
            });
        }
        
        // Clear signature buttons
        if (clearBtnIzin && signaturePadIzin) {
            clearBtnIzin.addEventListener('click', function() {
                signaturePadIzin.clear();
            });
        }
        
        if (clearBtnSakit && signaturePadSakit) {
            clearBtnSakit.addEventListener('click', function() {
                signaturePadSakit.clear();
            });
        }
        
        // Leave type selection
        const leaveCards = document.querySelectorAll('.leave-option-card');
        const containerIzin = document.getElementById('container-izin-biasa');
        const containerSakit = document.getElementById('container-izin-sakit');
        const selectorSection = document.querySelector('.leave-type-selector');
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        const dateInputs = ['tanggal-izin-biasa', 'tanggal-selesai-izin', 'tanggal-izin-sakit', 'tanggal-selesai-sakit'];
        dateInputs.forEach(id => {
            const input = document.getElementById(id);
            if (input) input.min = today;
        });
        
        function selectLeaveType(type) {
            // Hide all containers first
            containerIzin.classList.add('hidden');
            containerSakit.classList.add('hidden');
            
            // Remove active class from all cards
            leaveCards.forEach(card => {
                card.classList.remove('active-izin', 'active-sakit');
            });
            
            // Add active class to selected card
            const selectedCard = document.querySelector(`[data-type="${type}"]`);
            if (selectedCard) {
                if (type === 'izin') {
                    selectedCard.classList.add('active-izin');
                    containerIzin.classList.remove('hidden');
                } else if (type === 'sakit') {
                    selectedCard.classList.add('active-sakit');
                    containerSakit.classList.remove('hidden');
                }
            }
            
            // Hide selector section after selection
            selectorSection.classList.add('hidden');
            
            // Scroll to selected container
            setTimeout(() => {
                if (type === 'izin') {
                    containerIzin.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else if (type === 'sakit') {
                    containerSakit.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 100);
        }
        
        // Add click handlers to leave type cards
        leaveCards.forEach(card => {
            card.addEventListener('click', function() {
                selectLeaveType(this.dataset.type);
            });
        });
        
        // Calculate leave duration for izin biasa
        const tglMulaiIzin = document.getElementById('tanggal-izin-biasa');
        const tglSelesaiIzin = document.getElementById('tanggal-selesai-izin');
        const lamaIzin = document.getElementById('lama-izin-biasa');
        
        // Calculate leave duration for izin sakit
        const tglMulaiSakit = document.getElementById('tanggal-izin-sakit');
        const tglSelesaiSakit = document.getElementById('tanggal-selesai-sakit');
        const lamaSakit = document.getElementById('lama-izin-sakit');
        const dokumenMedisRequired = document.getElementById('dokumen-medis-required');
        const dokumenMedisGroup = document.getElementById('dokumen-medis-group');
        const dokumenMedisInput = document.getElementById('dokumen_medis');
        
        function checkDokumenMedisRequirement() {
            const lama = parseInt(lamaSakit.value) || 0;
            if (lama >= 2) {
                dokumenMedisRequired.style.display = 'block';
                dokumenMedisGroup.style.display = 'block';
            } else {
                dokumenMedisRequired.style.display = 'none';
                dokumenMedisGroup.style.display = 'none';
            }
        }
        
        function hitungLamaIzin() {
            if (tglMulaiIzin && tglSelesaiIzin && lamaIzin) {
                if (tglMulaiIzin.value && tglSelesaiIzin.value) {
                    const start = new Date(tglMulaiIzin.value);
                    const end = new Date(tglSelesaiIzin.value);
                    
                    if (!isNaN(start) && !isNaN(end) && end >= start) {
                        const diff = Math.floor((end - start) / (1000*60*60*24)) + 1;
                        lamaIzin.value = diff;
                    } else {
                        lamaIzin.value = '';
                    }
                } else {
                    lamaIzin.value = '';
                }
            }
        }
        
        function hitungLamaSakit() {
            if (tglMulaiSakit && tglSelesaiSakit && lamaSakit) {
                if (tglMulaiSakit.value && tglSelesaiSakit.value) {
                    const start = new Date(tglMulaiSakit.value);
                    const end = new Date(tglSelesaiSakit.value);
                    
                    if (!isNaN(start) && !isNaN(end) && end >= start) {
                        const diff = Math.floor((end - start) / (1000*60*60*24)) + 1;
                        lamaSakit.value = diff;
                        checkDokumenMedisRequirement();
                    } else {
                        lamaSakit.value = '';
                    }
                } else {
                    lamaSakit.value = '';
                }
            }
        }
        
        // Event listeners for date changes
        if (tglMulaiIzin && tglSelesaiIzin && lamaIzin) {
            tglMulaiIzin.addEventListener('change', hitungLamaIzin);
            tglSelesaiIzin.addEventListener('change', hitungLamaIzin);
        }
        
        if (tglMulaiSakit && tglSelesaiSakit && lamaSakit) {
            tglMulaiSakit.addEventListener('change', hitungLamaSakit);
            tglSelesaiSakit.addEventListener('change', hitungLamaSakit);
        }
        
        // Form submission validation
        const formIzin = document.getElementById('form-izin-biasa');
        const formSakit = document.getElementById('form-izin-sakit');
        
        if (formIzin) {
            formIzin.addEventListener('submit', function(e) {
                if (signaturePadIzin && signaturePadIzin.isEmpty()) {
                    alert('Mohon gambar tanda tangan Anda terlebih dahulu.');
                    e.preventDefault();
                    return false;
                }
                
                // ENHANCED: Set signature data for izin form
                if (signaturePadIzin && !signaturePadIzin.isEmpty()) {
                    const dataURL = signaturePadIzin.toDataURL('image/png');
                    document.getElementById('signature-data-izin').value = dataURL;
                }
                
                // ENHANCED: Show enhanced processing feedback
                const submitBtn = document.getElementById('submit-izin-btn');
                const submitText = document.getElementById('submit-izin-text');
                if (submitBtn && submitText) {
                    submitText.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses...';
                    submitBtn.disabled = true;
                }
            });
        }
        
        if (formSakit) {
            formSakit.addEventListener('submit', function(e) {
                if (signaturePadSakit && signaturePadSakit.isEmpty()) {
                    alert('Mohon gambar tanda tangan Anda terlebih dahulu.');
                    e.preventDefault();
                    return false;
                }
                
                const lama = parseInt(lamaSakit.value) || 0;
                if (lama >= 2 && (!dokumenMedisInput.files || dokumenMedisInput.files.length === 0)) {
                    alert('Izin sakit minimal 2 hari wajib lampirkan dokumen medis.');
                    e.preventDefault();
                    return false;
                }
            });
        }
        
        // File upload preview for sick leave
        if (dokumenMedisInput) {
            dokumenMedisInput.addEventListener('change', function() {
                const preview = document.getElementById('dokumen-medis-preview');
                preview.innerHTML = '';
                
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    const fileName = document.createElement('div');
                    fileName.innerHTML = `<i class="fa fa-file"></i> File dipilih: ${file.name} (${Math.round(file.size/1024)}KB)`;
                    fileName.style.color = '#10b981';
                    fileName.style.fontWeight = 'bold';
                    preview.appendChild(fileName);
                }
            });
        }
    });
    
    // Global function for back navigation
    function goBackToSelection() {
        // Show the selector section
        const selectorSection = document.querySelector('.leave-type-selector');
        if (selectorSection) {
            selectorSection.classList.remove('hidden');
        }
        
        // Hide all form containers
        const containerIzin = document.getElementById('container-izin-biasa');
        const containerSakit = document.getElementById('container-izin-sakit');
        if (containerIzin) containerIzin.classList.add('hidden');
        if (containerSakit) containerSakit.classList.add('hidden');
        
        // Remove active states from cards
        const leaveCards = document.querySelectorAll('.leave-option-card');
        leaveCards.forEach(card => {
            card.classList.remove('active-izin', 'active-sakit');
        });
        
        // Scroll to selector
        setTimeout(() => {
            if (selectorSection) {
                selectorSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
    </script>
    
    <script>
    // Processing overlay JavaScript for redirect feedback
    <?php if (isset($_SESSION['show_processing']) && $_SESSION['show_processing']): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('processingOverlay');
        const message = document.getElementById('processingMessage');
        
        if (overlay && message) {
            overlay.style.display = 'flex';
            message.textContent = '<?php echo $_SESSION['processing_message']; ?>';
            
            // Clear the session flag immediately
            setTimeout(() => {
                <?php unset($_SESSION['show_processing']); unset($_SESSION['processing_message']); ?>
            }, 100);
        }
    });
    <?php endif; ?>
    
    // Add click handlers to forms to show processing overlay on submit
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const overlay = document.getElementById('processingOverlay');
                const message = document.getElementById('processingMessage');
                if (overlay && message) {
                    overlay.style.display = 'flex';
                    message.textContent = 'Mohon tunggu, sedang memproses pengajuan izin Anda...';
                }
            });
        });
    });
    </script>
</body>
</html>