-- Initialize Stock Transfer Engine V3 Database
-- This script sets up the database with initial data

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_stocks_sku ON stocks(sku);
CREATE INDEX IF NOT EXISTS idx_stocks_status ON stocks(status);
CREATE INDEX IF NOT EXISTS idx_stocks_category ON stocks(category);
CREATE INDEX IF NOT EXISTS idx_stocks_available_stock ON stocks(available_stock);

CREATE INDEX IF NOT EXISTS idx_stock_movements_stock_id ON stock_movements(stock_id);
CREATE INDEX IF NOT EXISTS idx_stock_movements_date ON stock_movements(movement_date);
CREATE INDEX IF NOT EXISTS idx_stock_movements_type ON stock_movements(movement_type);

CREATE INDEX IF NOT EXISTS idx_transfers_status ON transfers(status);
CREATE INDEX IF NOT EXISTS idx_transfers_from_location ON transfers(from_location);
CREATE INDEX IF NOT EXISTS idx_transfers_to_location ON transfers(to_location);
CREATE INDEX IF NOT EXISTS idx_transfers_created_at ON transfers(created_at);

CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

CREATE INDEX IF NOT EXISTS idx_alerts_resolved ON alerts(is_resolved);
CREATE INDEX IF NOT EXISTS idx_alerts_created_at ON alerts(created_at);

-- Insert default admin user (password: admin123)
INSERT INTO users (
    username, 
    email, 
    full_name, 
    hashed_password, 
    role, 
    is_active, 
    is_verified,
    department
) VALUES (
    'admin',
    'admin@stockengine.com',
    'System Administrator',
    '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj4zY8K4OyQK', -- admin123
    'admin',
    true,
    true,
    'IT'
) ON CONFLICT (username) DO NOTHING;

-- Insert demo manager user (password: manager123)
INSERT INTO users (
    username,
    email,
    full_name,
    hashed_password,
    role,
    is_active,
    is_verified,
    department
) VALUES (
    'manager',
    'manager@stockengine.com',
    'Stock Manager',
    '$2b$12$8Hjk9PtFMvLJgF4QpN2lBe1pQF5mKx0jE8W6tN4lM2vS3rD7qK9aG', -- manager123
    'manager',
    true,
    true,
    'Operations'
) ON CONFLICT (username) DO NOTHING;

-- Insert demo operator user (password: operator123)
INSERT INTO users (
    username,
    email,
    full_name,
    hashed_password,
    role,
    is_active,
    is_verified,
    department
) VALUES (
    'operator',
    'operator@stockengine.com',
    'Stock Operator',
    '$2b$12$9Ijk0QtGNwMKhG5RqO3mCf2qRG6nLy1kF9X7uO5mN3wT4sE8rL0bH', -- operator123
    'operator',
    true,
    true,
    'Warehouse'
) ON CONFLICT (username) DO NOTHING;

-- Insert sample stock categories data
INSERT INTO stocks (
    sku, name, description, category, unit_of_measure, unit_cost, unit_price,
    current_stock, reserved_stock, available_stock, minimum_stock, maximum_stock,
    reorder_point, reorder_quantity, supplier_name, lead_time_days, location_code
) VALUES 
    ('WIDGET-001', 'Standard Widget', 'Basic widget for general use', 'finished_goods', 'pcs', 5.50, 12.99, 100, 0, 100, 20, 500, 30, 50, 'Widget Corp', 7, 'A-1-1'),
    ('SCREW-M6-10', 'M6x10 Screw', 'Metric screw M6 thread, 10mm length', 'raw_materials', 'pcs', 0.15, 0.35, 1000, 50, 950, 100, 5000, 200, 1000, 'Fastener Ltd', 3, 'B-2-5'),
    ('PAINT-RED-1L', 'Red Paint 1L', 'High quality red paint, 1 liter can', 'supplies', 'ltr', 8.75, 19.99, 25, 2, 23, 5, 100, 10, 20, 'Paint Plus', 5, 'C-1-3'),
    ('CABLE-ETH-5M', 'Ethernet Cable 5M', 'Category 6 Ethernet cable, 5 meters', 'supplies', 'pcs', 3.25, 7.99, 75, 10, 65, 15, 200, 25, 50, 'TechCable Inc', 2, 'D-3-2'),
    ('MOTOR-AC-1HP', '1HP AC Motor', 'Single phase AC motor, 1 horsepower', 'equipment', 'pcs', 125.00, 299.99, 8, 1, 7, 2, 20, 3, 5, 'Motor World', 14, 'E-1-1')
ON CONFLICT (sku) DO NOTHING;

-- Insert sample stock locations
INSERT INTO stock_locations (
    stock_id, location_code, location_name, warehouse, aisle, shelf, bin, quantity, reserved_quantity
) 
SELECT 
    s.id, s.location_code, 
    CASE s.location_code
        WHEN 'A-1-1' THEN 'Main Warehouse - Aisle A, Shelf 1, Bin 1'
        WHEN 'B-2-5' THEN 'Main Warehouse - Aisle B, Shelf 2, Bin 5'
        WHEN 'C-1-3' THEN 'Main Warehouse - Aisle C, Shelf 1, Bin 3'
        WHEN 'D-3-2' THEN 'Main Warehouse - Aisle D, Shelf 3, Bin 2'
        WHEN 'E-1-1' THEN 'Equipment Storage - Aisle E, Shelf 1, Bin 1'
    END,
    'Main Warehouse',
    LEFT(s.location_code, 1),
    SPLIT_PART(SPLIT_PART(s.location_code, '-', 2), '-', 1),
    SPLIT_PART(s.location_code, '-', 3),
    s.current_stock,
    s.reserved_stock
FROM stocks s
WHERE s.location_code IS NOT NULL
ON CONFLICT DO NOTHING;

-- Insert sample alert rules
INSERT INTO alert_rules (
    name, description, alert_type, severity, is_active, conditions,
    applies_to_all_stocks, email_notification, dashboard_notification
) VALUES 
    (
        'Low Stock Alert',
        'Alert when stock falls below minimum level',
        'low_stock',
        'warning',
        true,
        '{"condition": "available_stock <= minimum_stock"}',
        true,
        false,
        true
    ),
    (
        'Out of Stock Alert',
        'Critical alert when stock reaches zero',
        'out_of_stock',
        'critical',
        true,
        '{"condition": "available_stock <= 0"}',
        true,
        true,
        true
    ),
    (
        'Overstock Alert',
        'Alert when stock exceeds maximum level',
        'overstock',
        'warning',
        true,
        '{"condition": "current_stock >= maximum_stock"}',
        true,
        false,
        true
    )
ON CONFLICT (name) DO NOTHING;

-- Create some sample transfers
INSERT INTO transfers (
    transfer_number, status, from_location, to_location, reason,
    requested_by, priority
)
SELECT 
    'TRF-' || LPAD((ROW_NUMBER() OVER ())::text, 6, '0'),
    CASE (ROW_NUMBER() OVER ()) % 4
        WHEN 0 THEN 'completed'
        WHEN 1 THEN 'pending'
        WHEN 2 THEN 'in_transit'
        ELSE 'draft'
    END,
    'Main Warehouse',
    'Branch Office',
    'Regular inventory transfer',
    u.id,
    'normal'
FROM users u 
WHERE u.role IN ('admin', 'manager')
LIMIT 3
ON CONFLICT (transfer_number) DO NOTHING;

COMMIT;