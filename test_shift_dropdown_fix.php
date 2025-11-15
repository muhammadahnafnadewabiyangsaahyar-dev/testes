<?php
/**
 * Test File untuk Perbaikan Shift Dropdown Modal
 * Memverifikasi API get_shifts dan JavaScript integration
 */

session_start();
require_once 'connect.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Test Shift Dropdown Fix</title>\n</head>\n<body>\n";

echo "<h1>🧪 Test Shift Dropdown Modal Fix</h1>\n";

// Test 1: Direct API Call
echo "<h2>Test 1: Direct API Call ke get_shifts</h2>\n";

try {
    // Test parameters
    $testCabangId = 1; // Ganti dengan ID cabang yang valid
    $testMonth = 11; // November
    $testYear = 2025;
    
    echo "<p>Testing dengan params: cabang_id=$testCabangId, month=$testMonth, year=$testYear</p>\n";
    
    // Simulate API call
    ob_start();
    $_GET['action'] = 'get_shifts';
    $_GET['cabang_id'] = $testCabangId;
    $_GET['month'] = $testMonth;
    $_GET['year'] = $testYear;
    
    // Include the actual API file but capture output
    $apiOutput = '';
    ob_start();
    include 'api_shift_calendar.php';
    $apiOutput = ob_get_clean();
    
    echo "<h3>API Response:</h3>\n";
    echo "<pre>" . htmlspecialchars($apiOutput) . "</pre>\n";
    
    // Parse response
    $response = json_decode($apiOutput, true);
    if ($response) {
        echo "<h3>Parsed Response:</h3>\n";
        echo "<ul>\n";
        echo "<li>Status: " . ($response['status'] ?? 'N/A') . "</li>\n";
        echo "<li>Message: " . ($response['message'] ?? 'N/A') . "</li>\n";
        echo "<li>Data Count: " . (isset($response['data']) ? count($response['data']) : 'N/A') . "</li>\n";
        
        if (isset($response['data']) && is_array($response['data'])) {
            echo "<li>Shifts Data:</li>\n";
            echo "<ul>\n";
            foreach ($response['data'] as $i => $shift) {
                echo "<li>Shift $i: " . ($shift['nama_shift'] ?? 'N/A') . " (" . ($shift['jam_masuk'] ?? 'N/A') . " - " . ($shift['jam_keluar'] ?? 'N/A') . ")</li>\n";
            }
            echo "</ul>\n";
        }
        echo "</ul>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}

echo "\n<h2>Test 2: Database Structure Check</h2>\n";

try {
    // Check available tables
    echo "<h3>Available Tables:</h3>\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "<li>$table</li>\n";
    }
    
    // Check for shift-related tables
    echo "<h3>Shift-Related Tables:</h3>\n";
    $shiftTables = array_filter($tables, function($table) {
        return stripos($table, 'shift') !== false || stripos($table, 'cabang') !== false;
    });
    foreach ($shiftTables as $table) {
        echo "<li>$table</li>\n";
    }
    
    // Check cabang_outlet table structure
    echo "<h3>Cabang Structure:</h3>\n";
    $cabangStmt = $pdo->query("DESCRIBE cabang_outlet");
    $cabangColumns = $cabangStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . json_encode($cabangColumns, JSON_PRETTY_PRINT) . "</pre>\n";
    
    // Check sample data
    echo "<h3>Sample Cabang Data:</h3>\n";
    $sampleCabang = $pdo->query("SELECT * FROM cabang_outlet LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . json_encode($sampleCabang, JSON_PRETTY_PRINT) . "</pre>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}

echo "\n<h2>Test 3: JavaScript Integration Test</h2>\n";
echo "<p>Open browser console and test the following JavaScript:</p>\n";
echo "<pre>\n";
echo "// Test loadShiftList function\n";
echo "console.log('Testing loadShiftList function...');\n";
echo "// Assuming currentCabangId is set to a valid ID\n";
echo "// This should populate shiftList array and update dropdown\n";
echo "</pre>\n";

echo "\n<h2>Test 4: Dropdown Modal Test</h2>\n";
echo "<p>To test the dropdown modal:</p>\n";
echo "<ol>\n";
echo "<li>Select a branch in the calendar</li>\n";
echo "<li>Click on any day to open assign modal</li>\n";
echo "<li>Check if shift dropdown is populated</li>\n";
echo "</ol>\n";

echo "\n</body>\n</html>\n";
?>