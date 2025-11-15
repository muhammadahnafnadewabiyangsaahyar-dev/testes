<?php
/**
 * HR Overview Dashboard with AI-Powered Summaries
 *
 * Features:
 * - Real-time HR metrics and KPIs
 * - AI-powered performance analysis and insights
 * - Employee performance rankings
 * - Department-wise analytics
 * - Predictive analytics for attendance patterns
 * - Automated report generation
 *
 * AI Integration: Ollama API (apikey: 8eb759e4b66948599906c2cc4ecf2a36)
 */

session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Initialize OpenRouter API
define('OPENROUTER_API_KEY', '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');
define('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1');

// Get overview data with enhanced metrics
$overview_data = [];

// 1. Total employees
$sql_total_employees = "SELECT COUNT(*) as total FROM register WHERE role = 'user'";
$stmt = $pdo->query($sql_total_employees);
$overview_data['total_employees'] = $stmt->fetchColumn();

// 2. Active shifts today
$sql_active_shifts = "SELECT COUNT(DISTINCT sa.user_id) as total
                     FROM shift_assignments sa
                     JOIN cabang c ON sa.cabang_id = c.id
                     WHERE sa.tanggal_shift = CURDATE()
                     AND sa.status_konfirmasi = 'approved'";
$stmt = $pdo->prepare($sql_active_shifts);
$stmt->execute();
$overview_data['active_shifts_today'] = $stmt->fetchColumn();

// 3. Attendance today
$sql_attendance_today = "SELECT
    COUNT(CASE WHEN status_kehadiran = 'Hadir' THEN 1 END) as hadir,
    COUNT(CASE WHEN status_kehadiran = 'Tidak Hadir' THEN 1 END) as tidak_hadir,
    COUNT(CASE WHEN status_kehadiran = 'Izin' THEN 1 END) as izin,
    COUNT(CASE WHEN status_kehadiran = 'Sakit' THEN 1 END) as sakit
    FROM absensi WHERE tanggal_absensi = CURDATE()";
$stmt = $pdo->query($sql_attendance_today);
$attendance_today = $stmt->fetch(PDO::FETCH_ASSOC);
$overview_data['attendance_today'] = $attendance_today;

// 4. Pending shift confirmations
$sql_pending_confirmations = "SELECT COUNT(*) as total FROM shift_assignments
                             WHERE status_konfirmasi = 'pending'
                             AND tanggal_shift >= CURDATE()";
$stmt = $pdo->query($sql_pending_confirmations);
$overview_data['pending_confirmations'] = $stmt->fetchColumn();

// 5. Top performers this month
$sql_top_performers = "SELECT
    r.nama_lengkap,
    COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) as hadir,
    COUNT(CASE WHEN a.menit_terlambat > 0 THEN 1 END) as terlambat,
    ROUND(
        (COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) * 100.0) /
        NULLIF(COUNT(*), 0), 1
    ) as persentase_kehadiran
    FROM register r
    LEFT JOIN absensi a ON r.id = a.user_id
        AND DATE_FORMAT(a.tanggal_absensi, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    WHERE r.role = 'user'
    GROUP BY r.id, r.nama_lengkap
    ORDER BY persentase_kehadiran DESC, hadir DESC
    LIMIT 5";
$stmt = $pdo->query($sql_top_performers);
$overview_data['top_performers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Monthly attendance summary
$sql_monthly_summary = "SELECT
    COUNT(CASE WHEN status_kehadiran = 'Hadir' THEN 1 END) as total_hadir,
    COUNT(CASE WHEN status_kehadiran = 'Tidak Hadir' THEN 1 END) as total_tidak_hadir,
    COUNT(CASE WHEN status_kehadiran = 'Izin' THEN 1 END) as total_izin,
    COUNT(CASE WHEN status_kehadiran = 'Sakit' THEN 1 END) as total_sakit,
    ROUND(AVG(CASE WHEN menit_terlambat > 0 THEN menit_terlambat END), 1) as avg_terlambat
    FROM absensi
    WHERE DATE_FORMAT(tanggal_absensi, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
$stmt = $pdo->query($sql_monthly_summary);
$overview_data['monthly_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

// 7. Department-wise attendance
$sql_department_stats = "SELECT
    r.outlet as department,
    COUNT(DISTINCT r.id) as total_employees,
    COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) as hadir_today,
    ROUND(
        (COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) * 100.0) /
        NULLIF(COUNT(DISTINCT r.id), 0), 1
    ) as attendance_rate
    FROM register r
    LEFT JOIN absensi a ON r.id = a.user_id AND a.tanggal_absensi = CURDATE()
    WHERE r.role = 'user'
    GROUP BY r.outlet
    ORDER BY attendance_rate DESC";
$stmt = $pdo->query($sql_department_stats);
$overview_data['department_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. AI-Powered Analytics Data Collection
$ai_analytics = collectAIAnalyticsData($pdo);
$overview_data['ai_insights'] = generateAIInsights($ai_analytics);
$overview_data['performance_predictions'] = generatePerformancePredictions($ai_analytics);
$overview_data['department_trends'] = analyzeDepartmentTrends($ai_analytics);
$overview_data['employee_risk_assessment'] = assessEmployeeRisks($ai_analytics);

/**
 * Collect comprehensive data for AI analysis
 */
function collectAIAnalyticsData($pdo) {
    $data = [];

    // Current month attendance patterns
    $data['current_month'] = $pdo->query("
        SELECT
            DATE_FORMAT(tanggal_absensi, '%Y-%m-%d') as date,
            COUNT(CASE WHEN status_kehadiran = 'Hadir' THEN 1 END) as hadir,
            COUNT(CASE WHEN status_kehadiran = 'Tidak Hadir' THEN 1 END) as tidak_hadir,
            COUNT(CASE WHEN status_kehadiran = 'Izin' THEN 1 END) as izin,
            COUNT(CASE WHEN status_kehadiran = 'Sakit' THEN 1 END) as sakit,
            AVG(CASE WHEN menit_terlambat > 0 THEN menit_terlambat END) as avg_lateness
        FROM absensi
        WHERE DATE_FORMAT(tanggal_absensi, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        GROUP BY tanggal_absensi
        ORDER BY tanggal_absensi
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Employee performance metrics
    $data['employee_performance'] = $pdo->query("
        SELECT
            r.nama_lengkap,
            r.outlet,
            r.posisi,
            COUNT(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 END) as hadir_count,
            COUNT(CASE WHEN a.status_kehadiran = 'Tidak Hadir' THEN 1 END) as tidak_hadir_count,
            COUNT(CASE WHEN a.menit_terlambat > 0 THEN 1 END) as terlambat_count,
            AVG(CASE WHEN a.menit_terlambat > 0 THEN a.menit_terlambat END) as avg_lateness,
            COUNT(DISTINCT CASE WHEN sa.status_konfirmasi = 'approved' THEN sa.id END) as shifts_completed
        FROM register r
        LEFT JOIN absensi a ON r.id = a.user_id
            AND DATE_FORMAT(a.tanggal_absensi, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        LEFT JOIN shift_assignments sa ON r.id = sa.user_id
            AND DATE_FORMAT(sa.tanggal_shift, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        WHERE r.role = 'user'
        GROUP BY r.id, r.nama_lengkap, r.outlet, r.posisi
        ORDER BY hadir_count DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Department trends
    $data['department_trends'] = $pdo->query("
        SELECT
            r.outlet as department,
            COUNT(DISTINCT r.id) as total_employees,
            AVG(CASE WHEN a.status_kehadiran = 'Hadir' THEN 1 ELSE 0 END) * 100 as avg_attendance_rate,
            AVG(CASE WHEN a.menit_terlambat > 0 THEN a.menit_terlambat END) as avg_lateness,
            COUNT(CASE WHEN a.status_kehadiran = 'Tidak Hadir' THEN 1 END) as total_absences
        FROM register r
        LEFT JOIN absensi a ON r.id = a.user_id
            AND DATE_FORMAT(a.tanggal_absensi, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        WHERE r.role = 'user'
        GROUP BY r.outlet
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Leave patterns
    $data['leave_patterns'] = $pdo->query("
        SELECT
            r.outlet,
            COUNT(CASE WHEN p.perihal = 'Sakit' THEN 1 END) as sakit_count,
            COUNT(CASE WHEN p.perihal = 'Izin' THEN 1 END) as izin_count,
            COUNT(CASE WHEN p.status = 'Pending' THEN 1 END) as pending_leaves
        FROM register r
        LEFT JOIN pengajuan_izin p ON r.id = p.user_id
            AND DATE_FORMAT(p.tanggal_mulai, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        WHERE r.role = 'user'
        GROUP BY r.outlet
    ")->fetchAll(PDO::FETCH_ASSOC);

    return $data;
}

/**
 * Generate AI-powered insights using Ollama
 */
function generateAIInsights($data) {
    $insights = [];

    // Prepare data for AI analysis
    $analysis_data = [
        'total_employees' => count($data['employee_performance']),
        'avg_attendance' => calculateAverageAttendance($data),
        'top_performers' => array_slice($data['employee_performance'], 0, 5),
        'department_performance' => $data['department_trends'],
        'leave_patterns' => $data['leave_patterns']
    ];

    // Generate AI insights
    $insights['attendance_trends'] = callOllamaAI(
        "Analyze this attendance data and provide key insights: " . json_encode($analysis_data),
        "attendance_analysis"
    );

    $insights['performance_insights'] = callOllamaAI(
        "Based on employee performance data, identify patterns and recommendations: " . json_encode($data['employee_performance']),
        "performance_analysis"
    );

    $insights['department_analysis'] = callOllamaAI(
        "Analyze department performance and suggest improvements: " . json_encode($data['department_trends']),
        "department_analysis"
    );

    return $insights;
}

/**
 * Generate performance predictions
 */
function generatePerformancePredictions($data) {
    $predictions = [];

    // Simple trend analysis (can be enhanced with ML models)
    $current_month_data = $data['current_month'];

    if (count($current_month_data) >= 7) {
        $recent_week = array_slice($current_month_data, -7);
        $attendance_trend = calculateTrend($recent_week, 'hadir');

        $predictions['attendance_trend'] = $attendance_trend > 0 ? 'increasing' : ($attendance_trend < 0 ? 'decreasing' : 'stable');
        $predictions['predicted_attendance'] = predictNextWeekAttendance($recent_week);
    }

    // Employee risk assessment
    $predictions['at_risk_employees'] = identifyAtRiskEmployees($data['employee_performance']);

    return $predictions;
}

/**
 * Analyze department trends
 */
function analyzeDepartmentTrends($data) {
    $trends = [];

    foreach ($data['department_trends'] as $dept) {
        $trends[$dept['department']] = [
            'performance_score' => calculateDepartmentScore($dept),
            'risk_level' => assessDepartmentRisk($dept),
            'recommendations' => generateDepartmentRecommendations($dept)
        ];
    }

    return $trends;
}

/**
 * Assess employee risks
 */
function assessEmployeeRisks($data) {
    $risks = [];

    foreach ($data['employee_performance'] as $employee) {
        $risk_score = calculateEmployeeRiskScore($employee);
        $risks[] = [
            'name' => $employee['nama_lengkap'],
            'department' => $employee['outlet'],
            'risk_score' => $risk_score,
            'risk_level' => $risk_score > 70 ? 'high' : ($risk_score > 40 ? 'medium' : 'low'),
            'factors' => identifyRiskFactors($employee)
        ];
    }

    // Sort by risk score descending
    usort($risks, function($a, $b) {
        return $b['risk_score'] <=> $a['risk_score'];
    });

    return array_slice($risks, 0, 10); // Top 10 at-risk employees
}

/**
 * Call OpenRouter AI API for analysis
 */
function callOllamaAI($prompt, $context = "general") {
    try {
        $openrouter_url = OPENROUTER_BASE_URL . "/chat/completions";

        $data = [
            'model' => 'deepseek/deepseek-chat-v3.1:free', // Free model
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7,
            'top_p' => 0.9
        ];

        $ch = curl_init($openrouter_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENROUTER_API_KEY,
            'HTTP-Referer: https://kaori-hr.com',
            'X-Title: KAORI HR Analytics'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("OpenRouter API curl error: " . $error);
            return 'AI analysis temporarily unavailable (Connection Error)';
        }

        if ($http_code === 200) {
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                return trim($result['choices'][0]['message']['content']);
            }
        }

        error_log("OpenRouter API error - HTTP: {$http_code}, Response: " . substr($response, 0, 200));
        return 'AI analysis temporarily unavailable (API Error: ' . $http_code . ')';

    } catch (Exception $e) {
        error_log("OpenRouter API exception: " . $e->getMessage());
        return 'AI analysis temporarily unavailable (Exception Error)';
    }
}

/**
 * Helper functions for calculations
 */
function calculateAverageAttendance($data) {
    if (empty($data['current_month'])) return 0;

    $total_hadir = array_sum(array_column($data['current_month'], 'hadir'));
    $total_days = count($data['current_month']);

    return $total_days > 0 ? round(($total_hadir / ($total_days * count($data['employee_performance']))) * 100, 1) : 0;
}

function calculateTrend($data, $field) {
    if (count($data) < 2) return 0;

    $first_half = array_slice($data, 0, floor(count($data) / 2));
    $second_half = array_slice($data, floor(count($data) / 2));

    $first_avg = array_sum(array_column($first_half, $field)) / count($first_half);
    $second_avg = array_sum(array_column($second_half, $field)) / count($second_half);

    return $second_avg - $first_avg;
}

function predictNextWeekAttendance($recent_data) {
    // Simple linear regression for prediction
    $n = count($recent_data);
    if ($n < 2) return 0;

    $sum_x = $sum_y = $sum_xy = $sum_x2 = 0;

    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $recent_data[$i]['hadir'];

        $sum_x += $x;
        $sum_y += $y;
        $sum_xy += $x * $y;
        $sum_x2 += $x * $x;
    }

    $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
    $intercept = ($sum_y - $slope * $sum_x) / $n;

    return round($slope * ($n + 1) + $intercept);
}

function identifyAtRiskEmployees($employees) {
    $at_risk = [];

    foreach ($employees as $emp) {
        $attendance_rate = ($emp['hadir_count'] / max(1, $emp['hadir_count'] + $emp['tidak_hadir_count'])) * 100;

        if ($attendance_rate < 80 || $emp['terlambat_count'] > 5) {
            $at_risk[] = [
                'name' => $emp['nama_lengkap'],
                'attendance_rate' => round($attendance_rate, 1),
                'late_count' => $emp['terlambat_count']
            ];
        }
    }

    return $at_risk;
}

function calculateDepartmentScore($dept) {
    $attendance_score = min(100, $dept['avg_attendance_rate'] ?? 0);
    $lateness_penalty = min(50, ($dept['avg_lateness'] ?? 0) / 2);
    $absence_penalty = min(30, ($dept['total_absences'] ?? 0) * 2);

    return max(0, $attendance_score - $lateness_penalty - $absence_penalty);
}

function assessDepartmentRisk($dept) {
    $score = calculateDepartmentScore($dept);

    if ($score >= 80) return 'low';
    if ($score >= 60) return 'medium';
    return 'high';
}

function generateDepartmentRecommendations($dept) {
    $recommendations = [];

    if (($dept['avg_attendance_rate'] ?? 0) < 85) {
        $recommendations[] = "Improve attendance through engagement programs";
    }

    if (($dept['avg_lateness'] ?? 0) > 10) {
        $recommendations[] = "Address lateness issues with flexible scheduling";
    }

    if (($dept['total_absences'] ?? 0) > 5) {
        $recommendations[] = "Investigate causes of high absenteeism";

    }

    return $recommendations;
}

function calculateEmployeeRiskScore($employee) {
    $attendance_rate = ($employee['hadir_count'] / max(1, $employee['hadir_count'] + $employee['tidak_hadir_count'])) * 100;
    $lateness_score = min(40, ($employee['terlambat_count'] ?? 0) * 5);
    $absence_score = min(30, ($employee['tidak_hadir_count'] ?? 0) * 10);

    $base_score = 100 - $attendance_rate;
    return min(100, $base_score + $lateness_score + $absence_score);
}

function identifyRiskFactors($employee) {
    $factors = [];

    $attendance_rate = ($employee['hadir_count'] / max(1, $employee['hadir_count'] + $employee['tidak_hadir_count'])) * 100;

    if ($attendance_rate < 80) {
        $factors[] = 'Low attendance rate';
    }

    if (($employee['terlambat_count'] ?? 0) > 3) {
        $factors[] = 'Frequent lateness';
    }

    if (($employee['tidak_hadir_count'] ?? 0) > 2) {
        $factors[] = 'High absenteeism';
    }

    return $factors;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overview Kinerja - KAORI Indonesia</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .overview-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .overview-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-icon {
            font-size: 2em;
            margin-right: 15px;
            opacity: 0.7;
        }

        .card-title {
            font-size: 1.1em;
            font-weight: bold;
            color: #333;
            margin: 0;
        }

        .card-value {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .card-subtitle {
            color: #666;
            font-size: 0.9em;
        }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }

        .performance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .performance-table th,
        .performance-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .performance-table th {
            background: #f8f9fa;
            font-weight: bold;
        }

        .status-good { color: #4CAF50; }
        .status-warning { color: #FF9800; }
        .status-danger { color: #f44336; }

        .department-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .department-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .department-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .department-stats {
            font-size: 0.9em;
            color: #666;
        }

        /* AI Insights Styles */
        .ai-insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .insight-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .insight-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 1.1em;
        }

        .insight-content {
            color: #666;
            line-height: 1.6;
            font-size: 0.9em;
        }

        /* Prediction Styles */
        .prediction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .prediction-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #e9ecef;
        }

        .prediction-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .prediction-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .prediction-value.increasing { color: #28a745; }
        .prediction-value.decreasing { color: #dc3545; }
        .prediction-value.stable { color: #ffc107; }

        .prediction-subtitle {
            color: #999;
            font-size: 0.8em;
        }

        /* Risk Assessment Styles */
        .risk-table-container {
            overflow-x: auto;
        }

        .risk-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }

        .risk-low { background: #d4edda; color: #155724; }
        .risk-medium { background: #fff3cd; color: #856404; }
        .risk-high { background: #f8d7da; color: #721c24; }

        .risk-factors {
            margin: 0;
            padding-left: 15px;
        }

        .risk-factors li {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 2px;
        }

        /* Recommendations Styles */
        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .recommendation-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }

        .recommendation-card h3 {
            margin: 0 0 15px 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .performance-score {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            text-align: center;
        }

        .risk-level {
            margin-bottom: 15px;
            text-align: center;
        }

        .recommendations-list ul {
            margin: 10px 0 0 0;
            padding-left: 20px;
        }

        .recommendations-list li {
            margin-bottom: 8px;
            color: #666;
            line-height: 1.4;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .ai-insights-grid,
            .prediction-grid,
            .recommendations-grid {
                grid-template-columns: 1fr;
            }

            .prediction-card {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-title">📊 Overview Kinerja Karyawan</div>
    <div class="subtitle-container">
        <p class="subtitle">Dashboard analisis kinerja dan statistik karyawan KAORI Indonesia</p>
    </div>

    <div class="content-container">

        <!-- Key Metrics -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-label">Total Karyawan</div>
                <div class="stat-value"><?php echo $overview_data['total_employees']; ?></div>
                <div class="stat-label">Karyawan aktif</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-label">Shift Aktif Hari Ini</div>
                <div class="stat-value"><?php echo $overview_data['active_shifts_today']; ?></div>
                <div class="stat-label">Shift yang disetujui</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Hadir Hari Ini</div>
                <div class="stat-value"><?php echo $overview_data['attendance_today']['hadir']; ?></div>
                <div class="stat-label">Karyawan hadir</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-label">Menunggu Konfirmasi</div>
                <div class="stat-value"><?php echo $overview_data['pending_confirmations']; ?></div>
                <div class="stat-label">Shift pending approval</div>
            </div>
        </div>

        <!-- Attendance Chart -->
        <div class="content-container">
            <h2>📊 Status Absensi Hari Ini</h2>
            <div style="width: 100%; max-width: 400px; margin: 0 auto;">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- Monthly Summary -->
        <div class="content-container">
            <h2>📅 Ringkasan Bulan Ini</h2>
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-label">Total Hadir</div>
                    <div class="stat-value"><?php echo $overview_data['monthly_summary']['total_hadir']; ?></div>
                    <div class="stat-label">Hari kerja</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">❌</div>
                    <div class="stat-label">Tidak Hadir</div>
                    <div class="stat-value"><?php echo $overview_data['monthly_summary']['total_tidak_hadir']; ?></div>
                    <div class="stat-label">Tanpa keterangan</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">📄</div>
                    <div class="stat-label">Izin/Sakit</div>
                    <div class="stat-value">
                        <?php echo $overview_data['monthly_summary']['total_izin'] + $overview_data['monthly_summary']['total_sakit']; ?>
                    </div>
                    <div class="stat-label">Dengan keterangan</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">⏰</div>
                    <div class="stat-label">Rata-rata Keterlambatan</div>
                    <div class="stat-value">
                        <?php echo $overview_data['monthly_summary']['avg_terlambat'] ? $overview_data['monthly_summary']['avg_terlambat'] : 0; ?>
                    </div>
                    <div class="stat-label">Menit</div>
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="content-container">
            <h2>🏆 Top Performers Bulan Ini</h2>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Hadir</th>
                            <th>Terlambat</th>
                            <th>Tingkat Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overview_data['top_performers'] as $performer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($performer['nama_lengkap']); ?></td>
                            <td><?php echo $performer['hadir']; ?> hari</td>
                            <td><?php echo $performer['terlambat']; ?> kali</td>
                            <td>
                                <span class="badge badge-success"><?php echo $performer['persentase_kehadiran']; ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Department Performance -->
        <div class="content-container">
            <h2>🏢 Kinerja per Cabang</h2>
            <div class="dashboard-grid">
                <?php foreach ($overview_data['department_stats'] as $dept): ?>
                <div class="stat-card">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-label"><?php echo htmlspecialchars($dept['department']); ?></div>
                    <div class="stat-value"><?php echo $dept['attendance_rate']; ?>%</div>
                    <div class="stat-label"><?php echo $dept['hadir_today']; ?>/<?php echo $dept['total_employees']; ?> hadir</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- AI-Powered Insights -->
        <div class="chart-container">
            <h2><i class="fas fa-brain"></i> AI-Powered Insights</h2>
            <div class="ai-insights-grid">
                <div class="insight-card">
                    <h3><i class="fas fa-chart-line"></i> Attendance Trends</h3>
                    <div class="insight-content">
                        <?php echo nl2br(htmlspecialchars($overview_data['ai_insights']['attendance_trends'] ?? 'AI analysis in progress...')); ?>
                    </div>
                </div>

                <div class="insight-card">
                    <h3><i class="fas fa-users"></i> Performance Analysis</h3>
                    <div class="insight-content">
                        <?php echo nl2br(htmlspecialchars($overview_data['ai_insights']['performance_insights'] ?? 'AI analysis in progress...')); ?>
                    </div>
                </div>

                <div class="insight-card">
                    <h3><i class="fas fa-building"></i> Department Insights</h3>
                    <div class="insight-content">
                        <?php echo nl2br(htmlspecialchars($overview_data['ai_insights']['department_analysis'] ?? 'AI analysis in progress...')); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Predictions -->
        <div class="chart-container">
            <h2><i class="fas fa-crystal-ball"></i> Performance Predictions</h2>
            <div class="prediction-grid">
                <div class="prediction-card">
                    <h3>Attendance Trend</h3>
                    <div class="prediction-value <?php echo $overview_data['performance_predictions']['attendance_trend'] ?? 'stable'; ?>">
                        <?php
                        $trend = $overview_data['performance_predictions']['attendance_trend'] ?? 'stable';
                        echo ucfirst($trend);
                        ?>
                        <?php if ($trend === 'increasing'): ?>
                            <i class="fas fa-arrow-up"></i>
                        <?php elseif ($trend === 'decreasing'): ?>
                            <i class="fas fa-arrow-down"></i>
                        <?php else: ?>
                            <i class="fas fa-minus"></i>
                        <?php endif; ?>
                    </div>
                    <div class="prediction-subtitle">Next 7 days trend</div>
                </div>

                <div class="prediction-card">
                    <h3>Predicted Attendance</h3>
                    <div class="prediction-value">
                        <?php echo $overview_data['performance_predictions']['predicted_attendance'] ?? 'N/A'; ?>
                    </div>
                    <div class="prediction-subtitle">Average daily attendance</div>
                </div>

                <div class="prediction-card">
                    <h3>At-Risk Employees</h3>
                    <div class="prediction-value risk-high">
                        <?php echo count($overview_data['performance_predictions']['at_risk_employees'] ?? []); ?>
                    </div>
                    <div class="prediction-subtitle">Need attention</div>
                </div>
            </div>
        </div>

        <!-- Employee Risk Assessment -->
        <div class="chart-container">
            <h2><i class="fas fa-exclamation-triangle"></i> Employee Risk Assessment</h2>
            <div class="risk-table-container">
                <table class="performance-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Risk Score</th>
                            <th>Risk Level</th>
                            <th>Risk Factors</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($overview_data['employee_risk_assessment'] ?? []) as $risk): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($risk['name']); ?></td>
                            <td><?php echo htmlspecialchars($risk['department']); ?></td>
                            <td><?php echo $risk['risk_score']; ?>%</td>
                            <td>
                                <span class="risk-badge risk-<?php echo $risk['risk_level']; ?>">
                                    <?php echo ucfirst($risk['risk_level']); ?>
                                </span>
                            </td>
                            <td>
                                <ul class="risk-factors">
                                    <?php foreach ($risk['factors'] as $factor): ?>
                                    <li><?php echo htmlspecialchars($factor); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Department Recommendations -->
        <div class="chart-container">
            <h2><i class="fas fa-lightbulb"></i> Department Recommendations</h2>
            <div class="recommendations-grid">
                <?php foreach (($overview_data['department_trends'] ?? []) as $dept_name => $trends): ?>
                <div class="recommendation-card">
                    <h3><?php echo htmlspecialchars($dept_name); ?></h3>
                    <div class="performance-score">
                        Performance Score: <strong><?php echo round($trends['performance_score'], 1); ?>/100</strong>
                    </div>
                    <div class="risk-level">
                        Risk Level: <span class="risk-badge risk-<?php echo $trends['risk_level']; ?>">
                            <?php echo ucfirst($trends['risk_level']); ?>
                        </span>
                    </div>
                    <div class="recommendations-list">
                        <strong>Recommendations:</strong>
                        <ul>
                            <?php foreach ($trends['recommendations'] as $rec): ?>
                            <li><?php echo htmlspecialchars($rec); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <script>
        // Attendance Chart
        const attendanceData = <?php echo json_encode($overview_data['attendance_today']); ?>;
        const ctx = document.getElementById('attendanceChart').getContext('2d');

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Hadir', 'Tidak Hadir', 'Izin', 'Sakit'],
                datasets: [{
                    data: [
                        attendanceData.hadir,
                        attendanceData.tidak_hadir,
                        attendanceData.izin,
                        attendanceData.sakit
                    ],
                    backgroundColor: [
                        '#4CAF50',
                        '#f44336',
                        '#FF9800',
                        '#9C27B0'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>

</body>
</html>