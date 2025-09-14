<?php
/**
 * REAL TRANSFER EXECUTION ENGINE - NO MORE DEMO BULLSHIT
 * Connects to your existing transfer system and actually executes transfers
 * Shows container info, weights, quantities like your working system
 */

// Includes ALL CONFIG/DB & All asset/functions files - DO NOT INCLUDE ANYTHING ELSE
include("../../../assets/functions/config.php"); 

//######### AJAX BEGINS HERE #########

if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    // REAL Database Connection
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $user = getenv('DB_USER') ?: 'jcepnzzkmj';
    $pass = getenv('DB_PASS') ?: 'wprKh9Jq63';
    $db   = getenv('DB_NAME') ?: 'jcepnzzkmj';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        switch ($_POST['ajax_action']) {
            
            case 'execute_real_transfer':
                // ACTUALLY EXECUTE A REAL TRANSFER - NO BULLSHIT
                $mode = $_POST['mode'] ?? 'simulate';
                $simulate = ($mode === 'simulate') ? 1 : 0;
                
                // Call your ACTUAL transfer engine - the one that works
                $transfer_engine_path = '/home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3/index.php';
                
                // Build the command to run your actual transfer engine
                $cmd = "php {$transfer_engine_path}";
                $cmd .= " action=run";
                $cmd .= " simulate={$simulate}";
                $cmd .= " mode=auto";
                $cmd .= " 2>&1"; // Capture output
                
                // Execute the REAL transfer engine
                $output = shell_exec($cmd);
                
                // Parse the output to extract transfer details
                $transfers_created = 0;
                $containers = [];
                $total_weight = 0;
                $total_lines = 0;
                
                // Look for transfer creation patterns in output
                if (preg_match('/Transfers proposed:\s*(\d+)/', $output, $matches)) {
                    $transfers_created = (int)$matches[1];
                }
                
                // Look for container information
                if (preg_match_all('/Container:\s*([^•]+).*Weight:\s*(\d+(?:\.\d+)?)([gk]g).*Qty:\s*(\d+).*Lines:\s*(\d+)/s', $output, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $containers[] = [
                            'type' => trim($match[1]),
                            'weight' => $match[2],
                            'weight_unit' => $match[3],
                            'quantity' => (int)$match[4],
                            'lines' => (int)$match[5]
                        ];
                        $total_lines += (int)$match[5];
                    }
                }
                
                // Get the actual transfer records created
                if (!$simulate && $transfers_created > 0) {
                    $stmt = $pdo->query("
                        SELECT 
                            transfer_id,
                            outlet_from,
                            outlet_to,
                            status,
                            date_created,
                            (SELECT COUNT(*) FROM stock_products_to_transfer WHERE transfer_id = st.transfer_id) as product_count
                        FROM stock_transfers st
                        WHERE date_created >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                        ORDER BY date_created DESC
                        LIMIT {$transfers_created}
                    ");
                    $actual_transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $actual_transfers = [];
                }
                
                echo json_encode([
                    'success' => true,
                    'mode' => $mode,
                    'simulate' => $simulate,
                    'transfers_proposed' => $transfers_created,
                    'containers' => $containers,
                    'total_weight' => $total_weight,
                    'total_lines' => $total_lines,
                    'actual_transfers' => $actual_transfers,
                    'raw_output' => $output,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
                
            case 'get_transfer_history':
                // Get REAL transfer history with container details
                $stmt = $pdo->query("
                    SELECT 
                        st.transfer_id,
                        st.outlet_from,
                        st.outlet_to,
                        vof.outlet_name as from_name,
                        vot.outlet_name as to_name,
                        st.status,
                        st.date_created,
                        st.transfer_completed,
                        COUNT(spt.product_id) as product_lines,
                        SUM(spt.qty_to_transfer) as total_quantity,
                        GROUP_CONCAT(DISTINCT p.product_name SEPARATOR ', ') as products
                    FROM stock_transfers st
                    LEFT JOIN vend_outlets vof ON st.outlet_from = vof.outlet_id
                    LEFT JOIN vend_outlets vot ON st.outlet_to = vot.outlet_id
                    LEFT JOIN stock_products_to_transfer spt ON st.transfer_id = spt.transfer_id
                    LEFT JOIN vend_products p ON spt.product_id = p.product_id
                    WHERE st.deleted_at IS NULL
                    GROUP BY st.transfer_id
                    ORDER BY st.date_created DESC
                    LIMIT 50
                ");
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'transfers' => $history,
                    'total_count' => count($history)
                ]);
                exit;
                
            case 'get_active_transfers':
                // Get currently active transfers with details
                $stmt = $pdo->query("
                    SELECT 
                        st.transfer_id,
                        st.outlet_from,
                        st.outlet_to,
                        vof.outlet_name as from_name,
                        vot.outlet_name as to_name,
                        st.status,
                        st.date_created,
                        COUNT(spt.product_id) as product_count,
                        SUM(spt.qty_to_transfer) as total_qty
                    FROM stock_transfers st
                    LEFT JOIN vend_outlets vof ON st.outlet_from = vof.outlet_id
                    LEFT JOIN vend_outlets vot ON st.outlet_to = vot.outlet_id
                    LEFT JOIN stock_products_to_transfer spt ON st.transfer_id = spt.transfer_id
                    WHERE st.deleted_at IS NULL
                    AND st.status IN ('active', 'pending', 'in_progress')
                    GROUP BY st.transfer_id
                    ORDER BY st.date_created DESC
                ");
                $active = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'active_transfers' => $active,
                    'count' => count($active)
                ]);
                exit;
                
            case 'get_system_overview':
                // Get comprehensive system overview
                $stats = [];
                
                // Transfer statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_transfers,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_transfers,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transfers,
                        SUM(CASE WHEN DATE(date_created) = CURDATE() THEN 1 ELSE 0 END) as today_transfers
                    FROM stock_transfers 
                    WHERE deleted_at IS NULL
                ");
                $stats['transfers'] = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Inventory overview
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(DISTINCT vi.product_id) as total_products,
                        COUNT(DISTINCT vi.outlet_id) as active_outlets,
                        SUM(vi.inventory_level) as total_inventory,
                        COUNT(CASE WHEN vi.inventory_level < vi.reorder_point THEN 1 END) as low_stock_items,
                        COUNT(CASE WHEN vi.inventory_level > (vi.reorder_point * 3) THEN 1 END) as overstocked_items
                    FROM vend_inventory vi
                    WHERE vi.deleted_at IS NULL
                ");
                $stats['inventory'] = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Product categorization status
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_products,
                        COUNT(CASE WHEN category_name IS NULL OR category_name = '' THEN 1 END) as uncategorized_products,
                        COUNT(CASE WHEN brand_name IS NULL OR brand_name = '' THEN 1 END) as products_without_brand
                    FROM vend_products
                    WHERE deleted_at IS NULL
                ");
                $stats['categorization'] = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'stats' => $stats,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
                
            case 'get_store_list':
                // Get all active stores for selection
                $stmt = $pdo->query("
                    SELECT 
                        vo.outlet_id,
                        vo.outlet_name,
                        COUNT(vi.product_id) as product_count,
                        SUM(vi.inventory_level) as total_inventory,
                        COUNT(CASE WHEN vi.inventory_level < vi.reorder_point THEN 1 END) as low_stock_count,
                        COUNT(CASE WHEN vi.inventory_level > (vi.reorder_point * 3) THEN 1 END) as overstock_count
                    FROM vend_outlets vo
                    LEFT JOIN vend_inventory vi ON vo.outlet_id = vi.outlet_id AND vi.deleted_at IS NULL
                    WHERE vo.deleted_at IS NULL
                    GROUP BY vo.outlet_id, vo.outlet_name
                    ORDER BY vo.outlet_name
                ");
                $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'stores' => $stores,
                    'total_stores' => count($stores)
                ]);
                exit;
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'System error: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
}

//######### AJAX ENDS HERE #########

//######### HEADER BEGINS HERE ######### 
include("../../../assets/template/html-header.php");
include("../../../assets/template/header.php");
//######### HEADER ENDS HERE #########

?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("../../../assets/template/sidemenu.php"); ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Transfer Management</li>
        <li class="breadcrumb-item active">⚡ REAL TRANSFER EXECUTOR</li>
        <li class="breadcrumb-menu d-md-down-none">
          <?php include('../../../assets/template/quick-product-search.php'); ?>
        </li>
      </ol>
      
      <div class="container-fluid">
        <div class="animated fadeIn">
          
          <!-- SYSTEM OVERVIEW -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="card">
                <div class="card-header bg-dark text-white">
                  <h4 class="mb-0">🚀 REAL TRANSFER EXECUTION ENGINE</h4>
                  <small>Actual transfer execution with container details, weights, and quantities</small>
                </div>
              </div>
            </div>
          </div>

          <!-- LIVE STATISTICS -->
          <div class="row mb-4">
            <div class="col-md-2">
              <div class="card bg-primary text-white text-center">
                <div class="card-body p-2">
                  <h4 class="mb-0" id="total-transfers">0</h4>
                  <small>Total Transfers</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-success text-white text-center">
                <div class="card-body p-2">
                  <h4 class="mb-0" id="active-transfers">0</h4>
                  <small>Active</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-danger text-white text-center">
                <div class="card-body p-2">
                  <h4 class="mb-0" id="low-stock-items">0</h4>
                  <small>Low Stock</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-warning text-white text-center">
                <div class="card-body p-2">
                  <h4 class="mb-0" id="overstocked-items">0</h4>
                  <small>Overstocked</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-info text-white text-center">
                <div class="card-body p-2">
                  <h4 class="mb-0" id="total-inventory">0</h4>
                  <small>Total Stock</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-secondary text-white text-center">
                <div class="card-body p-2">
                  <h4 class="mb-0" id="uncategorized-products">0</h4>
                  <small>Uncategorized</small>
                </div>
              </div>
            </div>
          </div>

          <!-- TRANSFER EXECUTION CONTROLS -->
          <div class="row mb-4">
            <div class="col-md-6">
              <div class="card">
                <div class="card-header bg-danger text-white">
                  <h5><i class="fa fa-rocket"></i> TRANSFER EXECUTION</h5>
                </div>
                <div class="card-body">
                  
                  <div class="form-group">
                    <label><strong>Execution Mode:</strong></label>
                    <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                      <label class="btn btn-outline-warning active">
                        <input type="radio" name="execution-mode" value="simulate" checked> 
                        <i class="fa fa-eye"></i> DRY RUN / SIMULATE
                      </label>
                      <label class="btn btn-outline-danger">
                        <input type="radio" name="execution-mode" value="execute"> 
                        <i class="fa fa-fire"></i> LIVE EXECUTION
                      </label>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label><strong>Transfer Type:</strong></label>
                    <select class="form-control" id="transfer-type" onchange="updateTransferOptions()">
                      <option value="auto">🤖 Automatic (AI Powered)</option>
                      <option value="all_stores">🏪 All Stores Network</option>
                      <option value="new_store">🆕 New Store Setup</option>
                      <option value="critical_only">🚨 Critical Items Only</option>
                      <option value="manual">👤 Manual Selection</option>
                    </select>
                  </div>
                  
                  <!-- Dynamic options based on transfer type -->
                  <div id="transfer-options">
                    <div class="form-group" id="store-selector" style="display:none;">
                      <label><strong>Select Store:</strong></label>
                      <select class="form-control" id="target-store">
                        <option value="">Loading stores...</option>
                      </select>
                    </div>
                    
                    <div class="form-group" id="multi-store-selector" style="display:none;">
                      <label><strong>Select Stores:</strong></label>
                      <div id="store-checkboxes" class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                        Loading stores...
                      </div>
                    </div>
                    
                    <div class="form-group" id="priority-options" style="display:none;">
                      <label><strong>Priority Threshold:</strong></label>
                      <select class="form-control" id="priority-threshold">
                        <option value="high">High Priority Only</option>
                        <option value="critical">Critical Only</option>
                        <option value="emergency">Emergency Only</option>
                      </select>
                    </div>
                  </div>
                  
                  <button class="btn btn-lg btn-danger btn-block" onclick="executeRealTransfer()">
                    <i class="fa fa-rocket"></i> EXECUTE REAL TRANSFER
                  </button>
                  
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="card">
                <div class="card-header bg-info text-white">
                  <h5><i class="fa fa-history"></i> ACTIVE TRANSFERS</h5>
                </div>
                <div class="card-body">
                  <div id="active-transfers-list">
                    <div class="text-center text-muted">
                      <i class="fa fa-spinner fa-spin"></i> Loading active transfers...
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- EXECUTION RESULTS -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="card">
                <div class="card-header bg-success text-white">
                  <h5><i class="fa fa-chart-bar"></i> EXECUTION RESULTS</h5>
                </div>
                <div class="card-body">
                  <div id="execution-results">
                    <div class="text-center text-muted py-4">
                      <i class="fa fa-play fa-3x mb-3"></i>
                      <h5>Ready for Transfer Execution</h5>
                      <p>Click "EXECUTE REAL TRANSFER" to run your transfer engine and see container details</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- TRANSFER HISTORY -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h5><i class="fa fa-list"></i> TRANSFER HISTORY</h5>
                  <div class="float-right">
                    <button class="btn btn-sm btn-outline-primary" onclick="loadTransferHistory()">
                      <i class="fa fa-sync"></i> Refresh
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-striped" id="transfer-history-table">
                      <thead>
                        <tr>
                          <th>Transfer ID</th>
                          <th>From → To</th>
                          <th>Status</th>
                          <th>Products</th>
                          <th>Quantity</th>
                          <th>Created</th>
                          <th>Completed</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td colspan="7" class="text-center">
                            <i class="fa fa-spinner fa-spin"></i> Loading transfer history...
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </main>
    <?php include("../../../assets/template/personalisation-menu.php"); ?>
  </div>

<!-- REAL BUSINESS STYLING -->
<style>
.card {
  border: none;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  margin-bottom: 1rem;
}

.card-header {
  font-weight: 600;
  border-bottom: 2px solid rgba(255,255,255,0.2);
}

.btn-group-toggle .btn {
  border-radius: 4px !important;
  margin: 0 2px;
}

.btn-outline-warning {
  border: 2px solid #ffc107;
  color: #ffc107;
}

.btn-outline-danger {
  border: 2px solid #dc3545;
  color: #dc3545;
}

.execution-result-box {
  background: #f8f9fa;
  border-left: 4px solid #28a745;
  padding: 20px;
  margin-bottom: 15px;
  border-radius: 4px;
}

.container-info {
  background: #e3f2fd;
  border: 1px solid #2196f3;
  border-radius: 4px;
  padding: 15px;
  margin-bottom: 10px;
}

.transfer-warning {
  background: #fff3cd;
  border: 1px solid #ffc107;
  color: #856404;
  padding: 15px;
  border-radius: 4px;
  margin-bottom: 15px;
}
</style>

<!-- REAL JAVASCRIPT EXECUTION -->
<script>
$(document).ready(function() {
    loadSystemOverview();
    loadActiveTransfers();
    loadTransferHistory();
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        loadSystemOverview();
        loadActiveTransfers();
    }, 30000);
});

function loadSystemOverview() {
    $.post('', {
        ajax_action: 'get_system_overview'
    }, function(response) {
        if (response.success) {
            const t = response.stats.transfers;
            const i = response.stats.inventory;
            const c = response.stats.categorization;
            
            $('#total-transfers').text(t.total_transfers || 0);
            $('#active-transfers').text(t.active_transfers || 0);
            $('#low-stock-items').text(i.low_stock_items || 0);
            $('#overstocked-items').text(i.overstocked_items || 0);
            $('#total-inventory').text((i.total_inventory || 0).toLocaleString());
            $('#uncategorized-products').text(c.uncategorized_products || 0);
        }
    }, 'json');
}

function loadActiveTransfers() {
    $.post('', {
        ajax_action: 'get_active_transfers'
    }, function(response) {
        if (response.success) {
            let html = '';
            
            if (response.active_transfers.length === 0) {
                html = '<div class="text-muted text-center">No active transfers</div>';
            } else {
                response.active_transfers.forEach(function(transfer) {
                    html += `
                        <div class="border-bottom pb-2 mb-2">
                            <strong>#${transfer.transfer_id}</strong><br>
                            <small>${transfer.from_name} → ${transfer.to_name}</small><br>
                            <span class="badge badge-info">${transfer.status}</span>
                            <span class="badge badge-secondary">${transfer.product_count} items</span>
                        </div>
                    `;
                });
            }
            
            $('#active-transfers-list').html(html);
        }
    }, 'json');
}

function loadTransferHistory() {
    $.post('', {
        ajax_action: 'get_transfer_history'
    }, function(response) {
        if (response.success) {
            const tbody = $('#transfer-history-table tbody');
            tbody.empty();
            
            if (response.transfers.length === 0) {
                tbody.html('<tr><td colspan="7" class="text-center text-muted">No transfer history found</td></tr>');
                return;
            }
            
            response.transfers.forEach(function(transfer) {
                const statusClass = transfer.status === 'completed' ? 'success' : 
                                  transfer.status === 'active' ? 'info' : 'warning';
                
                tbody.append(`
                    <tr>
                        <td><strong>#${transfer.transfer_id}</strong></td>
                        <td>
                            <strong>${transfer.from_name}</strong><br>
                            <i class="fa fa-arrow-right"></i> ${transfer.to_name}
                        </td>
                        <td><span class="badge badge-${statusClass}">${transfer.status}</span></td>
                        <td>${transfer.product_lines || 0}</td>
                        <td>${transfer.total_quantity || 0}</td>
                        <td>${new Date(transfer.date_created).toLocaleDateString()}</td>
                        <td>${transfer.transfer_completed ? new Date(transfer.transfer_completed).toLocaleDateString() : '-'}</td>
                    </tr>
                `);
            });
        }
    }, 'json');
}

function executeRealTransfer() {
    const mode = $('input[name="execution-mode"]:checked').val();
    const type = $('#transfer-type').val();
    
    if (mode === 'execute') {
        if (!confirm('⚠️ WARNING: This will execute REAL transfers in your system!\n\nAre you absolutely sure you want to proceed?')) {
            return;
        }
    }
    
    $('#execution-results').html(`
        <div class="text-center">
            <i class="fa fa-cogs fa-spin fa-3x text-primary mb-3"></i>
            <h4>${mode === 'simulate' ? 'Running DRY RUN' : 'EXECUTING LIVE TRANSFERS'}</h4>
            <p>Calling your actual transfer engine...</p>
            <div class="progress">
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
            </div>
        </div>
    `);
    
    $.post('', {
        ajax_action: 'execute_real_transfer',
        mode: mode,
        type: type
    }, function(response) {
        if (response.success) {
            displayExecutionResults(response);
            // Refresh active transfers and history
            loadActiveTransfers();
            loadTransferHistory();
            loadSystemOverview();
        } else {
            $('#execution-results').html(`
                <div class="alert alert-danger">
                    <strong>Execution Failed:</strong> ${response.error}
                </div>
            `);
        }
    }, 'json').fail(function() {
        $('#execution-results').html(`
            <div class="alert alert-danger">
                <strong>System Error:</strong> Could not connect to transfer engine
            </div>
        `);
    });
}

function displayExecutionResults(data) {
    let html = `
        <div class="execution-result-box">
            <h5><i class="fa fa-check-circle text-success"></i> Transfer Engine ${data.mode === 'simulate' ? 'Simulation' : 'Execution'} Complete</h5>
            <div class="row">
                <div class="col-md-3">
                    <strong>Mode:</strong> ${data.mode.toUpperCase()}<br>
                    <strong>Transfers Proposed:</strong> ${data.transfers_proposed}
                </div>
                <div class="col-md-3">
                    <strong>Total Lines:</strong> ${data.total_lines}<br>
                    <strong>Containers:</strong> ${data.containers.length}
                </div>
                <div class="col-md-6">
                    <strong>Execution Time:</strong> ${data.timestamp}
                </div>
            </div>
        </div>
    `;
    
    if (data.transfers_proposed === 0) {
        html += `
            <div class="alert alert-info">
                <strong><i class="fa fa-info-circle"></i> No Transfers Needed</strong><br>
                The system analysis found no products requiring transfer at this time.
                All outlets appear to be properly stocked according to their reorder points.
            </div>
        `;
    } else {
        // Show container details like your working system
        if (data.containers.length > 0) {
            html += '<div class="row">';
            data.containers.forEach(function(container, index) {
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="container-info">
                            <h6><i class="fa fa-box"></i> Container ${index + 1}</h6>
                            <strong>Type:</strong> ${container.type}<br>
                            <strong>Weight:</strong> ${container.weight}${container.weight_unit}<br>
                            <strong>Quantity:</strong> ${container.quantity}<br>
                            <strong>Lines:</strong> ${container.lines}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        }
        
        // Show actual transfer records if executed
        if (data.mode === 'execute' && data.actual_transfers.length > 0) {
            html += `
                <div class="alert alert-success">
                    <strong><i class="fa fa-rocket"></i> LIVE TRANSFERS CREATED:</strong><br>
            `;
            data.actual_transfers.forEach(function(transfer) {
                html += `Transfer #${transfer.transfer_id}: ${transfer.outlet_from} → ${transfer.outlet_to} (${transfer.product_count} products)<br>`;
            });
            html += '</div>';
        }
        
        if (data.mode === 'simulate') {
            html += `
                <div class="transfer-warning">
                    <strong><i class="fa fa-exclamation-triangle"></i> SIMULATION MODE:</strong>
                    These are projected results. Switch to "LIVE EXECUTION" to create actual transfers.
                </div>
            `;
        }
    }
    
    // Raw output toggle for debugging
    html += `
        <div class="mt-3">
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleRawOutput()">
                <i class="fa fa-code"></i> Show Raw Engine Output
            </button>
            <div id="raw-output" style="display:none;" class="mt-2">
                <pre class="bg-light p-3 small">${data.raw_output}</pre>
            </div>
        </div>
    `;
    
    $('#execution-results').html(html);
}

function toggleRawOutput() {
    $('#raw-output').toggle();
}
</script>

<?php include("../../../assets/template/html-footer.php"); ?>
<?php include("../../../assets/template/footer.php"); ?>
