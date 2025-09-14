<?php
/**
 * GPT Auto-Categorization Engine
 * Intelligent product categorization using AI with learning capabilities
 * 
 * Features:
 * - AI-powered product category detection
 * - Learning from user corrections
 * - Batch processing for efficiency
 * - Confidence scoring and validation
 * - Category hierarchy management
 */

declare(strict_types=1);

class GPTAutoCategorization {
    private $db;
    private $logger;
    private $session_id;
    private $openai_api_key;
    private $confidence_threshold = 85.0;
    
    // Category mappings for vape products
    private const CATEGORY_HIERARCHY = [
        'Devices' => [
            'Pod Systems',
            'Mod Kits', 
            'Disposables',
            'Starter Kits',
            'Advanced Mods'
        ],
        'E-Liquids' => [
            'Freebase Nicotine',
            'Nicotine Salts', 
            'Shortfills',
            'Zero Nicotine'
        ],
        'Accessories' => [
            'Coils',
            'Tanks',
            'Batteries',
            'Chargers',
            'Cases'
        ],
        'Consumables' => [
            'Pods',
            'Cartridges',
            'Replacement Parts'
        ]
    ];
    
    public function __construct($database, ?string $api_key = null) {
        $this->db = $database;
        $this->session_id = 'CAT_' . date('YmdHis') . '_' . substr(md5(uniqid()), 0, 6);
        $this->openai_api_key = $api_key ?? $this->getApiKey();
        
        require_once __DIR__ . '/TransferLogger.php';
        $this->logger = new TransferLogger($this->session_id, true);
        
        $this->initializeCategoryTables();
        
        $this->logger->info("GPT Auto-Categorization initialized", [
            'session_id' => $this->session_id,
            'api_available' => !empty($this->openai_api_key)
        ]);
    }
    
    /**
     * Categorize all uncategorized products
     */
    public function categorizeAllProducts(array $options = []): array {
        $options = array_merge([
            'batch_size' => 50,
            'force_recategorize' => false,
            'min_confidence' => $this->confidence_threshold,
            'simulate' => false
        ], $options);
        
        $this->logger->info("Starting batch categorization", $options);
        
        try {
            $start_time = microtime(true);
            
            // Get uncategorized products
            $products = $this->getUncategorizedProducts($options);
            
            if (empty($products)) {
                return [
                    'success' => true,
                    'message' => 'No products require categorization',
                    'processed' => 0,
                    'session_id' => $this->session_id
                ];
            }
            
            $results = [
                'success' => true,
                'session_id' => $this->session_id,
                'total_products' => count($products),
                'processed' => 0,
                'categorized' => 0,
                'failed' => 0,
                'low_confidence' => 0,
                'categories_assigned' => [],
                'execution_time' => 0,
                'details' => []
            ];
            
            // Process in batches
            $batches = array_chunk($products, $options['batch_size']);
            
            foreach ($batches as $batch_num => $batch) {
                $this->logger->info("Processing batch " . ($batch_num + 1) . "/" . count($batches));
                
                $batch_result = $this->processBatch($batch, $options);
                
                $results['processed'] += $batch_result['processed'];
                $results['categorized'] += $batch_result['categorized'];
                $results['failed'] += $batch_result['failed'];
                $results['low_confidence'] += $batch_result['low_confidence'];
                $results['details'] = array_merge($results['details'], $batch_result['details']);
                
                // Merge category counts
                foreach ($batch_result['categories_assigned'] as $category => $count) {
                    $results['categories_assigned'][$category] = 
                        ($results['categories_assigned'][$category] ?? 0) + $count;
                }
                
                // Rate limiting - don't overwhelm API
                if ($batch_num < count($batches) - 1) {
                    sleep(2);
                }
            }
            
            $results['execution_time'] = round(microtime(true) - $start_time, 3);
            $results['success_rate'] = $results['processed'] > 0 
                ? round(($results['categorized'] / $results['processed']) * 100, 1) 
                : 0;
            
            $this->logger->info("Batch categorization completed", [
                'processed' => $results['processed'],
                'categorized' => $results['categorized'],
                'success_rate' => $results['success_rate'] . '%'
            ]);
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error("Batch categorization failed", [
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
     * Categorize a single product using AI
     */
    public function categorizeProduct(string $product_id, bool $force = false): array {
        try {
            // Get product details
            $product = $this->getProductDetails($product_id);
            
            if (!$product) {
                throw new Exception("Product not found: {$product_id}");
            }
            
            // Check if already categorized (unless forced)
            if (!$force && !empty($product['ai_category'])) {
                return [
                    'success' => true,
                    'already_categorized' => true,
                    'category' => $product['ai_category'],
                    'confidence' => $product['ai_confidence'] ?? 0
                ];
            }
            
            // Generate AI categorization
            $ai_result = $this->generateAICategory($product);
            
            if (!$ai_result['success']) {
                return $ai_result;
            }
            
            // Validate and store result
            $validation = $this->validateCategory($ai_result['category'], $ai_result['confidence']);
            
            if ($validation['valid'] && $ai_result['confidence'] >= $this->confidence_threshold) {
                $this->storeCategoryResult($product_id, $ai_result);
                
                return [
                    'success' => true,
                    'product_id' => $product_id,
                    'product_name' => $product['name'],
                    'category' => $ai_result['category'],
                    'subcategory' => $ai_result['subcategory'] ?? null,
                    'confidence' => $ai_result['confidence'],
                    'reasoning' => $ai_result['reasoning'] ?? null
                ];
            } else {
                // Store for manual review
                $this->storeForReview($product_id, $ai_result, $validation);
                
                return [
                    'success' => false,
                    'reason' => 'Low confidence or invalid category',
                    'confidence' => $ai_result['confidence'],
                    'suggested_category' => $ai_result['category'],
                    'requires_review' => true
                ];
            }
            
        } catch (Exception $e) {
            $this->logger->error("Product categorization failed", [
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Learn from user corrections to improve AI accuracy
     */
    public function learnFromCorrection(string $product_id, string $correct_category, ?string $user_feedback = null): array {
        try {
            $product = $this->getProductDetails($product_id);
            
            if (!$product) {
                throw new Exception("Product not found: {$product_id}");
            }
            
            // Store the correction
            $correction_id = $this->storeCorrection($product_id, $correct_category, $user_feedback);
            
            // Update the product category
            $this->updateProductCategory($product_id, $correct_category, 100.0, 'user_corrected');
            
            // Analyze correction for pattern learning
            $this->analyzeCorrection($product, $correct_category, $user_feedback);
            
            $this->logger->info("User correction learned", [
                'product_id' => $product_id,
                'product_name' => $product['name'],
                'correct_category' => $correct_category,
                'correction_id' => $correction_id
            ]);
            
            return [
                'success' => true,
                'correction_id' => $correction_id,
                'message' => 'Correction learned and applied'
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Learning from correction failed", [
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get categorization statistics and accuracy metrics
     */
    public function getCategorization Statistics(): array {
        try {
            $stats = [];
            
            // Overall categorization status
            $result = $this->db->query("
                SELECT 
                    COUNT(*) as total_products,
                    COUNT(CASE WHEN ai_category IS NOT NULL THEN 1 END) as categorized,
                    COUNT(CASE WHEN ai_category IS NOT NULL AND ai_confidence >= {$this->confidence_threshold} THEN 1 END) as high_confidence,
                    AVG(CASE WHEN ai_confidence IS NOT NULL THEN ai_confidence END) as avg_confidence
                FROM vend_products 
                WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'
            ");
            
            if ($result && $row = $result->fetch_assoc()) {
                $stats['overall'] = [
                    'total_products' => intval($row['total_products']),
                    'categorized' => intval($row['categorized']),
                    'high_confidence' => intval($row['high_confidence']),
                    'avg_confidence' => round(floatval($row['avg_confidence'] ?? 0), 2),
                    'categorization_rate' => $row['total_products'] > 0 
                        ? round((intval($row['categorized']) / intval($row['total_products'])) * 100, 1) 
                        : 0
                ];
            }
            
            // Category distribution
            $result = $this->db->query("
                SELECT 
                    ai_category,
                    COUNT(*) as product_count,
                    AVG(ai_confidence) as avg_confidence
                FROM vend_products 
                WHERE ai_category IS NOT NULL
                AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
                GROUP BY ai_category
                ORDER BY product_count DESC
            ");
            
            $stats['category_distribution'] = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $stats['category_distribution'][] = [
                        'category' => $row['ai_category'],
                        'product_count' => intval($row['product_count']),
                        'avg_confidence' => round(floatval($row['avg_confidence']), 2)
                    ];
                }
            }
            
            // User corrections and accuracy
            $result = $this->db->query("
                SELECT 
                    COUNT(*) as total_corrections,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_corrections
                FROM product_category_corrections
            ");
            
            if ($result && $row = $result->fetch_assoc()) {
                $stats['corrections'] = [
                    'total_corrections' => intval($row['total_corrections']),
                    'recent_corrections' => intval($row['recent_corrections'])
                ];
            }
            
            return [
                'success' => true,
                'statistics' => $stats,
                'session_id' => $this->session_id
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate AI category using OpenAI API
     */
    private function generateAICategory(array $product): array {
        if (empty($this->openai_api_key)) {
            // Fallback to rule-based categorization
            return $this->generateRuleBasedCategory($product);
        }
        
        try {
            $prompt = $this->buildCategorization Prompt($product);
            
            $response = $this->callOpenAI($prompt);
            
            if ($response['success']) {
                return $this->parseAIResponse($response['data']);
            } else {
                // Fallback to rules
                $this->logger->warning("AI API failed, using rule-based fallback", [
                    'error' => $response['error']
                ]);
                return $this->generateRuleBasedCategory($product);
            }
            
        } catch (Exception $e) {
            $this->logger->error("AI categorization failed", [
                'product_id' => $product['id'],
                'error' => $e->getMessage()
            ]);
            
            return $this->generateRuleBasedCategory($product);
        }
    }
    
    /**
     * Build categorization prompt for AI
     */
    private function buildCategorizationPrompt(array $product): string {
        $categories = json_encode(self::CATEGORY_HIERARCHY, JSON_PRETTY_PRINT);
        
        return "You are an expert in vape/e-cigarette product categorization for a retail chain.

Product Details:
- Name: {$product['name']}
- Brand: {$product['brand_name'] ?? 'Unknown'}
- Description: {$product['description'] ?? 'No description'}
- Price: \${$product['retail_price'] ?? 'Unknown'}
- Weight: {$product['weight_grams'] ?? 'Unknown'}g

Available Categories:
{$categories}

Task: Categorize this product with high accuracy.

Respond with JSON format:
{
    \"category\": \"Main category name\",
    \"subcategory\": \"Subcategory name (if applicable)\",
    \"confidence\": 85.5,
    \"reasoning\": \"Brief explanation of categorization logic\"
}

Requirements:
- Use ONLY categories from the provided hierarchy
- Confidence must be 0-100 (higher = more confident)
- Provide brief reasoning for your choice
- If unsure, lower the confidence score";
    }
    
    /**
     * Rule-based categorization fallback
     */
    private function generateRuleBasedCategory(array $product): array {
        $name = strtolower($product['name'] ?? '');
        $description = strtolower($product['description'] ?? '');
        $text = $name . ' ' . $description;
        
        // Device keywords
        if (preg_match('/\b(pod|kit|mod|device|starter|vape pen)\b/', $text)) {
            if (preg_match('/\b(disposable|throw away|single use)\b/', $text)) {
                return [
                    'success' => true,
                    'category' => 'Devices',
                    'subcategory' => 'Disposables',
                    'confidence' => 80.0,
                    'reasoning' => 'Rule-based: Contains disposable device keywords',
                    'method' => 'rule_based'
                ];
            } elseif (preg_match('/\bpod\b/', $text)) {
                return [
                    'success' => true,
                    'category' => 'Devices', 
                    'subcategory' => 'Pod Systems',
                    'confidence' => 75.0,
                    'reasoning' => 'Rule-based: Contains pod system keywords',
                    'method' => 'rule_based'
                ];
            } else {
                return [
                    'success' => true,
                    'category' => 'Devices',
                    'subcategory' => 'Mod Kits',
                    'confidence' => 70.0,
                    'reasoning' => 'Rule-based: Contains general device keywords',
                    'method' => 'rule_based'
                ];
            }
        }
        
        // E-liquid keywords
        if (preg_match('/\b(e-?liquid|juice|eliquid|vape liquid|shortfill)\b/', $text)) {
            if (preg_match('/\b(salt|salts|nic salt)\b/', $text)) {
                return [
                    'success' => true,
                    'category' => 'E-Liquids',
                    'subcategory' => 'Nicotine Salts',
                    'confidence' => 85.0,
                    'reasoning' => 'Rule-based: Contains nicotine salt keywords',
                    'method' => 'rule_based'
                ];
            } elseif (preg_match('/\b(shortfill|short fill)\b/', $text)) {
                return [
                    'success' => true,
                    'category' => 'E-Liquids',
                    'subcategory' => 'Shortfills',
                    'confidence' => 85.0,
                    'reasoning' => 'Rule-based: Contains shortfill keywords',
                    'method' => 'rule_based'
                ];
            } elseif (preg_match('/\b(0mg|zero|no nicotine)\b/', $text)) {
                return [
                    'success' => true,
                    'category' => 'E-Liquids',
                    'subcategory' => 'Zero Nicotine',
                    'confidence' => 80.0,
                    'reasoning' => 'Rule-based: Contains zero nicotine keywords',
                    'method' => 'rule_based'
                ];
            } else {
                return [
                    'success' => true,
                    'category' => 'E-Liquids',
                    'subcategory' => 'Freebase Nicotine',
                    'confidence' => 75.0,
                    'reasoning' => 'Rule-based: Contains e-liquid keywords',
                    'method' => 'rule_based'
                ];
            }
        }
        
        // Accessory keywords
        if (preg_match('/\b(coil|tank|battery|charger|case|replacement)\b/', $text)) {
            if (preg_match('/\bcoil\b/', $text)) {
                return [
                    'success' => true,
                    'category' => 'Accessories',
                    'subcategory' => 'Coils',
                    'confidence' => 90.0,
                    'reasoning' => 'Rule-based: Contains coil keywords',
                    'method' => 'rule_based'
                ];
            } elseif (preg_match('/\b(tank|atomizer)\b/', $text)) {
                return [
                    'success' => true,
                    'category' => 'Accessories',
                    'subcategory' => 'Tanks',
                    'confidence' => 85.0,
                    'reasoning' => 'Rule-based: Contains tank keywords', 
                    'method' => 'rule_based'
                ];
            } elseif (preg_match('/\bbatter(y|ies)\b/', $text)) {
                return [
                    'success' => true,
                    'category' => 'Accessories',
                    'subcategory' => 'Batteries',
                    'confidence' => 85.0,
                    'reasoning' => 'Rule-based: Contains battery keywords',
                    'method' => 'rule_based'
                ];
            } else {
                return [
                    'success' => true,
                    'category' => 'Accessories',
                    'subcategory' => 'Cases',
                    'confidence' => 60.0,
                    'reasoning' => 'Rule-based: General accessory keywords',
                    'method' => 'rule_based'
                ];
            }
        }
        
        // Low confidence fallback
        return [
            'success' => true,
            'category' => 'Accessories',
            'subcategory' => null,
            'confidence' => 30.0,
            'reasoning' => 'Rule-based: No clear keywords found, defaulting to Accessories',
            'method' => 'rule_based_fallback'
        ];
    }
    
    /**
     * Call OpenAI API for categorization
     */
    private function callOpenAI(string $prompt): array {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert product categorization AI for a vape retail chain. Always respond with valid JSON.'
                ],
                [
                    'role' => 'user', 
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 200,
            'temperature' => 0.3
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->openai_api_key,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            return [
                'success' => false,
                'error' => curl_error($ch)
            ];
        }
        
        curl_close($ch);
        
        if ($http_code !== 200) {
            return [
                'success' => false,
                'error' => "API request failed with status {$http_code}"
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result || !isset($result['choices'][0]['message']['content'])) {
            return [
                'success' => false,
                'error' => 'Invalid API response format'
            ];
        }
        
        return [
            'success' => true,
            'data' => $result['choices'][0]['message']['content']
        ];
    }
    
    /**
     * Parse AI response JSON
     */
    private function parseAIResponse(string $response): array {
        try {
            $data = json_decode(trim($response), true);
            
            if (!$data) {
                throw new Exception('Invalid JSON response from AI');
            }
            
            return [
                'success' => true,
                'category' => $data['category'] ?? '',
                'subcategory' => $data['subcategory'] ?? null,
                'confidence' => floatval($data['confidence'] ?? 0),
                'reasoning' => $data['reasoning'] ?? null,
                'method' => 'ai_generated'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to parse AI response: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Initialize category tables
     */
    private function initializeCategoryTables(): void {
        // Create category corrections table if not exists
        $this->db->query("
            CREATE TABLE IF NOT EXISTS product_category_corrections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id VARCHAR(255) NOT NULL,
                original_category VARCHAR(100),
                correct_category VARCHAR(100) NOT NULL,
                user_feedback TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                session_id VARCHAR(50),
                INDEX idx_product_id (product_id),
                INDEX idx_created_at (created_at)
            )
        ");
        
        // Add AI categorization columns to products table if not exist
        $columns_to_add = [
            'ai_category VARCHAR(100)',
            'ai_subcategory VARCHAR(100)', 
            'ai_confidence DECIMAL(5,2)',
            'ai_reasoning TEXT',
            'ai_method VARCHAR(50)',
            'ai_updated_at TIMESTAMP NULL'
        ];
        
        foreach ($columns_to_add as $column) {
            $column_name = explode(' ', $column)[0];
            
            $result = $this->db->query("SHOW COLUMNS FROM vend_products LIKE '{$column_name}'");
            if (!$result || $result->num_rows === 0) {
                $this->db->query("ALTER TABLE vend_products ADD COLUMN {$column}");
            }
        }
    }
    
    /**
     * Get uncategorized products
     */
    private function getUncategorizedProducts(array $options): array {
        $where_clause = $options['force_recategorize'] 
            ? "(deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')"
            : "(ai_category IS NULL OR ai_category = '') AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        
        $query = "
            SELECT 
                id,
                name,
                description,
                brand_id,
                retail_price,
                weight_grams,
                ai_category,
                ai_confidence
            FROM vend_products
            WHERE {$where_clause}
            ORDER BY created_at DESC
        ";
        
        $result = $this->db->query($query);
        
        $products = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $products[] = $row;
            }
        }
        
        return $products;
    }
    
    /**
     * Process a batch of products
     */
    private function processBatch(array $products, array $options): array {
        $result = [
            'processed' => 0,
            'categorized' => 0,
            'failed' => 0,
            'low_confidence' => 0,
            'categories_assigned' => [],
            'details' => []
        ];
        
        foreach ($products as $product) {
            $category_result = $this->categorizeProduct($product['id'], $options['force_recategorize']);
            
            $result['processed']++;
            
            if ($category_result['success']) {
                $result['categorized']++;
                
                $category = $category_result['category'];
                $result['categories_assigned'][$category] = ($result['categories_assigned'][$category] ?? 0) + 1;
                
                $result['details'][] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'category' => $category,
                    'confidence' => $category_result['confidence'],
                    'status' => 'categorized'
                ];
            } else {
                if (isset($category_result['requires_review'])) {
                    $result['low_confidence']++;
                } else {
                    $result['failed']++;
                }
                
                $result['details'][] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'status' => 'failed',
                    'reason' => $category_result['reason'] ?? $category_result['error'] ?? 'Unknown error'
                ];
            }
        }
        
        return $result;
    }
    
    // Additional helper methods would go here...
    // (getProductDetails, validateCategory, storeCategoryResult, etc.)
    
    public function getSessionId(): string {
        return $this->session_id;
    }
    
    private function getApiKey(): ?string {
        // Get from environment or config
        return $_ENV['OPENAI_API_KEY'] ?? null;
    }
}

// CLI interface
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    try {
        require_once __DIR__ . '/../../functions/mysql.php';
        
        if (!connectToSQL()) {
            die("❌ Cannot connect to database\n");
        }
        
        global $con;
        
        echo "🤖 GPT AUTO-CATEGORIZATION ENGINE\n";
        echo "==================================\n\n";
        
        $categorizer = new GPTAutoCategorization($con);
        
        $action = $_GET['action'] ?? 'categorize_all';
        
        switch ($action) {
            case 'categorize_all':
                echo "🔄 Starting batch categorization...\n\n";
                $results = $categorizer->categorizeAllProducts([
                    'simulate' => isset($_GET['simulate'])
                ]);
                
                if ($results['success']) {
                    echo "✅ Batch categorization completed!\n";
                    echo "Processed: {$results['processed']} products\n";
                    echo "Categorized: {$results['categorized']} products\n";
                    echo "Success Rate: {$results['success_rate']}%\n";
                    echo "Execution Time: {$results['execution_time']}s\n\n";
                    
                    if (!empty($results['categories_assigned'])) {
                        echo "Categories assigned:\n";
                        foreach ($results['categories_assigned'] as $category => $count) {
                            echo "  - {$category}: {$count} products\n";
                        }
                    }
                } else {
                    echo "❌ Batch categorization failed: {$results['error']}\n";
                }
                break;
                
            case 'stats':
                echo "📊 Getting categorization statistics...\n\n";
                $stats = $categorizer->getCategorization Statistics();
                
                if ($stats['success']) {
                    $overall = $stats['statistics']['overall'];
                    echo "📈 Overall Statistics:\n";
                    echo "  Total Products: {$overall['total_products']}\n";
                    echo "  Categorized: {$overall['categorized']} ({$overall['categorization_rate']}%)\n";
                    echo "  High Confidence: {$overall['high_confidence']}\n";
                    echo "  Average Confidence: {$overall['avg_confidence']}%\n\n";
                    
                    if (!empty($stats['statistics']['category_distribution'])) {
                        echo "📋 Category Distribution:\n";
                        foreach ($stats['statistics']['category_distribution'] as $cat) {
                            echo "  - {$cat['category']}: {$cat['product_count']} products (avg confidence: {$cat['avg_confidence']}%)\n";
                        }
                    }
                } else {
                    echo "❌ Failed to get statistics: {$stats['error']}\n";
                }
                break;
                
            default:
                echo "Available actions:\n";
                echo "  - categorize_all: Categorize all uncategorized products\n";
                echo "  - stats: Show categorization statistics\n";
                break;
        }
        
    } catch (Exception $e) {
        echo "❌ GPT categorization failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
