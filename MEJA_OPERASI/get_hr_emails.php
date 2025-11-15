<?php
/**
 * Script untuk mendapatkan email HR dan Kepala Toko dari database
 */

require_once 'connect.php';

echo "========================================\n";
echo "GET HR & KEPALA TOKO EMAILS\n";
echo "========================================\n\n";

try {
    // 1. Cari user dengan posisi HR
    echo "1. EMAIL HR:\n";
    $stmt = $pdo->query("SELECT id, nama_lengkap, posisi, email FROM register WHERE posisi LIKE '%HR%' OR posisi LIKE '%hr%'");
    $hr_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($hr_users)) {
        echo "   ❌ Tidak ada user dengan posisi HR\n";
    } else {
        foreach ($hr_users as $user) {
            echo "   - {$user['nama_lengkap']} ({$user['posisi']}) => {$user['email']}\n";
        }
    }
    
    echo "\n2. EMAIL KEPALA TOKO / OWNER / MANAGER:\n";
    $stmt = $pdo->query("SELECT id, nama_lengkap, posisi, email FROM register WHERE posisi LIKE '%owner%' OR posisi LIKE '%kepala%' OR posisi LIKE '%manager%' OR posisi LIKE '%Owner%'");
    $kepala_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($kepala_users)) {
        echo "   ❌ Tidak ada user dengan posisi Kepala Toko/Owner\n";
    } else {
        foreach ($kepala_users as $user) {
            echo "   - {$user['nama_lengkap']} ({$user['posisi']}) => {$user['email']}\n";
        }
    }
    
    echo "\n3. SEMUA POSISI UNIK:\n";
    $stmt = $pdo->query("SELECT DISTINCT posisi FROM register ORDER BY posisi");
    $posisi_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($posisi_list as $posisi) {
        echo "   - $posisi\n";
    }
    
    echo "\n========================================\n";
    echo "REKOMENDASI UNTUK email_config.php:\n";
    echo "========================================\n";
    
    if (!empty($hr_users)) {
        echo "// Email HR (pilih salah satu):\n";
        foreach ($hr_users as $user) {
            echo "// define('EMAIL_HR', '{$user['email']}'); // {$user['nama_lengkap']}\n";
        }
    }
    
    if (!empty($kepala_users)) {
        echo "\n// Email Kepala Toko/Owner (pilih salah satu):\n";
        foreach ($kepala_users as $user) {
            echo "// define('EMAIL_KEPALA_TOKO', '{$user['email']}'); // {$user['nama_lengkap']}\n";
        }
    }
    
    echo "\n========================================\n";
    
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
