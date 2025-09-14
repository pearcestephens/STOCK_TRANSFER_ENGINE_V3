<?php
// Includes ALL CONFIG/DB & All asset/functions files - DO NOT INCLUDE ANYTHING ELSE
include("../../../assets/functions/config.php"); 
// Includes ALL CONFIG/DB & All asset/functions files - DO NOT INCLUDE ANYTHING ELSE


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
            case 'get_transfer_stats':
                // Get REAL transfer statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_transfers,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_transfers,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transfers,
                        SUM(CASE WHEN DATE(date_created) = CURDATE() THEN 1 ELSE 0 END) as today_transfers
                    FROM stock_transfers 
                    WHERE deleted_at IS NULL
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get inventory levels
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_products,
                        SUM(inventory_level) as total_inventory,
                        COUNT(CASE WHEN inventory_level < reorder_point THEN 1 END) as low_stock_items
                    FROM vend_inventory 
                    WHERE deleted_at IS NULL
                ");
                $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'transfers' => $stats,
                    'inventory' => $inventory
                ]);
                exit;
                
            case 'get_outlet_data':
                // Get REAL outlet information
                $stmt = $pdo->query("
                    SELECT 
                        outlet_id,
                        outlet_name,
                        COUNT(vi.product_id) as product_count,
                        SUM(vi.inventory_level) as total_stock
                    FROM vend_outlets vo
                    LEFT JOIN vend_inventory vi ON vo.outlet_id = vi.outlet_id AND vi.deleted_at IS NULL
                    WHERE vo.deleted_at IS NULL
                    GROUP BY vo.outlet_id, vo.outlet_name
                    ORDER BY vo.outlet_name
                ");
                $outlets = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'outlets' => $outlets
                ]);
                exit;
                
            case 'run_transfer_analysis':
                // REAL transfer analysis - not demo bullshit
                $mode = $_POST['mode'] ?? 'network_scan';
                
                // Get products that need redistribution
                $stmt = $pdo->query("
                    SELECT 
                        p.product_name,
                        vi.outlet_id,
                        vo.outlet_name,
                        vi.inventory_level,
                        vi.reorder_point,
                        (vi.inventory_level - vi.reorder_point) as surplus_deficit
                    FROM vend_inventory vi
                    JOIN vend_products p ON vi.product_id = p.product_id
                    JOIN vend_outlets vo ON vi.outlet_id = vo.outlet_id
                    WHERE vi.deleted_at IS NULL 
                    AND p.deleted_at IS NULL 
                    AND vo.deleted_at IS NULL
                    AND (vi.inventory_level < vi.reorder_point OR vi.inventory_level > (vi.reorder_point * 3))
                    ORDER BY ABS(vi.inventory_level - vi.reorder_point) DESC
                    LIMIT 50
                ");
                $analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'analysis_mode' => $mode,
                    'recommendations' => $analysis,
                    'total_items' => count($analysis),
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
}

//######### AJAX ENDS HERE #########

//######### HEADER BEGINS HERE ######### -->

include("../../../assets/template/html-header.php");
include("../../../assets/template/header.php");

//######### HEADER ENDS HERE ######### -->

?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("../../../assets/template/sidemenu.php"); ?>
    <main class="main">
      <!-- Breadcrumb -->
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">
          <a href="#">Transfer Management</a>
        </li>
        <li class="breadcrumb-item active">REAL Transfer Dashboard</li>
        <!-- Breadcrumb Menu-->
        <li class="breadcrumb-menu d-md-down-none">
          <?php include('../../../assets/template/quick-product-search.php'); ?>
        </li>
      </ol>
      <div class="container-fluid">
        <div class="animated fadeIn">
          <div class="row">
            <div class="col ">
              <div class="card">
                <div class="card-header">
                  <h4 class="card-title mb-0">🚀 REAL Transfer Dashboard - Live Data Analytics</h4>
                  <div class="small text-muted">Real-time transfer analysis with actual database connections - No demo bullshit</div>
                </div>
                <div class="card-body">
                  <div class="cis-content">
                      
                      <!-- REAL DASHBOARD CONTENT STARTS HERE -->
                      
                      <!-- Live Statistics Row -->
                      <div class="row mb-4">
                          <div class="col-md-3">
                              <div class="card bg-primary text-white">
                                  <div class="card-body">
                                      <div class="d-flex justify-content-between">
                                          <div>
                                              <h3 class="mb-0" id="total-transfers">Loading...</h3>
                                              <p class="mb-0">Total Transfers</p>
                                          </div>
                                          <div class="align-self-center">
                                              <i class="fa fa-exchange-alt fa-2x"></i>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                          
                          <div class="col-md-3">
                              <div class="card bg-success text-white">
                                  <div class="card-body">
                                      <div class="d-flex justify-content-between">
                                          <div>
                                              <h3 class="mb-0" id="active-transfers">Loading...</h3>
                                              <p class="mb-0">Active Transfers</p>
                                          </div>
                                          <div class="align-self-center">
                                              <i class="fa fa-spinner fa-2x"></i>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                          
                          <div class="col-md-3">
                              <div class="card bg-warning text-white">
                                  <div class="card-body">
                                      <div class="d-flex justify-content-between">
                                          <div>
                                              <h3 class="mb-0" id="low-stock-items">Loading...</h3>
                                              <p class="mb-0">Low Stock Items</p>
                                          </div>
                                          <div class="align-self-center">
                                              <i class="fa fa-exclamation-triangle fa-2x"></i>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                          
                          <div class="col-md-3">
                              <div class="card bg-info text-white">
                                  <div class="card-body">
                                      <div class="d-flex justify-content-between">
                                          <div>
                                              <h3 class="mb-0" id="total-inventory">Loading...</h3>
                                              <p class="mb-0">Total Inventory</p>
                                          </div>
                                          <div class="align-self-center">
                                              <i class="fa fa-boxes fa-2x"></i>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                      
                      <!-- Control Panel -->
                      <div class="row mb-4">
                          <div class="col-md-12">
                              <div class="card">
                                  <div class="card-header">
                                      <h5><i class="fa fa-cogs"></i> Transfer Analysis Controls</h5>
                                  </div>
                                  <div class="card-body">
                                      <div class="row">
                                          <div class="col-md-4">
                                              <div class="form-group">
                                                  <label>Analysis Mode:</label>
                                                  <select class="form-control" id="analysis-mode">
                                                      <option value="network_scan">Full Network Scan</option>
                                                      <option value="critical_only">Critical Items Only</option>
                                                      <option value="surplus_analysis">Surplus Analysis</option>
                                                      <option value="deficit_analysis">Deficit Analysis</option>
                                                  </select>
                                              </div>
                                          </div>
                                          <div class="col-md-4">
                                              <div class="form-group">
                                                  <label>Priority Level:</label>
                                                  <select class="form-control" id="priority-level">
                                                      <option value="high">High Priority</option>
                                                      <option value="medium">Medium Priority</option>
                                                      <option value="low">Low Priority</option>
                                                      <option value="all">All Levels</option>
                                                  </select>
                                              </div>
                                          </div>
                                          <div class="col-md-4">
                                              <div class="form-group">
                                                  <label>&nbsp;</label>
                                                  <button class="btn btn-primary btn-block" onclick="runRealAnalysis()">
                                                      <i class="fa fa-play"></i> Run REAL Analysis
                                                  </button>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                      
                      <!-- Outlets Overview -->
                      <div class="row mb-4">
                          <div class="col-md-12">
                              <div class="card">
                                  <div class="card-header">
                                      <h5><i class="fa fa-store"></i> Live Outlet Status</h5>
                                  </div>
                                  <div class="card-body">
                                      <div class="table-responsive">
                                          <table class="table table-striped" id="outlets-table">
                                              <thead>
                                                  <tr>
                                                      <th>Outlet ID</th>
                                                      <th>Outlet Name</th>
                                                      <th>Products</th>
                                                      <th>Total Stock</th>
                                                      <th>Status</th>
                                                  </tr>
                                              </thead>
                                              <tbody>
                                                  <tr>
                                                      <td colspan="5" class="text-center">
                                                          <i class="fa fa-spinner fa-spin"></i> Loading real outlet data...
                                                      </td>
                                                  </tr>
                                              </tbody>
                                          </table>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                      
                      <!-- Analysis Results -->
                      <div class="row">
                          <div class="col-md-12">
                              <div class="card">
                                  <div class="card-header">
                                      <h5><i class="fa fa-chart-line"></i> Transfer Recommendations</h5>
                                  </div>
                                  <div class="card-body">
                                      <div id="analysis-results">
                                          <div class="text-center text-muted py-5">
                                              <i class="fa fa-info-circle fa-3x mb-3"></i>
                                              <h5>Ready for Analysis</h5>
                                              <p>Click "Run REAL Analysis" to see transfer recommendations based on actual inventory data</p>
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                      
                      <!-- REAL DASHBOARD CONTENT ENDS HERE -->
                      
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!--/.row-->
        </div>
      </div>
    </main>
    <!-- ######### FOOTER BEGINS HERE ######### -->
    <?php include("../../../assets/template/personalisation-menu.php"); ?>
  </div>

   <!-- ######### CSS BEGINS HERE ######### -->
   <!-- CIS Content Framework - Container-scoped CSS that doesn't override globals -->
   <link rel="stylesheet" href="../../../assets/css/cis-content-framework.css">
   
   <style>
   .cis-content .card {
       margin-bottom: 1.5rem;
   }
   
   .cis-content .bg-primary { background-color: #007bff !important; }
   .cis-content .bg-success { background-color: #28a745 !important; }
   .cis-content .bg-warning { background-color: #ffc107 !important; }
   .cis-content .bg-info { background-color: #17a2b8 !important; }
   
   .cis-content .text-white { color: #fff !important; }
   
   .cis-content .analysis-item {
       background: #f8f9fa;
       border-left: 4px solid #007bff;
       padding: 15px;
       margin-bottom: 10px;
       border-radius: 4px;
   }
   
   .cis-content .analysis-item.deficit {
       border-left-color: #dc3545;
   }
   
   .cis-content .analysis-item.surplus {
       border-left-color: #28a745;
   }
   
   .cis-content .status-online {
       width: 12px;
       height: 12px;
       background: #28a745;
       border-radius: 50%;
       display: inline-block;
       margin-right: 5px;
   }
   
   .cis-content .loading-spinner {
       text-align: center;
       padding: 20px;
   }
   
   .cis-content .metric-card {
       transition: transform 0.2s ease;
   }
   
   .cis-content .metric-card:hover {
       transform: translateY(-2px);
   }
   </style>
   
   <!-- ######### CSS ENDS HERE ######### -->


  <!-- ######### JAVASCRIPT BEGINS HERE ######### -->
  
  <script>
  // REAL JavaScript - No demo bullshit
  
  $(document).ready(function() {
      loadRealStats();
      loadOutletData();
      
      // Refresh data every 30 seconds
      setInterval(function() {
          loadRealStats();
          loadOutletData();
      }, 30000);
  });
  
  function loadRealStats() {
      $.post('', {
          ajax_action: 'get_transfer_stats'
      }, function(response) {
          if (response.success) {
              $('#total-transfers').text(response.transfers.total_transfers || 0);
              $('#active-transfers').text(response.transfers.active_transfers || 0);
              $('#low-stock-items').text(response.inventory.low_stock_items || 0);
              $('#total-inventory').text((response.inventory.total_inventory || 0).toLocaleString());
          } else {
              console.error('Failed to load stats:', response.error);
          }
      }, 'json').fail(function() {
          console.error('AJAX request failed for stats');
      });
  }
  
  function loadOutletData() {
      $.post('', {
          ajax_action: 'get_outlet_data'
      }, function(response) {
          if (response.success) {
              var tbody = $('#outlets-table tbody');
              tbody.empty();
              
              if (response.outlets.length === 0) {
                  tbody.append('<tr><td colspan="5" class="text-center text-muted">No outlets found</td></tr>');
                  return;
              }
              
              response.outlets.forEach(function(outlet) {
                  var statusClass = outlet.total_stock > 0 ? 'success' : 'warning';
                  var statusText = outlet.total_stock > 0 ? 'Active' : 'Low Stock';
                  
                  tbody.append(`
                      <tr>
                          <td><strong>${outlet.outlet_id}</strong></td>
                          <td>${outlet.outlet_name}</td>
                          <td>${outlet.product_count || 0}</td>
                          <td>${(outlet.total_stock || 0).toLocaleString()}</td>
                          <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                      </tr>
                  `);
              });
          } else {
              $('#outlets-table tbody').html('<tr><td colspan="5" class="text-center text-danger">Error: ' + response.error + '</td></tr>');
          }
      }, 'json').fail(function() {
          $('#outlets-table tbody').html('<tr><td colspan="5" class="text-center text-danger">AJAX request failed</td></tr>');
      });
  }
  
  function runRealAnalysis() {
      var mode = $('#analysis-mode').val();
      var priority = $('#priority-level').val();
      
      $('#analysis-results').html('<div class="loading-spinner"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Running REAL analysis...</div>');
      
      $.post('', {
          ajax_action: 'run_transfer_analysis',
          mode: mode,
          priority: priority
      }, function(response) {
          if (response.success) {
              displayAnalysisResults(response);
          } else {
              $('#analysis-results').html('<div class="alert alert-danger">Analysis failed: ' + response.error + '</div>');
          }
      }, 'json').fail(function() {
          $('#analysis-results').html('<div class="alert alert-danger">AJAX request failed for analysis</div>');
      });
  }
  
  function displayAnalysisResults(data) {
      var html = `
          <div class="mb-3">
              <h6><i class="fa fa-info-circle"></i> Analysis Complete</h6>
              <p><strong>Mode:</strong> ${data.analysis_mode} | <strong>Items Found:</strong> ${data.total_items} | <strong>Time:</strong> ${data.timestamp}</p>
          </div>
      `;
      
      if (data.recommendations.length === 0) {
          html += '<div class="alert alert-info">No transfer recommendations found. All outlets appear to be properly stocked.</div>';
      } else {
          html += '<div class="row">';
          
          data.recommendations.forEach(function(item) {
              var itemClass = item.surplus_deficit < 0 ? 'deficit' : 'surplus';
              var statusText = item.surplus_deficit < 0 ? 'NEEDS STOCK' : 'SURPLUS';
              var statusColor = item.surplus_deficit < 0 ? 'danger' : 'success';
              
              html += `
                  <div class="col-md-6 mb-3">
                      <div class="analysis-item ${itemClass}">
                          <div class="d-flex justify-content-between align-items-start">
                              <div>
                                  <h6 class="mb-1">${item.product_name}</h6>
                                  <small class="text-muted">${item.outlet_name}</small>
                              </div>
                              <span class="badge badge-${statusColor}">${statusText}</span>
                          </div>
                          <div class="mt-2">
                              <small>
                                  <strong>Current:</strong> ${item.inventory_level} | 
                                  <strong>Reorder Point:</strong> ${item.reorder_point} | 
                                  <strong>Difference:</strong> ${item.surplus_deficit}
                              </small>
                          </div>
                      </div>
                  </div>
              `;
          });
          
          html += '</div>';
      }
      
      $('#analysis-results').html(html);
  }
  
  </script>
  
  <!-- ######### JAVASCRIPT ENDS HERE ######### -->

  <?php include("../../../assets/template/html-footer.php"); ?>
  <?php include("../../../assets/template/footer.php"); ?>
  <!-- ######### FOOTER ENDS HERE ######### -->
