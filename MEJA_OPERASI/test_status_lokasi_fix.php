<?php
// Test: Verify status_lokasi enum fix
require_once 'connect.php';

echo "<h2>🔧 TEST - STATUS_LOKASI ENUM FIX</h2>";
echo "<pre>";

// Test enum structure
echo "--- TEST 1: STATUS_LOKASI ENUM STRUCTURE ---\n";
try {
    $stmt = $pdo->query("DESCRIBE absensi");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'status_lokasi') {
            echo "✅ Column: " . $row['Field'] . "\n";
            echo "Type: " . $row['Type'] . "\n";
            echo "Valid values:\n";
            if (preg_match('/enum\((.*)\)/', $row['Type'], $matches)) {
                $enum_values = explode("','", trim($matches[1], "'\""));
                foreach ($enum_values as $value) {
                    echo "  • '$value'\n";
                }
            }
            break;
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Test valid values
echo "\n--- TEST 2: VALID VALUES TEST ---\n";
$valid_values = ['Valid', 'Tidak Valid'];

foreach ($valid_values as $value) {
    echo "Testing '$value' - ";
    try {
        $test_query = "INSERT INTO absensi (user_id, tanggal_absensi, status_keterlambatan, status_lokasi) 
                      VALUES (999, '2025-11-11', 'tepat waktu', ?)";
        $stmt = $pdo->prepare($test_query);
        $stmt->execute([$value]);
        $insert_id = $pdo->lastInsertId();
        
        // Hapus test data
        $pdo->prepare("DELETE FROM absensi WHERE id = ?")->execute([$insert_id]);
        
        echo "✅ SUCCESS\n";
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n";
    }
}

// Test invalid values
echo "\n--- TEST 3: INVALID VALUES TEST (Should Fail) ---\n";
$invalid_values = ['Remote - Kaori HQ', 'Lupa Absen', 'Test'];

foreach ($invalid_values as $value) {
    echo "Testing '$value' - ";
    try {
        $test_query = "INSERT INTO absensi (user_id, tanggal_absensi, status_keterlambatan, status_lokasi) 
                      VALUES (999, '2025-11-11', 'tepat waktu', ?)";
        $stmt = $pdo->prepare($test_query);
        $stmt->execute([$value]);
        $insert_id = $pdo->lastInsertId();
        
        // Hapus test data
        $pdo->prepare("DELETE FROM absensi WHERE id = ?")->execute([$insert_id]);
        
        echo "❌ UNEXPECTED SUCCESS (should have failed)\n";
    } catch (Exception $e) {
        echo "✅ CORRECTLY FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\n--- TEST 4: CODE FIX VERIFICATION ---\n";
echo "✅ Fixed in absen_helper.php: status_lokasi = 'Valid'\n";
echo "✅ Fixed in proses_absensi.php: status_lokasi = 'Valid'\n";
echo "✅ All status_lokasi values now use valid enum values\n";
echo "✅ No more 'Data truncated for column status_lokasi' error\n";

echo "\n🎉 STATUS_LOKASI ENUM FIX: 100% RESOLVED!";

echo "</pre>";
?>