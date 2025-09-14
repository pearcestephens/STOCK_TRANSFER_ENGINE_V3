<?php
/**
 * Event-Driven Transfer Trigger System
 * Monitors inventory levels and business conditions to trigger smart transfers
 * 
 * Triggers:
 * - Stock level thresholds (overstock/understock)
 * - Sales velocity changes
 * - Profit opportunity detection  
 * - Network imbalance alerts
 * - Seasonal demand patterns
 * - Cost optimization windows
 */

declare(strict_types=1);

class EventDrivenTransferTriggers {
    private $db;
    private $logger;
    private $session_id;
    private $autonomous_engine;
    private $trigger_config;
    
    // Event types
    private const EVENT_OVERSTOCK = 'overstock_detected';
    private const EVENT_UNDERSTOCK = 'understock_detected';  
    private const EVENT_SALES_SPIKE = 'sales_velocity_increase';
    private const EVENT_SALES_DROP = 'sales_velocity_decrease';
    private const EVENT_PROFIT_OPPORTUNITY = 'profit_opportunity';
    private const EVENT_COST_OPTIMIZATION = 'cost_optimization_window';
    private const EVENT_NETWORK_IMBALANCE = 'network_imbalance';
    
    public function __construct($database) {
        $this->db = $database;
        $this->session_id = 'TRIGGER_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 6);
        
        require_once __DIR__ . '/TransferLogger.php';
        $this->logger = new TransferLogger($this->session_id, true);
        
        require_once __DIR__ . '/AutonomousTransferEngine.php';
        $this->autonomous_engine = new AutonomousTransferEngine($database);
        
        $this->trigger_config = $this->loadTriggerConfiguration();
        
        $this->initializeEventTables();
        
        $this->logger->info("Event-driven trigger system initialized", [
            'session_id' => $this->session_id,
            'triggers_enabled' => count($this->trigger_config['enabled_triggers'])
        ]);
    }
    
    /**
     * Main monitoring cycle - checks for trigger conditions
     */
    public function runTriggerMonitoring(): array {
        $this->logger->info("Starting trigger monitoring cycle");
        
        try {
            $start_time = microtime(true);
            $triggered_events = [];
            
            // Check each trigger type
            foreach ($this->trigger_config['enabled_triggers'] as $trigger_type) {
                $events = $this->checkTrigger($trigger_type);
                $triggered_events = array_merge($triggered_events, $events);
            }
            
            // Process triggered events
            $processing_results = $this->processTriggerEvents($triggered_events);
            
            $execution_time = microtime(true) - $start_time;
            
            $results = [
                'success' => true,
                'session_id' => $this->session_id,
                'execution_time' => round($execution_time, 3),
                'events_detected' => count($triggered_events),
                'events_processed' => count($processing_results),
                'transfers_initiated' => $this->countInitiatedTransfers($processing_results),
                'triggered_events' => $triggered_events,
                'processing_results' => $processing_results
            ];
            
            $this->logger->info("Trigger monitoring completed", [
                'events_detected' => $results['events_detected'],
                'transfers_initiated' => $results['transfers_initiated']
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error("Trigger monitoring failed", [
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
     * Check specific trigger type for events
     */
    private function checkTrigger(string $trigger_type): array {
        $this->logger->debug("Checking trigger: {$trigger_type}");
        
        switch ($trigger_type) {
            case self::EVENT_OVERSTOCK:
                return $this->checkOverstockTrigger();
                
            case self::EVENT_UNDERSTOCK:
                return $this->checkUnderstockTrigger();
                
            case self::EVENT_SALES_SPIKE:
                return $this->checkSalesVelocityTrigger('increase');
                
            case self::EVENT_SALES_DROP:
                return $this->checkSalesVelocityTrigger('decrease');
                
            case self::EVENT_PROFIT_OPPORTUNITY:
                return $this->checkProfitOpportunityTrigger();
                
            case self::EVENT_COST_OPTIMIZATION:
                return $this->checkCostOptimizationTrigger();
                
            case self::EVENT_NETWORK_IMBALANCE:
                return $this->checkNetworkImbalanceTrigger();
                
            default:
                $this->logger->warning("Unknown trigger type: {$trigger_type}");
                return [];
        }
    }
    
    /**
     * Check for overstock situations requiring redistribution
     */
    private function checkOverstockTrigger(): array {
        $query = "
            SELECT 
                vo.id as outlet_id,
                vo.name as outlet_name,
                vp.id as product_id,
                vp.name as product_name,
                vi.inventory_level,
                vi.reorder_point,
                COALESCE(sv.daily_velocity, 0.1) as daily_velocity,
                CASE 
                    WHEN COALESCE(sv.daily_velocity, 0) > 0 
                    THEN vi.inventory_level / sv.daily_velocity
                    ELSE 999
                END as days_of_stock,
                vp.retail_price,
                vp.supply_price,
                (vp.retail_price - vp.supply_price) as profit_per_unit
            FROM vend_inventory vi
            JOIN vend_outlets vo ON vi.outlet_id = vo.id
            JOIN vend_products vp ON vi.product_id = vp.id
            LEFT JOIN (
                -- Recent sales velocity (last 14 days)
                SELECT 
                    product_id,
                    outlet_id,
                    AVG(daily_sales) as daily_velocity
                FROM (
                    SELECT 
                        product_id,
                        outlet_id,
                        DATE(sale_date) as sale_day,
                        SUM(quantity) as daily_sales
                    FROM vend_sales 
                    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                    GROUP BY product_id, outlet_id, DATE(sale_date)
                ) daily_data
                GROUP BY product_id, outlet_id
                HAVING daily_velocity > 0
            ) sv ON vi.product_id = sv.product_id AND vi.outlet_id = sv.outlet_id
            WHERE (vo.deleted_at IS NULL OR vo.deleted_at = '0000-00-00 00:00:00')
            AND (vp.deleted_at IS NULL OR vp.deleted_at = '0000-00-00 00:00:00')
            AND (vi.deleted_at IS NULL OR vi.deleted_at = '0000-00-00 00:00:00')
            AND vi.inventory_level > 0
            HAVING days_of_stock > {$this->trigger_config['overstock_days_threshold']}
            AND vi.inventory_level > (vi.reorder_point * {$this->trigger_config['overstock_multiplier']})
            ORDER BY days_of_stock DESC, profit_per_unit DESC
            LIMIT {$this->trigger_config['max_events_per_trigger']}
        ";
        
        $result = $this->db->query($query);
        $events = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $excess_stock = $row['inventory_level'] - ($row['reorder_point'] * 2); // Keep 2x reorder as buffer
                
                if ($excess_stock > 0) {
                    $events[] = [
                        'type' => self::EVENT_OVERSTOCK,
                        'outlet_id' => $row['outlet_id'],
                        'outlet_name' => $row['outlet_name'],
                        'product_id' => $row['product_id'],
                        'product_name' => $row['product_name'],
                        'current_stock' => $row['inventory_level'],
                        'days_of_stock' => round($row['days_of_stock'], 1),
                        'excess_stock' => $excess_stock,
                        'daily_velocity' => $row['daily_velocity'],
                        'profit_potential' => $excess_stock * $row['profit_per_unit'],
                        'priority' => $this->calculateEventPriority(self::EVENT_OVERSTOCK, $row),
                        'detected_at' => date('Y-m-d H:i:s'),
                        'metadata' => [
                            'reorder_point' => $row['reorder_point'],
                            'retail_price' => $row['retail_price'],
                            'supply_price' => $row['supply_price']
                        ]
                    ];
                }
            }
        }
        
        $this->logger->info("Overstock trigger check completed", [
            'events_found' => count($events)
        ]);
        
        return $events;
    }
    
    /**
     * Check for understock situations requiring replenishment
     */
    private function checkUnderstockTrigger(): array {
        $query = "
            SELECT 
                vo.id as outlet_id,
                vo.name as outlet_name,
                vp.id as product_id,
                vp.name as product_name,
                vi.inventory_level,
                vi.reorder_point,
                vi.reorder_amount,
                COALESCE(sv.daily_velocity, 0.1) as daily_velocity,
                CASE 
                    WHEN COALESCE(sv.daily_velocity, 0) > 0 
                    THEN vi.inventory_level / sv.daily_velocity
                    ELSE 999
                END as days_of_stock,
                vp.retail_price,
                vp.supply_price
            FROM vend_inventory vi
            JOIN vend_outlets vo ON vi.outlet_id = vo.id
            JOIN vend_products vp ON vi.product_id = vp.id
            LEFT JOIN (
                -- Recent sales velocity
                SELECT 
                    product_id,
                    outlet_id,
                    AVG(daily_sales) as daily_velocity
                FROM (
                    SELECT 
                        product_id,
                        outlet_id,
                        DATE(sale_date) as sale_day,
                        SUM(quantity) as daily_sales
                    FROM vend_sales 
                    WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                    GROUP BY product_id, outlet_id, DATE(sale_date)
                ) daily_data
                GROUP BY product_id, outlet_id
                HAVING daily_velocity > 0
            ) sv ON vi.product_id = sv.product_id AND vi.outlet_id = sv.outlet_id
            WHERE (vo.deleted_at IS NULL OR vo.deleted_at = '0000-00-00 00:00:00')
            AND (vp.deleted_at IS NULL OR vp.deleted_at = '0000-00-00 00:00:00')
            AND (vi.deleted_at IS NULL OR vi.deleted_at = '0000-00-00 00:00:00')
            HAVING (vi.inventory_level <= vi.reorder_point 
                   OR days_of_stock <= {$this->trigger_config['understock_days_threshold']})
            AND sv.daily_velocity > {$this->trigger_config['min_velocity_for_understock']}
            ORDER BY days_of_stock ASC, sv.daily_velocity DESC
            LIMIT {$this->trigger_config['max_events_per_trigger']}
        ";
        
        $result = $this->db->query($query);
        $events = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $stock_needed = max($row['reorder_amount'], $row['daily_velocity'] * 14); // 14 days stock
                
                $events[] = [
                    'type' => self::EVENT_UNDERSTOCK,
                    'outlet_id' => $row['outlet_id'],
                    'outlet_name' => $row['outlet_name'],
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'current_stock' => $row['inventory_level'],
                    'days_of_stock' => round($row['days_of_stock'], 1),
                    'stock_needed' => round($stock_needed),
                    'daily_velocity' => $row['daily_velocity'],
                    'urgency' => $this->calculateUrgency($row['days_of_stock'], $row['daily_velocity']),
                    'priority' => $this->calculateEventPriority(self::EVENT_UNDERSTOCK, $row),
                    'detected_at' => date('Y-m-d H:i:s'),
                    'metadata' => [
                        'reorder_point' => $row['reorder_point'],
                        'reorder_amount' => $row['reorder_amount']
                    ]
                ];
            }
        }
        
        $this->logger->info("Understock trigger check completed", [
            'events_found' => count($events)
        ]);
        
        return $events;
    }
    
    /**
     * Check for sales velocity changes (spikes or drops)
     */
    private function checkSalesVelocityTrigger(string $direction): array {
        $comparison = $direction === 'increase' ? '>' : '<';
        $velocity_threshold = $direction === 'increase' 
            ? $this->trigger_config['velocity_spike_threshold']
            : $this->trigger_config['velocity_drop_threshold'];
        
        $query = "
            SELECT 
                outlet_id,
                product_id,
                recent_velocity,
                historical_velocity,
                velocity_change_percent,
                outlet_name,
                product_name,
                current_stock
            FROM (
                SELECT 
                    vi.outlet_id,
                    vi.product_id,
                    vo.name as outlet_name,
                    vp.name as product_name,
                    vi.inventory_level as current_stock,
                    COALESCE(recent.daily_velocity, 0) as recent_velocity,
                    COALESCE(historical.daily_velocity, 0) as historical_velocity,
                    CASE 
                        WHEN COALESCE(historical.daily_velocity, 0) > 0
                        THEN ((COALESCE(recent.daily_velocity, 0) - COALESCE(historical.daily_velocity, 0)) 
                              / COALESCE(historical.daily_velocity, 0)) * 100
                        ELSE 0
                    END as velocity_change_percent
                FROM vend_inventory vi
                JOIN vend_outlets vo ON vi.outlet_id = vo.id
                JOIN vend_products vp ON vi.product_id = vp.id
                LEFT JOIN (
                    -- Recent velocity (last 7 days)
                    SELECT 
                        product_id,
                        outlet_id,
                        AVG(daily_sales) as daily_velocity
                    FROM (
                        SELECT 
                            product_id,
                            outlet_id,
                            DATE(sale_date) as sale_day,
                            SUM(quantity) as daily_sales
                        FROM vend_sales 
                        WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY product_id, outlet_id, DATE(sale_date)
                    ) recent_data
                    GROUP BY product_id, outlet_id
                ) recent ON vi.product_id = recent.product_id AND vi.outlet_id = recent.outlet_id
                LEFT JOIN (
                    -- Historical velocity (8-28 days ago)
                    SELECT 
                        product_id,
                        outlet_id,
                        AVG(daily_sales) as daily_velocity
                    FROM (
                        SELECT 
                            product_id,
                            outlet_id,
                            DATE(sale_date) as sale_day,
                            SUM(quantity) as daily_sales
                        FROM vend_sales 
                        WHERE sale_date >= DATE_SUB(NOW(), INTERVAL 28 DAY)
                        AND sale_date < DATE_SUB(NOW(), INTERVAL 7 DAY)
                        GROUP BY product_id, outlet_id, DATE(sale_date)
                    ) historical_data
                    GROUP BY product_id, outlet_id
                ) historical ON vi.product_id = historical.product_id AND vi.outlet_id = historical.outlet_id
                WHERE (vo.deleted_at IS NULL OR vo.deleted_at = '0000-00-00 00:00:00')
                AND (vp.deleted_at IS NULL OR vp.deleted_at = '0000-00-00 00:00:00')
                AND (vi.deleted_at IS NULL OR vi.deleted_at = '0000-00-00 00:00:00')
            ) velocity_analysis
            WHERE ABS(velocity_change_percent) {$comparison}= {$velocity_threshold}
            AND recent_velocity > 0.1  -- Must have some recent sales
            AND historical_velocity > 0.1  -- Must have historical baseline
            ORDER BY ABS(velocity_change_percent) DESC
            LIMIT {$this->trigger_config['max_events_per_trigger']}
        ";
        
        $result = $this->db->query($query);
        $events = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $event_type = $direction === 'increase' ? self::EVENT_SALES_SPIKE : self::EVENT_SALES_DROP;
                
                $events[] = [
                    'type' => $event_type,
                    'outlet_id' => $row['outlet_id'],
                    'outlet_name' => $row['outlet_name'],
                    'product_id' => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'current_stock' => $row['current_stock'],
                    'recent_velocity' => round($row['recent_velocity'], 2),
                    'historical_velocity' => round($row['historical_velocity'], 2),
                    'velocity_change_percent' => round($row['velocity_change_percent'], 1),
                    'priority' => $this->calculateEventPriority($event_type, $row),
                    'detected_at' => date('Y-m-d H:i:s'),
                    'action_recommended' => $direction === 'increase' ? 'increase_stock' : 'redistribute_excess'
                ];
            }
        }
        
        $this->logger->info("Sales velocity trigger check completed", [
            'direction' => $direction,
            'events_found' => count($events)
        ]);
        
        return $events;
    }
    
    /**
     * Check for profit optimization opportunities
     */
    private function checkProfitOpportunityTrigger(): array {
        // This would analyze price differences, margin opportunities, etc.
        // For now, return empty array - would be expanded based on specific business logic
        return [];
    }
    
    /**
     * Check for cost optimization windows (bulk shipping opportunities)
     */
    private function checkCostOptimizationTrigger(): array {
        // This would identify opportunities to combine multiple transfers for cost savings
        // For now, return empty array - would be expanded based on shipping cost analysis
        return [];
    }
    
    /**
     * Check for overall network imbalances
     */
    private function checkNetworkImbalanceTrigger(): array {
        // This would analyze network-wide stock distribution patterns
        // For now, return empty array - would be expanded based on network analysis
        return [];
    }
    
    /**
     * Process triggered events and initiate appropriate actions
     */
    private function processTriggerEvents(array $events): array {
        $processing_results = [];
        
        foreach ($events as $event) {
            try {
                $this->logger->info("Processing trigger event", [
                    'type' => $event['type'],
                    'outlet' => $event['outlet_name'],
                    'product' => $event['product_name'],
                    'priority' => $event['priority']
                ]);
                
                $action_result = $this->executeEventAction($event);
                
                // Store event in database
                $event_id = $this->storeEventRecord($event, $action_result);
                
                $processing_results[] = [
                    'event_id' => $event_id,
                    'event_type' => $event['type'],
                    'action_taken' => $action_result['action'],
                    'success' => $action_result['success'],
                    'transfer_initiated' => $action_result['transfer_initiated'] ?? false,
                    'message' => $action_result['message'] ?? null
                ];
                
            } catch (Exception $e) {
                $this->logger->error("Event processing failed", [
                    'event' => $event,
                    'error' => $e->getMessage()
                ]);
                
                $processing_results[] = [
                    'event_type' => $event['type'],
                    'action_taken' => 'error',
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $processing_results;
    }
    
    /**
     * Execute appropriate action for triggered event
     */
    private function executeEventAction(array $event): array {
        switch ($event['type']) {
            case self::EVENT_OVERSTOCK:
                return $this->handleOverstockEvent($event);
                
            case self::EVENT_UNDERSTOCK:
                return $this->handleUnderstockEvent($event);
                
            case self::EVENT_SALES_SPIKE:
                return $this->handleSalesSpikeEvent($event);
                
            case self::EVENT_SALES_DROP:
                return $this->handleSalesDropEvent($event);
                
            default:
                return [
                    'action' => 'no_action',
                    'success' => false,
                    'message' => 'Unknown event type'
                ];
        }
    }
    
    /**
     * Handle overstock event by initiating redistribution
     */
    private function handleOverstockEvent(array $event): array {
        // Check if autonomous engine should handle this
        if ($event['priority'] >= $this->trigger_config['auto_action_threshold']) {
            // Run autonomous engine with specific focus on this product/outlet
            $result = $this->autonomous_engine->runAutonomousCycle();
            
            return [
                'action' => 'autonomous_redistribution',
                'success' => $result['success'],
                'transfer_initiated' => $result['success'] && $result['transfers_executed'] > 0,
                'message' => "Autonomous engine initiated for overstock redistribution"
            ];
        } else {
            // Queue for manual review
            return [
                'action' => 'queue_for_review',
                'success' => true,
                'transfer_initiated' => false,
                'message' => "Overstock queued for manual review (priority: {$event['priority']})"
            ];
        }
    }
    
    /**
     * Handle understock event by initiating replenishment
     */
    private function handleUnderstockEvent(array $event): array {
        // Similar logic to overstock but for replenishment
        if ($event['urgency'] === 'critical' || $event['priority'] >= $this->trigger_config['auto_action_threshold']) {
            // Trigger immediate replenishment transfer
            require_once __DIR__ . '/NewStoreSeeder.php';
            $seeder = new NewStoreSeeder($this->db, false);
            
            // Create targeted replenishment transfer
            $result = $seeder->createSmartSeed(
                $event['outlet_id'],
                [$event['product_id']], // Specific product focus
                [
                    'simulate' => $this->trigger_config['simulation_mode'],
                    'min_source_stock' => 1,
                    'candidate_limit' => 10,
                    'target_quantity' => $event['stock_needed']
                ]
            );
            
            return [
                'action' => 'replenishment_transfer',
                'success' => $result['success'],
                'transfer_initiated' => $result['success'],
                'message' => "Replenishment transfer initiated for critical understock"
            ];
        } else {
            return [
                'action' => 'queue_for_review',
                'success' => true,
                'transfer_initiated' => false,
                'message' => "Understock queued for manual review"
            ];
        }
    }
    
    /**
     * Handle sales spike by increasing stock levels
     */
    private function handleSalesSpikeEvent(array $event): array {
        return [
            'action' => 'increase_stock_recommendation',
            'success' => true,
            'transfer_initiated' => false,
            'message' => "Sales spike detected - recommend increasing stock levels"
        ];
    }
    
    /**
     * Handle sales drop by redistributing excess stock
     */
    private function handleSalesDropEvent(array $event): array {
        return [
            'action' => 'redistribute_recommendation',
            'success' => true,
            'transfer_initiated' => false,
            'message' => "Sales drop detected - recommend redistributing excess stock"
        ];
    }
    
    /**
     * Calculate event priority (0-100 scale)
     */
    private function calculateEventPriority(string $event_type, array $data): int {
        $priority = 50; // Base priority
        
        switch ($event_type) {
            case self::EVENT_OVERSTOCK:
                $days_excess = max(0, $data['days_of_stock'] - 30);
                $priority += min(30, $days_excess); // More days = higher priority
                break;
                
            case self::EVENT_UNDERSTOCK:
                $days_remaining = $data['days_of_stock'];
                if ($days_remaining <= 1) $priority += 40;
                elseif ($days_remaining <= 3) $priority += 30;
                elseif ($days_remaining <= 7) $priority += 20;
                break;
                
            case self::EVENT_SALES_SPIKE:
            case self::EVENT_SALES_DROP:
                $change_magnitude = abs($data['velocity_change_percent']);
                $priority += min(30, $change_magnitude / 10);
                break;
        }
        
        return min(100, max(0, $priority));
    }
    
    /**
     * Calculate urgency level for understock
     */
    private function calculateUrgency(float $days_of_stock, float $daily_velocity): string {
        if ($days_of_stock <= 1) return 'critical';
        if ($days_of_stock <= 3) return 'high';
        if ($days_of_stock <= 7) return 'medium';
        return 'low';
    }
    
    // Additional helper methods...
    
    public function getSessionId(): string {
        return $this->session_id;
    }
    
    private function loadTriggerConfiguration(): array {
        return [
            'enabled_triggers' => [
                self::EVENT_OVERSTOCK,
                self::EVENT_UNDERSTOCK,
                self::EVENT_SALES_SPIKE,
                self::EVENT_SALES_DROP
            ],
            'overstock_days_threshold' => 45,
            'understock_days_threshold' => 7,
            'overstock_multiplier' => 3,
            'velocity_spike_threshold' => 50, // 50% increase
            'velocity_drop_threshold' => 30, // 30% decrease
            'min_velocity_for_understock' => 0.5,
            'auto_action_threshold' => 75,
            'max_events_per_trigger' => 20,
            'simulation_mode' => true
        ];
    }
    
    private function initializeEventTables(): void {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS transfer_trigger_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(50) NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                outlet_id VARCHAR(255),
                product_id VARCHAR(255),
                priority INT DEFAULT 50,
                event_data JSON,
                action_taken VARCHAR(100),
                action_success BOOLEAN DEFAULT FALSE,
                transfer_initiated BOOLEAN DEFAULT FALSE,
                detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL,
                INDEX idx_event_type (event_type),
                INDEX idx_detected_at (detected_at),
                INDEX idx_session_id (session_id)
            )
        ");
    }
    
    private function storeEventRecord(array $event, array $action_result): int {
        $stmt = $this->db->prepare("
            INSERT INTO transfer_trigger_events 
            (session_id, event_type, outlet_id, product_id, priority, event_data, action_taken, action_success, transfer_initiated, processed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $event_data_json = json_encode($event);
        $action_taken = $action_result['action'] ?? 'unknown';
        $action_success = $action_result['success'] ?? false;
        $transfer_initiated = $action_result['transfer_initiated'] ?? false;
        
        $stmt->bind_param('ssssisiii', 
            $this->session_id,
            $event['type'],
            $event['outlet_id'],
            $event['product_id'],
            $event['priority'],
            $event_data_json,
            $action_taken,
            $action_success,
            $transfer_initiated
        );
        
        $stmt->execute();
        
        return $this->db->insert_id;
    }
    
    private function countInitiatedTransfers(array $processing_results): int {
        return count(array_filter($processing_results, function($result) {
            return $result['transfer_initiated'] ?? false;
        }));
    }
}

// CLI interface for trigger system
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        require_once __DIR__ . '/../../functions/mysql.php';
        
        if (!connectToSQL()) {
            die("❌ Cannot connect to database\n");
        }
        
        global $con;
        
        echo "⚡ EVENT-DRIVEN TRANSFER TRIGGERS\n";
        echo "==================================\n\n";
        
        $triggers = new EventDrivenTransferTriggers($con);
        
        echo "Session ID: " . $triggers->getSessionId() . "\n\n";
        
        $results = $triggers->runTriggerMonitoring();
        
        echo "🎯 TRIGGER MONITORING RESULTS:\n";
        echo "==============================\n";
        
        if ($results['success']) {
            echo "✅ Monitoring completed successfully\n";
            echo "Execution Time: {$results['execution_time']}s\n";
            echo "Events Detected: {$results['events_detected']}\n";
            echo "Events Processed: {$results['events_processed']}\n";
            echo "Transfers Initiated: {$results['transfers_initiated']}\n\n";
            
            if (!empty($results['triggered_events'])) {
                echo "📋 DETECTED EVENTS:\n";
                foreach ($results['triggered_events'] as $i => $event) {
                    echo "  " . ($i + 1) . ". {$event['type']} - {$event['outlet_name']} - {$event['product_name']} (Priority: {$event['priority']})\n";
                }
                echo "\n";
            }
            
            if (!empty($results['processing_results'])) {
                echo "⚙️ PROCESSING RESULTS:\n";
                foreach ($results['processing_results'] as $i => $result) {
                    $status = $result['success'] ? '✅' : '❌';
                    echo "  " . ($i + 1) . ". {$status} {$result['action_taken']} - " . ($result['message'] ?? 'No message') . "\n";
                }
            }
        } else {
            echo "❌ Monitoring failed: " . $results['error'] . "\n";
        }
        
        echo "\n⚡ TRIGGER MONITORING COMPLETE!\n";
        
    } catch (Exception $e) {
        echo "❌ Trigger system failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
