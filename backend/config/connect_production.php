<?php
/**
 * Database Connection for ByetHost Production
 * DO NOT display errors in production!
 */

// Set timezone
date_default_timezone_set('Asia/Makassar'); // WITA (UTC+8)

// ============================================================
// PRODUCTION DATABASE CONFIGURATION
// ============================================================
$host = "sql100.byethost6.com";      
$dbname = "b6_40348133_kaori";        
$username = "b6_40348133";  // ✅ HARUS SAMA dengan database name
$password = "6T3DIF3p@";              
$charset = "utf8mb4";

// ============================================================
// DSN
// ============================================================
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// ============================================================
// PDO OPTIONS
// ============================================================
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// ============================================================
// CREATE CONNECTION
// ============================================================
try {
    $pdo = new PDO($dsn, $username, $password, $options);
    $pdo->exec("SET time_zone = '+08:00'"); // WITA
    
} catch (\PDOException $e) {
    // TEMPORARY DEBUG MODE - Remove after fixing
    echo "<h3 style='color:red'>❌ Database Connection Error</h3>";
    echo "<p><b>Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><b>Host:</b> $host</p>";
    echo "<p><b>Database:</b> $dbname</p>";
    echo "<p><b>Username:</b> $username</p>";
    
    error_log("DB Connection Failed: " . $e->getMessage());
    die();
}

// Connection ready - $pdo variable is now available
