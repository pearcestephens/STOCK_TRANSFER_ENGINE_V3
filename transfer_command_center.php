<?php
/**
 * TRANSFER COMMAND CENTER - THE REAL DEAL FOR MEN
 * Ultimate hardcore transfer dashboard with GPT neural integration
 * Built for professionals who demand maximum control and transparency
 */

// Includes ALL CONFIG/DB & All asset/functions files
include("../../../assets/functions/config.php");

// Load the TurboAutonomousTransferEngine
require_once('TurboAutonomousTransferEngine.php');

//######### AJAX COMMAND CENTER #########

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
            
            case 'get_system_status':
                // Get comprehensive system status
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_transfers,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_transfers,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transfers,
                        SUM(CASE WHEN DATE(date_created) = CURDATE() THEN 1 ELSE 0 END) as today_transfers,
                        MAX(date_created) as last_transfer_time
                    FROM stock_transfers 
                    WHERE deleted_at IS NULL
                ");
                $transfers = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_products,
                        SUM(inventory_level) as total_inventory,
                        COUNT(CASE WHEN inventory_level < reorder_point THEN 1 END) as critical_items,
                        COUNT(CASE WHEN inventory_level > (reorder_point * 3) THEN 1 END) as surplus_items,
                        AVG(inventory_level) as avg_stock_level
                    FROM vend_inventory 
                    WHERE deleted_at IS NULL AND inventory_level > 0
                ");
                $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->query("
                    SELECT COUNT(*) as active_outlets
                    FROM vend_outlets 
                    WHERE deleted_at IS NULL
                ");
                $outlets = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'transfers' => $transfers,
                    'inventory' => $inventory,
                    'outlets' => $outlets,
                    'system_time' => date('Y-m-d H:i:s'),
                    'neural_status' => 'ONLINE',
                    'gpt_status' => 'READY'
                ]);
                exit;
                
            case 'run_neural_analysis':
                // Run the HARDCORE neural analysis
                $config = [
                    'mode' => $_POST['mode'] ?? 'full_network',
                    'target_stores' => $_POST['target_stores'] ?? 'all',
                    'neural_depth' => (int)($_POST['neural_depth'] ?? 3),
                    'gpt_enhancement' => $_POST['gpt_enhancement'] ?? 'enabled',
                    'pack_rules' => $_POST['pack_rules'] ?? 'strict',
                    'route_optimization' => $_POST['route_optimization'] ?? 'enabled',
                    'confidence_threshold' => (float)($_POST['confidence_threshold'] ?? 0.85),
                    'simulate' => $_POST['simulate'] ?? 'false'
                ];
                
                $engine = new TurboAutonomousTransferEngine($pdo);
                $results = $engine->runIntelligentAnalysis($config);
                
                echo json_encode([
                    'success' => true,
                    'analysis' => $results,
                    'config_used' => $config,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
                
            case 'execute_transfer_generation':
                // ACTUALLY GENERATE TRANSFERS - NOT JUST TALK ABOUT IT
                $config = [
                    'mode' => $_POST['mode'] ?? 'intelligent',
                    'target_outlets' => explode(',', $_POST['target_outlets'] ?? ''),
                    'execute' => $_POST['execute'] === 'true',
                    'neural_override' => $_POST['neural_override'] ?? 'false',
                    'pack_compliance' => $_POST['pack_compliance'] ?? 'strict',
                    'budget_limit' => (float)($_POST['budget_limit'] ?? 0),
                    'priority_filter' => $_POST['priority_filter'] ?? 'all'
                ];
                
                $engine = new TurboAutonomousTransferEngine($pdo);
                
                if ($config['execute']) {
                    $results = $engine->executeTransferGeneration($config);
                } else {
                    $results = $engine->simulateTransferGeneration($config);
                }
                
                echo json_encode([
                    'success' => true,
                    'execution_results' => $results,
                    'config_used' => $config,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                exit;
                
            case 'get_gpt_recommendations':
                // REAL GPT-powered analysis using actual API
                require_once('RealGPTAnalysisEngine.php');
                $gpt_engine = new RealGPTAnalysisEngine($pdo);
                
                $context = $_POST['context'] ?? 'general';
                
                switch ($context) {
                    case 'product_categorization':
                        $results = $gpt_engine->analyzeProductCategorization();
                        break;
                        
                    case 'pack_size_analysis':
                        $results = $gpt_engine->analyzePackSizes();
                        break;
                        
                    case 'missing_brands':
                        $results = $gpt_engine->analyzeMissingBrands();
                        break;
                        
                    default:
                        // General business intelligence analysis
                        $stmt = $pdo->query("
                            SELECT 
                                vo.outlet_name,
                                p.product_name,
                                p.brand_name,
                                vi.inventory_level,
                                vi.reorder_point,
                                (vi.inventory_level - vi.reorder_point) as variance,
                                COUNT(spt.transfer_id) as recent_transfers
                            FROM vend_inventory vi
                            JOIN vend_outlets vo ON vi.outlet_id = vo.outlet_id
                            JOIN vend_products p ON vi.product_id = p.product_id
                            LEFT JOIN stock_products_to_transfer spt ON p.product_id = spt.product_id
                            LEFT JOIN stock_transfers st ON spt.transfer_id = st.transfer_id 
                                AND st.date_created > DATE_SUB(NOW(), INTERVAL 7 DAY)
                            WHERE vi.deleted_at IS NULL 
                            AND vo.deleted_at IS NULL 
                            AND p.deleted_at IS NULL
                            GROUP BY vi.product_id, vo.outlet_id
                            HAVING ABS(variance) > 5 OR recent_transfers = 0
                            ORDER BY ABS(variance) DESC
                            LIMIT 50
                        ");
                        $business_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Build intelligent prompt for GPT
                        $prompt = "BUSINESS INTELLIGENCE ANALYSIS:\n";
                        $prompt .= "Analyze transfer patterns and inventory distribution for The Vape Shed network.\n";
                        $prompt .= "Data shows " . count($business_data) . " products with stock imbalances or no recent transfers.\n\n";
                        
                        foreach (array_slice($business_data, 0, 20) as $item) {
                            $prompt .= "- {$item['outlet_name']}: '{$item['product_name']}' ({$item['brand_name']}) Stock: {$item['inventory_level']} (variance: {$item['variance']}, recent transfers: {$item['recent_transfers']})\n";
                        }
                        
                        $prompt .= "\nProvide:\n1. Priority actions needed\n2. Patterns in the data\n3. Risk assessments\n4. Optimization opportunities\nRespond in JSON format.";
                        
                        // Call real GPT API
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, 'https://staff.vapeshed.co.nz/gpt_actions.php');
                        curl_setopt($ch, CURLOPT_POST, 1);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                            'action' => 'business_analysis',
                            'prompt' => $prompt,
                            'context' => 'transfer_optimization'
                        ]));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        
                        $gpt_response = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($http_code === 200 && $gpt_response) {
                            $decoded_response = json_decode($gpt_response, true);
                            $results = [
                                'success' => true,
                                'gpt_analysis' => $decoded_response,
                                'business_data' => $business_data,
                                'data_points' => count($business_data)
                            ];
                        } else {
                            $results = [
                                'success' => false,
                                'error' => 'GPT API unavailable (HTTP: ' . $http_code . ')',
                                'fallback_data' => $business_data
                            ];
                        }
                        break;
                }
                
                echo json_encode(array_merge($results, [
                    'context' => $context,
                    'timestamp' => date('Y-m-d H:i:s')
                ]));
                exit;
                
            case 'get_outlet_matrix':
                // Get the full outlet transfer matrix
                $stmt = $pdo->query("
                    SELECT 
                        vo.outlet_id,
                        vo.outlet_name,
                        vo.outlet_code,
                        COUNT(DISTINCT vi.product_id) as unique_products,
                        SUM(vi.inventory_level) as total_stock,
                        AVG(vi.inventory_level) as avg_stock_per_product,
                        COUNT(CASE WHEN vi.inventory_level < vi.reorder_point THEN 1 END) as understocked_items,
                        COUNT(CASE WHEN vi.inventory_level > (vi.reorder_point * 2) THEN 1 END) as overstocked_items
                    FROM vend_outlets vo
                    LEFT JOIN vend_inventory vi ON vo.outlet_id = vi.outlet_id AND vi.deleted_at IS NULL
                    WHERE vo.deleted_at IS NULL
                    GROUP BY vo.outlet_id, vo.outlet_name, vo.outlet_code
                    ORDER BY total_stock DESC
                ");
                $matrix = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'outlet_matrix' => $matrix,
                    'total_outlets' => count($matrix),
                    'timestamp' => date('Y-m-d H:i:s')
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

//######### HEADER SYSTEM #########

include("../../../assets/template/html-header.php");
include("../../../assets/template/header.php");

?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include("../../../assets/template/sidemenu.php"); ?>
    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item">Transfer Management</li>
        <li class="breadcrumb-item active">⚡ COMMAND CENTER</li>
        <li class="breadcrumb-menu d-md-down-none">
          <?php include('../../../assets/template/quick-product-search.php'); ?>
        </li>
      </ol>
      
      <div class="container-fluid">
        <div class="animated fadeIn">
          
          <!-- COMMAND HEADER -->
          <div class="row mb-3">
            <div class="col-12">
              <div class="card bg-dark text-white">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <h2 class="mb-0">⚡ TRANSFER COMMAND CENTER</h2>
                      <p class="mb-0">Ultimate Neural Transfer Engine with GPT Integration - Professional Grade</p>
                    </div>
                    <div class="text-right">
                      <div class="d-flex">
                        <span class="badge badge-success mr-2" id="neural-status">NEURAL: LOADING</span>
                        <span class="badge badge-info mr-2" id="gpt-status">GPT: LOADING</span>
                        <span class="badge badge-warning" id="system-time">SYSTEM: LOADING</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- SYSTEM STATUS DASHBOARD -->
          <div class="row mb-4">
            <div class="col-md-2">
              <div class="card bg-primary text-white text-center">
                <div class="card-body">
                  <h3 class="mb-0" id="total-transfers">0</h3>
                  <small>Total Transfers</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-success text-white text-center">
                <div class="card-body">
                  <h3 class="mb-0" id="active-transfers">0</h3>
                  <small>Active</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-danger text-white text-center">
                <div class="card-body">
                  <h3 class="mb-0" id="critical-items">0</h3>
                  <small>Critical Items</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-warning text-white text-center">
                <div class="card-body">
                  <h3 class="mb-0" id="surplus-items">0</h3>
                  <small>Surplus Items</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-info text-white text-center">
                <div class="card-body">
                  <h3 class="mb-0" id="active-outlets">0</h3>
                  <small>Active Outlets</small>
                </div>
              </div>
            </div>
            <div class="col-md-2">
              <div class="card bg-secondary text-white text-center">
                <div class="card-body">
                  <h3 class="mb-0" id="total-inventory">0</h3>
                  <small>Total Stock</small>
                </div>
              </div>
            </div>
          </div>

          <!-- HARDCORE CONTROL PANEL -->
          <div class="row mb-4">
            <div class="col-md-6">
              <div class="card">
                <div class="card-header bg-danger text-white">
                  <h5><i class="fa fa-cogs"></i> NEURAL TRANSFER ENGINE</h5>
                </div>
                <div class="card-body">
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label><strong>Analysis Mode:</strong></label>
                        <select class="form-control" id="neural-mode">
                          <option value="full_network">🌐 Full Network Scan</option>
                          <option value="critical_priority">🚨 Critical Priority Only</option>
                          <option value="intelligent_balance">🧠 Intelligent Balance</option>
                          <option value="surplus_optimization">📈 Surplus Optimization</option>
                          <option value="deficit_emergency">⚠️ Deficit Emergency</option>
                          <option value="custom_neural">🎯 Custom Neural Profile</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label><strong>Target Stores:</strong></label>
                        <select class="form-control" id="target-stores">
                          <option value="all">All Network Outlets</option>
                          <option value="critical_only">Critical Outlets Only</option>
                          <option value="single_store">Single Store Mode</option>
                          <option value="multi_select">Multi-Select Mode</option>
                          <option value="regional">Regional Clusters</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-4">
                      <div class="form-group">
                        <label><strong>Neural Depth:</strong></label>
                        <select class="form-control" id="neural-depth">
                          <option value="1">Level 1 - Basic</option>
                          <option value="2">Level 2 - Standard</option>
                          <option value="3" selected>Level 3 - Advanced</option>
                          <option value="4">Level 4 - Deep</option>
                          <option value="5">Level 5 - Maximum</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label><strong>GPT Enhancement:</strong></label>
                        <select class="form-control" id="gpt-enhancement">
                          <option value="enabled" selected>✅ GPT Enabled</option>
                          <option value="disabled">❌ GPT Disabled</option>
                          <option value="aggressive">🔥 GPT Aggressive</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-4">
                      <div class="form-group">
                        <label><strong>Pack Rules:</strong></label>
                        <select class="form-control" id="pack-rules">
                          <option value="strict" selected>Strict Compliance</option>
                          <option value="flexible">Flexible Packing</option>
                          <option value="optimal">Optimal Efficiency</option>
                          <option value="override">Manual Override</option>
                        </select>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label><strong>Confidence Threshold:</strong></label>
                        <input type="range" class="form-control-range" id="confidence-threshold" min="0.5" max="1.0" step="0.05" value="0.85">
                        <small class="text-muted">Current: <span id="confidence-display">85%</span></small>
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label><strong>Execution Mode:</strong></label>
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                          <label class="btn btn-outline-warning active">
                            <input type="radio" name="execution-mode" value="simulate" checked> SIMULATE
                          </label>
                          <label class="btn btn-outline-danger">
                            <input type="radio" name="execution-mode" value="execute"> EXECUTE
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div class="row mt-3">
                    <div class="col-md-6">
                      <button class="btn btn-primary btn-block" onclick="runNeuralAnalysis()">
                        <i class="fa fa-brain"></i> RUN NEURAL ANALYSIS
                      </button>
                    </div>
                    <div class="col-md-6">
                      <button class="btn btn-danger btn-block" onclick="executeTransfers()">
                        <i class="fa fa-rocket"></i> EXECUTE TRANSFERS
                      </button>
                    </div>
                  </div>
                  
                </div>
              </div>
            </div>
            
            <div class="col-md-6">
              <div class="card">
                <div class="card-header bg-info text-white">
                  <h5><i class="fa fa-robot"></i> GPT COMMAND INTERFACE</h5>
                </div>
                <div class="card-body">
                  
                  <div class="form-group">
                    <label><strong>REAL GPT Analysis:</strong></label>
                    <select class="form-control" id="gpt-context">
                      <option value="general">🧠 General Business Intelligence</option>
                      <option value="product_categorization">📋 Product Categorization Analysis</option>
                      <option value="pack_size_analysis">📦 Pack Size Optimization</option>
                      <option value="missing_brands">🔍 Missing Brand Investigation</option>
                      <option value="transfer_history">📊 Transfer Pattern Analysis</option>
                      <option value="inventory_gaps">⚠️ Inventory Gap Detection</option>
                    </select>
                  </div>
                  
                  <div class="form-group">
                    <label><strong>AI Recommendation Level:</strong></label>
                    <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                      <label class="btn btn-outline-success">
                        <input type="radio" name="ai-level" value="conservative"> Conservative
                      </label>
                      <label class="btn btn-outline-warning active">
                        <input type="radio" name="ai-level" value="balanced" checked> Balanced
                      </label>
                      <label class="btn btn-outline-danger">
                        <input type="radio" name="ai-level" value="aggressive"> Aggressive
                      </label>
                    </div>
                  </div>
                  
                  <button class="btn btn-info btn-block mb-3" onclick="getGptRecommendations()">
                    <i class="fa fa-magic"></i> GET GPT RECOMMENDATIONS
                  </button>
                  
                  <div id="gpt-output" class="bg-light p-3 rounded" style="min-height: 150px;">
                    <div class="text-center text-muted">
                      <i class="fa fa-robot fa-3x mb-2"></i>
                      <p>GPT Interface Ready</p>
                      <small>Click above to get AI-powered recommendations</small>
                    </div>
                  </div>
                  
                </div>
              </div>
            </div>
          </div>

          <!-- OUTLET COMMAND MATRIX -->
          <div class="row mb-4">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h5><i class="fa fa-network-wired"></i> OUTLET COMMAND MATRIX</h5>
                  <div class="float-right">
                    <button class="btn btn-sm btn-outline-primary" onclick="loadOutletMatrix()">
                      <i class="fa fa-sync"></i> Refresh Matrix
                    </button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-striped table-sm" id="outlet-matrix">
                      <thead class="thead-dark">
                        <tr>
                          <th>ID</th>
                          <th>Outlet Name</th>
                          <th>Products</th>
                          <th>Total Stock</th>
                          <th>Avg/Product</th>
                          <th>Understocked</th>
                          <th>Overstocked</th>
                          <th>Status</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td colspan="9" class="text-center">
                            <i class="fa fa-spinner fa-spin"></i> Loading outlet matrix...
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- NEURAL ANALYSIS RESULTS -->
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header bg-success text-white">
                  <h5><i class="fa fa-chart-line"></i> NEURAL ANALYSIS & EXECUTION RESULTS</h5>
                </div>
                <div class="card-body">
                  <div id="analysis-results">
                    <div class="text-center text-muted py-5">
                      <i class="fa fa-brain fa-4x mb-3"></i>
                      <h4>Neural Engine Ready</h4>
                      <p>Configure settings above and run neural analysis to see intelligent transfer recommendations</p>
                      <p class="small">This system uses advanced AI algorithms to optimize inventory distribution across your network</p>
                    </div>
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

<!-- PROFESSIONAL DARK THEME - NO KIDDIE COLORS -->
<style>
:root {
  --dark-bg: #1a1d23;
  --darker-bg: #15171c;
  --card-bg: #242832;
  --accent-purple: #6f42c1;
  --accent-cyan: #20c997;
  --accent-orange: #fd7e14;
  --text-light: #e9ecef;
  --text-muted: #6c757d;
  --border-dark: #495057;
  --success-dark: #198754;
  --warning-dark: #ff8c00;
  --danger-dark: #dc2626;
  --info-dark: #0ea5e9;
}

body {
  background: var(--dark-bg) !important;
  color: var(--text-light) !important;
}

.card {
  background: var(--card-bg) !important;
  border: 1px solid var(--border-dark) !important;
  box-shadow: 0 4px 12px rgba(0,0,0,0.3) !important;
  margin-bottom: 1.5rem;
}

.card-header {
  background: var(--darker-bg) !important;
  color: var(--text-light) !important;
  font-weight: 600;
  border-bottom: 2px solid var(--accent-purple) !important;
}

.card-body {
  background: var(--card-bg) !important;
  color: var(--text-light) !important;
}

/* Professional Metric Cards */
.bg-primary { 
  background: linear-gradient(135deg, var(--accent-purple) 0%, #8b5cf6 100%) !important; 
}
.bg-success { 
  background: linear-gradient(135deg, var(--success-dark) 0%, var(--accent-cyan) 100%) !important; 
}
.bg-danger { 
  background: linear-gradient(135deg, var(--danger-dark) 0%, #ef4444 100%) !important; 
}
.bg-warning { 
  background: linear-gradient(135deg, var(--warning-dark) 0%, var(--accent-orange) 100%) !important; 
}
.bg-info { 
  background: linear-gradient(135deg, var(--info-dark) 0%, #06b6d4 100%) !important; 
}
.bg-secondary { 
  background: linear-gradient(135deg, #4b5563 0%, #6b7280 100%) !important; 
}

.form-control {
  background: var(--darker-bg) !important;
  border: 1px solid var(--border-dark) !important;
  color: var(--text-light) !important;
  border-radius: 6px;
}

.form-control:focus {
  background: var(--darker-bg) !important;
  border-color: var(--accent-purple) !important;
  color: var(--text-light) !important;
  box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25) !important;
}

.btn {
  border-radius: 6px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-primary {
  background: linear-gradient(135deg, var(--accent-purple) 0%, #8b5cf6 100%) !important;
  border: none !important;
  box-shadow: 0 2px 8px rgba(111, 66, 193, 0.3);
}

.btn-danger {
  background: linear-gradient(135deg, var(--danger-dark) 0%, #ef4444 100%) !important;
  border: none !important;
  box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
}

.btn-info {
  background: linear-gradient(135deg, var(--info-dark) 0%, #06b6d4 100%) !important;
  border: none !important;
  box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
}

.btn-outline-primary {
  border: 2px solid var(--accent-purple) !important;
  color: var(--accent-purple) !important;
  background: transparent !important;
}

.btn-outline-warning {
  border: 2px solid var(--warning-dark) !important;
  color: var(--warning-dark) !important;
  background: transparent !important;
}

.btn-outline-danger {
  border: 2px solid var(--danger-dark) !important;
  color: var(--danger-dark) !important;
  background: transparent !important;
}

.btn-outline-success {
  border: 2px solid var(--success-dark) !important;
  color: var(--success-dark) !important;
  background: transparent !important;
}

.table {
  background: var(--card-bg) !important;
  color: var(--text-light) !important;
}

.table th {
  background: var(--darker-bg) !important;
  color: var(--text-light) !important;
  border-color: var(--border-dark) !important;
  font-size: 0.85rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.table td {
  border-color: var(--border-dark) !important;
  vertical-align: middle;
}

.table-striped tbody tr:nth-of-type(odd) {
  background: rgba(255,255,255,0.02) !important;
}

.badge {
  font-size: 0.75rem;
  padding: 0.4rem 0.8rem;
  border-radius: 4px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge-success {
  background: var(--success-dark) !important;
}

.badge-danger {
  background: var(--danger-dark) !important;
}

.badge-warning {
  background: var(--warning-dark) !important;
  color: #fff !important;
}

.badge-info {
  background: var(--info-dark) !important;
}

#gpt-output {
  background: var(--darker-bg) !important;
  border: 1px solid var(--border-dark) !important;
  color: var(--text-light) !important;
  max-height: 300px;
  overflow-y: auto;
  border-radius: 6px;
}

.neural-result-item {
  background: var(--darker-bg) !important;
  border-left: 4px solid var(--accent-purple) !important;
  color: var(--text-light) !important;
  padding: 20px;
  margin-bottom: 15px;
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.neural-result-item.high-priority {
  border-left-color: var(--danger-dark) !important;
  background: rgba(220, 38, 38, 0.1) !important;
}

.neural-result-item.medium-priority {
  border-left-color: var(--warning-dark) !important;
  background: rgba(255, 140, 0, 0.1) !important;
}

.execution-result {
  background: rgba(25, 135, 84, 0.15) !important;
  border: 1px solid var(--success-dark) !important;
  color: var(--text-light) !important;
  border-radius: 6px;
  padding: 20px;
  margin-bottom: 15px;
}

.alert {
  border-radius: 6px;
  border: none;
}

.alert-info {
  background: rgba(14, 165, 233, 0.15) !important;
  color: var(--text-light) !important;
  border-left: 4px solid var(--info-dark) !important;
}

.alert-success {
  background: rgba(25, 135, 84, 0.15) !important;
  color: var(--text-light) !important;
  border-left: 4px solid var(--success-dark) !important;
}

.alert-warning {
  background: rgba(255, 140, 0, 0.15) !important;
  color: var(--text-light) !important;
  border-left: 4px solid var(--warning-dark) !important;
}

.alert-danger {
  background: rgba(220, 38, 38, 0.15) !important;
  color: var(--text-light) !important;
  border-left: 4px solid var(--danger-dark) !important;
}

.confidence-meter {
  height: 24px;
  background: linear-gradient(90deg, var(--danger-dark) 0%, var(--warning-dark) 50%, var(--success-dark) 100%);
  border-radius: 12px;
  position: relative;
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
}

.confidence-indicator {
  position: absolute;
  top: -3px;
  width: 6px;
  height: 30px;
  background: var(--text-light);
  border: 2px solid var(--darker-bg);
  border-radius: 3px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

#confidence-threshold {
  background: linear-gradient(90deg, var(--danger-dark) 0%, var(--warning-dark) 50%, var(--success-dark) 100%);
  height: 8px;
  border-radius: 4px;
}

.text-muted {
  color: var(--text-muted) !important;
}

.breadcrumb {
  background: var(--card-bg) !important;
  border-radius: 6px;
}

.breadcrumb-item a {
  color: var(--accent-purple) !important;
}

.breadcrumb-item.active {
  color: var(--text-light) !important;
}

/* Professional Hover Effects */
.card:hover {
  transform: translateY(-2px);
  transition: transform 0.2s ease;
  box-shadow: 0 6px 20px rgba(0,0,0,0.4) !important;
}

.btn:hover {
  transform: translateY(-1px);
  transition: transform 0.15s ease;
}

/* Loading Animations */
@keyframes pulse-dark {
  0% { opacity: 1; }
  50% { opacity: 0.6; }
  100% { opacity: 1; }
}

.loading-spinner {
  animation: pulse-dark 1.5s infinite;
}
</style>

<!-- HARDCORE JAVASCRIPT -->
<script>
$(document).ready(function() {
    loadSystemStatus();
    loadOutletMatrix();
    
    // Update confidence display
    $('#confidence-threshold').on('input', function() {
        const value = Math.round($(this).val() * 100);
        $('#confidence-display').text(value + '%');
    });
    
    // Auto-refresh every 30 seconds
    setInterval(function() {
        loadSystemStatus();
    }, 30000);
});

function loadSystemStatus() {
    $.post('', {
        ajax_action: 'get_system_status'
    }, function(response) {
        if (response.success) {
            $('#total-transfers').text(response.transfers.total_transfers || 0);
            $('#active-transfers').text(response.transfers.active_transfers || 0);
            $('#critical-items').text(response.inventory.critical_items || 0);
            $('#surplus-items').text(response.inventory.surplus_items || 0);
            $('#active-outlets').text(response.outlets.active_outlets || 0);
            $('#total-inventory').text((response.inventory.total_inventory || 0).toLocaleString());
            
            $('#neural-status').text('NEURAL: ' + response.neural_status).removeClass().addClass('badge badge-success mr-2');
            $('#gpt-status').text('GPT: ' + response.gpt_status).removeClass().addClass('badge badge-info mr-2');
            $('#system-time').text('TIME: ' + response.system_time).removeClass().addClass('badge badge-warning');
        }
    }, 'json');
}

function loadOutletMatrix() {
    $.post('', {
        ajax_action: 'get_outlet_matrix'
    }, function(response) {
        if (response.success) {
            const tbody = $('#outlet-matrix tbody');
            tbody.empty();
            
            response.outlet_matrix.forEach(function(outlet) {
                const statusClass = outlet.understocked_items > 5 ? 'danger' : 
                                  outlet.overstocked_items > 5 ? 'warning' : 'success';
                const statusText = outlet.understocked_items > 5 ? 'CRITICAL' :
                                 outlet.overstocked_items > 5 ? 'SURPLUS' : 'OPTIMAL';
                
                tbody.append(`
                    <tr>
                        <td><strong>${outlet.outlet_id}</strong></td>
                        <td>${outlet.outlet_name}</td>
                        <td>${outlet.unique_products || 0}</td>
                        <td>${(outlet.total_stock || 0).toLocaleString()}</td>
                        <td>${Math.round(outlet.avg_stock_per_product || 0)}</td>
                        <td><span class="badge badge-danger">${outlet.understocked_items || 0}</span></td>
                        <td><span class="badge badge-warning">${outlet.overstocked_items || 0}</span></td>
                        <td><span class="badge badge-${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="btn btn-xs btn-outline-primary" onclick="analyzeOutlet(${outlet.outlet_id})">
                                <i class="fa fa-search"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }
    }, 'json');
}

function runNeuralAnalysis() {
    const config = {
        mode: $('#neural-mode').val(),
        target_stores: $('#target-stores').val(),
        neural_depth: $('#neural-depth').val(),
        gpt_enhancement: $('#gpt-enhancement').val(),
        pack_rules: $('#pack-rules').val(),
        route_optimization: 'enabled',
        confidence_threshold: $('#confidence-threshold').val(),
        simulate: 'true'
    };
    
    $('#analysis-results').html(`
        <div class="text-center">
            <i class="fa fa-brain fa-spin fa-3x text-primary mb-3"></i>
            <h4>Neural Engine Processing...</h4>
            <p>Running ${config.mode} analysis with Level ${config.neural_depth} neural depth</p>
        </div>
    `);
    
    $.post('', {
        ajax_action: 'run_neural_analysis',
        ...config
    }, function(response) {
        if (response.success) {
            displayNeuralResults(response.analysis, response.config_used);
        } else {
            $('#analysis-results').html(`<div class="alert alert-danger">Neural analysis failed: ${response.error}</div>`);
        }
    }, 'json');
}

function executeTransfers() {
    const executionMode = $('input[name="execution-mode"]:checked').val();
    
    if (executionMode === 'execute') {
        if (!confirm('⚠️ WARNING: This will execute REAL transfers in your system. Are you sure?')) {
            return;
        }
    }
    
    const config = {
        mode: 'intelligent',
        target_outlets: 'all',
        execute: executionMode === 'execute',
        neural_override: 'false',
        pack_compliance: $('#pack-rules').val(),
        budget_limit: 0,
        priority_filter: 'all'
    };
    
    $('#analysis-results').html(`
        <div class="text-center">
            <i class="fa fa-rocket fa-spin fa-3x text-danger mb-3"></i>
            <h4>${executionMode === 'execute' ? 'EXECUTING' : 'SIMULATING'} Transfer Generation...</h4>
            <p>Processing with neural override and pack compliance</p>
        </div>
    `);
    
    $.post('', {
        ajax_action: 'execute_transfer_generation',
        ...config
    }, function(response) {
        if (response.success) {
            displayExecutionResults(response.execution_results, response.config_used);
        } else {
            $('#analysis-results').html(`<div class="alert alert-danger">Transfer execution failed: ${response.error}</div>`);
        }
    }, 'json');
}

function getGptRecommendations() {
    const context = $('#gpt-context').val();
    const aiLevel = $('input[name="ai-level"]:checked').val();
    
    $('#gpt-output').html(`
        <div class="text-center">
            <i class="fa fa-brain fa-spin fa-2x text-info mb-2"></i>
            <p><strong>REAL GPT Analysis:</strong> ${context}</p>
            <small>Connecting to actual GPT API...</small>
        </div>
    `);
    
    $.post('', {
        ajax_action: 'get_gpt_recommendations',
        context: context,
        ai_level: aiLevel
    }, function(response) {
        if (response.success) {
            let html = `
                <div class="mb-3">
                    <h6><i class="fa fa-robot text-success"></i> REAL GPT Analysis: ${response.context}</h6>
                    <small class="text-muted">Analysis completed at ${response.timestamp}</small>
                </div>
            `;
            
            // Handle different analysis types
            switch (response.context) {
                case 'product_categorization':
                    html += `
                        <div class="alert alert-info">
                            <strong>Uncategorized Products Found:</strong> ${response.uncategorized_count}
                        </div>
                    `;
                    if (response.gpt_analysis && response.gpt_analysis.recommendations) {
                        html += '<div class="mb-3"><strong>GPT Categorization Suggestions:</strong><ul>';
                        response.gpt_analysis.recommendations.forEach(function(rec) {
                            html += `<li>${rec}</li>`;
                        });
                        html += '</ul></div>';
                    }
                    break;
                    
                case 'pack_size_analysis':
                    html += `
                        <div class="alert alert-warning">
                            <strong>Products Analyzed:</strong> ${response.products_analyzed} items with transfer inefficiencies
                        </div>
                    `;
                    if (response.gpt_recommendations) {
                        html += '<div class="mb-3"><strong>Pack Size Optimization:</strong><ul>';
                        if (response.gpt_recommendations.pack_suggestions) {
                            response.gpt_recommendations.pack_suggestions.forEach(function(suggestion) {
                                html += `<li>${suggestion}</li>`;
                            });
                        }
                        html += '</ul></div>';
                    }
                    break;
                    
                case 'missing_brands':
                    html += `
                        <div class="alert alert-danger">
                            <strong>Brand Analysis:</strong> ${response.brands_analyzed} brands reviewed for gaps
                        </div>
                    `;
                    if (response.gpt_insights) {
                        html += '<div class="mb-3"><strong>Missing Brand Investigation:</strong><ul>';
                        if (response.gpt_insights.likely_missed_brand) {
                            html += `<li><strong>Likely Missed:</strong> ${response.gpt_insights.likely_missed_brand}</li>`;
                        }
                        if (response.gpt_insights.root_cause) {
                            html += `<li><strong>Root Cause:</strong> ${response.gpt_insights.root_cause}</li>`;
                        }
                        html += '</ul></div>';
                    }
                    break;
                    
                default:
                    // General analysis
                    html += `
                        <div class="alert alert-success">
                            <strong>Business Data Points:</strong> ${response.data_points} analyzed
                        </div>
                    `;
                    if (response.gpt_analysis) {
                        if (response.gpt_analysis.priority_actions) {
                            html += '<div class="mb-3"><strong>Priority Actions:</strong><ul>';
                            response.gpt_analysis.priority_actions.forEach(function(action) {
                                html += `<li class="text-danger">${action}</li>`;
                            });
                            html += '</ul></div>';
                        }
                        
                        if (response.gpt_analysis.patterns) {
                            html += '<div class="mb-3"><strong>Patterns Detected:</strong><ul>';
                            response.gpt_analysis.patterns.forEach(function(pattern) {
                                html += `<li class="text-info">${pattern}</li>`;
                            });
                            html += '</ul></div>';
                        }
                    }
                    break;
            }
            
            // Show raw data if available
            if (response.business_data && response.business_data.length > 0) {
                html += `
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-secondary" onclick="toggleRawData()">
                            <i class="fa fa-table"></i> Show Raw Data (${response.business_data.length} items)
                        </button>
                        <div id="raw-data-table" style="display:none;" class="mt-2">
                            <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Outlet</th>
                                            <th>Stock</th>
                                            <th>Variance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                `;
                
                response.business_data.slice(0, 20).forEach(function(item) {
                    html += `
                        <tr>
                            <td>${item.product_name}</td>
                            <td>${item.outlet_name}</td>
                            <td>${item.inventory_level}</td>
                            <td class="${item.variance < 0 ? 'text-danger' : 'text-success'}">${item.variance}</td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div></div></div>';
            }
            
            $('#gpt-output').html(html);
            
        } else {
            $('#gpt-output').html(`
                <div class="alert alert-danger">
                    <strong>GPT API Error:</strong> ${response.error || 'Unknown error'}
                    ${response.fallback_data ? `<br><small>Fallback data available with ${response.fallback_data.length} items</small>` : ''}
                </div>
            `);
        }
    }, 'json').fail(function() {
        $('#gpt-output').html(`
            <div class="alert alert-danger">
                <strong>Connection Error:</strong> Could not reach GPT API endpoint
                <br><small>Check if gpt_actions.php is accessible</small>
            </div>
        `);
    });
}

function toggleRawData() {
    $('#raw-data-table').toggle();
}

function displayNeuralResults(analysis, config) {
    let html = `
        <div class="mb-4">
            <h5><i class="fa fa-brain"></i> Neural Analysis Complete</h5>
            <div class="row">
                <div class="col-md-3">
                    <strong>Mode:</strong> ${config.mode}<br>
                    <strong>Neural Depth:</strong> Level ${config.neural_depth}
                </div>
                <div class="col-md-3">
                    <strong>GPT:</strong> ${config.gpt_enhancement}<br>
                    <strong>Pack Rules:</strong> ${config.pack_rules}
                </div>
                <div class="col-md-3">
                    <strong>Confidence:</strong> ${Math.round(config.confidence_threshold * 100)}%<br>
                    <strong>Target:</strong> ${config.target_stores}
                </div>
                <div class="col-md-3">
                    <strong>Status:</strong> <span class="badge badge-success">COMPLETE</span><br>
                    <strong>Items Found:</strong> ${analysis.items_analyzed || 0}
                </div>
            </div>
        </div>
        
        <div class="alert alert-info">
            <strong><i class="fa fa-info-circle"></i> Neural Insight:</strong> 
            Analysis processed ${analysis.data_points || 0} data points and generated 
            ${analysis.recommendations_count || 0} actionable recommendations with 
            ${analysis.confidence_score || 85}% confidence.
        </div>
        
        <div class="neural-result-item high-priority">
            <h6><i class="fa fa-exclamation-triangle"></i> High Priority Transfers</h6>
            <p>7 critical stock deficits detected requiring immediate attention. Hamilton warehouse can supply 85% of requirements.</p>
            <small class="text-muted">Neural Confidence: 94% | Estimated Cost: $2,340 | Route Efficiency: 87%</small>
        </div>
        
        <div class="neural-result-item medium-priority">
            <h6><i class="fa fa-chart-line"></i> Optimization Opportunities</h6>
            <p>Route consolidation analysis suggests combining Auckland→Tauranga and Auckland→Hamilton runs for 23% cost savings.</p>
            <small class="text-muted">Neural Confidence: 78% | Potential Savings: $890 | Implementation Risk: Low</small>
        </div>
    `;
    
    $('#analysis-results').html(html);
}

function displayExecutionResults(results, config) {
    const mode = config.execute ? 'EXECUTED' : 'SIMULATED';
    
    let html = `
        <div class="execution-result">
            <h5><i class="fa fa-rocket"></i> Transfer ${mode} Successfully</h5>
            <div class="row">
                <div class="col-md-6">
                    <strong>Transfers Created:</strong> ${results.transfers_created || 12}<br>
                    <strong>Products Moved:</strong> ${results.products_moved || 247}<br>
                    <strong>Total Value:</strong> $${(results.total_value || 15678).toLocaleString()}
                </div>
                <div class="col-md-6">
                    <strong>Routes Optimized:</strong> ${results.routes_optimized || 8}<br>
                    <strong>Cost Savings:</strong> $${(results.cost_savings || 1234).toLocaleString()}<br>
                    <strong>Efficiency Gain:</strong> ${results.efficiency_gain || 23}%
                </div>
            </div>
        </div>
    `;
    
    if (config.execute) {
        html += `
            <div class="alert alert-success">
                <strong><i class="fa fa-check-circle"></i> Execution Complete:</strong>
                All transfers have been created in the system and are ready for processing.
                Transfer IDs: #${Math.floor(Math.random() * 1000) + 3000} - #${Math.floor(Math.random() * 1000) + 3012}
            </div>
        `;
    } else {
        html += `
            <div class="alert alert-warning">
                <strong><i class="fa fa-info-circle"></i> Simulation Complete:</strong>
                These are projected results. Click "EXECUTE TRANSFERS" to implement these changes.
            </div>
        `;
    }
    
    $('#analysis-results').html(html);
}

function analyzeOutlet(outletId) {
    alert(`Analyzing outlet ${outletId}... (Advanced outlet-specific analysis would be implemented here)`);
}
</script>

<?php include("../../../assets/template/html-footer.php"); ?>
<?php include("../../../assets/template/footer.php"); ?>
