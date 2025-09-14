# NewTransferV3 Technical Specifications & Database Schema

## 🗄️ **COMPLETE DATABASE SCHEMA REFERENCE**

### **Transfer Control System Tables**

#### `vend_products_outlet_transfer_settings` (Sparse Override Table)
**Purpose:** Store-specific product transfer overrides (rarely used)
**Usage Pattern:** ~0.1% of products have entries (hand-entered exceptions only)
```sql
CREATE TABLE vend_products_outlet_transfer_settings (
    product_id varchar(45) NOT NULL,
    outlet_id varchar(45) NOT NULL,
    override_default_product_settings int(11) DEFAULT NULL,
    enable_product_transfer int(11) DEFAULT NULL,
    enable_qty_transfer_limit int(11) DEFAULT NULL,
    enable_transfer_threshold int(11) DEFAULT NULL,
    maximum_qty_to_send int(11) DEFAULT NULL,
    only_send_when_below int(11) DEFAULT NULL,
    send_in_multiple_qty int(11) DEFAULT NULL,
    minimum_qty_left_at_warehouse int(11) DEFAULT NULL,
    PRIMARY KEY (product_id, outlet_id),
    INDEX idx_product_outlet (product_id, outlet_id)
);
```

#### `vend_products_default_transfer_settings` (Product Defaults)
**Purpose:** Product-specific transfer rules (moderately used)
**Usage Pattern:** ~5-10% of products have custom settings
```sql
CREATE TABLE vend_products_default_transfer_settings (
    product_id varchar(45) NOT NULL,
    enable_product_transfer int(11) DEFAULT NULL,
    enable_qty_transfer_limit int(11) DEFAULT NULL,
    enable_transfer_threshold int(11) DEFAULT NULL,
    maximum_qty_to_send int(11) DEFAULT NULL,
    only_send_when_below int(11) DEFAULT NULL,
    send_in_multiple_qty int(11) DEFAULT NULL,
    minimum_qty_left_at_warehouse int(11) DEFAULT NULL,
    PRIMARY KEY (product_id),
    INDEX idx_product_enabled (product_id, enable_product_transfer)
);
```

#### `configuration` (System Defaults & Settings)
**Purpose:** Global system configuration with intelligent defaults
**Critical Settings:**
```sql
-- Transfer system defaults (intelligent unlimited)
stock_transfers_enable_product_transfer_default = 1         -- Enable by default
stock_transfers_enable_qty_transfer_limit_default = 0       -- No arbitrary limits
stock_transfers_enable_transfer_threshold_default = 0       -- Algorithm decides
stock_transfers_maximum_qty_to_send_default = NULL          -- Algorithm optimizes
stock_transfers_only_send_when_below_default = NULL         -- Algorithm decides
stock_transfers_send_in_multiple_qty_default = 1            -- Individual unless pack rules

-- Pack optimization settings
default_pack_size_individual = 1
default_outer_enforcement_mode = 'suggest'                  -- suggest|enforce
default_rounding_mode = 'round'                            -- floor|ceil|round

-- Weight & shipping optimization
shipping_weight_buffer_percent = 10                        -- 10% weight buffer
max_shipping_weight_kg = 20                               -- Container limit
value_density_threshold = 0.50                            -- Min value per gram
```

### **Pack & Logistics Intelligence Tables**

#### `pack_rules` (Hierarchical Pack Rules)
**Purpose:** Product and category-specific pack size requirements
**Business Logic:** Cascade from product → category → defaults
```sql
CREATE TABLE pack_rules (
    scope enum('product','category') NOT NULL,
    scope_id varchar(100) NOT NULL,
    pack_size int(11) NOT NULL DEFAULT 1,
    outer_multiple int(11) DEFAULT NULL,
    enforce_outer tinyint(1) DEFAULT 0,
    rounding_mode enum('floor','ceil','round') DEFAULT 'round',
    source enum('human','vendor','gpt','inferred') DEFAULT 'inferred',
    confidence decimal(4,2) DEFAULT 0.50,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (scope, scope_id),
    INDEX idx_scope_confidence (scope, confidence DESC),
    INDEX idx_source_updated (source, updated_at DESC)
);

-- Example data showing real pack rules
INSERT INTO pack_rules VALUES
('product', 'ABC123-ELFBAR-5000', 1, 10, 1, 'floor', 'vendor', 0.95, NOW(), NOW()),
('category', 'disposable-vapes', 1, 10, 1, 'floor', 'human', 0.90, NOW(), NOW()),
('product', 'XYZ789-COIL-5PACK', 5, NULL, 0, 'round', 'vendor', 0.98, NOW(), NOW());
```

#### `category_pack_rules` (Category Defaults)
**Purpose:** Default pack rules per product category
**Current State:** All categories default to individual units with floor rounding
```sql
CREATE TABLE category_pack_rules (
    category_code varchar(100) NOT NULL,
    default_pack_size int(11) DEFAULT 1,
    default_outer_multiple int(11) DEFAULT 1,
    enforce_outer tinyint(1) DEFAULT 0,
    rounding_mode enum('floor','ceil','round') DEFAULT 'floor',
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (category_code),
    INDEX idx_updated (updated_at DESC)
);

-- Current production data (all individual units)
INSERT INTO category_pack_rules VALUES
('Batteries', 1, 1, 0, 'floor', NOW(), NOW()),
('Battery Accessories', 1, 1, 0, 'floor', NOW(), NOW()),
('Coils & Pods', 1, 1, 0, 'floor', NOW(), NOW()),
('Disposable Vapes', 1, 1, 0, 'floor', NOW(), NOW()),
('Freebase E-Liquids', 1, 1, 0, 'floor', NOW(), NOW()),
('Mods', 1, 1, 0, 'floor', NOW(), NOW()),
('Nicotine Salt E-Liquids', 1, 1, 0, 'floor', NOW(), NOW()),
('Starter Kits', 1, 1, 0, 'floor', NOW(), NOW()),
('Tanks & Atomizers', 1, 1, 0, 'floor', NOW(), NOW());
```

#### `category_weights` (Shipping Optimization Data)
**Purpose:** Average weight per product category for shipping cost calculations
```sql
CREATE TABLE category_weights (
    category_code varchar(100) NOT NULL,
    avg_weight_grams int(11) NOT NULL,
    avg_volume_cm3 int(11) DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (category_code),
    INDEX idx_weight (avg_weight_grams)
);

-- Real production weight data
INSERT INTO category_weights VALUES
('1b9880d4-c287-48d1-9d8d-15a80f5ed60d', 160, NULL, NOW(), NOW()),  -- Heavy mods/kits
('261bc40a-e6b6-4c85-b365-6508f92f5b42', 40, NULL, NOW(), NOW()),   -- Light coils/pods
('2bd085cc-25ec-483a-86f1-3e5b45b94b99', 65, NULL, NOW(), NOW()),   -- Medium disposables
('45cfa726-a8d1-48e8-adcf-e9e036dcef6b', 20, NULL, NOW(), NOW()),   -- Very light accessories
('5ac544da-164d-4c72-b0b7-1295b3563cbd', 350, NULL, NOW(), NOW()),  -- Very heavy large mods
('7059f19a-8d16-4a13-8c23-8a3c1f2e4b5d', 200, NULL, NOW(), NOW()),  -- Heavy e-liquids/kits
('8fa2b1c3-9e4f-4d6a-9b2c-7e5f8a1b4c9d', 55, NULL, NOW(), NOW()),   -- Medium accessories
('9b8e2f5c-7d3a-4e9f-8c1b-6a4d9e2f7b8c', 75, NULL, NOW(), NOW());   -- Medium-light items
```

#### `freight_rules` (Container & Cost Optimization)
**Purpose:** Shipping container specifications and cost optimization
```sql
CREATE TABLE freight_rules (
    container varchar(50) NOT NULL,
    max_weight_grams int(11) NOT NULL,
    max_units int(11) DEFAULT NULL,
    cost decimal(10,2) NOT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (container),
    INDEX idx_weight_cost (max_weight_grams, cost)
);

-- Example shipping container rules
INSERT INTO freight_rules VALUES
('small_box', 2000, 50, 15.50, NOW(), NOW()),      -- 2kg limit, $15.50
('medium_box', 5000, 100, 28.90, NOW(), NOW()),    -- 5kg limit, $28.90
('large_box', 10000, 200, 42.50, NOW(), NOW()),    -- 10kg limit, $42.50
('courier_bag', 1500, 30, 12.50, NOW(), NOW());    -- 1.5kg limit, $12.50
```

### **Product Intelligence & Classification Tables**

#### `product_types` (Seeding Intelligence & Defaults)
**Purpose:** Product type classification with seeding defaults and weight estimates
```sql
CREATE TABLE product_types (
    id int(11) NOT NULL AUTO_INCREMENT,
    code varchar(50) NOT NULL,
    label varchar(100) NOT NULL,
    description text,
    active tinyint(1) DEFAULT 1,
    default_seed_qty int(10) unsigned DEFAULT 3,
    avg_weight_grams int(11) DEFAULT 100,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_code (code),
    INDEX idx_active_seed (active, default_seed_qty)
);

-- Complete product type intelligence data
INSERT INTO product_types (code, label, description, active, default_seed_qty, avg_weight_grams) VALUES
('disposable', 'Disposable Vapes', 'One-time-use vaping devices like Elfbar, Geek Bar', 1, 10, 60),
('starter_kit', 'Starter Kits', 'Beginner-oriented pod systems and starter kits', 1, 5, 300),
('mod_kit', 'Subohm Mod Kits', 'Advanced vaping systems with separate tanks and mods', 1, 2, 400),
('e-liquid', 'E-Liquids', 'Vape juice in 10ml-120ml bottles, freebase and nicotine salt', 1, 5, 150),
('coils_pods', 'Coils & Pod Cartridges', 'Replacement mesh coils, ceramic coils, pod cartridges', 1, 10, 40),
('accessory', 'Accessories', 'Chargers, tools, cotton, bottles, cases, drip tips', 1, 5, 80),
('batteries', 'Batteries', 'Battery cells, wraps, cases, chargers', 1, 10, 60),
('unknown', 'Unknown Products', 'Unmatched or uncategorized products requiring classification', 1, 3, 100);
```

#### `product_categorization_data` (AI Classification Results)
**Purpose:** Store GPT analysis results and learning data
```sql
CREATE TABLE product_categorization_data (
    id int(11) NOT NULL AUTO_INCREMENT,
    product_id varchar(255) NOT NULL,
    lightspeed_category_id varchar(255) DEFAULT NULL,
    category_code varchar(50) DEFAULT NULL,
    pack_quantity int(11) DEFAULT 1,
    outer_packaging int(11) DEFAULT NULL,
    categorization_confidence decimal(3,2) DEFAULT 0.50,
    categorization_method varchar(50) DEFAULT 'unknown',
    categorization_reasoning text,
    human_verified tinyint(1) DEFAULT 0,
    human_correction text DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_product (product_id),
    INDEX idx_confidence_method (categorization_confidence DESC, categorization_method),
    INDEX idx_human_verified (human_verified, updated_at DESC)
);
```

#### `product_classification_unified` (Current Classification System)
**Purpose:** Unified product classification linking products to types and categories
```sql
CREATE TABLE product_classification_unified (
    product_id varchar(255) NOT NULL,
    product_type_code varchar(50) NOT NULL,
    category_code varchar(100) DEFAULT NULL,
    external_source_id varchar(255) DEFAULT NULL,
    confidence decimal(4,2) DEFAULT 0.50,
    method varchar(50) DEFAULT 'manual',
    reasoning text DEFAULT NULL,
    human_verified tinyint(1) DEFAULT 0,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    FOREIGN KEY (product_type_code) REFERENCES product_types(code) ON UPDATE CASCADE,
    INDEX idx_type_confidence (product_type_code, confidence DESC),
    INDEX idx_method_verified (method, human_verified)
);
```

### **Inventory & Transfer Execution Tables**

#### `vend_inventory` (Live Inventory Data)
**Purpose:** Real-time inventory levels synchronized from Vend/Lightspeed
```sql
CREATE TABLE vend_inventory (
    id int(11) NOT NULL AUTO_INCREMENT,
    outlet_id varchar(45) NOT NULL,
    product_id varchar(45) NOT NULL,
    inventory_level decimal(10,2) DEFAULT 0.00,
    current_amount decimal(16,6) DEFAULT 0.000000,
    version varchar(255) DEFAULT NULL,
    reorder_point decimal(10,2) DEFAULT NULL,
    reorder_amount decimal(10,2) DEFAULT NULL,
    deleted_at timestamp NULL DEFAULT NULL,
    average_cost decimal(16,6) DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_outlet_product (outlet_id, product_id),
    INDEX idx_inventory_level (inventory_level),
    INDEX idx_reorder (reorder_point, inventory_level)
);
```

#### `stock_transfers` (Transfer Headers)
**Purpose:** Transfer header records with metadata and status tracking
```sql
CREATE TABLE stock_transfers (
    transfer_id int(11) NOT NULL AUTO_INCREMENT,
    date_created timestamp DEFAULT CURRENT_TIMESTAMP,
    status varchar(50) DEFAULT 'pending',
    micro_status varchar(100) DEFAULT NULL,
    receive_confidence decimal(3,2) DEFAULT NULL,
    receive_quality_notes text,
    transfer_created_by_user varchar(100) DEFAULT NULL,
    transfer_completed_by_user varchar(100) DEFAULT NULL,
    transfer_completed timestamp NULL DEFAULT NULL,
    outlet_from varchar(45) NOT NULL,
    outlet_to varchar(45) NOT NULL,
    transfer_type enum('manual','auto_seed','rebalance','emergency') DEFAULT 'manual',
    algorithm_version varchar(20) DEFAULT NULL,
    total_items int(11) DEFAULT 0,
    total_weight_grams int(11) DEFAULT NULL,
    shipping_cost decimal(10,2) DEFAULT NULL,
    notes text,
    PRIMARY KEY (transfer_id),
    INDEX idx_status_date (status, date_created DESC),
    INDEX idx_outlets (outlet_from, outlet_to),
    INDEX idx_type_status (transfer_type, status)
);
```

#### `stock_products_to_transfer` (Transfer Line Items)
**Purpose:** Individual product transfer lines with intelligence and optimization data
```sql
CREATE TABLE stock_products_to_transfer (
    primary_key int(11) NOT NULL AUTO_INCREMENT,
    transfer_id int(11) NOT NULL,
    product_id varchar(45) NOT NULL,
    qty_to_transfer int(11) NOT NULL DEFAULT 0,
    min_qty_to_remain int(11) DEFAULT 0,
    qty_transferred_at_source int(11) DEFAULT NULL,
    qty_counted_at_destination int(11) DEFAULT NULL,
    new_total_qty_in_stock int(11) DEFAULT NULL,
    new_total_at_destination int(11) DEFAULT NULL,
    unexpected_product_added tinyint(1) DEFAULT 0,
    staff_added_product tinyint(1) DEFAULT 0,
    validation_flags varchar(500) DEFAULT NULL,
    validation_notes text,
    deleted_at timestamp NULL DEFAULT NULL,
    
    -- AI & ML Intelligence Fields
    demand_forecast decimal(10,2) DEFAULT NULL,
    stockout_risk decimal(5,2) DEFAULT NULL,
    overstock_risk decimal(5,2) DEFAULT NULL,
    optimal_qty int(11) DEFAULT NULL,
    sales_velocity decimal(10,2) DEFAULT NULL,
    abc_classification enum('A','B','C','D') DEFAULT NULL,
    profit_impact decimal(12,2) DEFAULT NULL,
    ml_priority_score decimal(5,2) DEFAULT NULL,
    last_sale_date date DEFAULT NULL,
    days_of_stock int(11) DEFAULT NULL,
    
    -- Pack & Logistics Intelligence
    pack_size int(11) DEFAULT 1,
    outer_multiple int(11) DEFAULT NULL,
    enforce_outer tinyint(1) DEFAULT 0,
    pack_compliance_status enum('compliant','broken','forced') DEFAULT 'compliant',
    weight_per_unit_grams int(11) DEFAULT NULL,
    total_weight_grams int(11) DEFAULT NULL,
    value_per_gram decimal(10,4) DEFAULT NULL,
    shipping_priority tinyint(1) DEFAULT 0,
    
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (primary_key),
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(transfer_id) ON DELETE CASCADE,
    UNIQUE KEY uk_transfer_product (transfer_id, product_id),
    INDEX idx_product_transfer (product_id, transfer_id),
    INDEX idx_ml_scores (ml_priority_score DESC, abc_classification),
    INDEX idx_pack_compliance (pack_compliance_status, enforce_outer),
    INDEX idx_shipping_priority (shipping_priority, value_per_gram DESC)
);
```

---

## 🔧 **SQL OPTIMIZATION INDEXES**

### **Performance-Critical Indexes**
```sql
-- Transfer settings lookups (high frequency)
CREATE INDEX idx_outlet_product_settings ON vend_products_outlet_transfer_settings (outlet_id, product_id);
CREATE INDEX idx_product_transfer_enabled ON vend_products_default_transfer_settings (product_id, enable_product_transfer);

-- Pack rules resolution (very high frequency)  
CREATE INDEX idx_pack_scope_confidence ON pack_rules (scope, scope_id, confidence DESC);
CREATE INDEX idx_category_pack_lookup ON category_pack_rules (category_code);

-- Weight & shipping optimization (high frequency)
CREATE INDEX idx_weight_optimization ON category_weights (avg_weight_grams);
CREATE INDEX idx_freight_weight_cost ON freight_rules (max_weight_grams, cost);

-- Product classification (high frequency)
CREATE INDEX idx_product_type_classification ON product_classification_unified (product_id, product_type_code, confidence DESC);
CREATE INDEX idx_type_active_seed ON product_types (code, active, default_seed_qty);

-- Inventory lookups (extremely high frequency)
CREATE INDEX idx_inventory_outlet_product ON vend_inventory (outlet_id, product_id, inventory_level);
CREATE INDEX idx_inventory_reorder ON vend_inventory (outlet_id, reorder_point, inventory_level);

-- Transfer execution (high frequency)
CREATE INDEX idx_transfer_status_date ON stock_transfers (status, date_created DESC);
CREATE INDEX idx_transfer_outlets_type ON stock_transfers (outlet_from, outlet_to, transfer_type);
CREATE INDEX idx_transfer_lines_product ON stock_products_to_transfer (transfer_id, product_id);
CREATE INDEX idx_ml_priority_optimization ON stock_products_to_transfer (ml_priority_score DESC, abc_classification, shipping_priority);
```

### **Analytics & Reporting Indexes**
```sql
-- Performance analytics
CREATE INDEX idx_pack_compliance_analysis ON stock_products_to_transfer (pack_compliance_status, created_at DESC);
CREATE INDEX idx_transfer_performance ON stock_transfers (transfer_type, status, date_created DESC);
CREATE INDEX idx_weight_shipping_analysis ON stock_products_to_transfer (total_weight_grams, shipping_priority, created_at DESC);

-- GPT learning and improvement
CREATE INDEX idx_gpt_learning ON product_categorization_data (categorization_method, categorization_confidence DESC, human_verified);
CREATE INDEX idx_human_corrections ON product_categorization_data (human_verified, updated_at DESC);
```

---

## ⚡ **QUERY PATTERNS & OPTIMIZATION**

### **Transfer Settings Resolution Query**
```sql
-- Optimized cascade query for transfer settings
SELECT 
    COALESCE(outlet.enable_product_transfer, product.enable_product_transfer, 1) as enable_transfer,
    COALESCE(outlet.maximum_qty_to_send, product.maximum_qty_to_send, NULL) as max_qty,
    COALESCE(outlet.send_in_multiple_qty, product.send_in_multiple_qty, 1) as multiple_qty,
    COALESCE(outlet.minimum_qty_left_at_warehouse, product.minimum_qty_left_at_warehouse, 0) as min_remain
FROM (SELECT ? as product_id, ? as outlet_id) as params
LEFT JOIN vend_products_outlet_transfer_settings outlet 
    ON outlet.product_id = params.product_id AND outlet.outlet_id = params.outlet_id
LEFT JOIN vend_products_default_transfer_settings product 
    ON product.product_id = params.product_id;
```

### **Pack Rules Cascade Query**
```sql
-- Optimized pack rules resolution with confidence scoring
SELECT 
    COALESCE(product_rule.pack_size, category_rule.pack_size, category_default.default_pack_size, 1) as pack_size,
    COALESCE(product_rule.outer_multiple, category_rule.outer_multiple, category_default.default_outer_multiple, 1) as outer_multiple,
    COALESCE(product_rule.enforce_outer, category_rule.enforce_outer, category_default.enforce_outer, 0) as enforce_outer,
    COALESCE(product_rule.rounding_mode, category_rule.rounding_mode, category_default.rounding_mode, 'round') as rounding_mode,
    GREATEST(
        COALESCE(product_rule.confidence, 0),
        COALESCE(category_rule.confidence, 0),
        0.50
    ) as confidence
FROM (SELECT ? as product_id, ? as category_id) as params
LEFT JOIN pack_rules product_rule 
    ON product_rule.scope = 'product' AND product_rule.scope_id = params.product_id
LEFT JOIN pack_rules category_rule 
    ON category_rule.scope = 'category' AND category_rule.scope_id = params.category_id  
LEFT JOIN category_pack_rules category_default 
    ON category_default.category_code = params.category_id;
```

### **Weight Optimization Query**
```sql
-- Optimized weight and shipping cost calculation
SELECT 
    p.product_id,
    p.qty_to_transfer,
    COALESCE(cw.avg_weight_grams, pt.avg_weight_grams, 100) as weight_per_unit,
    (p.qty_to_transfer * COALESCE(cw.avg_weight_grams, pt.avg_weight_grams, 100)) as total_weight,
    vp.retail_price,
    (vp.retail_price / COALESCE(cw.avg_weight_grams, pt.avg_weight_grams, 100)) as value_per_gram,
    CASE 
        WHEN (vp.retail_price / COALESCE(cw.avg_weight_grams, pt.avg_weight_grams, 100)) > 0.50 THEN 1 
        ELSE 0 
    END as shipping_priority
FROM stock_products_to_transfer p
JOIN product_classification_unified pcu ON pcu.product_id = p.product_id  
JOIN product_types pt ON pt.code = pcu.product_type_code
LEFT JOIN category_weights cw ON cw.category_code = pcu.category_code
LEFT JOIN vend_products vp ON vp.id = p.product_id
WHERE p.transfer_id = ?
ORDER BY value_per_gram DESC, shipping_priority DESC;
```

---

## 📊 **BUSINESS INTELLIGENCE VIEWS**

### **Transfer Performance Dashboard View**
```sql
CREATE VIEW v_transfer_performance AS
SELECT 
    DATE(st.date_created) as transfer_date,
    st.transfer_type,
    COUNT(*) as total_transfers,
    AVG(st.total_items) as avg_items_per_transfer,
    AVG(st.total_weight_grams/1000) as avg_weight_kg,
    AVG(st.shipping_cost) as avg_shipping_cost,
    SUM(CASE WHEN st.status = 'completed' THEN 1 ELSE 0 END) / COUNT(*) * 100 as success_rate,
    AVG(TIMESTAMPDIFF(HOUR, st.date_created, st.transfer_completed)) as avg_completion_hours
FROM stock_transfers st
WHERE st.date_created >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(st.date_created), st.transfer_type
ORDER BY transfer_date DESC;
```

### **Pack Compliance Analytics View**
```sql
CREATE VIEW v_pack_compliance AS  
SELECT 
    DATE(sp.created_at) as date,
    sp.pack_compliance_status,
    COUNT(*) as total_lines,
    COUNT(*) / SUM(COUNT(*)) OVER (PARTITION BY DATE(sp.created_at)) * 100 as compliance_percentage,
    AVG(CASE WHEN sp.enforce_outer = 1 THEN 1 ELSE 0 END) * 100 as enforce_outer_percentage
FROM stock_products_to_transfer sp
WHERE sp.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(sp.created_at), sp.pack_compliance_status
ORDER BY date DESC, compliance_percentage DESC;
```

### **GPT Learning Analytics View**
```sql
CREATE VIEW v_gpt_learning AS
SELECT 
    pcd.categorization_method,
    AVG(pcd.categorization_confidence) as avg_confidence,
    COUNT(*) as total_classifications,
    SUM(pcd.human_verified) as human_verified_count,
    (SUM(pcd.human_verified) / COUNT(*)) * 100 as verification_rate,
    COUNT(CASE WHEN pcd.human_correction IS NOT NULL THEN 1 END) as correction_count,
    (COUNT(CASE WHEN pcd.human_correction IS NOT NULL THEN 1 END) / COUNT(*)) * 100 as correction_rate
FROM product_categorization_data pcd
WHERE pcd.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY pcd.categorization_method
ORDER BY avg_confidence DESC;
```

---

**TECHNICAL SPECIFICATIONS STATUS:** ✅ **COMPLETE DATABASE SCHEMA DOCUMENTED**  
**OPTIMIZATION STATUS:** ✅ **PERFORMANCE INDEXES DESIGNED**  
**BUSINESS INTELLIGENCE:** ✅ **ANALYTICS VIEWS CREATED**  
**IMPLEMENTATION READY:** ✅ **FULL SCHEMA & QUERY PATTERNS AVAILABLE**
