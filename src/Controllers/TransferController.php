<?php
namespace NewTransferV3\Controllers;

use NewTransferV3\Core\TransferEngine;
use NewTransferV3\Services\DatabaseService;
use NewTransferV3\Services\NeuralBrainService;
use Exception;

/**
 * Main Transfer Controller
 * 
 * Handles all transfer operations and API endpoints
 */
class TransferController
{
    private TransferEngine $engine;
    private DatabaseService $db;
    
    public function __construct(TransferEngine $engine, DatabaseService $db)
    {
        $this->engine = $engine;
        $this->db = $db;
    }
    
    /**
     * Main API endpoint for transfers
     */
    public function handleRequest(): array
    {
        try {
            $action = $_GET['action'] ?? $_POST['action'] ?? 'status';
            
            switch ($action) {
                case 'run':
                    return $this->runTransfer();
                    
                case 'seed_new_store':
                    return $this->seedNewStore();
                    
                case 'status':
                    return $this->getStatus();
                    
                case 'get_transfer':
                    return $this->getTransfer();
                    
                case 'test_connection':
                    return $this->testConnection();
                    
                default:
                    return $this->errorResponse("Unknown action: {$action}");
            }
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e);
        }
    }
    
    /**
     * Run transfer based on parameters
     */
    private function runTransfer(): array
    {
        $params = $this->getRequestParams();
        
        // Validate critical parameters
        if (!isset($params['simulate'])) {
            $params['simulate'] = 1; // Default to simulation for safety
        }
        
        $result = $this->engine->executeTransfer($params);
        
        return $this->successResponse($result, 'Transfer executed successfully');
    }
    
    /**
     * Seed new store (CRITICAL for today's operations)
     */
    private function seedNewStore(): array
    {
        $newStoreId = $_GET['store_id'] ?? $_POST['store_id'] ?? null;
        
        if (!$newStoreId) {
            return $this->errorResponse('New store ID is required');
        }
        
        $params = $this->getRequestParams();
        $result = $this->engine->executeNewStoreSeed($newStoreId, $params);
        
        return $this->successResponse($result, 'New store seeding completed');
    }
    
    /**
     * Get system status
     */
    private function getStatus(): array
    {
        return $this->successResponse([
            'system' => 'NewTransferV3 MVC Engine',
            'version' => '2.0.0',
            'status' => 'operational',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => 'connected'
        ]);
    }
    
    /**
     * Get specific transfer details
     */
    private function getTransfer(): array
    {
        $transferId = $_GET['transfer_id'] ?? $_POST['transfer_id'] ?? null;
        
        if (!$transferId) {
            return $this->errorResponse('Transfer ID is required');
        }
        
        $transfer = $this->db->getTransfer($transferId);
        if (!$transfer) {
            return $this->errorResponse('Transfer not found');
        }
        
        $lines = $this->db->getTransferLines($transferId);
        
        return $this->successResponse([
            'transfer' => $transfer,
            'lines' => $lines,
            'total_lines' => count($lines)
        ]);
    }
    
    /**
     * Test database connection
     */
    private function testConnection(): array
    {
        try {
            $outlets = $this->db->getOutlets();
            $products = $this->db->getActiveProducts(10);
            
            return $this->successResponse([
                'database' => 'connected',
                'outlets_count' => count($outlets),
                'sample_products' => count($products),
                'test_time' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            return $this->errorResponse('Database connection failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Get all request parameters
     */
    private function getRequestParams(): array
    {
        $params = array_merge($_GET, $_POST);
        
        // Convert string numbers to integers where needed
        $numericParams = ['simulate', 'cover', 'buffer_pct', 'max_products', 'default_floor_qty'];
        foreach ($numericParams as $param) {
            if (isset($params[$param])) {
                $params[$param] = is_numeric($params[$param]) ? (int)$params[$param] : $params[$param];
            }
        }
        
        return $params;
    }
    
    /**
     * Success response format
     */
    private function successResponse(array $data, string $message = 'Success'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Error response format
     */
    private function errorResponse(string $message, ?Exception $exception = null): array
    {
        $response = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($exception && defined('DEBUG') && DEBUG) {
            $response['debug'] = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        return $response;
    }
}
