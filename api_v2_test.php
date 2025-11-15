<?php
/**
 * Simple API Test - Without Session Dependencies
 * Tests the core shift templates API functionality
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'connect.php';

try {
    echo "🧪 Testing Simple Shift Templates API\n\n";
    
    // Get all shift templates
    $stmt = $pdo->query("
        SELECT id, name, display_name, start_time, end_time, color_hex, icon_emoji, is_active
        FROM shift_templates 
        WHERE is_active = 1 
        ORDER BY name
    ");
    
    $shiftTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $response = [
        'status' => 'success',
        'message' => 'Shift templates retrieved successfully',
        'data' => $shiftTemplates,
        'count' => count($shiftTemplates),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
    echo "\n\n✅ API test successful!\n";
    echo "📊 " . count($shiftTemplates) . " shift templates loaded\n";
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>