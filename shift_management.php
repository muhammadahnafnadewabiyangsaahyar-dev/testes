<?php
session_start();
include 'connect.php';

// Check if user is admin or superadmin
if (!isset($_SESSION['user_id']) || !isAdminOrSuperadmin($_SESSION['role'])) {
    header('Location: index.php?error=unauthorized');
    exit();
}

$user_id = $_SESSION['user_id'];
$nama_lengkap = $_SESSION['nama_lengkap'] ?? 'Admin';

// Get all branches
$sql_cabang = "SELECT id, nama_cabang, nama_shift, jam_masuk, jam_keluar FROM cabang ORDER BY nama_cabang";
$stmt_cabang = $pdo->query($sql_cabang);
$branches = $stmt_cabang->fetchAll(PDO::FETCH_ASSOC);

// Get all active employees
$sql_pegawai = "SELECT id, nama_lengkap, posisi, outlet, id_cabang 
                FROM register 
                WHERE role = 'user' 
                ORDER BY nama_lengkap";
$stmt_pegawai = $pdo->query($sql_pegawai);
$employees = $stmt_pegawai->fetchAll(PDO::FETCH_ASSOC);

// Get shift assignments for current month
$current_month = date('Y-m');
$sql_assignments = "SELECT sa.*, r.nama_lengkap, c.nama_cabang, c.nama_shift, c.jam_masuk, c.jam_keluar
                    FROM shift_assignments sa
                    JOIN register r ON sa.user_id = r.id
                    JOIN cabang c ON sa.cabang_id = c.id
                    WHERE DATE_FORMAT(sa.tanggal_shift, '%Y-%m') = ?
                    ORDER BY sa.tanggal_shift DESC, r.nama_lengkap";
$stmt_assignments = $pdo->prepare($sql_assignments);
$stmt_assignments->execute([$current_month]);
$assignments = $stmt_assignments->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Management - Admin</title>
    <link rel="stylesheet" href="style_modern.css">
    <style>
        .container {
            max-width: 1400px;
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
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-success {
            background: #4CAF50;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .form-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-section h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }
        
        .table-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .table-section h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        table th {
            background: #f8f9fa;
            color: #333;
            font-weight: bold;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-pending {
            background: #FFC107;
            color: #333;
        }
        
        .badge-confirmed {
            background: #4CAF50;
            color: white;
        }
        
        .badge-declined {
            background: #f44336;
            color: white;
        }
        
        .badge-approved {
            background: #4CAF50;
            color: white;
        }
        
        .shift-locked {
            background-color: #f5f5f5 !important;
            opacity: 0.8;
        }
        
        .shift-locked button[disabled] {
            cursor: not-allowed !important;
            opacity: 0.6;
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
            <h1>📅 Shift Management</h1>
            <a href="kalender.php" class="btn btn-secondary">← Kembali</a>
        </div>
        
        <div id="alert-message" class="alert hidden"></div>
        
        <!-- Form Assign Shift -->
        <div class="form-section">
            <h2>Assign Shift ke Pegawai</h2>
            <form id="form-assign-shift">
                <div class="form-row">
                    <div class="form-group">
                        <label for="pegawai_id">Pegawai *</label>
                        <select id="pegawai_id" name="pegawai_id" required>
                            <option value="">-- Pilih Pegawai --</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" data-cabang="<?= $emp['id_cabang'] ?>">
                                <?= htmlspecialchars($emp['nama_lengkap']) ?> - <?= htmlspecialchars($emp['posisi']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="cabang_id">Cabang/Shift *</label>
                        <select id="cabang_id" name="cabang_id" required>
                            <option value="">-- Pilih Cabang --</option>
                            <?php foreach ($branches as $branch): ?>
                            <option value="<?= $branch['id'] ?>">
                                <?= htmlspecialchars($branch['nama_cabang']) ?> - 
                                <?= htmlspecialchars($branch['nama_shift']) ?> 
                                (<?= substr($branch['jam_masuk'], 0, 5) ?> - <?= substr($branch['jam_keluar'], 0, 5) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tanggal_shift">Tanggal *</label>
                        <input type="date" id="tanggal_shift" name="tanggal_shift" required min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Assign Shift</button>
                    <button type="button" id="btn-bulk-assign" class="btn btn-success">Bulk Assign (Range Tanggal)</button>
                </div>
            </form>
        </div>
        
        <!-- Table Assignment -->
        <div class="table-section">
            <h2>Shift Assignments - <?= date('F Y') ?></h2>
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Pegawai</th>
                        <th>Cabang</th>
                        <th>Shift</th>
                        <th>Status</th>
                        <th>Konfirmasi</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #999;">
                            Belum ada shift assignment untuk bulan ini
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($assignments as $assign): ?>
                    <tr class="<?= $assign['status_konfirmasi'] === 'approved' ? 'shift-locked' : '' ?>">
                        <td><?= date('d M Y', strtotime($assign['tanggal_shift'])) ?></td>
                        <td><?= htmlspecialchars($assign['nama_lengkap']) ?></td>
                        <td><?= htmlspecialchars($assign['nama_cabang']) ?></td>
                        <td>
                            <?= htmlspecialchars($assign['nama_shift']) ?>
                            <small style="color: #666; display: block; margin-top: 2px;">
                                (<?= substr($assign['jam_masuk'], 0, 5) ?> - <?= substr($assign['jam_keluar'], 0, 5) ?>)
                            </small>
                        </td>
                        <td>
                            <?php
                            $status = $assign['status_konfirmasi'] ?? 'pending';
                            $status_class = 'badge-pending';
                            $status_text = 'Pending';
                            
                            if ($status === 'approved') {
                                $status_class = 'badge-approved';
                                $status_text = '✓ Approved';
                            } elseif ($status === 'declined') {
                                $status_class = 'badge-declined';
                                $status_text = '✗ Declined';
                            } else {
                                $status_text = '⏱ Pending';
                            }
                            ?>
                            <span class="badge <?= $status_class ?>">
                                <?= $status_text ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($assign['waktu_konfirmasi']): ?>
                            <?= date('d/m H:i', strtotime($assign['waktu_konfirmasi'])) ?>
                            <?php else: ?>
                            <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($status === 'approved'): ?>
                            <button class="btn btn-secondary" disabled title="Shift yang sudah approved tidak dapat dihapus">
                                🔒 Locked
                            </button>
                            <?php else: ?>
                            <button class="btn btn-secondary" onclick="deleteAssignment(<?= $assign['id'] ?>)">
                                Hapus
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
        // Form Submit Handler
        document.getElementById('form-assign-shift').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const pegawai_id = formData.get('pegawai_id');
            const cabang_id = formData.get('cabang_id');
            const tanggal_shift = formData.get('tanggal_shift');
            
            try {
                const response = await fetch('api_shift_calendar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'create',
                        user_id: pegawai_id,
                        cabang_id: cabang_id,
                        tanggal_shift: tanggal_shift
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showAlert(result.message, 'success');
                    this.reset();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Terjadi kesalahan', 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            }
        });
        
        // Auto-select cabang based on pegawai
        document.getElementById('pegawai_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const cabangId = selectedOption.getAttribute('data-cabang');
            if (cabangId) {
                document.getElementById('cabang_id').value = cabangId;
            }
        });
        
        // Delete Assignment
        async function deleteAssignment(id) {
            if (!confirm('Yakin ingin menghapus assignment ini?')) return;
            
            try {
                const response = await fetch('api_shift_calendar.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'delete',
                        id: id
                    })
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    showAlert(result.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(result.message || 'Gagal menghapus', 'error');
                }
            } catch (error) {
                showAlert('Error: ' + error.message, 'error');
            }
        }
        
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
    </script>
</body>
</html>
