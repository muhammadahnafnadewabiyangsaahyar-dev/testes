<?php
// Export Attendance Reports - CSV and Excel formats
// Supports daily, weekly, monthly, yearly views
// Includes all 7 status types and comprehensive analytics

session_start();
include 'connect.php';
include 'calculate_status_kehadiran.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=notloggedin');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get export parameters
$format = $_GET['format'] ?? 'csv'; // csv, excel
$view_type = $_GET['view'] ?? 'monthly';
$month = $_GET['month'] ?? date('Y-m');
$year = $_GET['year'] ?? date('Y');
$branch_filter = $_GET['branch'] ?? 'all';

// Calculate date ranges (same as rekap_absensi.php)
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
    $sql = "
        SELECT
            a.*,
            r.nama_lengkap,
            r.username,
            r.outlet,
            r.role,
            c.nama_cabang,
            c.nama_shift,
            c.jam_masuk,
            c.jam_keluar
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

} else {
    // Regular user sees only their own data
    $sql = "
        SELECT
            a.*,
            r.nama_lengkap,
            r.username,
            r.outlet,
            c.nama_cabang,
            c.nama_shift,
            c.jam_masuk,
            c.jam_keluar
        FROM absensi a
        JOIN register r ON a.user_id = r.id
        LEFT JOIN cabang c ON a.cabang_id = c.id
        WHERE a.user_id = ? AND DATE(a.tanggal_absensi) BETWEEN ? AND ?
        ORDER BY a.tanggal_absensi DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $start_date, $end_date]);
    $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Process data with status calculation
$processed_data = [];
$summary_stats = [
    'hadir' => 0, 'belum_memenuhi_kriteria' => 0, 'tidak_hadir' => 0,
    'terlambat_tanpa_potongan' => 0, 'terlambat_dengan_potongan' => 0,
    'izin' => 0, 'sakit' => 0, 'lupa_absen_pulang' => 0, 'belum_absen_keluar' => 0,
    'total_potongan_gaji' => 0, 'total_potongan_tunjangan' => 0,
    'total_menit_terlambat' => 0, 'overwork_count' => 0
];

foreach ($attendance_data as $record) {
    // Calculate status using the enhanced function
    $record['status_calculated'] = hitungStatusKehadiran($record, $pdo);

    // Calculate deductions
    $potongan_gaji = calculatePotonganGaji($record['status_calculated'], $record, $pdo);
    $potongan_tunjangan = calculatePotonganTunjangan($record['status_calculated'], $record, $pdo);

    $record['potongan_gaji'] = $potongan_gaji;
    $record['potongan_tunjangan'] = $potongan_tunjangan;

    // Update summary statistics
    $status_key = strtolower(str_replace([' ', '_'], '_', $record['status_calculated']));
    if (isset($summary_stats[$status_key])) {
        $summary_stats[$status_key]++;
    }

    if ($record['status_lembur'] === 'Approved') {
        $summary_stats['overwork_count']++;
    }

    $summary_stats['total_potongan_gaji'] += $potongan_gaji;
    if (!empty($record['menit_terlambat'])) {
        $summary_stats['total_menit_terlambat'] += $record['menit_terlambat'];
    }

    $processed_data[] = $record;
}

// Generate filename
$filename_prefix = "rekap_absensi_" . $view_type;
if ($view_type === 'monthly') {
    $filename_prefix .= "_" . date('F_Y', strtotime($start_date));
} elseif ($view_type === 'weekly') {
    $filename_prefix .= "_" . date('d_M', strtotime($start_date)) . "-" . date('d_M_Y', strtotime($end_date));
} elseif ($view_type === 'yearly') {
    $filename_prefix .= "_" . $year;
} else {
    $filename_prefix .= "_" . date('d_M_Y', strtotime($start_date));
}

if ($branch_filter !== 'all') {
    $filename_prefix .= "_cabang_" . preg_replace('/[^a-zA-Z0-9]/', '_', $branch_filter);
}

// Export based on format
if ($format === 'csv') {
    exportToCSV($processed_data, $summary_stats, $filename_prefix, $view_type, $start_date, $end_date, $user_role);
} elseif ($format === 'excel') {
    exportToExcel($processed_data, $summary_stats, $filename_prefix, $view_type, $start_date, $end_date, $user_role);
} else {
    die('Invalid format specified');
}

function exportToCSV($data, $summary, $filename, $view_type, $start_date, $end_date, $user_role) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $output = fopen('php://output', 'w');

    // BOM for Excel UTF-8 recognition
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header information
    fputcsv($output, ['KAORI Indonesia - Rekap Absensi']);
    fputcsv($output, ['Periode', ucfirst($view_type) . ' - ' . formatDateRange($start_date, $end_date, $view_type)]);
    fputcsv($output, ['Tanggal Export', date('d/m/Y H:i:s')]);
    fputcsv($output, ['Total Data', count($data) . ' record(s)']);
    fputcsv($output, []); // Empty row

    // Summary statistics
    fputcsv($output, ['RINGKASAN STATISTIK']);
    fputcsv($output, ['Status', 'Jumlah', 'Persentase']);
    $total_records = count($data);
    foreach ($summary as $key => $value) {
        if ($key !== 'total_potongan_gaji' && $key !== 'total_potongan_tunjangan' && $key !== 'total_menit_terlambat') {
            $percentage = $total_records > 0 ? round(($value / $total_records) * 100, 1) . '%' : '0%';
            fputcsv($output, [formatStatusLabel($key), $value, $percentage]);
        }
    }
    fputcsv($output, []); // Empty row

    // Financial summary
    fputcsv($output, ['RINGKASAN KEUANGAN']);
    fputcsv($output, ['Total Potongan Gaji', 'Rp ' . number_format($summary['total_potongan_gaji'], 0, ',', '.')]);
    fputcsv($output, ['Total Potongan Tunjangan', 'Rp ' . number_format($summary['total_potongan_tunjangan'], 0, ',', '.')]);
    fputcsv($output, ['Total Menit Terlambat', $summary['total_menit_terlambat'] . ' menit']);
    fputcsv($output, ['Jumlah Overwork', $summary['overwork_count'] . ' hari']);
    fputcsv($output, []); // Empty row

    // Data headers
    $headers = ['Tanggal', 'Nama Lengkap'];
    if ($user_role === 'admin') {
        $headers = array_merge($headers, ['Username', 'Cabang', 'Posisi']);
    }
    $headers = array_merge($headers, [
        'Waktu Masuk', 'Waktu Keluar', 'Status Kehadiran',
        'Menit Terlambat', 'Status Lembur', 'Potongan Gaji', 'Potongan Tunjangan'
    ]);
    fputcsv($output, $headers);

    // Data rows
    foreach ($data as $record) {
        $row = [
            date('d/m/Y', strtotime($record['tanggal_absensi'])),
            $record['nama_lengkap']
        ];

        if ($user_role === 'admin') {
            $row = array_merge($row, [
                $record['username'],
                $record['outlet'] ?? 'N/A',
                $record['role']
            ]);
        }

        $row = array_merge($row, [
            $record['waktu_masuk'] ? date('H:i:s', strtotime($record['waktu_masuk'])) : '-',
            $record['waktu_keluar'] ? date('H:i:s', strtotime($record['waktu_keluar'])) : '-',
            $record['status_calculated'],
            $record['menit_terlambat'] ?? 0,
            $record['status_lembur'] ?? 'Not Applicable',
            'Rp ' . number_format($record['potongan_gaji'], 0, ',', '.'),
            $record['potongan_tunjangan'] ?: '-'
        ]);

        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}

function exportToExcel($data, $summary, $filename, $view_type, $start_date, $end_date, $user_role) {
    // Check if PHPSpreadsheet is available
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        // Fallback to CSV if PHPSpreadsheet not available
        exportToCSV($data, $summary, $filename, $view_type, $start_date, $end_date, $user_role);
        return;
    }

    require_once 'vendor/autoload.php';

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Header information
    $sheet->setCellValue('A1', 'KAORI Indonesia - Rekap Absensi');
    $sheet->setCellValue('A2', 'Periode: ' . ucfirst($view_type) . ' - ' . formatDateRange($start_date, $end_date, $view_type));
    $sheet->setCellValue('A3', 'Tanggal Export: ' . date('d/m/Y H:i:s'));
    $sheet->setCellValue('A4', 'Total Data: ' . count($data) . ' record(s)');

    // Summary section
    $sheet->setCellValue('A6', 'RINGKASAN STATISTIK');
    $sheet->setCellValue('A7', 'Status');
    $sheet->setCellValue('B7', 'Jumlah');
    $sheet->setCellValue('C7', 'Persentase');

    $row = 8;
    $total_records = count($data);
    foreach ($summary as $key => $value) {
        if ($key !== 'total_potongan_gaji' && $key !== 'total_potongan_tunjangan' && $key !== 'total_menit_terlambat') {
            $percentage = $total_records > 0 ? round(($value / $total_records) * 100, 1) . '%' : '0%';
            $sheet->setCellValue('A' . $row, formatStatusLabel($key));
            $sheet->setCellValue('B' . $row, $value);
            $sheet->setCellValue('C' . $row, $percentage);
            $row++;
        }
    }

    // Financial summary
    $row += 2;
    $sheet->setCellValue('A' . $row, 'RINGKASAN KEUANGAN');
    $row++;
    $sheet->setCellValue('A' . $row, 'Total Potongan Gaji');
    $sheet->setCellValue('B' . $row, 'Rp ' . number_format($summary['total_potongan_gaji'], 0, ',', '.'));
    $row++;
    $sheet->setCellValue('A' . $row, 'Total Potongan Tunjangan');
    $sheet->setCellValue('B' . $row, 'Rp ' . number_format($summary['total_potongan_tunjangan'], 0, ',', '.'));
    $row++;
    $sheet->setCellValue('A' . $row, 'Total Menit Terlambat');
    $sheet->setCellValue('B' . $row, $summary['total_menit_terlambat'] . ' menit');
    $row++;
    $sheet->setCellValue('A' . $row, 'Jumlah Overwork');
    $sheet->setCellValue('B' . $row, $summary['overwork_count'] . ' hari');

    // Data headers
    $row += 3;
    $headers = ['Tanggal', 'Nama Lengkap'];
    if ($user_role === 'admin') {
        $headers = array_merge($headers, ['Username', 'Cabang', 'Posisi']);
    }
    $headers = array_merge($headers, [
        'Waktu Masuk', 'Waktu Keluar', 'Status Kehadiran',
        'Menit Terlambat', 'Status Lembur', 'Potongan Gaji', 'Potongan Tunjangan'
    ]);

    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $col++;
    }

    // Data rows
    $row++;
    foreach ($data as $record) {
        $col = 'A';
        $sheet->setCellValue($col++ . $row, date('d/m/Y', strtotime($record['tanggal_absensi'])));
        $sheet->setCellValue($col++ . $row, $record['nama_lengkap']);

        if ($user_role === 'admin') {
            $sheet->setCellValue($col++ . $row, $record['username']);
            $sheet->setCellValue($col++ . $row, $record['outlet'] ?? 'N/A');
            $sheet->setCellValue($col++ . $row, $record['role']);
        }

        $sheet->setCellValue($col++ . $row, $record['waktu_masuk'] ? date('H:i:s', strtotime($record['waktu_masuk'])) : '-');
        $sheet->setCellValue($col++ . $row, $record['waktu_keluar'] ? date('H:i:s', strtotime($record['waktu_keluar'])) : '-');
        $sheet->setCellValue($col++ . $row, $record['status_calculated']);
        $sheet->setCellValue($col++ . $row, $record['menit_terlambat'] ?? 0);
        $sheet->setCellValue($col++ . $row, $record['status_lembur'] ?? 'Not Applicable');
        $sheet->setCellValue($col++ . $row, 'Rp ' . number_format($record['potongan_gaji'], 0, ',', '.'));
        $sheet->setCellValue($col++ . $row, $record['potongan_tunjangan'] ?: '-');

        $row++;
    }

    // Auto-size columns
    foreach (range('A', $col) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function formatDateRange($start, $end, $view_type) {
    switch ($view_type) {
        case 'weekly':
            return date('d M', strtotime($start)) . ' - ' . date('d M Y', strtotime($end));
        case 'monthly':
            return date('F Y', strtotime($start));
        case 'yearly':
            return date('Y', strtotime($start));
        default:
            return date('d M Y', strtotime($start));
    }
}

function formatStatusLabel($key) {
    $labels = [
        'hadir' => 'Hadir',
        'belum_memenuhi_kriteria' => 'Belum Memenuhi Kriteria',
        'tidak_hadir' => 'Tidak Hadir',
        'terlambat_tanpa_potongan' => 'Terlambat Tanpa Potongan',
        'terlambat_dengan_potongan' => 'Terlambat Dengan Potongan',
        'izin' => 'Izin',
        'sakit' => 'Sakit',
        'lupa_absen_pulang' => 'Lupa Absen Pulang',
        'belum_absen_keluar' => 'Belum Absen Keluar',
        'overwork_count' => 'Overwork'
    ];

    return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
}
?>