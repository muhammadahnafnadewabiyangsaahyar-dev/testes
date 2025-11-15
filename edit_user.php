<?php
session_start();
include 'connect.php';

// Hanya superadmin, HR, finance, owner yang boleh akses
$allowed_roles = ['superadmin', 'HR', 'finance', 'owner'];
if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['posisi'] ?? ''), array_map('strtolower', $allowed_roles))) {
    header('Location: index.php?error=unauthorized');
    exit;
}

// Validasi ID user
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header('Location: view_user.php?error=invalid_userid');
    exit;
}
$user_id = (int)$_GET['id'];

// Ambil data user
$stmt = $pdo->prepare('SELECT id, nama_lengkap, posisi, outlet, no_whatsapp, email, username, role FROM register WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: view_user.php?error=usernotfound');
    exit;
}

// Proses update jika form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $posisi = trim($_POST['posisi'] ?? '');
    $outlet = trim($_POST['outlet'] ?? '');
    $no_whatsapp = trim($_POST['no_whatsapp'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = trim($_POST['role'] ?? '');

    // Validasi sederhana
    if ($nama_lengkap === '' || $posisi === '' || $outlet === '' || $no_whatsapp === '' || $email === '' || $username === '' || $role === '') {
        $error = 'Semua field wajib diisi!';
    } else {
        // Update data
        $stmt_update = $pdo->prepare('UPDATE register SET nama_lengkap=?, posisi=?, outlet=?, no_whatsapp=?, email=?, username=?, role=? WHERE id=?');
        $stmt_update->execute([$nama_lengkap, $posisi, $outlet, $no_whatsapp, $email, $username, $role, $user_id]);
        header('Location: view_user.php?status=edit_success');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna</title>
    <link rel="stylesheet" href="style_modern.css">
</head>
<body>
    <div class="main-title">Edit Pengguna</div>
    <div class="content-container">
        <a href="view_user.php">&larr; Kembali ke Manajemen Pengguna</a>
        <h2>Edit Data Pengguna</h2>
        <?php if (!empty($error)): ?>
            <p style="color: red; font-weight: bold;">Error: <?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <label>Nama Lengkap:<br><input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" readonly style="background:#eee;"></label><br><br>
            <label>Posisi:<br><input type="text" name="posisi" value="<?php echo htmlspecialchars($user['posisi']); ?>" required></label><br><br>
            <label>Outlet:<br><input type="text" name="outlet" value="<?php echo htmlspecialchars($user['outlet']); ?>" required></label><br><br>
            <label>No. WhatsApp:<br><input type="text" name="no_whatsapp" value="<?php echo htmlspecialchars($user['no_whatsapp']); ?>" readonly style="background:#eee;"></label><br><br>
            <label>Email:<br><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly style="background:#eee;"></label><br><br>
            <label>Username:<br><input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly style="background:#eee;"></label><br><br>
            <label>Role:<br><input type="text" name="role" value="<?php echo htmlspecialchars($user['role']); ?>" required></label><br><br>
            <button type="submit">Simpan Perubahan</button>
        </form>
    </div>
</body>
</html>
