# NewTransferV3 Database Schema

## Overview

The NewTransferV3 system operates on a MariaDB 10.5 database with multiple interconnected tables for inventory management, transfer operations, and AI-driven analytics.

## Core Tables

### stock_transfers
Primary transfer header table containing transfer metadata.

```sql
CREATE TABLE stock_transfers (
    transfer_id INT PRIMARY KEY AUTO_INCREMENT,
    date_created DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','in_progress','completed','failed') DEFAULT 'pending',
    micro_status VARCHAR(255),
    receive_confidence DECIMAL(5,2),
    receive_quality_notes TEXT,
    transfer_created_by_user INT,
    transfer_completed_by_user INT,
    transfer_completed DATETIME,
    outlet_from INT NOT NULL,
    outlet_to INT NOT NULL,
    total_products INT DEFAULT 0,
    total_value DECIMAL(12,2) DEFAULT 0.00,
    simulation_mode TINYINT(1) DEFAULT 0,
    neural_session_id VARCHAR(64),
    
    INDEX idx_outlets (outlet_from, outlet_to),
    INDEX idx_status (status, date_created),
    INDEX idx_neural (neural_session_id)
);
```

### stock_products_to_transfer
Transfer line items with AI-enhanced scoring and analytics.

```sql
CREATE TABLE stock_products_to_transfer (
    primary_key INT PRIMARY KEY AUTO_INCREMENT,
    transfer_id INT NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    qty_to_transfer INT NOT NULL DEFAULT 0,
    min_qty_to_remain INT DEFAULT 0,
    qty_transferred_at_source INT DEFAULT 0,
    qty_counted_at_destination INT DEFAULT 0,
    new_total_qty_in_stock INT DEFAULT 0,
    new_total_at_destination INT DEFAULT 0,
    unexpected_product_added TINYINT(1) DEFAULT 0,
    staff_added_product TINYINT(1) DEFAULT 0,
    validation_flags TEXT,
    validation_notes TEXT,
    deleted_at DATETIME NULL,
    
    -- AI/ML Enhancement Fields
    demand_forecast DECIMAL(10,2) DEFAULT 0.00,
    stockout_risk DECIMAL(5,2) DEFAULT 0.00,
    overstock_risk DECIMAL(5,2) DEFAULT 0.00,
    optimal_qty INT DEFAULT 0,
    sales_velocity DECIMAL(10,2) DEFAULT 0.00,
    abc_classification ENUM('A','B','C','D') DEFAULT 'D',
    profit_impact DECIMAL(12,2) DEFAULT 0.00,
    ml_priority_score DECIMAL(5,2) DEFAULT 0.00,
    last_sale_date DATE,
    days_of_stock DECIMAL(8,2) DEFAULT 0.00,
    
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(transfer_id),
    UNIQUE KEY unique_transfer_product (transfer_id, product_id),
    INDEX idx_product (product_id),
    INDEX idx_abc_class (abc_classification),
    INDEX idx_priority (ml_priority_score DESC),
    INDEX idx_risk (stockout_risk DESC, overstock_risk ASC)
);
```

### vend_inventory
Real-time inventory levels synced with Vend POS system.

```sql
CREATE TABLE vend_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    outlet_id INT NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    inventory_level DECIMAL(10,2) DEFAULT 0.00,
    current_amount DECIMAL(10,2) DEFAULT 0.00,
    version INT DEFAULT 1,
    reorder_point INT DEFAULT 0,
    reorder_amount INT DEFAULT 0,
    deleted_at DATETIME NULL,
    average_cost DECIMAL(16,6) DEFAULT 0.000000,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_outlet_product (outlet_id, product_id),
    INDEX idx_inventory_levels (inventory_level),
    INDEX idx_reorder (reorder_point, current_amount),
    INDEX idx_outlet (outlet_id)
);
```

### vend_outlets
Store location and configuration data.

```sql
CREATE TABLE vend_outlets (
    outlet_id INT PRIMARY KEY,
    outlet_name VARCHAR(255) NOT NULL,
    outlet_prefix VARCHAR(10),
    outlet_type ENUM('store','warehouse','hub') DEFAULT 'store',
    is_active TINYINT(1) DEFAULT 1,
    buffer_days INT DEFAULT 14,
    safety_factor DECIMAL(4,2) DEFAULT 1.20,
    max_transfer_value DECIMAL(12,2),
    deleted_at DATETIME NULL,
    
    INDEX idx_active (is_active, outlet_type),
    INDEX idx_name (outlet_name)
);
```

## Supporting Tables

### neural_brain_sessions
AI session tracking and decision logging.

```sql
CREATE TABLE neural_brain_sessions (
    session_id VARCHAR(64) PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    transfer_id INT,
    outlet_from INT,
    outlet_to INT,
    mode ENUM('all_stores','hub_to_stores','specific_transfer'),
    decisions_json JSON,
    performance_metrics JSON,
    completed_at TIMESTAMP NULL,
    
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(transfer_id),
    INDEX idx_transfer (transfer_id),
    INDEX idx_created (created_at)
);
```

### transfer_decision_log
Detailed decision audit trail.

```sql
CREATE TABLE transfer_decision_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    transfer_id INT NOT NULL,
    product_id VARCHAR(255) NOT NULL,
    decision_type ENUM('include','exclude','adjust_qty','force_include'),
    decision_reason TEXT,
    ai_confidence DECIMAL(5,2),
    override_reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(transfer_id),
    INDEX idx_transfer_product (transfer_id, product_id),
    INDEX idx_decision_type (decision_type)
);
```

## Key Relationships

```
vend_outlets (1) ←→ (N) stock_transfers
stock_transfers (1) ←→ (N) stock_products_to_transfer
vend_outlets (1) ←→ (N) vend_inventory
stock_transfers (1) ←→ (1) neural_brain_sessions
stock_transfers (1) ←→ (N) transfer_decision_log
```

## Performance Indexes

### Critical Query Patterns

1. **Active transfers by outlet**
```sql
INDEX idx_active_transfers ON stock_transfers (status, outlet_from, outlet_to, date_created);
```

2. **Inventory lookup by store and product**
```sql
INDEX idx_store_inventory ON vend_inventory (outlet_id, product_id, current_amount);
```

3. **AI scoring and classification**
```sql
INDEX idx_ml_scoring ON stock_products_to_transfer (abc_classification, ml_priority_score DESC, stockout_risk DESC);
```

4. **Transfer performance analytics**
```sql
INDEX idx_transfer_analytics ON stock_transfers (date_created, status, outlet_from, total_value);
```

## Data Constraints

### Business Rules
- `stock_transfers.outlet_from` ≠ `stock_transfers.outlet_to`
- `stock_products_to_transfer.qty_to_transfer` > 0
- `vend_inventory.current_amount` ≥ 0
- `abc_classification` ∈ {'A','B','C','D'}
- `stockout_risk`, `overstock_risk` ∈ [0.00, 100.00]

### Referential Integrity
- All outlet references must exist in `vend_outlets`
- Transfer lines must have valid transfer headers
- Neural sessions link to specific transfers
- Decision logs reference existing transfers and products

## Backup and Maintenance

### Daily Operations
- Automated backups at 02:00 NZST
- Index optimization on `stock_products_to_transfer`
- Cleanup of completed transfers older than 90 days
- Neural session archival after 30 days

### Performance Monitoring
- Query execution time tracking
- Index usage statistics
- Table growth monitoring
- Transaction lock analysis

## Migration Notes

### Schema Evolution
- Version tracking in `schema_migrations` table
- Backwards-compatible column additions
- Safe index creation with `ALGORITHM=INPLACE`
- Data migration scripts for ML field population

---

*Generated: September 14, 2025*  
*Database: MariaDB 10.5 (MySQL-compatible)*  
*Environment: Production (jcepnzzkmj)*
