<?php
/**
 * NewStoreSeeder.php - Working Store Seeding Engine
 * 
 * Creates initial stock transfers for new stores by intelligently
 * sourcing products from existing stores with proper pack outer handling
 */

declare(strict_types=1);

class NewStoreSeeder {
    private $db;
    private $log = [];
    private $debug_mode = false;
    private $session_id;
    private $logger;
    private $error_handler;
    
    public function __construct($database, $debug = false) {
        if (!$database || !($database instanceof mysqli)) {
            throw new InvalidArgumentException('Valid mysqli connection required');
        }
        
        $this->db = $database;
        $this->debug_mode = $debug;
        $this->session_id = 'SEED_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 6);
        
        // Initialize enhanced logging and error handling
        require_once __DIR__ . '/TransferLogger.php';
        require_once __DIR__ . '/TransferErrorHandler.php';
        
        $this->logger = new TransferLogger($this->session_id, $debug);
        $this->error_handler = new TransferErrorHandler($this->logger);
        
        $this->logger->info("NewStoreSeeder initialized", [
            'session_id' => $this->session_id,
            'debug_mode' => $debug,
            'memory_limit' => ini_get('memory_limit'),
            'time_limit' => ini_get('max_execution_time')
        ]);
        
        $this->addLog("🚀 NewStoreSeeder initialized - Session: {$this->session_id}");
    }
    
    /**
     * Get the current session ID
     */
    public function getSessionId() {
        return $this->session_id;
    }
    
    /**
     * Enable or disable debug mode
     */
    public function setDebugMode($debug) {
        $this->debug_mode = $debug;
    }
    
    /**
     * Create smart seed transfer for a new store
     */
    public function createSmartSeed($target_outlet_id, $exclude_outlets = [], $options = []) {
        $start_time = microtime(true);
        $this->log = [];
        
        // Default options
        $opts = array_merge([
            'respect_pack_outers' => true,
            'pack_rounding_mode' => 'smart', // 'smart', 'down', 'up', 'nearest'
            'pack_rounding_threshold' => 0.6, // For smart rounding (60% to next pack)
            'balance_categories' => true,
            'max_contribution_per_store' => 2,
            'min_source_stock' => 5,
            'candidate_limit' => 100,
            'simulate' => true
        ], $options);
        
        $this->addLog("🌱 Starting smart seed for outlet: $target_outlet_id");
        $this->addLog("🔧 Options: " . json_encode($opts));
        
        try {
            // 1. Validate target outlet
            $target_outlet = $this->getOutletInfo($target_outlet_id);
            if (!$target_outlet) {
                return ['success' => false, 'error' => 'Target outlet not found'];
            }
            
            $this->addLog("🎯 Target: {$target_outlet['outlet_name']}");
            
            // 2. Get source outlets (exclude target and any specified exclusions)
            $exclude_list = array_merge([$target_outlet_id], $exclude_outlets);
            $source_outlets = $this->getSourceOutlets($exclude_list);
            
            if (empty($source_outlets)) {
                return ['success' => false, 'error' => 'No source outlets available'];
            }
            
            $this->addLog("🏪 Found " . count($source_outlets) . " source outlets");
            
            // 3. Find products suitable for seeding
            $candidates = $this->findSeedCandidates($source_outlets, $opts);
            
            if (empty($candidates)) {
                return ['success' => false, 'error' => 'No suitable products found for seeding'];
            }
            
            $this->addLog("📦 Found " . count($candidates) . " candidate products");
            
            // 4. Create transfer if not simulating
            $transfer_id = null;
            $products_created = 0;
            $total_quantity = 0;
            
            if (!$opts['simulate']) {
                $transfer_id = $this->createTransferHeader($target_outlet_id, 'HUB_TO_STORE');
                if (!$transfer_id) {
                    return ['success' => false, 'error' => 'Failed to create transfer header'];
                }
                
                $this->addLog("📋 Created transfer ID: $transfer_id");
            } else {
                $this->addLog("🧪 SIMULATION MODE - No actual transfer created");
            }
            
            // 5. Process each candidate product
            foreach ($candidates as $candidate) {
                $qty = $this->calculateOptimalQuantity($candidate, $opts);
                
                if ($qty > 0) {
                    if (!$opts['simulate']) {
                        $this->createTransferLine($transfer_id, $candidate, $qty);
                    }
                    
                    $products_created++;
                    $total_quantity += $qty;
                    
                    $this->addLog("✅ Product: {$candidate['product_name']} | Qty: $qty | From: {$candidate['source_name']}");
                }
            }
            
            // 6. Complete transfer if not simulating
            if (!$opts['simulate'] && $transfer_id) {
                $this->completeTransfer($transfer_id);
                $this->addLog("🏁 Transfer completed successfully");
            }
            
            $execution_time = round(microtime(true) - $start_time, 2);
            
            return [
                'success' => true,
                'transfer_id' => $transfer_id,
                'products_count' => $products_created,
                'total_quantity' => $total_quantity,
                'source_stores' => count($source_outlets),
                'execution_time' => $execution_time,
                'log' => $this->log,
                'simulation' => $opts['simulate']
            ];
            
        } catch (Exception $e) {
            $this->addLog("💥 ERROR: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log' => $this->log
            ];
        }
    }
    
    private function getOutletInfo($outlet_id) {
        $stmt = $this->db->prepare("
            SELECT id as outlet_id, name as outlet_name, store_code as outlet_prefix 
            FROM vend_outlets 
            WHERE id = ? AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
        ");
        $stmt->bind_param('s', $outlet_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    private function getSourceOutlets($exclude_list) {
        if (empty($exclude_list)) {
            throw new InvalidArgumentException('Exclude list cannot be empty');
        }
        
        $placeholders = str_repeat('?,', count($exclude_list) - 1) . '?';
        
        // Optimized query with proper indexing hints
        $stmt = $this->db->prepare("
            SELECT id as outlet_id, name as outlet_name, store_code
            FROM vend_outlets USE INDEX (PRIMARY)
            WHERE id NOT IN ($placeholders) 
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            AND name IS NOT NULL
            ORDER BY name
            LIMIT 50
        ");
        
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare source outlets query: ' . $this->db->error);
        }
        
        $types = str_repeat('s', count($exclude_list));
        $stmt->bind_param($types, ...$exclude_list);
        
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to execute source outlets query: ' . $stmt->error);
        }
        
        $outlets = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $outlets[] = $row;
        }
        
        return $outlets;
    }
    
    private function findSeedCandidates($source_outlets, $opts) {
        $candidates = [];
        $total_queries = 0;
        $start_time = microtime(true);
        
        $this->addLog("🔍 Finding seed candidates from " . count($source_outlets) . " source outlets");
        
        foreach ($source_outlets as $outlet) {
            if (empty($outlet['outlet_id'])) {
                $this->addLog("⚠️ Skipping outlet with empty ID");
                continue;
            }
            
            // Optimized query with proper indexing
            $stmt = $this->db->prepare("
                SELECT 
                    vi.product_id,
                    vi.inventory_level,
                    COALESCE(p.name, 'Unknown Product') as product_name,
                    COALESCE(p.supply_price, 0) as supply_price,
                    COALESCE(p.brand_id, '') as brand_id,
                    vi.outlet_id,
                    ? as source_name
                FROM vend_inventory vi USE INDEX (outlet_id)
                LEFT JOIN vend_products p ON vi.product_id = p.id
                WHERE vi.outlet_id = ?
                AND vi.inventory_level >= ?
                AND (vi.deleted_at IS NULL OR vi.deleted_at = '0000-00-00 00:00:00')
                AND p.deleted_at IS NULL
                ORDER BY vi.inventory_level DESC
                LIMIT ?
            ");
            
            if (!$stmt) {
                $this->addLog("❌ Failed to prepare candidate query for outlet {$outlet['outlet_id']}: " . $this->db->error);
                continue;
            }
            
            $stmt->bind_param('ssii', 
                $outlet['outlet_name'],
                $outlet['outlet_id'], 
                $opts['min_source_stock'],
                $opts['candidate_limit']
            );
            
            if (!$stmt->execute()) {
                $this->addLog("❌ Failed to execute candidate query for outlet {$outlet['outlet_id']}: " . $stmt->error);
                continue;
            }
            
            $result = $stmt->get_result();
            $outlet_candidates = 0;
            
            while ($row = $result->fetch_assoc()) {
                // Add metadata for better tracking
                $row['query_time'] = microtime(true);
                $row['source_outlet_name'] = $outlet['outlet_name'];
                $candidates[] = $row;
                $outlet_candidates++;
            }
            
            $total_queries++;
            if ($this->debug_mode) {
                $this->addLog("✅ {$outlet['outlet_name']}: {$outlet_candidates} products found");
            }
        }
        
        $query_time = round(microtime(true) - $start_time, 3);
        $this->addLog("📊 Candidate search complete: {$total_queries} outlets, " . count($candidates) . " products, {$query_time}s");
        
        if (empty($candidates)) {
            $this->addLog("❌ No suitable products found for seeding");
            return [];
        }
        
        // Enhanced sorting: prioritize by inventory level, then by supply price (higher value first)
        usort($candidates, function($a, $b) {
            // Primary sort: inventory level (descending)
            $level_diff = $b['inventory_level'] <=> $a['inventory_level'];
            if ($level_diff !== 0) return $level_diff;
            
            // Secondary sort: supply price (descending for higher value items)
            return $b['supply_price'] <=> $a['supply_price'];
        });
        
        // Apply candidate limit
        $limited_candidates = array_slice($candidates, 0, $opts['candidate_limit']);
        
        $this->addLog("🎯 Selected " . count($limited_candidates) . " top candidates");
        
        return $limited_candidates;
    }
    
    private function calculateOptimalQuantity($candidate, $opts) {
        $available = (int)$candidate['inventory_level'];
        $contribution_limit = $opts['max_contribution_per_store'];
        
        $this->logger->debug("Calculating optimal quantity", [
            'product_id' => $candidate['product_id'],
            'available' => $available,
            'contribution_limit' => $contribution_limit,
            'source_outlet' => $candidate['source_name']
        ]);
        
        // Get pack outer information if available
        $pack_outer = $this->getPackOuter($candidate['product_id']);
        
        // Base quantity calculation with pack outer consideration
        $base_qty = min($contribution_limit, floor($available / 2));
        
        // INTELLIGENT PACK SIZING - Don't break boxes unnecessarily
        if ($opts['respect_pack_outers'] && $pack_outer > 1) {
            $original_qty = $base_qty;
            
            // Calculate complete packs we could take
            $complete_packs = floor($base_qty / $pack_outer);
            $remainder = $base_qty % $pack_outer;
            
            // SMART PACK DECISION LOGIC
            if ($complete_packs >= 1) {
                // We can take at least one complete pack - stick with complete packs only
                $base_qty = $complete_packs * $pack_outer;
                $rounding_decision = "COMPLETE_PACKS_ONLY";
                
                $this->logger->debug("Pack decision: Taking complete packs", [
                    'product_id' => $candidate['product_id'],
                    'original_qty' => $original_qty,
                    'complete_packs' => $complete_packs,
                    'pack_outer' => $pack_outer,
                    'final_qty' => $base_qty
                ]);
                
            } elseif ($base_qty >= ($pack_outer * 0.8) && $available >= ($pack_outer * 2)) {
                // We're close to a full pack AND source has plenty - take one complete pack
                $base_qty = $pack_outer;
                $rounding_decision = "ROUND_UP_TO_PACK";
                
                $this->logger->info("Pack decision: Rounded up to complete pack", [
                    'product_id' => $candidate['product_id'],
                    'original_qty' => $original_qty,
                    'pack_outer' => $pack_outer,
                    'final_qty' => $base_qty,
                    'reason' => 'Close to full pack and source has plenty'
                ]);
                
            } else {
                // Not worth breaking a pack - skip this product
                $base_qty = 0;
                $rounding_decision = "SKIP_INSUFFICIENT_FOR_PACK";
                
                $this->logger->info("Pack decision: Skipped - insufficient for complete pack", [
                    'product_id' => $candidate['product_id'],
                    'original_qty' => $original_qty,
                    'pack_outer' => $pack_outer,
                    'available' => $available,
                    'reason' => 'Not enough for complete pack, not worth breaking boxes'
                ]);
            }
        } else {
            // No pack outer constraints - use individual units
            $rounding_decision = "INDIVIDUAL_UNITS";
        }
        
        // FINAL PACK VALIDATION - Ensure we only send complete packs
        if ($opts['respect_pack_outers'] && $pack_outer > 1 && $base_qty > 0) {
            if ($base_qty % $pack_outer !== 0) {
                // This should not happen with our logic above, but safety check
                $base_qty = floor($base_qty / $pack_outer) * $pack_outer;
                $this->logger->warning("Had to force pack compliance - check logic", [
                    'product_id' => $candidate['product_id'],
                    'pack_outer' => $pack_outer,
                    'corrected_qty' => $base_qty
                ]);
            }
        }
        
        // Final safety check - don't exceed available stock
        $final_qty = min($base_qty, $available - 1); // Leave at least 1 at source
        
        $this->logger->debug("Final quantity calculation", [
            'product_id' => $candidate['product_id'],
            'final_qty' => $final_qty,
            'reasoning' => [
                'available_stock' => $available,
                'base_calculation' => $base_qty,
                'pack_outer_applied' => $opts['respect_pack_outers'],
                'pack_outer_value' => $pack_outer ?? 'N/A'
            ]
        ]);
        
        return max(0, $final_qty);
    }
    
    private function getPackOuter($product_id) {
        return $this->error_handler->wrapDatabaseOperation(function() use ($product_id) {
            // Smart pack outer detection - only for products that actually come in packs
            static $pack_cache = [];
            
            if (isset($pack_cache[$product_id])) {
                return $pack_cache[$product_id];
            }
            
            $stmt = $this->db->prepare("
                SELECT 
                    pack_outer,
                    outer_pack_size,
                    pack_size,
                    name as product_name,
                    description,
                    ai_category,
                    COALESCE(pack_outer, outer_pack_size, pack_size, 1) as raw_pack_value
                FROM vend_products 
                WHERE id = ?
                AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            ");
            
            if (!$stmt) {
                $this->logger->warning("Failed to prepare enhanced pack outer query", [
                    'product_id' => $product_id,
                    'error' => $this->db->error
                ]);
                $pack_cache[$product_id] = 1;
                return 1;
            }
            
            $stmt->bind_param('s', $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $raw_pack = (int)$row['raw_pack_value'];
                
                // SMART PACK DETECTION - Only apply pack logic if product actually needs it
                $effective_pack = $this->determineSmartPackSize($row, $raw_pack);
                
                // Log pack decision for debugging
                if ($this->debug_mode) {
                    $this->logger->debug("Smart pack detection", [
                        'product_id' => $product_id,
                        'product_name' => $row['product_name'],
                        'raw_pack_outer' => $row['pack_outer'],
                        'outer_pack_size' => $row['outer_pack_size'],
                        'pack_size' => $row['pack_size'],
                        'category' => $row['ai_category'],
                        'requires_pack_logic' => $effective_pack > 1,
                        'effective_pack_size' => $effective_pack,
                        'reasoning' => $this->getPackReasoning($row, $effective_pack)
                    ]);
                }
                
                $pack_cache[$product_id] = $effective_pack;
                return $effective_pack;
            }
            
            $this->logger->warning("Product not found for pack outer calculation", [
                'product_id' => $product_id
            ]);
            
            $pack_cache[$product_id] = 1;
            return 1; // Default pack outer
        }, 'get smart pack outer for product');
    }
    
    /**
     * Determine if product actually needs pack logic based on category and attributes
     */
    private function determineSmartPackSize(array $product, int $raw_pack): int {
        $name = strtolower($product['product_name'] ?? '');
        $description = strtolower($product['description'] ?? '');
        $category = strtolower($product['ai_category'] ?? '');
        $text = "$name $description $category";
        
        // Products that DON'T need pack logic (individual items)
        $individual_keywords = [
            'device', 'mod', 'kit', 'starter', 'battery', 'charger', 'case', 'tank', 'atomizer'
        ];
        
        foreach ($individual_keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $this->logger->debug("Individual item detected", [
                    'product_name' => $product['product_name'],
                    'keyword_matched' => $keyword,
                    'pack_override' => 1
                ]);
                return 1; // Individual item - no pack logic
            }
        }
        
        // Products that NEED pack logic (boxed items)
        $pack_required_patterns = [
            // Disposables often come in boxes of 10
            '/\b(disposable|puff|bar)\b/' => function($raw) { return max($raw, 10); },
            
            // Coils often come in 5-packs
            '/\bcoil\b/' => function($raw) { return max($raw, 5); },
            
            // E-liquids might come in cases
            '/\b(liquid|juice|flavou?r|10ml|30ml|60ml)\b/' => function($raw) { 
                // Only if raw pack > 1 (meaning it's actually boxed)
                return $raw > 1 ? $raw : 1; 
            },
            
            // Pods often come in 2-4 packs
            '/\b(pod|cartridge|refill)\b/' => function($raw) { return max($raw, 2); },
            
            // Small accessories in multipacks
            '/\b(drip tip|o-ring|screw|spare)\b/' => function($raw) { return max($raw, 5); }
        ];
        
        foreach ($pack_required_patterns as $pattern => $pack_calculator) {
            if (preg_match($pattern, $text)) {
                $calculated_pack = $pack_calculator($raw_pack);
                
                $this->logger->debug("Pack required item detected", [
                    'product_name' => $product['product_name'],
                    'pattern_matched' => $pattern,
                    'raw_pack' => $raw_pack,
                    'calculated_pack' => $calculated_pack
                ]);
                
                return $calculated_pack;
            }
        }
        
        // Default: Use raw pack value but only if > 1, otherwise treat as individual
        return $raw_pack > 1 ? $raw_pack : 1;
    }
    
    /**
     * Get reasoning for pack decision (for debugging)
     */
    private function getPackReasoning(array $product, int $effective_pack): string {
        if ($effective_pack === 1) {
            return "Individual item - no pack constraints";
        }
        
        $name = strtolower($product['product_name'] ?? '');
        
        if (strpos($name, 'disposable') !== false) return "Disposable - boxed item";
        if (strpos($name, 'coil') !== false) return "Coil - multipack item";
        if (strpos($name, 'pod') !== false) return "Pod - multipack item";
        if (strpos($name, 'liquid') !== false) return "E-liquid - potential case pack";
        
        return "Pack detected from database: {$effective_pack}";
    }
    
    /**
     * Make smart pack decision based on product type and quantities
     */
    private function makeSmartPackDecision(int $desired_qty, int $pack_size, int $available_stock, array $candidate): array {
        $product_name = $candidate['product_name'] ?? 'Unknown';
        
        // Calculate pack scenarios
        $complete_packs = floor($desired_qty / $pack_size);
        $remainder = $desired_qty % $pack_size;
        $pack_threshold = $pack_size * 0.8; // 80% of pack size
        
        // DECISION 1: We can get complete packs - take them
        if ($complete_packs >= 1) {
            return [
                'quantity' => $complete_packs * $pack_size,
                'decision' => 'COMPLETE_PACKS',
                'reasoning' => "Taking {$complete_packs} complete pack(s) of {$pack_size}"
            ];
        }
        
        // DECISION 2: Close to one pack AND plenty of stock - round up
        if ($desired_qty >= $pack_threshold && $available_stock >= ($pack_size * 2)) {
            return [
                'quantity' => $pack_size,
                'decision' => 'ROUND_UP_ONE_PACK', 
                'reasoning' => "Close to pack size ({$desired_qty}/{$pack_size}), source has plenty ({$available_stock})"
            ];
        }
        
        // DECISION 3: For critical items (coils, disposables), try to get at least one pack if possible
        if ($this->isCriticalPackItem($product_name) && $available_stock >= $pack_size) {
            return [
                'quantity' => $pack_size,
                'decision' => 'CRITICAL_ITEM_ONE_PACK',
                'reasoning' => "Critical item - ensuring new store gets at least one pack"
            ];
        }
        
        // DECISION 4: Not worth breaking packs
        return [
            'quantity' => 0,
            'decision' => 'SKIP_INSUFFICIENT',
            'reasoning' => "Insufficient quantity ({$desired_qty}) for pack size ({$pack_size}), not worth breaking boxes"
        ];
    }
    
    /**
     * Determine if item is critical for new store (should prioritize getting at least one pack)
     */
    private function isCriticalPackItem(string $product_name): bool {
        $name = strtolower($product_name);
        
        // Critical items that new stores NEED
        $critical_patterns = [
            'disposable', 'coil', 'pod', 'starter', 'basic'
        ];
        
        foreach ($critical_patterns as $pattern) {
            if (strpos($name, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate pack quantities and ensure compliance with pack requirements
     */
    private function validatePackQuantity($quantity, $pack_outer, $product_name = null) {
        if ($pack_outer <= 1) {
            return [
                'valid' => true,
                'quantity' => $quantity,
                'pack_compliant' => true,
                'adjustment_made' => false
            ];
        }
        
        $original_quantity = $quantity;
        $pack_compliant_qty = floor($quantity / $pack_outer) * $pack_outer;
        
        if ($pack_compliant_qty < $pack_outer) {
            // If we can't make even one pack, suggest minimum
            $pack_compliant_qty = $pack_outer;
        }
        
        $adjustment_made = ($pack_compliant_qty !== $original_quantity);
        
        if ($adjustment_made && $this->debug_mode) {
            $this->logger->info("Pack quantity adjusted", [
                'product_name' => $product_name,
                'original_quantity' => $original_quantity,
                'pack_outer' => $pack_outer,
                'adjusted_quantity' => $pack_compliant_qty,
                'packs_created' => $pack_compliant_qty / $pack_outer
            ]);
        }
        
        return [
            'valid' => true,
            'quantity' => $pack_compliant_qty,
            'pack_compliant' => !$adjustment_made,
            'adjustment_made' => $adjustment_made,
            'packs_count' => $pack_compliant_qty / $pack_outer,
            'original_quantity' => $original_quantity
        ];
    }
    
    private function createTransferHeader($target_outlet_id, $type = 'HUB_TO_STORE') {
        $stmt = $this->db->prepare("
            INSERT INTO stock_transfers (
                outlet_to, 
                transfer_type, 
                status, 
                date_created,
                created_by_system
            ) VALUES (?, ?, 'PENDING', NOW(), 1)
        ");
        
        $stmt->bind_param('ss', $target_outlet_id, $type);
        
        if ($stmt->execute()) {
            return $this->db->insert_id;
        }
        
        return null;
    }
    
    private function createTransferLine($transfer_id, $candidate, $quantity) {
        $stmt = $this->db->prepare("
            INSERT INTO stock_products_to_transfer (
                transfer_id,
                product_id,
                qty_to_transfer,
                source_outlet_id,
                date_created
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param('isis', 
            $transfer_id, 
            $candidate['product_id'], 
            $quantity,
            $candidate['outlet_id']
        );
        
        return $stmt->execute();
    }
    
    private function completeTransfer($transfer_id) {
        $stmt = $this->db->prepare("
            UPDATE stock_transfers 
            SET status = 'READY_TO_PACK', 
                date_updated = NOW() 
            WHERE transfer_id = ?
        ");
        $stmt->bind_param('i', $transfer_id);
        return $stmt->execute();
    }
    
    private function addLog($message) {
        $this->log[] = $message;
    }
}
