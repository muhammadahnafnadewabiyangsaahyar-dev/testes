<?php
// Fix Absensi Database Schema - Add Missing Columns
require_once 'connect.php';

try {
    // Add missing columns to absensi table
    $columns_to_add = [
        'latitude_absen_masuk' => 'DOUBLE NULL COMMENT "Latitude saat absen masuk"',
        'longitude_absen_masuk' => 'DOUBLE NULL COMMENT "Longitude saat absen masuk"',
        'latitude_absen_keluar' => 'DOUBLE NULL COMMENT "Latitude saat absen keluar"',
        'longitude_absen_keluar' => 'DOUBLE NULL COMMENT "Longitude saat absen keluar"',
        'foto_absen_masuk' => 'VARCHAR(255) NULL COMMENT "Nama file foto absen masuk"',
        'foto_absen_keluar' => 'VARCHAR(255) NULL COMMENT "Nama file foto absen keluar"',
        'menit_terlambat' => 'INT DEFAULT 0 COMMENT "Menit keterlambatan"',
        'status_keterlambatan' => 'VARCHAR(50) DEFAULT "tepat waktu" COMMENT "Status keterlambatan"',
        'potongan_tunjangan' => 'VARCHAR(100) DEFAULT "tidak ada" COMMENT "Jenis potongan tunjangan"',
        'catatan_lupa_absen' => 'TEXT NULL COMMENT "Catatan lupa absen"',
        'status_lembur' => 'VARCHAR(20) DEFAULT "Not Applicable" COMMENT "Status lembur"',
        'cabang_id' => 'INT NULL COMMENT "ID cabang tempat absen"',
        'jam_masuk_shift' => 'TIME NULL COMMENT "Jam masuk shift"',
        'jam_keluar_shift' => 'TIME NULL COMMENT "Jam keluar shift"',
        'durasi_kerja' => 'TIME NULL COMMENT "Durasi kerja total"'
    ];

    // Check which columns already exist
    $existing_columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM absensi");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_columns[] = $row['Field'];
    }

    // Add missing columns
    foreach ($columns_to_add as $column_name => $definition) {
        if (!in_array($column_name, $existing_columns)) {
            $sql = "ALTER TABLE absensi ADD COLUMN $column_name $definition";
            $pdo->exec($sql);
            echo "✅ Added column: $column_name\n";
        } else {
            echo "⏭️ Column already exists: $column_name\n";
        }
    }

    // Add missing columns to register table
    $register_columns_to_add = [
        'telegram_chat_id' => 'VARCHAR(50) NULL COMMENT "Telegram Chat ID"',
        'bio' => 'TEXT NULL COMMENT "Bio/deskripsi pengguna"'
    ];

    // Check existing columns in register table
    $existing_register_columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM register");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing_register_columns[] = $row['Field'];
    }

    foreach ($register_columns_to_add as $column_name => $definition) {
        if (!in_array($column_name, $existing_register_columns)) {
            $sql = "ALTER TABLE register ADD COLUMN $column_name $definition";
            $pdo->exec($sql);
            echo "✅ Added column to register: $column_name\n";
        } else {
            echo "⏭️ Column already exists in register: $column_name\n";
        }
    }

    // Create error log table for absensi if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS absensi_error_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            error_type VARCHAR(50) NOT NULL,
            error_message VARCHAR(255) NOT NULL,
            error_details TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_error_type (error_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ Created absensi_error_log table\n";

    echo "\n🎉 Database schema fix completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>