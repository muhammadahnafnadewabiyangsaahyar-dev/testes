<?php
session_start();
include 'connect.php';

// 1. Keamanan: Pastikan user login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=unauthorized');
    exit;
}
$user_id = $_SESSION['user_id'];

// 2. Proses hanya jika metode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 3. Validasi Input
    if (!isset($_POST['absen_id']) || !filter_var($_POST['absen_id'], FILTER_VALIDATE_INT) || !isset($_POST['konfirmasi_lembur'])) {
        header('Location: absen.php?error=invalid_input');
        exit;
    }
    $absen_id = (int)$_POST['absen_id'];
    $konfirmasi = $_POST['konfirmasi_lembur']; // 'ya' atau 'tidak'
    $new_status_lembur = '';

    if ($konfirmasi === 'ya') {
        $new_status_lembur = 'Pending';
    } elseif ($konfirmasi === 'tidak') {
        $new_status_lembur = 'Not Applicable';
    } else {
        header('Location: absen.php?error=invalid_action');
        exit;
    }

    // 4. Update status lembur di database (hanya jika absen milik user)
    $sql_update = "UPDATE absensi SET status_lembur = ? WHERE id = ? AND user_id = ?";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([$new_status_lembur, $absen_id, $user_id]);

    // Redirect ke halaman utama/user
    header('Location: absen.php?status=lembur_konfirmasi');
    exit;

} else {
    header('Location: absen.php?error=invalid_method');
    exit;
}
?>