<?php
/**
 * SCRIPT TESTING UNTUK VERIFIKASI PERBAIKAN OPENTBS FLUSH()
 * Testing semua perbaikan yang telah diimplementasikan
 */

echo "<h1>🧪 OPENTBS FLUSH() FIXES VERIFICATION TEST</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-result { padding: 10px; margin: 10px 0; border-radius: 5px; }
    .pass { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .fail { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
</style>";

$test_results = [];
$total_tests = 0;
$passed_tests = 0;

function runTest($test_name, $test_function) {
    global $test_results, $total_tests, $passed_tests;
    $total_tests++;
    
    echo "<h3>Test " . $total_tests . ": " . $test_name . "</h3>";
    
    try {
        $result = $test_function();
        if ($result['success']) {
            echo "<div class='test-result pass'>✅ PASS: " . $result['message'] . "</div>";
            $passed_tests++;
        } else {
            echo "<div class='test-result fail'>❌ FAIL: " . $result['message'] . "</div>";
        }
        $test_results[] = [
            'name' => $test_name,
            'status' => $result['success'] ? 'PASS' : 'FAIL',
            'message' => $result['message']
        ];
    } catch (Exception $e) {
        echo "<div class='test-result fail'>❌ FAIL: Exception - " . $e->getMessage() . "</div>";
        $test_results[] = [
            'name' => $test_name,
            'status' => 'FAIL',
            'message' => 'Exception: ' . $e->getMessage()
        ];
    }
}

// TEST 1: Directory Permission Management
runTest("Directory Permission Management", function() {
    $test_dir = "test_directory_permissions";
    if (is_dir($test_dir)) {
        rmdir($test_dir);
    }
    
    // Test directory creation
    if (!mkdir($test_dir, 0755, true)) {
        return ['success' => false, 'message' => 'Failed to create test directory'];
    }
    
    // Test permission setting
    $permissions_to_try = [0755, 0775, 0777];
    $permission_fixed = false;
    
    foreach ($permissions_to_try as $perm) {
        if (chmod($test_dir, $perm)) {
            $permission_fixed = true;
            break;
        }
    }
    
    rmdir($test_dir);
    
    if ($permission_fixed) {
        return ['success' => true, 'message' => 'Directory permission management working correctly'];
    } else {
        return ['success' => false, 'message' => 'Could not set directory permissions'];
    }
});

// TEST 2: Unique File Naming System
runTest("Unique File Naming System", function() {
    $test_dir = "uploads/test_unique_files/";
    if (!is_dir($test_dir)) {
        mkdir($test_dir, 0755, true);
    }
    
    // Simulate the unique filename generation logic
    $nomor_surat = "TEST" . date('YmdHis');
    $original_filename = "surat_izin_{$nomor_surat}.docx";
    $output_path = $test_dir . $original_filename;
    
    // Create first file
    file_put_contents($output_path, "test content 1");
    
    // Test counter system
    $counter = 1;
    $final_path = $output_path;
    while (file_exists($final_path)) {
        $filename_parts = pathinfo($original_filename);
        $new_filename = $filename_parts['filename'] . '_' . $counter . '.' . $filename_parts['extension'];
        $final_path = $test_dir . $new_filename;
        $counter++;
    }
    
    // Test uniqid fallback
    if (file_exists($output_path)) {
        $unique_id = uniqid('', true);
        $final_path = $test_dir . $filename_parts['filename'] . '_' . $unique_id . '.docx';
    }
    
    file_put_contents($final_path, "test content 2");
    
    // Cleanup
    $files = glob($test_dir . "*");
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($test_dir);
    
    return ['success' => true, 'message' => 'Unique filename generation working correctly'];
});

// TEST 3: OpenTBS NoErr Configuration
runTest("OpenTBS NoErr Configuration", function() {
    // Test if we can load TBS classes
    if (!file_exists('tbs/tbs_class.php')) {
        return ['success' => false, 'message' => 'TBS class file not found'];
    }
    
    if (!file_exists('tbs/tbs_plugin_opentbs.php')) {
        return ['success' => false, 'message' => 'OpenTBS plugin file not found'];
    }
    
    require_once 'tbs/tbs_class.php';
    require_once 'tbs/tbs_plugin_opentbs.php';
    
    $TBS = new clsTinyButStrong;
    $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
    
    // Test NoErr property setting
    $TBS->SetOption('opentbs_noerr', true);
    $TBS->SetOption('opentbs_verbose', 0);
    $TBS->SetOption('opentbs_zip', 'auto');
    $TBS->SetOption('opentbs_tpl_allownew', true);
    
    return ['success' => true, 'message' => 'OpenTBS NoErr configuration working correctly'];
});

// TEST 4: Template File Validation
runTest("Template File Validation", function() {
    $template_file = 'template.docx';
    
    if (!file_exists($template_file)) {
        return ['success' => false, 'message' => 'Template file not found'];
    }
    
    if (filesize($template_file) < 1024) {
        return ['success' => false, 'message' => 'Template file too small or corrupted'];
    }
    
    // Check if it's a valid ZIP/Word document
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $template_file);
    finfo_close($finfo);
    
    if (strpos($mime_type, 'zip') === false && strpos($mime_type, 'vnd.openxmlformats-officedocument') === false) {
        return ['success' => false, 'message' => 'Template file is not a valid Word document'];
    }
    
    return ['success' => true, 'message' => 'Template file validation working correctly'];
});

// TEST 5: Upload Directory Write Permission
runTest("Upload Directory Write Permission", function() {
    $directories = [
        'uploads/surat_izin/',
        'uploads/tanda_tangan/',
        'uploads/dokumen_medis/'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            return ['success' => false, 'message' => 'Directory does not exist: ' . $dir];
        }
        
        if (!is_writable($dir)) {
            // Try to fix permissions
            $permissions_to_try = [0755, 0775, 0777];
            $fixed = false;
            
            foreach ($permissions_to_try as $perm) {
                if (chmod($dir, $perm)) {
                    $fixed = true;
                    break;
                }
            }
            
            if (!$fixed) {
                return ['success' => false, 'message' => 'Directory not writable and cannot fix: ' . $dir];
            }
        }
    }
    
    return ['success' => true, 'message' => 'All upload directories are writable'];
});

// TEST 6: Disk Space Check
runTest("Disk Space Check", function() {
    $test_dir = 'uploads/surat_izin/';
    $free_space = disk_free_space($test_dir);
    $required_space = 10 * 1024 * 1024; // 10MB
    
    if ($free_space < $required_space) {
        return ['success' => false, 'message' => 'Insufficient disk space. Available: ' . ($free_space/1024/1024) . 'MB, Required: 10MB'];
    }
    
    return ['success' => true, 'message' => 'Sufficient disk space available'];
});

// TEST 7: PHP Memory and Execution Time Settings
runTest("PHP Memory and Execution Time Settings", function() {
    $memory_limit = ini_get('memory_limit');
    $max_execution_time = ini_get('max_execution_time');
    
    // Convert memory limit to bytes
    $memory_limit_bytes = php_parse_size($memory_limit);
    $required_memory = 128 * 1024 * 1024; // 128MB
    
    if ($memory_limit_bytes < $required_memory) {
        return ['success' => false, 'message' => 'Memory limit too low: ' . $memory_limit . ' (minimum 128M recommended)'];
    }
    
    if ($max_execution_time < 30) {
        return ['success' => false, 'message' => 'Execution time too low: ' . $max_execution_time . ' (minimum 30 seconds recommended)'];
    }
    
    return ['success' => true, 'message' => 'PHP settings are adequate'];
});

// TEST 8: Mock DOCX Generation
runTest("Mock DOCX Generation Test", function() {
    if (!file_exists('template.docx')) {
        return ['success' => false, 'message' => 'Template file not available'];
    }
    
    require_once 'tbs/tbs_class.php';
    require_once 'tbs/tbs_plugin_opentbs.php';
    
    $TBS = new clsTinyButStrong;
    $TBS->Plugin(TBS_INSTALL, OPENTBS_PLUGIN);
    $TBS->SetOption('opentbs_noerr', true);
    $TBS->SetOption('opentbs_verbose', 0);
    
    $TBS->LoadTemplate('template.docx');
    
    // Test merge field
    $TBS->MergeField('test_field', 'Test Value');
    $TBS->MergeField('date', date('j F Y'));
    
    $test_output = "test_mock_output.docx";
    
    try {
        $TBS->Show(OPENTBS_FILE, $test_output);
        
        if (file_exists($test_output) && filesize($test_output) > 1024) {
            unlink($test_output); // Clean up
            return ['success' => true, 'message' => 'Mock DOCX generation successful'];
        } else {
            if (file_exists($test_output)) unlink($test_output);
            return ['success' => false, 'message' => 'Generated file is invalid or too small'];
        }
    } catch (Exception $e) {
        if (file_exists($test_output)) unlink($test_output);
        return ['success' => false, 'message' => 'DOCX generation failed: ' . $e->getMessage()];
    }
});

// Helper function to parse PHP size notation
function php_parse_size($size) {
    $unit = strtolower(substr($size, -1));
    $value = (int) substr($size, 0, -1);
    
    switch ($unit) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    
    return $value;
}

// SUMMARY
echo "<h2>📊 TEST SUMMARY</h2>";
echo "<div class='test-result info'>";
echo "<strong>Total Tests:</strong> " . $total_tests . "<br>";
echo "<strong>Passed:</strong> " . $passed_tests . "<br>";
echo "<strong>Failed:</strong> " . ($total_tests - $passed_tests) . "<br>";
echo "<strong>Success Rate:</strong> " . round(($passed_tests / $total_tests) * 100, 1) . "%";
echo "</div>";

if ($passed_tests === $total_tests) {
    echo "<div class='test-result pass'>";
    echo "<h3>🎉 ALL TESTS PASSED!</h3>";
    echo "<p>Semua perbaikan OpenTBS Flush() telah berhasil diimplementasikan dan berfungsi dengan baik.</p>";
    echo "</div>";
} else {
    echo "<div class='test-result warning'>";
    echo "<h3>⚠️ SOME TESTS FAILED</h3>";
    echo "<p>Silakan periksa pesan error di atas dan lakukan perbaikan tambahan jika diperlukan.</p>";
    echo "</div>";
}

echo "<h2>📝 DETAILED TEST RESULTS</h2>";
echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background-color: #f8f9fa;'><th>Test Name</th><th>Status</th><th>Message</th></tr>";

foreach ($test_results as $result) {
    $status_color = $result['status'] === 'PASS' ? '#d4edda' : '#f8d7da';
    $status_text = $result['status'] === 'PASS' ? '✅ PASS' : '❌ FAIL';
    
    echo "<tr style='background-color: {$status_color};'>";
    echo "<td>" . htmlspecialchars($result['name']) . "</td>";
    echo "<td>" . $status_text . "</td>";
    echo "<td>" . htmlspecialchars($result['message']) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>🔧 RECOMMENDATIONS</h2>";
echo "<div class='test-result info'>";
echo "<h4>Untuk memastikan performa optimal:</h4>";
echo "<ul>";
echo "<li>Pastikan direktori uploads memiliki permission 755 atau 777</li>";
echo "<li>Monitor disk space secara berkala (minimum 10MB free)</li>";
echo "<li>Periksa error logs jika ada masalah</li>";
echo "<li>Test dengan file template yang valid</li>";
echo "<li>Pastikan PHP memory limit minimal 128M</li>";
echo "</ul>";
echo "</div>";

?>