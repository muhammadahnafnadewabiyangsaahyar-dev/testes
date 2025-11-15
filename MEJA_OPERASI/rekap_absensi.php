<?php
// Enhanced Attendance Report with multiple views
session_start();
include 'connect.php';
include 'calculate_status_kehadiran.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=notloggedin');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$nama_pengguna = $_SESSION['nama_lengkap'] ?? $_SESSION['username'];

// Get filter parameters
$view_type = $_GET['view'] ?? 'daily'; // daily, weekly, monthly, yearly
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$branch_filter = $_GET['branch'] ?? 'all';

// Calculate date ranges
switch ($view_type) {
    case 'weekly':
        $start_date = date('Y-m-d', strtotime('monday this week', strtotime("$year-$month-01")));
        $end_date = date('Y-m-d', strtotime('sunday this week', strtotime($start_date)));
        break;
    case 'monthly':
        $start_date = "$year-$month-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        break;
    case 'yearly':
        $start_date = "$year-01-01";
        $end_date = "$year-12-31";
        break;
    default: // daily
        $start_date = $end_date = date('Y-m-d');
}

// Get attendance data
if ($user_role === 'admin') {
    // Admin can see all users
    $sql = "
        SELECT
            a.*,
            r.nama_lengkap,
            r.username,
            r.outlet,
            r.role,
            c.nama_cabang,
            c.nama_shift
        FROM absensi a
        JOIN register r ON a.user_id = r.id
        LEFT JOIN cabang c ON a.cabang_id = c.id
        WHERE DATE(a.tanggal_absensi) BETWEEN ? AND ?
    ";

    $params = [$start_date, $end_date];

    if ($branch_filter !== 'all') {
        $sql .= " AND r.outlet = ?";
        $params[] = $branch_filter;
    }

    $sql .= " ORDER BY a.tanggal_absensi DESC, r.nama_lengkap ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get branch list for filter
    $stmt_branches = $pdo->query("SELECT DISTINCT outlet FROM register WHERE outlet IS NOT NULL ORDER BY outlet");
    $branches = $stmt_branches->fetchAll(PDO::FETCH_COLUMN);

} else {
    // Regular user sees only their own data
    $sql = "
        SELECT
            a.*,
            r.nama_lengkap,
            r.username,
            r.outlet,
            c.nama_cabang,
            c.nama_shift
        FROM absensi a
        JOIN register r ON a.user_id = r.id
        LEFT JOIN cabang c ON a.cabang_id = c.id
        WHERE a.user_id = ? AND DATE(a.tanggal_absensi) BETWEEN ? AND ?
        ORDER BY a.tanggal_absensi DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $start_date, $end_date]);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $branches = [];
}

// Calculate comprehensive summary statistics with all 7 status types
$summary = [
    'total_records' => count($attendance_data),
    'hadir' => 0,
    'belum_memenuhi_kriteria' => 0,
    'tidak_hadir' => 0,
    'terlambat_tanpa_potongan' => 0,
    'terlambat_dengan_potongan' => 0,
    'izin' => 0,
    'sakit' => 0,
    'lupa_absen_pulang' => 0,
    'belum_absen_keluar' => 0,
    'overwork' => 0,
    'total_potongan_gaji' => 0,
    'total_potongan_tunjangan' => 0,
    'total_menit_terlambat' => 0,
    'rata_rata_terlambat' => 0
];

// Process each record with enhanced status calculation
foreach ($attendance_data as &$record) {
    // Calculate status for each record using the new 7-type logic
    $record['status_calculated'] = hitungStatusKehadiran($record, $pdo);

    // Count statistics for all 7 status types
    switch ($record['status_calculated']) {
        case 'Hadir':
            $summary['hadir']++;
            break;
        case 'Belum Memenuhi Kriteria':
            $summary['belum_memenuhi_kriteria']++;
            break;
        case 'Tidak Hadir':
            $summary['tidak_hadir']++;
            break;
        case 'Terlambat Tanpa Potongan':
            $summary['terlambat_tanpa_potongan']++;
            break;
        case 'Terlambat Dengan Potongan':
            $summary['terlambat_dengan_potongan']++;
            break;
        case 'Izin':
            $summary['izin']++;
            break;
        case 'Sakit':
            $summary['sakit']++;
            break;
        case 'Lupa Absen Pulang':
            $summary['lupa_absen_pulang']++;
            break;
        case 'Belum Absen Keluar':
            $summary['belum_absen_keluar']++;
            break;
    }

    // Count overwork
    if ($record['status_lembur'] === 'Approved') {
        $summary['overwork']++;
    }

    // Calculate total minutes late
    if (!empty($record['menit_terlambat']) && $record['menit_terlambat'] > 0) {
        $summary['total_menit_terlambat'] += $record['menit_terlambat'];
    }

    // Calculate deductions based on status
    $potongan_gaji = calculatePotonganGaji($record['status_calculated'], $record, $pdo);
    $potongan_tunjangan = calculatePotonganTunjangan($record['status_calculated'], $record, $pdo);

    $summary['total_potongan_gaji'] += $potongan_gaji;
    $record['potongan_gaji'] = $potongan_gaji;
    $record['potongan_tunjangan'] = $potongan_tunjangan;
}

// Calculate comprehensive percentages and KPIs
$total_days = $summary['total_records'];
$summary['persentase_hadir'] = $total_days > 0 ? round(($summary['hadir'] / $total_days) * 100, 1) : 0;
$summary['persentase_belum_memenuhi'] = $total_days > 0 ? round(($summary['belum_memenuhi_kriteria'] / $total_days) * 100, 1) : 0;
$summary['persentase_tidak_hadir'] = $total_days > 0 ? round(($summary['tidak_hadir'] / $total_days) * 100, 1) : 0;
$summary['persentase_terlambat'] = $total_days > 0 ? round((($summary['terlambat_tanpa_potongan'] + $summary['terlambat_dengan_potongan']) / $total_days) * 100, 1) : 0;
$summary['persentase_izin_sakit'] = $total_days > 0 ? round((($summary['izin'] + $summary['sakit']) / $total_days) * 100, 1) : 0;

// Calculate attendance rate (Hadir + Izin + Sakit)
$present_days = $summary['hadir'] + $summary['izin'] + $summary['sakit'];
$summary['persentase_kehadiran'] = $total_days > 0 ? round(($present_days / $total_days) * 100, 1) : 0;

// Calculate average lateness
$late_records = $summary['terlambat_tanpa_potongan'] + $summary['terlambat_dengan_potongan'];
$summary['rata_rata_terlambat'] = $late_records > 0 ? round($summary['total_menit_terlambat'] / $late_records, 1) : 0;

// Calculate disciplinary statistics
$summary['total_hari_kerja'] = $total_days;
$summary['total_hari_efektif'] = $summary['hadir'] + $summary['belum_memenuhi_kriteria'];
$summary['total_pelanggaran'] = $summary['tidak_hadir'] + $summary['terlambat_dengan_potongan'];

// Calculate performance score (0-100)
$base_score = 100;
$alpha_penalty = $summary['tidak_hadir'] * 10; // -10 per alpha
$late_penalty = $summary['terlambat_dengan_potongan'] * 2; // -2 per late with penalty
$summary['skor_kinerja'] = max(0, $base_score - $alpha_penalty - $late_penalty);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Absensi - <?php echo ucfirst($view_type); ?></title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .summary-card.hadir { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .summary-card.terlambat { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .summary-card.tidak-hadir { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .summary-card.izin { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }

        .summary-value {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }

        .summary-label {
            font-size: 0.9em;
            opacity: 0.9;
        }

        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
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

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .attendance-table th,
        .attendance-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .attendance-table th {
            background: #667eea;
            color: white;
            font-weight: bold;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
            text-align: center;
            display: inline-block;
            min-width: 100px;
        }

        .status-hadir { background: #d4edda; color: #155724; }
        .status-belum-memenuhi-kriteria { background: #fff3cd; color: #856404; }
        .status-tidak-hadir { background: #f8d7da; color: #721c24; }
        .status-terlambat-tanpa-potongan { background: #d1ecf1; color: #0c5460; }
        .status-terlambat-dengan-potongan { background: #f8d7da; color: #721c24; }
        .status-izin { background: #d1ecf1; color: #0c5460; }
        .status-sakit { background: #e2e3e5; color: #383d41; }
        .status-lupa-absen-pulang { background: #fff3cd; color: #856404; }
        .status-belum-absen-keluar { background: #d1ecf1; color: #0c5460; }

        .export-buttons {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-export {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .btn-csv { background: #28a745; color: white; }
        .btn-excel { background: #007bff; color: white; }
        .btn-pdf { background: #dc3545; color: white; }

        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters {
                flex-direction: column;
                align-items: stretch;
            }

            .attendance-table {
                font-size: 12px;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 8px 4px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-title">Teman KAORI</div>
    <div class="subtitle-container">
        <p class="subtitle">Selamat Datang, <?php echo htmlspecialchars($nama_pengguna); ?> [<?php echo htmlspecialchars($user_role); ?>]</p>
    </div>

    <div class="content-container">
        <h2>Rekap Absensi - <?php echo ucfirst($view_type); ?>
            <?php if ($view_type === 'monthly'): ?>
                (<?php echo date('F Y', strtotime($start_date)); ?>)
            <?php elseif ($view_type === 'weekly'): ?>
                (<?php echo date('d M', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?>)
            <?php elseif ($view_type === 'yearly'): ?>
                (<?php echo $year; ?>)
            <?php else: ?>
                (<?php echo date('d M Y', strtotime($start_date)); ?>)
            <?php endif; ?>
        </h2>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center;">
                <label>View:</label>
                <select name="view" onchange="this.form.submit()">
                    <option value="daily" <?php echo $view_type === 'daily' ? 'selected' : ''; ?>>Harian</option>
                    <option value="weekly" <?php echo $view_type === 'weekly' ? 'selected' : ''; ?>>Mingguan</option>
                    <option value="monthly" <?php echo $view_type === 'monthly' ? 'selected' : ''; ?>>Bulanan</option>
                    <option value="yearly" <?php echo $view_type === 'yearly' ? 'selected' : ''; ?>>Tahunan</option>
                </select>

                <?php if ($view_type === 'monthly' || $view_type === 'yearly'): ?>
                    <label>Bulan:</label>
                    <select name="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $month === sprintf('%02d', $m) ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                <?php endif; ?>

                <label>Tahun:</label>
                <select name="year">
                    <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>

                <?php if ($user_role === 'admin' && !empty($branches)): ?>
                    <label>Cabang:</label>
                    <select name="branch">
                        <option value="all">Semua Cabang</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $branch_filter === $branch ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <button type="submit" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer;">
                    <i class="fa fa-search"></i> Filter
                </button>
            </form>
        </div>

        <!-- Enhanced Summary Cards with all 7 status types -->
        <div class="summary-cards">
            <div class="summary-card hadir">
                <div class="summary-label">Hadir</div>
                <div class="summary-value"><?php echo $summary['hadir']; ?></div>
                <div class="summary-label"><?php echo $summary['persentase_hadir']; ?>%</div>
            </div>

            <div class="summary-card" style="background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%);">
                <div class="summary-label">Belum Memenuhi Kriteria</div>
                <div class="summary-value"><?php echo $summary['belum_memenuhi_kriteria']; ?></div>
                <div class="summary-label"><?php echo $summary['persentase_belum_memenuhi']; ?>%</div>
            </div>

            <div class="summary-card tidak-hadir">
                <div class="summary-label">Tidak Hadir</div>
                <div class="summary-value"><?php echo $summary['tidak_hadir']; ?></div>
                <div class="summary-label"><?php echo $summary['persentase_tidak_hadir']; ?>%</div>
            </div>

            <div class="summary-card" style="background: linear-gradient(135deg, #fff176 0%, #fdd835 100%); color: #333;">
                <div class="summary-label">Terlambat Tanpa Potongan</div>
                <div class="summary-value"><?php echo $summary['terlambat_tanpa_potongan']; ?></div>
                <div class="summary-label">1-20 menit</div>
            </div>

            <div class="summary-card terlambat">
                <div class="summary-label">Terlambat Dengan Potongan</div>
                <div class="summary-value"><?php echo $summary['terlambat_dengan_potongan']; ?></div>
                <div class="summary-label">20+ menit</div>
            </div>

            <div class="summary-card izin">
                <div class="summary-label">Izin</div>
                <div class="summary-value"><?php echo $summary['izin']; ?></div>
                <div class="summary-label">Dengan Surat</div>
            </div>

            <div class="summary-card" style="background: linear-gradient(135deg, #ba68c8 0%, #9c27b0 100%);">
                <div class="summary-label">Sakit</div>
                <div class="summary-value"><?php echo $summary['sakit']; ?></div>
                <div class="summary-label">Dengan Surat</div>
            </div>

            <div class="summary-card" style="background: linear-gradient(135deg, #4db6ac 0%, #009688 100%);">
                <div class="summary-label">Tingkat Kehadiran</div>
                <div class="summary-value"><?php echo $summary['persentase_kehadiran']; ?>%</div>
                <div class="summary-label">Hadir + Izin + Sakit</div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="performance-metrics" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #2196f3;">
                <h4 style="margin: 0 0 10px 0; color: #1976d2;">📊 Statistik Keterlambatan</h4>
                <p style="margin: 5px 0;"><strong>Rata-rata:</strong> <?php echo $summary['rata_rata_terlambat']; ?> menit</p>
                <p style="margin: 5px 0;"><strong>Total:</strong> <?php echo $summary['total_menit_terlambat']; ?> menit</p>
                <p style="margin: 5px 0;"><strong>Overwork:</strong> <?php echo $summary['overwork']; ?> hari</p>
            </div>

            <div style="background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #ff9800;">
                <h4 style="margin: 0 0 10px 0; color: #e65100;">💰 Total Potongan</h4>
                <p style="margin: 5px 0;"><strong>Gaji Pokok:</strong> Rp <?php echo number_format($summary['total_potongan_gaji'], 0, ',', '.'); ?></p>
                <p style="margin: 5px 0;"><strong>Hari Kerja:</strong> <?php echo $summary['total_hari_kerja']; ?> hari</p>
                <p style="margin: 5px 0;"><strong>Pelanggaran:</strong> <?php echo $summary['total_pelanggaran']; ?> kali</p>
            </div>

            <div style="background: linear-gradient(135deg, <?php echo $summary['skor_kinerja'] >= 80 ? '#e8f5e9 0%, #c8e6c9 100%' : ($summary['skor_kinerja'] >= 60 ? '#fff3e0 0%, #ffe0b2 100%' : '#ffebee 0%, #ffcdd2 100%'); ?>); padding: 20px; border-radius: 10px; border-left: 4px solid <?php echo $summary['skor_kinerja'] >= 80 ? '#4caf50' : ($summary['skor_kinerja'] >= 60 ? '#ff9800' : '#f44336'); ?>;">
                <h4 style="margin: 0 0 10px 0; color: <?php echo $summary['skor_kinerja'] >= 80 ? '#2e7d32' : ($summary['skor_kinerja'] >= 60 ? '#e65100' : '#c62828'); ?>;">🎯 Skor Kinerja</h4>
                <div style="font-size: 2em; font-weight: bold; margin: 10px 0;"><?php echo $summary['skor_kinerja']; ?>/100</div>
                <p style="margin: 5px 0;"><strong>Status:</strong>
                    <?php if ($summary['skor_kinerja'] >= 80): ?>Sangat Baik<?php elseif ($summary['skor_kinerja'] >= 60): ?>Baik<?php else: ?>Perlu Perbaikan<?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Export Buttons -->
        <?php if ($user_role === 'admin'): ?>
        <div class="export-buttons">
            <a href="export_absensi.php?format=csv&view=<?php echo $view_type; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>&branch=<?php echo $branch_filter; ?>"
               class="btn-export btn-csv" target="_blank">
                <i class="fa fa-file-csv"></i> Export CSV
            </a>
            <a href="export_absensi.php?format=excel&view=<?php echo $view_type; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>&branch=<?php echo $branch_filter; ?>"
               class="btn-export btn-excel" target="_blank">
                <i class="fa fa-file-excel"></i> Export Excel
            </a>
            <a href="export_absensi.php?format=pdf&view=<?php echo $view_type; ?>&month=<?php echo $month; ?>&year=<?php echo $year; ?>&branch=<?php echo $branch_filter; ?>"
               class="btn-export btn-pdf" target="_blank">
                <i class="fa fa-file-pdf"></i> Export PDF
            </a>
        </div>
        <?php endif; ?>

        <!-- Enhanced Attendance Table with detailed status -->
        <table class="attendance-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <?php if ($user_role === 'admin'): ?>
                        <th>Nama</th>
                        <th>Cabang</th>
                        <th>Shift</th>
                    <?php endif; ?>
                    <th>Waktu Masuk</th>
                    <th>Waktu Keluar</th>
                    <th>Status Kehadiran</th>
                    <th>Keterlambatan</th>
                    <th>Overwork</th>
                    <th>Potongan</th>
                    <th>Foto</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($attendance_data)): ?>
                    <tr>
                        <td colspan="<?php echo $user_role === 'admin' ? '11' : '8'; ?>" style="text-align: center; padding: 40px;">
                            <i class="fa fa-info-circle" style="font-size: 2em; color: #ccc; margin-bottom: 10px;"></i>
                            <br>Tidak ada data absensi untuk periode ini.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($attendance_data as $record): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($record['tanggal_absensi'])); ?></td>
                            <?php if ($user_role === 'admin'): ?>
                                <td><?php echo htmlspecialchars($record['nama_lengkap']); ?></td>
                                <td><?php echo htmlspecialchars($record['outlet'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($record['nama_shift'] ?? '-'); ?></td>
                            <?php endif; ?>
                            <td><?php echo $record['waktu_masuk'] ? date('H:i', strtotime($record['waktu_masuk'])) : '-'; ?></td>
                            <td><?php echo $record['waktu_keluar'] ? date('H:i', strtotime($record['waktu_keluar'])) : '-'; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $record['status_calculated'])); ?>">
                                    <?php echo htmlspecialchars($record['status_calculated']); ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if (!empty($record['menit_terlambat']) && $record['menit_terlambat'] > 0) {
                                    echo $record['menit_terlambat'] . ' menit';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                if ($record['status_lembur'] === 'Approved') {
                                    echo '<span style="color: #28a745; font-weight: bold;">✓ Approved</span>';
                                } elseif ($record['status_lembur'] === 'Pending') {
                                    echo '<span style="color: #ffc107; font-weight: bold;">⏳ Pending</span>';
                                } elseif ($record['status_lembur'] === 'Rejected') {
                                    echo '<span style="color: #dc3545; font-weight: bold;">✗ Rejected</span>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if (!empty($record['potongan_gaji']) && $record['potongan_gaji'] > 0): ?>
                                    <span style="color: #dc3545; font-weight: bold;">
                                        Rp <?php echo number_format($record['potongan_gaji'], 0, ',', '.'); ?>
                                    </span>
                                <?php elseif (!empty($record['potongan_tunjangan'])): ?>
                                    <span style="color: #ff9800; font-weight: bold;">
                                        <?php echo htmlspecialchars($record['potongan_tunjangan']); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #28a745;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($record['foto_absen_masuk'])): ?>
                                    <a href="uploads/absensi/foto_masuk/<?php echo $record['nama_lengkap']; ?>/<?php echo $record['foto_absen_masuk']; ?>" target="_blank" title="Foto Absen Masuk">
                                        <i class="fa fa-camera"></i> Masuk
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($record['foto_absen_keluar'])): ?>
                                    <?php if (!empty($record['foto_absen_masuk'])) echo ' | '; ?>
                                    <a href="uploads/absensi/foto_keluar/<?php echo $record['nama_lengkap']; ?>/<?php echo $record['foto_absen_keluar']; ?>" target="_blank" title="Foto Absen Keluar">
                                        <i class="fa fa-camera"></i> Keluar
                                    </a>
                                <?php endif; ?>
                                <?php if (empty($record['foto_absen_masuk']) && empty($record['foto_absen_keluar'])): ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Summary Table for Admin -->
        <?php if ($user_role === 'admin' && !empty($attendance_data)): ?>
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px;">
            <h3 style="margin-bottom: 15px; color: #333;">📊 Ringkasan Periode <?php echo ucfirst($view_type); ?></h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #28a745;"><?php echo $summary['total_hari_kerja']; ?></div>
                    <div style="color: #666;">Total Hari</div>
                </div>
                <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #2196f3;"><?php echo $summary['total_hari_efektif']; ?></div>
                    <div style="color: #666;">Hari Efektif</div>
                </div>
                <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #ff9800;"><?php echo $summary['total_pelanggaran']; ?></div>
                    <div style="color: #666;">Pelanggaran</div>
                </div>
                <div style="text-align: center; padding: 15px; background: white; border-radius: 8px;">
                    <div style="font-size: 1.5em; font-weight: bold; color: #9c27b0;"><?php echo $summary['skor_kinerja']; ?>/100</div>
                    <div style="color: #666;">Skor Rata-rata</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>