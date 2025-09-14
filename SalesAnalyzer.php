<?php
/**
 * SalesAnalyzer.php - Advanced Sales Data Analysis for Transfer Optimization
 * 
 * Analyzes 30/60-day sales data to determine optimal transfer quantities
 * and validate transfer decisions against real sales patterns.
 */

class SalesAnalyzer 
{
    private $db;
    private $cache = [];
    
    public function __construct($database)
    {
        $this->db = $database;
    }
    
    /**
     * Get comprehensive sales analysis for products
     */
    public function analyzeSalesData($outlet_id, $product_ids = [], $days = 30)
    {
        $cache_key = "sales_analysis_{$outlet_id}_" . md5(implode(',', $product_ids)) . "_{$days}";
        
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $product_filter = '';
        $params = [$outlet_id];
        $param_types = 's';
        
        if (!empty($product_ids)) {
            $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
            $product_filter = "AND vli.product_id IN ($placeholders)";
            $params = array_merge($params, $product_ids);
            $param_types .= str_repeat('s', count($product_ids));
        }
        
        $query = "
            SELECT 
                vli.product_id,
                vp.name as product_name,
                vp.type as product_type,
                COUNT(DISTINCT vs.id) as transaction_count,
                SUM(vli.quantity) as total_quantity_sold,
                AVG(vli.quantity) as avg_quantity_per_transaction,
                MIN(vs.created_at) as first_sale,
                MAX(vs.created_at) as last_sale,
                DATEDIFF(NOW(), MAX(vs.created_at)) as days_since_last_sale,
                SUM(vli.quantity) / $days as daily_velocity,
                COUNT(DISTINCT DATE(vs.created_at)) as active_days,
                SUM(vli.price_total) as total_revenue,
                AVG(vli.price_total) as avg_price,
                -- Current stock
                COALESCE(vi.current_amount, 0) as current_stock,
                -- Sales trend (last 7 vs previous 7 days)
                (
                    SELECT SUM(vli2.quantity) 
                    FROM vend_line_items vli2 
                    INNER JOIN vend_sales vs2 ON vli2.sale_id = vs2.id 
                    WHERE vs2.outlet_id = ? 
                        AND vli2.product_id = vli.product_id
                        AND vs2.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        AND vs2.deleted_at IS NULL
                ) as sales_last_7d,
                (
                    SELECT SUM(vli3.quantity) 
                    FROM vend_line_items vli3 
                    INNER JOIN vend_sales vs3 ON vli3.sale_id = vs3.id 
                    WHERE vs3.outlet_id = ? 
                        AND vli3.product_id = vli.product_id
                        AND vs3.created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                        AND vs3.created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
                        AND vs3.deleted_at IS NULL
                ) as sales_prev_7d
            FROM vend_line_items vli
            INNER JOIN vend_sales vs ON vli.sale_id = vs.id
            INNER JOIN vend_products vp ON vli.product_id = vp.id
            LEFT JOIN vend_inventory vi ON (vi.product_id = vli.product_id AND vi.outlet_id = vs.outlet_id)
            WHERE vs.outlet_id = ?
                AND vs.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
                AND vs.deleted_at IS NULL
                $product_filter
            GROUP BY vli.product_id, vp.name, vp.type, vi.current_amount
            ORDER BY total_quantity_sold DESC
        ";
        
        // Add outlet_id twice more for subqueries
        $all_params = array_merge([$outlet_id, $outlet_id], $params);
        $all_param_types = 'ss' . $param_types;
        
        $stmt = $this->db->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare sales analysis query: " . $this->db->error);
        }
        
        $stmt->bind_param($all_param_types, ...$all_params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $analysis = [];
        while ($row = $result->fetch_assoc()) {
            // Calculate additional metrics
            $row['stockout_risk'] = $this->calculateStockoutRisk($row);
            $row['demand_category'] = $this->categorizeDemand($row, $days);
            $row['optimal_transfer_qty'] = $this->calculateOptimalTransferQuantity($row, $days);
            $row['sales_trend'] = $this->calculateSalesTrend($row);
            
            $analysis[$row['product_id']] = $row;
        }
        
        $this->cache[$cache_key] = $analysis;
        return $analysis;
    }
    
    /**
     * Calculate stockout risk based on sales velocity and current stock
     */
    private function calculateStockoutRisk($product_data)
    {
        $current_stock = floatval($product_data['current_stock']);
        $daily_velocity = floatval($product_data['daily_velocity']);
        
        if ($daily_velocity <= 0) {
            return 0; // No sales, no risk
        }
        
        $days_of_stock = $current_stock / $daily_velocity;
        
        if ($days_of_stock < 3) {
            return 100; // Critical
        } elseif ($days_of_stock < 7) {
            return 80; // High
        } elseif ($days_of_stock < 14) {
            return 60; // Medium
        } elseif ($days_of_stock < 30) {
            return 30; // Low
        } else {
            return 10; // Very low
        }
    }
    
    /**
     * Categorize demand pattern
     */
    private function categorizeDemand($product_data, $period_days)
    {
        $daily_velocity = floatval($product_data['daily_velocity']);
        $transaction_count = intval($product_data['transaction_count']);
        $active_days = intval($product_data['active_days']);
        
        $sales_frequency = $active_days / $period_days;
        
        if ($daily_velocity >= 1.0 && $sales_frequency >= 0.5) {
            return 'HIGH_DEMAND';
        } elseif ($daily_velocity >= 0.3 && $sales_frequency >= 0.3) {
            return 'MEDIUM_DEMAND';
        } elseif ($daily_velocity >= 0.1 && $sales_frequency >= 0.1) {
            return 'LOW_DEMAND';
        } elseif ($transaction_count > 0) {
            return 'SPORADIC';
        } else {
            return 'NO_SALES';
        }
    }
    
    /**
     * Calculate optimal transfer quantity based on sales data
     */
    private function calculateOptimalTransferQuantity($product_data, $period_days)
    {
        $daily_velocity = floatval($product_data['daily_velocity']);
        $current_stock = floatval($product_data['current_stock']);
        $avg_transaction = floatval($product_data['avg_quantity_per_transaction']);
        
        if ($daily_velocity <= 0) {
            return 0; // No sales history
        }
        
        // Target: 21 days of stock (3 weeks)
        $target_days = 21;
        $target_stock = $daily_velocity * $target_days;
        
        // Consider minimum viable quantities
        $min_quantity = max(1, ceil($avg_transaction * 2));
        
        $needed_quantity = max(0, $target_stock - $current_stock);
        
        return max($min_quantity, ceil($needed_quantity));
    }
    
    /**
     * Calculate sales trend (growing, stable, declining)
     */
    private function calculateSalesTrend($product_data)
    {
        $last_7d = floatval($product_data['sales_last_7d'] ?? 0);
        $prev_7d = floatval($product_data['sales_prev_7d'] ?? 0);
        
        if ($prev_7d == 0) {
            return $last_7d > 0 ? 'NEW' : 'NO_DATA';
        }
        
        $change_ratio = ($last_7d - $prev_7d) / $prev_7d;
        
        if ($change_ratio > 0.2) {
            return 'GROWING';
        } elseif ($change_ratio < -0.2) {
            return 'DECLINING';
        } else {
            return 'STABLE';
        }
    }
    
    /**
     * Compare transfer plan against sales data
     */
    public function validateTransferPlan($outlet_id, $transfer_plan)
    {
        echo "📊 Validating Transfer Plan Against Sales Data\n";
        
        $product_ids = array_column($transfer_plan, 'product_id');
        $sales_analysis = $this->analyzeSalesData($outlet_id, $product_ids, 30);
        
        $validation_results = [];
        $total_score = 0;
        $total_products = 0;
        
        foreach ($transfer_plan as $transfer_item) {
            $product_id = $transfer_item['product_id'];
            $transfer_qty = intval($transfer_item['quantity']);
            
            if (!isset($sales_analysis[$product_id])) {
                // No sales data - neutral score
                $validation_results[] = [
                    'product_id' => $product_id,
                    'transfer_qty' => $transfer_qty,
                    'score' => 5,
                    'reason' => 'No sales data available',
                    'recommendation' => 'NEUTRAL'
                ];
                continue;
            }
            
            $sales_data = $sales_analysis[$product_id];
            $optimal_qty = $sales_data['optimal_transfer_qty'];
            $score = $this->scoreTransferDecision($transfer_qty, $optimal_qty, $sales_data);
            
            $validation_results[] = [
                'product_id' => $product_id,
                'product_name' => $sales_data['product_name'],
                'transfer_qty' => $transfer_qty,
                'optimal_qty' => $optimal_qty,
                'current_stock' => $sales_data['current_stock'],
                'daily_velocity' => round($sales_data['daily_velocity'], 2),
                'demand_category' => $sales_data['demand_category'],
                'stockout_risk' => $sales_data['stockout_risk'],
                'sales_trend' => $sales_data['sales_trend'],
                'score' => $score,
                'recommendation' => $this->getRecommendation($score, $sales_data)
            ];
            
            $total_score += $score;
            $total_products++;
        }
        
        $avg_score = $total_products > 0 ? $total_score / $total_products : 0;
        
        // Display results
        echo "   Products analyzed: $total_products\n";
        echo "   Average validation score: " . round($avg_score, 2) . "/10\n";
        
        // Show top recommendations
        $high_priority = array_filter($validation_results, function($item) {
            return $item['score'] >= 8 || ($item['stockout_risk'] ?? 0) >= 80;
        });
        
        if (!empty($high_priority)) {
            echo "   🎯 High priority transfers: " . count($high_priority) . "\n";
        }
        
        $low_score = array_filter($validation_results, function($item) {
            return $item['score'] <= 4;
        });
        
        if (!empty($low_score)) {
            echo "   ⚠️  Questionable transfers: " . count($low_score) . "\n";
        }
        
        return [
            'average_score' => $avg_score,
            'total_products' => $total_products,
            'validation_results' => $validation_results,
            'high_priority_count' => count($high_priority),
            'questionable_count' => count($low_score)
        ];
    }
    
    /**
     * Score transfer decision (0-10)
     */
    private function scoreTransferDecision($transfer_qty, $optimal_qty, $sales_data)
    {
        if ($optimal_qty <= 0) {
            return $transfer_qty == 0 ? 8 : 3; // Should not transfer if no demand
        }
        
        $ratio = $transfer_qty / $optimal_qty;
        
        // Perfect match
        if ($ratio >= 0.8 && $ratio <= 1.2) {
            return 10;
        }
        
        // Close match
        if ($ratio >= 0.6 && $ratio <= 1.5) {
            return 8;
        }
        
        // Acceptable
        if ($ratio >= 0.4 && $ratio <= 2.0) {
            return 6;
        }
        
        // Poor match
        if ($ratio >= 0.2 && $ratio <= 3.0) {
            return 4;
        }
        
        // Very poor match
        return 2;
    }
    
    /**
     * Get recommendation text
     */
    private function getRecommendation($score, $sales_data)
    {
        if ($score >= 8) {
            return "EXCELLENT - Matches sales pattern well";
        } elseif ($score >= 6) {
            return "GOOD - Reasonable quantity";
        } elseif ($score >= 4) {
            return "FAIR - Consider adjusting quantity";
        } else {
            return "POOR - Quantity doesn't match demand pattern";
        }
    }
    
    /**
     * Get outlet sales summary
     */
    public function getOutletSalesSummary($outlet_id, $days = 30)
    {
        $query = "
            SELECT 
                COUNT(DISTINCT vs.id) as total_transactions,
                COUNT(DISTINCT vli.product_id) as unique_products_sold,
                SUM(vli.quantity) as total_items_sold,
                SUM(vli.price_total) as total_revenue,
                AVG(vli.price_total) as avg_item_value,
                COUNT(DISTINCT DATE(vs.created_at)) as active_days
            FROM vend_line_items vli
            INNER JOIN vend_sales vs ON vli.sale_id = vs.id
            WHERE vs.outlet_id = ?
                AND vs.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND vs.deleted_at IS NULL
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('si', $outlet_id, $days);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        // Add calculated metrics
        $result['daily_transaction_avg'] = $result['active_days'] > 0 ? 
            round($result['total_transactions'] / $result['active_days'], 2) : 0;
        $result['daily_revenue_avg'] = $result['active_days'] > 0 ? 
            round($result['total_revenue'] / $result['active_days'], 2) : 0;
        
        return $result;
    }
}
?>
