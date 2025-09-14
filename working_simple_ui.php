<?php 
/**
 * ========================================
 * 🛠️ WORKING SIMPLE TRANSFER UI
 * ========================================
 * 
 * SIMPLE, BORING, BUT IT ACTUALLY WORKS
 * No fancy shit - just functional buttons that DO THINGS
 * 
 * @author Pearce Stephens / Ecigdis Ltd
 */

// ERROR DEBUGGING - TEMPORARY FOR 500 ERROR FIX
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Catch all errors and display them
set_error_handler(function($severity, $message, $file, $line) {
    echo "<div style='background:red;color:white;padding:10px;margin:10px;'>";
    echo "<strong>PHP Error:</strong> $message<br>";
    echo "<strong>File:</strong> $file<br>";
    echo "<strong>Line:</strong> $line<br>";
    echo "</div>";
});

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if($error !== NULL && $error['type'] === E_ERROR) {
        echo "<div style='background:darkred;color:white;padding:10px;margin:10px;'>";
        echo "<strong>FATAL ERROR:</strong> " . $error['message'] . "<br>";
        echo "<strong>File:</strong> " . $error['file'] . "<br>";
        echo "<strong>Line:</strong> " . $error['line'] . "<br>";
        echo "</div>";
    }
});

// ========================================
// INCLUDES & DATABASE SETUP - FIXED FOR 500 ERRORS
// ========================================

// Direct database connection - bypass config.php for now
$link = mysqli_connect('localhost', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');
if (!$link) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Try to include config if it exists, but don't fail if it doesn't
$base_path = '/home/master/applications/jcepnzzkmj/public_html/';
$config_path = $base_path . "assets/functions/config.php";
if (file_exists($config_path)) {
    @include_once($config_path);
}

// ========================================
// POST HANDLERS - ACTUAL WORKING FUNCTIONS
// ========================================
if (isset($_POST["run_transfer"])){
    // SECURITY: Validate inputs
    $mode = $_POST["mode"] ?? 'simulate';
    $outlet_from = $_POST["outlet_from"] ?? '';
    $outlet_to = $_POST["outlet_to"] ?? '';
    
    // Validate mode
    if (!in_array($mode, ['simulate', 'live'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid mode']);
        die();
    }
    
    // Validate outlet IDs if provided
    if ($outlet_from && !is_numeric($outlet_from)) {
        echo json_encode(['success' => false, 'error' => 'Invalid outlet_from']);
        die();
    }
    if ($outlet_to && !is_numeric($outlet_to)) {
        echo json_encode(['success' => false, 'error' => 'Invalid outlet_to']);
        die();
    }
    
    // Log the operation
    $log_entry = date('Y-m-d H:i:s') . " - Transfer execution: mode={$mode}, from={$outlet_from}, to={$outlet_to}\n";
    file_put_contents(__DIR__ . '/logs/transfer_operations.log', $log_entry, FILE_APPEND | LOCK_EX);
    
    try {
        // Build command with proper escaping
        $index_path = __DIR__ . '/index.php';
        if (!file_exists($index_path)) {
            throw new Exception("Transfer engine not found at: {$index_path}");
        }
        
        // Use URL parameters approach that the engine expects
        $params = "action=run&simulate=" . ($mode === 'simulate' ? '1' : '0');
        if ($outlet_from) $params .= "&outlet_from=" . urlencode($outlet_from);
        if ($outlet_to) $params .= "&outlet_to=" . urlencode($outlet_to);
        
        // Set environment variables for the subprocess
        $env = $_ENV;
        $env['QUERY_STRING'] = $params;
        
        // Execute with timeout and proper error handling
        $descriptorspec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];
        
        $process = proc_open("php {$index_path}", $descriptorspec, $pipes, __DIR__, $env);
        
        if (!is_resource($process)) {
            throw new Exception("Failed to start transfer process");
        }
        
        // Close stdin
        fclose($pipes[0]);
        
        // Read output with timeout
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        
        $output = '';
        $error_output = '';
        $timeout = 300; // 5 minutes
        $start_time = time();
        
        while (proc_get_status($process)['running'] && (time() - $start_time) < $timeout) {
            $output .= stream_get_contents($pipes[1]);
            $error_output .= stream_get_contents($pipes[2]);
            usleep(100000); // 0.1 seconds
        }
        
        // Get any remaining output
        $output .= stream_get_contents($pipes[1]);
        $error_output .= stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $return_value = proc_close($process);
        
        // Combine outputs
        $full_output = $output;
        if ($error_output) {
            $full_output .= "\n--- ERRORS ---\n" . $error_output;
        }
        
        // Log result
        $result_log = date('Y-m-d H:i:s') . " - Transfer completed: return_code={$return_value}, output_length=" . strlen($full_output) . "\n";
        file_put_contents(__DIR__ . '/logs/transfer_operations.log', $result_log, FILE_APPEND | LOCK_EX);
        
        echo json_encode([
            'success' => $return_value === 0,
            'command' => "php index.php with params: {$params}",
            'output' => $full_output ?: 'No output received',
            'return_code' => $return_value,
            'execution_time' => (time() - $start_time) . ' seconds'
        ]);
        
    } catch (Exception $e) {
        // Log error
        $error_log = date('Y-m-d H:i:s') . " - Transfer ERROR: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/logs/transfer_operations.log', $error_log, FILE_APPEND | LOCK_EX);
        
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'command' => 'Failed to execute'
        ]);
    }
    
    die();
}

if (isset($_POST["get_outlets"])){
    try {
        $outlets = array();
        
        // Get real outlets from database with error handling
        $sql = "SELECT outlet_id, outlet_name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY outlet_name";
        $result = mysqli_query($link, $sql);
        
        if (!$result) {
            throw new Exception("Database query failed: " . mysqli_error($link));
        }
        
        while ($row = mysqli_fetch_assoc($result)) {
            $outlets[] = [
                'outlet_id' => $row['outlet_id'],
                'outlet_name' => htmlspecialchars($row['outlet_name'], ENT_QUOTES, 'UTF-8')
            ];
        }
        
        // Log successful outlet fetch
        $log_entry = date('Y-m-d H:i:s') . " - Outlets fetched: " . count($outlets) . " outlets\n";
        file_put_contents(__DIR__ . '/logs/transfer_operations.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        echo json_encode(['success' => true, 'outlets' => $outlets]);
        
    } catch (Exception $e) {
        // Log error
        $error_log = date('Y-m-d H:i:s') . " - Outlet fetch ERROR: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/logs/transfer_operations.log', $error_log, FILE_APPEND | LOCK_EX);
        
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    die();
}

if (isset($_POST["get_recent_transfers"])){
    try {
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        if ($limit > 100) $limit = 100; // Security: prevent excessive queries
        
        $sql = "SELECT st.transfer_id, st.outlet_from, st.outlet_to, st.status, st.date_created,
                       vo_from.outlet_name as from_name, vo_to.outlet_name as to_name,
                       (SELECT COUNT(*) FROM stock_products_to_transfer spt WHERE spt.transfer_id = st.transfer_id) as product_count
                FROM stock_transfers st
                LEFT JOIN vend_outlets vo_from ON st.outlet_from = vo_from.outlet_id
                LEFT JOIN vend_outlets vo_to ON st.outlet_to = vo_to.outlet_id
                ORDER BY st.date_created DESC 
                LIMIT ?";
        
        $stmt = mysqli_prepare($link, $sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare statement: " . mysqli_error($link));
        }
        
        mysqli_stmt_bind_param($stmt, "i", $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (!$result) {
            throw new Exception("Query execution failed: " . mysqli_error($link));
        }
        
        $transfers = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $transfers[] = [
                'transfer_id' => $row['transfer_id'],
                'outlet_from' => $row['outlet_from'],
                'outlet_to' => $row['outlet_to'],
                'from_name' => htmlspecialchars($row['from_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'),
                'to_name' => htmlspecialchars($row['to_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'),
                'status' => htmlspecialchars($row['status'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'),
                'date_created' => $row['date_created'],
                'product_count' => $row['product_count']
            ];
        }
        
        mysqli_stmt_close($stmt);
        
        // Log successful transfer fetch
        $log_entry = date('Y-m-d H:i:s') . " - Recent transfers fetched: " . count($transfers) . " transfers\n";
        file_put_contents(__DIR__ . '/logs/transfer_operations.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        echo json_encode(['success' => true, 'transfers' => $transfers]);
        
    } catch (Exception $e) {
        // Log error
        $error_log = date('Y-m-d H:i:s') . " - Transfer fetch ERROR: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/logs/transfer_operations.log', $error_log, FILE_APPEND | LOCK_EX);
        
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    die();
}

// NEW: Get active/running transfer processes
if (isset($_POST["get_active_transfers"])){
    try {
        // Check for running transfer processes
        $processes = [];
        $pid_files = glob(__DIR__ . '/logs/transfer_*.pid');
        
        foreach ($pid_files as $pid_file) {
            $pid = (int)file_get_contents($pid_file);
            if ($pid > 0) {
                // Check if process is still running
                $running = file_exists("/proc/{$pid}");
                if ($running) {
                    $processes[] = [
                        'pid' => $pid,
                        'started' => date('Y-m-d H:i:s', filemtime($pid_file)),
                        'duration' => time() - filemtime($pid_file)
                    ];
                } else {
                    // Clean up stale PID file
                    unlink($pid_file);
                }
            }
        }
        
        // Get transfers in progress status
        $sql = "SELECT transfer_id, status, date_created, outlet_from, outlet_to 
                FROM stock_transfers 
                WHERE status IN ('pending', 'processing', 'in_progress') 
                ORDER BY date_created DESC";
        
        $result = mysqli_query($link, $sql);
        $active_transfers = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $active_transfers[] = $row;
            }
        }
        
        echo json_encode([
            'success' => true,
            'processes' => $processes,
            'active_transfers' => $active_transfers,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    die();
}

// NEW: Get system status and stats
if (isset($_POST["get_system_status"])){
    try {
        // Get transfer statistics
        $stats = [];
        
        // Today's transfers
        $sql = "SELECT COUNT(*) as count FROM stock_transfers WHERE DATE(date_created) = CURDATE()";
        $result = mysqli_query($link, $sql);
        $stats['today_transfers'] = mysqli_fetch_assoc($result)['count'];
        
        // This week's transfers
        $sql = "SELECT COUNT(*) as count FROM stock_transfers WHERE YEARWEEK(date_created) = YEARWEEK(NOW())";
        $result = mysqli_query($link, $sql);
        $stats['week_transfers'] = mysqli_fetch_assoc($result)['count'];
        
        // Failed transfers (last 24h)
        $sql = "SELECT COUNT(*) as count FROM stock_transfers 
                WHERE status = 'failed' AND date_created > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = mysqli_query($link, $sql);
        $stats['failed_transfers_24h'] = mysqli_fetch_assoc($result)['count'];
        
        // Total outlets
        $sql = "SELECT COUNT(*) as count FROM vend_outlets WHERE deleted_at IS NULL";
        $result = mysqli_query($link, $sql);
        $stats['total_outlets'] = mysqli_fetch_assoc($result)['count'];
        
        // Engine status
        $engine_status = 'idle';
        if (file_exists(__DIR__ . '/logs/transfer_running.flag')) {
            $engine_status = 'running';
        }
        
        // Log file size
        $log_file = __DIR__ . '/logs/transfer_operations.log';
        $log_size = file_exists($log_file) ? filesize($log_file) : 0;
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'engine_status' => $engine_status,
            'log_size' => $log_size,
            'timestamp' => date('Y-m-d H:i:s'),
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    die();
}

// DEBUG: Quick system check (GET request for easy testing)
if (isset($_GET['debug']) && $_GET['debug'] === 'status') {
    echo "<h2>🔧 SYSTEM DEBUG STATUS</h2>";
    echo "<strong>Database Connection:</strong> " . ($link ? "✅ Connected" : "❌ Failed") . "<br>";
    echo "<strong>Server Time:</strong> " . date('Y-m-d H:i:s') . "<br>";
    echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
    echo "<strong>Transfer Engine:</strong> " . (file_exists(__DIR__ . '/index.php') ? "✅ Found" : "❌ Missing") . "<br>";
    echo "<strong>Logs Directory:</strong> " . (is_dir(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs') ? "✅ Writable" : "❌ Not writable") . "<br>";
    echo "<strong>Shell Exec:</strong> " . (function_exists('shell_exec') ? "✅ Available" : "❌ Disabled") . "<br>";
    
    if ($link) {
        $result = mysqli_query($link, "SELECT COUNT(*) as count FROM vend_outlets WHERE deleted_at IS NULL");
        $outlet_count = mysqli_fetch_assoc($result)['count'];
        echo "<strong>Outlets Available:</strong> {$outlet_count}<br>";
        
        $result = mysqli_query($link, "SELECT COUNT(*) as count FROM stock_transfers WHERE DATE(date_created) = CURDATE()");
        $today_count = mysqli_fetch_assoc($result)['count'];
        echo "<strong>Today's Transfers:</strong> {$today_count}<br>";
    }
    
    echo "<br><a href='?'>🔙 Back to Transfer UI</a>";
    die();
}

// ========================================
// TEMPLATE HEADERS - SAFE INCLUDES
// ========================================
$header_path1 = $base_path . "assets/template/html-header.php";
$header_path2 = $base_path . "assets/template/header.php";

if (file_exists($header_path1)) {
    include($header_path1);
} else {
    // Minimal HTML header if template not found
    echo '<!DOCTYPE html><html><head><title>Transfer System</title>';
    echo '<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">';
    echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script></head>';
}

if (file_exists($header_path2)) {
    include($header_path2);
}

// ========================================
// GET OUTLETS FOR DROPDOWNS
// ========================================
$outlets = array();
$sql = "SELECT outlet_id, outlet_name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY outlet_name";
$result = mysqli_query($link, $sql);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $outlets[] = $row;
    }
}

?>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
   
<div class="app-body">
    
    <?php 
    $sidemenu_path = $base_path . "assets/template/sidemenu.php";
    if (file_exists($sidemenu_path)) {
        include($sidemenu_path);
    } else {
        echo '<nav class="sidebar"><div class="sidebar-header"><h3>Transfer System</h3></div></nav>';
    }
    ?>
    
    <main class="main">
        
        <ol class="breadcrumb">
            <li class="breadcrumb-item">Home</li>
            <li class="breadcrumb-item">
                <a href="#">Transfers</a>
            </li>
            <li class="breadcrumb-item active">Working Transfer UI</li>
            
            <li class="breadcrumb-menu d-md-down-none">
                <?php 
                $search_path = $base_path . 'assets/template/quick-product-search.php';
                if (file_exists($search_path)) {
                    include($search_path);
                } else {
                    echo '<span class="text-muted">Search unavailable</span>';
                }
                ?>
            </li>
        </ol>
        
        <div class="container-fluid">
            <div class="animated fadeIn">
                <div class="row">         
                    <div class="col">
                        
                        <!-- SIMPLE WORKING TRANSFER INTERFACE -->
                        <div class="card">
                            <div class="card-header">
                                <strong>🛠️ Simple Transfer Engine</strong>
                                <small class="text-muted">Boring but it works</small>
                            </div>
                            
                            <div class="card-body">
                                
                                <!-- TRANSFER EXECUTION FORM -->
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Run Transfer</h5>
                                        
                                        <div class="form-group">
                                            <label>Mode:</label>
                                            <select id="transfer_mode" class="form-control">
                                                <option value="simulate">Simulate (Safe - No Changes)</option>
                                                <option value="live">Live Run (DANGER - Makes Changes)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>From Outlet:</label>
                                            <select id="outlet_from" class="form-control">
                                                <option value="">All Outlets (Auto-Detection)</option>
                                                <?php foreach($outlets as $outlet): ?>
                                                <option value="<?= $outlet['outlet_id'] ?>"><?= htmlspecialchars($outlet['outlet_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>To Outlet:</label>
                                            <select id="outlet_to" class="form-control">
                                                <option value="">All Outlets (Auto-Detection)</option>
                                                <?php foreach($outlets as $outlet): ?>
                                                <option value="<?= $outlet['outlet_id'] ?>"><?= htmlspecialchars($outlet['outlet_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <button id="run_transfer_btn" class="btn btn-primary">
                                            🚀 Run Transfer Engine
                                        </button>
                                        
                                        <button id="refresh_outlets_btn" class="btn btn-secondary ml-2">
                                            🔄 Refresh Outlets
                                        </button>
                                        
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <h5>System Status</h5>
                                        <div id="system_status" class="mb-3">
                                            <p class="text-muted">Loading system status...</p>
                                        </div>
                                        
                                        <h5>Recent Transfers</h5>
                                        <div id="recent_transfers">
                                            <p class="text-muted">Loading transfers...</p>
                                        </div>
                                        
                                        <div class="mt-2">
                                            <button id="refresh_transfers_btn" class="btn btn-info btn-sm">
                                                🔄 Refresh Transfers
                                            </button>
                                            <button id="refresh_status_btn" class="btn btn-success btn-sm ml-1">
                                                📊 Refresh Status
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <!-- ACTIVE TRANSFERS MONITORING -->
                                <div class="row mb-3">
                                    <div class="col-12">
                                        <h5>Active Transfers <small id="active_status" class="text-muted">(Auto-refresh every 10s)</small></h5>
                                        <div id="active_transfers" class="alert alert-info">
                                            <p class="mb-0">Loading active transfer status...</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- OUTPUT AREA -->
                                <div class="row">
                                    <div class="col-12">
                                        <h5>Transfer Output</h5>
                                        <div id="transfer_output" style="background: #f8f9fa; padding: 15px; border-radius: 4px; min-height: 200px; font-family: monospace; white-space: pre-wrap;">
                                            Click "Run Transfer Engine" to see output here...
                                        </div>
                                        
                                        <div class="mt-2">
                                            <button id="clear_output_btn" class="btn btn-secondary btn-sm">
                                                🗑️ Clear Output
                                            </button>
                                            <button id="view_logs_btn" class="btn btn-warning btn-sm ml-1">
                                                📜 View Operation Logs
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
        
    </main>
    
</div>

<?php 
$footer_path = $base_path . "assets/template/html-footer.php";
if (file_exists($footer_path)) {
    include($footer_path);
} else {
    echo '</body></html>';
}
?>

<script>
// ========================================
// ACTUAL WORKING JAVASCRIPT FUNCTIONS
// ========================================

$(document).ready(function() {
    
    // Load recent transfers on page load
    loadRecentTransfers();
    
    // Run Transfer Button - ACTUALLY WORKS
    $('#run_transfer_btn').click(function() {
        var mode = $('#transfer_mode').val();
        var outlet_from = $('#outlet_from').val();
        var outlet_to = $('#outlet_to').val();
        
        // Show loading state
        $(this).prop('disabled', true).text('🔄 Running...');
        $('#transfer_output').text('Running transfer engine...\n\n');
        
        $.post('', {
            run_transfer: '1',
            mode: mode,
            outlet_from: outlet_from,
            outlet_to: outlet_to
        })
        .done(function(response) {
            try {
                var data = JSON.parse(response);
                $('#transfer_output').text('Command: ' + data.command + '\n\n' + 'Output:\n' + data.output);
            } catch(e) {
                $('#transfer_output').text('Response:\n' + response);
            }
        })
        .fail(function(xhr) {
            $('#transfer_output').text('ERROR: ' + xhr.status + ' - ' + xhr.statusText + '\n\n' + xhr.responseText);
        })
        .always(function() {
            $('#run_transfer_btn').prop('disabled', false).text('🚀 Run Transfer Engine');
        });
    });
    
    // Refresh Outlets Button - ACTUALLY WORKS
    $('#refresh_outlets_btn').click(function() {
        $(this).prop('disabled', true).text('🔄 Loading...');
        
        $.post('', {
            get_outlets: '1'
        })
        .done(function(response) {
            try {
                var data = JSON.parse(response);
                
                if (data.success) {
                    var outlets = data.outlets;
                    
                    // Update both dropdowns
                    $('#outlet_from, #outlet_to').each(function() {
                        var selected = $(this).val();
                        $(this).empty().append('<option value="">All Outlets (Auto-Detection)</option>');
                        
                        outlets.forEach(function(outlet) {
                            var option = $('<option></option>')
                                .attr('value', outlet.outlet_id)
                                .text(outlet.outlet_name);
                            
                            if (outlet.outlet_id == selected) {
                                option.prop('selected', true);
                            }
                            
                            $(this).append(option);
                        }.bind(this));
                    });
                    
                    alert('Outlets refreshed successfully! (' + outlets.length + ' outlets loaded)');
                } else {
                    alert('Error loading outlets: ' + data.error);
                }
                
            } catch(e) {
                alert('Error parsing outlet data: ' + e.message);
            }
        })
        .fail(function(xhr) {
            alert('Error loading outlets: ' + xhr.status + ' - ' + xhr.statusText);
        })
        .always(function() {
            $('#refresh_outlets_btn').prop('disabled', false).text('🔄 Refresh Outlets');
        });
    });
    
    // Refresh Transfers Button - ACTUALLY WORKS
    $('#refresh_transfers_btn').click(function() {
        loadRecentTransfers();
    });
    
    // Refresh Status Button - NEW
    $('#refresh_status_btn').click(function() {
        loadSystemStatus();
    });
    
    // Clear Output Button - NEW
    $('#clear_output_btn').click(function() {
        $('#transfer_output').text('Output cleared at ' + new Date().toLocaleString());
    });
    
    // View Logs Button - NEW
    $('#view_logs_btn').click(function() {
        // Open logs in new window/tab
        window.open('logs/transfer_operations.log', '_blank');
    });
    
    // Auto-refresh active transfers every 10 seconds
    setInterval(function() {
        loadActiveTransfers();
    }, 10000);
    
    // Load initial data
    loadSystemStatus();
    loadActiveTransfers();
    
    function loadRecentTransfers() {
        $('#recent_transfers').html('<p class="text-muted">Loading...</p>');
        
        $.post('', {
            get_recent_transfers: '1'
        })
        .done(function(response) {
            try {
                var data = JSON.parse(response);
                var html = '';
                
                if (data.success) {
                    var transfers = data.transfers;
                    
                    if (transfers.length === 0) {
                        html = '<p class="text-muted">No recent transfers found.</p>';
                    } else {
                        html = '<div style="max-height: 300px; overflow-y: auto;"><table class="table table-sm table-striped">';
                        html += '<thead><tr><th>ID</th><th>From → To</th><th>Status</th><th>Products</th><th>Date</th></tr></thead><tbody>';
                        
                        transfers.forEach(function(transfer) {
                            var statusClass = '';
                            if (transfer.status === 'completed') statusClass = 'text-success';
                            else if (transfer.status === 'failed') statusClass = 'text-danger';
                            else if (transfer.status === 'pending') statusClass = 'text-warning';
                            
                            html += '<tr>';
                            html += '<td><strong>' + transfer.transfer_id + '</strong></td>';
                            html += '<td>' + transfer.from_name + ' → ' + transfer.to_name + '</td>';
                            html += '<td class="' + statusClass + '">' + transfer.status + '</td>';
                            html += '<td>' + transfer.product_count + '</td>';
                            html += '<td><small>' + transfer.date_created + '</small></td>';
                            html += '</tr>';
                        });
                        
                        html += '</tbody></table></div>';
                    }
                } else {
                    html = '<p class="text-danger">Error: ' + data.error + '</p>';
                }
                
                $('#recent_transfers').html(html);
                
            } catch(e) {
                $('#recent_transfers').html('<p class="text-danger">Error loading transfers: ' + e.message + '</p>');
            }
        })
        .fail(function(xhr) {
            $('#recent_transfers').html('<p class="text-danger">Error: ' + xhr.status + ' - ' + xhr.statusText + '</p>');
        });
    }
    
    function loadSystemStatus() {
        $.post('', {
            get_system_status: '1'
        })
        .done(function(response) {
            try {
                var data = JSON.parse(response);
                var html = '';
                
                if (data.success) {
                    var stats = data.stats;
                    
                    html += '<div class="row">';
                    html += '<div class="col-3"><strong>Today:</strong><br><span class="h5 text-primary">' + stats.today_transfers + '</span> transfers</div>';
                    html += '<div class="col-3"><strong>This Week:</strong><br><span class="h5 text-info">' + stats.week_transfers + '</span> transfers</div>';
                    html += '<div class="col-3"><strong>Failed (24h):</strong><br><span class="h5 text-danger">' + stats.failed_transfers_24h + '</span> errors</div>';
                    html += '<div class="col-3"><strong>Outlets:</strong><br><span class="h5 text-success">' + stats.total_outlets + '</span> stores</div>';
                    html += '</div>';
                    
                    html += '<hr class="my-2">';
                    html += '<div class="row">';
                    html += '<div class="col-6"><strong>Engine Status:</strong> <span class="badge badge-' + (data.engine_status === 'running' ? 'warning' : 'success') + '">' + data.engine_status.toUpperCase() + '</span></div>';
                    html += '<div class="col-6"><strong>Log Size:</strong> ' + Math.round(data.log_size / 1024) + ' KB</div>';
                    html += '</div>';
                    
                    html += '<small class="text-muted">Last updated: ' + data.timestamp + ' (' + data.timezone + ')</small>';
                } else {
                    html = '<p class="text-danger">Error: ' + data.error + '</p>';
                }
                
                $('#system_status').html(html);
                
            } catch(e) {
                $('#system_status').html('<p class="text-danger">Error loading status: ' + e.message + '</p>');
            }
        })
        .fail(function(xhr) {
            $('#system_status').html('<p class="text-danger">Error: ' + xhr.status + ' - ' + xhr.statusText + '</p>');
        });
    }
    
    function loadActiveTransfers() {
        $.post('', {
            get_active_transfers: '1'
        })
        .done(function(response) {
            try {
                var data = JSON.parse(response);
                var html = '';
                
                if (data.success) {
                    var processes = data.processes;
                    var active_transfers = data.active_transfers;
                    
                    if (processes.length === 0 && active_transfers.length === 0) {
                        html = '<p class="mb-0 text-muted">✅ No active transfers or running processes</p>';
                    } else {
                        if (processes.length > 0) {
                            html += '<div class="mb-2"><strong>🔄 Running Processes:</strong><br>';
                            processes.forEach(function(proc) {
                                html += '<span class="badge badge-warning mr-1">PID ' + proc.pid + ' (running ' + Math.floor(proc.duration / 60) + 'm)</span>';
                            });
                            html += '</div>';
                        }
                        
                        if (active_transfers.length > 0) {
                            html += '<div><strong>📋 Active Transfers:</strong><br>';
                            active_transfers.forEach(function(transfer) {
                                var statusColor = transfer.status === 'pending' ? 'secondary' : 'primary';
                                html += '<span class="badge badge-' + statusColor + ' mr-1">Transfer #' + transfer.transfer_id + ' (' + transfer.status + ')</span>';
                            });
                            html += '</div>';
                        }
                    }
                    
                    html += '<div class="mt-2"><small class="text-muted">Updated: ' + data.timestamp + '</small></div>';
                } else {
                    html = '<p class="mb-0 text-danger">Error: ' + data.error + '</p>';
                }
                
                $('#active_transfers').html(html);
                $('#active_status').text('(Auto-refresh every 10s - Last: ' + new Date().toLocaleTimeString() + ')');
                
            } catch(e) {
                $('#active_transfers').html('<p class="mb-0 text-danger">Error loading active transfers: ' + e.message + '</p>');
            }
        })
        .fail(function(xhr) {
            $('#active_transfers').html('<p class="mb-0 text-danger">Error: ' + xhr.status + ' - ' + xhr.statusText + '</p>');
        });
    }
    
});
</script>

</body>
</html>
