<?php
declare(strict_types=1);

namespace CIS\Enhanced\Integration;

use CIS\Enhanced\Models\{TransferOrder, ProductMasterRegistry, InventoryIntelligence};
use CIS\Enhanced\Services\{NeuralBrainService, AIDecisionService};
use CIS\Enhanced\Repositories\{EnhancedTransferRepository, ProductRepository};

/**
 * Website-CIS Integration Service
 * 
 * Handles bidirectional synchronization between website orders 
 * and CIS transfer system with AI-enhanced decision making.
 * 
 * @package CIS\Enhanced\Integration
 * @version 1.0.0
 * @author The Vape Shed Engineering Team
 */
class WebsiteIntegrationService
{
    private EnhancedTransferRepository $transferRepo;
    private ProductRepository $productRepo;
    private NeuralBrainService $neuralBrain;
    private AIDecisionService $aiDecision;
    private IntegrationEventBus $eventBus;
    private array $config;
    
    public function __construct(
        EnhancedTransferRepository $transferRepo,
        ProductRepository $productRepo,
        NeuralBrainService $neuralBrain,
        AIDecisionService $aiDecision,
        IntegrationEventBus $eventBus,
        array $config = []
    ) {
        $this->transferRepo = $transferRepo;
        $this->productRepo = $productRepo;
        $this->neuralBrain = $neuralBrain;
        $this->aiDecision = $aiDecision;
        $this->eventBus = $eventBus;
        $this->config = array_merge([
            'max_retry_attempts' => 3,
            'sync_timeout_seconds' => 30,
            'ai_confidence_threshold' => 0.75,
            'auto_approve_threshold' => 0.90
        ], $config);
    }
    
    /**
     * Synchronize website order to CIS transfer system
     */
    public function syncWebsiteOrderToCIS(array $websiteOrder): SyncResult
    {
        $startTime = microtime(true);
        $syncId = $this->createSyncRecord($websiteOrder, 'website_to_cis');
        
        try {
            $this->updateSyncStatus($syncId, 'processing');
            
            // AI-enhanced order analysis
            $aiAnalysis = $this->neuralBrain->analyzeWebsiteOrder($websiteOrder);
            $this->logAIDecision($aiAnalysis, 'website_order_analysis', $websiteOrder);
            
            // Transform website order to transfer data
            $transferData = $this->transformWebsiteOrderToTransfer($websiteOrder, $aiAnalysis);
            
            // Create AI decision record
            $aiDecision = $this->aiDecision->recordDecision([
                'decision_type' => 'transfer_recommendation',
                'context_data' => [
                    'website_order_id' => $websiteOrder['id'],
                    'order_value' => $websiteOrder['total_value'] ?? 0,
                    'product_count' => count($websiteOrder['items'] ?? []),
                    'customer_type' => $websiteOrder['customer_type'] ?? 'retail'
                ],
                'confidence_score' => $aiAnalysis['confidence_score'],
                'reasoning' => $aiAnalysis['reasoning'],
                'predicted_outcome' => [
                    'success_probability' => $aiAnalysis['success_probability'],
                    'estimated_completion_time' => $aiAnalysis['estimated_completion_time'],
                    'risk_factors' => $aiAnalysis['risk_factors']
                ]
            ]);
            
            // Create enhanced transfer with AI integration
            $transferId = $this->transferRepo->createTransferWithAI($transferData, $aiDecision);
            
            // Update sync record with success
            $this->updateSyncRecord($syncId, [
                'cis_transfer_id' => $transferId,
                'sync_status' => 'completed',
                'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Trigger integration events
            $this->eventBus->dispatch('website_order_synced', [
                'sync_id' => $syncId,
                'website_order_id' => $websiteOrder['id'],
                'cis_transfer_id' => $transferId,
                'ai_confidence' => $aiAnalysis['confidence_score']
            ]);
            
            // Auto-approve if AI confidence is high enough
            if ($aiAnalysis['confidence_score'] >= $this->config['auto_approve_threshold']) {
                $this->transferRepo->approveTransfer($transferId, 'ai_auto_approval');
            }
            
            return new SyncResult([
                'success' => true,
                'sync_id' => $syncId,
                'transfer_id' => $transferId,
                'ai_confidence' => $aiAnalysis['confidence_score'],
                'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000)
            ]);
            
        } catch (\Exception $e) {
            $this->handleSyncError($syncId, $e, $startTime);
            throw new IntegrationException("Website order sync failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Sync CIS transfer status back to website
     */
    public function syncTransferStatusToWebsite(int $transferId): SyncResult
    {
        $startTime = microtime(true);
        
        try {
            $transfer = $this->transferRepo->findById($transferId);
            if (!$transfer) {
                throw new IntegrationException("Transfer not found: {$transferId}");
            }
            
            $websiteOrderId = $transfer->getWebsiteOrderId();
            if (!$websiteOrderId) {
                throw new IntegrationException("No website order linked to transfer: {$transferId}");
            }
            
            $syncId = $this->createSyncRecord([
                'transfer_id' => $transferId,
                'website_order_id' => $websiteOrderId
            ], 'cis_to_website');
            
            // Transform transfer status to website format
            $websiteUpdateData = $this->transformTransferStatusToWebsite($transfer);
            
            // Send update to website via API
            $websiteResponse = $this->callWebsiteAPI('update_order_status', $websiteUpdateData);
            
            // Record AI learning from transfer outcome
            if ($transfer->getStatus() === 'completed') {
                $this->neuralBrain->recordTransferOutcome($transfer);
            }
            
            $this->updateSyncRecord($syncId, [
                'sync_status' => 'completed',
                'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000),
                'completed_at' => date('Y-m-d H:i:s')
            ]);
            
            return new SyncResult([
                'success' => true,
                'sync_id' => $syncId,
                'website_response' => $websiteResponse
            ]);
            
        } catch (\Exception $e) {
            $this->handleSyncError($syncId ?? null, $e, $startTime);
            throw new IntegrationException("Transfer status sync failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Real-time inventory synchronization
     */
    public function syncInventoryToWebsite(array $productIds = []): SyncResult
    {
        $startTime = microtime(true);
        
        try {
            $inventoryData = $this->productRepo->getInventoryIntelligence($productIds);
            
            $syncPayload = [
                'type' => 'inventory_update',
                'timestamp' => date('c'),
                'products' => []
            ];
            
            foreach ($inventoryData as $item) {
                $product = $this->productRepo->findByVendId($item['product_id']);
                if (!$product || !$product->getWebsiteProductId()) {
                    continue;
                }
                
                $syncPayload['products'][] = [
                    'website_product_id' => $product->getWebsiteProductId(),
                    'sku' => $product->getSku(),
                    'outlets' => $this->formatOutletInventoryForWebsite($item)
                ];
            }
            
            // Send to website
            $websiteResponse = $this->callWebsiteAPI('update_inventory', $syncPayload);
            
            // Log sync event
            $this->eventBus->dispatch('inventory_synced_to_website', [
                'product_count' => count($syncPayload['products']),
                'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000),
                'website_response' => $websiteResponse
            ]);
            
            return new SyncResult([
                'success' => true,
                'products_synced' => count($syncPayload['products']),
                'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000)
            ]);
            
        } catch (\Exception $e) {
            throw new IntegrationException("Inventory sync failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Transform website order to CIS transfer format
     */
    private function transformWebsiteOrderToTransfer(array $websiteOrder, array $aiAnalysis): array
    {
        $sourceOutlet = $this->determineOptimalSourceOutlet($websiteOrder, $aiAnalysis);
        $destOutlet = $this->determineDestinationOutlet($websiteOrder);
        
        return [
            'source_outlet_id' => $sourceOutlet,
            'destination_outlet_id' => $destOutlet,
            'transfer_type' => $this->determineTransferType($websiteOrder),
            'priority' => $this->determinePriority($websiteOrder, $aiAnalysis),
            'website_order_id' => $websiteOrder['id'],
            'transfer_notes' => "Website Order #{$websiteOrder['number']} - AI Confidence: " . 
                              round($aiAnalysis['confidence_score'] * 100, 1) . '%',
            'special_instructions' => $websiteOrder['special_instructions'] ?? '',
            'products' => $this->transformOrderItemsToTransferProducts($websiteOrder['items'], $aiAnalysis)
        ];
    }
    
    /**
     * Transform order items to transfer products with AI enhancement
     */
    private function transformOrderItemsToTransferProducts(array $items, array $aiAnalysis): array
    {
        $products = [];
        
        foreach ($items as $item) {
            $productIntelligence = $this->productRepo->getProductIntelligence($item['product_id']);
            $aiRecommendation = $aiAnalysis['product_recommendations'][$item['product_id']] ?? [];
            
            $products[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'ai_recommended_quantity' => $aiRecommendation['recommended_quantity'] ?? $item['quantity'],
                'confidence_level' => $aiRecommendation['confidence'] ?? 0.5,
                'unit_cost_nzd' => $item['cost_price'] ?? 0,
                'unit_retail_price_nzd' => $item['price'] ?? 0,
                'profit_impact_nzd' => ($item['price'] - $item['cost_price']) * $item['quantity'],
                'stockout_probability' => $productIntelligence['stockout_probability'] ?? 0,
                'abc_classification' => $productIntelligence['abc_classification'] ?? 'C'
            ];
        }
        
        return $products;
    }
    
    /**
     * Determine optimal source outlet using AI
     */
    private function determineOptimalSourceOutlet(array $websiteOrder, array $aiAnalysis): string
    {
        // AI-driven source selection based on:
        // - Inventory levels
        // - Geographic proximity  
        // - Historical performance
        // - Current capacity
        
        $candidates = $this->neuralBrain->recommendSourceOutlets([
            'destination' => $websiteOrder['shipping_address'] ?? [],
            'products' => $websiteOrder['items'] ?? [],
            'urgency' => $websiteOrder['priority'] ?? 'normal',
            'order_value' => $websiteOrder['total_value'] ?? 0
        ]);
        
        return $candidates['primary_recommendation'] ?? 'default_warehouse';
    }
    
    /**
     * Create website sync record
     */
    private function createSyncRecord(array $data, string $direction): int
    {
        return $this->transferRepo->createSyncRecord([
            'website_order_id' => $data['website_order_id'] ?? $data['id'] ?? null,
            'sync_direction' => $direction,
            'sync_type' => 'order_creation',
            'order_data' => json_encode($data),
            'sync_status' => 'pending',
            'integration_version' => 'v2.0_enhanced',
            'source_system' => $direction === 'website_to_cis' ? 'website' : 'cis',
            'target_system' => $direction === 'website_to_cis' ? 'cis' : 'website'
        ]);
    }
    
    /**
     * Update sync record status
     */
    private function updateSyncStatus(int $syncId, string $status): void
    {
        $this->transferRepo->updateSyncRecord($syncId, [
            'sync_status' => $status,
            'started_processing_at' => $status === 'processing' ? date('Y-m-d H:i:s') : null
        ]);
    }
    
    /**
     * Handle sync errors with intelligent retry logic
     */
    private function handleSyncError(?int $syncId, \Exception $e, float $startTime): void
    {
        if (!$syncId) {
            error_log("Sync error without sync ID: " . $e->getMessage());
            return;
        }
        
        $retryCount = $this->transferRepo->incrementSyncRetry($syncId);
        $shouldRetry = $retryCount < $this->config['max_retry_attempts'] && 
                       $this->isRetryableError($e);
        
        $this->transferRepo->updateSyncRecord($syncId, [
            'sync_status' => $shouldRetry ? 'retrying' : 'failed',
            'retry_count' => $retryCount,
            'last_error' => substr($e->getMessage(), 0, 1000),
            'error_details' => json_encode([
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice($e->getTrace(), 0, 5)
            ]),
            'processing_time_ms' => (int)((microtime(true) - $startTime) * 1000),
            'next_retry_at' => $shouldRetry ? 
                date('Y-m-d H:i:s', time() + (60 * pow(2, $retryCount))) : null
        ]);
        
        // Trigger error event for monitoring
        $this->eventBus->dispatch('sync_error_occurred', [
            'sync_id' => $syncId,
            'error_message' => $e->getMessage(),
            'retry_count' => $retryCount,
            'will_retry' => $shouldRetry
        ]);
    }
    
    /**
     * Call website API endpoint
     */
    private function callWebsiteAPI(string $endpoint, array $data): array
    {
        $url = $this->config['website_api_base_url'] . '/' . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['sync_timeout_seconds'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->config['website_api_token'],
                'X-CIS-Integration-Version: 2.0'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new IntegrationException("Website API call failed: {$error}");
        }
        
        if ($httpCode >= 400) {
            throw new IntegrationException("Website API returned error: HTTP {$httpCode}");
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new IntegrationException("Invalid JSON response from website API");
        }
        
        return $decoded;
    }
    
    /**
     * Determine if error is retryable
     */
    private function isRetryableError(\Exception $e): bool
    {
        $retryableErrors = [
            'Connection timed out',
            'HTTP 500',
            'HTTP 502',
            'HTTP 503',
            'HTTP 504',
            'Deadlock found',
            'Lock wait timeout'
        ];
        
        $message = $e->getMessage();
        foreach ($retryableErrors as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

/**
 * Sync Result Value Object
 */
class SyncResult
{
    private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function isSuccess(): bool
    {
        return $this->data['success'] ?? false;
    }
    
    public function getSyncId(): ?int
    {
        return $this->data['sync_id'] ?? null;
    }
    
    public function getTransferId(): ?int
    {
        return $this->data['transfer_id'] ?? null;
    }
    
    public function getProcessingTime(): int
    {
        return $this->data['processing_time_ms'] ?? 0;
    }
    
    public function toArray(): array
    {
        return $this->data;
    }
}

/**
 * Integration Exception
 */
class IntegrationException extends \Exception
{
}
