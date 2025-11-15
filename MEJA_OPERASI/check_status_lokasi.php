<?php
// Check status_lokasi column structure
require_once 'connect.php';

echo "<h2>🔍 CHECK STATUS_LOKASI COLUMN</h2>";
echo "<pre>";

// Check structure of status_lokasi
try {
    $stmt = $pdo->query("DESCRIBE absensi");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'status_lokasi') {
            echo "Column: " . $row['Field'] . "\n";
            echo "Type: " . $row['Type'] . "\n";
            echo "Null: " . $row['Null'] . "\n";
            echo "Default: " . $row['Default'] . "\n";
            
            // Extract enum values if any
            if (preg_match('/enum\((.*)\)/', $row['Type'], $matches)) {
                echo "Enum values:\n";
                $enum_values = explode("','", trim($matches[1], "'\""));
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

echo "</pre>";
?>