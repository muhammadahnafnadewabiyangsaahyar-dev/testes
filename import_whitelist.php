<?php
/**
 * Enhanced Import CSV dengan Anti-Duplicate + Multiple Modes
 * Features:
 * - SKIP mode: Skip existing (safe)
 * - UPDATE mode: Update existing data
 * - REPORT mode: Detailed import report
 */

session_start();

// DEBUG: Log session status
error_log("=== IMPORT CSV ENHANCED DEBUG ===");
error_log("Session ID: " . session_id());
error_log("Session status: " . session_status());
error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("csrf_token_import before: " . (isset($_SESSION['csrf_token_import']) ? 'EXISTS' : 'NOT SET'));

include 'connect.php';
include 'functions_role.php'; // Central role function

// Check admin or superadmin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'superadmin')) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// Generate CSRF token ONLY if not exists (don't regenerate!)
if (!isset($_SESSION['csrf_token_import']) || empty($_SESSION['csrf_token_import'])) {
    $_SESSION['csrf_token_import'] = bin2hex(random_bytes(32));
    error_log("csrf_token_import GENERATED: " . substr($_SESSION['csrf_token_import'], 0, 20) . '...');
} else {
    error_log("csrf_token_import EXISTS: " . substr($_SESSION['csrf_token_import'], 0, 20) . '... (reusing)');
}

$success = '';
$error = '';
$importReport = null;
$debug_info = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    // DEBUG: Log all relevant information
    error_log("=== POST REQUEST RECEIVED ===");
    error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST keys: " . implode(', ', array_keys($_POST)));
    error_log("FILES keys: " . (isset($_FILES) ? implode(', ', array_keys($_FILES)) : 'NONE'));
    
    $debug_info['post_data'] = [
        'import_button' => isset($_POST['import']) ? 'YES' : 'NO',
        'csrf_token_posted' => isset($_POST['csrf_token']) ? 'YES (length: ' . strlen($_POST['csrf_token']) . ')' : 'NO',
        'import_mode' => $_POST['import_mode'] ?? 'NOT SET',
        'file_uploaded' => isset($_FILES['import_file']) ? 'YES' : 'NO'
    ];
    
    $debug_info['session_data'] = [
        'csrf_token_exists' => isset($_SESSION['csrf_token_import']) ? 'YES (length: ' . strlen($_SESSION['csrf_token_import']) . ')' : 'NO',
        'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
        'role' => $_SESSION['role'] ?? 'NOT SET'
    ];
    
    error_log("POST csrf_token: " . (isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 20) . '... (length: ' . strlen($_POST['csrf_token']) . ')' : 'NOT SET'));
    error_log("SESSION csrf_token_import: " . (isset($_SESSION['csrf_token_import']) ? substr($_SESSION['csrf_token_import'], 0, 20) . '... (length: ' . strlen($_SESSION['csrf_token_import']) . ')' : 'NOT SET'));
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token_import']) {
        error_log("❌ CSRF VALIDATION FAILED!");
        error_log("Reason: " . (!isset($_POST['csrf_token']) ? 'Token not posted' : 'Token mismatch'));
        
        $debug_info['csrf_error'] = [
            'posted_token' => isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 20) . '...' : 'NULL',
            'session_token' => isset($_SESSION['csrf_token_import']) ? substr($_SESSION['csrf_token_import'], 0, 20) . '...' : 'NULL',
            'tokens_match' => (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token_import']) && $_POST['csrf_token'] === $_SESSION['csrf_token_import']) ? 'YES' : 'NO'
        ];
        $error = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        error_log("✅ CSRF VALIDATION PASSED!");
        
        $file = $_FILES['import_file'] ?? null;
        $import_mode = $_POST['import_mode'] ?? 'skip';
    
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $filename = $file['tmp_name'];
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        
        if (!in_array($extension, ['csv', 'txt'])) {
            $error = 'Hanya file CSV atau TXT yang diperbolehkan.';
        } else {
            try {
                error_log("Opening CSV file: $filename");
                $handle = fopen($filename, "r");
                if ($handle) {
                    error_log("CSV file opened successfully");
                    $report = [
                        'imported' => 0,
                        'updated' => 0,
                        'skipped' => 0,
                        'errors' => 0,
                        'details' => []
                    ];
                    
                    $rowNum = 0;
                    
                    while (($row = fgetcsv($handle, 2000, ";")) !== false) {
                        $rowNum++;
                        
                        // Skip header
                        if ($rowNum === 1 && (stripos($row[1] ?? '', 'nama') !== false)) {
                            $report['details'][] = [
                                'row' => $rowNum,
                                'status' => 'header',
                                'message' => 'Header row - skipped'
                            ];
                            continue;
                        }
                        
                        // Parse data - support Kaori HR database format
                        $nama = trim($row[1] ?? '');
                        $posisi = trim($row[2] ?? '');
                        
                        // Komponen gaji sesuai dengan database Kaori_hr (5 komponen utama)
                        $gaji_pokok = isset($row[3]) && $row[3] !== '' ? floatval($row[3]) : null;
                        $tunjangan_transport = isset($row[4]) && $row[4] !== '' ? floatval($row[4]) : null;
                        $tunjangan_makan = isset($row[5]) && $row[5] !== '' ? floatval($row[5]) : null;
                        $overwork = isset($row[6]) && $row[6] !== '' ? floatval($row[6]) : null;
                        $tunjangan_jabatan = isset($row[7]) && $row[7] !== '' ? floatval($row[7]) : null;
                        // Extended columns ignored (not in Kaori HR database)
                        // $bonus_kehadiran = isset($row[8]) && $row[8] !== '' ? floatval($row[8]) : null;
                        // $bonus_marketing = isset($row[9]) && $row[9] !== '' ? floatval($row[9]) : null;
                        // $insentif_omset = isset($row[10]) && $row[10] !== '' ? floatval($row[10]) : null;
                        
                        // Validate
                        if ($nama === '') {
                            $report['skipped']++;
                            $report['details'][] = [
                                'row' => $rowNum,
                                'status' => 'error',
                                'message' => 'Empty name - skipped'
                            ];
                            continue;
                        }
                        
                        // Auto-detect role dari database
                        $role = getRoleByPosisiFromDB($pdo, $posisi);
                        
                        // Check existing
                        $stmt = $pdo->prepare("SELECT * FROM pegawai_whitelist WHERE nama_lengkap = ?");
                        $stmt->execute([$nama]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existing) {
                            // Data already exists
                            
                            if ($import_mode === 'skip') {
                                // SKIP MODE: Don't import duplicate
                                $report['skipped']++;
                                $report['details'][] = [
                                    'row' => $rowNum,
                                    'nama' => $nama,
                                    'status' => 'skipped',
                                    'message' => "Already exists (Posisi: {$existing['posisi']}, Role: {$existing['role']})",
                                    'action' => 'SKIP'
                                ];
                                
                            } elseif ($import_mode === 'update') {
                                // UPDATE MODE: Update existing data + komponen gaji
                                try {
                                    $old_posisi = $existing['posisi'];
                                    $old_role = $existing['role'];
                                    
                                    // Update pegawai_whitelist dengan 5 komponen gaji sesuai database Kaori_hr
                                    $stmt = $pdo->prepare("
                                        UPDATE pegawai_whitelist SET
                                            posisi = ?,
                                            role = ?,
                                            gaji_pokok = ?,
                                            tunjangan_transport = ?,
                                            tunjangan_makan = ?,
                                            overwork = ?,
                                            tunjangan_jabatan = ?
                                        WHERE nama_lengkap = ?
                                    ");
                                    $stmt->execute([
                                        $posisi, $role,
                                        $gaji_pokok ?? 0,
                                        $tunjangan_transport ?? 0,
                                        $tunjangan_makan ?? 0,
                                        $overwork ?? 0,
                                        $tunjangan_jabatan ?? 0,
                                        $nama
                                    ]);
                                    
                                    $report['updated']++;
                                    $hasGajiData = $gaji_pokok !== null || $tunjangan_transport !== null || $tunjangan_makan !== null;
                                    $gajiMsg = $hasGajiData ? " + Gaji updated" : "";
                                    $report['details'][] = [
                                        'row' => $rowNum,
                                        'nama' => $nama,
                                        'status' => 'updated',
                                        'message' => "Updated: Posisi ($old_posisi → $posisi), Role ($old_role → $role)$gajiMsg",
                                        'action' => 'UPDATE'
                                    ];
                                } catch (Exception $e) {
                                    $report['errors']++;
                                    $report['details'][] = [
                                        'row' => $rowNum,
                                        'nama' => $nama,
                                        'status' => 'error',
                                        'message' => 'Update failed: ' . $e->getMessage(),
                                        'action' => 'ERROR'
                                    ];
                                }
                            }
                            
                        } else {
                            // New data - INSERT
                            try {
                                // Insert ke pegawai_whitelist dengan 5 komponen gaji sesuai database Kaori_hr
                                $stmt = $pdo->prepare("
                                    INSERT INTO pegawai_whitelist (
                                        nama_lengkap, posisi, status_registrasi, role,
                                        gaji_pokok, tunjangan_transport, tunjangan_makan, overwork,
                                        tunjangan_jabatan
                                    ) VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $nama, $posisi, $role,
                                    $gaji_pokok ?? 0,
                                    $tunjangan_transport ?? 0,
                                    $tunjangan_makan ?? 0,
                                    $overwork ?? 0,
                                    $tunjangan_jabatan ?? 0
                                ]);
                                
                                $report['imported']++;
                                $hasGajiData = $gaji_pokok !== null || $tunjangan_transport !== null || $tunjangan_makan !== null;
                                $gajiMsg = $hasGajiData ? " + Gaji data saved" : "";
                                
                                $report['details'][] = [
                                    'row' => $rowNum,
                                    'nama' => $nama,
                                    'status' => 'imported',
                                    'message' => "New entry: $nama ($posisi) as role='$role'$gajiMsg",
                                    'action' => 'INSERT'
                                ];
                            } catch (PDOException $e) {
                                $report['errors']++;
                                $report['details'][] = [
                                    'row' => $rowNum,
                                    'nama' => $nama,
                                    'status' => 'error',
                                    'message' => 'Database error: ' . $e->getMessage(),
                                    'action' => 'ERROR'
                                ];
                            }
                        }
                    }
                    
                    fclose($handle);
                    $importReport = $report;
                    
                    // Success message
                    $success = "Import complete! Imported: {$report['imported']}, Updated: {$report['updated']}, Skipped: {$report['skipped']}, Errors: {$report['errors']}";
                }
            } catch (Exception $e) {
                $error = 'Import failed: ' . $e->getMessage();
            }
        }
        } else {
            $error = 'No file uploaded or upload error.';
        }
    } // Close CSRF validation else block
}
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Performance: Critical meta tags for faster loading -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Enhanced Import CSV - Anti-Duplicate</title>
    
    <!-- Security: Content Security Policy -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    
    <!-- Performance: Preload critical resources -->
    <link rel="preload" href="style_modern.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="style_modern.css"></noscript>
    
    <!-- Performance: DNS prefetch for external resources -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    
    <!-- Security: Validate file existence before including with XSS protection -->
    <?php
    // Sanitize navbar path to prevent directory traversal
    $navbar_path = basename('navbar.php');
    $navbar_full_path = __DIR__ . '/' . $navbar_path;
    
    if (file_exists($navbar_full_path) && is_readable($navbar_full_path)) {
        include $navbar_full_path;
    } else {
        error_log("SECURITY WARNING: navbar.php not found or not readable at: " . realpath($navbar_full_path));
        // Secure fallback minimal navbar
        echo '<nav class="navbar-fallback" role="navigation" aria-label="Main navigation">';
        echo '<div style="background: #333; color: white; padding: 10px; margin-bottom: 20px;">';
        echo '<strong>Sistem Import CSV - Enhanced Security</strong>';
        echo '</div></nav>';
    }
    ?>
    
    <!-- Performance: Optimized CSS loading with cache busting -->
    <link rel="stylesheet" href="style_modern.css?v=<?= hash_file('crc32', 'style_modern.css') ?: time() ?>" 
          media="all" crossorigin="anonymous" integrity="">
    
    <style>
        /* Import Whitelist Specific Styles - Using style_modern.css system */
        :root {
            /* Override some variables for import whitelist specific needs */
            --import-primary: #6366f1;
            --import-primary-dark: #4f46e5;
            --import-success: #10b981;
            --import-warning: #f59e0b;
            --import-error: #ef4444;
            --import-info: #3b82f6;
        }
        
        /* Import Whitelist Container */
        .import-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: var(--radius-2xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }
        
        .import-header {
            background: linear-gradient(135deg, var(--import-primary), var(--import-primary-dark));
            color: white;
            padding: var(--spacing-8) var(--spacing-10);
            text-align: center;
            position: relative;
        }
        
        .import-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Ccircle cx='30' cy='30' r='2'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        
        .import-content {
            padding: var(--spacing-10);
        }
        
        /* Import specific card styles */
        .import-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-6);
            margin-bottom: var(--spacing-6);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: var(--transition-normal);
        }
        
        .import-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
        }
        
        /* Import Form Container */
        .import-form-container {
            background: var(--gray-50);
            border-radius: var(--radius-xl);
            padding: var(--spacing-8);
            margin: var(--spacing-6) 0;
            border: 1px solid var(--gray-200);
        }
        
        /* File Input Styling */
        .file-input-wrapper {
            position: relative;
            display: block;
        }
        
        .file-input {
            width: 100%;
            padding: var(--spacing-4);
            border: 2px dashed var(--import-primary);
            border-radius: var(--radius-lg);
            background: white;
            cursor: pointer;
            transition: var(--transition-fast);
            font-size: var(--font-size-base);
        }
        
        .file-input:hover {
            border-color: var(--import-primary-dark);
            background: var(--gray-50);
        }
        
        .file-input:focus {
            outline: none;
            border-color: var(--import-primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        /* Status specific cards */
        .status-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--import-success);
            color: #065f46;
        }
        
        .status-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--import-error);
            color: #991b1b;
        }
        
        .status-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--import-info);
            color: #1e40af;
        }
        
        .status-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid var(--import-warning);
            color: #92400e;
        }
        
        /* Import specific table */
        .import-table {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-200);
        }
        
        .import-table .table {
            margin: 0;
            box-shadow: none;
            border: none;
        }
        
        .import-table .table th {
            background: linear-gradient(135deg, var(--import-primary), var(--import-primary-dark));
            color: white;
        }
        
        /* Import specific badges */
        .import-badge {
            display: inline-flex;
            align-items: center;
            padding: var(--spacing-1) var(--spacing-3);
            border-radius: var(--radius-2xl);
            font-size: var(--font-size-xs);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .import-badge.imported {
            background: var(--import-success);
            color: white;
        }
        
        .import-badge.updated {
            background: var(--import-warning);
            color: white;
        }
        
        .import-badge.skipped {
            background: var(--gray-400);
            color: white;
        }
        
        .import-badge.error {
            background: var(--import-error);
            color: white;
        }
        
        /* Debug box styling */
        .debug-box {
            background: var(--gray-900);
            color: #00ff00;
            border-radius: var(--radius-lg);
            padding: var(--spacing-6);
            margin: var(--spacing-6) 0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: var(--font-size-sm);
            overflow-x: auto;
            border: 1px solid var(--gray-700);
        }
        
        .debug-box h3 {
            color: var(--import-info);
            margin-bottom: var(--spacing-4);
            font-size: var(--font-size-base);
        }
        
        .debug-box pre {
            background: none;
            color: #00ff00;
            padding: 0;
            overflow-x: auto;
            white-space: pre-wrap;
            margin: 0;
        }
        
        /* Import summary cards */
        .import-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-4);
            margin: var(--spacing-6) 0;
        }
        
        .summary-card {
            text-align: center;
            padding: var(--spacing-6);
            background: white;
            border-radius: var(--radius-xl);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: var(--transition-normal);
        }
        
        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .summary-value {
            font-size: var(--font-size-4xl);
            font-weight: 800;
            margin-bottom: var(--spacing-2);
        }
        
        .summary-value.imported { color: var(--import-success); }
        .summary-value.updated { color: var(--import-warning); }
        .summary-value.skipped { color: var(--gray-500); }
        .summary-value.errors { color: var(--import-error); }
        
        .summary-label {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .import-content {
                padding: var(--spacing-6);
            }
            
            .import-header {
                padding: var(--spacing-6) var(--spacing-6);
            }
            
            .import-form-container {
                padding: var(--spacing-6);
            }
            
            .import-summary {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--spacing-3);
            }
            
            .summary-card {
                padding: var(--spacing-4);
            }
            
            .summary-value {
                font-size: var(--font-size-3xl);
            }
        }
        
        @media (max-width: 480px) {
            .import-content {
                padding: var(--spacing-4);
            }
            
            .import-summary {
                grid-template-columns: 1fr;
            }
            
            .summary-value {
                font-size: var(--font-size-2xl);
            }
        }
    </style>
</head>
<body>
    <div class="import-container">
        <div class="import-header">
            <h1>🚀 Enhanced Import CSV - Anti-Duplicate</h1>
        </div>
        
        <div class="import-content">
            <?php if ($success): ?>
                <div class="import-card status-success">
                    <div class="card-header">
                        <span style="margin-right: var(--spacing-3);">✅</span>
                        <strong>Success!</strong>
                    </div>
                    <div><?= $success ?></div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="import-card status-error">
                    <div class="card-header">
                        <span style="margin-right: var(--spacing-3);">❌</span>
                        <strong>Error!</strong>
                    </div>
                    <div><?= htmlspecialchars($error) ?></div>
                    
                    <!-- DEBUG INFO when error occurs -->
                    <?php if (!empty($debug_info)): ?>
                        <div class="debug-box" style="margin-top: 20px;">
                            <h3>🔍 DEBUG INFORMATION:</h3>
                            <pre><?= print_r($debug_info, true) ?></pre>
                            
                            <h4 style="color: #ffeb3b; margin-top: 16px;">Troubleshooting Steps:</h4>
                            <ol style="color: #00ff00;">
                                <li>Check if CSRF token is in the form (view page source)</li>
                                <li>Verify session is active (check user_id and role)</li>
                                <li>Try refreshing the page to regenerate token</li>
                                <li>Clear browser cache and cookies</li>
                                <li>Check if session.save_path is writable</li>
                            </ol>
                            
                            <p style="color: #00bfff;"><strong>Session Token (first 20 chars):</strong> <?= isset($_SESSION['csrf_token_import']) ? substr($_SESSION['csrf_token_import'], 0, 20) . '...' : 'NOT SET' ?></p>
                            <p style="color: #00bfff;"><strong>Posted Token (first 20 chars):</strong> <?= isset($_POST['csrf_token']) ? substr($_POST['csrf_token'], 0, 20) . '...' : 'NOT SET' ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="import-card status-info">
                <div class="card-header">
                    <span style="margin-right: var(--spacing-3);">ℹ️</span>
                    <strong>Import Features</strong>
                </div>
                <div>
                    <div style="margin-bottom: 16px;">
                        <strong>🎯 Fitur Import:</strong><br>
                        - ✅ Cek duplikasi berdasarkan <strong>Nama Lengkap</strong><br>
                        - ✅ UNIQUE constraint di database<br>
                        - ✅ Pilih mode import: SKIP atau UPDATE<br>
                        - ✅ <strong>Support import komponen gaji langsung!</strong> (Tidak perlu akun dulu)<br>
                        - ✅ Sesuai dengan database Kaori_hr (5 komponen gaji)<br>
                        - ✅ Detailed import report
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <strong>📄 Format CSV (Database Kaori_hr):</strong><br>
                        <code style="background: #f8f9fa; padding: 8px; border-radius: 6px; display: block; margin: 8px 0;">No; Nama Lengkap; Posisi; Gaji Pokok; Tunjangan Transport; Tunjangan Makan; Overwork; Tunjangan Jabatan</code>
                    </div>
                    
                    <div style="margin-bottom: 16px;">
                        <strong>Example:</strong><br>
                        <code style="background: #f8f9fa; padding: 6px; border-radius: 4px; display: block; margin: 4px 0;">1;John Doe;Manager;5000000;500000;300000;0;1000000</code><br>
                        <code style="background: #f8f9fa; padding: 6px; border-radius: 4px; display: block; margin: 4px 0;">2;Jane Smith;Staff;3000000;300000;200000;0;0</code>
                    </div>
                    
                    <div>
                        <strong>📌 Data Storage:</strong><br>
                        - ✅ Data gaji langsung tersimpan di <code>pegawai_whitelist</code><br>
                        - ✅ Tidak perlu menunggu pegawai register<br>
                        - ✅ Saat pegawai register nanti, data gaji akan otomatis tersinkronisasi
                    </div>
                </div>
            </div>
            
            <!-- DEBUG: Show CSRF Token Status -->
            <div class="import-card status-warning">
                <div class="card-header">
                    <span style="margin-right: var(--spacing-3);">🔐</span>
                    <strong>CSRF Token Status</strong>
                </div>
                <div>
                    <div style="margin-bottom: 12px;">
                        Session Token: <?= isset($_SESSION['csrf_token_import']) ? '✅ Active (' . strlen($_SESSION['csrf_token_import']) . ' chars)' : '❌ Not Set' ?><br>
                        Form Token: <span id="form-token-status">Checking...</span>
                    </div>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const tokenInput = document.querySelector('input[name="csrf_token"]');
                            const status = document.getElementById('form-token-status');
                            if (tokenInput && tokenInput.value) {
                                status.innerHTML = '✅ Present in form (' + tokenInput.value.length + ' chars)';
                                status.style.color = '#28a745';
                                status.style.fontWeight = '600';
                            } else {
                                status.innerHTML = '❌ Missing from form!';
                                status.style.color = '#dc3545';
                                status.style.fontWeight = '600';
                            }
                        });
                    </script>
                </div>
            </div>
            
            <div class="import-form-container">
                <form method="post" enctype="multipart/form-data" onsubmit="return debugFormSubmit(this);">
                    <!-- CSRF Token for import -->
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token_import'] ?>">
                    
                    <div class="form-group">
                        <label for="import_mode" class="form-label">🎯 Import Mode</label>
                        <select name="import_mode" id="import_mode" class="form-control focus-ring" onchange="updateModeDescription()">
                            <option value="skip">SKIP - Skip existing data (Safe, Default)</option>
                            <option value="update">UPDATE - Update existing data (Advanced)</option>
                        </select>
                    </div>
                    
                    <div class="import-card status-warning" id="mode-description">
                        <div class="card-header">
                            <span style="margin-right: var(--spacing-3);">📌</span>
                            <strong>SKIP MODE (Recommended)</strong>
                        </div>
                        <div>
                            - Jika nama sudah ada → <strong>SKIP</strong>, tidak diimport<br>
                            - Aman, tidak akan overwrite data existing<br>
                            - Cocok untuk: Import pegawai baru
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="import_file" class="form-label">📁 Select CSV File</label>
                        <div class="file-input-wrapper">
                            <input type="file" name="import_file" id="import_file" class="file-input" accept=".csv,.txt" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="import" value="1" class="btn btn-primary">🚀 Import CSV</button>
                    </div>
                </form>
            </div>
        
        <?php if ($importReport): ?>
                <div class="import-card">
                    <h2>📊 Import Report</h2>
                    
                    <div class="import-summary">
                        <div class="summary-card">
                            <div class="summary-value imported"><?= $importReport['imported'] ?></div>
                            <div class="summary-label">Imported (New)</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value updated"><?= $importReport['updated'] ?></div>
                            <div class="summary-label">Updated</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value skipped"><?= $importReport['skipped'] ?></div>
                            <div class="summary-label">Skipped</div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-value errors"><?= $importReport['errors'] ?></div>
                            <div class="summary-label">Errors</div>
                        </div>
                    </div>
                    
                    <div class="import-table">
                        <div class="table-wrapper">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Nama</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($importReport['details'] as $detail): ?>
                                        <tr>
                                            <td><?= $detail['row'] ?></td>
                                            <td><?= htmlspecialchars($detail['nama'] ?? '-') ?></td>
                                            <td><span class="import-badge <?= $detail['status'] ?>"><?= strtoupper($detail['status']) ?></span></td>
                                            <td><?= htmlspecialchars($detail['message']) ?></td>
                                            <td><?= $detail['action'] ?? '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="nav-links" style="text-align: center; margin-top: var(--spacing-8); padding-top: var(--spacing-6); border-top: 1px solid var(--gray-200);">
                <a href="whitelist.php" class="btn btn-secondary">← Back to Whitelist</a>
                <a href="template_import_kaori_hr.csv" download class="btn btn-secondary">📥 Download Template Kaori_hr</a>
                <a href="import_whitelist.php" class="btn btn-secondary">🔄 Change Import Method</a>
            </div>
            
            <!-- DEBUG INFO BOX (Always show in debug mode) -->
            <?php if (!empty($debug_info)): ?>
                <div class="debug-box">
                    <h3>🔍 Debug Information</h3>
                    <pre><?= print_r($debug_info, true) ?></pre>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function debugFormSubmit(form) {
            console.log('=== FORM SUBMIT DEBUG ===');
            console.log('Form action:', form.action);
            console.log('Form method:', form.method);
            console.log('Form enctype:', form.enctype);
            
            const csrfToken = form.querySelector('input[name="csrf_token"]');
            console.log('CSRF Token Element:', csrfToken);
            console.log('CSRF Token Value:', csrfToken ? csrfToken.value : 'NOT FOUND');
            console.log('CSRF Token Length:', csrfToken ? csrfToken.value.length : 0);
            
            const importButton = form.querySelector('button[name="import"]');
            console.log('Import Button:', importButton);
            console.log('Import Button Value:', importButton ? importButton.value : 'NOT FOUND');
            
            const file = form.querySelector('input[name="import_file"]');
            console.log('File Input:', file);
            console.log('File Selected:', file && file.files.length > 0 ? file.files[0].name : 'NO FILE');
            
            // Show alert with debug info
            const debugMsg = `
DEBUG INFO:
- CSRF Token: ${csrfToken ? '✅ Present (' + csrfToken.value.length + ' chars)' : '❌ Missing'}
- Import Button: ${importButton ? '✅ Present' : '❌ Missing'}
- File: ${file && file.files.length > 0 ? '✅ ' + file.files[0].name : '❌ No file selected'}

Check browser console for detailed logs.
            `;
            
            if (!csrfToken || !csrfToken.value) {
                alert('ERROR: CSRF Token is missing from form!\n\n' + debugMsg);
                return false;
            }
            
            if (!file || file.files.length === 0) {
                alert('Please select a CSV file to upload.');
                return false;
            }
            
            console.log('Form validation passed. Submitting...');
            return true;
        }
        
        function updateModeDescription() {
            const mode = document.getElementById('import_mode').value;
            const desc = document.getElementById('mode-description');
            
            if (mode === 'skip') {
                desc.innerHTML = `
                    <div class="card-header">
                        <span style="margin-right: var(--spacing-3);">📌</span>
                        <strong>SKIP MODE (Recommended)</strong>
                    </div>
                    <div>
                        - Jika nama sudah ada → <strong>SKIP</strong>, tidak diimport<br>
                        - Aman, tidak akan overwrite data existing<br>
                        - Cocok untuk: Import pegawai baru
                    </div>
                `;
                desc.className = 'import-card status-success';
            } else if (mode === 'update') {
                desc.innerHTML = `
                    <div class="card-header">
                        <span style="margin-right: var(--spacing-3);">⚠️</span>
                        <strong>UPDATE MODE (Advanced)</strong>
                    </div>
                    <div>
                        - Jika nama sudah ada → <strong>UPDATE</strong> posisi dan role<br>
                        - Will overwrite existing data!<br>
                        - Cocok untuk: Bulk update pegawai existing
                    </div>
                `;
                desc.className = 'import-card status-warning';
            }
        }
    </script>
</body>
</html>
