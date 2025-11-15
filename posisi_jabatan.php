<?php
/**
 * ENHANCED POSITION/JOB TITLE MANAGEMENT FOR SUPERADMIN
 *
 * Features:
 * - Complete CRUD operations for positions
 * - Role assignment and management
 * - Bulk operations and data integrity
 * - Advanced validation and error handling
 * - Audit logging and activity tracking
 * - Modern UI with responsive design
 * - Real-time statistics and analytics
 */

ob_start();
session_start();
include 'connect.php';
include 'functions_role.php';

// Security: Only superadmin can access
if (!isset($_SESSION['user_id']) || !isSuperadmin($_SESSION['role'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Initialize variables
$errors = [];
$success = [];
$edit_position = null;
$stats = [];

// Generate CSRF token
if (!isset($_SESSION['csrf_token_positions'])) {
    $_SESSION['csrf_token_positions'] = bin2hex(random_bytes(32));
}

// Get position data for editing
if (isset($_GET['edit']) && filter_var($_GET['edit'], FILTER_VALIDATE_INT)) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT * FROM posisi_jabatan WHERE id = ?');
    $stmt->execute([$edit_id]);
    $edit_position = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle success messages
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'add':
            $success[] = 'Posisi berhasil ditambahkan!';
            break;
        case 'update':
            $success[] = 'Posisi dan role berhasil diupdate!';
            break;
        case 'delete':
            $position_name = $_GET['name'] ?? 'tersebut';
            $success[] = "Posisi '$position_name' berhasil dihapus! Semua pegawai dengan posisi ini telah dipindahkan ke 'Tidak Ada Posisi'.";
            break;
        case 'bulk_update':
            $success[] = 'Bulk update posisi berhasil!';
            break;
    }
}

// Get statistics
try {
    // Total positions
    $stmt = $pdo->query("SELECT COUNT(*) FROM posisi_jabatan");
    $stats['total_positions'] = $stmt->fetchColumn();

    // Positions by role
    $stmt = $pdo->prepare("SELECT role_posisi, COUNT(*) as count FROM posisi_jabatan GROUP BY role_posisi");
    $stmt->execute();
    $role_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $stats['admin_positions'] = $role_counts['admin'] ?? 0;
    $stats['user_positions'] = $role_counts['user'] ?? 0;

    // Employees using each position
    $stmt = $pdo->query("
        SELECT pj.nama_posisi, pj.role_posisi,
               COUNT(DISTINCT COALESCE(r.id, pw.id)) as total_employees
        FROM posisi_jabatan pj
        LEFT JOIN register r ON pj.nama_posisi = r.posisi
        LEFT JOIN pegawai_whitelist pw ON pj.nama_posisi = pw.posisi
        GROUP BY pj.id, pj.nama_posisi, pj.role_posisi
        ORDER BY pj.nama_posisi
    ");
    $stats['position_usage'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errors[] = "Error loading statistics: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token_positions']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'save';

        if ($action === 'save') {
            handlePositionSave();
        } elseif ($action === 'bulk_update') {
            handleBulkUpdate();
        } elseif ($action === 'delete') {
            handlePositionDelete();
        }
    }
}

function handlePositionSave() {
    global $pdo, $errors, $success;

    $position_name = trim($_POST['nama_posisi'] ?? '');
    $position_role = trim($_POST['role_posisi'] ?? 'user');
    $position_id = isset($_POST['id_posisi']) ? intval($_POST['id_posisi']) : null;
    $description = trim($_POST['description'] ?? '');

    // Validation
    if (empty($position_name)) {
        $errors[] = 'Nama posisi tidak boleh kosong.';
        return;
    }

    if (!in_array($position_role, ['user', 'admin'])) {
        $errors[] = 'Role posisi harus user atau admin.';
        return;
    }

    // Check for duplicates
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM posisi_jabatan WHERE nama_posisi = ?' . ($position_id ? ' AND id != ?' : ''));
    $params = $position_id ? [$position_name, $position_id] : [$position_name];
    $stmt->execute($params);

    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Nama posisi sudah ada.';
        return;
    }

    try {
        $pdo->beginTransaction();

        if ($position_id) {
            // Update existing position
            $stmt = $pdo->prepare('SELECT * FROM posisi_jabatan WHERE id = ?');
            $stmt->execute([$position_id]);
            $old_position = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old_position) {
                $errors[] = 'Posisi tidak ditemukan.';
                $pdo->rollBack();
                return;
            }

            // Update position
            $stmt = $pdo->prepare('UPDATE posisi_jabatan SET nama_posisi = ?, role_posisi = ?, description = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$position_name, $position_role, $description, $position_id]);

            // Update all employees with this position
            $stmt = $pdo->prepare('UPDATE pegawai_whitelist SET posisi = ?, role = ? WHERE posisi = ?');
            $stmt->execute([$position_name, $position_role, $old_position['nama_posisi']]);

            $stmt = $pdo->prepare('UPDATE register SET posisi = ?, role = ? WHERE posisi = ?');
            $stmt->execute([$position_name, $position_role, $old_position['nama_posisi']]);

            // Log activity
            logUserActivity($pdo, $_SESSION['user_id'], 'position_update', "Updated position: {$old_position['nama_posisi']} -> $position_name");

            $pdo->commit();
            header('Location: posisi_jabatan.php?success=update');
            exit;

        } else {
            // Create new position
            $stmt = $pdo->prepare('INSERT INTO posisi_jabatan (nama_posisi, role_posisi, description, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
            $stmt->execute([$position_name, $position_role, $description]);

            // Log activity
            logUserActivity($pdo, $_SESSION['user_id'], 'position_create', "Created new position: $position_name");

            $pdo->commit();
            header('Location: posisi_jabatan.php?success=add');
            exit;
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

function handleBulkUpdate() {
    global $pdo, $errors, $success;

    $updates = $_POST['bulk_updates'] ?? [];

    if (empty($updates)) {
        $errors[] = 'Tidak ada data untuk diupdate.';
        return;
    }

    try {
        $pdo->beginTransaction();
        $updated_count = 0;

        foreach ($updates as $position_id => $data) {
            $position_id = intval($position_id);
            $new_role = trim($data['role'] ?? '');

            if (!in_array($new_role, ['user', 'admin'])) {
                continue;
            }

            // Get current position data
            $stmt = $pdo->prepare('SELECT * FROM posisi_jabatan WHERE id = ?');
            $stmt->execute([$position_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($current && $current['role_posisi'] !== $new_role) {
                // Update position
                $stmt = $pdo->prepare('UPDATE posisi_jabatan SET role_posisi = ?, updated_at = NOW() WHERE id = ?');
                $stmt->execute([$new_role, $position_id]);

                // Update employees
                $stmt = $pdo->prepare('UPDATE pegawai_whitelist SET role = ? WHERE posisi = ?');
                $stmt->execute([$new_role, $current['nama_posisi']]);

                $stmt = $pdo->prepare('UPDATE register SET role = ? WHERE posisi = ?');
                $stmt->execute([$new_role, $current['nama_posisi']]);

                $updated_count++;
            }
        }

        $pdo->commit();

        if ($updated_count > 0) {
            logUserActivity($pdo, $_SESSION['user_id'], 'position_bulk_update', "Bulk updated $updated_count positions");
            header('Location: posisi_jabatan.php?success=bulk_update');
            exit;
        } else {
            $errors[] = 'Tidak ada perubahan yang dilakukan.';
        }

    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = 'Bulk update error: ' . $e->getMessage();
    }
}

function handlePositionDelete() {
    global $pdo, $errors, $success;

    $position_id = intval($_POST['position_id'] ?? 0);

    if (!$position_id) {
        $errors[] = 'ID posisi tidak valid.';
        return;
    }

    try {
        $pdo->beginTransaction();

        // Get position data
        $stmt = $pdo->prepare('SELECT * FROM posisi_jabatan WHERE id = ?');
        $stmt->execute([$position_id]);
        $position = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$position) {
            $errors[] = 'Posisi tidak ditemukan.';
            $pdo->rollBack();
            return;
        }

        // Prevent deletion of default position
        if ($position['nama_posisi'] === 'Tidak Ada Posisi') {
            $errors[] = 'Tidak bisa menghapus posisi default.';
            $pdo->rollBack();
            return;
        }

        // Move employees to default position
        $stmt = $pdo->prepare('UPDATE pegawai_whitelist SET posisi = ?, role = ? WHERE posisi = ?');
        $stmt->execute(['Tidak Ada Posisi', 'user', $position['nama_posisi']]);

        $stmt = $pdo->prepare('UPDATE register SET posisi = ?, role = ? WHERE posisi = ?');
        $stmt->execute(['Tidak Ada Posisi', 'user', $position['nama_posisi']]);

        // Delete position
        $stmt = $pdo->prepare('DELETE FROM posisi_jabatan WHERE id = ?');
        $stmt->execute([$position_id]);

        // Log activity
        logUserActivity($pdo, $_SESSION['user_id'], 'position_delete', "Deleted position: {$position['nama_posisi']}");

        $pdo->commit();
        header('Location: posisi_jabatan.php?success=delete&name=' . urlencode($position['nama_posisi']));
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        $errors[] = 'Delete error: ' . $e->getMessage();
    }
}

// Handle GET delete requests (legacy support)
if (isset($_GET['delete']) && filter_var($_GET['delete'], FILTER_VALIDATE_INT)) {
    $position_id = intval($_GET['delete']);

    // Simulate POST request for delete
    $_POST['action'] = 'delete';
    $_POST['position_id'] = $position_id;
    $_POST['csrf_token'] = $_SESSION['csrf_token_positions'];

    handlePositionDelete();
}

// Get all positions with usage statistics
try {
    $stmt = $pdo->query("
        SELECT pj.*,
               COALESCE(pj.description, '') as description,
               COUNT(DISTINCT COALESCE(r.id, pw.id)) as employee_count
        FROM posisi_jabatan pj
        LEFT JOIN register r ON pj.nama_posisi = r.posisi
        LEFT JOIN pegawai_whitelist pw ON pj.nama_posisi = pw.posisi
        GROUP BY pj.id, pj.nama_posisi, pj.role_posisi, pj.description, pj.created_at, pj.updated_at
        ORDER BY pj.nama_posisi ASC
    ");
    $positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error loading positions: " . $e->getMessage();
    $positions = [];
}

/**
 * Activity logging function
 */
function logUserActivity($pdo, $user_id, $action, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $description]);
    } catch (Exception $e) {
        error_log("Failed to log activity for user $user_id: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Posisi Jabatan - Superadmin</title>
    <link rel="stylesheet" href="style_modern.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">
    <style>
        .positions-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }

        .positions-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .positions-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .positions-list {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .position-form {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            margin-left: 10px;
        }

        .positions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .positions-table th,
        .positions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .positions-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .position-row:hover {
            background: #f8f9fa;
        }

        .role-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .role-admin {
            background: #dc3545;
            color: white;
        }

        .role-user {
            background: #28a745;
            color: white;
        }

        .employee-count {
            background: #667eea;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-edit {
            background: #ffc107;
            color: #212529;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
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

        .bulk-actions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        @media (max-width: 768px) {
            .positions-content {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="positions-container">
        <div class="positions-header">
            <h1><i class="fas fa-cogs"></i> Manajemen Posisi Jabatan</h1>
            <p>Kelola posisi dan role karyawan dalam sistem</p>
        </div>

        <!-- Display Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <ul>
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div style="font-size: 24px; color: #667eea; margin-bottom: 10px;">
                    <i class="fas fa-briefcase"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_positions']; ?></div>
                <div>Total Posisi</div>
            </div>

            <div class="stat-card">
                <div style="font-size: 24px; color: #28a745; margin-bottom: 10px;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo $stats['user_positions']; ?></div>
                <div>Posisi User</div>
            </div>

            <div class="stat-card">
                <div style="font-size: 24px; color: #dc3545; margin-bottom: 10px;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-value"><?php echo $stats['admin_positions']; ?></div>
                <div>Posisi Admin</div>
            </div>

            <div class="stat-card">
                <div style="font-size: 24px; color: #17a2b8; margin-bottom: 10px;">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="stat-value"><?php echo count($stats['position_usage']); ?></div>
                <div>Aktif Digunakan</div>
            </div>
        </div>

        <div class="positions-content">
            <!-- Positions List -->
            <div class="positions-list">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><i class="fas fa-list"></i> Daftar Posisi</h2>
                    <button type="button" class="btn-secondary" onclick="toggleBulkMode()">
                        <i class="fas fa-tasks"></i> Bulk Edit
                    </button>
                </div>

                <!-- Bulk Actions (Hidden by default) -->
                <div id="bulk-actions" class="bulk-actions" style="display: none;">
                    <h4>Bulk Update Role</h4>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token_positions']; ?>">
                        <input type="hidden" name="action" value="bulk_update">
                        <div id="bulk-updates-container"></div>
                        <button type="submit" class="btn-primary" style="margin-top: 10px;">
                            <i class="fas fa-save"></i> Update Selected
                        </button>
                    </form>
                </div>

                <table class="positions-table">
                    <thead>
                        <tr>
                            <th>Nama Posisi</th>
                            <th>Role</th>
                            <th>Karyawan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($positions as $position): ?>
                            <tr class="position-row">
                                <td>
                                    <strong><?php echo htmlspecialchars($position['nama_posisi']); ?></strong>
                                    <?php if (!empty($position['description'])): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($position['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $position['role_posisi']; ?>">
                                        <?php echo ucfirst($position['role_posisi']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="employee-count">
                                        <?php echo $position['employee_count']; ?> orang
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="editPosition(<?php echo $position['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <?php if ($position['nama_posisi'] !== 'Tidak Ada Posisi'): ?>
                                        <button class="btn-delete" onclick="deletePosition(<?php echo $position['id']; ?>, '<?php echo htmlspecialchars($position['nama_posisi']); ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Position Form -->
            <div class="position-form">
                <h2 id="form-title"><i class="fas fa-plus"></i> Tambah Posisi Baru</h2>

                <form method="POST" id="position-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token_positions']; ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id_posisi" id="position-id" value="">

                    <div class="form-group">
                        <label>Nama Posisi *</label>
                        <input type="text" name="nama_posisi" id="position-name" required
                               placeholder="Contoh: Barista Senior, Marketing Manager">
                    </div>

                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role_posisi" id="position-role" required>
                            <option value="user">User (Karyawan Biasa)</option>
                            <option value="admin">Admin (Manajemen)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea name="description" id="position-description" rows="3"
                                  placeholder="Jelaskan tanggung jawab dan kualifikasi posisi ini..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary" id="submit-btn">
                        <i class="fas fa-save"></i> Simpan Posisi
                    </button>

                    <button type="button" class="btn-secondary" onclick="resetForm()" style="margin-top: 10px;">
                        <i class="fas fa-times"></i> Batal
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let bulkMode = false;

        function editPosition(id) {
            // Fetch position data and populate form
            fetch(`api_get_position.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Posisi';
                        document.getElementById('position-id').value = data.position.id;
                        document.getElementById('position-name').value = data.position.nama_posisi;
                        document.getElementById('position-role').value = data.position.role_posisi;
                        document.getElementById('position-description').value = data.position.description || '';
                        document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Update Posisi';

                        // Scroll to form
                        document.querySelector('.position-form').scrollIntoView({ behavior: 'smooth' });
                    }
                })
                .catch(error => {
                    alert('Error loading position data: ' + error.message);
                });
        }

        function deletePosition(id, name) {
            if (confirm(`Apakah Anda yakin ingin menghapus posisi "${name}"?\n\nSemua karyawan dengan posisi ini akan dipindahkan ke "Tidak Ada Posisi".`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token_positions']; ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="position_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function resetForm() {
            document.getElementById('form-title').innerHTML = '<i class="fas fa-plus"></i> Tambah Posisi Baru';
            document.getElementById('position-form').reset();
            document.getElementById('position-id').value = '';
            document.getElementById('submit-btn').innerHTML = '<i class="fas fa-save"></i> Simpan Posisi';
        }

        function toggleBulkMode() {
            bulkMode = !bulkMode;
            const bulkContainer = document.getElementById('bulk-actions');
            const bulkUpdatesContainer = document.getElementById('bulk-updates-container');

            if (bulkMode) {
                bulkContainer.style.display = 'block';

                // Generate bulk update checkboxes
                let html = '';
                <?php foreach ($positions as $position): ?>
                    if ('<?php echo $position['nama_posisi']; ?>' !== 'Tidak Ada Posisi') {
                        html += `
                            <div style="margin-bottom: 10px; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                                <label>
                                    <input type="checkbox" name="bulk_updates[<?php echo $position['id']; ?>][selected]" value="1">
                                    <strong><?php echo htmlspecialchars($position['nama_posisi']); ?></strong>
                                </label>
                                <select name="bulk_updates[<?php echo $position['id']; ?>][role]" style="margin-left: 10px; padding: 2px;">
                                    <option value="user" <?php echo $position['role_posisi'] === 'user' ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo $position['role_posisi'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                </select>
                            </div>
                        `;
                    }
                <?php endforeach; ?>

                bulkUpdatesContainer.innerHTML = html;
            } else {
                bulkContainer.style.display = 'none';
                bulkUpdatesContainer.innerHTML = '';
            }
        }

        // Prevent form resubmission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Form validation
        document.getElementById('position-form').addEventListener('submit', function(e) {
            const nameInput = document.getElementById('position-name');
            if (!nameInput.value.trim()) {
                alert('Nama posisi tidak boleh kosong.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>

<?php ob_end_flush(); ?>
