<?php
/**
 * NewStoreSeed API Endpoint
 * 
 * CRITICAL: New store seeding for TODAY'S operations
 * Usage: curl "https://staff.vapeshed.co.nz/assets/cron/NewTransferV3/new_store_seed.php?store_id=NEW_STORE_ID&simulate=0"
 */

require_once __DIR__ . '/bootstrap.php';

// CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Force new store seeding action
    $_GET['action'] = 'seed_new_store';
    
    // Validate store ID
    $storeId = $_GET['store_id'] ?? $_POST['store_id'] ?? null;
    if (!$storeId) {
        throw new Exception('New store ID is required. Usage: ?store_id=OUTLET_ID');
    }
    
    // Set safe defaults for new store seeding
    $_GET['simulate'] = $_GET['simulate'] ?? 1; // Default to simulation
    $_GET['cover'] = $_GET['cover'] ?? 21;      // 21 days coverage for new store
    $_GET['buffer_pct'] = $_GET['buffer_pct'] ?? 35; // 35% buffer for new store
    $_GET['default_floor_qty'] = $_GET['default_floor_qty'] ?? 3;
    
    echo "🌱 NewTransferV3 New Store Seeding\n";
    echo "=====================================\n";
    echo "Store ID: {$storeId}\n";
    echo "Simulate: " . ($_GET['simulate'] ? 'YES' : 'NO (LIVE)') . "\n";
    echo "Coverage: {$_GET['cover']} days\n";
    echo "Buffer: {$_GET['buffer_pct']}%\n";
    echo "=====================================\n\n";
    
    // The bootstrap.php will handle the rest through the controller
    
} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'usage' => 'GET /new_store_seed.php?store_id=OUTLET_ID&simulate=1',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
