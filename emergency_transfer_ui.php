<?php 
/**
 * ========================================
 * 🛠️ EMERGENCY SIMPLE TRANSFER UI - NO DEPENDENCIES
 * ========================================
 * 
 * STANDALONE VERSION - NO EXTERNAL INCLUDES
 * FOR FIXING 500 ERRORS
 * 
 * @author Pearce Stephens / Ecigdis Ltd
 */

// ERROR DEBUGGING
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ========================================
// DIRECT DATABASE CONNECTION - NO DEPENDENCIES
// ========================================
$link = mysqli_connect('localhost', 'jcepnzzkmj', 'wprKh9Jq63', 'jcepnzzkmj');
if (!$link) {
    die("Database connection failed: " . mysqli_connect_error());
}

// ========================================
// POST HANDLERS - WORKING FUNCTIONS
// ========================================
if (isset($_POST["run_transfer"])){
    try {
        $mode = $_POST["mode"] ?? 'simulate';
        $outlet_from = $_POST["outlet_from"] ?? '';
        $outlet_to = $_POST["outlet_to"] ?? '';
        
        // Validate mode
        if (!in_array($mode, ['simulate', 'live'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid mode']);
            exit;
        }
        
        // Build command
        $index_path = __DIR__ . '/index.php';
        if (!file_exists($index_path)) {
            echo json_encode(['success' => false, 'error' => 'Transfer engine not found']);
            exit;
        }
        
        $params = "action=run&simulate=" . ($mode === 'simulate' ? '1' : '0');
        if ($outlet_from) $params .= "&outlet_from=" . urlencode($outlet_from);
        if ($outlet_to) $params .= "&outlet_to=" . urlencode($outlet_to);
        
        // Simple execution
        $cmd = "cd " . __DIR__ . " && php index.php '{$params}' 2>&1";
        $output = shell_exec($cmd);
        
        echo json_encode([
            'success' => true,
            'command' => $cmd,
            'output' => $output ?: 'No output received'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (isset($_POST["get_outlets"])){
    try {
        $sql = "SELECT outlet_id, outlet_name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY outlet_name";
        $result = mysqli_query($link, $sql);
        
        $outlets = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $outlets[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'outlets' => $outlets]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if (isset($_POST["get_recent_transfers"])){
    try {
        $sql = "SELECT transfer_id, outlet_from, outlet_to, status, date_created 
                FROM stock_transfers 
                ORDER BY date_created DESC 
                LIMIT 10";
        
        $result = mysqli_query($link, $sql);
        $transfers = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $transfers[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'transfers' => $transfers]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// DEBUG STATUS
if (isset($_GET['debug'])) {
    echo "<h2>🔧 DEBUG STATUS</h2>";
    echo "Database: " . ($link ? "✅ Connected" : "❌ Failed") . "<br>";
    echo "Transfer Engine: " . (file_exists(__DIR__ . '/index.php') ? "✅ Found" : "❌ Missing") . "<br>";
    echo "Shell Exec: " . (function_exists('shell_exec') ? "✅ Available" : "❌ Disabled") . "<br>";
    echo "PHP Version: " . phpversion() . "<br>";
    echo "Directory: " . __DIR__ . "<br>";
    echo "<br><a href='?'>🔙 Back</a>";
    exit;
}

// Get outlets for dropdown
$outlets = [];
$sql = "SELECT outlet_id, outlet_name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY outlet_name";
$result = mysqli_query($link, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $outlets[] = $row;
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Emergency Transfer UI</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body style="padding: 20px;">

<div class="container">
    <div class="row">
        <div class="col-12">
            <h2>🛠️ Emergency Transfer System</h2>
            <p class="text-muted">Standalone version - no dependencies</p>
            
            <div class="card">
                <div class="card-header">
                    <strong>Transfer Execution</strong>
                    <a href="?debug=1" class="btn btn-sm btn-warning float-right">🔧 Debug</a>
                </div>
                <div class="card-body">
                    
                    <div class="row">
                        <div class="col-md-6">
                            
                            <div class="form-group">
                                <label>Mode:</label>
                                <select id="mode" class="form-control">
                                    <option value="simulate">Simulate (Safe)</option>
                                    <option value="live">Live Run (DANGER)</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>From Outlet:</label>
                                <select id="outlet_from" class="form-control">
                                    <option value="">Auto-Detection</option>
                                    <?php foreach($outlets as $outlet): ?>
                                    <option value="<?= $outlet['outlet_id'] ?>"><?= htmlspecialchars($outlet['outlet_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>To Outlet:</label>
                                <select id="outlet_to" class="form-control">
                                    <option value="">Auto-Detection</option>
                                    <?php foreach($outlets as $outlet): ?>
                                    <option value="<?= $outlet['outlet_id'] ?>"><?= htmlspecialchars($outlet['outlet_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button id="run_btn" class="btn btn-primary">🚀 Run Transfer</button>
                            
                        </div>
                        
                        <div class="col-md-6">
                            <h5>Recent Transfers</h5>
                            <div id="recent_transfers">
                                <p class="text-muted">Click refresh to load...</p>
                            </div>
                            <button id="refresh_btn" class="btn btn-info btn-sm">🔄 Refresh</button>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-12">
                            <h5>Output</h5>
                            <div id="output" style="background: #f8f9fa; padding: 15px; font-family: monospace; white-space: pre-wrap; min-height: 200px;">
                                Ready to execute transfers...
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    
    $('#run_btn').click(function() {
        var mode = $('#mode').val();
        var outlet_from = $('#outlet_from').val();
        var outlet_to = $('#outlet_to').val();
        
        $(this).prop('disabled', true).text('🔄 Running...');
        $('#output').text('Executing transfer...\n');
        
        $.post('', {
            run_transfer: '1',
            mode: mode,
            outlet_from: outlet_from,
            outlet_to: outlet_to
        })
        .done(function(response) {
            try {
                var data = JSON.parse(response);
                if (data.success) {
                    $('#output').text('SUCCESS\n\nCommand: ' + data.command + '\n\nOutput:\n' + data.output);
                } else {
                    $('#output').text('ERROR: ' + data.error);
                }
            } catch(e) {
                $('#output').text('Raw Response:\n' + response);
            }
        })
        .fail(function(xhr) {
            $('#output').text('AJAX ERROR: ' + xhr.status + ' - ' + xhr.statusText + '\n\n' + xhr.responseText);
        })
        .always(function() {
            $('#run_btn').prop('disabled', false).text('🚀 Run Transfer');
        });
    });
    
    $('#refresh_btn').click(function() {
        $(this).prop('disabled', true).text('Loading...');
        
        $.post('', { get_recent_transfers: '1' })
        .done(function(response) {
            try {
                var data = JSON.parse(response);
                if (data.success) {
                    var html = '';
                    if (data.transfers.length === 0) {
                        html = '<p class="text-muted">No transfers found</p>';
                    } else {
                        html = '<table class="table table-sm"><thead><tr><th>ID</th><th>From</th><th>To</th><th>Status</th><th>Date</th></tr></thead><tbody>';
                        data.transfers.forEach(function(t) {
                            html += '<tr><td>' + t.transfer_id + '</td><td>' + (t.outlet_from || 'N/A') + '</td><td>' + (t.outlet_to || 'N/A') + '</td><td>' + (t.status || 'N/A') + '</td><td><small>' + (t.date_created || 'N/A') + '</small></td></tr>';
                        });
                        html += '</tbody></table>';
                    }
                    $('#recent_transfers').html(html);
                } else {
                    $('#recent_transfers').html('<p class="text-danger">Error: ' + data.error + '</p>');
                }
            } catch(e) {
                $('#recent_transfers').html('<p class="text-danger">Parse error: ' + e.message + '</p>');
            }
        })
        .fail(function(xhr) {
            $('#recent_transfers').html('<p class="text-danger">AJAX Error: ' + xhr.status + '</p>');
        })
        .always(function() {
            $('#refresh_btn').prop('disabled', false).text('🔄 Refresh');
        });
    });
    
});
</script>

</body>
</html>
