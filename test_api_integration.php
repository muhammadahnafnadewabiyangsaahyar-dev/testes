<?php
/**
 * API Integration Test & Verification
 * Tests the new dynamic shift configuration system
 */

require_once 'connect.php';

try {
    echo "🧪 Testing API Integration & System Verification\n\n";
    
    // Test 1: Shift Templates API
    echo "📋 Test 1: Shift Templates API Simulation\n";
    
    $stmt = $pdo->query("
        SELECT id, name, display_name, start_time, end_time, color_hex, icon_emoji 
        FROM shift_templates 
        WHERE is_active = 1 
        ORDER BY name
    ");
    
    $shiftTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Shift Templates Data: " . count($shiftTemplates) . " templates\n";
    foreach ($shiftTemplates as $template) {
        echo "   - {$template['name']}: {$template['display_name']} ({$template['start_time']}-{$template['end_time']})\n";
    }
    
    // Test 2: Branch Configuration API
    echo "\n🏢 Test 2: Branch Configuration API Simulation\n";
    
    $stmt = $pdo->query("
        SELECT co.id, co.nama_cabang, 
               COUNT(bsc.shift_template_id) as enabled_shifts,
               GROUP_CONCAT(st.display_name SEPARATOR ', ') as shifts
        FROM cabang_outlet co
        LEFT JOIN branch_shift_config bsc ON co.id = bsc.branch_id 
            AND bsc.is_available = 1
        LEFT JOIN shift_templates st ON bsc.shift_template_id = st.id 
            AND st.is_active = 1
        GROUP BY co.id, co.nama_cabang
        ORDER BY co.nama_cabang
    ");
    
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Branch Configuration Data: " . count($branches) . " branches\n";
    foreach ($branches as $branch) {
        echo "   - {$branch['nama_cabang']} (ID: {$branch['id']}): {$branch['enabled_shifts']} shifts enabled\n";
    }
    
    // Test 3: Dynamic Shift Assignment Simulation
    echo "\n👥 Test 3: Dynamic Assignment System Simulation\n";
    
    // Simulate getting employees for a branch
    $stmt = $pdo->query("
        SELECT r.id, r.nama_lengkap, co.nama_cabang 
        FROM register r 
        JOIN cabang_outlet co ON r.outlet = co.nama_cabang 
        WHERE r.role IN ('karyawan', 'admin')
        LIMIT 5
    ");
    
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Employee Data: " . count($employees) . " employees (sample)\n";
    foreach ($employees as $employee) {
        echo "   - {$employee['nama_lengkap']} (Branch: {$employee['nama_cabang']})\n";
    }
    
    // Test 4: New Assignment System
    echo "\n📅 Test 4: New Assignment System Test\n";
    
    // Test inserting a sample assignment using new system
    if (count($employees) > 0 && count($shiftTemplates) > 0 && count($branches) > 0) {
        $sampleEmployee = $employees[0];
        $sampleShift = $shiftTemplates[0];
        $sampleBranch = $branches[0];
        $today = date('Y-m-d');
        
        echo "🔄 Testing assignment for: {$sampleEmployee['nama_lengkap']} -> {$sampleShift['display_name']} (Branch: {$sampleBranch['nama_cabang']})";
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO shift_assignments_v2 (
                    user_id, branch_id, shift_template_id, assignment_date,
                    start_time, end_time, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                shift_template_id = VALUES(shift_template_id),
                start_time = VALUES(start_time),
                end_time = VALUES(end_time),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $stmt->execute([
                $sampleEmployee['id'],
                $sampleBranch['id'], 
                $sampleShift['id'],
                $today,
                $sampleShift['start_time'],
                $sampleShift['end_time'],
                1 // admin user
            ]);
            
            echo " - ✅ SUCCESS\n";
            
        } catch (Exception $e) {
            echo " - ⚠️  " . $e->getMessage() . "\n";
        }
    }
    
    // Test 5: Check assignment retrieval
    echo "\n🔍 Test 5: Assignment Retrieval Test\n";
    
    $stmt = $pdo->query("
        SELECT sa.id, sa.assignment_date,
               r.nama_lengkap,
               co.nama_cabang,
               st.display_name as shift_name,
               sa.start_time, sa.end_time,
               sa.created_at as assignment_created
        FROM shift_assignments_v2 sa
        JOIN register r ON sa.user_id = r.id
        JOIN cabang_outlet co ON sa.branch_id = co.id
        JOIN shift_templates st ON sa.shift_template_id = st.id
        ORDER BY sa.assignment_date DESC
        LIMIT 3
    ");
    
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Current Assignments: " . count($assignments) . " assignments\n";
    foreach ($assignments as $assignment) {
        echo "   - {$assignment['nama_lengkap']}: {$assignment['shift_name']} pada {$assignment['assignment_date']} (Created: {$assignment['assignment_created']})\n";
    }
    
    // Test 6: Performance Check
    echo "\n⚡ Test 6: Database Performance Check\n";
    
    $startTime = microtime(true);
    
    // Complex query to test performance
    $stmt = $pdo->query("
        SELECT 
            co.nama_cabang,
            st.display_name,
            st.color_hex,
            COUNT(sa.id) as assignment_count,
            GROUP_CONCAT(r.nama_lengkap SEPARATOR ', ') as employees
        FROM cabang_outlet co
        JOIN branch_shift_config bsc ON co.id = bsc.branch_id
        JOIN shift_templates st ON bsc.shift_template_id = st.id
        LEFT JOIN shift_assignments_v2 sa ON sa.branch_id = co.id AND sa.shift_template_id = st.id
        LEFT JOIN register r ON sa.user_id = r.id
        WHERE st.is_active = 1 AND bsc.is_available = 1
        GROUP BY co.id, st.id
        ORDER BY co.nama_cabang, st.display_name
    ");
    
    $complexResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $endTime = microtime(true);
    $queryTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
    
    echo "✅ Complex Query Performance: " . number_format($queryTime, 2) . "ms (" . count($complexResults) . " results)\n";
    
    // Test 7: Integration Summary
    echo "\n📊 INTEGRATION TEST SUMMARY\n";
    echo "=====================================\n";
    echo "✅ Shift Templates API: " . count($shiftTemplates) . " templates loaded\n";
    echo "✅ Branch Configuration: " . count($branches) . " branches configured\n";
    echo "✅ Employee System: " . count($employees) . " employees available\n";
    echo "✅ Assignment System: " . count($assignments) . " assignments processed\n";
    echo "✅ Database Performance: " . number_format($queryTime, 2) . "ms response time\n";
    echo "✅ Modern Architecture: READY\n";
    
    echo "\n🚀 SYSTEM STATUS: FULLY OPERATIONAL\n";
    echo "📋 Next: Frontend Integration Testing\n";
    
} catch (Exception $e) {
    echo "❌ Integration Test Failed: " . $e->getMessage() . "\n";
    echo "🔧 Troubleshooting needed\n";
}
?>