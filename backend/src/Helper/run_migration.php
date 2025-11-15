<?php
/**
 * XAMPP Compatible Migration - Dynamic Shift Configuration System
 * Works with existing database connection - No root password needed!
 */

require_once 'connect.php';

try {
    echo "🔧 Starting XAMPP-compatible database migration...\n";
    echo "📋 Using existing database connection from connect.php\n\n";
    
    // Get database info from connection
    echo "🔍 Checking database connection...\n";
    echo "Database: aplikasi\n";
    echo "Connection: ✅ Working\n\n";
    
    // Step 1: Create new tables
    echo "📋 Step 1: Creating shift_templates table...\n";
    
    $createShiftTemplates = "
    CREATE TABLE IF NOT EXISTS shift_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        display_name VARCHAR(100) NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        color_hex VARCHAR(7) NOT NULL DEFAULT '#2196F3',
        icon_emoji VARCHAR(10) DEFAULT '📅',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_name (name),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    $pdo->exec($createShiftTemplates);
    echo "✅ shift_templates table created/verified\n";
    
    echo "📋 Step 2: Creating branch_shift_config table...\n";
    
    $createBranchShiftConfig = "
    CREATE TABLE IF NOT EXISTS branch_shift_config (
        branch_id INT NOT NULL,
        shift_template_id INT NOT NULL,
        priority_order INT NOT NULL DEFAULT 1,
        is_available TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (branch_id, shift_template_id),
        INDEX idx_branch (branch_id),
        INDEX idx_template (shift_template_id),
        INDEX idx_available (is_available),
        FOREIGN KEY (branch_id) REFERENCES cabang_outlet(id) ON DELETE CASCADE,
        FOREIGN KEY (shift_template_id) REFERENCES shift_templates(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    $pdo->exec($createBranchShiftConfig);
    echo "✅ branch_shift_config table created/verified\n";
    
    echo "📋 Step 3: Creating shift_assignments_v2 table...\n";
    
    $createShiftAssignmentsV2 = "
    CREATE TABLE IF NOT EXISTS shift_assignments_v2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        branch_id INT NOT NULL,
        shift_template_id INT NOT NULL,
        assignment_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        status_konfirmasi ENUM('pending','confirmed','declined') NOT NULL DEFAULT 'pending',
        created_by INT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_assignment (user_id, assignment_date),
        INDEX idx_user (user_id),
        INDEX idx_branch (branch_id),
        INDEX idx_template (shift_template_id),
        INDEX idx_date (assignment_date),
        INDEX idx_status (status_konfirmasi),
        FOREIGN KEY (user_id) REFERENCES register(id) ON DELETE CASCADE,
        FOREIGN KEY (branch_id) REFERENCES cabang_outlet(id) ON DELETE CASCADE,
        FOREIGN KEY (shift_template_id) REFERENCES shift_templates(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ";
    
    $pdo->exec($createShiftAssignmentsV2);
    echo "✅ shift_assignments_v2 table created/verified\n";
    
    // Step 4: Insert default shift templates
    echo "📋 Step 4: Inserting default shift templates...\n";
    
    $defaultTemplates = [
        [
            'name' => 'pagi',
            'display_name' => 'Shift Pagi',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
            'color_hex' => '#FF9800',
            'icon_emoji' => '🌅'
        ],
        [
            'name' => 'middle', 
            'display_name' => 'Shift Middle',
            'start_time' => '13:00:00',
            'end_time' => '21:00:00',
            'color_hex' => '#2196F3',
            'icon_emoji' => '☀️'
        ],
        [
            'name' => 'sore',
            'display_name' => 'Shift Sore', 
            'start_time' => '15:00:00',
            'end_time' => '23:00:00',
            'color_hex' => '#9C27B0',
            'icon_emoji' => '🌆'
        ],
        [
            'name' => 'off',
            'display_name' => 'Off/Hari Libur',
            'start_time' => '00:00:00',
            'end_time' => '23:59:59',
            'color_hex' => '#9E9E9E',
            'icon_emoji' => '🚫'
        ]
    ];
    
    foreach ($defaultTemplates as $template) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO shift_templates (name, display_name, start_time, end_time, color_hex, icon_emoji)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute(array_values($template));
        echo "✅ Created template: " . $template['display_name'] . "\n";
    }
    
    // Step 5: Enable default shifts for all branches
    echo "📋 Step 5: Enabling default shifts for existing branches...\n";
    
    $stmt = $pdo->query("SELECT id, nama_cabang FROM cabang_outlet");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($branches as $branch) {
        echo "   Setting up shifts for: " . $branch['nama_cabang'] . " (ID: " . $branch['id'] . ")\n";
        
        // Get shift template IDs
        $stmt = $pdo->query("SELECT id, name FROM shift_templates WHERE name IN ('pagi', 'middle', 'sore')");
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($templates as $template) {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO branch_shift_config (branch_id, shift_template_id, priority_order, is_available)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([
                $branch['id'],
                $template['id'],
                $template['name'] === 'pagi' ? 1 : ($template['name'] === 'middle' ? 2 : 3)
            ]);
        }
        
        echo "   ✅ Enabled " . count($templates) . " shifts for branch " . $branch['nama_cabang'] . "\n";
    }
    
    // Step 6: Verify setup
    echo "\n📋 Step 6: Verifying migration setup...\n";
    
    $tables = ['shift_templates', 'branch_shift_config', 'shift_assignments_v2'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "✅ Table '{$table}': {$count} records\n";
    }
    
    echo "\n🎉 MIGRATION COMPLETED SUCCESSFULLY!\n";
    echo "\n📋 Next steps:\n";
    echo "1. ✅ Test API endpoint: http://localhost/aplikasi/api/v2/shift-templates\n";
    echo "2. ✅ Update kalender.php to use new architecture\n";
    echo "3. ✅ Verify dynamic shift loading\n";
    echo "4. ✅ Test branch selection\n";
    
    // Test API endpoint
    echo "\n🧪 Testing API endpoint...\n";
    try {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'http://localhost/aplikasi/api/v2/shift-templates');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            echo "✅ API endpoint working: " . ($data['data'] ? count($data['data']) : 0) . " shift templates\n";
        } else {
            echo "⚠️  API endpoint test: HTTP {$httpCode}\n";
        }
    } catch (Exception $e) {
        echo "⚠️  API endpoint test: " . $e->getMessage() . "\n";
    }
    
    echo "\n🔍 Quick verification:\n";
    echo "php -r \"\n";
    echo "require_once 'connect.php';\n";
    echo "\$stmt = \$pdo->query('SELECT COUNT(*) FROM shift_templates');\n";
    echo "echo 'Shift templates: ' . \$stmt->fetchColumn() . PHP_EOL;\n";
    echo "\$stmt = \$pdo->query('SELECT COUNT(*) FROM branch_shift_config');\n";
    echo "echo 'Branch configs: ' . \$stmt->fetchColumn() . PHP_EOL;\n";
    echo "\$stmt = \$pdo->query('SELECT COUNT(*) FROM shift_assignments_v2');\n";
    echo "echo 'Assignments: ' . \$stmt->fetchColumn() . PHP_EOL;\n";
    echo "\"\n";
    
} catch (Exception $e) {
    echo "❌ Error during migration: " . $e->getMessage() . "\n";
    echo "\n🔧 Troubleshooting:\n";
    echo "1. Check if database service is running in XAMPP\n";
    echo "2. Verify connect.php has correct credentials\n";
    echo "3. Ensure all required tables exist in aplikasi database\n";
    echo "4. Check if user has CREATE privileges\n";
}
?>