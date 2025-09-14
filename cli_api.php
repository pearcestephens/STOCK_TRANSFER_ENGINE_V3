<?php
/**
 * cli_api.php - CLI-Safe API Endpoint for NewTransferV3
 * 
 * Handles API requests without ANY session/config interference
 * Uses direct database connection and bypasses all web-based includes
 */

declare(strict_types=1);

// Basic error handling
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
date_default_timezone_set('Pacific/Auckland');
ini_set('display_errors', '0'); // Suppress all output interference
set_time_limit(300);
ini_set('memory_limit', '1024M');

// Use the mysql.php connection method (clean, no sessions)
try {
    // Include the MySQL connection functions
    require_once __DIR__ . "/../../functions/mysql.php";
    
    // Connect using the standard method
    if (!connectToSQL()) {
        throw new Exception("Failed to connect to database");
    }
    
    global $con;
    
    // Test the connection
    if (!$con || $con->connect_error) {
        throw new Exception("Connection error: " . ($con->connect_error ?? 'Unknown'));
    }
    
    // Alias for consistency with the rest of this file
    $db = $con;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit(1);
}

// Helper functions
function get_cli_param($key, $default = '') {
    global $argv;
    
    if (!isset($argv)) return $default;
    
    // Check if we have a query string argument (like "action=test&param=value")
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false && strpos($arg, '&') !== false) {
            // Parse as query string
            parse_str($arg, $params);
            return $params[$key] ?? $default;
        }
        
        // Check for individual key=value format
        if (strpos($arg, "$key=") === 0) {
            return substr($arg, strlen("$key="));
        }
    }
    
    return $default;
}

function as_int_safe($val, $def, $min = 1, $max = 1000) {
    $val = (int)$val;
    return ($val >= $min && $val <= $max) ? $val : $def;
}

function as_bool_safe($val, $def) {
    if ($val === '1' || $val === 'true' || $val === 'yes') return true;
    if ($val === '0' || $val === 'false' || $val === 'no') return false;
    return $def;
}

// Always output JSON
header('Content-Type: application/json; charset=utf-8');

// Handle actions
$action = get_cli_param('action', '');

switch ($action) {
    case 'test_db':
        // Simple database test
        $result = $db->query("SELECT COUNT(*) as count FROM vend_outlets WHERE deleted_at IS NULL");
        $count = $result ? $result->fetch_assoc()['count'] : 0;
        echo json_encode([
            'success' => true,
            'message' => 'Database connection successful',
            'outlet_count' => $count,
            'timestamp' => date('c')
        ]);
        break;
        
    case 'neural_test':
        // Test Neural Brain integration
        require_once __DIR__ . '/neural_brain_integration.php';
        
        $neural = init_neural_brain($db);
        
        if ($neural) {
            $stats = $neural->getStats();
            $test_memory = $neural->storeSolution(
                'CLI Test Pattern',
                'Testing Neural Brain from CLI - ' . date('Y-m-d H:i:s'),
                ['cli', 'test', 'integration']
            );
            
            echo json_encode([
                'success' => true,
                'neural_enabled' => true,
                'session_id' => $neural->getSessionId(),
                'stats' => $stats,
                'test_memory_id' => $test_memory
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'neural_enabled' => false,
                'error' => 'Neural Brain initialization failed'
            ]);
        }
        break;
        
    case 'simple_seed':
        // Enhanced store seeding with comprehensive validation
        require_once __DIR__ . '/TransferLogger.php';
        require_once __DIR__ . '/TransferErrorHandler.php';
        
        $logger = new TransferLogger('CLI_SEED_' . date('YmdHis'), false);
        $error_handler = new TransferErrorHandler($logger);
        
        try {
            // Validate input parameters
            $params = [
                'target_outlet_id' => get_cli_param('target_outlet_id', ''),
                'simulate' => get_cli_param('simulate', '1'),
                'min_source_stock' => get_cli_param('min_source_stock', '5'),
                'candidate_limit' => get_cli_param('candidate_limit', '100'),
                'max_contribution' => get_cli_param('max_contribution', '2')
            ];
            
            $validation_rules = [
                'target_outlet_id' => [
                    'required' => true,
                    'type' => 'uuid',
                    'max_length' => 36
                ],
                'simulate' => [
                    'type' => 'bool'
                ],
                'min_source_stock' => [
                    'type' => 'int',
                    'validator' => function($val) {
                        $int_val = (int)$val;
                        return ($int_val >= 1 && $int_val <= 20) ? true : 'Must be between 1 and 20';
                    }
                ],
                'candidate_limit' => [
                    'type' => 'int',
                    'validator' => function($val) {
                        $int_val = (int)$val;
                        return ($int_val >= 10 && $int_val <= 1000) ? true : 'Must be between 10 and 1000';
                    }
                ],
                'max_contribution' => [
                    'type' => 'int',
                    'validator' => function($val) {
                        $int_val = (int)$val;
                        return ($int_val >= 1 && $int_val <= 10) ? true : 'Must be between 1 and 10';
                    }
                ]
            ];
            
            $error_handler->validateInput($params, $validation_rules, 'simple_seed');
            
            $target_outlet_id = $params['target_outlet_id'];
            
            // Validate outlet exists
            $outlet = $error_handler->wrapDatabaseOperation(function() use ($db, $target_outlet_id) {
                $stmt = $db->prepare("SELECT id, name FROM vend_outlets WHERE id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')");
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare outlet validation query: ' . $db->error);
                }
                
                $stmt->bind_param('s', $target_outlet_id);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Failed to execute outlet validation query: ' . $stmt->error);
                }
                
                return $stmt->get_result()->fetch_assoc();
            }, 'outlet validation');
            
            if (!$outlet) {
                throw new InvalidArgumentException('Target outlet not found or inactive');
            }
            
            $logger->info("Target outlet validated", [
                'outlet_id' => $outlet['id'],
                'outlet_name' => $outlet['name']
            ]);
            
        } catch (Exception $e) {
            $logger->error("Parameter validation failed", [
                'error' => $e->getMessage(),
                'params' => array_keys($params ?? [])
            ]);
            
            echo json_encode([
                'success' => false, 
                'error' => $e->getMessage(),
                'error_type' => 'validation_error',
                'session_id' => $logger->getSessionId()
            ]);
            break;
        }
        
        // Load NewStoreSeeder
        require_once __DIR__ . '/NewStoreSeeder.php';
        require_once __DIR__ . '/neural_brain_integration.php';
        
        $neural = init_neural_brain($db);
        $seeder = new NewStoreSeeder($db);
        
        $options = [
            'respect_pack_outers' => as_bool_safe(get_cli_param('respect_pack_outers', '1'), true),
            'balance_categories' => as_bool_safe(get_cli_param('balance_categories', '1'), true),
            'max_contribution_per_store' => as_int_safe(get_cli_param('max_contribution', '2'), 2, 1, 5),
            'min_source_stock' => as_int_safe(get_cli_param('min_stock', '5'), 5, 1, 20),
            'candidate_limit' => as_int_safe(get_cli_param('candidate_limit', '100'), 100, 10, 1000),
            'simulate' => $simulate
        ];
        
        $start_time = microtime(true);
        $result = $seeder->createSmartSeed($target_outlet, [], $options);
        $execution_time = round(microtime(true) - $start_time, 2);
        
        if ($result && isset($result['success']) && $result['success']) {
            // Store success in Neural Brain
            if ($neural) {
                $metrics = [
                    'execution_time' => $execution_time,
                    'products_count' => $result['products_count'] ?? 0,
                    'total_quantity' => $result['total_quantity'] ?? 0,
                    'source_stores' => $result['source_stores'] ?? 0,
                    'mode' => $simulate ? 'simulation' : 'live',
                    'target_outlet' => $outlet['name']
                ];
                
                $neural->reportTransferComplete($metrics, true);
            }
            
            $result['execution_time'] = $execution_time;
            $result['neural_brain'] = $neural ? $neural->getSessionId() : 'disabled';
            
        } else {
            // Store failure in Neural Brain
            if ($neural) {
                $error_msg = $result['error'] ?? 'Unknown error';
                $neural->storeError($error_msg, '', "Target: {$outlet['name']}, Simulate: $simulate");
            }
        }
        
        echo json_encode($result);
        break;
        
    case 'validate_transfer':
        // Validation test for real transfer creation
        $transfer_id = (int)get_cli_param('transfer_id', 0);
        
        if (!$transfer_id) {
            echo json_encode(['success' => false, 'error' => 'transfer_id required']);
            break;
        }
        
        // Check transfer exists
        $stmt = $db->prepare("
            SELECT t.*, 
                   vo_from.name as from_store, 
                   vo_to.name as to_store,
                   COUNT(spt.product_id) as product_count,
                   SUM(spt.qty_to_transfer) as total_quantity
            FROM stock_transfers t
            LEFT JOIN vend_outlets vo_from ON t.outlet_from = vo_from.id
            LEFT JOIN vend_outlets vo_to ON t.outlet_to = vo_to.id  
            LEFT JOIN stock_products_to_transfer spt ON t.transfer_id = spt.transfer_id
            WHERE t.transfer_id = ?
            GROUP BY t.transfer_id
        ");
        $stmt->bind_param('i', $transfer_id);
        $stmt->execute();
        $transfer = $stmt->get_result()->fetch_assoc();
        
        if ($transfer) {
            echo json_encode([
                'success' => true,
                'transfer' => $transfer,
                'validation' => [
                    'transfer_exists' => true,
                    'has_products' => (int)$transfer['product_count'] > 0,
                    'total_quantity' => (int)$transfer['total_quantity'],
                    'status' => $transfer['status']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Transfer not found']);
        }
        break;
        
    case 'get_outlets':
        // Simple outlet listing
        $stmt = $db->prepare("
            SELECT id as outlet_id, name as outlet_name
            FROM vend_outlets 
            WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
            ORDER BY name
            LIMIT 20
        ");
        $stmt->execute();
        $outlets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        echo json_encode(['success' => true, 'outlets' => $outlets]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}

$db->close();
?>
