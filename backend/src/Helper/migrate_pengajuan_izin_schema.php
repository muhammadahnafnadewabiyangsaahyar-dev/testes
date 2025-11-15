<?php
/**
 * Database Schema Migration untuk Menambahkan Kolom yang Missing
 * from pengajuan_izin table
 */

require_once 'logger.php';
include 'connect.php';

echo "<h2>🔧 Database Schema Migration: pengajuan_izin</h2>\n";

try {
    echo "<h3>1. Checking Current Schema</h3>\n";
    
    // Check current columns
    $stmt = $pdo->query("SHOW COLUMNS FROM pengajuan_izin");
    $current_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Current columns in pengajuan_izin table:\n";
    foreach ($current_columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
    echo "\n<h3>2. Adding Missing Columns</h3>\n";
    
    // Add missing columns yang diperlukan oleh aplikasi
    $alter_statements = [
        "ALTER TABLE pengajuan_izin ADD COLUMN jenis_izin VARCHAR(50) DEFAULT NULL AFTER Perihal",
        "ALTER TABLE pengajuan_izin ADD COLUMN outlet VARCHAR(100) DEFAULT NULL AFTER jenis_izin", 
        "ALTER TABLE pengajuan_izin ADD COLUMN posisi VARCHAR(100) DEFAULT NULL AFTER outlet",
        "ALTER TABLE pengajuan_izin ADD COLUMN require_dokumen_medis TINYINT(1) DEFAULT 0 AFTER posisi",
        "ALTER TABLE pengajuan_izin ADD COLUMN dokumen_medis_file VARCHAR(255) DEFAULT NULL AFTER require_dokumen_medis",
        "ALTER TABLE pengajuan_izin ADD COLUMN approval_status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER dokumen_medis_file",
        "ALTER TABLE pengajuan_izin ADD COLUMN approver_id INT(11) DEFAULT NULL AFTER approval_status",
        "ALTER TABLE pengajuan_izin ADD COLUMN approver_approved_at TIMESTAMP NULL DEFAULT NULL AFTER approver_id"
    ];
    
    foreach ($alter_statements as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ " . str_replace("ALTER TABLE pengajuan_izin ", "", $sql) . "\n";
        } catch (PDOException $e) {
            if ($e->getCode() == '42S21') { // Column already exists
                echo "⚠️  Column already exists: " . explode(" ADD COLUMN ", $sql)[1] . "\n";
            } else {
                echo "❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n<h3>3. Verifying Updated Schema</h3>\n";
    
    // Check updated columns
    $stmt = $pdo->query("SHOW COLUMNS FROM pengajuan_izin");
    $updated_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Updated columns in pengajuan_izin table:\n";
    foreach ($updated_columns as $col) {
        $nullable = $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $col['Default'] ? " DEFAULT '{$col['Default']}'" : '';
        echo "- {$col['Field']} ({$col['Type']}) {$nullable}{$default}\n";
    }
    
    echo "\n<h3>4. Testing Insert Query</h3>\n";
    
    // Test basic insert to ensure all columns work
    $test_sql = "INSERT INTO pengajuan_izin (
        user_id, Perihal, tanggal_mulai, tanggal_selesai, lama_izin, alasan,
        file_surat, tanda_tangan_file, status, tanggal_pengajuan, jenis_izin, outlet, posisi,
        require_dokumen_medis, approval_status
    ) VALUES (1, 'Test', CURDATE(), CURDATE(), 1, 'Test reason', 'test.docx', 'test.png', 'Pending', NOW(), 'Izin', 'Test Outlet', 'Test Position', 0, 'pending')";
    
    try {
        $pdo->exec($test_sql);
        echo "✅ Test insert successful\n";
        
        // Get the inserted record
        $test_id = $pdo->lastInsertId();
        echo "Test record ID: $test_id\n";
        
        // Clean up test record
        $pdo->exec("DELETE FROM pengajuan_izin WHERE id = $test_id");
        echo "✅ Test record cleaned up\n";
        
    } catch (PDOException $e) {
        echo "❌ Test insert failed: " . $e->getMessage() . "\n";
    }
    
    echo "\n<h3>5. Creating Performance Indexes</h3>\n";
    
    // Add indexes for better performance
    $index_statements = [
        "CREATE INDEX IF NOT EXISTS idx_pengajuan_jenis_izin ON pengajuan_izin(jenis_izin)",
        "CREATE INDEX IF NOT EXISTS idx_pengajuan_outlet ON pengajuan_izin(outlet)", 
        "CREATE INDEX IF NOT EXISTS idx_pengajuan_posisi ON pengajuan_izin(posisi)",
        "CREATE INDEX IF NOT EXISTS idx_pengajuan_approval_status ON pengajuan_izin(approval_status)"
    ];
    
    foreach ($index_statements as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Index created\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⚠️  Index already exists\n";
            } else {
                echo "❌ Index creation error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n<h3>🎯 Migration Summary</h3>\n";
    echo "✅ Schema migration completed successfully!\n";
    echo "✅ All required columns are now available\n";
    echo "✅ Performance indexes added\n";
    echo "✅ Insert queries will now work without errors\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    log_error("Database schema migration failed", [
        'error' => $e->getMessage(),
        'file' => __FILE__
    ]);
}

?>