<?php
/**
 * Database Migration Fix - MacOS Compatible & Error Handling
 * Fixes the migration issues and handles existing procedures
 */

require_once 'connect.php';

try {
    echo "🔧 Fixing database migration for MariaDB compatibility...\n";
    
    // Drop all existing procedures first
    $dropProcedures = [
        "DROP PROCEDURE IF EXISTS AssignShiftBulk",
        "DROP PROCEDURE IF EXISTS AssignShiftSimple",
        "DROP PROCEDURE IF EXISTS GetBranchShifts"
    ];
    
    foreach ($dropProcedures as $sql) {
        try {
            $pdo->exec($sql);
            echo "✅ Dropped procedure: " . explode(' ', $sql)[4] . "\n";
        } catch (Exception $e) {
            echo "⚠️  Warning dropping procedure: " . $e->getMessage() . "\n";
        }
    }
    
    // Create compatible procedure for MariaDB
    echo "📋 Creating MariaDB-compatible procedures...\n";
    
    // Simple assignment procedure
    $simpleAssignmentSql = "
    CREATE PROCEDURE AssignShiftSimple(
        IN p_branch_id INT,
        IN p_shift_template_id INT,
        IN p_assignment_date DATE,
        IN p_user_id INT,
        IN p_created_by INT
    )
    BEGIN
        INSERT INTO shift_assignments_v2 (
            user_id, branch_id, shift_template_id, assignment_date,
            start_time, end_time, created_by
        ) 
        SELECT 
            p_user_id,
            p_branch_id,
            p_shift_template_id,
            p_assignment_date,
            st.start_time,
            st.end_time,
            p_created_by
        FROM shift_templates st 
        WHERE st.id = p_shift_template_id
        ON DUPLICATE KEY UPDATE
            shift_template_id = p_shift_template_id,
            start_time = st.start_time,
            end_time = st.end_time,
            updated_at = CURRENT_TIMESTAMP;
    END
    ";
    
    $pdo->exec($simpleAssignmentSql);
    echo "✅ Created AssignShiftSimple procedure\n";
    
    // Get branch shifts procedure
    $getBranchShiftsSql = "
    CREATE PROCEDURE GetBranchShifts(
        IN p_branch_id INT
    )
    BEGIN
        SELECT 
            st.id,
            st.name,
            st.display_name,
            st.start_time,
            st.end_time,
            bsc.priority_order,
            bsc.is_available
        FROM shift_templates st
        JOIN branch_shift_config bsc ON st.id = bsc.shift_template_id
        WHERE bsc.branch_id = p_branch_id 
            AND st.is_active = 1 
            AND bsc.is_available = 1
        ORDER BY bsc.priority_order ASC, st.display_name ASC;
    END
    ";
    
    $pdo->exec($getBranchShiftsSql);
    echo "✅ Created GetBranchShifts procedure\n";
    
    // Verify tables exist
    echo "🔍 Verifying database structure...\n";
    
    $tablesToCheck = [
        'shift_templates',
        'branch_shift_config', 
        'shift_assignments_v2'
    ];
    
    foreach ($tablesToCheck as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '{$table}' exists\n";
                
                // Get row count
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "   📊 Rows: {$count}\n";
            } else {
                echo "❌ Table '{$table}' does not exist\n";
            }
        } catch (Exception $e) {
            echo "❌ Error checking table '{$table}': " . $e->getMessage() . "\n";
        }
    }
    
    // Insert sample data if tables are empty
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM shift_templates");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count == 0) {
            echo "📝 Inserting sample shift templates...\n";
            
            $sampleTemplates = [
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
                ]
            ];
            
            foreach ($sampleTemplates as $template) {
                $stmt = $pdo->prepare("
                    INSERT INTO shift_templates (name, display_name, start_time, end_time, color_hex, icon_emoji)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute(array_values($template));
                echo "✅ Created template: " . $template['display_name'] . "\n";
            }
        }
    } catch (Exception $e) {
        echo "⚠️  Warning inserting sample data: " . $e->getMessage() . "\n";
    }
    
    echo "🎉 Database migration fixes completed successfully!\n";
    echo "\n📋 Next steps:\n";
    echo "1. Test the new API endpoints\n";
    echo "2. Update frontend to use new dynamic shift configuration\n";
    echo "3. Run comprehensive tests\n";
    
} catch (Exception $e) {
    echo "❌ Error fixing database migration: " . $e->getMessage() . "\n";
    echo "\n🔧 Troubleshooting steps:\n";
    echo "1. Check if MariaDB/MySQL service is running\n";
    echo "2. Verify database connection credentials\n";
    echo "3. Ensure database user has CREATE PROCEDURE privileges\n";
    echo "4. Check if database exists and is accessible\n";
}
?>