<?php
session_start();
include 'connect.php';

// ========================================================
// --- BLOK PERBAIKAN: Keamanan CSRF & Validasi ---
// ========================================================

// 1. Keamanan: Pastikan hanya admin yang bisa akses
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    // Redirect jika bukan admin
    header('Location: index.php?error=unauthorized');
    exit;
}

// 2. Keamanan CSRF: Hanya izinkan metode POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect jika metode salah (misal: akses via GET)
    header('Location: view_user.php?error=invalid_method');
    exit;
}

// 3. Ambil data dari POST dan validasi
if (!isset($_POST['user_id']) || !filter_var($_POST['user_id'], FILTER_VALIDATE_INT)) {
    header('Location: view_user.php?error=invalid_userid');
    exit;
}
$user_id_to_delete = (int)$_POST['user_id'];
$admin_id = (int)$_SESSION['user_id'];

// 4. Bonus Keamanan Logika: Jangan biarkan admin menghapus dirinya sendiri
if ($user_id_to_delete == $admin_id) {
    header('Location: view_user.php?error=cannot_delete_self');
    exit;
}

// 5. Ambil nama file foto profil dan tanda tangan user
$stmt_files = $pdo->prepare('SELECT foto_profil, tanda_tangan_file FROM register WHERE id = ?');
$stmt_files->execute([$user_id_to_delete]);
$user_files = $stmt_files->fetch(PDO::FETCH_ASSOC);
if ($user_files) {
    // Hapus foto profil jika ada dan bukan default
    if (!empty($user_files['foto_profil']) && $user_files['foto_profil'] != 'default.png') {
        $foto_path = 'uploads/foto_profil/' . $user_files['foto_profil'];
        if (file_exists($foto_path)) @unlink($foto_path);
    }
    // Hapus tanda tangan jika ada
    if (!empty($user_files['tanda_tangan_file'])) {
        $ttd_path = 'uploads/tanda_tangan/' . $user_files['tanda_tangan_file'];
        if (file_exists($ttd_path)) @unlink($ttd_path);
    }
}

// 6. Lakukan penghapusan menggunakan prepared statement PDO
$sql_delete = "DELETE FROM register WHERE id = ?";
$stmt_delete = $pdo->prepare($sql_delete);
$stmt_delete->execute([$user_id_to_delete]);

header('Location: view_user.php?success=deleted');
exit;
// ========================================================
// --- AKHIR BLOK PERBAIKAN ---
// ========================================================
?>