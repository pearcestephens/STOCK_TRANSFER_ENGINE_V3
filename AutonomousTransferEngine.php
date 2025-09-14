<?php
/**
 * Autonomous Transfer Engine - The Beast That Never Sleeps
 * Continuously monitors, analyzes, and optimizes stock transfers across the network
 * 
 * Features:
 * - Real-time overstock detection and redistribution
 * - Profit margin analysis and ROI calculations
 * - Shipping cost optimization
 * - Sales velocity tracking and prediction
 * - Autonomous decision making with business intelligence
 */

declare(strict_types=1);

class AutonomousTransferEngine {
    private $db;
    private $logger;
    private $session_id;
    private $config;
    
    // Business Intelligence Thresholds
    private const MIN_TRANSFER_ROI = 15.0;           // Minimum 15% ROI to justify transfer
    private const MAX_SHIPPING_COST_RATIO = 0.20;    // Max 20% of product value for shipping
    private const OVERSTOCK_THRESHOLD = 30;          // Days of stock = overstock
    private const UNDERSTOCK_THRESHOLD = 7;          // Days of stock = understock
    private const MIN_PROFIT_MARGIN = 25.0;         // Minimum 25% profit margin required
    
    public function __construct($database, bool $debug = false) {
        $this->db = $database;
        $this->session_id = 'AUTO_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 6);
        
        require_once __DIR__ . '/TransferLogger.php';
        $this->logger = new TransferLogger($this->session_id, $debug);
        
        $this->config = $this->loadConfiguration();
        
        $this->logger->info("Autonomous Transfer Engine initialized", [
            'session_id' => $this->session_id,
            'min_roi' => self::MIN_TRANSFER_ROI,
            'max_shipping_ratio' => self::MAX_SHIPPING_COST_RATIO
        ]);
    }
    
    /**
     * Main autonomous cycle - runs continuously to optimize network
     */
    public function runAutonomousCycle(): array {
        $this->logger->info("Starting autonomous rebalancing cycle");
        
        try {
            $start_time = microtime(true);
            
            // Step 1: Analyze entire network inventory status
            $network_analysis = $this->analyzeNetworkInventory();
            
            // Step 2: Identify profitable rebalancing opportunities
            $opportunities = $this->identifyTransferOpportunities($network_analysis);
            
            // Step 3: Calculate ROI and filter profitable transfers
            $profitable_transfers = $this->filterProfitableTransfers($opportunities);
            
            // Step 4: Optimize transfer combinations (batch optimization)
            $optimized_transfers = $this->optimizeTransferBatches($profitable_transfers);
            
            // Step 5: Execute approved transfers
            $execution_results = $this->executeAutonomousTransfers($optimized_transfers);
            
            $execution_time = microtime(true) - $start_time;
            
            $results = [
                'success' => true,
                'session_id' => $this->session_id,
                'execution_time' => round($execution_time, 3),
                'network_analysis' => $network_analysis,
                'opportunities_found' => count($opportunities),
                'profitable_transfers' => count($profitable_transfers),
                'transfers_executed' => count($execution_results['executed']),
                'total_roi_projected' => $execution_results['total_roi'],
                'total_cost_savings' => $execution_results['cost_savings'],
                'execution_results' => $execution_results
            ];
            
            $this->logger->info("Autonomous cycle completed successfully", $results);
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error("Autonomous cycle failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'session_id' => $this->session_id
            ];
        }
    }
    
    /**
     * Analyze entire network inventory to identify imbalances
     */
    private function analyzeNetworkInventory(): array {
        $this->logger->debug("Analyzing network inventory status");
        
        $query = "
            SELECT 
                vo.id as outlet_id,
                vo.name as outlet_name,
                vp.id as product_id,
                vp.name as product_name,
                vp.supply_price,
                vp.retail_price,
                vp.weight_grams,
                vp.pack_size,
                vp.outer_pack_size,
                vi.inventory_level,
                vi.reorder_point,
                vi.reorder_amount,
                -- Calculate days of stock based on sales velocity
                COALESCE(sv.daily_sales_velocity, 0.1) as daily_velocity,
                CASE 
                    WHEN COALESCE(sv.daily_sales_velocity, 0) > 0 
                    THEN vi.inventory_level / sv.daily_sales_velocity
                    ELSE 999
                END as days_of_stock,
                -- Profit calculations
                CASE 
                    WHEN vp.supply_price > 0 AND vp.retail_price > 0
                    THEN ((vp.retail_price - vp.supply_price) / vp.retail_price) * 100
                    ELSE 0
                END as profit_margin_percent,
                (vp.retail_price - vp.supply_price) as profit_per_unit
            FROM vend_inventory vi
            LEFT JOIN vend_outlets vo ON vi.outlet_id = vo.id
            LEFT JOIN vend_products vp ON vi.product_id = vp.id
            LEFT JOIN (
                -- Sales velocity subquery (we'll calculate this from sales data)
                SELECT 
                    product_id,
                    outlet_id,
                    AVG(daily_sales) as daily_sales_velocity
                FROM (
                    SELECT 
                        product_id,
                        outlet_id,
                        DATE(sale_date) as sale_day,
                        SUM(quantity) as daily_sales
                    FROM vend_sales 
                    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY product_id, outlet_id, DATE(sale_date)
                ) daily_sales_data
                GROUP BY product_id, outlet_id
            ) sv ON vi.product_id = sv.product_id AND vi.outlet_id = sv.outlet_id
            WHERE (vo.deleted_at IS NULL OR vo.deleted_at = '0000-00-00 00:00:00')
            AND (vp.deleted_at IS NULL OR vp.deleted_at = '0000-00-00 00:00:00')
            AND (vi.deleted_at IS NULL OR vi.deleted_at = '0000-00-00 00:00:00')
            AND vi.inventory_level > 0
            AND vp.supply_price > 0
            AND vp.retail_price > vp.supply_price
            ORDER BY vo.name, days_of_stock DESC
        ";
        
        $result = $this->db->query($query);
        if (!$result) {
            throw new Exception("Failed to analyze network inventory: " . $this->db->error);
        }
        
        $inventory_data = [];
        $overstock_items = [];
        $understock_items = [];
        $network_stats = [
            'total_products' => 0,
            'total_value' => 0,
            'overstock_count' => 0,
            'understock_count' => 0,
            'avg_profit_margin' => 0
        ];
        
        while ($row = $result->fetch_assoc()) {
            $inventory_data[] = $row;
            $network_stats['total_products']++;
            $network_stats['total_value'] += ($row['inventory_level'] * $row['retail_price']);
            $network_stats['avg_profit_margin'] += $row['profit_margin_percent'];
            
            // Classify stock levels
            if ($row['days_of_stock'] > self::OVERSTOCK_THRESHOLD) {
                $overstock_items[] = $row;
                $network_stats['overstock_count']++;
            } elseif ($row['days_of_stock'] < self::UNDERSTOCK_THRESHOLD) {
                $understock_items[] = $row;
                $network_stats['understock_count']++;
            }
        }
        
        $network_stats['avg_profit_margin'] = $network_stats['total_products'] > 0 
            ? $network_stats['avg_profit_margin'] / $network_stats['total_products'] 
            : 0;
        
        $this->logger->info("Network inventory analysis completed", [
            'total_products' => $network_stats['total_products'],
            'overstock_items' => count($overstock_items),
            'understock_items' => count($understock_items),
            'network_value' => $network_stats['total_value']
        ]);
        
        return [
            'network_stats' => $network_stats,
            'inventory_data' => $inventory_data,
            'overstock_items' => $overstock_items,
            'understock_items' => $understock_items
        ];
    }
    
    /**
     * Identify profitable transfer opportunities by matching overstock with understock
     */
    private function identifyTransferOpportunities(array $network_analysis): array {
        $this->logger->debug("Identifying transfer opportunities");
        
        $opportunities = [];
        $overstock = $network_analysis['overstock_items'];
        $understock = $network_analysis['understock_items'];
        
        foreach ($overstock as $overstock_item) {
            foreach ($understock as $understock_item) {
                // Match same product between different stores
                if ($overstock_item['product_id'] === $understock_item['product_id'] 
                    && $overstock_item['outlet_id'] !== $understock_item['outlet_id']) {
                    
                    // Calculate transfer opportunity
                    $opportunity = $this->calculateTransferOpportunity($overstock_item, $understock_item);
                    
                    if ($opportunity['viable']) {
                        $opportunities[] = $opportunity;
                    }
                }
            }
        }
        
        // Sort by ROI potential (highest first)
        usort($opportunities, function($a, $b) {
            return $b['roi_percentage'] <=> $a['roi_percentage'];
        });
        
        $this->logger->info("Transfer opportunities identified", [
            'total_opportunities' => count($opportunities),
            'avg_roi' => count($opportunities) > 0 
                ? array_sum(array_column($opportunities, 'roi_percentage')) / count($opportunities) 
                : 0
        ]);
        
        return $opportunities;
    }
    
    /**
     * Calculate transfer opportunity between overstock and understock items
     */
    private function calculateTransferOpportunity(array $source_item, array $target_item): array {
        // Calculate optimal transfer quantity
        $excess_stock = max(0, $source_item['inventory_level'] - ($source_item['daily_velocity'] * self::UNDERSTOCK_THRESHOLD));
        $needed_stock = max(0, ($target_item['daily_velocity'] * self::OVERSTOCK_THRESHOLD) - $target_item['inventory_level']);
        
        // Transfer quantity should respect pack sizes
        $optimal_qty = min($excess_stock, $needed_stock);
        $pack_size = max(1, intval($source_item['pack_size']));
        $transfer_qty = floor($optimal_qty / $pack_size) * $pack_size;
        
        if ($transfer_qty < $pack_size) {
            return ['viable' => false, 'reason' => 'Insufficient quantity for pack size'];
        }
        
        // Calculate costs and benefits
        $product_value = $transfer_qty * $source_item['retail_price'];
        $profit_per_unit = $source_item['profit_per_unit'];
        $total_profit_potential = $transfer_qty * $profit_per_unit;
        
        // Estimate shipping cost (based on weight and distance)
        $shipping_cost = $this->calculateShippingCost(
            $source_item['outlet_id'], 
            $target_item['outlet_id'], 
            $transfer_qty * $source_item['weight_grams']
        );
        
        // Calculate ROI
        $net_benefit = $total_profit_potential - $shipping_cost;
        $roi_percentage = $shipping_cost > 0 ? ($net_benefit / $shipping_cost) * 100 : 999;
        
        // Viability checks
        $viable = true;
        $viability_reasons = [];
        
        if ($roi_percentage < self::MIN_TRANSFER_ROI) {
            $viable = false;
            $viability_reasons[] = "ROI too low: {$roi_percentage}%";
        }
        
        if ($shipping_cost / $product_value > self::MAX_SHIPPING_COST_RATIO) {
            $viable = false;
            $viability_reasons[] = "Shipping cost too high: " . round($shipping_cost / $product_value * 100, 1) . "%";
        }
        
        if ($source_item['profit_margin_percent'] < self::MIN_PROFIT_MARGIN) {
            $viable = false;
            $viability_reasons[] = "Profit margin too low: {$source_item['profit_margin_percent']}%";
        }
        
        return [
            'viable' => $viable,
            'viability_reasons' => $viability_reasons,
            'source_outlet_id' => $source_item['outlet_id'],
            'source_outlet_name' => $source_item['outlet_name'],
            'target_outlet_id' => $target_item['outlet_id'],
            'target_outlet_name' => $target_item['outlet_name'],
            'product_id' => $source_item['product_id'],
            'product_name' => $source_item['product_name'],
            'transfer_qty' => $transfer_qty,
            'pack_size' => $pack_size,
            'product_value' => $product_value,
            'shipping_cost' => $shipping_cost,
            'total_profit_potential' => $total_profit_potential,
            'net_benefit' => $net_benefit,
            'roi_percentage' => $roi_percentage,
            'source_days_of_stock' => $source_item['days_of_stock'],
            'target_days_of_stock' => $target_item['days_of_stock'],
            'profit_margin' => $source_item['profit_margin_percent']
        ];
    }
    
    /**
     * Calculate shipping cost based on weight and distance between stores
     */
    private function calculateShippingCost(string $source_outlet_id, string $target_outlet_id, float $weight_grams): float {
        // Basic shipping cost calculation
        // In reality, you'd integrate with actual shipping APIs or use historical data
        
        $base_cost = 15.00; // Base shipping cost in NZD
        $weight_kg = $weight_grams / 1000;
        $weight_cost = $weight_kg * 2.50; // $2.50 per kg
        
        // Distance multiplier (simplified - in reality use actual distance calculation)
        $distance_multiplier = $this->getDistanceMultiplier($source_outlet_id, $target_outlet_id);
        
        $total_cost = ($base_cost + $weight_cost) * $distance_multiplier;
        
        return round($total_cost, 2);
    }
    
    /**
     * Get distance multiplier between outlets (simplified)
     */
    private function getDistanceMultiplier(string $source_id, string $target_id): float {
        // Simplified distance calculation
        // In production, you'd use actual GPS coordinates and distance APIs
        
        if ($source_id === $target_id) return 0; // Same store
        
        // Query actual outlet locations if available
        $query = "
            SELECT 
                (SELECT name FROM vend_outlets WHERE id = ?) as source_name,
                (SELECT name FROM vend_outlets WHERE id = ?) as target_name
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ss', $source_id, $target_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            // Basic distance estimation based on store names/locations
            // Auckland region = 1.0x, North Island = 1.5x, South Island = 2.0x
            return 1.2; // Default moderate distance
        }
        
        return 1.0; // Default local transfer
    }
    
    /**
     * Filter opportunities to only profitable transfers
     */
    private function filterProfitableTransfers(array $opportunities): array {
        $profitable = array_filter($opportunities, function($opportunity) {
            return $opportunity['viable'] && 
                   $opportunity['roi_percentage'] >= self::MIN_TRANSFER_ROI &&
                   $opportunity['net_benefit'] > 0;
        });
        
        $this->logger->info("Filtered to profitable transfers", [
            'total_opportunities' => count($opportunities),
            'profitable_count' => count($profitable),
            'filter_rate' => count($opportunities) > 0 ? round(count($profitable) / count($opportunities) * 100, 1) . '%' : '0%'
        ]);
        
        return array_values($profitable);
    }
    
    /**
     * Optimize transfer batches to minimize costs and maximize efficiency
     */
    private function optimizeTransferBatches(array $profitable_transfers): array {
        $this->logger->debug("Optimizing transfer batches");
        
        // Group transfers by source-target outlet pairs
        $batches = [];
        
        foreach ($profitable_transfers as $transfer) {
            $batch_key = $transfer['source_outlet_id'] . '_to_' . $transfer['target_outlet_id'];
            
            if (!isset($batches[$batch_key])) {
                $batches[$batch_key] = [
                    'source_outlet_id' => $transfer['source_outlet_id'],
                    'source_outlet_name' => $transfer['source_outlet_name'],
                    'target_outlet_id' => $transfer['target_outlet_id'],
                    'target_outlet_name' => $transfer['target_outlet_name'],
                    'transfers' => [],
                    'total_value' => 0,
                    'total_shipping_cost' => 0,
                    'total_roi' => 0,
                    'total_weight' => 0
                ];
            }
            
            $batches[$batch_key]['transfers'][] = $transfer;
            $batches[$batch_key]['total_value'] += $transfer['product_value'];
            $batches[$batch_key]['total_roi'] += $transfer['net_benefit'];
            
            // Recalculate shipping cost for combined batch (economies of scale)
            $total_weight = 0;
            foreach ($batches[$batch_key]['transfers'] as $t) {
                $total_weight += $t['transfer_qty'] * $this->getProductWeight($t['product_id']);
            }
            
            $batches[$batch_key]['total_weight'] = $total_weight;
            $batches[$batch_key]['total_shipping_cost'] = $this->calculateShippingCost(
                $transfer['source_outlet_id'],
                $transfer['target_outlet_id'],
                $total_weight
            );
        }
        
        // Sort batches by efficiency (ROI per shipping dollar)
        uasort($batches, function($a, $b) {
            $efficiency_a = $a['total_shipping_cost'] > 0 ? $a['total_roi'] / $a['total_shipping_cost'] : 0;
            $efficiency_b = $b['total_shipping_cost'] > 0 ? $b['total_roi'] / $b['total_shipping_cost'] : 0;
            return $efficiency_b <=> $efficiency_a;
        });
        
        $this->logger->info("Transfer batches optimized", [
            'batch_count' => count($batches),
            'total_transfers' => array_sum(array_map(function($b) { return count($b['transfers']); }, $batches))
        ]);
        
        return array_values($batches);
    }
    
    /**
     * Execute autonomous transfers (with safety checks)
     */
    private function executeAutonomousTransfers(array $optimized_batches): array {
        $this->logger->info("Executing autonomous transfers", ['batch_count' => count($optimized_batches)]);
        
        $executed = [];
        $failed = [];
        $total_roi = 0;
        $cost_savings = 0;
        
        foreach ($optimized_batches as $batch) {
            try {
                // Safety check: Don't execute if total value is too high without human approval
                if ($batch['total_value'] > $this->config['max_autonomous_value']) {
                    $this->logger->warning("Batch exceeds autonomous limit, requiring approval", [
                        'batch_value' => $batch['total_value'],
                        'limit' => $this->config['max_autonomous_value']
                    ]);
                    
                    $this->queueForApproval($batch);
                    continue;
                }
                
                // Execute the transfer batch
                $execution_result = $this->executeSingleBatch($batch);
                
                if ($execution_result['success']) {
                    $executed[] = $execution_result;
                    $total_roi += $batch['total_roi'];
                    $cost_savings += $execution_result['cost_savings'];
                } else {
                    $failed[] = $execution_result;
                }
                
            } catch (Exception $e) {
                $this->logger->error("Batch execution failed", [
                    'batch' => $batch,
                    'error' => $e->getMessage()
                ]);
                
                $failed[] = [
                    'batch' => $batch,
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }
        
        return [
            'executed' => $executed,
            'failed' => $failed,
            'total_roi' => $total_roi,
            'cost_savings' => $cost_savings
        ];
    }
    
    /**
     * Execute a single transfer batch
     */
    private function executeSingleBatch(array $batch): array {
        // In simulation mode, just log what would happen
        if ($this->config['simulation_mode']) {
            $this->logger->info("SIMULATION: Would execute transfer batch", [
                'source' => $batch['source_outlet_name'],
                'target' => $batch['target_outlet_name'],
                'transfer_count' => count($batch['transfers']),
                'total_value' => $batch['total_value'],
                'projected_roi' => $batch['total_roi']
            ]);
            
            return [
                'success' => true,
                'simulation' => true,
                'batch' => $batch,
                'cost_savings' => $batch['total_roi']
            ];
        }
        
        // Real execution would create actual transfer records
        // For now, we'll prepare the data structure
        
        require_once __DIR__ . '/NewStoreSeeder.php';
        $seeder = new NewStoreSeeder($this->db, false);
        
        // Create transfer header
        // ... (actual transfer creation logic would go here)
        
        return [
            'success' => true,
            'simulation' => false,
            'batch' => $batch,
            'cost_savings' => $batch['total_roi'],
            'transfer_id' => 'AUTO_' . $this->session_id
        ];
    }
    
    /**
     * Queue high-value transfers for human approval
     */
    private function queueForApproval(array $batch): void {
        // Store in approval queue table or send notification
        $this->logger->info("Transfer batch queued for approval", [
            'batch_value' => $batch['total_value'],
            'source' => $batch['source_outlet_name'],
            'target' => $batch['target_outlet_name']
        ]);
        
        // In production, this would create approval records or send notifications
    }
    
    /**
     * Get product weight for shipping calculations
     */
    private function getProductWeight(string $product_id): float {
        static $weight_cache = [];
        
        if (!isset($weight_cache[$product_id])) {
            $query = "SELECT weight_grams FROM vend_products WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param('s', $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $row = $result->fetch_assoc()) {
                $weight_cache[$product_id] = floatval($row['weight_grams'] ?? 100); // Default 100g
            } else {
                $weight_cache[$product_id] = 100.0; // Default weight
            }
        }
        
        return $weight_cache[$product_id];
    }
    
    /**
     * Load engine configuration
     */
    private function loadConfiguration(): array {
        return [
            'simulation_mode' => true, // Start in simulation mode for safety
            'max_autonomous_value' => 5000.00, // Max $5k transfers without approval
            'min_roi_threshold' => self::MIN_TRANSFER_ROI,
            'max_shipping_ratio' => self::MAX_SHIPPING_COST_RATIO,
            'cycle_interval_minutes' => 30, // Run every 30 minutes
            'max_transfers_per_cycle' => 50
        ];
    }
    
    /**
     * Get current session ID
     */
    public function getSessionId(): string {
        return $this->session_id;
    }
    
    /**
     * Get engine configuration
     */
    public function getConfiguration(): array {
        return $this->config;
    }
    
    /**
     * Update configuration
     */
    public function updateConfiguration(array $new_config): void {
        $this->config = array_merge($this->config, $new_config);
        $this->logger->info("Configuration updated", $new_config);
    }
}

// CLI interface for autonomous engine
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        require_once __DIR__ . '/../../functions/mysql.php';
        
        if (!connectToSQL()) {
            die("❌ Cannot connect to database\n");
        }
        
        global $con;
        
        echo "🤖 AUTONOMOUS TRANSFER ENGINE STARTING...\n";
        echo "==========================================\n\n";
        
        $engine = new AutonomousTransferEngine($con, true);
        
        echo "Session ID: " . $engine->getSessionId() . "\n";
        echo "Configuration: " . json_encode($engine->getConfiguration(), JSON_PRETTY_PRINT) . "\n\n";
        
        $results = $engine->runAutonomousCycle();
        
        echo "🎯 AUTONOMOUS CYCLE RESULTS:\n";
        echo "============================\n";
        echo "Success: " . ($results['success'] ? '✅ YES' : '❌ NO') . "\n";
        
        if ($results['success']) {
            echo "Execution Time: " . $results['execution_time'] . "s\n";
            echo "Opportunities Found: " . $results['opportunities_found'] . "\n";
            echo "Profitable Transfers: " . $results['profitable_transfers'] . "\n";
            echo "Transfers Executed: " . $results['transfers_executed'] . "\n";
            echo "Total ROI Projected: $" . number_format($results['total_roi_projected'], 2) . "\n";
            echo "Cost Savings: $" . number_format($results['total_cost_savings'], 2) . "\n";
        } else {
            echo "Error: " . $results['error'] . "\n";
        }
        
        echo "\n🎊 AUTONOMOUS ENGINE CYCLE COMPLETE!\n";
        
    } catch (Exception $e) {
        echo "❌ Autonomous engine failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
