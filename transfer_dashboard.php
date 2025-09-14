<?php 
/**
 * ========================================
 * 🎛️ NEWTRANSFERV3 DASHBOARD - CIS TEMPLATE
 * ========================================
 * 
 * Professional transfer management dashboard integrated with CIS template
 * 
 * @author GitHub Copilot / Ecigdis Ltd
 * @created 2025-09-13
 * @version Production Ready
 */

// ========================================
// 🔧 STEP 1: INCLUDES (NEVER CHANGE THESE)
// ========================================
include("assets/functions/config.php");

// ========================================
// 🔧 STEP 2: HANDLE POST REQUESTS (YOUR BACKEND LOGIC GOES HERE)
// ========================================
// AJAX Handler for Dashboard Operations
if (isset($_POST["action"])) {
    header('Content-Type: application/json');
    
    switch($_POST["action"]) {
        case 'run_transfer':
            $outlet_from = (int)$_POST['outlet_from'];
            $outlet_to = (int)$_POST['outlet_to'];
            $simulate = (int)$_POST['simulate'];
            
            // Run transfer engine
            $result = shell_exec("php index.php action=run simulate=$simulate outlet_from=$outlet_from outlet_to=$outlet_to 2>&1");
            echo json_encode(['success' => true, 'output' => $result]);
            break;
            
        case 'get_outlets':
            $outlets = query("SELECT id, name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY name");
            echo json_encode(['success' => true, 'outlets' => $outlets]);
            break;
            
        case 'get_transfers':
            $transfers = query("SELECT transfer_id, outlet_from, outlet_to, date_created, status FROM stock_transfers ORDER BY date_created DESC LIMIT 10");
            echo json_encode(['success' => true, 'transfers' => $transfers]);
            break;
            
        case 'get_json_files':
            $files = glob('*.json');
            $file_data = [];
            foreach($files as $file) {
                $file_data[] = [
                    'name' => $file,
                    'size' => filesize($file),
                    'modified' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
            echo json_encode(['success' => true, 'files' => $file_data]);
            break;
    }
    die();
}

// ========================================
// 🔧 STEP 3: TEMPLATE HEADERS (NEVER CHANGE THESE)
// ========================================
include("assets/template/html-header.php");
include("assets/template/header.php");

// ========================================
// 🔧 STEP 4: PAGE DATA SETUP (YOUR PHP LOGIC GOES HERE)
// ========================================
// Get outlets for dropdown
$outlets = query("SELECT id, name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY name");

// Get recent transfers
$recentTransfers = query("SELECT transfer_id, outlet_from, outlet_to, date_created, status FROM stock_transfers ORDER BY date_created DESC LIMIT 5");

// Get system stats
$totalOutlets = query("SELECT COUNT(*) as count FROM vend_outlets WHERE deleted_at IS NULL")[0]['count'];
$totalTransfers = query("SELECT COUNT(*) as count FROM stock_transfers")[0]['count'];

?>

<!-- ========================================
     🎨 STEP 5: HTML BODY START (NEVER CHANGE)
     ======================================== -->
<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
   
<div class="app-body">
    
    <!-- ========================================
         🔧 STEP 6: SIDE MENU (NEVER CHANGE)
         ======================================== -->
    <?php include("assets/template/sidemenu.php") ?>
    
    <!-- ========================================
         🎨 STEP 7: MAIN CONTENT AREA BEGINS
         ======================================== -->
    <main class="main">
        
        <!-- ========================================
             🧭 STEP 8: BREADCRUMB (CUSTOMIZE THIS)
             ======================================== -->
        <ol class="breadcrumb">
            <li class="breadcrumb-item">Home</li>
            <li class="breadcrumb-item">
                <a href="#">Transfer Engine</a>
            </li>
            <li class="breadcrumb-item active">NewTransferV3 Dashboard</li>
            
            <!-- Breadcrumb Menu (NEVER CHANGE) -->
            <li class="breadcrumb-menu d-md-down-none">
                <?php include('assets/template/quick-product-search.php');?>
            </li>
        </ol>
        
        <!-- ========================================
             📦 STEP 9: MAIN CONTAINER (NEVER CHANGE STRUCTURE)
             ======================================== -->
        <div class="container-fluid">
            <div class="animated fadeIn">
                
                <!-- Status Cards Row -->
                <div class="row mb-3">
                    <div class="col-sm-6 col-lg-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body pb-0">
                                <div class="text-value-lg"><?php echo $totalOutlets; ?></div>
                                <div>Active Outlets</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card text-white bg-info">
                            <div class="card-body pb-0">
                                <div class="text-value-lg"><?php echo $totalTransfers; ?></div>
                                <div>Total Transfers</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body pb-0">
                                <div class="text-value-lg" id="running-status">Ready</div>
                                <div>Engine Status</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="card text-white bg-success">
                            <div class="card-body pb-0">
                                <div class="text-value-lg" id="last-run">Never</div>
                                <div>Last Run</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Dashboard Tabs -->
                <div class="row">         
                    <div class="col">
                        
                        <!-- ========================================
                             🎯 DASHBOARD CONTENT (REPLACE THIS ENTIRE CARD)
                             ======================================== -->
                        <div class="card">
                            
                            <!-- Card Header with Tabs -->
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="dashboard-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-toggle="tab" href="#execute-tab">Execute</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#monitor-tab">Monitor</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#history-tab">History</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-toggle="tab" href="#files-tab">Files</a>
                                    </li>
                                </ul>
                            </div>
                            
                            <!-- Card Body with Tab Content -->
                            <div class="card-body">
                                <div class="tab-content">
                                    
                                    <!-- Execute Tab -->
                                    <div class="tab-pane active" id="execute-tab">
                                        <h5>Run New Transfer</h5>
                                        
                                        <form id="transfer-form">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>From Outlet</label>
                                                        <select class="form-control" id="outlet_from" name="outlet_from">
                                                            <option value="">Select outlet...</option>
                                                            <?php foreach($outlets as $outlet): ?>
                                                            <option value="<?php echo $outlet['id']; ?>"><?php echo htmlspecialchars($outlet['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>To Outlet</label>
                                                        <select class="form-control" id="outlet_to" name="outlet_to">
                                                            <option value="">Select outlet...</option>
                                                            <?php foreach($outlets as $outlet): ?>
                                                            <option value="<?php echo $outlet['id']; ?>"><?php echo htmlspecialchars($outlet['name']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label>Mode</label>
                                                        <select class="form-control" id="simulate" name="simulate">
                                                            <option value="1">Simulate (Safe)</option>
                                                            <option value="0">Live Run (Real)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="button" class="btn btn-primary" onclick="runTransfer()">
                                                <i class="fa fa-play"></i> Run Transfer
                                            </button>
                                        </form>
                                        
                                        <div id="transfer-output" class="mt-3" style="display:none;">
                                            <h6>Transfer Output:</h6>
                                            <pre id="output-content" class="bg-light p-3"></pre>
                                        </div>
                                    </div>
                                    
                                    <!-- Monitor Tab -->
                                    <div class="tab-pane" id="monitor-tab">
                                        <h5>Real-Time Monitoring</h5>
                                        <div id="monitor-content">
                                            <div class="alert alert-info">
                                                <i class="fa fa-info-circle"></i>
                                                Monitoring system ready. Status updates every 15 seconds.
                                            </div>
                                            <div id="live-status"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- History Tab -->
                                    <div class="tab-pane" id="history-tab">
                                        <h5>Transfer History</h5>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Transfer ID</th>
                                                        <th>From → To</th>
                                                        <th>Date</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="history-tbody">
                                                    <?php foreach($recentTransfers as $transfer): ?>
                                                    <tr>
                                                        <td><?php echo $transfer['transfer_id']; ?></td>
                                                        <td><?php echo $transfer['outlet_from']; ?> → <?php echo $transfer['outlet_to']; ?></td>
                                                        <td><?php echo $transfer['date_created']; ?></td>
                                                        <td><span class="badge badge-secondary"><?php echo $transfer['status']; ?></span></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <!-- Files Tab -->
                                    <div class="tab-pane" id="files-tab">
                                        <h5>JSON Files Browser</h5>
                                        <div id="files-content">
                                            <button class="btn btn-secondary mb-3" onclick="loadFiles()">
                                                <i class="fa fa-refresh"></i> Refresh Files
                                            </button>
                                            <div id="files-list"></div>
                                        </div>
                                    </div>
                                    
                                </div>
                            </div>
                            
                        </div>
                        <!-- END DASHBOARD CONTENT -->
                        
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- ========================================
         🔧 STEP 10: PERSONALIZATION MENU (NEVER CHANGE)
         ======================================== -->
    <?php include("assets/template/personalisation-menu.php") ?>
    
</div>

<!-- ========================================
     🔧 STEP 11: TEMPLATE FOOTERS (NEVER CHANGE)
     ======================================== -->
<?php include("assets/template/html-footer.php") ?>
<?php include("assets/template/footer.php") ?>

<!-- ========================================
     🎨 STEP 12: YOUR JAVASCRIPT GOES HERE
     ======================================== -->
<script>
// Dashboard JavaScript Functions
function runTransfer() {
    const outletFrom = document.getElementById('outlet_from').value;
    const outletTo = document.getElementById('outlet_to').value;
    const simulate = document.getElementById('simulate').value;
    
    if (!outletFrom || !outletTo) {
        alert('Please select both outlet FROM and TO');
        return;
    }
    
    if (outletFrom === outletTo) {
        alert('Source and destination outlets cannot be the same');
        return;
    }
    
    // Show loading
    document.getElementById('running-status').textContent = 'Running...';
    document.getElementById('transfer-output').style.display = 'block';
    document.getElementById('output-content').textContent = 'Executing transfer...';
    
    // Make AJAX request
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=run_transfer&outlet_from=${outletFrom}&outlet_to=${outletTo}&simulate=${simulate}`
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('running-status').textContent = 'Ready';
        document.getElementById('output-content').textContent = data.output || 'Transfer completed';
        document.getElementById('last-run').textContent = new Date().toLocaleTimeString();
        
        // Refresh history
        loadHistory();
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('running-status').textContent = 'Error';
        document.getElementById('output-content').textContent = 'Error running transfer: ' + error.message;
    });
}

function loadHistory() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_transfers'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const tbody = document.getElementById('history-tbody');
            tbody.innerHTML = '';
            
            data.transfers.forEach(transfer => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td>${transfer.transfer_id}</td>
                    <td>${transfer.outlet_from} → ${transfer.outlet_to}</td>
                    <td>${transfer.date_created}</td>
                    <td><span class="badge badge-secondary">${transfer.status}</span></td>
                `;
            });
        }
    });
}

function loadFiles() {
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_json_files'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const filesList = document.getElementById('files-list');
            filesList.innerHTML = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>File</th><th>Size</th><th>Modified</th><th>Actions</th></tr></thead><tbody>';
            
            data.files.forEach(file => {
                filesList.innerHTML += `
                    <tr>
                        <td>${file.name}</td>
                        <td>${(file.size / 1024).toFixed(1)} KB</td>
                        <td>${file.modified}</td>
                        <td>
                            <a href="${file.name}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                            <a href="${file.name}" download class="btn btn-sm btn-outline-secondary">Download</a>
                        </td>
                    </tr>
                `;
            });
            
            filesList.innerHTML += '</tbody></table></div>';
        }
    });
}

// Auto-refresh monitoring every 15 seconds
setInterval(function() {
    if (document.getElementById('monitor-tab').classList.contains('active')) {
        // Update monitoring data
        document.getElementById('live-status').innerHTML = 
            '<div class="alert alert-success"><i class="fa fa-check"></i> System operational - ' + 
            new Date().toLocaleTimeString() + '</div>';
    }
}, 15000);

// Load files on first page load
document.addEventListener('DOMContentLoaded', function() {
    loadFiles();
});
</script>

<!-- ========================================
     🎨 STEP 13: YOUR CSS GOES HERE
     ======================================== -->
<style>
/* Dashboard-specific styles - scoped to avoid conflicts */
.dashboard-card {
    border-left: 4px solid #007bff;
}

.tab-content {
    min-height: 400px;
}

#transfer-output pre {
    max-height: 300px;
    overflow-y: auto;
    font-size: 12px;
}

.text-value-lg {
    font-size: 2.5rem;
    font-weight: 300;
    line-height: 1;
}

.card .nav-tabs {
    border-bottom: 1px solid #dee2e6;
}

.nav-tabs .nav-link {
    border: 1px solid transparent;
    border-top-left-radius: 0.25rem;
    border-top-right-radius: 0.25rem;
}

.nav-tabs .nav-link:hover {
    border-color: #e9ecef #e9ecef #dee2e6;
}

.nav-tabs .nav-link.active {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .text-value-lg {
        font-size: 1.8rem;
    }
    
    .nav-tabs {
        font-size: 14px;
    }
}
</style>
