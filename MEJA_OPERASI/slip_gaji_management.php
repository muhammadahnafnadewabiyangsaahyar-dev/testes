<?php
// Enhanced Slip Gaji Management for Admin
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['nama_lengkap'] ?? $_SESSION['username'];

// Get filter parameters
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');
$status_filter = $_GET['status'] ?? 'all'; // all, editable, finalized

// Get pending slips count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM riwayat_gaji WHERE is_editable = 1");
$stmt->execute();
$pending_count = $stmt->fetchColumn();

// Get slips based on filters
$query = "
    SELECT rg.*, r.nama_lengkap, r.posisi, r.outlet
    FROM riwayat_gaji rg
    JOIN register r ON rg.register_id = r.id
    WHERE rg.periode_bulan = ? AND rg.periode_tahun = ?
";

$params = [$month, $year];

if ($status_filter === 'editable') {
    $query .= " AND rg.is_editable = 1";
} elseif ($status_filter === 'finalized') {
    $query .= " AND rg.is_editable = 0";
}

$query .= " ORDER BY r.nama_lengkap ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$slips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totals = [
    'total_slips' => count($slips),
    'total_pendapatan' => 0,
    'total_potongan' => 0,
    'total_gaji_bersih' => 0,
    'total_piutang' => 0,
    'total_kasbon' => 0
];

foreach ($slips as $slip) {
    $totals['total_pendapatan'] += $slip['total_pendapatan'];
    $totals['total_potongan'] += $slip['total_potongan'];
    $totals['total_gaji_bersih'] += $slip['gaji_bersih'];
    $totals['total_piutang'] += $slip['piutang_toko'];
    $totals['total_kasbon'] += $slip['kasbon'];
}

function formatRupiah($angka) {
    if ($angka === null || $angka === '') return '-';
    if ($angka == 0) return 'Rp 0';
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function getNamaBulan($bulan) {
    $namaBulan = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni',
                  7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
    return $namaBulan[(int)$bulan] ?? 'Bulan?';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Slip Gaji - Admin</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .management-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filters select, .filters input {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .summary-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .summary-card.info { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        .summary-value {
            font-size: 1.5em;
            font-weight: bold;
            margin: 10px 0;
        }

        .slip-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .slip-table th,
        .slip-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .slip-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }

        .status-editable {
            background: #fff3cd;
            color: #856404;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .status-finalized {
            background: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            margin-right: 5px;
        }

        .btn-edit { background: #28a745; color: white; }
        .btn-finalize { background: #dc3545; color: white; }
        .btn-download { background: #007bff; color: white; }

        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .editable-cell {
            background: #fffacd;
            cursor: pointer;
        }

        .editable-cell:hover {
            background: #ffe4b5;
        }

        @media (max-width: 768px) {
            .management-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .slip-table {
                font-size: 12px;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-title">Teman KAORI</div>
    <div class="subtitle-container">
        <p class="subtitle">Selamat Datang, <?php echo htmlspecialchars($user_name); ?> [Admin]</p>
    </div>

    <div class="content-container">
        <div class="management-header">
            <div>
                <h2><i class="fas fa-receipt"></i> Manajemen Slip Gaji</h2>
                <p>Kelola komponen manual dan finalisasi slip gaji karyawan</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 1.5em; font-weight: bold;"><?php echo $pending_count; ?></div>
                <div>Slip Pending</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <label>Bulan:</label>
                <select name="month">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $month === sprintf('%02d', $m) ? 'selected' : ''; ?>>
                            <?php echo getNamaBulan($m); ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <label>Tahun:</label>
                <select name="year">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>

                <label>Status:</label>
                <select name="status">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                    <option value="editable" <?php echo $status_filter === 'editable' ? 'selected' : ''; ?>>Pending Edit</option>
                    <option value="finalized" <?php echo $status_filter === 'finalized' ? 'selected' : ''; ?>>Final</option>
                </select>

                <button type="submit" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    <i class="fa fa-search"></i> Filter
                </button>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-label">Total Slip</div>
                <div class="summary-value"><?php echo $totals['total_slips']; ?></div>
            </div>

            <div class="summary-card warning">
                <div class="summary-label">Total Pendapatan</div>
                <div class="summary-value"><?php echo formatRupiah($totals['total_pendapatan']); ?></div>
            </div>

            <div class="summary-card warning">
                <div class="summary-label">Total Potongan</div>
                <div class="summary-value"><?php echo formatRupiah($totals['total_potongan']); ?></div>
            </div>

            <div class="summary-card info">
                <div class="summary-label">Total Gaji Bersih</div>
                <div class="summary-value"><?php echo formatRupiah($totals['total_gaji_bersih']); ?></div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <button onclick="bulkEdit()" class="btn-action" style="background: #28a745; color: white; padding: 10px 20px;">
                <i class="fa fa-edit"></i> Edit Massal
            </button>
            <button onclick="bulkFinalize()" class="btn-action" style="background: #dc3545; color: white; padding: 10px 20px;">
                <i class="fa fa-check"></i> Finalisasi Massal
            </button>
            <button onclick="exportToCSV()" class="btn-action" style="background: #007bff; color: white; padding: 10px 20px;">
                <i class="fa fa-download"></i> Export CSV
            </button>
            <button onclick="sendBulkEmail()" class="btn-action" style="background: #ff9800; color: white; padding: 10px 20px;">
                <i class="fa fa-envelope"></i> Kirim Email Massal
            </button>
        </div>

        <!-- Slip Table -->
        <table class="slip-table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Nama Karyawan</th>
                    <th>Posisi</th>
                    <th>Cabang</th>
                    <th>Gaji Pokok</th>
                    <th>Overwork</th>
                    <th>Piutang Toko</th>
                    <th>Kasbon</th>
                    <th>Bonus Marketing</th>
                    <th>Insentif Omset</th>
                    <th>Gaji Bersih</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($slips)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 40px;">
                            <i class="fa fa-info-circle" style="font-size: 3em; color: #ccc;"></i>
                            <br><br>Tidak ada data slip gaji untuk periode ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($slips as $slip): ?>
                        <tr data-slip-id="<?php echo $slip['id']; ?>">
                            <td><input type="checkbox" class="slip-checkbox" value="<?php echo $slip['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($slip['nama_lengkap']); ?></td>
                            <td><?php echo htmlspecialchars($slip['posisi']); ?></td>
                            <td><?php echo htmlspecialchars($slip['outlet'] ?? 'N/A'); ?></td>
                            <td><?php echo formatRupiah($slip['gaji_pokok_aktual']); ?></td>
                            <td><?php echo formatRupiah($slip['overwork']); ?></td>
                            <td class="editable-cell" data-field="piutang_toko"><?php echo formatRupiah($slip['piutang_toko']); ?></td>
                            <td class="editable-cell" data-field="kasbon"><?php echo formatRupiah($slip['kasbon']); ?></td>
                            <td class="editable-cell" data-field="bonus_marketing"><?php echo formatRupiah($slip['bonus_marketing']); ?></td>
                            <td class="editable-cell" data-field="insentif_omset"><?php echo formatRupiah($slip['insentif_omset']); ?></td>
                            <td style="font-weight: bold; color: #4CAF50;"><?php echo formatRupiah($slip['gaji_bersih']); ?></td>
                            <td>
                                <?php if ($slip['is_editable']): ?>
                                    <span class="status-editable">Pending Edit</span>
                                <?php else: ?>
                                    <span class="status-finalized">Final</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($slip['is_editable']): ?>
                                    <button onclick="editSlip(<?php echo $slip['id']; ?>)" class="btn-action btn-edit">
                                        <i class="fa fa-edit"></i> Edit
                                    </button>
                                    <button onclick="finalizeSlip(<?php echo $slip['id']; ?>)" class="btn-action btn-finalize">
                                        <i class="fa fa-check"></i> Final
                                    </button>
                                <?php endif; ?>
                                <a href="generate_slip.php?id=<?php echo $slip['id']; ?>" target="_blank" class="btn-action btn-download">
                                    <i class="fa fa-download"></i> Download
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="modal-content">
            <h3>Edit Komponen Manual Slip Gaji</h3>
            <form id="edit-form">
                <input type="hidden" id="edit-slip-id" name="slip_id">

                <div class="form-group">
                    <label>Nama Karyawan:</label>
                    <input type="text" id="edit-nama" readonly>
                </div>

                <div class="form-group">
                    <label>Piutang Toko:</label>
                    <input type="number" id="edit-piutang" name="piutang_toko" step="0.01">
                </div>

                <div class="form-group">
                    <label>Kasbon:</label>
                    <input type="number" id="edit-kasbon" name="kasbon" step="0.01">
                </div>

                <div class="form-group">
                    <label>Bonus Marketing:</label>
                    <input type="number" id="edit-bonus-marketing" name="bonus_marketing" step="0.01">
                </div>

                <div class="form-group">
                    <label>Insentif Omset:</label>
                    <input type="number" id="edit-insentif-omset" name="insentif_omset" step="0.01">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                        <i class="fa fa-save"></i> Simpan
                    </button>
                    <button type="button" onclick="closeModal()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                        <i class="fa fa-times"></i> Batal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.slip-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Inline editing
        document.querySelectorAll('.editable-cell').forEach(cell => {
            cell.addEventListener('click', function() {
                if (this.closest('tr').querySelector('.status-editable')) {
                    const currentValue = this.textContent.replace('Rp ', '').replace(/\./g, '').replace(/,/g, '');
                    const field = this.dataset.field;
                    const slipId = this.closest('tr').dataset.slipId;

                    const input = document.createElement('input');
                    input.type = 'number';
                    input.step = '0.01';
                    input.value = currentValue;
                    input.style.width = '100%';

                    this.innerHTML = '';
                    this.appendChild(input);
                    input.focus();

                    input.addEventListener('blur', () => saveInlineEdit(slipId, field, input.value, this));
                    input.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            saveInlineEdit(slipId, field, input.value, this);
                        }
                    });
                }
            });
        });

        async function saveInlineEdit(slipId, field, value, cell) {
            try {
                const formData = new FormData();
                formData.append('action', 'update_manual_components');
                formData.append('slip_id', slipId);
                formData.append(field, value);

                const response = await fetch('api_slip_gaji.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Update the cell with formatted value
                    cell.textContent = formatRupiah(value);
                    // Update gaji bersih in the same row
                    const row = cell.closest('tr');
                    const gajiBersihCell = row.querySelector('td:nth-child(11)');
                    if (gajiBersihCell) {
                        gajiBersihCell.textContent = formatRupiah(result.data.gaji_bersih);
                    }
                } else {
                    alert('Error: ' + result.message);
                    location.reload();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menyimpan data');
                location.reload();
            }
        }

        function formatRupiah(amount) {
            if (amount == 0) return 'Rp 0';
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(amount);
        }

        function editSlip(slipId) {
            // Get slip details and show modal
            fetch(`api_slip_gaji.php?action=get_slip_details&slip_id=${slipId}`)
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        const data = result.data;
                        document.getElementById('edit-slip-id').value = slipId;
                        document.getElementById('edit-nama').value = data.nama_karyawan;
                        document.getElementById('edit-piutang').value = data.komponen_tidak_pasti.piutang_toko;
                        document.getElementById('edit-kasbon').value = data.komponen_tidak_pasti.kasbon;
                        document.getElementById('edit-bonus-marketing').value = data.komponen_tidak_pasti.bonus_marketing;
                        document.getElementById('edit-insentif-omset').value = data.komponen_tidak_pasti.insentif_omset;

                        document.getElementById('edit-modal').style.display = 'block';
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengambil data');
                });
        }

        function closeModal() {
            document.getElementById('edit-modal').style.display = 'none';
        }

        // Edit form submission
        document.getElementById('edit-form').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'update_manual_components');

            try {
                const response = await fetch('api_slip_gaji.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert('Data berhasil diperbarui');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menyimpan data');
            }
        });

        function finalizeSlip(slipId) {
            if (confirm('Apakah Anda yakin ingin memfinalisasi slip gaji ini? Setelah difinalisasi, slip tidak dapat diedit lagi.')) {
                const formData = new FormData();
                formData.append('action', 'finalize_slip');
                formData.append('slip_id', slipId);

                fetch('api_slip_gaji.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.status === 'success') {
                        alert('Slip gaji berhasil difinalisasi');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memfinalisasi slip');
                });
            }
        }

        function bulkEdit() {
            const selectedSlips = Array.from(document.querySelectorAll('.slip-checkbox:checked')).map(cb => cb.value);

            if (selectedSlips.length === 0) {
                alert('Pilih minimal 1 slip gaji untuk edit massal');
                return;
            }

            // For now, just show alert. In production, implement bulk edit modal
            alert('Fitur edit massal sedang dalam pengembangan. Gunakan edit individual untuk saat ini.');
        }

        function bulkFinalize() {
            const selectedSlips = Array.from(document.querySelectorAll('.slip-checkbox:checked')).map(cb => cb.value);

            if (selectedSlips.length === 0) {
                alert('Pilih minimal 1 slip gaji untuk finalisasi massal');
                return;
            }

            if (confirm(`Apakah Anda yakin ingin memfinalisasi ${selectedSlips.length} slip gaji?`)) {
                // Implement bulk finalize
                alert('Fitur finalisasi massal sedang dalam pengembangan.');
            }
        }

        function exportToCSV() {
            const month = '<?php echo $month; ?>';
            const year = '<?php echo $year; ?>';
            const status = '<?php echo $status_filter; ?>';

            window.open(`export_slip_gaji.php?format=csv&month=${month}&year=${year}&status=${status}`, '_blank');
        }

        function sendBulkEmail() {
            const selectedSlips = Array.from(document.querySelectorAll('.slip-checkbox:checked')).map(cb => cb.value);

            if (selectedSlips.length === 0) {
                alert('Pilih minimal 1 slip gaji untuk kirim email');
                return;
            }

            if (confirm(`Kirim email slip gaji ke ${selectedSlips.length} karyawan?`)) {
                // Implement bulk email sending
                alert('Fitur kirim email massal sedang dalam pengembangan.');
            }
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('edit-modal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
