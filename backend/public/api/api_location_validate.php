<?php
session_start();
include 'connect.php';
include 'absen_helper.php'; // Include helper functions

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'valid' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['latitude']) || !isset($input['longitude'])) {
    echo json_encode([
        'valid' => false,
        'message' => 'Invalid input data'
    ]);
    exit;
}

$user_lat = (float)$input['latitude'];
$user_lon = (float)$input['longitude'];

// Handle Admin/Superadmin (no location restrictions for remote work)
if (in_array($user_role, ['admin', 'superadmin'])) {
    echo json_encode([
        'valid' => true,
        'branch' => 'Remote Work',
        'distance' => 0,
        'message' => 'Admin/Superadmin - Remote work allowed'
    ]);
    exit;
}

// For regular users, validate location based on active shift
try {
    $location_check = validateUserLocation($pdo, $user_id, $user_lat, $user_lon);
    echo json_encode($location_check);
} catch (Exception $e) {
    echo json_encode([
        'valid' => false,
        'message' => 'Gagal memverifikasi lokasi: ' . $e->getMessage()
    ]);
}
?>
