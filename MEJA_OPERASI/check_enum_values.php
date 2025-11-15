<?php
// Check current enum values for status_keterlambatan
require_once 'connect.php';

echo "<h2>🔍 CHECK ENUM VALUES FOR STATUS_KETERLAMBATAN</h2>";
echo "<pre>";

// Check current enum structure
try {
    $stmt = $pdo->query("DESCRIBE absensi");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'status_keterlambatan') {
            echo "Column: " . $row['Field'] . "\n";
            echo "Type: " . $row['Type'] . "\n";
            echo "Null: " . $row['Null'] . "\n";
            echo "Default: " . $row['Default'] . "\n";
            
            // Extract enum values
            if (preg_match('/enum\((.*)\)/', $row['Type'], $matches)) {
                $enum_values = explode("','", trim($matches[1], "'\""));
                echo "Valid enum values:\n";
                foreach ($enum_values as $value) {
                    echo "  - '$value'\n";
                }
            }
            break;
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check sample data
echo "\n--- SAMPLE DATA CHECK ---\n";
try {
    $stmt = $pdo->query("SELECT status_keterlambatan FROM absensi ORDER BY id DESC LIMIT 5");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as $row) {
        echo "Value: '" . $row['status_keterlambatan'] . "' (length: " . strlen($row['status_keterlambatan']) . ")\n";
    }
} catch (Exception $e) {
    echo "Error checking data: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>