<?php
/**
 * Neural Brain Integration Helper for NewTransferV3 Transfer Engine
 * 
 * Provides seamless integration between the transfer engine and Neural Brain Enterprise system.
 * Enables intelligent memory storage, pattern recognition, and solution retrieval.
 * 
 * @author GitHub Copilot
 * @created September 10, 2025
 * @version 1.0
 */

declare(strict_types=1);

class NeuralBrainIntegration {
    private mysqli $con;
    private string $session_id;
    private array $context_cache = [];
    private bool $enabled = true;
    
    public function __construct(mysqli $connection) {
        $this->con = $connection;
        $this->session_id = $this->generateSessionId();
        $this->enabled = $this->verifyNeuralTables();
    }
    
    /**
     * Generate unique session ID for this transfer run
     */
    private function generateSessionId(): string {
        return 'transfer_' . date('YmdHis') . '_' . substr(uniqid(), -6);
    }
    
    /**
     * Verify Neural Brain tables exist
     */
    private function verifyNeuralTables(): bool {
        $required_tables = ['neural_memory_core', 'neural_ai_agents', 'neural_projects'];
        
        foreach ($required_tables as $table) {
            $result = $this->con->query("SHOW TABLES LIKE '$table'");
            if (!$result || $result->num_rows === 0) {
                error_log("NeuralBrainIntegration: Missing table $table - Neural Brain disabled");
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Store a transfer solution in Neural Brain memory
     */
    public function storeSolution(string $title, string $content, array $tags = [], float $confidence = 0.85): ?int {
        if (!$this->enabled) return null;
        
        try {
            $tags_json = !empty($tags) ? json_encode($tags) : null;
            $summary = $this->generateSummary($content);
            
            // Format content as JSON to match database constraint
            $content_json = json_encode([
                'description' => $content,
                'title' => $title,
                'memory_type' => 'solution',
                'timestamp' => date('Y-m-d H:i:s'),
                'source' => 'transfer_engine'
            ]);
            
            $sql = "INSERT INTO neural_memory_core 
                    (session_id, memory_type, system_context, title, memory_content, summary, tags, 
                     confidence_score, created_by_agent, is_active, access_count, importance_weight)
                    VALUES (?, 'solution', 'NewTransferV3', ?, ?, ?, ?, ?, 'transfer_engine', 1, 0, 0.8)";
                    
            $stmt = $this->con->prepare($sql);
            if (!$stmt) {
                error_log("NeuralBrain: Failed to prepare solution storage: " . $this->con->error);
                return null;
            }
            
            $stmt->bind_param('sssssd', $this->session_id, $title, $content_json, $summary, $tags_json, $confidence);
            
            if ($stmt->execute()) {
                $memory_id = $this->con->insert_id;
                error_log("NeuralBrain: Stored solution - ID: $memory_id, Title: '$title'");
                return $memory_id;
            } else {
                error_log("NeuralBrain: Failed to store solution: " . $stmt->error);
                return null;
            }
        } catch (Exception $e) {
            error_log("NeuralBrain: Exception storing solution: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Store a transfer error with solution in Neural Brain
     */
    public function storeError(string $error_msg, string $solution = '', string $context = '', float $confidence = 0.9): ?int {
        if (!$this->enabled) return null;
        
        $title = "Transfer Error: " . $this->truncate($error_msg, 60);
        $content = "Error: $error_msg\n\n";
        
        if (!empty($solution)) {
            $content .= "Solution Applied: $solution\n\n";
        }
        
        if (!empty($context)) {
            $content .= "Context: $context\n\n";
        }
        
        $content .= "Session ID: {$this->session_id}\n";
        $content .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        
        $tags = ['transfer', 'error'];
        if (!empty($solution)) {
            $tags[] = 'solution';
            $tags[] = 'resolved';
        } else {
            $tags[] = 'unresolved';
        }
        
        return $this->storeSolution($title, $content, $tags, $confidence);
    }
    
    /**
     * Store a transfer pattern or optimization
     */
    public function storePattern(string $pattern_name, string $description, array $metrics = [], float $confidence = 0.8): ?int {
        if (!$this->enabled) return null;
        
        $title = "Transfer Pattern: $pattern_name";
        $content = "Pattern Description: $description\n\n";
        
        if (!empty($metrics)) {
            $content .= "Performance Metrics:\n";
            foreach ($metrics as $key => $value) {
                $content .= "- $key: $value\n";
            }
            $content .= "\n";
        }
        
        $content .= "Session ID: {$this->session_id}\n";
        $content .= "Detected: " . date('Y-m-d H:i:s') . "\n";
        
        $tags = ['transfer', 'pattern', 'optimization', 'performance'];
        
        return $this->storeSolution($title, $content, $tags, $confidence);
    }
    
    /**
     * Find similar solutions from Neural Brain memory
     */
    public function findSimilarSolutions(string $search_term, int $limit = 5): array {
        if (!$this->enabled) return [];
        
        // Check cache first
        $cache_key = md5($search_term . $limit);
        if (isset($this->context_cache[$cache_key])) {
            return $this->context_cache[$cache_key];
        }
        
        try {
            // First try FULLTEXT search
            $sql = "SELECT id, title, memory_content, summary, confidence_score, created_at, access_count,
                           MATCH(title, summary, memory_content) AGAINST(? IN BOOLEAN MODE) as relevance_score
                    FROM neural_memory_core 
                    WHERE system_context = 'NewTransferV3' 
                    AND memory_type IN ('solution', 'pattern', 'optimization', 'error')
                    AND is_active = 1
                    AND (MATCH(title, summary, memory_content) AGAINST(? IN BOOLEAN MODE)
                         OR title LIKE ? OR memory_content LIKE ? OR summary LIKE ?)
                    ORDER BY relevance_score DESC, confidence_score DESC, created_at DESC
                    LIMIT ?";
                    
            $stmt = $this->con->prepare($sql);
            
            // If FULLTEXT fails, fallback to LIKE-only search
            if (!$stmt) {
                error_log("NeuralBrain: FULLTEXT search failed, falling back to LIKE search");
                $sql = "SELECT id, title, memory_content, summary, confidence_score, created_at, access_count,
                               0 as relevance_score
                        FROM neural_memory_core 
                        WHERE system_context = 'NewTransferV3' 
                        AND memory_type IN ('solution', 'pattern', 'optimization', 'error')
                        AND is_active = 1
                        AND (title LIKE ? OR memory_content LIKE ? OR summary LIKE ?)
                        ORDER BY confidence_score DESC, created_at DESC
                        LIMIT ?";
                        
                $stmt = $this->con->prepare($sql);
                
                if (!$stmt) {
                    error_log("NeuralBrain: Failed to prepare fallback search query: " . $this->con->error);
                    return [];
                }
                
                // For LIKE-only search, we only need 4 parameters
                $search_wildcard = "%$search_term%";
                $stmt->bind_param('sssi', $search_wildcard, $search_wildcard, $search_wildcard, $limit);
                
            } else {
                // For FULLTEXT search, we need all 6 parameters
                $search_wildcard = "%$search_term%";
                $stmt->bind_param('sssssi', $search_term, $search_term, $search_wildcard, $search_wildcard, $search_wildcard, $limit);
            }
            
            if (!$stmt->execute()) {
                error_log("NeuralBrain: Failed to execute search: " . $stmt->error);
                return [];
            }
            
            $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Update access counts for returned results
            foreach ($results as $result) {
                $this->updateAccessCount($result['id']);
            }
            
            // Cache results
            $this->context_cache[$cache_key] = $results;
            
            return $results;
        } catch (Exception $e) {
            error_log("NeuralBrain: Exception in search: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Load session context from previous transfer runs
     */
    public function loadSessionContext(int $limit = 10): array {
        if (!$this->enabled) return [];
        
        try {
            $sql = "SELECT title, memory_content, memory_type, confidence_score, created_at
                    FROM neural_memory_core 
                    WHERE system_context = 'NewTransferV3'
                    AND is_active = 1
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ORDER BY created_at DESC, confidence_score DESC
                    LIMIT ?";
                    
            $stmt = $this->con->prepare($sql);
            if (!$stmt) {
                error_log("NeuralBrain: Failed to prepare context query: " . $this->con->error);
                return [];
            }
            
            $stmt->bind_param('i', $limit);
            
            if (!$stmt->execute()) {
                error_log("NeuralBrain: Failed to load context: " . $stmt->error);
                return [];
            }
            
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("NeuralBrain: Exception loading context: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get Neural Brain statistics
     */
    public function getStats(): array {
        if (!$this->enabled) return ['enabled' => false];
        
        try {
            $stats = ['enabled' => true];
            
            // Total memories
            $result = $this->con->query("SELECT COUNT(*) as total FROM neural_memory_core WHERE system_context = 'NewTransferV3' AND is_active = 1");
            $stats['total_memories'] = $result ? $result->fetch_assoc()['total'] : 0;
            
            // Solutions count
            $result = $this->con->query("SELECT COUNT(*) as total FROM neural_memory_core WHERE system_context = 'NewTransferV3' AND memory_type = 'solution' AND is_active = 1");
            $stats['solutions'] = $result ? $result->fetch_assoc()['total'] : 0;
            
            // Error count
            $result = $this->con->query("SELECT COUNT(*) as total FROM neural_memory_core WHERE system_context = 'NewTransferV3' AND memory_type = 'error' AND is_active = 1");
            $stats['errors'] = $result ? $result->fetch_assoc()['total'] : 0;
            
            // Patterns count
            $result = $this->con->query("SELECT COUNT(*) as total FROM neural_memory_core WHERE system_context = 'NewTransferV3' AND memory_type = 'pattern' AND is_active = 1");
            $stats['patterns'] = $result ? $result->fetch_assoc()['total'] : 0;
            
            // Active agents
            $result = $this->con->query("SELECT COUNT(*) as total FROM neural_ai_agents WHERE status = 'active'");
            $stats['active_agents'] = $result ? $result->fetch_assoc()['total'] : 0;
            
            return $stats;
        } catch (Exception $e) {
            error_log("NeuralBrain: Exception getting stats: " . $e->getMessage());
            return ['enabled' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check for patterns before starting transfer operations
     */
    public function checkPreTransferPatterns(array $transfer_params): array {
        if (!$this->enabled) return [];
        
        $search_terms = [];
        
        // Build search terms from transfer parameters
        if (isset($transfer_params['warehouse_id'])) {
            $search_terms[] = "warehouse {$transfer_params['warehouse_id']}";
        }
        if (isset($transfer_params['outlet_ids']) && is_array($transfer_params['outlet_ids'])) {
            $search_terms[] = "outlets " . implode(' ', $transfer_params['outlet_ids']);
        }
        if (isset($transfer_params['product_types'])) {
            $search_terms[] = "products {$transfer_params['product_types']}";
        }
        
        $all_patterns = [];
        foreach ($search_terms as $term) {
            $patterns = $this->findSimilarSolutions($term, 3);
            $all_patterns = array_merge($all_patterns, $patterns);
        }
        
        // Remove duplicates and sort by relevance
        $unique_patterns = [];
        foreach ($all_patterns as $pattern) {
            $unique_patterns[$pattern['id']] = $pattern;
        }
        
        $final_patterns = array_slice($unique_patterns, 0, 5);
        
        // Format results as expected by trans.php
        $formatted_solutions = [];
        foreach ($final_patterns as $pattern) {
            $formatted_solutions[] = [
                'context' => $pattern['title'],
                'created_at' => $pattern['created_at'],
                'success' => $pattern['confidence_score'] >= 0.7
            ];
        }
        
        // Generate recommendations
        $recommendations = [];
        if (!empty($formatted_solutions)) {
            $recommendations[] = "Found " . count($formatted_solutions) . " similar transfer patterns";
            if (count($formatted_solutions) >= 3) {
                $recommendations[] = "High confidence patterns detected - consider reviewing previous approaches";
            }
        }
        
        return [
            'similar_solutions' => $formatted_solutions,
            'recommendations' => $recommendations
        ];
    }
    
    /**
     * Report transfer completion with metrics
     */
    public function reportTransferComplete(array $metrics, bool $success = true): void {
        if (!$this->enabled) return;
        
        $title = $success ? "Transfer Completed Successfully" : "Transfer Failed";
        $content = "Transfer Run Metrics:\n\n";
        
        foreach ($metrics as $key => $value) {
            $content .= "- $key: $value\n";
        }
        
        $content .= "\nSession ID: {$this->session_id}\n";
        $content .= "Completion Time: " . date('Y-m-d H:i:s') . "\n";
        $content .= "Status: " . ($success ? 'SUCCESS' : 'FAILED') . "\n";
        
        $tags = ['transfer', 'completion', $success ? 'success' : 'failure'];
        if (isset($metrics['execution_time_seconds'])) {
            $tags[] = 'performance';
        }
        
        $confidence = $success ? 0.85 : 0.7;
        $this->storeSolution($title, $content, $tags, $confidence);
    }
    
    /**
     * Get current session ID
     */
    public function getSessionId(): string {
        return $this->session_id;
    }
    
    /**
     * Check if Neural Brain is enabled
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }
    
    // Private helper methods
    
    private function updateAccessCount(int $memory_id): void {
        try {
            $sql = "UPDATE neural_memory_core SET access_count = access_count + 1, last_accessed_at = NOW() WHERE id = ?";
            $stmt = $this->con->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $memory_id);
                $stmt->execute();
            }
        } catch (Exception $e) {
            // Silently fail access count updates
            error_log("NeuralBrain: Failed to update access count: " . $e->getMessage());
        }
    }
    
    private function generateSummary(string $content, int $max_length = 200): string {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        if (strlen($content) <= $max_length) {
            return $content;
        }
        
        $truncated = substr($content, 0, $max_length);
        $last_space = strrpos($truncated, ' ');
        
        if ($last_space !== false) {
            $truncated = substr($truncated, 0, $last_space);
        }
        
        return $truncated . '...';
    }
    
    private function truncate(string $text, int $length): string {
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length - 3) . '...';
    }
}

// Global neural brain instance
$neural_brain = null;

/**
 * Initialize Neural Brain integration
 */
function init_neural_brain(mysqli $con): ?NeuralBrainIntegration {
    global $neural_brain;
    
    try {
        $neural_brain = new NeuralBrainIntegration($con);
        if ($neural_brain->isEnabled()) {
            error_log("NeuralBrain: Integration initialized successfully - Session: " . $neural_brain->getSessionId());
            return $neural_brain;
        } else {
            error_log("NeuralBrain: Integration disabled (missing tables or error)");
            return null;
        }
    } catch (Exception $e) {
        error_log("NeuralBrain: Failed to initialize: " . $e->getMessage());
        return null;
    }
}

/**
 * Get the global neural brain instance
 */
function get_neural_brain(): ?NeuralBrainIntegration {
    global $neural_brain;
    return $neural_brain;
}
?>
