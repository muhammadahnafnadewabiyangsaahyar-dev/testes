<?php
/**
 * Debug Log Viewer untuk Import Whitelist
 * 
 * Script untuk membaca dan menampilkan log import_whitelist.php
 */

session_start();

// Auto-refresh setiap 3 detik
header("Refresh: 3;");

$logFile = 'logs/import_whitelist_debug.log';
$systemLog = '/Applications/XAMPP/xamppfiles/htdocs/aplikasi/logs/import_whitelist_debug.log';
$errorLog = 'logs/absensi_errors.log';

// Function untuk baca log
function readLogFile($file) {
    if (!file_exists($file)) {
        return ["Log file tidak ditemukan: " . $file];
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    return array_reverse(array_slice($lines, -100)); // Ambil 100 baris terakhir
}

?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Import Whitelist - KAORI Indonesia</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .log-section {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #3e3e3e;
        }
        .log-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #4ec9b0;
        }
        .log-content {
            background: #1e1e1e;
            border: 1px solid #3e3e3e;
            border-radius: 4px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            font-size: 12px;
            line-height: 1.4;
        }
        .error { color: #f48771; }
        .success { color: #89ca78; }
        .info { color: #4ec9b0; }
        .debug { color: #dcdcaa; }
        .timestamp { color: #6a9955; }
        
        .controls {
            background: #2d2d2d;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #3e3e3e;
        }
        
        .btn {
            background: #0e639c;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #1177bb; }
        .btn-danger { background: #c50e1f; }
        .btn-danger:hover { background: #e01e2a; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Debug Import Whitelist</h1>
        <p>Auto-refresh setiap 3 detik | <a href="import_whitelist.php" class="btn">Kembali ke Import</a></p>
        
        <div class="controls">
            <strong>Waktu Sekarang:</strong> <?php echo date('Y-m-d H:i:s'); ?>
            <button onclick="location.reload();" class="btn">Refresh Manual</button>
            <a href="?clear=1" class="btn btn-danger" onclick="return confirm('Yakin hapus log?')">Clear Logs</a>
        </div>
        
        <?php
        // Clear logs if requested
        if (isset($_GET['clear'])) {
            if (file_exists($logFile)) file_put_contents($logFile, '');
            if (file_exists($systemLog)) file_put_contents($systemLog, '');
            echo "<div class='log-section'><div class='log-header'>Log Cleared</div><div class='log-content'>Logs telah dibersihkan.</div></div>";
        }
        
        // Read log files
        $debugLogs = readLogFile($logFile);
        $systemLogs = readLogFile($systemLog);
        $errorLogs = readLogFile($errorLog);
        ?>
        
        <div class="log-section">
            <div class="log-header">📁 Debug Logs (<?php echo count($debugLogs); ?> baris)</div>
            <div class="log-content">
                <?php 
                if (empty($debugLogs)) {
                    echo "Tidak ada log debug ditemukan.";
                } else {
                    foreach ($debugLogs as $line) {
                        $class = 'info';
                        if (strpos($line, 'ERROR') !== false) $class = 'error';
                        else if (strpos($line, 'SUCCESS') !== false || strpos($line, 'SUCCESS') !== false) $class = 'success';
                        else if (strpos($line, '=== DEBUG') !== false) $class = 'debug';
                        else if (strpos($line, 'Date:') !== false || strpos($line, 'Time:') !== false) $class = 'timestamp';
                        
                        echo "<div class='$class'>" . htmlspecialchars($line) . "</div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <div class="log-section">
            <div class="log-header">🖥️ System Logs (<?php echo count($systemLogs); ?> baris)</div>
            <div class="log-content">
                <?php 
                if (empty($systemLogs)) {
                    echo "Tidak ada system log ditemukan.";
                } else {
                    foreach ($systemLogs as $line) {
                        $class = 'info';
                        if (strpos($line, 'ERROR') !== false) $class = 'error';
                        else if (strpos($line, 'SUCCESS') !== false) $class = 'success';
                        else if (strpos($line, '=== DEBUG') !== false) $class = 'debug';
                        else if (strpos($line, 'Date:') !== false || strpos($line, 'Time:') !== false) $class = 'timestamp';
                        
                        echo "<div class='$class'>" . htmlspecialchars($line) . "</div>";
                    }
                }
                ?>
            </div>
        </div>
        
        <div class="log-section">
            <div class="log-header">⚠️ Error Logs (<?php echo count($errorLogs); ?> baris)</div>
            <div class="log-content">
                <?php 
                if (empty($errorLogs)) {
                    echo "Tidak ada error log ditemukan.";
                } else {
                    foreach ($errorLogs as $line) {
                        $class = 'info';
                        if (strpos($line, 'ERROR') !== false || strpos($line, 'Exception') !== false) $class = 'error';
                        else if (strpos($line, 'SUCCESS') !== false) $class = 'success';
                        else if (strpos($line, 'Date:') !== false || strpos($line, 'Time:') !== false) $class = 'timestamp';
                        
                        echo "<div class='$class'>" . htmlspecialchars($line) . "</div>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>