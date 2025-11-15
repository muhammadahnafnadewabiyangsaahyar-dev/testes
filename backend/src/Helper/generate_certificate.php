<?php
/**
 * PERFORMANCE CERTIFICATE GENERATOR
 *
 * Features:
 * - Professional certificate design
 * - Customizable templates
 * - PDF generation with employee details
 * - Performance metrics inclusion
 * - Company branding
 */

require_once 'connect.php';
require_once 'vendor/autoload.php'; // For PDF generation

// Get parameters
$employee_id = $_GET['employee_id'] ?? 0;
$rank = $_GET['rank'] ?? 0;
$period = $_GET['period'] ?? 'monthly';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Get employee data
$stmt = $pdo->prepare("
    SELECT r.*, bp.total_score, bp.attendance_rate, bp.punctuality_score
    FROM register r
    LEFT JOIN (
        SELECT * FROM (
            SELECT
                r.id,
                r.nama_lengkap,
                COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) as hadir_count,
                COUNT(CASE WHEN a.status_kehadiran IN ('Tidak Hadir', 'Izin', 'Sakit') THEN 1 END) as absence_count,
                COUNT(CASE WHEN a.menit_terlambat > 0 THEN 1 END) as late_count,
                ROUND(
                    (COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) * 100.0) /
                    NULLIF(COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) + COUNT(CASE WHEN a.status_kehadiran IN ('Tidak Hadir', 'Izin', 'Sakit') THEN 1 END), 0), 1
                ) as attendance_rate,
                ROUND(
                    100 - (COUNT(CASE WHEN a.menit_terlambat > 0 THEN 1 END) * 5), 1
                ) as punctuality_score,
                (ROUND(
                    (COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) * 100.0) /
                    NULLIF(COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) + COUNT(CASE WHEN a.status_kehadiran IN ('Tidak Hadir', 'Izin', 'Sakit') THEN 1 END), 0), 1
                ) * 0.4 +
                ROUND(100 - (COUNT(CASE WHEN a.menit_terlambat > 0 THEN 1 END) * 5), 1) * 0.3 +
                80 * 0.2 + 8 * 0.1) as total_score
            FROM register r
            LEFT JOIN absensi a ON r.id = a.user_id
                AND DATE_FORMAT(a.tanggal_absensi, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
            WHERE r.role = 'user'
            GROUP BY r.id
            ORDER BY total_score DESC
        ) ranked
        WHERE ranked.id = ?
    ) bp ON r.id = bp.id
    WHERE r.id = ?
");
$stmt->execute([$employee_id, $employee_id]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    die("Employee not found");
}

// Generate certificate data
$certificate_data = [
    'employee_name' => $employee['nama_lengkap'],
    'position' => $employee['posisi'],
    'department' => $employee['outlet'],
    'rank' => $rank,
    'period' => getPeriodText($period, $month, $year),
    'score' => $employee['total_score'] ?? 0,
    'attendance_rate' => $employee['attendance_rate'] ?? 0,
    'punctuality_score' => $employee['punctuality_score'] ?? 0,
    'date_issued' => date('d F Y'),
    'certificate_id' => 'CERT-' . date('Y') . '-' . str_pad($employee_id, 4, '0', STR_PAD_LEFT) . '-' . str_pad($rank, 2, '0', STR_PAD_LEFT)
];

// Generate PDF certificate
generateCertificatePDF($certificate_data);

/**
 * Generate professional PDF certificate
 */
function generateCertificatePDF($data) {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('KAORI Indonesia HR System');
    $pdf->SetAuthor('KAORI Indonesia');
    $pdf->SetTitle('Performance Certificate - ' . $data['employee_name']);
    $pdf->SetSubject('Employee Performance Certificate');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Add a page
    $pdf->AddPage();

    // Certificate design
    generateCertificateDesign($pdf, $data);

    // Output PDF
    $filename = 'performance_certificate_' . preg_replace('/[^a-zA-Z0-9]/', '_', $data['employee_name']) . '.pdf';
    $pdf->Output($filename, 'D');
}

/**
 * Generate certificate visual design
 */
function generateCertificateDesign($pdf, $data) {
    // Background color
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect(0, 0, 210, 297, 'F');

    // Border
    $pdf->SetDrawColor(102, 126, 234);
    $pdf->SetLineWidth(3);
    $pdf->Rect(10, 10, 190, 277);

    // Inner border
    $pdf->SetDrawColor(102, 126, 234);
    $pdf->SetLineWidth(1);
    $pdf->Rect(15, 15, 180, 267);

    // Company header
    $pdf->SetFont('helvetica', 'B', 24);
    $pdf->SetTextColor(102, 126, 234);
    $pdf->SetXY(20, 30);
    $pdf->Cell(170, 15, 'KAORI INDONESIA', 0, 1, 'C');

    // Certificate title
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(20, 50);
    $pdf->Cell(170, 12, 'PERFORMANCE CERTIFICATE', 0, 1, 'C');

    // Certificate of recognition
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetXY(20, 70);
    $pdf->MultiCell(170, 8, 'This is to certify that', 0, 'C');

    // Employee name
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(102, 126, 234);
    $pdf->SetXY(20, 85);
    $pdf->Cell(170, 12, $data['employee_name'], 0, 1, 'C');

    // Position and department
    $pdf->SetFont('helvetica', '', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(20, 100);
    $pdf->Cell(170, 8, $data['position'] . ' - ' . $data['department'], 0, 1, 'C');

    // Achievement text
    $achievement_text = "has achieved outstanding performance and ranked #" . $data['rank'] . " in the " . $data['period'] . " performance evaluation.";
    $pdf->SetXY(20, 115);
    $pdf->MultiCell(170, 8, $achievement_text, 0, 'C');

    // Performance metrics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetXY(20, 135);
    $pdf->Cell(170, 8, 'Performance Metrics:', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 11);
    $metrics = [
        "Overall Score: " . number_format($data['score'], 1) . " points",
        "Attendance Rate: " . $data['attendance_rate'] . "%",
        "Punctuality Score: " . $data['punctuality_score'] . "%"
    ];

    $y = 150;
    foreach ($metrics as $metric) {
        $pdf->SetXY(20, $y);
        $pdf->Cell(170, 8, $metric, 0, 1, 'C');
        $y += 10;
    }

    // Certificate ID
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->SetXY(20, 190);
    $pdf->Cell(170, 6, 'Certificate ID: ' . $data['certificate_id'], 0, 1, 'C');

    // Date issued
    $pdf->SetXY(20, 200);
    $pdf->Cell(170, 6, 'Date Issued: ' . $data['date_issued'], 0, 1, 'C');

    // Signatures section
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);

    // HR Signature
    $pdf->SetXY(30, 230);
    $pdf->Cell(60, 8, '___________________________', 0, 1, 'C');
    $pdf->SetXY(30, 240);
    $pdf->Cell(60, 8, 'HR Manager', 0, 1, 'C');

    // CEO Signature
    $pdf->SetXY(120, 230);
    $pdf->Cell(60, 8, '___________________________', 0, 1, 'C');
    $pdf->SetXY(120, 240);
    $pdf->Cell(60, 8, 'CEO/Owner', 0, 1, 'C');

    // Footer
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->SetXY(20, 270);
    $pdf->Cell(170, 6, 'This certificate is automatically generated by KAORI Indonesia HR Management System', 0, 1, 'C');
}

/**
 * Get period text for certificate
 */
function getPeriodText($period, $month, $year) {
    switch ($period) {
        case 'weekly':
            return "Week of " . date('F Y', strtotime($year . '-' . $month . '-01'));
        case 'monthly':
            return date('F Y', strtotime($year . '-' . $month . '-01'));
        case 'yearly':
            return $year;
        default:
            return date('F Y');
    }
}
?>