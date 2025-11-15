<?php
/**
 * Absensi Log Viewer - Real-time debugging tool
 * Admin only access
 */

session_start();

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die('Access denied. Admin only.');
}

// Clear log action
if (isset($_GET['action']) && $_GET['action'] === 'clear_log') {
    file_put_contents('logs/absensi_errors.log', '');
    header('Location: view_absen_log.php?cleared=1');
    exit;
}

// Read PHP error log
$php_error_log = [];
if (function_exists('ini_get')) {
    $error_log_path = ini_get('error_log');
    if ($error_log_path && file_exists($error_log_path)) {
        $lines = file($error_log_path);
        $php_error_log = array_slice(array_reverse($lines), 0, 50); // Last 50 lines
    }
}

// Read custom absensi error log
$absensi_errors = [];
if (file_exists('logs/absensi_errors.log')) {
    $lines = file('logs/absensi_errors.log');
    $absensi_errors = array_reverse($lines); // Newest first
}

// Auto-refresh setting
$auto_refresh = isset($_GET['auto']) ? (int)$_GET['auto'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Absensi Debug Log Viewer</title>
    <?php if ($auto_refresh > 0): ?>
    <meta http-equiv="refresh" content="<?= $auto_refresh ?>">
    <?php endif; ?>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #4ec9b0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #4ec9b0;
        }
        .controls {
            background: #252526;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary { background: #0e639c; color: white; }
        .btn-danger { background: #f48771; color: white; }
        .btn-success { background: #4ec9b0; color: #1e1e1e; }
        .btn:hover { opacity: 0.8; }
        .log-section {
            background: #252526;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .log-section h2 {
            color: #569cd6;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .log-entry {
            padding: 10px;
            margin-bottom: 5px;
            background: #1e1e1e;
            border-left: 4px solid #4ec9b0;
            border-radius: 3px;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
        }
        .log-entry.error {
            border-left-color: #f48771;
            background: #2d1f1f;
        }
        .log-entry.warning {
            border-left-color: #dcdcaa;
            background: #2d2d1f;
        }
        .log-entry.success {
            border-left-color: #4ec9b0;
        }
        .timestamp {
            color: #858585;
            font-weight: bold;
        }
        .user-id {
            color: #4ec9b0;
            font-weight: bold;
        }
        .error-msg {
            color: #f48771;
        }
        .data-json {
            color: #ce9178;
            margin-left: 20px;
            font-size: 12px;
        }
        .empty-log {
            text-align: center;
            padding: 40px;
            color: #858585;
            font-style: italic;
        }
        .badge {
            background: #0e639c;
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 10px;
        }
        .filter-input {
            padding: 8px 12px;
            background: #3c3c3c;
            border: 1px solid #555;
            color: #d4d4d4;
            border-radius: 4px;
            font-size: 14px;
        }
        pre {
            background: #1e1e1e;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Absensi Debug Log Viewer</h1>
        
        <?php if (isset($_GET['cleared'])): ?>
            <div style="background: #4ec9b0; color: #1e1e1e; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                ✅ Log cleared successfully!
            </div>
        <?php endif; ?>
        
        <div class="controls">
            <a href="view_absen_log.php" class="btn btn-primary">🔄 Refresh</a>
            <a href="view_absen_log.php?auto=5" class="btn <?= $auto_refresh == 5 ? 'btn-success' : 'btn-primary' ?>">
                Auto-refresh (5s) <?= $auto_refresh == 5 ? '✓' : '' ?>
            </a>
            <a href="view_absen_log.php?auto=10" class="btn <?= $auto_refresh == 10 ? 'btn-success' : 'btn-primary' ?>">
                Auto-refresh (10s) <?= $auto_refresh == 10 ? '✓' : '' ?>
            </a>
            <a href="view_absen_log.php?action=clear_log" class="btn btn-danger" onclick="return confirm('Clear all logs?')">
                🗑️ Clear Log
            </a>
            <input type="text" id="filterInput" class="filter-input" placeholder="Filter logs...">
            <span class="badge">Last updated: <?= date('H:i:s') ?></span>
        </div>

        <!-- PHP Error Log (from error_log()) -->
        <div class="log-section">
            <h2>📋 PHP Error Log (Recent 50 lines)</h2>
            <?php if (!empty($php_error_log)): ?>
                <div id="phpErrorLog">
                    <?php foreach ($php_error_log as $line): ?>
                        <?php
                        $class = 'log-entry';
                        if (stripos($line, 'error') !== false || stripos($line, '❌') !== false) {
                            $class .= ' error';
                        } elseif (stripos($line, 'warning') !== false || stripos($line, '⚠️') !== false) {
                            $class .= ' warning';
                        } elseif (stripos($line, 'success') !== false || stripos($line, '✅') !== false) {
                            $class .= ' success';
                        }
                        ?>
                        <div class="<?= $class ?>">
                            <?= htmlspecialchars($line) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-log">No PHP error logs found</div>
            <?php endif; ?>
        </div>

        <!-- Custom Absensi Error Log -->
        <div class="log-section">
            <h2>🚨 Custom Absensi Error Log</h2>
            <?php if (!empty($absensi_errors)): ?>
                <div id="absensiErrorLog">
                    <?php foreach ($absensi_errors as $line): ?>
                        <div class="log-entry error">
                            <?= htmlspecialchars($line) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-log">✅ No errors logged! System is healthy.</div>
            <?php endif; ?>
        </div>

        <!-- Instructions -->
        <div class="log-section">
            <h2>📖 How to Use This Log Viewer</h2>
            <div style="padding: 15px; line-height: 1.8;">
                <p><strong>1. Simulate Error:</strong></p>
                <ul style="margin-left: 20px;">
                    <li>Go to <a href="absen.php" style="color: #4ec9b0;">absen.php</a> as admin</li>
                    <li>Try to do attendance (absen masuk/keluar)</li>
                    <li>Come back here to see the logs</li>
                </ul>
                <br>
                <p><strong>2. Log Types:</strong></p>
                <ul style="margin-left: 20px;">
                    <li>🚀 <span style="color: #4ec9b0;">ABSEN PROCESS START</span> - Process initiated</li>
                    <li>👑 <span style="color: #4ec9b0;">ADMIN MODE ACTIVATED</span> - Admin detected, location validation skipped</li>
                    <li>👤 <span style="color: #569cd6;">USER MODE</span> - Regular user, location validation required</li>
                    <li>❌ <span style="color: #f48771;">ERROR</span> - Something went wrong</li>
                    <li>✅ <span style="color: #4ec9b0;">SUCCESS</span> - Operation completed</li>
                </ul>
                <br>
                <p><strong>3. Filter Logs:</strong></p>
                <p>Use the filter input above to search for specific keywords (e.g., "admin", "error", "user_id")</p>
            </div>
        </div>
    </div>

    <script>
        // Real-time filter
        document.getElementById('filterInput').addEventListener('input', function(e) {
            const filter = e.target.value.toLowerCase();
            const logs = document.querySelectorAll('.log-entry');
            
            logs.forEach(log => {
                const text = log.textContent.toLowerCase();
                if (text.includes(filter)) {
                    log.style.display = 'block';
                } else {
                    log.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
