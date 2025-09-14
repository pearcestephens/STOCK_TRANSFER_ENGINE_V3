<?php
declare(strict_types=1);

namespace TransferEngine\Core;

use TransferEngine\Models\Transfer;
use TransferEngine\Models\Product;
use TransferEngine\Services\AllocationService;
use TransferEngine\Services\NeuralBrainService;
use TransferEngine\Repositories\TransferRepository;
use TransferEngine\Repositories\ProductRepository;
use TransferEngine\Repositories\OutletRepository;
use TransferEngine\Config\TransferConfig;
use TransferEngine\Exceptions\TransferEngineException;

/**
 * NewTransferV3 - Enterprise Transfer Engine Core
 * 
 * Object-oriented, modular transfer engine for inventory distribution
 * across retail network. Designed for MVC integration.
 * 
 * @package TransferEngine\Core
 * @version 3.0.0
 * @author The Vape Shed Engineering Team
 */
class TransferEngine
{
    private TransferRepository $transferRepo;
    private ProductRepository $productRepo;
    private OutletRepository $outletRepo;
    private AllocationService $allocationService;
    private NeuralBrainService $neuralBrain;
    private TransferConfig $config;
    private array $metrics = [];
    
    public function __construct(
        TransferRepository $transferRepo,
        ProductRepository $productRepo,
        OutletRepository $outletRepo,
        AllocationService $allocationService,
        NeuralBrainService $neuralBrain,
        TransferConfig $config
    ) {
        $this->transferRepo = $transferRepo;
        $this->productRepo = $productRepo;
        $this->outletRepo = $outletRepo;
        $this->allocationService = $allocationService;
        $this->neuralBrain = $neuralBrain;
        $this->config = $config;
    }
    
    /**
     * Execute transfer generation based on mode and parameters
     */
    public function executeTransfer(array $params): TransferResult
    {
        $startTime = microtime(true);
        
        try {
            // Validate parameters
            $this->validateParameters($params);
            
            // Initialize transfer session
            $session = $this->initializeSession($params);
            
            // Execute based on transfer mode
            $result = match($params['mode']) {
                'all_stores' => $this->executeAllStoresTransfer($session),
                'specific_transfer' => $this->executeSpecificTransfer($session),
                'hub_to_stores' => $this->executeHubToStores($session),
                default => throw new TransferEngineException("Invalid transfer mode: {$params['mode']}")
            };
            
            // Calculate metrics
            $this->calculateMetrics($result, microtime(true) - $startTime);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logError($e, $params);
            throw new TransferEngineException("Transfer execution failed: " . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * All Stores Transfer Mode - Automatic distribution across network
     */
    private function executeAllStoresTransfer(TransferSession $session): TransferResult
    {
        $outlets = $this->outletRepo->getActiveOutlets();
        $products = $this->productRepo->getTransferableProducts($session->getMaxProducts());
        
        $transfers = [];
        $totalProcessed = 0;
        
        foreach ($outlets as $outlet) {
            if ($outlet->isWarehouse()) continue;
            
            $allocation = $this->allocationService->calculateAllocation(
                $outlet,
                $products,
                $session->getParameters()
            );
            
            if ($allocation->hasAllocations()) {
                $transfer = $this->createTransfer($outlet, $allocation, $session);
                $transfers[] = $transfer;
                $totalProcessed += $allocation->getTotalQuantity();
            }
        }
        
        return new TransferResult($transfers, $totalProcessed, $this->metrics);
    }
    
    /**
     * Specific Transfer Mode - Direct 1:1 transfer between locations
     */
    private function executeSpecificTransfer(TransferSession $session): TransferResult
    {
        $params = $session->getParameters();
        
        $sourceOutlet = $this->outletRepo->findById($params['source_outlet']);
        $destOutlet = $this->outletRepo->findById($params['dest_outlet']);
        
        if (!$sourceOutlet || !$destOutlet) {
            throw new TransferEngineException("Invalid source or destination outlet");
        }
        
        $products = $this->productRepo->getAvailableProducts($sourceOutlet->getId());
        
        $allocation = $this->allocationService->calculateSpecificAllocation(
            $sourceOutlet,
            $destOutlet,
            $products,
            $params
        );
        
        $transfers = [];
        if ($allocation->hasAllocations()) {
            $transfer = $this->createTransfer($destOutlet, $allocation, $session);
            $transfers[] = $transfer;
        }
        
        return new TransferResult($transfers, $allocation->getTotalQuantity(), $this->metrics);
    }
    
    /**
     * Hub to Stores Mode - Distribution from warehouse to selected stores
     */
    private function executeHubToStores(TransferSession $session): TransferResult
    {
        $params = $session->getParameters();
        
        $hubOutlet = $this->outletRepo->findWarehouseById($params['source_outlet']);
        $targetOutlets = $this->outletRepo->findByIds($params['dest_outlets']);
        
        $products = $this->productRepo->getAvailableProducts($hubOutlet->getId());
        
        $transfers = [];
        $totalProcessed = 0;
        
        foreach ($targetOutlets as $outlet) {
            $allocation = $this->allocationService->calculateHubAllocation(
                $hubOutlet,
                $outlet,
                $products,
                $params
            );
            
            if ($allocation->hasAllocations()) {
                $transfer = $this->createTransfer($outlet, $allocation, $session);
                $transfers[] = $transfer;
                $totalProcessed += $allocation->getTotalQuantity();
            }
        }
        
        return new TransferResult($transfers, $totalProcessed, $this->metrics);
    }
    
    /**
     * Create and persist transfer record
     */
    private function createTransfer(
        Outlet $destination, 
        ProductAllocation $allocation, 
        TransferSession $session
    ): Transfer {
        
        $transfer = new Transfer([
            'outlet_from' => $allocation->getSourceOutlet()->getId(),
            'outlet_to' => $destination->getId(),
            'transfer_date' => date('Y-m-d'),
            'transfer_notes' => $session->getNotes(),
            'created_by' => $session->getUserId(),
            'status' => 'pending'
        ]);
        
        // Save transfer header
        $transferId = $this->transferRepo->create($transfer);
        $transfer->setId($transferId);
        
        // Save product lines
        foreach ($allocation->getProducts() as $productAllocation) {
            $this->transferRepo->addProductLine(
                $transferId,
                $productAllocation->getProduct(),
                $productAllocation->getQuantity(),
                $productAllocation->getMetadata()
            );
        }
        
        // Apply neural brain learning
        $this->neuralBrain->recordDecision($transfer, $allocation);
        
        return $transfer;
    }
    
    /**
     * Validate input parameters
     */
    private function validateParameters(array $params): void
    {
        $required = ['mode', 'simulate'];
        
        foreach ($required as $field) {
            if (!isset($params[$field])) {
                throw new TransferEngineException("Missing required parameter: {$field}");
            }
        }
        
        if (!in_array($params['mode'], ['all_stores', 'specific_transfer', 'hub_to_stores'])) {
            throw new TransferEngineException("Invalid transfer mode: {$params['mode']}");
        }
    }
    
    /**
     * Initialize transfer session
     */
    private function initializeSession(array $params): TransferSession
    {
        return new TransferSession($params, $this->config);
    }
    
    /**
     * Calculate performance metrics
     */
    private function calculateMetrics(TransferResult $result, float $executionTime): void
    {
        $this->metrics = [
            'execution_time' => $executionTime,
            'transfers_created' => count($result->getTransfers()),
            'products_processed' => $result->getTotalProcessed(),
            'memory_peak' => memory_get_peak_usage(true),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Log error for debugging
     */
    private function logError(\Exception $e, array $params): void
    {
        error_log("TransferEngine Error: " . $e->getMessage() . " | Params: " . json_encode($params));
    }
    
    /**
     * Get performance metrics
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
