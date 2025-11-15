<?php
// Script migrasi untuk menambahkan kolom role_posisi ke tabel posisi_jabatan
// dan sinkronisasi dengan role dari pegawai_whitelist

include 'connect.php';

echo "<h2>Migrasi Role Posisi</h2>";

try {
    // 1. Tambah kolom role_posisi jika belum ada
    echo "<p>1. Menambahkan kolom role_posisi ke tabel posisi_jabatan...</p>";
    $pdo->exec("ALTER TABLE posisi_jabatan ADD COLUMN IF NOT EXISTS role_posisi VARCHAR(20) DEFAULT 'user'");
    echo "<p style='color:green;'>✓ Kolom role_posisi berhasil ditambahkan (atau sudah ada)</p>";
    
    // 2. Update role_posisi berdasarkan role di pegawai_whitelist
    echo "<p>2. Sinkronisasi role_posisi dengan data dari pegawai_whitelist...</p>";
    
    // Ambil semua posisi unik dari pegawai_whitelist beserta role-nya
    $stmt = $pdo->query("
        SELECT DISTINCT posisi, role 
        FROM pegawai_whitelist 
        WHERE posisi IS NOT NULL AND posisi != ''
        ORDER BY posisi
    ");
    $data_whitelist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $updated = 0;
    foreach ($data_whitelist as $row) {
        $posisi = $row['posisi'];
        $role = $row['role'] ?? 'user';
        
        // Update atau insert posisi ke posisi_jabatan
        $stmt_check = $pdo->prepare("SELECT id FROM posisi_jabatan WHERE nama_posisi = ?");
        $stmt_check->execute([$posisi]);
        
        if ($stmt_check->fetchColumn()) {
            // Update existing
            $stmt_update = $pdo->prepare("UPDATE posisi_jabatan SET role_posisi = ? WHERE nama_posisi = ?");
            $stmt_update->execute([$role, $posisi]);
            echo "<p>- Update posisi '<b>$posisi</b>' dengan role: <b>$role</b></p>";
        } else {
            // Insert new
            $stmt_insert = $pdo->prepare("INSERT INTO posisi_jabatan (nama_posisi, role_posisi) VALUES (?, ?)");
            $stmt_insert->execute([$posisi, $role]);
            echo "<p>- Tambah posisi baru '<b>$posisi</b>' dengan role: <b>$role</b></p>";
        }
        $updated++;
    }
    
    echo "<p style='color:green;'>✓ Berhasil update/tambah $updated posisi</p>";
    
    // 3. Set default 'user' untuk posisi yang belum memiliki role_posisi
    echo "<p>3. Mengatur default role 'user' untuk posisi yang belum memiliki role...</p>";
    $pdo->exec("UPDATE posisi_jabatan SET role_posisi = 'user' WHERE role_posisi IS NULL OR role_posisi = ''");
    echo "<p style='color:green;'>✓ Default role berhasil diatur</p>";
    
    echo "<hr>";
    echo "<h3 style='color:green;'>✓ Migrasi selesai!</h3>";
    echo "<p><a href='posisi_jabatan.php'>← Kembali ke Manajemen Posisi</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ Error: " . $e->getMessage() . "</p>";
}
?>
