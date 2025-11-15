<?php
session_start();
include 'connect.php';

$feedback = '';
$token = $_GET['token'] ?? '';
$valid = false;
$user_id = null;

if ($token) {
    // Cek token di database
    $stmt = $pdo->prepare('SELECT *, NOW() as current_db_time FROM reset_password WHERE token = ?');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row) {
        if ($row['used'] == 1) {
            $feedback = 'Token sudah pernah digunakan.';
        } elseif ($row['expires_at'] <= $row['current_db_time']) {
            $feedback = 'Token sudah kadaluarsa.';
        } else {
            $valid = true;
            $user_id = $row['user_id'];
        }
    } else {
        $feedback = 'Token tidak ditemukan di database.';
    }
} else {
    $feedback = 'Token tidak ditemukan.';
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    if (strlen($password) < 6) {
        $feedback = 'Password minimal 6 karakter.';
    } elseif ($password !== $password2) {
        $feedback = 'Konfirmasi password tidak cocok.';
    } else {
        // Update password user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE register SET password = ? WHERE id = ?')->execute([$hash, $user_id]);
        // Tandai token sudah dipakai
        $pdo->prepare('UPDATE reset_password SET used = 1 WHERE token = ?')->execute([$token]);
        $feedback = 'Password berhasil direset. <a href="index.php">Login sekarang</a>';
        $valid = false;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="style_modern.css">
</head>
<body>
    <div class="main-title">Reset Password</div>
    <div class="content-container" style="max-width:400px;margin:auto;">
        <?php if ($valid): ?>
        <form method="POST" action="">
            <label for="password">Password Baru:</label><br>
            <input type="password" name="password" id="password" required minlength="6" style="width:100%;padding:8px;margin:10px 0;">
            <label for="password2">Konfirmasi Password:</label><br>
            <input type="password" name="password2" id="password2" required minlength="6" style="width:100%;padding:8px;margin:10px 0;">
            <button type="submit" class="btn-approve" style="width:100%;">Reset Password</button>
        </form>
        <?php endif; ?>
        <?php if ($feedback): ?>
            <div style="margin-top:20px;color:<?= strpos($feedback,'berhasil')!==false?'green':'red' ?>;">
                <?= $feedback ?>
            </div>
        <?php endif; ?>
        <div style="margin-top:20px;">
            <a href="index.php">Kembali ke Login</a>
        </div>
    </div>
</body>
</html>
