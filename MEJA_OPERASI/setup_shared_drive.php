<?php
/**
 * Setup Shared Drive for Google Drive Integration
 * Instructions and helper script for setting up Google Shared Drives
 */

session_start();
include 'connect.php';

// Define DISABLE_LOGGING to prevent logger issues
define('DISABLE_LOGGING', true);

$message = '';
$instructions = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['check_shared_drives'])) {
        // Check if shared drives are available
        require_once 'classes/GoogleDriveService.php';

        try {
            $driveService = new GoogleDriveService();

            if ($driveService->isAvailable()) {
                $message = "✅ Google Drive service is available and ready to use!";
                $instructions = "Service is working. You can now use Google Drive for file storage.";
            } else {
                $message = "❌ Google Drive service is not available. Check the error logs.";
                $instructions = "Service initialization failed. Check PHP error logs for details.";
            }
        } catch (Exception $e) {
            $message = "❌ Error: " . $e->getMessage();
            $instructions = "Failed to initialize Google Drive service.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Google Shared Drive - KAORI HR</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .setup-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .setup-header {
            background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }

        .setup-body {
            padding: 30px;
        }

        .step-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
        }

        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .step-number {
            background: #4285f4;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }

        .step-title {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            margin: 0;
        }

        .step-content {
            color: #666;
            line-height: 1.6;
        }

        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            margin: 10px 0;
            overflow-x: auto;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .warning-box h4 {
            color: #856404;
            margin-top: 0;
        }

        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }

        .success-box h4 {
            color: #155724;
            margin-top: 0;
        }

        .btn-setup {
            background: linear-gradient(135deg, #4285f4 0%, #34a853 100%);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: transform 0.2s ease;
            margin: 10px 5px;
        }

        .btn-setup:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alternatives {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .alternatives h3 {
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }

        .alternative-option {
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }

        .alternative-option h4 {
            margin-top: 0;
            color: #007bff;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-title">🔗 Setup Google Shared Drive</div>
    <div class="subtitle-container">
        <p class="subtitle">Panduan setup Google Shared Drive untuk penyimpanan file unlimited</p>
    </div>

    <div class="content-container">
        <div class="setup-container">
            <div class="setup-header">
                <h1><i class="fab fa-google-drive"></i> Google Shared Drive Setup</h1>
                <p>Setup shared drive untuk mengatasi batasan quota service account</p>
            </div>

            <div class="setup-body">
                <?php if ($message): ?>
                    <div class="message <?php
                        echo strpos($message, '✅') === 0 ? 'success' :
                             (strpos($message, '❌') === 0 ? 'error' : 'warning');
                    ?>">
                        <i class="fas fa-<?php
                            echo strpos($message, '✅') === 0 ? 'check-circle' :
                                 (strpos($message, '❌') === 0 ? 'times-circle' : 'exclamation-triangle');
                        ?>"></i>
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <!-- Step 1: Create Shared Drive -->
                <div class="step-section">
                    <div class="step-header">
                        <div class="step-number">1</div>
                        <h3 class="step-title">Buat Shared Drive</h3>
                    </div>
                    <div class="step-content">
                        <p>Shared Drive memberikan storage unlimited dan bisa diakses oleh service account.</p>

                        <ol>
                            <li>Buka <a href="https://drive.google.com" target="_blank">Google Drive</a></li>
                            <li>Klik "Shared drives" di sidebar kiri</li>
                            <li>Klik "New" → pilih "Shared drive"</li>
                            <li>Beri nama: <strong>KAORI_HR_FILES</strong></li>
                            <li>Klik "Create"</li>
                        </ol>

                        <div class="warning-box">
                            <h4><i class="fas fa-exclamation-triangle"></i> Penting!</h4>
                            <p>Nama shared drive HARUS <strong>KAORI_HR_FILES</strong> (case-sensitive)</p>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Add Service Account -->
                <div class="step-section">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <h3 class="step-title">Tambahkan Service Account</h3>
                    </div>
                    <div class="step-content">
                        <p>Share shared drive dengan service account agar bisa upload file.</p>

                        <ol>
                            <li>Buka shared drive "KAORI_HR_FILES" yang baru dibuat</li>
                            <li>Klik "Share" button (kanan atas)</li>
                            <li>Masukkan email service account:</li>
                        </ol>

                        <div class="code-block">
# Cari email service account di file credentials:
cat config/google_drive_credentials.json | grep "client_email"

# Atau lihat di Google Cloud Console → IAM & Admin → Service Accounts
                        </div>

                        <p><strong>Berikan role "Manager"</strong> untuk full access.</p>

                        <div class="warning-box">
                            <h4><i class="fas fa-exclamation-triangle"></i> Manager Role Required</h4>
                            <p>Service account butuh role "Manager" untuk membuat folder dan upload file.</p>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Enable API -->
                <div class="step-section">
                    <div class="step-header">
                        <div class="step-number">3</div>
                        <h3 class="step-title">Enable Drive API</h3>
                    </div>
                    <div class="step-content">
                        <p>Pastikan Google Drive API sudah enabled di Google Cloud Console.</p>

                        <ol>
                            <li>Buka <a href="https://console.cloud.google.com/apis/library" target="_blank">Google Cloud Console APIs</a></li>
                            <li>Cari "Google Drive API"</li>
                            <li>Pastikan status "Enabled"</li>
                        </ol>
                    </div>
                </div>

                <!-- Step 4: Test Connection -->
                <div class="step-section">
                    <div class="step-header">
                        <div class="step-number">4</div>
                        <h3 class="step-title">Test Koneksi</h3>
                    </div>
                    <div class="step-content">
                        <p>Test apakah shared drive sudah bekerja dengan benar.</p>

                        <form method="POST" action="">
                            <button type="submit" name="check_shared_drives" class="btn-setup">
                                <i class="fas fa-plug"></i> Test Shared Drive Connection
                            </button>
                        </form>

                        <?php if ($instructions): ?>
                            <div class="success-box">
                                <h4><i class="fas fa-info-circle"></i> Status:</h4>
                                <p><?php echo htmlspecialchars($instructions); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Alternative Solutions -->
                <div class="alternatives">
                    <h3><i class="fas fa-lightbulb"></i> Alternatif Solutions</h3>

                    <div class="alternative-option">
                        <h4><i class="fas fa-user"></i> OAuth 2.0 User Authentication</h4>
                        <p>User login dengan Google account untuk upload file. Lebih secure tapi butuh user interaction.</p>
                        <small><strong>Pro:</strong> Unlimited storage, secure | <strong>Con:</strong> User perlu login</small>
                    </div>

                    <div class="alternative-option">
                        <h4><i class="fas fa-building"></i> Google Workspace (Paid)</h4>
                        <p>Gunakan Google Workspace business account dengan domain-wide delegation.</p>
                        <small><strong>Pro:</strong> Full admin control | <strong>Con:</strong> Berbayar</small>
                    </div>

                    <div class="alternative-option">
                        <h4><i class="fas fa-server"></i> Local Storage (Current)</h4>
                        <p>Gunakan penyimpanan lokal server. Simple dan tidak butuh setup Google API.</p>
                        <small><strong>Pro:</strong> Simple, no cost | <strong>Con:</strong> Limited by server space</small>
                    </div>
                </div>

                <!-- Links -->
                <div class="step-section">
                    <h3><i class="fas fa-external-link-alt"></i> Useful Links</h3>
                    <ul>
                        <li><a href="https://developers.google.com/workspace/guides/create-shared-drive" target="_blank">Create Shared Drive Guide</a></li>
                        <li><a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                        <li><a href="https://drive.google.com" target="_blank">Google Drive</a></li>
                        <li><a href="test_google_drive.php" target="_blank">Test Google Drive Upload</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</body>
</html>