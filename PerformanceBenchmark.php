#!/usr/bin/env php
<?php
/**
 * Performance Benchmark Script
 * Tests system under load and generates detailed performance reports
 */

declare(strict_types=1);

class TransferPerformanceBenchmark {
    private $db;
    private $logger;
    private $results = [];
    
    public function __construct($database) {
        $this->db = $database;
        
        require_once __DIR__ . '/TransferLogger.php';
        $this->logger = new TransferLogger('BENCH_' . date('YmdHis'), true);
    }
    
    public function runBenchmarks() {
        echo "🚀 Starting Performance Benchmarks...\n\n";
        
        $benchmarks = [
            'Database Performance' => $this->benchmarkDatabase(),
            'Seeder Performance' => $this->benchmarkSeeder(),
            'Memory Usage' => $this->benchmarkMemory(),
            'Concurrent Operations' => $this->benchmarkConcurrency(),
            'Large Dataset Handling' => $this->benchmarkLargeDataset()
        ];
        
        $this->generatePerformanceReport($benchmarks);
        
        return $this->results;
    }
    
    private function benchmarkDatabase() {
        $tests = [];
        
        // Test 1: Query Speed
        $tests['query_speed'] = $this->benchmarkTest('Database Query Speed', function() {
            $queries = [
                'simple_count' => "SELECT COUNT(*) FROM vend_outlets",
                'filtered_count' => "SELECT COUNT(*) FROM vend_outlets WHERE deleted_at IS NULL",
                'inventory_join' => "
                    SELECT COUNT(*) 
                    FROM vend_inventory vi 
                    LEFT JOIN vend_outlets vo ON vi.outlet_id = vo.id 
                    WHERE vi.inventory_level > 0
                ",
                'complex_aggregation' => "
                    SELECT 
                        vo.name,
                        COUNT(vi.id) as product_count,
                        AVG(vi.inventory_level) as avg_stock,
                        SUM(vi.inventory_level) as total_stock
                    FROM vend_outlets vo
                    LEFT JOIN vend_inventory vi ON vo.id = vi.outlet_id
                    WHERE (vo.deleted_at IS NULL OR vo.deleted_at = '0000-00-00 00:00:00')
                    GROUP BY vo.id, vo.name
                    HAVING product_count > 0
                    ORDER BY total_stock DESC
                    LIMIT 10
                "
            ];
            
            $results = [];
            $iterations = 5;
            
            foreach ($queries as $query_name => $query) {
                $times = [];
                
                for ($i = 0; $i < $iterations; $i++) {
                    $start = microtime(true);
                    $result = $this->db->query($query);
                    $end = microtime(true);
                    
                    if ($result) {
                        $times[] = ($end - $start) * 1000; // Convert to ms
                    }
                }
                
                if (!empty($times)) {
                    $results[$query_name] = [
                        'avg_ms' => round(array_sum($times) / count($times), 3),
                        'min_ms' => round(min($times), 3),
                        'max_ms' => round(max($times), 3),
                        'iterations' => $iterations
                    ];
                }
            }
            
            return $results;
        });
        
        // Test 2: Connection Pool
        $tests['connection_pool'] = $this->benchmarkTest('Connection Pool Performance', function() {
            $connection_times = [];
            $iterations = 10;
            
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                
                // Simulate connection test
                $result = $this->db->ping();
                
                $end = microtime(true);
                $connection_times[] = ($end - $start) * 1000;
            }
            
            return [
                'avg_ping_ms' => round(array_sum($connection_times) / count($connection_times), 3),
                'min_ping_ms' => round(min($connection_times), 3),
                'max_ping_ms' => round(max($connection_times), 3),
                'iterations' => $iterations
            ];
        });
        
        return $tests;
    }
    
    private function benchmarkSeeder() {
        $tests = [];
        
        require_once __DIR__ . '/NewStoreSeeder.php';
        
        // Test 1: Seeder Initialization Speed
        $tests['init_speed'] = $this->benchmarkTest('Seeder Initialization', function() {
            $init_times = [];
            $iterations = 10;
            
            for ($i = 0; $i < $iterations; $i++) {
                $start = microtime(true);
                $seeder = new NewStoreSeeder($this->db, false);
                $end = microtime(true);
                
                $init_times[] = ($end - $start) * 1000;
            }
            
            return [
                'avg_init_ms' => round(array_sum($init_times) / count($init_times), 3),
                'min_init_ms' => round(min($init_times), 3),
                'max_init_ms' => round(max($init_times), 3),
                'iterations' => $iterations
            ];
        });
        
        // Test 2: Seed Generation Performance
        $tests['seed_performance'] = $this->benchmarkTest('Seed Generation Performance', function() {
            $seeder = new NewStoreSeeder($this->db, false);
            
            // Get test outlet
            $result = $this->db->query("
                SELECT id FROM vend_outlets 
                WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
                LIMIT 1
            ");
            
            if (!$result || $result->num_rows == 0) {
                throw new Exception('No outlets available for benchmark');
            }
            
            $target_outlet_id = $result->fetch_assoc()['id'];
            
            $test_scenarios = [
                'small_batch' => ['candidate_limit' => 10, 'min_source_stock' => 5],
                'medium_batch' => ['candidate_limit' => 50, 'min_source_stock' => 3],
                'large_batch' => ['candidate_limit' => 100, 'min_source_stock' => 1]
            ];
            
            $results = [];
            
            foreach ($test_scenarios as $scenario_name => $options) {
                $options['simulate'] = true;
                
                $start = microtime(true);
                $memory_start = memory_get_usage(true);
                
                $result = $seeder->createSmartSeed($target_outlet_id, [], $options);
                
                $end = microtime(true);
                $memory_end = memory_get_usage(true);
                
                $results[$scenario_name] = [
                    'execution_ms' => round(($end - $start) * 1000, 3),
                    'memory_mb' => round(($memory_end - $memory_start) / 1024 / 1024, 3),
                    'success' => $result['success'] ?? false,
                    'products_processed' => $result['products_count'] ?? 0
                ];
            }
            
            return $results;
        });
        
        return $tests;
    }
    
    private function benchmarkMemory() {
        $tests = [];
        
        // Test 1: Memory Usage Under Load
        $tests['memory_load'] = $this->benchmarkTest('Memory Usage Under Load', function() {
            $initial_memory = memory_get_usage(true);
            $peak_memory = memory_get_peak_usage(true);
            
            // Create multiple seeders to test memory scaling
            $seeders = [];
            $seeder_count = 5;
            
            for ($i = 0; $i < $seeder_count; $i++) {
                require_once __DIR__ . '/NewStoreSeeder.php';
                $seeders[] = new NewStoreSeeder($this->db, false);
            }
            
            $after_creation = memory_get_usage(true);
            $peak_after_creation = memory_get_peak_usage(true);
            
            // Clean up
            unset($seeders);
            
            $after_cleanup = memory_get_usage(true);
            
            return [
                'initial_mb' => round($initial_memory / 1024 / 1024, 2),
                'after_creation_mb' => round($after_creation / 1024 / 1024, 2),
                'after_cleanup_mb' => round($after_cleanup / 1024 / 1024, 2),
                'peak_mb' => round($peak_after_creation / 1024 / 1024, 2),
                'memory_per_seeder_mb' => round(($after_creation - $initial_memory) / $seeder_count / 1024 / 1024, 2),
                'memory_limit' => ini_get('memory_limit')
            ];
        });
        
        return $tests;
    }
    
    private function benchmarkConcurrency() {
        $tests = [];
        
        // Test 1: Simulated Concurrent Access
        $tests['concurrent_simulation'] = $this->benchmarkTest('Concurrent Access Simulation', function() {
            // Simulate multiple concurrent operations
            $processes = [];
            $process_count = 3;
            
            $start_time = microtime(true);
            
            for ($i = 0; $i < $process_count; $i++) {
                // Simulate concurrent database queries
                $queries_per_process = 5;
                $process_times = [];
                
                for ($j = 0; $j < $queries_per_process; $j++) {
                    $query_start = microtime(true);
                    
                    $result = $this->db->query("
                        SELECT COUNT(*) 
                        FROM vend_inventory 
                        WHERE inventory_level > " . ($i + 1) . "
                    ");
                    
                    $query_end = microtime(true);
                    $process_times[] = ($query_end - $query_start) * 1000;
                    
                    // Small delay to simulate processing
                    usleep(10000); // 10ms
                }
                
                $processes[$i] = [
                    'avg_query_ms' => round(array_sum($process_times) / count($process_times), 3),
                    'queries_executed' => $queries_per_process
                ];
            }
            
            $total_time = microtime(true) - $start_time;
            
            return [
                'processes' => $processes,
                'total_simulation_time' => round($total_time, 3),
                'process_count' => $process_count
            ];
        });
        
        return $tests;
    }
    
    private function benchmarkLargeDataset() {
        $tests = [];
        
        // Test 1: Large Result Set Handling
        $tests['large_resultset'] = $this->benchmarkTest('Large Result Set Handling', function() {
            $queries = [
                'all_inventory' => "SELECT * FROM vend_inventory LIMIT 1000",
                'inventory_with_outlets' => "
                    SELECT vi.*, vo.name as outlet_name
                    FROM vend_inventory vi
                    LEFT JOIN vend_outlets vo ON vi.outlet_id = vo.id
                    WHERE vi.inventory_level > 0
                    LIMIT 500
                "
            ];
            
            $results = [];
            
            foreach ($queries as $query_name => $query) {
                $start = microtime(true);
                $memory_start = memory_get_usage(true);
                
                $result = $this->db->query($query);
                $rows_processed = 0;
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $rows_processed++;
                        // Process row (simulate work)
                    }
                }
                
                $end = microtime(true);
                $memory_end = memory_get_usage(true);
                
                $results[$query_name] = [
                    'execution_ms' => round(($end - $start) * 1000, 3),
                    'memory_used_mb' => round(($memory_end - $memory_start) / 1024 / 1024, 3),
                    'rows_processed' => $rows_processed,
                    'ms_per_row' => $rows_processed > 0 ? round((($end - $start) * 1000) / $rows_processed, 3) : 0
                ];
            }
            
            return $results;
        });
        
        return $tests;
    }
    
    private function benchmarkTest($test_name, $test_function) {
        $start_time = microtime(true);
        
        try {
            $this->logger->info("Running benchmark: {$test_name}");
            
            $result = $test_function();
            $execution_time = microtime(true) - $start_time;
            
            $benchmark_result = [
                'name' => $test_name,
                'status' => 'COMPLETED',
                'execution_time' => round($execution_time, 3),
                'result' => $result,
                'error' => null
            ];
            
        } catch (Exception $e) {
            $execution_time = microtime(true) - $start_time;
            
            $benchmark_result = [
                'name' => $test_name,
                'status' => 'FAILED',
                'execution_time' => round($execution_time, 3),
                'result' => null,
                'error' => $e->getMessage()
            ];
            
            $this->logger->error("Benchmark failed: {$test_name}", [
                'error' => $e->getMessage()
            ]);
        }
        
        $this->results[] = $benchmark_result;
        
        return $benchmark_result;
    }
    
    private function generatePerformanceReport($benchmark_groups) {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "TRANSFER ENGINE PERFORMANCE BENCHMARK REPORT\n";
        echo str_repeat("=", 80) . "\n\n";
        
        foreach ($benchmark_groups as $group_name => $benchmarks) {
            echo "⚡ {$group_name}\n";
            echo str_repeat("-", 50) . "\n";
            
            foreach ($benchmarks as $benchmark) {
                $status_icon = $benchmark['status'] === 'COMPLETED' ? '✅' : '❌';
                
                echo sprintf("%s %s (%.3fs)\n", 
                    $status_icon, 
                    $benchmark['name'], 
                    $benchmark['execution_time']
                );
                
                if ($benchmark['error']) {
                    echo "   ❌ Error: {$benchmark['error']}\n";
                } elseif ($benchmark['result']) {
                    $this->displayBenchmarkResults($benchmark['result']);
                }
            }
            
            echo "\n";
        }
        
        echo "📊 PERFORMANCE SUMMARY\n";
        echo str_repeat("-", 50) . "\n";
        
        $total_benchmarks = count($this->results);
        $completed_benchmarks = count(array_filter($this->results, function($r) {
            return $r['status'] === 'COMPLETED';
        }));
        
        echo "Total Benchmarks: {$total_benchmarks}\n";
        echo "Completed: {$completed_benchmarks}\n";
        echo "Failed: " . ($total_benchmarks - $completed_benchmarks) . "\n";
        echo "Session ID: " . $this->logger->getSessionId() . "\n";
        
        // Performance recommendations
        $this->generatePerformanceRecommendations();
        
        echo str_repeat("=", 80) . "\n";
    }
    
    private function displayBenchmarkResults($results, $indent = "   ") {
        foreach ($results as $key => $value) {
            if (is_array($value)) {
                echo "{$indent}📋 {$key}:\n";
                $this->displayBenchmarkResults($value, $indent . "  ");
            } else {
                echo "{$indent}• {$key}: {$value}\n";
            }
        }
    }
    
    private function generatePerformanceRecommendations() {
        echo "\n🎯 PERFORMANCE RECOMMENDATIONS\n";
        echo str_repeat("-", 50) . "\n";
        
        $recommendations = [];
        
        // Analyze results and generate recommendations
        foreach ($this->results as $result) {
            if ($result['status'] === 'COMPLETED' && $result['result']) {
                $this->analyzePerformanceResult($result, $recommendations);
            }
        }
        
        if (empty($recommendations)) {
            echo "✅ No performance issues detected. System performing well!\n";
        } else {
            foreach ($recommendations as $recommendation) {
                echo "⚠️ {$recommendation}\n";
            }
        }
        
        echo "\n💡 GENERAL OPTIMIZATION TIPS:\n";
        echo "• Monitor query execution times regularly\n";
        echo "• Consider database indexing for slow queries\n";
        echo "• Implement query caching for repeated operations\n";
        echo "• Use connection pooling for high-concurrency scenarios\n";
        echo "• Profile memory usage under production load\n";
    }
    
    private function analyzePerformanceResult($result, &$recommendations) {
        // Analyze database performance
        if (strpos($result['name'], 'Database') !== false && isset($result['result'])) {
            foreach ($result['result'] as $query_name => $metrics) {
                if (isset($metrics['avg_ms']) && $metrics['avg_ms'] > 500) {
                    $recommendations[] = "Query '{$query_name}' averaging {$metrics['avg_ms']}ms - consider optimization";
                }
            }
        }
        
        // Analyze memory usage
        if (strpos($result['name'], 'Memory') !== false && isset($result['result']['memory_per_seeder_mb'])) {
            if ($result['result']['memory_per_seeder_mb'] > 10) {
                $recommendations[] = "High memory usage per seeder: {$result['result']['memory_per_seeder_mb']}MB - consider optimization";
            }
        }
        
        // Analyze execution times
        if ($result['execution_time'] > 5) {
            $recommendations[] = "Benchmark '{$result['name']}' took {$result['execution_time']}s - investigate performance bottlenecks";
        }
    }
}

// Run benchmarks if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        require_once __DIR__ . '/../../functions/mysql.php';
        
        if (!connectToSQL()) {
            die("❌ Cannot connect to database for benchmarking\n");
        }
        
        global $con;
        
        $benchmark = new TransferPerformanceBenchmark($con);
        $benchmark->runBenchmarks();
        
    } catch (Exception $e) {
        echo "❌ Benchmark suite failed to initialize: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
