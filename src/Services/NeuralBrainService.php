<?php
namespace NewTransferV3\Services;

use Exception;

/**
 * Neural Brain Service Integration
 * 
 * Provides AI-powered analytics and decision support
 */
class NeuralBrainService
{
    private array $config;
    private string $sessionId;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'api_url' => 'https://staff.vapeshed.co.nz/assets/functions/',
            'timeout' => 30,
            'enabled' => true
        ], $config);
    }
    
    /**
     * Initialize Neural Brain session
     */
    public function initializeSession(): string
    {
        if (!$this->config['enabled']) {
            return 'neural_brain_disabled';
        }
        
        $this->sessionId = 'transfer_' . uniqid() . '_' . date('YmdHis');
        
        // Log session start
        $this->logDecision('session_start', [
            'session_id' => $this->sessionId,
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => 'transfer_engine_mvc'
        ]);
        
        return $this->sessionId;
    }
    
    /**
     * Get AI recommendation for product allocation
     */
    public function getAllocationRecommendation(array $context): array
    {
        if (!$this->config['enabled']) {
            return $this->getDefaultRecommendation($context);
        }
        
        try {
            // Build recommendation request
            $request = [
                'action' => 'ai_recommendation',
                'context' => $context,
                'session_id' => $this->sessionId
            ];
            
            $response = $this->makeApiCall('neural_brain_api.php', $request);
            
            if ($response && isset($response['recommendation'])) {
                return $response['recommendation'];
            }
            
        } catch (Exception $e) {
            $this->logError('Neural Brain API call failed', $e);
        }
        
        // Fallback to default logic
        return $this->getDefaultRecommendation($context);
    }
    
    /**
     * Log transfer decision for learning
     */
    public function logDecision(string $type, array $data): void
    {
        if (!$this->config['enabled']) {
            return;
        }
        
        try {
            $logEntry = [
                'session_id' => $this->sessionId,
                'decision_type' => $type,
                'timestamp' => date('Y-m-d H:i:s'),
                'data' => $data
            ];
            
            $this->makeApiCall('log_decision.php', $logEntry);
            
        } catch (Exception $e) {
            // Silently fail - don't break transfer process for logging
            error_log("Neural Brain logging failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get product priority score from AI
     */
    public function getProductPriority(string $productId, string $outletId): float
    {
        if (!$this->config['enabled']) {
            return 0.5; // Default neutral priority
        }
        
        try {
            $request = [
                'action' => 'product_priority',
                'product_id' => $productId,
                'outlet_id' => $outletId,
                'session_id' => $this->sessionId
            ];
            
            $response = $this->makeApiCall('product_analytics.php', $request);
            
            return (float)($response['priority_score'] ?? 0.5);
            
        } catch (Exception $e) {
            return 0.5; // Default on error
        }
    }
    
    /**
     * Analyze transfer performance
     */
    public function analyzeTransferPerformance(array $transferData): array
    {
        if (!$this->config['enabled']) {
            return ['analysis' => 'Neural Brain disabled'];
        }
        
        try {
            $request = [
                'action' => 'analyze_transfer',
                'transfer_data' => $transferData,
                'session_id' => $this->sessionId
            ];
            
            $response = $this->makeApiCall('transfer_analytics.php', $request);
            
            return $response['analysis'] ?? ['status' => 'no_analysis'];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * Make API call to Neural Brain system
     */
    private function makeApiCall(string $endpoint, array $data): ?array
    {
        $url = rtrim($this->config['api_url'], '/') . '/' . $endpoint;
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($data),
                'timeout' => $this->config['timeout']
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("API call failed: {$url}");
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    /**
     * Get default recommendation when AI is unavailable
     */
    private function getDefaultRecommendation(array $context): array
    {
        return [
            'confidence' => 0.7,
            'allocation_strategy' => 'balanced',
            'priority_factors' => [
                'sales_velocity' => 0.4,
                'stock_level' => 0.3,
                'profit_margin' => 0.3
            ],
            'source' => 'default_algorithm'
        ];
    }
    
    /**
     * Log error for debugging
     */
    private function logError(string $message, Exception $e): void
    {
        error_log("Neural Brain Error: {$message} - " . $e->getMessage());
    }
    
    /**
     * Check if Neural Brain is enabled and available
     */
    public function isAvailable(): bool
    {
        return $this->config['enabled'];
    }
    
    /**
     * Get current session ID
     */
    public function getSessionId(): string
    {
        return $this->sessionId ?? 'no_session';
    }
}
