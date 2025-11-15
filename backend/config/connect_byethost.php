<?php
/**
 * Database Connection Configuration for ByetHost
 * 
 * INSTRUKSI PENGISIAN:
 * 1. Login ke ByetHost Control Panel (VCP)
 * 2. Klik "MySQL Databases"
 * 3. Lihat informasi database Anda di bagian "Current MySQL Databases"
 * 4. Copy kredensial dan paste di bawah ini
 */

// Set timezone untuk konsistensi PHP & MySQL
date_default_timezone_set('Asia/Makassar'); // WITA (UTC+8)

// ============================================================
// KONFIGURASI DATABASE BYETHOST
// ============================================================
// Ganti nilai di bawah ini dengan kredensial dari ByetHost Anda

// 1. Database Host (biasanya: sqlxxx.byetcluster.com atau sqlxxx.byethost.com)
//    Contoh: sql100.byetcluster.com, sql110.byethost.com
$host = "sql100.byetcluster.com";  // 👈 GANTI DENGAN HOST ANDA

// 2. Database Name (format: bX_XXXXXXXX_namadb)
//    Contoh: b6_40348133_kaori, b10_12345678_aplikasi
$dbname = "b6_40348133_kaori";  // 👈 GANTI DENGAN DATABASE NAME ANDA

// 3. Database Username (sama dengan database name)
//    Di ByetHost, username = database name
$username = "b6_40348133_kaori";  // 👈 GANTI DENGAN USERNAME ANDA

// 4. Database Password (yang Anda buat saat create database)
//    Password ini BUKAN password login ByetHost!
$password = "YOUR_DB_PASSWORD_HERE";  // 👈 GANTI DENGAN PASSWORD DATABASE ANDA

// Character set (JANGAN DIUBAH)
$charset = "utf8mb4";

// ============================================================
// DATA SOURCE NAME (DSN)
// ============================================================
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// ============================================================
// PDO OPTIONS
// ============================================================
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Melempar exception jika ada error SQL
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Mengembalikan data sebagai associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Menggunakan prepared statements asli dari database
];

// ============================================================
// CREATE PDO CONNECTION
// ============================================================
try {
    // Buat instance PDO
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Set MySQL timezone to match PHP timezone (WITA = UTC+8)
    $pdo->exec("SET time_zone = '+08:00'"); // WITA timezone (Makassar)
    
} catch (\PDOException $e) {
    // Tangani error koneksi
    // Di lingkungan produksi, jangan tampilkan detail error ke pengguna
    error_log("Koneksi Gagal: " . $e->getMessage());
    die("Koneksi ke database gagal. Silakan coba lagi nanti.");
}

// ============================================================
// CONNECTION SUCCESS (optional - hapus di production)
// ============================================================
// Uncomment baris di bawah untuk testing koneksi
// echo "✅ Koneksi database berhasil!<br>";
// echo "Connected to: " . $dbname . " on " . $host . "<br>";

// NOTE: Closing tag dihilangkan untuk mencegah whitespace output (PSR standard)
