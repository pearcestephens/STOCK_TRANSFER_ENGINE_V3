<?php
/**
 * REAL GPT INTEGRATION ENGINE
 * Connects to actual GPT Actions API for genuine business intelligence
 * No fake demo responses - REAL AI analysis of your transfer data
 */

class RealGPTAnalysisEngine {
    private $pdo;
    private $gpt_api_endpoint;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
        // Use your actual GPT Actions API endpoint
        $this->gpt_api_endpoint = 'https://staff.vapeshed.co.nz/gpt_actions.php';
    }
    
    /**
     * Get REAL product categorization analysis using GPT
     */
    public function analyzeProductCategorization() {
        // Get products without proper categorization
        $stmt = $this->pdo->query("
            SELECT 
                p.product_id,
                p.product_name,
                p.product_description,
                p.brand_name,
                p.category_name,
                CASE 
                    WHEN p.category_name IS NULL OR p.category_name = '' THEN 1
                    WHEN p.brand_name IS NULL OR p.brand_name = '' THEN 1
                    ELSE 0
                END as needs_categorization
            FROM vend_products p
            WHERE p.deleted_at IS NULL
            AND (p.category_name IS NULL OR p.category_name = '' OR p.brand_name IS NULL OR p.brand_name = '')
            LIMIT 100
        ");
        
        $uncategorized = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($uncategorized)) {
            return [
                'success' => true,
                'message' => 'All products are properly categorized',
                'uncategorized_count' => 0,
                'recommendations' => []
            ];
        }
        
        // Prepare GPT prompt with actual product data
        $gpt_prompt = $this->buildCategorizationPrompt($uncategorized);
        
        // Call REAL GPT API
        $gpt_response = $this->callGPTAPI('product_categorization', $gpt_prompt);
        
        return [
            'success' => true,
            'uncategorized_count' => count($uncategorized),
            'products' => $uncategorized,
            'gpt_analysis' => $gpt_response,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Analyze pack sizes and compliance using GPT
     */
    public function analyzePackSizes() {
        // Get products with potential pack size issues
        $stmt = $this->pdo->query("
            SELECT 
                p.product_id,
                p.product_name,
                p.product_description,
                vi.inventory_level,
                vi.reorder_point,
                COUNT(st.transfer_id) as transfer_frequency,
                AVG(spt.qty_to_transfer) as avg_transfer_qty
            FROM vend_products p
            LEFT JOIN vend_inventory vi ON p.product_id = vi.product_id
            LEFT JOIN stock_products_to_transfer spt ON p.product_id = spt.product_id
            LEFT JOIN stock_transfers st ON spt.transfer_id = st.transfer_id
            WHERE p.deleted_at IS NULL
            AND vi.deleted_at IS NULL
            GROUP BY p.product_id, p.product_name, p.product_description, vi.inventory_level, vi.reorder_point
            HAVING transfer_frequency > 5 OR avg_transfer_qty < 5
            ORDER BY transfer_frequency DESC
            LIMIT 50
        ");
        
        $pack_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $gpt_prompt = $this->buildPackSizePrompt($pack_data);
        $gpt_response = $this->callGPTAPI('pack_size_analysis', $gpt_prompt);
        
        return [
            'success' => true,
            'products_analyzed' => count($pack_data),
            'pack_data' => $pack_data,
            'gpt_recommendations' => $gpt_response,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Analyze missing brands and suggest GPT-powered solutions
     */
    public function analyzeMissingBrands() {
        // Find the brand that was "missed" recently
        $stmt = $this->pdo->query("
            SELECT 
                p.brand_name,
                COUNT(*) as product_count,
                SUM(vi.inventory_level) as total_stock,
                MAX(st.date_created) as last_transfer_date,
                COUNT(DISTINCT vi.outlet_id) as outlets_with_stock,
                AVG(vi.inventory_level) as avg_stock_per_outlet
            FROM vend_products p
            LEFT JOIN vend_inventory vi ON p.product_id = vi.product_id
            LEFT JOIN stock_products_to_transfer spt ON p.product_id = spt.product_id
            LEFT JOIN stock_transfers st ON spt.transfer_id = st.transfer_id
            WHERE p.deleted_at IS NULL
            AND p.brand_name IS NOT NULL
            AND p.brand_name != ''
            GROUP BY p.brand_name
            HAVING last_transfer_date IS NULL OR last_transfer_date < DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY total_stock DESC
        ");
        
        $brand_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $gpt_prompt = $this->buildBrandAnalysisPrompt($brand_analysis);
        $gpt_response = $this->callGPTAPI('brand_analysis', $gpt_prompt);
        
        return [
            'success' => true,
            'brands_analyzed' => count($brand_analysis),
            'potentially_missed_brands' => $brand_analysis,
            'gpt_insights' => $gpt_response,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * REAL GPT API call to your actual system
     */
    private function callGPTAPI($action, $prompt) {
        $postData = [
            'action' => $action,
            'prompt' => $prompt,
            'context' => 'transfer_analysis',
            'system_role' => 'business_intelligence_analyst'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->gpt_api_endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: CIS-Transfer-Engine/1.0'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $decoded = json_decode($response, true);
            return $decoded ?: ['error' => 'Invalid JSON response', 'raw_response' => $response];
        }
        
        return [
            'error' => 'GPT API call failed',
            'http_code' => $http_code,
            'response' => $response
        ];
    }
    
    /**
     * Build intelligent categorization prompt with real product data
     */
    private function buildCategorizationPrompt($products) {
        $prompt = "BUSINESS INTELLIGENCE TASK: Product Categorization Analysis\n\n";
        $prompt .= "You are analyzing " . count($products) . " uncategorized products from The Vape Shed retail network.\n";
        $prompt .= "Your task: Suggest proper categories and brands for these products based on their names and descriptions.\n\n";
        $prompt .= "PRODUCTS TO ANALYZE:\n";
        
        foreach (array_slice($products, 0, 20) as $product) { // Limit to avoid token limits
            $prompt .= "- ID: {$product['product_id']} | Name: '{$product['product_name']}' | Description: '{$product['product_description']}'\n";
        }
        
        $prompt .= "\nPlease provide:\n";
        $prompt .= "1. Suggested category for each product\n";
        $prompt .= "2. Suggested brand extraction from product names\n";
        $prompt .= "3. Priority order (which products need immediate categorization)\n";
        $prompt .= "4. Any patterns you notice in the uncategorized products\n";
        $prompt .= "\nRespond in JSON format with actionable recommendations.";
        
        return $prompt;
    }
    
    /**
     * Build pack size analysis prompt with transfer data
     */
    private function buildPackSizePrompt($products) {
        $prompt = "BUSINESS INTELLIGENCE TASK: Pack Size Optimization Analysis\n\n";
        $prompt .= "Analyzing transfer patterns for " . count($products) . " products to optimize pack sizes.\n";
        $prompt .= "Goal: Reduce transfer frequency by suggesting better pack sizes.\n\n";
        $prompt .= "PRODUCT TRANSFER DATA:\n";
        
        foreach (array_slice($products, 0, 15) as $product) {
            $prompt .= "- '{$product['product_name']}': Avg Transfer: {$product['avg_transfer_qty']}, Frequency: {$product['transfer_frequency']}, Stock: {$product['inventory_level']}\n";
        }
        
        $prompt .= "\nAnalyze and suggest:\n";
        $prompt .= "1. Optimal pack sizes to reduce transfer frequency\n";
        $prompt .= "2. Products that should be bundled differently\n";
        $prompt .= "3. Minimum order quantities that make business sense\n";
        $prompt .= "4. Cost-benefit analysis of larger pack sizes\n";
        $prompt .= "\nProvide JSON response with specific pack size recommendations.";
        
        return $prompt;
    }
    
    /**
     * Build brand analysis prompt for missed brands
     */
    private function buildBrandAnalysisPrompt($brands) {
        $prompt = "BUSINESS INTELLIGENCE TASK: Brand Transfer Gap Analysis\n\n";
        $prompt .= "Investigating brands that may have been missed in recent transfer runs.\n";
        $prompt .= "Someone reported a brand was missed - need to identify the issue.\n\n";
        $prompt .= "BRAND TRANSFER DATA:\n";
        
        foreach (array_slice($brands, 0, 20) as $brand) {
            $prompt .= "- '{$brand['brand_name']}': {$brand['product_count']} products, {$brand['total_stock']} total stock, Last transfer: {$brand['last_transfer_date']}\n";
        }
        
        $prompt .= "\nAnalyze and identify:\n";
        $prompt .= "1. Which brand was most likely 'missed' and why\n";
        $prompt .= "2. Root cause of the missing brand issue\n";
        $prompt .= "3. System improvements to prevent future misses\n";
        $prompt .= "4. Immediate actions needed to fix the gap\n";
        $prompt .= "\nProvide JSON response with specific brand and fix recommendations.";
        
        return $prompt;
    }
}
?>
