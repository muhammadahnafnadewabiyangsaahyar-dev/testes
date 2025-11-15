<?php
session_start();
// Cek login
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// Validasi GET parameter
if (!isset($_GET['absen_id']) || !filter_var($_GET['absen_id'], FILTER_VALIDATE_INT)) {
    die("ID Absensi tidak valid.");
}
$absen_id = (int)$_GET['absen_id'];

// (Opsional tapi direkomendasikan: Cek ke DB apakah $absen_id ini benar milik $user_id)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Konfirmasi Lembur</title>
    <link rel="stylesheet" href="style_modern.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="content-container" style="text-align: center; max-width: 500px; margin: 50px auto;">
        <h2>Konfirmasi Lembur</h2>
        <p>Absen keluar Anda (ID: <?php echo $absen_id; ?>) telah berhasil dicatat.</p>
        <p style="font-weight: bold; margin-top: 20px;">Apakah Anda bekerja lembur (1 shift) pada hari ini?</p>
        
        <form action="proses_konfirmasi_lembur.php" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="absen_id" value="<?php echo $absen_id; ?>">
            
            <div class="button-group">
                <button type="submit" name="konfirmasi_lembur" value="ya" style="background-color: #5cb85c;">
                    Ya, Saya Lembur
                </button>
                <button type="submit" name="konfirmasi_lembur" value="tidak" style="background-color: #d9534f;">
                    Tidak Lembur
                </button>
            </div>
        </form>
    </div>
</body>
</html>