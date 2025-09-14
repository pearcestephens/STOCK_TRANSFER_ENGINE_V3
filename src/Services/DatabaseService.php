<?php
namespace NewTransferV3\Services;

use PDO;
use Exception;

/**
 * Database Service for NewTransferV3
 * 
 * Centralized database operations with transaction support
 */
class DatabaseService
{
    private PDO $pdo;
    private bool $inTransaction = false;
    
    public function __construct(array $config)
    {
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
        
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    
    /**
     * Begin database transaction
     */
    public function beginTransaction(): void
    {
        if (!$this->inTransaction) {
            $this->pdo->beginTransaction();
            $this->inTransaction = true;
        }
    }
    
    /**
     * Commit database transaction
     */
    public function commit(): void
    {
        if ($this->inTransaction) {
            $this->pdo->commit();
            $this->inTransaction = false;
        }
    }
    
    /**
     * Rollback database transaction
     */
    public function rollback(): void
    {
        if ($this->inTransaction) {
            $this->pdo->rollBack();
            $this->inTransaction = false;
        }
    }
    
    /**
     * Get all active outlets
     */
    public function getOutlets(): array
    {
        $sql = "SELECT outlet_id, outlet_name, outlet_prefix, 
                CASE WHEN outlet_prefix IN ('WH', 'WAREHOUSE') THEN 1 ELSE 0 END as is_warehouse,
                outlet_timezone, outlet_email
                FROM vend_outlets 
                WHERE outlet_id IS NOT NULL 
                AND outlet_name != '' 
                ORDER BY outlet_name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get active products with optional limit
     */
    public function getActiveProducts(int $limit = 0): array
    {
        $sql = "SELECT DISTINCT p.product_id, p.product_name, p.product_sku
                FROM vend_products p
                INNER JOIN vend_inventory i ON p.product_id = i.product_id
                WHERE p.deleted_at IS NULL
                AND i.current_amount > 0";
        
        if ($limit > 0) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get current inventory for outlet
     */
    public function getOutletInventory(string $outletId): array
    {
        $sql = "SELECT product_id, current_amount as stock_level
                FROM vend_inventory
                WHERE outlet_id = ? 
                AND current_amount > 0";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$outletId]);
        
        $inventory = [];
        while ($row = $stmt->fetch()) {
            $inventory[$row['product_id']] = (int)$row['stock_level'];
        }
        
        return $inventory;
    }
    
    /**
     * Get sales velocity for product at outlet
     */
    public function getSalesVelocity(string $outletId, string $productId, int $days = 30): float
    {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total_sold
                FROM vend_sales_products vsp
                INNER JOIN vend_sales vs ON vsp.sale_id = vs.sale_id
                WHERE vs.outlet_id = ?
                AND vsp.product_id = ?
                AND vs.sale_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND vs.deleted_at IS NULL";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$outletId, $productId, $days]);
        
        $result = $stmt->fetch();
        return (float)($result['total_sold'] ?? 0);
    }
    
    /**
     * Create transfer header
     */
    public function createTransferHeader(array $data): string
    {
        $sql = "INSERT INTO stock_transfers (
                    outlet_from, outlet_to, transfer_date, transfer_notes, 
                    transfer_created_by_user, date_created, status, micro_status
                ) VALUES (?, ?, ?, ?, ?, NOW(), 'pending', 'created')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['outlet_from'],
            $data['outlet_to'],
            $data['transfer_date'],
            $data['transfer_notes'] ?? '',
            $data['created_by'] ?? 'system'
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Create transfer line
     */
    public function createTransferLine(array $data): string
    {
        $sql = "INSERT INTO stock_products_to_transfer (
                    transfer_id, product_id, qty_to_transfer, min_qty_to_remain,
                    demand_forecast, days_of_stock, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['transfer_id'],
            $data['product_id'],
            $data['qty_to_transfer'],
            $data['min_qty_to_remain'] ?? 0,
            $data['demand_forecast'] ?? 0,
            $data['days_of_stock'] ?? 0,
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Get transfer by ID
     */
    public function getTransfer(string $transferId): ?array
    {
        $sql = "SELECT st.*, 
                       fo.outlet_name as from_outlet_name,
                       to_out.outlet_name as to_outlet_name
                FROM stock_transfers st
                LEFT JOIN vend_outlets fo ON st.outlet_from = fo.outlet_id
                LEFT JOIN vend_outlets to_out ON st.outlet_to = to_out.outlet_id
                WHERE st.transfer_id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$transferId]);
        
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Get transfer lines
     */
    public function getTransferLines(string $transferId): array
    {
        $sql = "SELECT spt.*, p.product_name, p.product_sku
                FROM stock_products_to_transfer spt
                LEFT JOIN vend_products p ON spt.product_id = p.product_id
                WHERE spt.transfer_id = ?
                ORDER BY spt.primary_key";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$transferId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Execute custom query
     */
    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Execute update/insert query
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}
