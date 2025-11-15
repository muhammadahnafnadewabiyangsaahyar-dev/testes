<?php
session_start();
include 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'User';

// Get pending shift assignments
$sql_pending = "SELECT sa.*, c.nama_cabang, c.nama_shift, c.jam_masuk, c.jam_keluar
                FROM shift_assignments sa
                JOIN cabang c ON sa.cabang_id = c.id
                WHERE sa.user_id = ? AND sa.status_konfirmasi = 'pending'
                AND sa.tanggal_shift >= CURDATE()
                ORDER BY sa.tanggal_shift ASC";
$stmt_pending = $pdo->prepare($sql_pending);
$stmt_pending->execute([$user_id]);
$pending_shifts = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);

// Get confirmed/declined shifts
$sql_history = "SELECT sa.*, c.nama_cabang, c.nama_shift, c.jam_masuk, c.jam_keluar
                FROM shift_assignments sa
                JOIN cabang c ON sa.cabang_id = c.id
                WHERE sa.user_id = ? AND sa.status_konfirmasi IN ('confirmed', 'declined')
                ORDER BY sa.tanggal_shift DESC
                LIMIT 20";
$stmt_history = $pdo->prepare($sql_history);
$stmt_history->execute([$user_id]);
$history_shifts = $stmt_history->fetchAll(PDO::FETCH_ASSOC);

// Count pending
$pending_count = count($pending_shifts);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Shift - <?= htmlspecialchars($nama_lengkap) ?></title>
    <link rel="stylesheet" href="style_modern.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #667eea;
        }
        
        .header h1 {
            color: #667eea;
            margin: 0;
        }
        
        .badge-notification {
            background: #f44336;
            color: white;
            padding: 5px 10px;
            border-radius: 50%;
            font-size: 14px;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .section h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .shift-card {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .shift-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }
        
        .shift-card.pending {
            border-left: 5px solid #FFC107;
        }
        
        .shift-card.confirmed {
            border-left: 5px solid #4CAF50;
        }
        
        .shift-card.declined {
            border-left: 5px solid #f44336;
        }
        
        .shift-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .shift-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .shift-info-item label {
            font-size: 12px;
            color: #999;
            margin-bottom: 5px;
        }
        
        .shift-info-item span {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        
        .shift-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-confirm {
            background: #4CAF50;
            color: white;
        }
        
        .btn-confirm:hover {
            background: #45a049;
        }
        
        .btn-decline {
            background: #f44336;
            color: white;
        }
        
        .btn-decline:hover {
            background: #da190b;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            margin-bottom: 20px;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            resize: vertical;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="header">
            <div>
                <h1>📅 Konfirmasi Shift</h1>
                <p style="color: #666; margin: 5px 0 0 0;">
                    Halo, <?= htmlspecialchars($nama_lengkap) ?>!
                </p>
            </div>
            <a href="mainpage.php" class="btn btn-secondary">← Kembali</a>
        </div>
        
        <div id="alert-message" class="alert hidden"></div>
        
        <!-- Pending Shifts -->
        <div class="section">
            <h2>
                Shift Menunggu Konfirmasi
                <?php if ($pending_count > 0): ?>
                <span class="badge-notification"><?= $pending_count ?></span>
                <?php endif; ?>
            </h2>
            
            <?php if (empty($pending_shifts)): ?>
            <div class="empty-state">
                <div style="font-size: 48px;">✅</div>
                <p>Tidak ada shift yang perlu dikonfirmasi</p>
            </div>
            <?php else: ?>
            <?php foreach ($pending_shifts as $shift): ?>
            <div class="shift-card pending">
                <div class="shift-info">
                    <div class="shift-info-item">
                        <label>Tanggal</label>
                        <span><?= date('d F Y', strtotime($shift['tanggal_shift'])) ?></span>
                        <span style="font-size: 12px; color: #666;">
                            (<?= date('l', strtotime($shift['tanggal_shift'])) ?>)
                        </span>
                    </div>
                    <div class="shift-info-item">
                        <label>Lokasi</label>
                        <span><?= htmlspecialchars($shift['nama_cabang']) ?></span>
                    </div>
                    <div class="shift-info-item">
                        <label>Shift</label>
                        <span><?= htmlspecialchars($shift['nama_shift']) ?></span>
                    </div>
                    <div class="shift-info-item">
                        <label>Jam Kerja</label>
                        <span>
                            <?= substr($shift['jam_masuk'], 0, 5) ?> - 
                            <?= substr($shift['jam_keluar'], 0, 5) ?>
                        </span>
                    </div>
                </div>
                <div class="shift-actions">
                    <button class="btn btn-confirm" onclick="confirmShift(<?= $shift['id'] ?>, 'confirmed')">
                        ✓ Konfirmasi
                    </button>
                    <button class="btn btn-decline" onclick="showDeclineModal(<?= $shift['id'] ?>)">
                        ✗ Tolak
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- History -->
        <div class="section">
            <h2>Riwayat Shift</h2>
            
            <?php if (empty($history_shifts)): ?>
            <div class="empty-state">
                <div style="font-size: 48px;">📋</div>
                <p>Belum ada riwayat shift</p>
            </div>
            <?php else: ?>
            <?php foreach ($history_shifts as $shift): ?>
            <div class="shift-card <?= $shift['status_konfirmasi'] ?>">
                <div class="shift-info">
                    <div class="shift-info-item">
                        <label>Tanggal</label>
                        <span><?= date('d F Y', strtotime($shift['tanggal_shift'])) ?></span>
                    </div>
                    <div class="shift-info-item">
                        <label>Lokasi</label>
                        <span><?= htmlspecialchars($shift['nama_cabang']) ?></span>
                    </div>
                    <div class="shift-info-item">
                        <label>Shift</label>
                        <span><?= htmlspecialchars($shift['nama_shift']) ?></span>
                    </div>
                    <div class="shift-info-item">
                        <label>Status</label>
                        <span style="color: <?= $shift['status_konfirmasi'] === 'confirmed' ? '#4CAF50' : '#f44336' ?>;">
                            <?= $shift['status_konfirmasi'] === 'confirmed' ? '✓ Dikonfirmasi' : '✗ Ditolak' ?>
                        </span>
                    </div>
                    <?php if ($shift['status_konfirmasi'] === 'declined' && !empty($shift['decline_reason'])): ?>
                    <div class="shift-info-item">
                        <label>Alasan</label>
                        <span style="font-size: 14px;">
                            <?php
                            $reasons = ['sakit' => '🤒 Sakit', 'izin' => '📝 Izin', 'reschedule' => '🔄 Reschedule'];
                            echo $reasons[$shift['decline_reason']] ?? $shift['decline_reason'];
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="shift-info-item">
                        <label>Waktu Konfirmasi</label>
                        <span><?= date('d/m/Y H:i', strtotime($shift['waktu_konfirmasi'])) ?></span>
                    </div>
                </div>
                <?php if ($shift['catatan_pegawai']): ?>
                <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                    <label style="font-size: 12px; color: #666;">Catatan:</label>
                    <p style="margin: 5px 0 0 0;"><?= htmlspecialchars($shift['catatan_pegawai']) ?></p>
                </div>
                <?php endif; ?>
                <div style="margin-top: 10px; padding: 8px; background: <?= $shift['status_konfirmasi'] === 'confirmed' ? '#e8f5e9' : '#ffebee' ?>; border-radius: 5px; font-size: 12px; color: #666;">
                    🔒 Shift ini sudah dikonfirmasi dan tidak dapat diubah
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Decline -->
    <div id="modal-decline" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>❌ Tolak Shift</h3>
            </div>
            <form id="form-decline">
                <input type="hidden" id="decline-shift-id" name="shift_id">
                
                <div class="form-group">
                    <label for="decline_reason" style="font-weight: bold; margin-bottom: 10px; display: block;">
                        Pilih Alasan Penolakan <span style="color: red;">*</span>
                    </label>
                    <select id="decline_reason" name="decline_reason" required style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 14px; margin-bottom: 15px;">
                        <option value="">-- Pilih Alasan --</option>
                        <option value="sakit">🤒 Sakit</option>
                        <option value="izin">📝 Izin</option>
                        <option value="reschedule">🔄 Meminta Reschedule</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="catatan">Catatan Tambahan (opsional)</label>
                    <textarea id="catatan" name="catatan" rows="4" placeholder="Berikan detail tambahan jika diperlukan..." style="width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 14px;"></textarea>
                </div>
                
                <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 15px 0; border-radius: 4px; font-size: 13px;">
                    <strong>⚠️ Perhatian:</strong> Email notifikasi akan otomatis dikirim ke HR dan Kepala Toko setelah Anda menyimpan penolakan ini.
                </div>
                
                <div class="shift-actions">
                    <button type="submit" class="btn btn-decline">
                        💾 Simpan & Kirim Notifikasi
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Confirm Shift
        async function confirmShift(shiftId, status) {
            if (!confirm('✅ Konfirmasi shift ini?\n\nEmail notifikasi akan dikirim ke HR dan Kepala Toko.')) return;
            
            const formData = new FormData();
            formData.append('shift_id', shiftId);
            formData.append('status', status);
            
            try {
                const response = await fetch('api_shift_confirm.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                // Get response text first for debugging
                const responseText = await response.text();
                console.log('API Response:', responseText);

                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }

                if (result.status === 'success') {
                    showAlert(result.message, 'success');
                    // Send notification to HR
                    await sendNotificationToHR(shiftId, 'confirmed');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(result.message || 'Terjadi kesalahan', 'error');
                }
            } catch (error) {
                console.error('Confirm Shift Error:', error);
                showAlert('Error: ' + error.message, 'error');
            }
        }
        
        // Show Decline Modal
        function showDeclineModal(shiftId) {
            document.getElementById('decline-shift-id').value = shiftId;
            document.getElementById('modal-decline').style.display = 'block';
        }
        
        // Close Modal
        function closeModal() {
            document.getElementById('modal-decline').style.display = 'none';
            document.getElementById('form-decline').reset();
        }
        
        // Form Decline Submit
        document.getElementById('form-decline').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validate decline reason
            const declineReason = document.getElementById('decline_reason').value;
            if (!declineReason) {
                showAlert('⚠️ Silakan pilih alasan penolakan', 'error');
                return;
            }
            
            const formData = new FormData(this);
            formData.append('status', 'declined');
            
            try {
                const response = await fetch('api_shift_confirmation_email.php', {
                    method: 'POST',
                    body: formData
                });
                
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                // Get response text first for debugging
                const responseText = await response.text();
                console.log('API Response:', responseText);
                
                // Try to parse JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Response Text:', responseText);
                    throw new Error('Invalid JSON response from server');
                }
                
                if (result.status === 'success') {
                    closeModal();
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(result.message || 'Terjadi kesalahan', 'error');
                }
            } catch (error) {
                console.error('Decline Shift Error:', error);
                showAlert('Error: ' + error.message, 'error');
            }
        });
        
        // Show Alert
        function showAlert(message, type) {
            const alert = document.getElementById('alert-message');
            alert.className = 'alert alert-' + type;
            alert.textContent = message;
            alert.classList.remove('hidden');

            setTimeout(() => {
                alert.classList.add('hidden');
            }, 5000);
        }

        // Send notification to HR (placeholder for now)
        async function sendNotificationToHR(shiftId, status) {
            // This will be implemented when we have the notification system ready
            console.log(`Notification sent to HR for shift ${shiftId} with status ${status}`);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('modal-decline');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
```
