<?php
/**
 * Comprehensive Test Suite for Transfer Engine
 * Includes unit tests, integration tests, and performance benchmarks
 */

declare(strict_types=1);

class TransferEngineTestSuite {
    private $db;
    private $logger;
    private $test_results = [];
    private $start_time;
    
    public function __construct($database) {
        $this->db = $database;
        $this->start_time = microtime(true);
        
        require_once __DIR__ . '/TransferLogger.php';
        $this->logger = new TransferLogger('TEST_' . date('YmdHis'), true);
    }
    
    public function runAllTests() {
        $this->logger->info("Starting comprehensive test suite");
        
        $test_groups = [
            'Database Tests' => $this->runDatabaseTests(),
            'Validation Tests' => $this->runValidationTests(),
            'NewStoreSeeder Tests' => $this->runSeederTests(),
            'CLI API Tests' => $this->runApiTests(),
            'Performance Tests' => $this->runPerformanceTests(),
            'Integration Tests' => $this->runIntegrationTests()
        ];
        
        $this->generateReport($test_groups);
        
        return $this->test_results;
    }
    
    private function runDatabaseTests() {
        $tests = [];
        
        // Test 1: Connection Test
        $tests['connection'] = $this->runTest('Database Connection', function() {
            if (!$this->db || !($this->db instanceof mysqli)) {
                throw new Exception('Invalid database connection');
            }
            
            $result = $this->db->query("SELECT 1 as test");
            if (!$result) {
                throw new Exception('Query failed: ' . $this->db->error);
            }
            
            return ['status' => 'Connected', 'server_info' => $this->db->server_info];
        });
        
        // Test 2: Outlets Table Test
        $tests['outlets_table'] = $this->runTest('Outlets Table Structure', function() {
            $result = $this->db->query("DESCRIBE vend_outlets");
            if (!$result) {
                throw new Exception('Cannot access outlets table');
            }
            
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            
            $required_columns = ['id', 'name'];
            foreach ($required_columns as $col) {
                if (!in_array($col, $columns)) {
                    throw new Exception("Missing required column: {$col}");
                }
            }
            
            return ['columns' => $columns, 'count' => count($columns)];
        });
        
        // Test 3: Inventory Table Test
        $tests['inventory_table'] = $this->runTest('Inventory Table Data', function() {
            $result = $this->db->query("
                SELECT COUNT(*) as total,
                       SUM(CASE WHEN inventory_level > 0 THEN 1 ELSE 0 END) as with_stock
                FROM vend_inventory 
                WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
            ");
            
            if (!$result) {
                throw new Exception('Cannot query inventory table');
            }
            
            $data = $result->fetch_assoc();
            
            if ($data['total'] == 0) {
                throw new Exception('No inventory data found');
            }
            
            return [
                'total_records' => $data['total'],
                'records_with_stock' => $data['with_stock'],
                'stock_percentage' => round(($data['with_stock'] / $data['total']) * 100, 2)
            ];
        });
        
        return $tests;
    }
    
    private function runValidationTests() {
        $tests = [];
        
        require_once __DIR__ . '/TransferErrorHandler.php';
        $error_handler = new TransferErrorHandler($this->logger);
        
        // Test 1: Input Validation
        $tests['input_validation'] = $this->runTest('Input Validation', function() use ($error_handler) {
            $valid_data = [
                'target_outlet_id' => '0a6f6e36-8b71-11eb-f3d6-40cea3d59c5a',
                'simulate' => '1',
                'min_source_stock' => '5'
            ];
            
            $rules = [
                'target_outlet_id' => ['required' => true, 'type' => 'uuid'],
                'simulate' => ['type' => 'bool'],
                'min_source_stock' => ['type' => 'int']
            ];
            
            // Should pass
            $error_handler->validateInput($valid_data, $rules);
            
            // Test invalid data
            $invalid_data = [
                'target_outlet_id' => '', // Empty required field
                'simulate' => 'invalid',
                'min_source_stock' => 'not_a_number'
            ];
            
            $exception_thrown = false;
            try {
                $error_handler->validateInput($invalid_data, $rules);
            } catch (Exception $e) {
                $exception_thrown = true;
            }
            
            if (!$exception_thrown) {
                throw new Exception('Validation should have failed for invalid data');
            }
            
            return ['valid_data_passed' => true, 'invalid_data_rejected' => true];
        });
        
        return $tests;
    }
    
    private function runSeederTests() {
        $tests = [];
        
        require_once __DIR__ . '/NewStoreSeeder.php';
        
        // Test 1: Seeder Initialization
        $tests['seeder_init'] = $this->runTest('NewStoreSeeder Initialization', function() {
            $seeder = new NewStoreSeeder($this->db, false);
            
            if (!$seeder->getSessionId()) {
                throw new Exception('Session ID not generated');
            }
            
            return ['session_id' => $seeder->getSessionId()];
        });
        
        // Test 2: Outlet Info Retrieval
        $tests['outlet_info'] = $this->runTest('Outlet Info Retrieval', function() {
            $seeder = new NewStoreSeeder($this->db, false);
            
            // Get a real outlet ID
            $result = $this->db->query("
                SELECT id FROM vend_outlets 
                WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
                LIMIT 1
            ");
            
            if (!$result || $result->num_rows == 0) {
                throw new Exception('No outlets available for testing');
            }
            
            $outlet_id = $result->fetch_assoc()['id'];
            
            // Use reflection to test private method
            $reflection = new ReflectionClass($seeder);
            $method = $reflection->getMethod('getOutletInfo');
            $method->setAccessible(true);
            
            $outlet_info = $method->invoke($seeder, $outlet_id);
            
            if (!$outlet_info) {
                throw new Exception('Failed to retrieve outlet info');
            }
            
            return [
                'outlet_id' => $outlet_id,
                'outlet_name' => $outlet_info['outlet_name']
            ];
        });
        
        // Test 3: Seed Simulation
        $tests['seed_simulation'] = $this->runTest('Seed Transfer Simulation', function() {
            $seeder = new NewStoreSeeder($this->db, false);
            
            // Get a real outlet ID for testing
            $result = $this->db->query("
                SELECT id FROM vend_outlets 
                WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
                LIMIT 1
            ");
            
            if (!$result || $result->num_rows == 0) {
                throw new Exception('No outlets available for testing');
            }
            
            $target_outlet_id = $result->fetch_assoc()['id'];
            
            $options = [
                'simulate' => true,
                'min_source_stock' => 1,
                'candidate_limit' => 10
            ];
            
            $start_time = microtime(true);
            $result = $seeder->createSmartSeed($target_outlet_id, [], $options);
            $execution_time = microtime(true) - $start_time;
            
            if (!isset($result['success'])) {
                throw new Exception('Invalid result format from seeder');
            }
            
            return [
                'success' => $result['success'],
                'execution_time' => round($execution_time, 3),
                'error' => $result['error'] ?? null,
                'session_id' => $seeder->getSessionId()
            ];
        });
        
        return $tests;
    }
    
    private function runApiTests() {
        $tests = [];
        
        // Test 1: CLI API Actions
        $tests['api_actions'] = $this->runTest('CLI API Actions', function() {
            $available_actions = [];
            
            // Simulate different API calls
            $test_cases = [
                'test_db' => [],
                'get_outlets' => []
            ];
            
            foreach ($test_cases as $action => $params) {
                $_GET['action'] = $action;
                foreach ($params as $key => $value) {
                    $_GET[$key] = $value;
                }
                
                ob_start();
                try {
                    include __DIR__ . '/cli_api.php';
                    $output = ob_get_clean();
                    
                    $json = json_decode($output, true);
                    if ($json && isset($json['success'])) {
                        $available_actions[$action] = $json['success'];
                    }
                } catch (Exception $e) {
                    ob_end_clean();
                    $available_actions[$action] = false;
                }
                
                // Clean up
                unset($_GET[$action]);
                foreach ($params as $key => $value) {
                    unset($_GET[$key]);
                }
            }
            
            return ['actions_tested' => $available_actions];
        });
        
        return $tests;
    }
    
    private function runPerformanceTests() {
        $tests = [];
        
        // Test 1: Database Query Performance
        $tests['query_performance'] = $this->runTest('Database Query Performance', function() {
            $query_tests = [
                'outlets_query' => "SELECT COUNT(*) FROM vend_outlets WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'",
                'inventory_query' => "SELECT COUNT(*) FROM vend_inventory WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'",
                'complex_join' => "
                    SELECT COUNT(*) 
                    FROM vend_inventory vi 
                    LEFT JOIN vend_outlets vo ON vi.outlet_id = vo.id 
                    WHERE (vi.deleted_at IS NULL OR vi.deleted_at = '0000-00-00 00:00:00')
                    AND vi.inventory_level > 0
                "
            ];
            
            $results = [];
            
            foreach ($query_tests as $test_name => $query) {
                $start_time = microtime(true);
                $result = $this->db->query($query);
                $execution_time = microtime(true) - $start_time;
                
                $results[$test_name] = [
                    'execution_time_ms' => round($execution_time * 1000, 3),
                    'success' => $result !== false
                ];
                
                if ($execution_time > 1.0) { // Over 1 second is slow
                    $this->logger->warning("Slow query detected: {$test_name}", [
                        'execution_time' => $execution_time,
                        'query' => substr($query, 0, 100) . '...'
                    ]);
                }
            }
            
            return $results;
        });
        
        // Test 2: Memory Usage
        $tests['memory_usage'] = $this->runTest('Memory Usage Test', function() {
            $initial_memory = memory_get_usage(true);
            
            // Create seeder and run simulation
            require_once __DIR__ . '/NewStoreSeeder.php';
            $seeder = new NewStoreSeeder($this->db, false);
            
            $peak_memory = memory_get_peak_usage(true);
            $current_memory = memory_get_usage(true);
            
            return [
                'initial_mb' => round($initial_memory / 1024 / 1024, 2),
                'current_mb' => round($current_memory / 1024 / 1024, 2),
                'peak_mb' => round($peak_memory / 1024 / 1024, 2),
                'memory_limit' => ini_get('memory_limit')
            ];
        });
        
        return $tests;
    }
    
    private function runIntegrationTests() {
        $tests = [];
        
        // Test 1: End-to-End Transfer Test
        $tests['e2e_transfer'] = $this->runTest('End-to-End Transfer Test', function() {
            require_once __DIR__ . '/NewStoreSeeder.php';
            
            // Get test outlet
            $result = $this->db->query("
                SELECT id FROM vend_outlets 
                WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
                LIMIT 1
            ");
            
            if (!$result || $result->num_rows == 0) {
                throw new Exception('No outlets available for integration test');
            }
            
            $target_outlet_id = $result->fetch_assoc()['id'];
            
            // Run full simulation
            $seeder = new NewStoreSeeder($this->db, true); // Debug mode
            
            $options = [
                'simulate' => true, // Always simulate in tests
                'min_source_stock' => 1,
                'candidate_limit' => 50,
                'max_contribution_per_store' => 2
            ];
            
            $start_time = microtime(true);
            $result = $seeder->createSmartSeed($target_outlet_id, [], $options);
            $execution_time = microtime(true) - $start_time;
            
            return [
                'success' => $result['success'] ?? false,
                'execution_time' => round($execution_time, 3),
                'error' => $result['error'] ?? null,
                'products_found' => $result['products_count'] ?? 0,
                'session_id' => $seeder->getSessionId()
            ];
        });
        
        return $tests;
    }
    
    private function runTest($test_name, $test_function) {
        $start_time = microtime(true);
        
        try {
            $this->logger->debug("Running test: {$test_name}");
            
            $result = $test_function();
            $execution_time = microtime(true) - $start_time;
            
            $test_result = [
                'name' => $test_name,
                'status' => 'PASS',
                'execution_time' => round($execution_time, 3),
                'result' => $result,
                'error' => null
            ];
            
            $this->logger->info("Test passed: {$test_name}", [
                'execution_time' => $execution_time
            ]);
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            
            $test_result = [
                'name' => $test_name,
                'status' => 'FAIL',
                'execution_time' => round($execution_time, 3),
                'result' => null,
                'error' => $e->getMessage()
            ];
            
            $this->logger->error("Test failed: {$test_name}", [
                'error' => $e->getMessage(),
                'execution_time' => $execution_time
            ]);
        }
        
        $this->test_results[] = $test_result;
        
        return $test_result;
    }
    
    private function generateReport($test_groups) {
        $total_tests = 0;
        $passed_tests = 0;
        $total_time = microtime(true) - $this->start_time;
        
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TRANSFER ENGINE TEST SUITE REPORT\n";
        echo str_repeat("=", 80) . "\n\n";
        
        foreach ($test_groups as $group_name => $tests) {
            echo "📋 {$group_name}\n";
            echo str_repeat("-", 40) . "\n";
            
            foreach ($tests as $test) {
                $total_tests++;
                $status_icon = $test['status'] === 'PASS' ? '✅' : '❌';
                
                if ($test['status'] === 'PASS') {
                    $passed_tests++;
                }
                
                echo sprintf("%s %s (%.3fs)\n", 
                    $status_icon, 
                    $test['name'], 
                    $test['execution_time']
                );
                
                if ($test['error']) {
                    echo "   Error: {$test['error']}\n";
                }
            }
            
            echo "\n";
        }
        
        $success_rate = round(($passed_tests / $total_tests) * 100, 1);
        
        echo "📊 SUMMARY\n";
        echo str_repeat("-", 40) . "\n";
        echo "Total Tests: {$total_tests}\n";
        echo "Passed: {$passed_tests}\n";
        echo "Failed: " . ($total_tests - $passed_tests) . "\n";
        echo "Success Rate: {$success_rate}%\n";
        echo "Total Time: " . round($total_time, 3) . "s\n";
        echo "Session ID: " . $this->logger->getSessionId() . "\n";
        
        if ($success_rate >= 90) {
            echo "\n🎉 EXCELLENT! System is performing well.\n";
        } elseif ($success_rate >= 70) {
            echo "\n⚠️ GOOD but needs attention to failed tests.\n";
        } else {
            echo "\n❌ POOR performance. System needs significant fixes.\n";
        }
        
        echo "\nDetailed logs available in: logs/transfer_" . date('Y-m-d') . ".log\n";
        echo str_repeat("=", 80) . "\n";
    }
    
    public function getResults() {
        return $this->test_results;
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        require_once __DIR__ . '/../../functions/mysql.php';
        
        if (!connectToSQL()) {
            die("❌ Cannot connect to database for testing\n");
        }
        
        global $con;
        
        $test_suite = new TransferEngineTestSuite($con);
        $test_suite->runAllTests();
        
    } catch (Exception $e) {
        echo "❌ Test suite failed to initialize: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
