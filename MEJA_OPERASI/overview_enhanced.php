<?php
// Enhanced Overview with AI-Powered Analytics
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$user_name = $_SESSION['nama_lengkap'] ?? $_SESSION['username'];

// Get AI-powered insights
$ai_insights = [];
try {
    // Get general summary
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/api_ai_overview.php?action=generate_summary&type=general');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    if ($response) {
        $result = json_decode($response, true);
        if ($result['status'] === 'success') {
            $ai_insights['general'] = $result;
        }
    }
    curl_close($ch);

    // Get department insights
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/api_ai_overview.php?action=generate_summary&type=department');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    if ($response) {
        $result = json_decode($response, true);
        if ($result['status'] === 'success') {
            $ai_insights['department'] = $result;
        }
    }
    curl_close($ch);

} catch (Exception $e) {
    // Fallback if AI service not available
    $ai_insights = null;
}

// Get traditional overview data (fallback)
$overview_data = [];
try {
    // Total employees
    $sql_total_employees = "SELECT COUNT(*) as total FROM register WHERE role = 'user'";
    $stmt = $pdo->query($sql_total_employees);
    $overview_data['total_employees'] = $stmt->fetchColumn();

    // Active shifts today
    $sql_active_shifts = "SELECT COUNT(DISTINCT sa.user_id) as total
                      FROM shift_assignments sa
                      JOIN cabang c ON sa.cabang_id = c.id
                      WHERE sa.tanggal_shift = CURDATE()
                      AND sa.status_konfirmasi = 'approved'";
    $stmt = $pdo->prepare($sql_active_shifts);
    $stmt->execute();
    $overview_data['active_shifts_today'] = $stmt->fetchColumn();

    // Attendance today
    $sql_attendance_today = "SELECT
        COUNT(CASE WHEN status_kehadiran = 'Hadir' THEN 1 END) as hadir,
        COUNT(CASE WHEN status_kehadiran = 'Tidak Hadir' THEN 1 END) as tidak_hadir,
        COUNT(CASE WHEN status_kehadiran = 'Izin' THEN 1 END) as izin,
        COUNT(CASE WHEN status_kehadiran = 'Sakit' THEN 1 END) as sakit
        FROM absensi WHERE tanggal_absensi = CURDATE()";
    $stmt = $pdo->query($sql_attendance_today);
    $attendance_today = $stmt->fetch(PDO::FETCH_ASSOC);
    $overview_data['attendance_today'] = $attendance_today;

    // Pending confirmations
    $sql_pending_confirmations = "SELECT COUNT(*) as total FROM shift_assignments
                              WHERE status_konfirmasi = 'pending'
                              AND tanggal_shift >= CURDATE()";
    $stmt = $pdo->query($sql_pending_confirmations);
    $overview_data['pending_confirmations'] = $stmt->fetchColumn();

    // Top performers this month
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

    // Monthly summary
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

    // Department performance
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

} catch (Exception $e) {
    // Handle database errors gracefully
    $overview_data = [];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI-Powered Overview - KAORI Indonesia</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .ai-insights {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .ai-insights h3 {
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ai-insights h3 i {
            color: #ffd700;
        }

        .insight-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #ffd700;
        }

        .recommendation-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #4CAF50;
        }

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

        .ai-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 50px;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .ai-toggle:hover {
            background: #5a67d8;
        }

        .ai-panel {
            position: fixed;
            top: 70px;
            right: 20px;
            width: 350px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 20px;
            z-index: 999;
            display: none;
            max-height: 70vh;
            overflow-y: auto;
        }

        .ai-panel h4 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .trend-indicator {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 10px;
        }

        .trend-up { background: #d4edda; color: #155724; }
        .trend-down { background: #f8d7da; color: #721c24; }
        .trend-stable { background: #fff3cd; color: #856404; }

        .performance-score {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 10px 0;
        }

        .performance-score .score {
            font-size: 2em;
            font-weight: bold;
            margin: 5px 0;
        }

        .performance-score .label {
            font-size: 0.9em;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="main-title">🤖 AI-Powered Overview</div>
    <div class="subtitle-container">
        <p class="subtitle">Dashboard cerdas dengan analisis AI untuk KAORI Indonesia</p>
    </div>

    <!-- AI Toggle Button -->
    <button class="ai-toggle" onclick="toggleAIPanel()">
        <i class="fas fa-robot"></i> AI Insights
    </button>

    <!-- AI Insights Panel -->
    <div class="ai-panel" id="aiPanel">
        <h4><i class="fas fa-brain"></i> AI-Powered Insights</h4>

        <?php if ($ai_insights && isset($ai_insights['general'])): ?>
            <div class="ai-insights">
                <h3><i class="fas fa-chart-line"></i> Ringkasan AI</h3>
                <p><?php echo htmlspecialchars($ai_insights['general']['summary']); ?></p>

                <?php if (!empty($ai_insights['general']['insights'])): ?>
                    <h4>Key Insights:</h4>
                    <?php foreach ($ai_insights['general']['insights'] as $insight): ?>
                        <div class="insight-item">
                            <i class="fas fa-lightbulb"></i> <?php echo htmlspecialchars($insight); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($ai_insights['general']['recommendations'])): ?>
                    <h4>Rekomendasi AI:</h4>
                    <?php foreach ($ai_insights['general']['recommendations'] as $rec): ?>
                        <div class="recommendation-item">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($rec); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="ai-insights">
                <h3><i class="fas fa-exclamation-triangle"></i> AI Service Unavailable</h3>
                <p>AI insights sementara tidak tersedia. Menampilkan data tradisional.</p>
            </div>
        <?php endif; ?>

        <?php if ($ai_insights && isset($ai_insights['department'])): ?>
            <div class="ai-insights">
                <h3><i class="fas fa-building"></i> Analisis Departemen</h3>
                <p><?php echo htmlspecialchars($ai_insights['department']['summary']); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="content-container">

        <!-- Key Metrics -->
        <div class="overview-grid">
            <div class="overview-card">
                <div class="card-header">
                    <div class="card-icon">👥</div>
                    <h3 class="card-title">Total Karyawan</h3>
                </div>
                <div class="card-value"><?php echo $overview_data['total_employees'] ?? 0; ?></div>
                <div class="card-subtitle">Karyawan aktif</div>
            </div>

            <div class="overview-card">
                <div class="card-header">
                    <div class="card-icon">📅</div>
                    <h3 class="card-title">Shift Aktif Hari Ini</h3>
                </div>
                <div class="card-value"><?php echo $overview_data['active_shifts_today'] ?? 0; ?></div>
                <div class="card-subtitle">Shift yang disetujui</div>
            </div>

            <div class="overview-card">
                <div class="card-header">
                    <div class="card-icon">✅</div>
                    <h3 class="card-title">Hadir Hari Ini</h3>
                </div>
                <div class="card-value status-good"><?php echo $overview_data['attendance_today']['hadir'] ?? 0; ?></div>
                <div class="card-subtitle">Karyawan hadir</div>
            </div>

            <div class="overview-card">
                <div class="card-header">
                    <div class="card-icon">⏳</div>
                    <h3 class="card-title">Menunggu Konfirmasi</h3>
                </div>
                <div class="card-value status-warning"><?php echo $overview_data['pending_confirmations'] ?? 0; ?></div>
                <div class="card-subtitle">Shift pending approval</div>
            </div>
        </div>

        <!-- Attendance Chart -->
        <div class="chart-container">
            <h2><i class="fas fa-chart-pie"></i> Status Absensi Hari Ini</h2>
            <div style="width: 100%; max-width: 400px; margin: 0 auto;">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>

        <!-- Monthly Summary with AI Insights -->
        <div class="chart-container">
            <h2><i class="fas fa-calendar-alt"></i> Ringkasan Bulan Ini
                <?php
                $monthly = $overview_data['monthly_summary'] ?? [];
                $attendance_rate = ($monthly['total_hadir'] ?? 0) > 0 ?
                    round((($monthly['total_hadir'] ?? 0) / (($monthly['total_hadir'] ?? 0) + ($monthly['total_tidak_hadir'] ?? 0))) * 100, 1) : 0;

                $trend_class = 'trend-stable';
                $trend_text = 'Stabil';
                if ($attendance_rate >= 90) {
                    $trend_class = 'trend-up';
                    $trend_text = 'Sangat Baik';
                } elseif ($attendance_rate < 70) {
                    $trend_class = 'trend-down';
                    $trend_text = 'Perlu Perhatian';
                }
                ?>
                <span class="trend-indicator <?php echo $trend_class; ?>"><?php echo $trend_text; ?></span>
            </h2>

            <div class="performance-score">
                <div class="label">Tingkat Kehadiran Bulan Ini</div>
                <div class="score"><?php echo $attendance_rate; ?>%</div>
            </div>

            <div class="overview-grid">
                <div class="overview-card">
                    <div class="card-header">
                        <h3 class="card-title">Total Hadir</h3>
                    </div>
                    <div class="card-value status-good"><?php echo $monthly['total_hadir'] ?? 0; ?></div>
                    <div class="card-subtitle">Hari kerja</div>
                </div>

                <div class="overview-card">
                    <div class="card-header">
                        <h3 class="card-title">Tidak Hadir</h3>
                    </div>
                    <div class="card-value status-danger"><?php echo $monthly['total_tidak_hadir'] ?? 0; ?></div>
                    <div class="card-subtitle">Tanpa keterangan</div>
                </div>

                <div class="overview-card">
                    <div class="card-header">
                        <h3 class="card-title">Izin/Sakit</h3>
                    </div>
                    <div class="card-value status-warning">
                        <?php echo (($monthly['total_izin'] ?? 0) + ($monthly['total_sakit'] ?? 0)); ?>
                    </div>
                    <div class="card-subtitle">Dengan keterangan</div>
                </div>

                <div class="overview-card">
                    <div class="card-header">
                        <h3 class="card-title">Rata-rata Keterlambatan</h3>
                    </div>
                    <div class="card-value">
                        <?php echo $monthly['avg_terlambat'] ? $monthly['avg_terlambat'] : 0; ?>
                    </div>
                    <div class="card-subtitle">Menit</div>
                </div>
            </div>
        </div>

        <!-- Top Performers with AI Ranking -->
        <div class="chart-container">
            <h2><i class="fas fa-trophy"></i> AI-Ranked Top Performers Bulan Ini</h2>
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Peringkat</th>
                        <th>Nama</th>
                        <th>Hadir</th>
                        <th>Terlambat</th>
                        <th>Tingkat Kehadiran</th>
                        <th>AI Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1;
                    foreach (($overview_data['top_performers'] ?? []) as $performer):
                        // Calculate AI score (simple algorithm)
                        $attendance_score = min(100, $performer['persentase_kehadiran']);
                        $lateness_penalty = max(0, 100 - ($performer['terlambat'] * 5)); // 5 points per lateness
                        $ai_score = round(($attendance_score * 0.7) + ($lateness_penalty * 0.3), 1);

                        $score_class = $ai_score >= 90 ? 'status-good' : ($ai_score >= 70 ? 'status-warning' : 'status-danger');
                    ?>
                    <tr>
                        <td><strong><?php echo $rank++; ?></strong></td>
                        <td><?php echo htmlspecialchars($performer['nama_lengkap']); ?></td>
                        <td><?php echo $performer['hadir']; ?> hari</td>
                        <td><?php echo $performer['terlambat']; ?> kali</td>
                        <td>
                            <span class="status-good"><?php echo $performer['persentase_kehadiran']; ?>%</span>
                        </td>
                        <td>
                            <span class="<?php echo $score_class; ?>"><strong><?php echo $ai_score; ?>/100</strong></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Department Performance with AI Insights -->
        <div class="chart-container">
            <h2><i class="fas fa-building"></i> AI-Analyzed Department Performance</h2>
            <div class="department-grid">
                <?php foreach (($overview_data['department_stats'] ?? []) as $dept):
                    $performance_level = $dept['attendance_rate'] >= 90 ? 'Excellent' :
                                       ($dept['attendance_rate'] >= 80 ? 'Good' :
                                       ($dept['attendance_rate'] >= 70 ? 'Fair' : 'Needs Attention'));
                    $performance_color = $dept['attendance_rate'] >= 90 ? '#4CAF50' :
                                       ($dept['attendance_rate'] >= 80 ? '#FF9800' : '#f44336');
                ?>
                <div class="department-card">
                    <div class="department-name" style="color: <?php echo $performance_color; ?>">
                        <?php echo htmlspecialchars($dept['department']); ?>
                        <span style="font-size: 0.8em; background: <?php echo $performance_color; ?>; color: white; padding: 2px 6px; border-radius: 10px; margin-left: 8px;">
                            <?php echo $performance_level; ?>
                        </span>
                    </div>
                    <div class="department-stats">
                        <div>Total Karyawan: <?php echo $dept['total_employees']; ?></div>
                        <div>Hadir Hari Ini: <?php echo $dept['hadir_today']; ?></div>
                        <div>Tingkat Kehadiran: <span class="status-good"><?php echo $dept['attendance_rate']; ?>%</span></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- AI Predictions Section -->
        <div class="chart-container">
            <h2><i class="fas fa-crystal-ball"></i> AI Trend Predictions</h2>
            <div class="overview-grid">
                <div class="overview-card">
                    <div class="card-header">
                        <h3 class="card-title">Prediksi Kehadiran Bulan Depan</h3>
                    </div>
                    <div class="card-value status-good">~<?php echo $attendance_rate; ?>%</div>
                    <div class="card-subtitle">Berdasarkan pola historis</div>
                </div>

                <div class="overview-card">
                    <div class="card-header">
                        <h3 class="card-title">Risiko Absensi</h3>
                    </div>
                    <div class="card-value status-warning">
                        <?php
                        $risk_level = $attendance_rate < 80 ? 'Tinggi' :
                                    ($attendance_rate < 90 ? 'Sedang' : 'Rendah');
                        echo $risk_level;
                        ?>
                    </div>
                    <div class="card-subtitle">Level risiko berdasarkan data</div>
                </div>

                <div class="overview-card">
                    <div class="card-header">
                        <h3 class="card-title">Rekomendasi AI</h3>
                    </div>
                    <div class="card-value">
                        <?php
                        $recommendation = $attendance_rate < 85 ? 'Perlu Intervensi' : 'Pertahankan';
                        echo $recommendation;
                        ?>
                    </div>
                    <div class="card-subtitle">Action items untuk HR</div>
                </div>
            </div>
        </div>

    </div>

    <script>
        // Toggle AI Panel
        function toggleAIPanel() {
            const panel = document.getElementById('aiPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }

        // Close AI panel when clicking outside
        document.addEventListener('click', function(event) {
            const panel = document.getElementById('aiPanel');
            const toggle = document.querySelector('.ai-toggle');

            if (!panel.contains(event.target) && !toggle.contains(event.target)) {
                panel.style.display = 'none';
            }
        });

        // Attendance Chart
        const attendanceData = <?php echo json_encode($overview_data['attendance_today'] ?? ['hadir' => 0, 'tidak_hadir' => 0, 'izin' => 0, 'sakit' => 0]); ?>;
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

        // Auto-refresh data every 5 minutes
        setInterval(function() {
            // In a real implementation, you would refresh the data here
            console.log('AI Overview: Auto-refresh triggered');
        }, 300000); // 5 minutes
    </script>

</body>
</html>