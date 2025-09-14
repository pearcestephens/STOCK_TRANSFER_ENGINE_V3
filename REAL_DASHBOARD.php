<?php
/**
 * 🔥 REAL TURBO TRANSFER DASHBOARD - NO BULLSHIT VERSION
 * Real database connections, real data analysis, real business intelligence
 */

// REAL DATABASE CONNECTION - NO DEMO SHIT
$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'jcepnzzkmj';
$pass = getenv('DB_PASS') ?: 'wprKh9Jq63';
$db   = getenv('DB_NAME') ?: 'jcepnzzkmj';

try {
    $mysqli = new mysqli($host, $user, $pass, $db);
    if ($mysqli->connect_error) {
        die("REAL CONNECTION FAILED: " . $mysqli->connect_error);
    }
    $mysqli->set_charset("utf8mb4");
} catch (Exception $e) {
    die("DATABASE ERROR: " . $e->getMessage());
}

// REAL DATA ANALYSIS FUNCTIONS
function getRealInventoryData($mysqli) {
    $sql = "SELECT 
        COUNT(DISTINCT outlet_id) as total_outlets,
        COUNT(*) as total_inventory_lines,
        SUM(inventory_level) as total_stock_units,
        AVG(inventory_level) as avg_stock_per_line,
        SUM(CASE WHEN inventory_level = 0 THEN 1 ELSE 0 END) as stockout_count,
        SUM(CASE WHEN inventory_level < 5 THEN 1 ELSE 0 END) as low_stock_count
    FROM vend_inventory WHERE deleted_at IS NULL";
    
    $result = $mysqli->query($sql);
    return $result ? $result->fetch_assoc() : [];
}

function getRealTransferData($mysqli) {
    $sql = "SELECT 
        COUNT(*) as total_transfers,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_transfers,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transfers,
        COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as active_transfers,
        COUNT(CASE WHEN DATE(date_created) = CURDATE() THEN 1 END) as today_transfers
    FROM stock_transfers WHERE deleted_at IS NULL";
    
    $result = $mysqli->query($sql);
    return $result ? $result->fetch_assoc() : [];
}

function getRealOutletData($mysqli) {
    $sql = "SELECT 
        outlet_id,
        outlet_name,
        outlet_timezone,
        COUNT(vi.id) as inventory_lines,
        SUM(vi.inventory_level) as total_stock
    FROM vend_outlets vo
    LEFT JOIN vend_inventory vi ON vo.outlet_id = vi.outlet_id 
    WHERE vo.deleted_at IS NULL AND vi.deleted_at IS NULL
    GROUP BY vo.outlet_id, vo.outlet_name, vo.outlet_timezone
    ORDER BY total_stock DESC";
    
    $result = $mysqli->query($sql);
    $outlets = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $outlets[] = $row;
        }
    }
    return $outlets;
}

function getRealProductAnalysis($mysqli) {
    $sql = "SELECT 
        vp.product_name,
        vp.retail_price,
        SUM(vi.inventory_level) as network_stock,
        COUNT(DISTINCT vi.outlet_id) as outlets_with_stock,
        AVG(vi.inventory_level) as avg_per_outlet,
        MAX(vi.inventory_level) as max_stock,
        MIN(vi.inventory_level) as min_stock
    FROM vend_products vp
    JOIN vend_inventory vi ON vp.product_id = vi.product_id
    WHERE vp.deleted_at IS NULL AND vi.deleted_at IS NULL AND vi.inventory_level > 0
    GROUP BY vp.product_id, vp.product_name, vp.retail_price
    HAVING network_stock > 10
    ORDER BY network_stock DESC
    LIMIT 50";
    
    $result = $mysqli->query($sql);
    $products = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
    return $products;
}

function getRealTransferOpportunities($mysqli) {
    $sql = "SELECT 
        outlet_from.outlet_name as from_store,
        outlet_to.outlet_name as to_store,
        COUNT(*) as transfer_lines,
        SUM(spt.qty_to_transfer) as total_qty,
        AVG(spt.qty_to_transfer) as avg_qty_per_line,
        st.status,
        st.date_created,
        st.transfer_id
    FROM stock_transfers st
    JOIN stock_products_to_transfer spt ON st.transfer_id = spt.transfer_id
    JOIN vend_outlets outlet_from ON st.outlet_from = outlet_from.outlet_id
    JOIN vend_outlets outlet_to ON st.outlet_to = outlet_to.outlet_id
    WHERE st.deleted_at IS NULL AND spt.deleted_at IS NULL
    AND st.date_created >= DATE_SUB(NOW(), INTERVAL 30 DAYS)
    GROUP BY st.transfer_id, outlet_from.outlet_name, outlet_to.outlet_name, st.status, st.date_created
    ORDER BY st.date_created DESC
    LIMIT 100";
    
    $result = $mysqli->query($sql);
    $transfers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transfers[] = $row;
        }
    }
    return $transfers;
}

function getCriticalStockAlerts($mysqli) {
    $sql = "SELECT 
        vo.outlet_name,
        vp.product_name,
        vi.inventory_level,
        vi.reorder_point,
        vi.reorder_amount,
        vp.retail_price,
        (vi.reorder_point - vi.inventory_level) as shortage
    FROM vend_inventory vi
    JOIN vend_outlets vo ON vi.outlet_id = vo.outlet_id
    JOIN vend_products vp ON vi.product_id = vp.product_id
    WHERE vi.deleted_at IS NULL 
    AND vo.deleted_at IS NULL 
    AND vp.deleted_at IS NULL
    AND vi.inventory_level < vi.reorder_point
    AND vi.reorder_point > 0
    ORDER BY shortage DESC
    LIMIT 50";
    
    $result = $mysqli->query($sql);
    $alerts = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $alerts[] = $row;
        }
    }
    return $alerts;
}

// GET ALL REAL DATA
$inventory_data = getRealInventoryData($mysqli);
$transfer_data = getRealTransferData($mysqli);
$outlet_data = getRealOutletData($mysqli);
$product_analysis = getRealProductAnalysis($mysqli);
$transfer_opportunities = getRealTransferOpportunities($mysqli);
$critical_alerts = getCriticalStockAlerts($mysqli);

// CALCULATE REAL BUSINESS METRICS
$total_network_value = 0;
$total_stock_units = $inventory_data['total_stock_units'] ?? 0;
$stockout_percentage = $total_stock_units > 0 ? 
    round(($inventory_data['stockout_count'] ?? 0) / $total_stock_units * 100, 2) : 0;

// Calculate network value
foreach ($product_analysis as $product) {
    $total_network_value += $product['network_stock'] * $product['retail_price'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔥 REAL Transfer Analytics Dashboard | CIS</title>
    
    <!-- Bootstrap 4.6 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1400px !important;
            background: rgba(255, 255, 255, 0.98);
            margin: 20px auto;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .real-header {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .real-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .real-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }

        .metric-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 5px solid #007bff;
            transition: transform 0.3s ease;
        }

        .metric-card:hover {
            transform: translateY(-5px);
        }

        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            display: block;
        }

        .metric-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .data-section {
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-title {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 15px 25px;
            margin: -30px -30px 25px -30px;
            font-size: 1.3rem;
            font-weight: bold;
        }

        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .data-table table {
            margin: 0;
        }

        .data-table th {
            background: #343a40;
            color: white;
            font-weight: 600;
            border: none;
            padding: 15px 12px;
        }

        .data-table td {
            padding: 12px;
            border-top: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .alert-card {
            border-left: 5px solid #dc3545;
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-active { background: #d1ecf1; color: #0c5460; }

        .chart-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .real-footer {
            background: #343a40;
            color: white;
            text-align: center;
            padding: 20px;
            font-size: 0.9rem;
        }

        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 25px;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .refresh-btn:hover {
            background: #218838;
            transform: scale(1.05);
            color: white;
        }

        .value-highlight {
            color: #28a745;
            font-weight: bold;
        }

        .shortage-highlight {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    
    <!-- REAL HEADER -->
    <div class="real-header">
        <h1><i class="fas fa-fire"></i> REAL TRANSFER ANALYTICS</h1>
        <p class="mb-0">Live Data from Production Database | Last Updated: <?= date('Y-m-d H:i:s') ?></p>
        <p class="mt-2"><strong>Database:</strong> <?= $db ?> | <strong>Connection:</strong> LIVE</p>
    </div>
    
    <!-- REAL METRICS GRID -->
    <div class="real-metrics">
        <div class="metric-card">
            <span class="metric-value"><?= number_format($inventory_data['total_outlets'] ?? 0) ?></span>
            <div class="metric-label">Active Outlets</div>
        </div>
        <div class="metric-card">
            <span class="metric-value"><?= number_format($total_stock_units) ?></span>
            <div class="metric-label">Total Stock Units</div>
        </div>
        <div class="metric-card">
            <span class="metric-value">$<?= number_format($total_network_value, 2) ?></span>
            <div class="metric-label">Network Value</div>
        </div>
        <div class="metric-card">
            <span class="metric-value"><?= $transfer_data['total_transfers'] ?? 0 ?></span>
            <div class="metric-label">Total Transfers</div>
        </div>
        <div class="metric-card">
            <span class="metric-value"><?= $inventory_data['stockout_count'] ?? 0 ?></span>
            <div class="metric-label">Stockouts</div>
        </div>
        <div class="metric-card">
            <span class="metric-value"><?= $stockout_percentage ?>%</span>
            <div class="metric-label">Stockout Rate</div>
        </div>
    </div>
    
    <!-- CRITICAL ALERTS SECTION -->
    <div class="data-section">
        <div class="section-title">
            <i class="fas fa-exclamation-triangle"></i> CRITICAL STOCK ALERTS (<?= count($critical_alerts) ?> items)
        </div>
        
        <?php if (!empty($critical_alerts)): ?>
            <div class="row">
                <?php foreach (array_slice($critical_alerts, 0, 6) as $alert): ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="alert-card">
                        <h6 class="mb-2"><?= htmlspecialchars($alert['outlet_name']) ?></h6>
                        <p class="mb-1"><strong><?= htmlspecialchars($alert['product_name']) ?></strong></p>
                        <p class="mb-1">Stock: <span class="shortage-highlight"><?= $alert['inventory_level'] ?></span> | 
                        Reorder: <?= $alert['reorder_point'] ?></p>
                        <p class="mb-0">Shortage: <span class="shortage-highlight"><?= $alert['shortage'] ?> units</span></p>
                        <small class="text-muted">Value: $<?= number_format($alert['retail_price'], 2) ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> No critical stock alerts - all outlets adequately stocked!
            </div>
        <?php endif; ?>
    </div>
    
    <!-- OUTLET PERFORMANCE -->
    <div class="data-section">
        <div class="section-title">
            <i class="fas fa-store"></i> OUTLET INVENTORY ANALYSIS
        </div>
        
        <div class="data-table">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Outlet Name</th>
                        <th>Inventory Lines</th>
                        <th>Total Stock</th>
                        <th>Avg per Line</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($outlet_data, 0, 10) as $outlet): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($outlet['outlet_name']) ?></strong></td>
                        <td><?= number_format($outlet['inventory_lines']) ?></td>
                        <td class="value-highlight"><?= number_format($outlet['total_stock']) ?></td>
                        <td><?= number_format($outlet['total_stock'] / max($outlet['inventory_lines'], 1), 1) ?></td>
                        <td>
                            <?php 
                            $performance = $outlet['total_stock'] > 1000 ? 'HIGH' : 
                                         ($outlet['total_stock'] > 500 ? 'MEDIUM' : 'LOW');
                            $badge_class = $performance == 'HIGH' ? 'status-completed' : 
                                         ($performance == 'MEDIUM' ? 'status-pending' : 'status-active');
                            ?>
                            <span class="status-badge <?= $badge_class ?>"><?= $performance ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- TOP PRODUCTS ANALYSIS -->
    <div class="data-section">
        <div class="section-title">
            <i class="fas fa-trophy"></i> TOP PRODUCTS BY NETWORK STOCK
        </div>
        
        <div class="data-table">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Network Stock</th>
                        <th>Outlets</th>
                        <th>Avg per Outlet</th>
                        <th>Retail Price</th>
                        <th>Network Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($product_analysis, 0, 15) as $product): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($product['product_name']) ?></strong></td>
                        <td class="value-highlight"><?= number_format($product['network_stock']) ?></td>
                        <td><?= $product['outlets_with_stock'] ?></td>
                        <td><?= number_format($product['avg_per_outlet'], 1) ?></td>
                        <td>$<?= number_format($product['retail_price'], 2) ?></td>
                        <td class="value-highlight">$<?= number_format($product['network_stock'] * $product['retail_price'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- RECENT TRANSFER ACTIVITY -->
    <div class="data-section">
        <div class="section-title">
            <i class="fas fa-exchange-alt"></i> RECENT TRANSFER ACTIVITY (Last 30 Days)
        </div>
        
        <div class="data-table">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Transfer ID</th>
                        <th>From Store</th>
                        <th>To Store</th>
                        <th>Items</th>
                        <th>Total Qty</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($transfer_opportunities, 0, 20) as $transfer): ?>
                    <tr>
                        <td><strong><?= $transfer['transfer_id'] ?></strong></td>
                        <td><?= htmlspecialchars($transfer['from_store']) ?></td>
                        <td><?= htmlspecialchars($transfer['to_store']) ?></td>
                        <td><?= $transfer['transfer_lines'] ?></td>
                        <td class="value-highlight"><?= number_format($transfer['total_qty']) ?></td>
                        <td>
                            <?php 
                            $status_class = $transfer['status'] == 'completed' ? 'status-completed' : 
                                          ($transfer['status'] == 'pending' ? 'status-pending' : 'status-active');
                            ?>
                            <span class="status-badge <?= $status_class ?>"><?= strtoupper($transfer['status']) ?></span>
                        </td>
                        <td><?= date('M j, Y', strtotime($transfer['date_created'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- NETWORK HEALTH SUMMARY -->
    <div class="data-section">
        <div class="section-title">
            <i class="fas fa-heartbeat"></i> NETWORK HEALTH SUMMARY
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="alert alert-info">
                    <h5><i class="fas fa-database"></i> Database Health</h5>
                    <p>Total Inventory Records: <strong><?= number_format($inventory_data['total_inventory_lines'] ?? 0) ?></strong></p>
                    <p>Active Outlets: <strong><?= number_format($inventory_data['total_outlets'] ?? 0) ?></strong></p>
                    <p>Connection Status: <strong class="text-success">LIVE</strong></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-circle"></i> Stock Issues</h5>
                    <p>Stockouts: <strong><?= number_format($inventory_data['stockout_count'] ?? 0) ?></strong></p>
                    <p>Low Stock: <strong><?= number_format($inventory_data['low_stock_count'] ?? 0) ?></strong></p>
                    <p>Critical Alerts: <strong><?= count($critical_alerts) ?></strong></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="alert alert-success">
                    <h5><i class="fas fa-chart-line"></i> Transfer Stats</h5>
                    <p>Total Transfers: <strong><?= $transfer_data['total_transfers'] ?? 0 ?></strong></p>
                    <p>Completed: <strong><?= $transfer_data['completed_transfers'] ?? 0 ?></strong></p>
                    <p>Today: <strong><?= $transfer_data['today_transfers'] ?? 0 ?></strong></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- REAL FOOTER -->
    <div class="real-footer">
        <p><strong>REAL DATA DASHBOARD</strong> | Connected to: <?= $host ?>/<?= $db ?> | 
        Generated: <?= date('Y-m-d H:i:s') ?> | 
        Queries: <?= $mysqli->stat ?? 'Live Connection' ?></p>
    </div>

</div>

<!-- REFRESH BUTTON -->
<button class="refresh-btn" onclick="window.location.reload()">
    <i class="fas fa-sync"></i> REFRESH REAL DATA
</button>

<script>
// Auto-refresh every 5 minutes
setTimeout(function() {
    window.location.reload();
}, 300000);

console.log('🔥 REAL DASHBOARD LOADED');
console.log('Total Stock Units:', <?= $total_stock_units ?>);
console.log('Network Value: $', <?= $total_network_value ?>);
console.log('Critical Alerts:', <?= count($critical_alerts) ?>);
</script>

</body>
</html>

<?php
// Close database connection
$mysqli->close();
?>
