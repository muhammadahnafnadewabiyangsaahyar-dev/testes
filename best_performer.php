<?php
/**
 * EMPLOYEE BEST PERFORMER RANKINGS
 *
 * Features:
 * - Monthly and weekly performance rankings
 * - Multi-criteria scoring system
 * - Department-wise and company-wide rankings
 * - Performance badges and certificates
 * - Historical performance tracking
 * - AI-powered performance analysis
 *
 * Scoring Criteria:
 * - Attendance Rate (40%)
 * - Punctuality (30%)
 * - Shift Completion (20%)
 * - Leave Management (10%)
 */

session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Get filter parameters
$period = $_GET['period'] ?? 'monthly'; // monthly, weekly, yearly
$department = $_GET['department'] ?? 'all';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Calculate date ranges
switch ($period) {
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
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

// Get performance rankings
$rankings = getPerformanceRankings($pdo, $start_date, $end_date, $department);

// Get department list for filter
$departments = $pdo->query("SELECT DISTINCT outlet FROM register WHERE outlet IS NOT NULL ORDER BY outlet")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Best Performer Rankings - KAORI Indonesia</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .ranking-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .ranking-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .ranking-title {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            font-weight: bold;
        }

        .ranking-subtitle {
            font-size: 1.2em;
            opacity: 0.9;
            margin: 0;
        }

        .filters-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-weight: bold;
            color: #333;
        }

        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            min-width: 120px;
        }

        .podium-section {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }

        .podium-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .podium-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
        }

        .podium-card.gold::before { background: linear-gradient(90deg, #FFD700, #FFA500); }
        .podium-card.silver::before { background: linear-gradient(90deg, #C0C0C0, #A8A8A8); }
        .podium-card.bronze::before { background: linear-gradient(90deg, #CD7F32, #A0522D); }

        .rank-badge {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            margin: 0 auto 15px;
            color: white;
        }

        .rank-badge.gold { background: linear-gradient(135deg, #FFD700, #FFA500); }
        .rank-badge.silver { background: linear-gradient(135deg, #C0C0C0, #A8A8A8); }
        .rank-badge.bronze { background: linear-gradient(135deg, #CD7F32, #A0522D); }

        .employee-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            border: 4px solid #667eea;
            object-fit: cover;
        }

        .employee-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .employee-dept {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .performance-score {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }

        .performance-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 0.85em;
            color: #666;
        }

        .metric-item {
            display: flex;
            justify-content: space-between;
        }

        .ranking-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .ranking-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .ranking-table th {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }

        .ranking-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .ranking-table tr:hover {
            background: #f8f9fa;
        }

        .rank-number {
            width: 50px;
            text-align: center;
            font-weight: bold;
            font-size: 1.2em;
        }

        .rank-number.gold { color: #FFD700; }
        .rank-number.silver { color: #C0C0C0; }
        .rank-number.bronze { color: #CD7F32; }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .employee-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #667eea;
        }

        .employee-details h4 {
            margin: 0 0 5px 0;
            color: #333;
        }

        .employee-details small {
            color: #666;
        }

        .score-display {
            text-align: center;
            font-weight: bold;
            font-size: 1.1em;
            color: #667eea;
        }

        .metrics-display {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .metric-badge {
            background: #f0f0f0;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            color: #666;
        }

        .certificate-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: 30px;
            text-align: center;
        }

        .certificate-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .certificate-btn:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .podium-section {
                grid-template-columns: 1fr;
            }

            .filters-section {
                flex-direction: column;
                align-items: stretch;
            }

            .ranking-table {
                font-size: 14px;
            }

            .ranking-table th,
            .ranking-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="ranking-container">
        <div class="ranking-header">
            <h1 class="ranking-title">🏆 Best Performer Rankings</h1>
            <p class="ranking-subtitle">Recognizing Excellence in Employee Performance</p>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                <div class="filter-group">
                    <label>Period:</label>
                    <select name="period" onchange="this.form.submit()">
                        <option value="weekly" <?php echo $period === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                        <option value="monthly" <?php echo $period === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        <option value="yearly" <?php echo $period === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                    </select>
                </div>

                <?php if ($period !== 'yearly'): ?>
                <div class="filter-group">
                    <label>Month:</label>
                    <select name="month" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="filter-group">
                    <label>Year:</label>
                    <select name="year" onchange="this.form.submit()">
                        <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Department:</label>
                    <select name="department" onchange="this.form.submit()">
                        <option value="all">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Top 3 Podium -->
        <?php if (!empty($rankings) && count($rankings) >= 3): ?>
        <div class="podium-section">
            <?php
            $top3 = array_slice($rankings, 0, 3);
            $positions = ['gold', 'silver', 'bronze'];
            $ranks = [1, 2, 3];

            foreach ($top3 as $index => $performer):
            ?>
            <div class="podium-card <?php echo $positions[$index]; ?>">
                <div class="rank-badge <?php echo $positions[$index]; ?>"><?php echo $ranks[$index]; ?></div>

                <img src="<?php echo !empty($performer['foto_profil']) && $performer['foto_profil'] !== 'default.png'
                    ? 'uploads/foto_profil/' . $performer['foto_profil']
                    : 'logo.png'; ?>"
                    alt="Employee Photo" class="employee-avatar">

                <div class="employee-name"><?php echo htmlspecialchars($performer['nama_lengkap']); ?></div>
                <div class="employee-dept"><?php echo htmlspecialchars($performer['outlet'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($performer['posisi']); ?></div>

                <div class="performance-score"><?php echo number_format($performer['total_score'], 1); ?> pts</div>

                <div class="performance-metrics">
                    <div class="metric-item">
                        <span>Attendance:</span>
                        <span><?php echo $performer['attendance_rate']; ?>%</span>
                    </div>
                    <div class="metric-item">
                        <span>Punctuality:</span>
                        <span><?php echo $performer['punctuality_score']; ?>%</span>
                    </div>
                    <div class="metric-item">
                        <span>Shifts:</span>
                        <span><?php echo $performer['shift_completion']; ?>%</span>
                    </div>
                    <div class="metric-item">
                        <span>Leaves:</span>
                        <span><?php echo $performer['leave_score']; ?>/10</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Full Rankings Table -->
        <div class="ranking-table">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Employee</th>
                        <th>Score</th>
                        <th>Performance Metrics</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rankings)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-info-circle" style="font-size: 2em; margin-bottom: 10px;"></i>
                            <br>No performance data available for the selected period.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($rankings as $rank => $performer): ?>
                        <tr>
                            <td class="rank-number <?php
                                if ($rank + 1 === 1) echo 'gold';
                                elseif ($rank + 1 === 2) echo 'silver';
                                elseif ($rank + 1 === 3) echo 'bronze';
                            ?>">
                                <?php if ($rank + 1 <= 3): ?>
                                    <i class="fas fa-trophy"></i>
                                <?php else: ?>
                                    <?php echo $rank + 1; ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="employee-info">
                                    <img src="<?php echo !empty($performer['foto_profil']) && $performer['foto_profil'] !== 'default.png'
                                        ? 'uploads/foto_profil/' . $performer['foto_profil']
                                        : 'logo.png'; ?>"
                                        alt="Employee Photo" class="employee-photo">

                                    <div class="employee-details">
                                        <h4><?php echo htmlspecialchars($performer['nama_lengkap']); ?></h4>
                                        <small><?php echo htmlspecialchars($performer['outlet'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($performer['posisi']); ?></small>
                                    </div>
                                </div>
                            </td>

                            <td class="score-display">
                                <?php echo number_format($performer['total_score'], 1); ?> pts
                            </td>

                            <td>
                                <div class="metrics-display">
                                    <span class="metric-badge">📊 <?php echo $performer['attendance_rate']; ?>% Attendance</span>
                                    <span class="metric-badge">⏰ <?php echo $performer['punctuality_score']; ?>% Punctual</span>
                                    <span class="metric-badge">📅 <?php echo $performer['shift_completion']; ?>% Shifts</span>
                                    <span class="metric-badge">📄 <?php echo $performer['leave_score']; ?>/10 Leaves</span>
                                </div>
                            </td>

                            <td>
                                <button class="certificate-btn" onclick="generateCertificate(<?php echo $performer['id']; ?>, <?php echo $rank + 1; ?>)">
                                    🏆 Certificate
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Certificate Generation Section -->
        <div class="certificate-section">
            <h2><i class="fas fa-certificate"></i> Performance Certificates</h2>
            <p>Generate and download performance certificates for top performers</p>
            <button class="certificate-btn" onclick="generateBulkCertificates()">
                📜 Generate Bulk Certificates
            </button>
        </div>
    </div>

    <script>
        function generateCertificate(employeeId, rank) {
            // Open certificate generation in new window
            window.open(`generate_certificate.php?employee_id=${employeeId}&rank=${rank}&period=<?php echo $period; ?>&year=<?php echo $year; ?><?php echo $period !== 'yearly' ? '&month=' . $month : ''; ?>`, '_blank');
        }

        function generateBulkCertificates() {
            // Generate certificates for top 10 performers
            if (confirm('Generate certificates for top 10 performers?')) {
                window.open(`generate_bulk_certificates.php?period=<?php echo $period; ?>&year=<?php echo $year; ?><?php echo $period !== 'yearly' ? '&month=' . $month : ''; ?>&department=<?php echo urlencode($department); ?>`, '_blank');
            }
        }
    </script>
</body>
</html>

<?php
/**
 * Calculate performance rankings with multi-criteria scoring
 */
function getPerformanceRankings($pdo, $start_date, $end_date, $department_filter = 'all') {
    // Get all employees with their performance data
    $sql = "
        SELECT
            r.id,
            r.nama_lengkap,
            r.posisi,
            r.outlet,
            r.foto_profil,

            -- Attendance metrics
            COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) as hadir_count,
            COUNT(CASE WHEN a.status_kehadiran IN ('Tidak Hadir', 'Izin', 'Sakit') THEN 1 END) as absence_count,
            COUNT(CASE WHEN a.menit_terlambat > 0 THEN 1 END) as late_count,
            AVG(CASE WHEN a.menit_terlambat > 0 THEN a.menit_terlambat END) as avg_lateness,

            -- Shift completion
            COUNT(DISTINCT CASE WHEN sa.status_konfirmasi = 'approved' THEN sa.id END) as shifts_completed,
            COUNT(DISTINCT CASE WHEN sa.status_konfirmasi IN ('pending', 'declined') THEN sa.id END) as shifts_pending,

            -- Leave management (lower is better - fewer unplanned leaves)
            COUNT(CASE WHEN p.status = 'Diterima' THEN 1 END) as approved_leaves,
            COUNT(CASE WHEN p.status = 'Ditolak' THEN 1 END) as rejected_leaves

        FROM register r
        LEFT JOIN absensi a ON r.id = a.user_id
            AND DATE(a.tanggal_absensi) BETWEEN ? AND ?
        LEFT JOIN shift_assignments sa ON r.id = sa.user_id
            AND DATE(sa.tanggal_shift) BETWEEN ? AND ?
        LEFT JOIN pengajuan_izin p ON r.id = p.user_id
            AND DATE(p.tanggal_mulai) BETWEEN ? AND ?
        WHERE r.role = 'user'
    ";

    $params = [$start_date, $end_date, $start_date, $end_date, $start_date, $end_date];

    if ($department_filter !== 'all') {
        $sql .= " AND r.outlet = ?";
        $params[] = $department_filter;
    }

    $sql .= " GROUP BY r.id, r.nama_lengkap, r.posisi, r.outlet, r.foto_profil";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate performance scores for each employee
    $rankings = [];
    foreach ($employees as $emp) {
        $scores = calculateEmployeePerformanceScore($emp);
        $emp = array_merge($emp, $scores);
        $rankings[] = $emp;
    }

    // Sort by total score descending
    usort($rankings, function($a, $b) {
        return $b['total_score'] <=> $a['total_score'];
    });

    return $rankings;
}

/**
 * Calculate comprehensive performance score for an employee
 */
function calculateEmployeePerformanceScore($employee) {
    $total_days = $employee['hadir_count'] + $employee['absence_count'];
    if ($total_days == 0) $total_days = 1; // Avoid division by zero

    // 1. Attendance Rate (40% weight)
    $attendance_rate = ($employee['hadir_count'] / $total_days) * 100;
    $attendance_score = min(100, $attendance_rate) * 0.4;

    // 2. Punctuality Score (30% weight)
    $late_penalty = min(50, ($employee['late_count'] * 5)); // 5 points per late instance
    $lateness_penalty = min(30, ($employee['avg_lateness'] ?? 0) / 2); // 0.5 points per minute
    $punctuality_score = max(0, 100 - $late_penalty - $lateness_penalty) * 0.3;

    // 3. Shift Completion Rate (20% weight)
    $total_shifts = $employee['shifts_completed'] + $employee['shifts_pending'];
    if ($total_shifts == 0) $total_shifts = 1;
    $shift_completion_rate = ($employee['shifts_completed'] / $total_shifts) * 100;
    $shift_score = min(100, $shift_completion_rate) * 0.2;

    // 4. Leave Management Score (10% weight) - Reward planned leaves, penalize rejected
    $leave_score = 10; // Start with perfect score
    if ($employee['approved_leaves'] > 0) {
        $leave_score -= min(3, $employee['approved_leaves']); // Small penalty for approved leaves
    }
    if ($employee['rejected_leaves'] > 0) {
        $leave_score -= ($employee['rejected_leaves'] * 2); // Larger penalty for rejected leaves
    }
    $leave_score = max(0, $leave_score) * 0.1;

    // Total score
    $total_score = $attendance_score + $punctuality_score + $shift_score + $leave_score;

    return [
        'attendance_rate' => round($attendance_rate, 1),
        'punctuality_score' => round(($punctuality_score / 0.3), 1), // Convert back to percentage
        'shift_completion' => round($shift_completion_rate, 1),
        'leave_score' => round($leave_score / 0.1, 1), // Convert back to scale
        'total_score' => round($total_score, 1)
    ];
}
?>