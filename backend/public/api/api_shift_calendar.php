<?php
session_start();
require_once 'connect.php';
header('Content-Type: application/json');

// Cek login
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Please login first']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_cabang':
            getCabang($pdo);
            break;

        case 'get_users':
            $cabang_id = $_GET['cabang_id'] ?? null;
            getUsers($pdo, $cabang_id);
            break;

        case 'get_shifts':
            $cabang_id = $_GET['cabang_id'] ?? null;
            $month = $_GET['month'] ?? null;
            $year = $_GET['year'] ?? null;
            getShifts($pdo, $cabang_id, $month, $year);
            break;

        case 'get_pegawai':
            $outlet = $_GET['outlet'] ?? null;
            getPegawai($pdo, $outlet);
            break;

        case 'get_assignments':
            $cabang_id = $_GET['cabang_id'] ?? null;
            $month = $_GET['month'] ?? null;
            $year = $_GET['year'] ?? null;
            getAssignments($pdo, $cabang_id, $month, $year);
            break;

        case 'get_summary':
            $cabang_id = $_GET['cabang_id'] ?? null;
            $month = $_GET['month'] ?? null;
            $year = $_GET['year'] ?? null;
            getSummary($pdo, $cabang_id, $month, $year);
            break;

        case 'save_shift':
            saveShift($pdo);
            break;

        case 'delete_shift':
            deleteShift($pdo);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getCabang($pdo) {
    $sql = "SELECT id, nama_cabang as nama, nama_cabang as nama_cabang FROM cabang_outlet ORDER BY nama_cabang";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $cabang = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cabang[] = [
            'id' => $row['id'],
            'nama' => $row['nama'],
            'nama_cabang' => $row['nama_cabang']
        ];
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Cabang loaded successfully',
        'data' => $cabang
    ]);
}

function getUsers($pdo, $cabang_id) {
    if (!$cabang_id) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'No cabang selected',
            'data' => []
        ]);
        return;
    }

    $sql = "SELECT r.id, r.nama_lengkap, r.email, r.role
            FROM register r
            JOIN cabang_outlet co ON r.outlet = co.nama_cabang
            WHERE co.id = ? AND r.role IN ('karyawan', 'admin')
            ORDER BY r.nama_lengkap";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cabang_id]);

    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id' => $row['id'],
            'name' => $row['nama_lengkap'],
            'email' => $row['email'],
            'role' => $row['role']
        ];
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Users loaded successfully',
        'data' => $users
    ]);
}

function getPegawai($pdo, $outlet) {
    if (!$outlet) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'No outlet specified',
            'data' => []
        ]);
        return;
    }

    $sql = "SELECT id, nama_lengkap, email, role
            FROM register 
            WHERE outlet = ? AND role IN ('karyawan', 'admin')
            ORDER BY nama_lengkap";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outlet]);

    $pegawai = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pegawai[] = [
            'id' => $row['id'],
            'name' => $row['nama_lengkap'],
            'email' => $row['email'],
            'role' => $row['role']
        ];
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Pegawai loaded successfully',
        'data' => $pegawai
    ]);
}

function getShifts($pdo, $cabang_id, $month, $year) {
    if (!$cabang_id || !$month || !$year) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Missing parameters: cabang_id=' . ($cabang_id ? 'OK' : 'MISSING') . ', month=' . ($month ? 'OK' : 'MISSING') . ', year=' . ($year ? 'OK' : 'MISSING'),
            'data' => []
        ]);
        return;
    }

    // Get available shifts for this cabang
    // First get the cabang name from ID
    $cabang_sql = "SELECT nama_cabang FROM cabang_outlet WHERE id = ?";
    $cabang_stmt = $pdo->prepare($cabang_sql);
    $cabang_stmt->execute([$cabang_id]);
    $cabang_row = $cabang_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cabang_row) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Cabang not found',
            'data' => []
        ]);
        return;
    }

    $cabang_name = $cabang_row['nama_cabang'];

    // Try multiple shift configuration tables
    $shifts = [];
    $queryOptions = [
        // Try cabang_outlet_shift table if exists
        "SELECT id, nama_shift, jam_masuk, jam_keluar, cabang_id FROM cabang_outlet_shift WHERE cabang_id = ? ORDER BY nama_shift",
        // Try cabang table
        "SELECT id, nama_shift, jam_masuk, jam_keluar, nama_cabang FROM cabang WHERE nama_cabang = ? ORDER BY nama_shift",
        // Try register table shifts
        "SELECT DISTINCT r.shift_type as nama_shift, r.shift_masuk as jam_masuk, r.shift_keluar as jam_keluar FROM register r WHERE r.outlet = ? AND r.shift_type IS NOT NULL"
    ];
    
    foreach ($queryOptions as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cabang_id]);
            $shifts = [];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Handle different field names
                $shiftId = $row['id'] ?? uniqid();
                $shiftName = $row['nama_shift'] ?? $row['shift_type'] ?? 'Shift';
                $jamMasuk = $row['jam_masuk'] ?? $row['shift_masuk'] ?? '08:00:00';
                $jamKeluar = $row['jam_keluar'] ?? $row['shift_keluar'] ?? '16:00:00';
                
                $shifts[] = [
                    'id' => $shiftId,
                    'nama_shift' => $shiftName,
                    'jam_masuk' => $jamMasuk,
                    'jam_keluar' => $jamKeluar,
                    'label' => $shiftName . ' (' . substr($jamMasuk, 0, 5) . ' - ' . substr($jamKeluar, 0, 5) . ')'
                ];
            }
            
            if (!empty($shifts)) break; // If we found shifts, stop trying other queries
            
        } catch (Exception $e) {
            // Continue to next query option
            continue;
        }
    }

    // If no shifts found, provide default shifts
    if (empty($shifts)) {
        $shifts[] = [
            'id' => 'pagi',
            'nama_shift' => 'Shift Pagi',
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '16:00:00',
            'label' => 'Shift Pagi (08:00 - 16:00)'
        ];
        $shifts[] = [
            'id' => 'siang',
            'nama_shift' => 'Shift Siang',
            'jam_masuk' => '16:00:00',
            'jam_keluar' => '00:00:00',
            'label' => 'Shift Siang (16:00 - 00:00)'
        ];
        $shifts[] = [
            'id' => 'malam',
            'nama_shift' => 'Shift Malam',
            'jam_masuk' => '00:00:00',
            'jam_keluar' => '08:00:00',
            'label' => 'Shift Malam (00:00 - 08:00)'
        ];
        $shifts[] = [
            'id' => 'off',
            'nama_shift' => 'Off',
            'jam_masuk' => '00:00:00',
            'jam_keluar' => '00:00:00',
            'label' => 'Off (-)'
        ];
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Shifts loaded successfully',
        'data' => $shifts
    ]);
}

function getAssignments($pdo, $cabang_id, $month = null, $year = null) {
    if (!$cabang_id) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'No cabang selected',
            'data' => []
        ]);
        return;
    }

    // Build the query
    $whereConditions = ["co.id = ?"];
    $params = [$cabang_id];

    if ($month && $year) {
        $whereConditions[] = "MONTH(sa.tanggal_shift) = ? AND YEAR(sa.tanggal_shift) = ?";
        $params[] = $month;
        $params[] = $year;
    }

    $whereClause = implode(" AND ", $whereConditions);

    $sql = "SELECT sa.id, sa.user_id, sa.tanggal_shift,
                   r.nama_lengkap, r.email, r.role,
                   co.nama_cabang,
                   c.nama_shift as shift_name,
                   c.jam_masuk, c.jam_keluar
            FROM shift_assignments sa
            JOIN register r ON sa.user_id = r.id
            JOIN cabang_outlet co ON r.outlet = co.nama_cabang
            LEFT JOIN cabang c ON r.outlet = c.nama_cabang
            WHERE $whereClause
            ORDER BY sa.tanggal_shift DESC, r.nama_lengkap";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $assignments = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $assignments[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'nama_lengkap' => $row['nama_lengkap'],
            'email' => $row['email'],
            'role' => $row['role'],
            'nama_shift' => $row['shift_name'] ?? 'Custom',
            'tanggal_shift' => $row['tanggal_shift'],
            'shift_masuk' => $row['jam_masuk'] ?? null,
            'shift_keluar' => $row['jam_keluar'] ?? null,
            'nama_cabang' => $row['nama_cabang']
        ];
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Assignments loaded successfully',
        'data' => $assignments
    ]);
}

function getSummary($pdo, $cabang_id, $month, $year) {
    if (!$cabang_id || !$month || !$year) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Missing parameters',
            'data' => []
        ]);
        return;
    }

    // Get summary data for each user
    $sql = "SELECT r.id, r.nama_lengkap,
                   COUNT(sa.id) as total_shifts,
                   COUNT(CASE WHEN sa.shift_masuk = c.shift_pagi_masuk THEN 1 END) as pagi_count,
                   COUNT(CASE WHEN sa.shift_masuk = c.shift_siang_masuk THEN 1 END) as siang_count,
                   COUNT(CASE WHEN sa.shift_masuk = c.shift_malam_masuk THEN 1 END) as malam_count
            FROM register r
            LEFT JOIN shift_assignments sa ON r.id = sa.user_id
                AND YEAR(sa.tanggal_shift) = ? AND MONTH(sa.tanggal_shift) = ?
            LEFT JOIN cabang c ON r.outlet = c.nama_cabang
            JOIN cabang_outlet co ON r.outlet = co.nama_cabang
            WHERE co.id = ? AND r.role IN ('karyawan', 'admin')
            GROUP BY r.id, r.nama_lengkap
            ORDER BY r.nama_lengkap";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$year, $month, $cabang_id]);

    $summary = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $summary[] = [
            'user_id' => $row['id'],
            'name' => $row['nama_lengkap'],
            'total_shifts' => $row['total_shifts'],
            'pagi_count' => $row['pagi_count'],
            'siang_count' => $row['siang_count'],
            'malam_count' => $row['malam_count']
        ];
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Summary loaded successfully',
        'data' => $summary
    ]);
}

function saveShift($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['user_id']) || !isset($data['date']) || !isset($data['shift_type'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    $user_id = $data['user_id'];
    $date = $data['date'];
    $shift_type = $data['shift_type'];

    // Get cabang shift times
    $sql = "SELECT c.id as cabang_id, c.shift_pagi_masuk, c.shift_pagi_keluar,
                   c.shift_siang_masuk, c.shift_siang_keluar,
                   c.shift_malam_masuk, c.shift_malam_keluar
            FROM register r
            JOIN cabang c ON r.outlet = c.nama_cabang
            WHERE r.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $cabang_shifts = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cabang_shifts) {
        http_response_code(404);
        echo json_encode(['error' => 'User or cabang not found']);
        return;
    }

    $cabang_id = $cabang_shifts['cabang_id'];

    // Set shift times based on type
    switch ($shift_type) {
        case 'pagi':
            $shift_masuk = $cabang_shifts['shift_pagi_masuk'];
            $shift_keluar = $cabang_shifts['shift_pagi_keluar'];
            break;
        case 'siang':
            $shift_masuk = $cabang_shifts['shift_siang_masuk'];
            $shift_keluar = $cabang_shifts['shift_siang_keluar'];
            break;
        case 'malam':
            $shift_masuk = $cabang_shifts['shift_malam_masuk'];
            $shift_keluar = $cabang_shifts['shift_malam_keluar'];
            break;
        case 'off':
            // Delete existing assignment for off day
            $delete_sql = "DELETE FROM shift_assignments WHERE user_id = ? AND tanggal_shift = ?";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute([$user_id, $date]);

            echo json_encode(['success' => true, 'message' => 'Shift set to OFF (deleted)']);
            return;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid shift type']);
            return;
    }

    // Insert or update shift assignment
    $upsert_sql = "INSERT INTO shift_assignments (user_id, cabang_id, tanggal_shift, shift_masuk, shift_keluar)
                   VALUES (?, ?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE
                   shift_masuk = VALUES(shift_masuk),
                   shift_keluar = VALUES(shift_keluar)";

    $stmt = $pdo->prepare($upsert_sql);

    if ($stmt->execute([$user_id, $cabang_id, $date, $shift_masuk, $shift_keluar])) {
        echo json_encode(['success' => true, 'message' => 'Shift saved successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save shift']);
    }
}

function deleteShift($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['user_id']) || !isset($data['date'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }

    $user_id = $data['user_id'];
    $date = $data['date'];

    $sql = "DELETE FROM shift_assignments WHERE user_id = ? AND tanggal_shift = ?";
    $stmt = $pdo->prepare($sql);

    if ($stmt->execute([$user_id, $date])) {
        echo json_encode(['success' => true, 'message' => 'Shift deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete shift']);
    }
}
?>
