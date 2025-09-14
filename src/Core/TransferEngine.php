<?php
namespace NewTransferV3\Core;

use NewTransferV3\Services\DatabaseService;
use NewTransferV3\Services\NeuralBrainService;
use NewTransferV3\Models\Transfer;
use NewTransferV3\Models\TransferLine;
use Exception;

/**
 * NewTransferV3 Core Transfer Engine
 * 
 * Enterprise-grade inventory transfer orchestration with AI integration
 * Handles all transfer modes: all_stores, specific_transfer, hub_to_stores
 */
class TransferEngine
{
    private DatabaseService $db;
    private NeuralBrainService $neuralBrain;
    private array $config;
    private array $outlets = [];
    private array $products = [];
    
    public function __construct(DatabaseService $db, NeuralBrainService $neuralBrain, array $config = [])
    {
        $this->db = $db;
        $this->neuralBrain = $neuralBrain;
        $this->config = array_merge($this->getDefaultConfig(), $config);
    }
    
    /**
     * Execute transfer based on parameters
     */
    public function executeTransfer(array $params): array
    {
        try {
            $this->validateParameters($params);
            
            // Load required data
            $this->loadOutlets();
            $this->loadProducts($params);
            
            // Initialize Neural Brain session
            $sessionId = $this->neuralBrain->initializeSession();
            
            switch ($params['transfer_mode'] ?? 'all_stores') {
                case 'specific_transfer':
                    return $this->executeSpecificTransfer($params);
                    
                case 'hub_to_stores':
                    return $this->executeHubToStores($params);
                    
                case 'all_stores':
                default:
                    return $this->executeAllStores($params);
            }
            
        } catch (Exception $e) {
            $this->logError('Transfer execution failed', $e, $params);
            throw $e;
        }
    }
    
    /**
     * Execute new store seeding (CRITICAL for today's operation)
     */
    public function executeNewStoreSeed(string $newStoreId, array $params = []): array
    {
        try {
            $params = array_merge([
                'transfer_mode' => 'specific_transfer',
                'dest_outlet' => $newStoreId,
                'cover' => 21,
                'buffer_pct' => 35,
                'default_floor_qty' => 3,
                'simulate' => 0
            ], $params);
            
            $this->log("🌱 Starting new store seed for outlet: {$newStoreId}");
            
            // Get all source outlets (warehouses + stores for skimming)
            $sourceOutlets = $this->getSourceOutletsForSeeding();
            
            $transferResults = [];
            
            foreach ($sourceOutlets as $source) {
                $seedParams = array_merge($params, [
                    'source_outlet' => $source['outlet_id']
                ]);
                
                $result = $this->executeSpecificTransfer($seedParams);
                if (!empty($result['lines'])) {
                    $transferResults[] = $result;
                }
            }
            
            return [
                'success' => true,
                'transfers' => $transferResults,
                'new_store' => $newStoreId,
                'total_transfers' => count($transferResults),
                'message' => 'New store seeding completed successfully'
            ];
            
        } catch (Exception $e) {
            $this->logError('New store seeding failed', $e, $params);
            throw $e;
        }
    }
    
    /**
     * Execute all stores distribution
     */
    private function executeAllStores(array $params): array
    {
        $this->log("🏢 Executing all stores distribution");
        
        $warehouses = $this->getWarehouses();
        $stores = $this->getStores();
        
        $transferResults = [];
        
        foreach ($stores as $store) {
            // Calculate demand for this store
            $demand = $this->calculateStoreDemand($store, $params);
            
            if (empty($demand)) {
                continue;
            }
            
            // Find best source for each product
            foreach ($demand as $productId => $demandQty) {
                $bestSource = $this->findBestSource($productId, $demandQty, $warehouses, $stores);
                
                if ($bestSource) {
                    $transferResults[] = $this->createTransferLine(
                        $bestSource['outlet_id'],
                        $store['outlet_id'],
                        $productId,
                        $demandQty,
                        $params
                    );
                }
            }
        }
        
        return $this->consolidateTransfers($transferResults, $params);
    }
    
    /**
     * Execute specific 1-to-1 transfer
     */
    private function executeSpecificTransfer(array $params): array
    {
        $sourceId = $params['source_outlet'] ?? null;
        $destId = $params['dest_outlet'] ?? null;
        
        if (!$sourceId || !$destId) {
            throw new Exception('Source and destination outlets required for specific transfer');
        }
        
        $this->log("🎯 Executing specific transfer: {$sourceId} → {$destId}");
        
        // Get available products at source
        $sourceInventory = $this->getOutletInventory($sourceId);
        
        // Calculate destination demand
        $destDemand = $this->calculateOutletDemand($destId, $params);
        
        $transferLines = [];
        
        foreach ($destDemand as $productId => $demandQty) {
            $available = $sourceInventory[$productId] ?? 0;
            
            if ($available > 0) {
                $allocateQty = min($demandQty, $available);
                
                $transferLines[] = [
                    'product_id' => $productId,
                    'qty_to_transfer' => $allocateQty,
                    'available_at_source' => $available,
                    'demand_at_destination' => $demandQty
                ];
            }
        }
        
        if (!empty($transferLines)) {
            return $this->createTransfer($sourceId, $destId, $transferLines, $params);
        }
        
        return ['success' => false, 'message' => 'No products to transfer'];
    }
    
    /**
     * Execute hub to stores distribution
     */
    private function executeHubToStores(array $params): array
    {
        $sourceId = $params['source_outlet'] ?? null;
        $destinations = $this->parseDestinations($params['dest_outlet'] ?? '');
        
        if (!$sourceId) {
            throw new Exception('Source outlet required for hub to stores transfer');
        }
        
        $this->log("🏭 Executing hub to stores: {$sourceId} → " . count($destinations) . " destinations");
        
        $transferResults = [];
        
        foreach ($destinations as $destId) {
            $specificParams = array_merge($params, [
                'source_outlet' => $sourceId,
                'dest_outlet' => $destId
            ]);
            
            $result = $this->executeSpecificTransfer($specificParams);
            if ($result['success'] ?? false) {
                $transferResults[] = $result;
            }
        }
        
        return [
            'success' => true,
            'transfers' => $transferResults,
            'total_transfers' => count($transferResults)
        ];
    }
    
    /**
     * Create transfer in database
     */
    private function createTransfer(string $sourceId, string $destId, array $lines, array $params): array
    {
        $simulate = (int)($params['simulate'] ?? 1);
        
        try {
            $this->db->beginTransaction();
            
            // Create transfer header
            $transferId = $this->db->createTransferHeader([
                'outlet_from' => $sourceId,
                'outlet_to' => $destId,
                'transfer_date' => date('Y-m-d'),
                'transfer_notes' => $this->generateTransferNotes($params),
                'created_by' => 'NewTransferV3_Engine',
                'simulate_mode' => $simulate
            ]);
            
            $insertedLines = [];
            
            // Create transfer lines
            foreach ($lines as $line) {
                $lineId = $this->db->createTransferLine([
                    'transfer_id' => $transferId,
                    'product_id' => $line['product_id'],
                    'qty_to_transfer' => $line['qty_to_transfer'],
                    'min_qty_to_remain' => 0,
                    'demand_forecast' => $line['demand_at_destination'] ?? 0,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $insertedLines[] = array_merge($line, ['line_id' => $lineId]);
            }
            
            if ($simulate) {
                $this->db->rollback();
                $message = 'SIMULATION: Transfer created successfully (not saved)';
            } else {
                $this->db->commit();
                $message = 'LIVE: Transfer created and saved to database';
            }
            
            $this->log("✅ {$message} - Transfer ID: {$transferId}");
            
            return [
                'success' => true,
                'transfer_id' => $transferId,
                'source_outlet' => $sourceId,
                'dest_outlet' => $destId,
                'lines' => $insertedLines,
                'total_lines' => count($insertedLines),
                'simulate_mode' => $simulate,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Calculate store demand based on sales velocity and parameters
     */
    private function calculateStoreDemand(array $store, array $params): array
    {
        $coverDays = (int)($params['cover'] ?? 14);
        $bufferPct = (float)($params['buffer_pct'] ?? 20);
        
        $storeId = $store['outlet_id'];
        $products = $this->getActiveProducts($params);
        
        $demand = [];
        
        foreach ($products as $product) {
            $currentStock = $this->getCurrentStock($storeId, $product['product_id']);
            $salesVelocity = $this->getSalesVelocity($storeId, $product['product_id'], 30); // 30-day velocity
            
            // Calculate target stock level
            $dailyDemand = $salesVelocity / 30;
            $targetStock = ($dailyDemand * $coverDays) * (1 + $bufferPct / 100);
            
            // Calculate need
            $need = max(0, $targetStock - $currentStock);
            
            if ($need > 0) {
                $demand[$product['product_id']] = ceil($need);
            }
        }
        
        return $demand;
    }
    
    /**
     * Get source outlets for new store seeding (warehouses + high-stock stores)
     */
    private function getSourceOutletsForSeeding(): array
    {
        $warehouses = $this->getWarehouses();
        $highStockStores = $this->getHighStockStores();
        
        return array_merge($warehouses, $highStockStores);
    }
    
    /**
     * Get outlets with high stock levels (for seeding sources)
     */
    private function getHighStockStores(): array
    {
        return array_filter($this->outlets, function($outlet) {
            return !$outlet['is_warehouse'] && $this->getOutletStockValue($outlet['outlet_id']) > 50000;
        });
    }
    
    /**
     * Load all outlets from database
     */
    private function loadOutlets(): void
    {
        $this->outlets = $this->db->getOutlets();
        $this->log("📍 Loaded " . count($this->outlets) . " outlets");
    }
    
    /**
     * Load active products based on filters
     */
    private function loadProducts(array $params): void
    {
        $maxProducts = (int)($params['max_products'] ?? 0);
        $this->products = $this->db->getActiveProducts($maxProducts);
        $this->log("📦 Loaded " . count($this->products) . " products");
    }
    
    /**
     * Get default configuration
     */
    private function getDefaultConfig(): array
    {
        return [
            'cover_days' => 14,
            'buffer_pct' => 20,
            'default_floor_qty' => 2,
            'margin_factor' => 1.2,
            'max_products' => 0,
            'rounding_mode' => 'nearest'
        ];
    }
    
    /**
     * Validate input parameters
     */
    private function validateParameters(array $params): void
    {
        $required = [];
        $transferMode = $params['transfer_mode'] ?? 'all_stores';
        
        if ($transferMode === 'specific_transfer') {
            $required = ['source_outlet', 'dest_outlet'];
        }
        
        foreach ($required as $param) {
            if (empty($params[$param])) {
                throw new Exception("Required parameter missing: {$param}");
            }
        }
    }
    
    /**
     * Log message with timestamp
     */
    private function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
    }
    
    /**
     * Log error with context
     */
    private function logError(string $message, Exception $e, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] ERROR: {$message} - {$e->getMessage()}\n";
        if (!empty($context)) {
            echo "[{$timestamp}] Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
    
    // Additional helper methods would be implemented here...
    // (Truncated for brevity - the full implementation would include all helper methods)
}
