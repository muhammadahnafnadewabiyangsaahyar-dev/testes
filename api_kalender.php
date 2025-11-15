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

        case 'save_shift':
            saveShift($pdo);
            break;

        case 'delete_shift':
            deleteShift($pdo);
            break;

        case 'get_summary':
            $cabang_id = $_GET['cabang_id'] ?? null;
            $month = $_GET['month'] ?? null;
            $year = $_GET['year'] ?? null;
            getSummary($pdo, $cabang_id, $month, $year);
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
    $sql = "SELECT id, nama_cabang as nama FROM cabang_outlet ORDER BY nama_cabang";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $cabang = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cabang[] = [
            'id' => $row['id'],
            'nama' => $row['nama']
        ];
    }

    echo json_encode(['cabang' => $cabang]);
}

function getUsers($pdo, $cabang_id) {
    if (!$cabang_id) {
        echo json_encode(['users' => []]);
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

    echo json_encode(['users' => $users]);
}

function getShifts($pdo, $cabang_id, $month, $year) {
    if (!$cabang_id || !$month || !$year) {
        echo json_encode(['shifts' => []]);
        return;
    }

    // Get shift assignments for the month
    $sql = "SELECT sa.id, sa.user_id, sa.tanggal_shift, sa.shift_masuk, sa.shift_keluar,
                   r.nama_lengkap as user_name,
                   c.shift_pagi_masuk, c.shift_pagi_keluar,
                   c.shift_siang_masuk, c.shift_siang_keluar,
                   c.shift_malam_masuk, c.shift_malam_keluar
            FROM shift_assignments sa
            JOIN register r ON sa.user_id = r.id
            JOIN cabang c ON sa.cabang_id = c.id
            JOIN cabang_outlet co ON c.nama_cabang = co.nama_cabang
            WHERE co.id = ?
            AND YEAR(sa.tanggal_shift) = ?
            AND MONTH(sa.tanggal_shift) = ?
            ORDER BY sa.tanggal_shift, r.nama_lengkap";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$cabang_id, $year, $month]);

    $shifts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Determine shift type based on time
        $shift_type = 'Unknown';
        $shift_label = '';

        if ($row['shift_masuk'] == $row['shift_pagi_masuk'] && $row['shift_keluar'] == $row['shift_pagi_keluar']) {
            $shift_type = 'pagi';
            $shift_label = 'Pagi (' . $row['shift_masuk'] . ' - ' . $row['shift_keluar'] . ')';
        } elseif ($row['shift_masuk'] == $row['shift_siang_masuk'] && $row['shift_keluar'] == $row['shift_siang_keluar']) {
            $shift_type = 'siang';
            $shift_label = 'Siang (' . $row['shift_masuk'] . ' - ' . $row['shift_keluar'] . ')';
        } elseif ($row['shift_masuk'] == $row['shift_malam_masuk'] && $row['shift_keluar'] == $row['shift_malam_keluar']) {
            $shift_type = 'malam';
            $shift_label = 'Malam (' . $row['shift_masuk'] . ' - ' . $row['shift_keluar'] . ')';
        } else {
            $shift_label = 'Custom (' . $row['shift_masuk'] . ' - ' . $row['shift_keluar'] . ')';
        }

        $shifts[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'user_name' => $row['user_name'],
            'date' => $row['tanggal_shift'],
            'shift_type' => $shift_type,
            'shift_label' => $shift_label,
            'shift_masuk' => $row['shift_masuk'],
            'shift_keluar' => $row['shift_keluar']
        ];
    }

    echo json_encode(['shifts' => $shifts]);
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

function getSummary($pdo, $cabang_id, $month, $year) {
    if (!$cabang_id || !$month || !$year) {
        echo json_encode(['summary' => []]);
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

    echo json_encode(['summary' => $summary]);
}
?>
