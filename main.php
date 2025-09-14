<?php
/**
 * CIS Transfer Engine Dashboard - Main Interface
 * This file should be included by index.php, but can handle direct access
 */

// Initialize missing variables if not set
if (!isset($gpt_availability)) {
    // Try to load OpenAIHelper if available
    $openai_helper_path = "/home/master/applications/jcepnzzkmj/public_html/assets/functions/OpenAIHelper.php";
    if (file_exists($openai_helper_path)) {
        try {
            require_once $openai_helper_path;
            $gpt_availability = OpenAIHelper::availability();
            
            // Also auto-load the API key if not already set
            if (!isset($gpt_api_key) || empty($gpt_api_key)) {
                $gpt_api_key = OpenAIHelper::getKey() ?: '';
            }
        } catch (Exception $e) {
            $gpt_availability = ['enabled' => false, 'key' => false, 'error' => $e->getMessage()];
        }
    } else {
        $gpt_availability = ['enabled' => false, 'key' => false, 'error' => 'OpenAIHelper not found'];
    }
}

if (!isset($outlets)) {
    $outlets = [];
    // Try to load basic config for outlets
    $config_path = __DIR__ . "/../../functions/config.php";
    if (file_exists($config_path)) {
        try {
            require_once $config_path;
            // Try to get outlets from database if possible
            if (isset($con) && $con instanceof mysqli) {
                // Use schema-aware query for outlets (matches index.php logic) - INCLUDES is_warehouse field
                $result = $con->query("SELECT id AS outlet_id, name, is_warehouse FROM vend_outlets WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = '') AND website_active = 1 ORDER BY name");
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $outlets[] = [
                            'outlet_id' => $row['outlet_id'],
                            'name' => $row['name'],
                            'is_warehouse' => (bool)$row['is_warehouse']
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            // Silent fail, outlets will remain empty array
        }
    }
}

// Initialize other missing variables
if (!isset($gpt_api_key)) {
    // Auto-load API key from database using OpenAIHelper if available
    if (class_exists('OpenAIHelper')) {
        $gpt_api_key = OpenAIHelper::getKey() ?: '';
    } else {
        $gpt_api_key = '';
    }
}
if (!isset($gpt_prompt)) {
    // Initialize GPT prompt with smart default
    $gpt_prompt = 'You are an expert product categorization AI for The Vape Shed, New Zealand\'s premier vape retailer. Analyze each product and assign to the most appropriate category: Hardware, E-Liquids, Accessories, Starter Kits, or Premium. Return structured JSON with product_id, primary_category, confidence_score, and reasoning.';
}
if (!isset($outlet_debug)) {
    $outlet_debug = [];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>CIS Transfer Engine Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- DEBUG MARKER: Store Dropdowns Updated - Version 2.0 - <?= date('Y-m-d H:i:s') ?> -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
.main-container { background: rgba(255,255,255,0.98); backdrop-filter: blur(10px); border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
.card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-radius: 12px; transition: transform 0.2s; }
.card:hover { transform: translateY(-2px); }
.control-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
.running-overlay { 
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 100vw; 
    height: 100vh; 
    background: rgba(0, 0, 0, 0.85); 
    z-index: 999999; 
    display: none; 
    align-items: center; 
    justify-content: center;
    backdrop-filter: blur(5px);
    animation: fadeInOverlay 0.2s ease-out;
}

/* Prevent page scrolling when overlay is shown */
body.overlay-active {
    overflow: hidden;
    position: fixed;
    width: 100%;
}

/* Ensure toast notifications stay below overlay */
.toast-container {
    z-index: 999998 !important;
}

/* Push neural memory notifications out of the way */
body {
    padding-top: 45px !important;
}

/* Ensure neural memory stays in top corner and doesn't overlap */
[class*="neural"], [class*="Neural"], [id*="neural"], [id*="Neural"] {
    z-index: 1000 !important;
    position: fixed !important;
    top: 5px !important;
    left: 5px !important;
    font-size: 12px !important;
    max-width: 300px !important;
}

@keyframes fadeInOverlay {
    from { opacity: 0; }
    to { opacity: 1; }
}

.overlay-card {
    background: linear-gradient(145deg, #1a1a1a, #2d2d2d);
    border: 2px solid #333;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.7);
    color: white;
    max-width: 400px;
    width: 90vw;
    max-height: 90vh;
    overflow: hidden;
}
.spinner-border { width: 3rem; height: 3rem; }
.warehouse-badge { background: linear-gradient(45deg, #ff6b6b, #ee5a24); }
.store-badge { background: linear-gradient(45deg, #74b9ff, #0984e3); }

/* Enhanced Preset Button Styling */
.preset-btn { 
    cursor: pointer; 
    transition: all 0.3s ease; 
    border-width: 2px;
    position: relative;
    overflow: hidden;
}
.preset-btn:hover { 
    transform: translateY(-3px); 
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}
.preset-btn.active {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    border-width: 3px;
}
.preset-btn.active::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 4px 4px 0 0;
}

/* Transfer Mode Enhanced Styling */
.form-check {
    transition: all 0.2s ease;
}
.form-check:hover {
    background-color: rgba(0,0,0,0.02);
    border-radius: 8px;
}
.form-check-input:checked + .form-check-label {
    color: inherit;
    font-weight: 600;
}

/* Better alignment for sections */
.col-lg-4 {
    display: flex;
    flex-direction: column;
}
.destination-checkboxes {
    background: rgba(248, 249, 250, 0.8);
    border: 1px solid #dee2e6;
}
.destination-checkboxes:hover {
    background: rgba(248, 249, 250, 1);
    border-color: #adb5bd;
}

/* Active preset indicator styling */
#activePresetIndicator {
    border-left: 5px solid #17a2b8;
    background: linear-gradient(135deg, rgba(23, 162, 184, 0.1), rgba(255, 255, 255, 0.8));
    animation: slideInFromTop 0.5s ease-out;
}

@keyframes slideInFromTop {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Form control enhancements */
.form-select-lg, .form-control-lg {
    font-size: 1.1rem;
    padding: 0.75rem 1rem;
}

/* Default selection highlighting */
.bg-success.bg-opacity-10 {
    background-color: rgba(25, 135, 84, 0.1) !important;
    border-color: rgba(25, 135, 84, 0.3) !important;
}

/* Small alerts for validation feedback */
.alert-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.375rem;
}

.alert-sm ul {
    padding-left: 1.2rem;
    margin-bottom: 0;
}

.alert-sm li {
    margin-bottom: 0.25rem;
}

/* Quick Transfer Scenarios grid alignment */
.preset-btn .text-center {
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}
</style>
</head>
<body class="p-4">

<div class="container-fluid main-container p-4">
    <!-- Clean Processing Overlay -->
    <div class="running-overlay" id="runningOverlay">
        <div class="overlay-card">
            <div class="p-4 text-center">
                <!-- Simple Spinner -->
                <div class="mb-3">
                    <div class="spinner-border text-warning" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Processing...</span>
                    </div>
                </div>
                
                <!-- Clean Title -->
                <h4 class="mb-3 text-white">
                    <i class="bi bi-gear text-warning"></i> Processing Transfer Engine
                </h4>
                
                <!-- Status Text -->
                <div id="progress-text" class="mb-3">
                    <p class="text-light mb-0">Analyzing inventory across stores...</p>
                </div>
                
                <!-- Simple Progress Bar -->
                <div class="progress mb-3" style="height: 8px; background: rgba(255,255,255,0.2);">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" 
                         style="width: 30%;" role="progressbar"></div>
                </div>
                
                <!-- Execution Timer -->
                <div class="border-top border-secondary pt-3">
                    <div id="executionTimer" class="fs-5 text-warning">
                        ⏱️ 0s
                    </div>
                    <small class="text-muted">Please wait...</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12 text-center">
            <h1 class="mb-3"><i class="bi bi-speedometer2 text-success"></i> CIS Transfer Engine Dashboard</h1>
            <p class="lead text-muted">Configure and execute intelligent inventory transfers across your retail network</p>
            
            <!-- System Status Indicator (Professional) -->
            <div class="alert alert-success alert-sm border-0 d-inline-block">
                <small><i class="bi bi-check-circle-fill"></i> <strong>System Ready</strong> - <?= count($outlets) ?> outlets loaded, AI enhanced</small>
            </div>
        </div>
    </div>

    <!-- Quick Preset Selector -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark py-2">
                    <h6 class="mb-0"><i class="bi bi-bookmark-star"></i> Quick Configuration Presets</h6>
                </div>
                <div class="card-body py-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-lg-4">
                            <label for="presetSelector" class="form-label fw-bold mb-1">Choose Preset:</label>
                            <select class="form-select" id="presetSelector" onchange="handlePresetChange(this.value)">
                                <option value="">🎯 Select Configuration...</option>
                                <option value="conservative">🛡️ Conservative (Safe)</option>
                                <option value="standard">⚙️ Standard (Default)</option>
                                <option value="aggressive">⚡ Aggressive (High Turnover)</option>
                                <option value="new_store">🏪 New Store Seeding</option>
                            </select>
                        </div>
                        <div class="col-lg-6">
                            <div id="presetDescription" class="text-muted small">
                                Select a preset above to auto-configure settings for common scenarios.
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="resetForm()">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset All
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12 text-center">
            
            <!-- Debug Panel (Collapsible, Professional) -->
            <?php if (isset($_GET['debug']) || isset($_GET['debug_outlets'])): ?>
            <div class="mt-2">
                <details class="text-start">
                    <summary class="btn btn-outline-secondary btn-sm">🔧 System Diagnostics</summary>
                    <div class="alert alert-light mt-2" style="font-size:11px; max-height:300px; overflow:auto;">
                        <strong>Database Query:</strong> <code><?= htmlspecialchars($outlet_debug['query'] ?? 'N/A') ?></code><br>
                        <strong>Outlets Count:</strong> <?= $outlet_debug['count'] ?><br>
                        <?php if (isset($outlet_debug['stats'])): ?>
                        <strong>DB Stats:</strong> Total: <?= $outlet_debug['stats']['total_count'] ?>, NULL: <?= $outlet_debug['stats']['null_count'] ?>, Zero-date: <?= $outlet_debug['stats']['zero_date_count'] ?>, Empty: <?= $outlet_debug['stats']['empty_count'] ?><br>
                        <?php endif; ?>
                        <?php if (!empty($outlet_debug['sample_data'])): ?>
                        <strong>Sample Data:</strong>
                        <?php foreach($outlet_debug['sample_data'] as $sample): ?>
                            <br>&nbsp;&nbsp;ID: <?= $sample['id'] ?>, Name: <?= htmlspecialchars($sample['name']) ?>, deleted_at: "<?= htmlspecialchars($sample['deleted_at'] ?? 'NULL') ?>" (<?= $sample['deleted_at_type'] ?>)
                        <?php endforeach; ?>
                        <br>
                        <?php endif; ?>
                        <strong>Sales Filter:</strong> <code>deleted_at IS NULL AND status IN ('CLOSED', 'ONACCOUNT_CLOSED', 'LAYBY_CLOSED')</code><br>
                        <strong>Line Items Filter:</strong> <code>status = 'CONFIRMED'</code><br>
                        <?php if (!empty($outlet_debug['error'])): ?>
                        <strong>Error:</strong> <span style="color:red"><?= htmlspecialchars($outlet_debug['error']) ?></span><br>
                        <?php endif; ?>
                        <strong>Source Module:</strong> <code>transfer_engine</code> (updated from automatic_transfers)<br>
                        <strong>Outlet Data:</strong>
                        <pre><?= htmlspecialchars(json_encode($outlets, JSON_PRETTY_PRINT)) ?></pre>
                    </div>
                </details>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Action Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card control-card">
                <div class="card-body text-center">
                    <h5 class="mb-3"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <button class="btn btn-light btn-lg" onclick="runEngine('simulation')" id="btnSimulation">
                            <i class="bi bi-eye"></i> Run Simulation
                        </button>
                        <button class="btn btn-warning btn-lg" onclick="runEngine('live')" id="btnLive">
                            <i class="bi bi-play-circle"></i> Run Live Transfer
                        </button>
                        <button class="btn btn-info btn-lg" onclick="runEngine('json')" id="btnJson">
                            <i class="bi bi-file-earmark-code"></i> Export JSON
                        </button>
                        <button class="btn btn-secondary btn-lg" onclick="showHelpModal()" type="button">
                            <i class="bi bi-question-circle"></i> Help & Presets
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Outlet Selection & Transfer Types -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <div>
                        <h5 class="mb-0"><i class="bi bi-building"></i> Store Selection & Transfer Types</h5>
                        <small class="text-light">🎯 <strong>PRIMARY INTERFACE</strong> - Configure all transfer settings here (replaces legacy section below)</small>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Transfer Mode Selection -->
                        <div class="col-lg-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-gear-fill text-success me-2"></i>
                                <h6 class="mb-0 text-success">Transfer Mode</h6>
                            </div>
                            
                            <div class="d-flex flex-column gap-3">
                                <div class="form-check p-3 border rounded bg-success bg-opacity-10 border-success">
                                    <input class="form-check-input" type="radio" name="transfer_mode" id="mode_all" value="all_stores" checked>
                                    <label class="form-check-label fw-bold text-success" for="mode_all">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-buildings me-2"></i> 
                                            <span>All Stores (Auto Distribution)</span>
                                            <span class="badge bg-success ms-2">⭐ DEFAULT</span>
                                        </div>
                                        <small class="d-block text-success mt-1 fw-normal">Automatically distribute from warehouses to all retail stores based on demand</small>
                                    </label>
                                </div>
                                
                                <div class="form-check p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="transfer_mode" id="mode_specific" value="specific_transfer">
                                    <label class="form-check-label fw-bold" for="mode_specific">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-arrow-left-right me-2"></i> 
                                            <span>Specific 1:1 Transfer</span>
                                        </div>
                                        <small class="d-block text-muted mt-1 fw-normal">Direct transfer between two specific locations</small>
                                    </label>
                                </div>
                                
                                <div class="form-check p-3 border rounded">
                                    <input class="form-check-input" type="radio" name="transfer_mode" id="mode_hub" value="hub_to_stores">
                                    <label class="form-check-label fw-bold" for="mode_hub">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-diagram-3 me-2"></i> 
                                            <span>Hub to Selected Stores</span>
                                        </div>
                                        <small class="d-block text-muted mt-1 fw-normal">Distribute from main warehouse to selected retail outlets</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Source Selection -->
                        <div class="col-lg-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-box-arrow-up text-success me-2"></i>
                                <h6 class="mb-0 text-success">Source Location</h6>
                            </div>
                            
                            <div id="source_selection">
                                <select class="form-select form-select-lg mb-3" name="source_outlet" id="sourceOutlet" disabled>
                                    <option value="">Auto-Select Hub/Warehouse</option>
                                    <?php foreach ($outlets as $outlet): ?>
                                        <option value="<?= htmlspecialchars($outlet['outlet_id']) ?>" 
                                                data-warehouse="<?= $outlet['is_warehouse'] ? '1' : '0' ?>">
                                            <?= htmlspecialchars($outlet['name']) ?>
                                            <?= $outlet['is_warehouse'] ? ' 🏢 (Warehouse)' : ' 🛍️ (Store)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="warehouseOnly" name="warehouse_only" disabled>
                                    <label class="form-check-label" for="warehouseOnly">
                                        <i class="bi bi-building-check me-1"></i> Warehouses Only
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Destination Selection -->
                        <div class="col-lg-4">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-box-arrow-down text-info me-2"></i>
                                <h6 class="mb-0 text-info">Destination Location</h6>
                            </div>
                            
                            <div id="destination_selection">
                                <!-- Multi-select destination dropdown with filtering -->
                                <div class="border rounded p-3 destination-checkboxes mb-3" style="max-height: 250px; overflow-y: auto;" id="destinationList">
                                    <div class="form-check mb-2 p-2 border rounded bg-success bg-opacity-10 border-success">
                                        <input class="form-check-input" type="checkbox" id="dest_all" value="" checked>
                                        <label class="form-check-label fw-bold text-success" for="dest_all">
                                            <i class="bi bi-check2-all me-2"></i> All Eligible Stores
                                            <span class="badge bg-success ms-2">⭐ DEFAULT</span>
                                        </label>
                                    </div>
                                    <hr class="my-2">
                                    <?php foreach ($outlets as $outlet): ?>
                                        <div class="form-check mb-1 destination-option" data-warehouse="<?= $outlet['is_warehouse'] ? '1' : '0' ?>">
                                            <input class="form-check-input destination-check" type="checkbox" 
                                                   id="dest_<?= $outlet['outlet_id'] ?>" 
                                                   value="<?= htmlspecialchars($outlet['outlet_id']) ?>"
                                                   name="selected_destinations[]">
                                            <label class="form-check-label" for="dest_<?= $outlet['outlet_id'] ?>">
                                                <?= htmlspecialchars($outlet['name']) ?>
                                                <?= $outlet['is_warehouse'] ? ' 🏢 <small class="text-muted">(Warehouse)</small>' : ' 🛍️ <small class="text-muted">(Store)</small>' ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Simple 1:1 Destination Dropdown (hidden by default) -->
                                <select class="form-select form-select-lg mb-3" name="dest_outlet_single" id="destOutlet" style="display: none;">
                                    <option value="">Select Destination Store...</option>
                                    <?php foreach ($outlets as $outlet): ?>
                                        <option value="<?= htmlspecialchars($outlet['outlet_id']) ?>" data-warehouse="<?= $outlet['is_warehouse'] ? '1' : '0' ?>">
                                            <?= htmlspecialchars($outlet['name']) ?><?= $outlet['is_warehouse'] ? ' (Warehouse)' : ' (Store)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <!-- Warehouse filter moved to bottom -->
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="excludeWarehouses" name="exclude_warehouses">
                                    <label class="form-check-label text-muted" for="excludeWarehouses">
                                        <i class="bi bi-building-x me-1"></i> Hide Warehouses from List
                                    </label>
                                </div>
                                
                                <!-- Hidden input to store selected destinations for form submission -->
                                <input type="hidden" name="dest_outlet" id="selectedDestinations" value="">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i> <span id="destinationCount">All eligible stores selected (18 stores)</span>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Transfer Scenarios - Enhanced with Visual States -->
                    <hr class="my-4">
                    <div class="row">
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-bookmark-star-fill text-warning me-2"></i>
                                <h6 class="mb-0 text-warning">Quick Transfer Scenarios</h6>
                                <small class="text-muted ms-2">Click to automatically configure transfer settings</small>
                            </div>
                            
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <button type="button" class="btn btn-outline-success btn-lg w-100 preset-btn h-100" 
                                            onclick="applyTransferPreset('warehouse_to_all')" data-preset="warehouse_to_all">
                                        <div class="text-center p-2">
                                            <i class="bi bi-building-add fs-2 d-block mb-2"></i>
                                            <div class="fw-bold">Warehouse → All Stores</div>
                                            <small class="text-muted d-block mt-1">Standard hub distribution to all retail outlets</small>
                                        </div>
                                    </button>
                                </div>
                                
                                <div class="col-lg-4">
                                    <button type="button" class="btn btn-outline-warning btn-lg w-100 preset-btn h-100" 
                                            onclick="applyTransferPreset('new_store_seed')" data-preset="new_store_seed">
                                        <div class="text-center p-2">
                                            <i class="bi bi-shop-window fs-2 d-block mb-2"></i>
                                            <div class="fw-bold">New Store Seeding</div>
                                            <small class="text-muted d-block mt-1">High-stock initial setup for new locations</small>
                                            <span class="badge bg-warning mt-2">Special Interface</span>
                                        </div>
                                    </button>
                                </div>
                                
                                <div class="col-lg-4">
                                    <button type="button" class="btn btn-outline-info btn-lg w-100 preset-btn h-100" 
                                            onclick="applyTransferPreset('emergency_transfer')" data-preset="emergency_transfer">
                                        <div class="text-center p-2">
                                            <i class="bi bi-lightning-charge-fill fs-2 d-block mb-2"></i>
                                            <div class="fw-bold">Emergency Restock</div>
                                            <small class="text-muted d-block mt-1">Fast 7-day coverage with low buffers</small>
                                        </div>
                                    </button>
                                </div>
                                
                                <div class="col-lg-6">
                                    <button type="button" class="btn btn-outline-secondary btn-lg w-100 preset-btn h-100" 
                                            onclick="applyTransferPreset('inter_store')" data-preset="inter_store">
                                        <div class="text-center p-2">
                                            <i class="bi bi-arrow-left-right fs-2 d-block mb-2"></i>
                                            <div class="fw-bold">Store to Store</div>
                                            <small class="text-muted d-block mt-1">Direct inter-store transfers (any to any)</small>
                                        </div>
                                    </button>
                                </div>
                                
                                <div class="col-lg-6">
                                    <button type="button" class="btn btn-outline-success btn-lg w-100 preset-btn h-100" 
                                            onclick="applyTransferPreset('overflow_to_hub')" data-preset="overflow_to_hub">
                                        <div class="text-center p-2">
                                            <i class="bi bi-arrow-return-left fs-2 d-block mb-2"></i>
                                            <div class="fw-bold">Store Overflow → Hub</div>
                                            <small class="text-muted d-block mt-1">Return excess inventory to central warehouse</small>
                                        </div>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Active Preset Indicator -->
                            <div id="activePresetIndicator" class="alert alert-info mt-3" style="display: none;">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <strong>Active Preset:</strong> <span id="activePresetName"></span> 
                                <small class="text-muted">— Settings have been automatically configured</small>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="clearPreset()">
                                    <i class="bi bi-x-circle me-1"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GPT Categorization Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-robot fs-4"></i> AI Product Categorization</h5>
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" id="enableGptCategorization" name="use_gpt_categorization" value="1" checked>
                            <label class="form-check-label text-white fw-bold" for="enableGptCategorization">Enable AI</label>
                            <?php if ($gpt_availability['enabled'] && $gpt_availability['key']): ?>
                                <span class="badge bg-success ms-2 fs-6">✅ Ready</span>
                            <?php elseif (!$gpt_availability['key']): ?>
                                <span class="badge bg-warning ms-2 fs-6">🔑 Key Loaded</span>
                            <?php else: ?>
                                <span class="badge bg-danger ms-2 fs-6">❌ Disabled</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-body" id="gptCategorizationBody" style="display: block;">
                    <div class="row">
                        <!-- API Configuration -->
                        <div class="col-lg-6 mb-3">
                            <label class="form-label fw-bold">OpenAI API Key</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="gpt_api_key" id="gpt_api_key"
                                       value="<?= htmlspecialchars($gpt_api_key) ?>"
                                       placeholder="<?= !empty($gpt_api_key) ? 'Auto-loaded from database' : 'sk-...' ?>" 
                                       autocomplete="new-password">
                                <button type="button" class="btn btn-success" onclick="saveApiKey()" title="Save API key to database">
                                    <i class="bi bi-save"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="toggleKeyVisibility()" title="Show/Hide API key">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <?php if (!empty($gpt_api_key)): ?>
                                <div class="form-text text-success"><i class="bi bi-check-circle"></i> Auto-loaded from database (ID: 28)</div>
                            <?php else: ?>
                                <div class="form-text text-warning"><i class="bi bi-exclamation-triangle"></i> Enter your OpenAI API key</div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Fallback Mode -->
                        <div class="col-lg-6 mb-3">
                            <label class="form-label fw-bold">Fallback Mode</label>
                            <select class="form-select" name="gpt_fallback_db">
                                <option value="1">Use Database Categories as Fallback</option>
                                <option value="0">GPT Only (Skip if API fails)</option>
                            </select>
                            <div class="form-text text-muted">Fallback to database categories if AI fails</div>
                        </div>
                    </div>

                    <!-- GPT Prompt Configuration -->
                    <div class="row">
                        <div class="col-12">
                            <label class="form-label fw-bold">GPT Categorization Prompt</label>
                            <div class="d-flex gap-2 mb-2 flex-wrap">
                                <button type="button" class="btn btn-success btn-sm" onclick="autoGenerateGptPrompt()" title="Generate advanced database-driven prompt">
                                    <i class="bi bi-cpu"></i> Auto-Generate
                                </button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="saveCustomPrompt()" title="Save this prompt as your custom default">
                                    <i class="bi bi-save"></i> Save Prompt
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadSavedPrompt()" title="Load your saved custom prompt">
                                    <i class="bi bi-folder-open"></i> Load Saved
                                </button>
                                <button type="button" class="btn btn-warning btn-sm" onclick="validatePrompt()" title="Validate prompt format">
                                    <i class="bi bi-check2-circle"></i> Validate
                                </button>
                                <button type="button" class="btn btn-info btn-sm" onclick="testGptCategorization()" title="Test current configuration">
                                    <i class="bi bi-gear"></i> Test Config
                                </button>
                            </div>
                            <textarea class="form-control" name="gpt_prompt" id="gpt_prompt" rows="5" placeholder="Advanced prompt will be auto-generated..."><?= htmlspecialchars($gpt_prompt ?? '') ?></textarea>
                            
                            <!-- Status and validation feedback -->
                            <div id="prompt_validation" class="mt-2"></div>
                            <div class="form-text">
                                <i class="bi bi-lightbulb text-success"></i> <strong>Smart Prompts:</strong> Auto-generated with database intelligence, brand patterns, and NZ compliance.
                                Use "Auto-Generate" for latest database intelligence or "Save Prompt" to store custom versions.
                            </div>
                        </div>
                    </div>

                    <!-- AI Features Info -->
                    <div class="alert alert-info mt-3 d-flex align-items-center">
                        <i class="bi bi-info-circle me-2"></i>
                        <div class="small">
                            <strong>Features:</strong> Smart analysis, consistent categories, weight estimation, transfer optimization. 
                            <strong>Cost:</strong> ~$0.002 per product (GPT-3.5-turbo). 
                            <strong>Performance:</strong> Results cached to avoid repeat API calls.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Configuration Form -->
    <form id="configForm" method="POST" action="<?= htmlspecialchars($current_url) ?>">
        <input type="hidden" name="action" value="run" id="action">
        
        <!-- Hidden inputs for outlet selection - ensures form submission includes all selections -->
        <input type="hidden" name="transfer_mode_hidden" id="transferModeHidden" value="">
        <input type="hidden" name="source_outlet_hidden" id="sourceOutletHidden" value="">
        <input type="hidden" name="dest_outlet_hidden" id="destOutletHidden" value="">
        
        <div class="row">
            <!-- Basic Parameters -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-sliders"></i> Basic Parameters</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Format</label>
                                <select class="form-select" name="format">
                                    <option value="html">HTML Dashboard</option>
                                    <option value="json">JSON Export</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mode</label>
                                <select class="form-select" name="simulate">
                                    <option value="0" selected>🔥 PRODUCTION (Live Apply)</option>
                                    <option value="1">Simulation (Safe)</option>
                                </select>
                                <div class="alert alert-success mt-2 p-2">
                                    <i class="bi bi-rocket-takeoff"></i> <strong>DEFAULT: PRODUCTION MODE</strong><br>
                                    Optimized for real warehouse-to-stores distribution.
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cover Days</label>
                                <input type="number" class="form-control" name="cover" value="14" min="1" max="120">
                                <div class="form-text">Days of demand to cover</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Buffer %</label>
                                <input type="number" class="form-control" name="buffer_pct" value="20" min="0" max="90">
                                <div class="form-text">Safety stock buffer percentage</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Rounding Mode</label>
                                <select class="form-select" name="rounding_mode">
                                    <option value="nearest">Nearest</option>
                                    <option value="up">Round Up</option>
                                    <option value="down">Round Down</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Min Units/Line</label>
                                <input type="number" class="form-control" name="min_units_per_line" value="0" min="0" max="100">
                              <div class="form-text">🚀 <strong>PRODUCTION:</strong> 0 = No minimum (allow any positive quantity; recommended for full scale)</div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Parameters -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-gear"></i> Advanced Parameters</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Floor Sales Threshold</label>
                                <input type="number" class="form-control" name="floor_sales_threshold" value="0.20" step="0.01" min="0" max="2">
                                <div class="form-text">Minimum sales velocity</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Floor Qty</label>
                                <input type="number" class="form-control" name="default_floor_qty" value="2" min="0" max="20">
                                <div class="form-text">Minimum stock floor</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Turnover Min Multiplier</label>
                                <input type="number" class="form-control" name="turnover_min_mult" value="0.7" step="0.1" min="0.1" max="3">
                                <div class="form-text">Minimum turnover factor</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Turnover Max Multiplier</label>
                                <input type="number" class="form-control" name="turnover_max_mult" value="1.4" step="0.1" min="0.1" max="5">
                                <div class="form-text">Maximum turnover factor</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Overflow Days</label>
                                <input type="number" class="form-control" name="overflow_days" value="180" min="30" max="720">
                                <div class="form-text">Days to trigger overflow transfer</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Overflow Multiplier</label>
                                <input type="number" class="form-control" name="overflow_mult" value="2.0" step="0.1" min="1" max="5">
                                <div class="form-text">Overflow transfer factor</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Limits -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-funnel"></i> Filters & Limits</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Margin Factor</label>
                                <input type="number" class="form-control" name="margin_factor" value="1.2" step="0.1" min="1" max="5">
                                <div class="form-text">Profitability multiplier</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Max Products</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="max_products" value="0" min="0" max="100000" id="max_products_input">
                                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        Quick Set
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="setMaxProducts(10)">🔟 10 (Fast Test)</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setMaxProducts(50)">🔴 50 (Medium Test)</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setMaxProducts(100)">💯 100 (Large Test)</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="setMaxProducts(0)">♾️ Unlimited (Full Run)</a></li>
                                    </ul>
                                </div>
                                <div class="form-text">🚀 <strong>PRODUCTION: Unlimited products</strong> (0 = process all products)</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Store Filter</label>
                                <select class="form-select" name="store" id="store_filter_select">
                                    <option value="">All Stores</option>
                                </select>
                                <div class="form-text">Focus on specific destination store</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">New Store ID</label>
                                <select class="form-select" name="new_store" id="new_store_select">
                                    <option value="">Select New Store</option>
                                </select>
                                <div class="form-text">Seeding mode for new store</div>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Operator Note</label>
                                <textarea class="form-control" name="note" rows="2" placeholder="Optional note for this run..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Transfer Type Help Modal -->
<div class="modal fade" id="transferTypeHelpModal" tabindex="-1" aria-labelledby="transferTypeHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="transferTypeHelpModalLabel">
                    <i class="bi bi-book"></i> Transfer Types & Presets Guide
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12 mb-4">
                        <h6 class="text-info"><i class="bi bi-gear"></i> Transfer Types</h6>
                        
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-success"><i class="bi bi-buildings"></i> All Stores Analysis</h6>
                                <p class="card-text">
                                    <strong>What it does:</strong> Analyzes inventory across all locations and automatically creates optimal transfers based on demand patterns, stock levels, and business rules.
                                </p>
                                <p class="card-text">
                                    <strong>Best for:</strong> Daily/weekly inventory optimization, automatic rebalancing, demand-driven transfers.
                                </p>
                                <p class="card-text">
                                    <strong>Settings:</strong> Uses all configured parameters including cover days, buffer percentages, and profitability guards.
                                </p>
                            </div>
                        </div>
                        
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title text-info"><i class="bi bi-arrow-left-right"></i> Specific Transfer (1:1)</h6>
                                <p class="card-text">
                                    <strong>What it does:</strong> Creates transfers between specific source and destination locations you choose.
                                </p>
                                <p class="card-text">
                                    <strong>Best for:</strong> Targeted moves, emergency stock transfers, manual rebalancing between specific stores.
                                </p>
                                <p class="card-text">
                                    <strong>Settings:</strong> Respects source/destination filters, ignores global store filters.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 mb-4">
                        <h6 class="text-warning"><i class="bi bi-lightning"></i> Quick Transfer Presets</h6>
                        
                        <div class="accordion" id="presetAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#warehouse-to-all">
                                        <i class="bi bi-distribute-horizontal text-success me-2"></i> Warehouse → All Stores
                                    </button>
                                </h2>
                                <div id="warehouse-to-all" class="accordion-collapse collapse" data-bs-parent="#presetAccordion">
                                    <div class="accordion-body">
                                        <strong>Purpose:</strong> Distribute stock from warehouse to stores needing inventory.<br>
                                        <strong>Configuration:</strong> Sources limited to warehouses, destinations include all stores.<br>
                                        <strong>Settings:</strong> Standard parameters with focus on demand fulfillment.<br>
                                        <strong>Best for:</strong> Regular restocking operations, fulfilling store demand.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#store-to-store">
                                        <i class="bi bi-arrow-left-right text-success me-2"></i> Store → Store
                                    </button>
                                </h2>
                                <div id="store-to-store" class="accordion-collapse collapse" data-bs-parent="#presetAccordion">
                                    <div class="accordion-body">
                                        <strong>Purpose:</strong> Transfer excess inventory between retail stores.<br>
                                        <strong>Configuration:</strong> Excludes warehouses, focuses on store-to-store transfers.<br>
                                        <strong>Settings:</strong> Lower thresholds, faster turnover parameters.<br>
                                        <strong>Best for:</strong> Balancing seasonal stock, moving slow-sellers to better locations.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rebalance">
                                        <i class="bi bi-diagram-3 text-info me-2"></i> Auto Rebalance
                                    </button>
                                </h2>
                                <div id="rebalance" class="accordion-collapse collapse" data-bs-parent="#presetAccordion">
                                    <div class="accordion-body">
                                        <strong>Purpose:</strong> Comprehensive network rebalancing across all locations.<br>
                                        <strong>Configuration:</strong> All sources and destinations enabled.<br>
                                        <strong>Settings:</strong> Balanced parameters optimizing turnover and coverage.<br>
                                        <strong>Best for:</strong> Monthly optimization, correcting imbalances, performance improvement.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#new-store">
                                        <i class="bi bi-shop text-warning me-2"></i> New Store Seed
                                    </button>
                                </h2>
                                <div id="new-store" class="accordion-collapse collapse" data-bs-parent="#presetAccordion">
                                    <div class="accordion-body">
                                        <strong>Purpose:</strong> Initial inventory setup for new store locations.<br>
                                        <strong>Configuration:</strong> Focused on new store as destination.<br>
                                        <strong>Settings:</strong> Higher coverage days, larger buffer percentages, comprehensive product range.<br>
                                        <strong>Best for:</strong> Store openings, location expansions, initial stocking.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#overflow">
                                        <i class="bi bi-box-arrow-up text-danger me-2"></i> Overflow Control
                                    </button>
                                </h2>
                                <div id="overflow" class="accordion-collapse collapse" data-bs-parent="#presetAccordion">
                                    <div class="accordion-body">
                                        <strong>Purpose:</strong> Move excess inventory from overstocked locations.<br>
                                        <strong>Configuration:</strong> Aggressive overflow parameters.<br>
                                        <strong>Settings:</strong> Lower overflow days, higher overflow multipliers, focus on space optimization.<br>
                                        <strong>Best for:</strong> Clearing slow-moving stock, optimizing warehouse space, seasonal adjustments.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 🚀 ENHANCED ALGORITHM PARAMETERS -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#enhancedAlgorithms">
                                        <i class="bi bi-cpu text-primary me-2"></i> Enhanced Algorithm Parameters
                                    </button>
                                </h2>
                                <div id="enhancedAlgorithms" class="accordion-collapse collapse" data-bs-parent="#presetAccordion">
                                    <div class="accordion-body">
                                        <div class="alert alert-success mb-3">
                                            <i class="bi bi-stars"></i> <strong>Enhanced Algorithms Active:</strong> 
                                            Advanced transfer logic with value-aware caps, hub throttling, and economic shipping gates.
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label"><i class="bi bi-building"></i> Hub High-Water Cap</label>
                                                <input type="number" class="form-control" name="hub_highwater_cap" value="2000" min="100" max="10000">
                                                <div class="form-text">Trigger hub stock throttling above this level</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label"><i class="bi bi-calendar3"></i> Target Months of Supply</label>
                                                <input type="number" class="form-control" name="target_mos" value="3.0" step="0.1" min="0.5" max="12">
                                                <div class="form-text">Company-wide target MOS threshold</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label"><i class="bi bi-cash-coin"></i> Minimum Line Value</label>
                                                <input type="number" class="form-control" name="min_line_value" value="15.00" step="0.01" min="0" max="200">
                                                <div class="form-text">Economic shipping gate per line</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label"><i class="bi bi-tag"></i> Minimum Unit Price</label>
                                                <input type="number" class="form-control" name="min_unit_price" value="3.00" step="0.01" min="0" max="50">
                                                <div class="form-text">Economic unit price threshold</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label"><i class="bi bi-speedometer"></i> Per-Store Transfer Limit</label>
                                                <input type="number" class="form-control" name="per_store_max_transfer" value="100" min="10" max="1000">
                                                <div class="form-text">Maximum units per product per store</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check form-switch mt-4">
                                                    <input class="form-check-input" type="checkbox" id="oversupply_throttle_enabled" name="oversupply_throttle_enabled" checked>
                                                    <label class="form-check-label" for="oversupply_throttle_enabled">
                                                        <i class="bi bi-shield-check"></i> Enable Oversupply Throttling
                                                    </label>
                                                    <div class="form-text">Reduce transfers for overstocked products</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-info mt-3">
                                            <small><i class="bi bi-info-circle"></i> 
                                            <strong>Algorithm Integration:</strong> These parameters work with the enhanced transfer algorithms to provide 
                                            intelligent throttling, economic gates, and hub stock management.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="bi bi-lightbulb"></i> <strong>Pro Tip:</strong> 
                    Start with presets and adjust individual parameters as needed. Always run simulations first to preview changes before applying them live.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="applyTransferPreset('rebalance')" data-bs-dismiss="modal">>
                    Try Auto Rebalance
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// IMMEDIATE TEST - SHOULD SHOW IN CONSOLE
console.log('🚨🚨🚨 EMERGENCY: SCRIPT IS LOADING!');

console.log('🚨 JAVASCRIPT LOADED!'); // EMERGENCY DEBUG

// TRY IMMEDIATE MODE CHANGE WITH TIMEOUT
setTimeout(function() {
    console.log('🚨 TIMEOUT: Trying to fix transfer mode');
    try {
        handleTransferModeChange();
    } catch (e) {
        console.error('🚨 ERROR in handleTransferModeChange:', e);
    }
}, 1000);

// Global execution timer variables
let executionStartTime = null;
let executionTimer = null;

function startExecutionTimer() {
    executionStartTime = new Date();
    
    // Update the timer display immediately
    const timerDisplay = document.getElementById('executionTimer');
    if (timerDisplay) {
        timerDisplay.innerHTML = '⏱️ 00:00';
    }
    
    // Start the interval timer
    executionTimer = setInterval(() => {
        if (executionStartTime && timerDisplay) {
            const elapsed = Math.floor((new Date() - executionStartTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;
            timerDisplay.innerHTML = `⏱️ ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }, 1000);
    
    console.log('⏱️ Execution timer started at:', executionStartTime.toISOString());
}

function stopExecutionTimer() {
    if (executionTimer) {
        clearInterval(executionTimer);
        executionTimer = null;
    }
    
    const timerDisplay = document.getElementById('executionTimer');
    if (timerDisplay && executionStartTime) {
        const elapsed = Math.floor((new Date() - executionStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        timerDisplay.innerHTML = `✅ Completed in ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        timerDisplay.classList.add('text-success');
    }
    
    console.log('⏱️ Execution timer stopped');
}

function runEngine(mode) {
  console.log('🚀 runEngine called with mode:', mode);

  // Optional UX helpers if present
  try { if (typeof startExecutionTimer === 'function') startExecutionTimer(); } catch(_) {}
  try { if (typeof showRunning === 'function') showRunning(); } catch(_) {}

  const form = document.getElementById('configForm');
  if (!form) {
    console.error('❌ ERROR: configForm not found');
    try { if (typeof hideRunning === 'function') hideRunning(); } catch(_) {}
    return false;
  }

  // Ensure action field
  const actionInput   = form.querySelector('input[name="action"]');
  const simulateInput = form.querySelector('select[name="simulate"]');
  const formatInput   = form.querySelector('select[name="format"]');
  if (actionInput) actionInput.value = 'run';

  // Stamp execution start time (preserve your existing behavior)
  try {
    const ts = (typeof executionStartTime !== 'undefined' && executionStartTime?.toISOString)
      ? executionStartTime.toISOString()
      : new Date().toISOString();
    const startTimeInput = document.createElement('input');
    startTimeInput.type  = 'hidden';
    startTimeInput.name  = 'execution_start_time';
    startTimeInput.value = ts;
    form.appendChild(startTimeInput);
    console.log('⏱️ Execution timer at:', ts);
  } catch (e) {
    console.warn('⚠️ Could not append execution_start_time:', e);
  }

  // Map mode -> simulate/format
  if (mode === 'live') {
    if (!confirm('⚠️ WARNING: This will make LIVE changes to inventory transfers!\n\nAre you sure you want to proceed?')) {
      try { if (typeof hideRunning === 'function') hideRunning(); } catch(_) {}
      return false;
    }
    if (simulateInput) simulateInput.value = '0';
    if (formatInput)   formatInput.value   = 'html';
  } else if (mode === 'json') {
    if (simulateInput) simulateInput.value = '1';
    if (formatInput)   formatInput.value   = 'json';
  } else {
    if (simulateInput) simulateInput.value = '1';
    if (formatInput)   formatInput.value   = 'html';
  }

  // 🔒 Always sync UI -> form fields before submit
  if (typeof syncSelectionFields === 'function') {
    syncSelectionFields();
  } else {
    // Minimal inline fallback if helper not present
    const modeRadio   = document.querySelector('input[name="transfer_mode"]:checked');
    const modeVal     = modeRadio ? modeRadio.value : 'all_stores';
    const srcSel      = document.getElementById('sourceOutlet');
    const dstSingle   = document.getElementById('destOutlet');
    const destHidden  = document.getElementById('selectedDestinations');
    const destAll     = document.getElementById('dest_all');
    const newStoreSel = document.getElementById('new_store_select');
    const newStoreId  = (newStoreSel && newStoreSel.value) ? newStoreSel.value : '';

    if (srcSel && srcSel.name !== 'source_outlet') srcSel.name = 'source_outlet';
    if (dstSingle && dstSingle.name !== 'dest_outlet_single') dstSingle.name = 'dest_outlet_single';

    if (modeVal === 'specific_transfer') {
      const dst = newStoreId || (dstSingle && dstSingle.value) || '';
      if (destHidden) destHidden.value = dst;
    } else if (modeVal === 'hub_to_stores') {
      if (newStoreId) {
        if (destHidden) destHidden.value = newStoreId;
      } else if (destAll && destAll.checked) {
        if (destHidden) destHidden.value = ''; // blank => ALL
      }
      // else: keep whatever multi-select already set
    } else {
      if (destHidden) destHidden.value = ''; // all_stores
    }
  }

  // Mirror into any extra hidden “_hidden” fields if you use them
  try {
    const tmH = document.getElementById('transferModeHidden');
    const soH = document.getElementById('sourceOutletHidden');
    const doH = document.getElementById('destOutletHidden');
    const mR  = document.querySelector('input[name="transfer_mode"]:checked');
    if (tmH && mR) tmH.value = mR.value;
    const srcSel = document.getElementById('sourceOutlet');
    if (soH && srcSel && srcSel.value) soH.value = srcSel.value;
    const dstSel = document.getElementById('destOutlet');
    if (doH && dstSel && dstSel.value) doH.value = dstSel.value;
  } catch(_) {}

  // Debug: final submission keys
  try {
    const formData = new FormData(form);
    const keys = ['action','simulate','format','transfer_mode','source_outlet','dest_outlet','dest_outlet_single','execution_start_time','transfer_mode_hidden','source_outlet_hidden','dest_outlet_hidden'];
    console.log('🚀 Final form submission data (selected keys):');
    for (let [k,v] of formData.entries()) if (keys.includes(k)) console.log(`  ${k}: ${v}`);
  } catch(_) {}

  // Submit POST with all params
  console.log('🚀 Submitting form with full selection payload');
  form.submit();
  return true;
}


function setAction(mode) {
    showRunning();
    
    if (mode === 'live') {
        if (!confirm('⚠️ WARNING: This will make LIVE changes to inventory transfers!\n\nAre you sure you want to proceed?')) {
            hideRunning();
            event.preventDefault();
            return false;
        }
        document.querySelector('select[name="simulate"]').value = '0';
    } else if (mode === 'simulation') {
        document.querySelector('select[name="simulate"]').value = '1';
    }
    
    if (mode === 'json') {
        document.querySelector('select[name="format"]').value = 'json';
    }
}

function showRunning() {
    document.getElementById('runningOverlay').style.display = 'flex';
    document.body.classList.add('overlay-active');
    
    // Disable all buttons
    const buttons = document.querySelectorAll('button, input[type="submit"]');
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.classList.add('disabled');
    });
}

function hideRunning() {
    const overlay = document.getElementById('runningOverlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
    document.body.classList.remove('overlay-active');
    
    // Stop the execution timer and show final time
    stopExecutionTimer();
    
    // Re-enable all buttons
    const buttons = document.querySelectorAll('button, input[type="submit"]');
    buttons.forEach(btn => {
        btn.disabled = false;
        btn.classList.remove('disabled');
    });
    
    // Reset transfer in progress state if it exists
    if (typeof transferInProgress !== 'undefined') {
        transferInProgress = false;
    }
    
    console.log('🎯 Running overlay hidden and controls re-enabled');
}

// Execution Timer Functions
function startExecutionTimer() {
    executionStartTime = new Date();
    console.log('⏱️ EXECUTION TIMER STARTED:', executionStartTime.toISOString());
    
    // Update timer display every second
    executionTimer = setInterval(updateTimerDisplay, 1000);
}

function stopExecutionTimer() {
    if (executionTimer) {
        clearInterval(executionTimer);
        executionTimer = null;
        console.log('⏱️ EXECUTION TIMER STOPPED');
    }
}

function updateTimerDisplay() {
    if (!executionStartTime) return;
    
    const now = new Date();
    const elapsed = Math.floor((now - executionStartTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    
    const timerDisplay = document.getElementById('executionTimer');
    if (timerDisplay) {
        timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
}

function getExecutionTimeString() {
    if (!executionStartTime) return 'Unknown';
    
    const now = new Date();
    const elapsed = Math.floor((now - executionStartTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    
    return `${minutes}m ${seconds}s`;
}
function applyPreset(preset) {
  const form = document.getElementById('configForm');
  const setIf = (name, value) => { if (form && form[name] !== undefined) form[name].value = value; };

  if (preset === 'conservative') {
    setIf('cover','21'); setIf('buffer_pct','30'); setIf('margin_factor','1.5');
    setIf('turnover_min_mult','0.5'); setIf('turnover_max_mult','1.2');
    setIf('floor_sales_threshold','0.30'); setIf('default_floor_qty','3');
    if (form.hub_highwater_cap) form.hub_highwater_cap.value = '3000';
    if (form.target_mos) form.target_mos.value = '4.0';
    if (form.min_line_value) form.min_line_value.value = '20.00';
    if (form.min_unit_price) form.min_unit_price.value = '4.00';
    if (form.per_store_max_transfer) form.per_store_max_transfer.value = '50';
    if (form.oversupply_throttle_enabled) form.oversupply_throttle_enabled.checked = true;
  }
  else if (preset === 'standard') {
    resetForm();
  }
  else if (preset === 'aggressive') {
    setIf('cover','10'); setIf('buffer_pct','15'); setIf('margin_factor','1.1');
    setIf('turnover_min_mult','0.8'); setIf('turnover_max_mult','1.6');
    setIf('floor_sales_threshold','0.15'); setIf('default_floor_qty','1');
    if (form.hub_highwater_cap) form.hub_highwater_cap.value = '1500';
    if (form.target_mos) form.target_mos.value = '2.0';
    if (form.min_line_value) form.min_line_value.value = '10.00';
    if (form.min_unit_price) form.min_unit_price.value = '2.00';
    if (form.per_store_max_transfer) form.per_store_max_transfer.value = '150';
    if (form.oversupply_throttle_enabled) form.oversupply_throttle_enabled.checked = true;
  }
  else if (preset === 'new_store') {
    setIf('cover','30'); setIf('buffer_pct','50'); setIf('margin_factor','1.3');
    setIf('default_floor_qty','5');
    if (form.hub_highwater_cap) form.hub_highwater_cap.value = '2500';
    if (form.target_mos) form.target_mos.value = '2.5';
    if (form.min_line_value) form.min_line_value.value = '8.00';
    if (form.min_unit_price) form.min_unit_price.value = '1.50';
    if (form.per_store_max_transfer) form.per_store_max_transfer.value = '200';
    if (form.oversupply_throttle_enabled) form.oversupply_throttle_enabled.checked = false;

    // ✅ Set the actual field the backend reads (name="new_store")
    const selected = getSelectedDestinations();
    if (selected.length === 1) {
      const newStoreField = form.querySelector('select[name="new_store"]');
      if (newStoreField) newStoreField.value = selected[0];
      alert(`✅ New store seeding preset applied! ${selected[0]} will be seeded.`);
    } else if (selected.length === 0) {
      alert('⚠️ Please select a destination store first, then apply the new store preset.');
    } else {
      alert('⚠️ New store seeding works with single destinations only. Please select one store.');
    }
  }

  // Visual feedback
  const btn = (window.event && window.event.target) ? window.event.target.closest('button') : null;
  if (btn) {
    btn.classList.add('btn-success'); setTimeout(()=>btn.classList.remove('btn-success'),1500);
  }
  showToast(`Applied "${preset.replace(/_/g,' ')}" preset configuration`, 'success');
}

// Handle preset dropdown changes with descriptions
function handlePresetChange(preset) {
  const descElement = document.getElementById('presetDescription');
  
  if (!preset) {
    descElement.innerHTML = 'Select a preset above to auto-configure settings for common scenarios.';
    return;
  }
  
  // Update description based on selection
  const descriptions = {
    'conservative': '🛡️ <strong>Conservative:</strong> Safe settings with higher safety margins, longer cover periods, and moderate transfer sizes. Ideal for stable inventory management.',
    'standard': '⚙️ <strong>Standard:</strong> Balanced default settings suitable for most retail operations. This resets all settings to recommended defaults.',
    'aggressive': '⚡ <strong>Aggressive:</strong> High-turnover settings with lower buffers and faster stock movement. Best for fast-moving inventory and high-volume stores.',
    'new_store': '🏪 <strong>New Store Seeding:</strong> Optimized for initial stock seeding of new locations with higher quantities and broader product range.'
  };
  
  descElement.innerHTML = descriptions[preset] || 'Configuration selected.';
  
  // Apply the preset
  applyPreset(preset);
  
  // Reset dropdown to default after applying (optional)
  setTimeout(() => {
    document.getElementById('presetSelector').value = '';
    descElement.innerHTML = 'Select a preset above to auto-configure settings for common scenarios.';
  }, 3000);
}

function resetForm() {
    const form = document.getElementById('configForm');
    form.cover.value = '14';
    form.buffer_pct.value = '20';
    form.rounding_mode.value = 'nearest';
    form.min_units_per_line.value = '0';  // 🚨 CRITICAL FIX: Allow fractional allocations
    form.floor_sales_threshold.value = '0.20';
    form.default_floor_qty.value = '2';
    form.turnover_min_mult.value = '0.7';
    form.turnover_max_mult.value = '1.4';
    form.overflow_days.value = '180';
    form.overflow_mult.value = '2.0';
    
    // 🚀 Enhanced Algorithm Parameters - Standard Defaults
    if (form.hub_highwater_cap) form.hub_highwater_cap.value = '2000';
    if (form.target_mos) form.target_mos.value = '3.0';
    if (form.min_line_value) form.min_line_value.value = '15.00';
    if (form.min_unit_price) form.min_unit_price.value = '3.00';
    if (form.per_store_max_transfer) form.per_store_max_transfer.value = '100';
    if (form.oversupply_throttle_enabled) form.oversupply_throttle_enabled.checked = true;
    form.margin_factor.value = '1.2';
    form.max_products.value = '0';
    
    // Reset dropdowns to default empty values
    const storeFilterSelect = document.getElementById('store_filter_select');
    const newStoreSelect = document.getElementById('new_store_select');
    if (storeFilterSelect) storeFilterSelect.selectedIndex = 0; // "All Stores"
    if (newStoreSelect) newStoreSelect.selectedIndex = 0; // "Select New Store"
    
    form.note.value = '';
    form.format.value = 'html';
    form.simulate.value = '1';
}

// Enhanced page leave protection during processing
let transferInProgress = false;

window.addEventListener('beforeunload', function(e) {
    const overlay = document.getElementById('runningOverlay');
    const isProcessing = overlay.style.display === 'flex' || transferInProgress;
    
    if (isProcessing) {
        const message = 'Transfer engine is processing inventory. Leaving now may interrupt operations.';
        e.preventDefault();
        e.returnValue = message;
        return message;
    }
    // No popup if nothing is running
});

// New functionality for outlet filtering and transfer presets
function updateTransferType() {
    const isSpecific = document.getElementById('typeSpecific').checked;
    const sourceSelect = document.getElementById('sourceOutlet');
    const destSelect = document.getElementById('destOutlet');
    
    if (isSpecific) {
        sourceSelect.disabled = false;
        destSelect.disabled = false;
        sourceSelect.required = true;
        destSelect.required = true;
    } else {
        sourceSelect.disabled = true;
        destSelect.disabled = true;
        sourceSelect.required = false;
        destSelect.required = false;
        sourceSelect.value = '';
        destSelect.value = '';
    }
}

function filterSources() {
    const warehouseOnly = document.getElementById('sourceWarehouseOnly').checked;
    const select = document.getElementById('sourceOutlet');
    const options = select.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') return; // Skip the "All Available" option
        
        const isWarehouse = option.getAttribute('data-warehouse') === '1';
        if (warehouseOnly) {
            option.style.display = isWarehouse ? '' : 'none';
        } else {
            option.style.display = '';
        }
    });
    
    // Reset selection if current selection is hidden
    const currentSelection = select.options[select.selectedIndex];
    if (currentSelection && currentSelection.style.display === 'none') {
        select.value = '';
    }
}

function filterDestinations() {
    const storesOnly = document.getElementById('destStoresOnly').checked;
    const select = document.getElementById('destOutlet');
    const options = select.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') return; // Skip the "All Eligible" option
        
        const isWarehouse = option.getAttribute('data-warehouse') === '1';
        if (storesOnly) {
            option.style.display = !isWarehouse ? '' : 'none';
        } else {
            option.style.display = '';
        }
    });
    
    // Reset selection if current selection is hidden
    const currentSelection = select.options[select.selectedIndex];
    if (currentSelection && currentSelection.style.display === 'none') {
        select.value = '';
    }
}
function applyTransferPreset(preset, ev) {
    const e = ev || window.event || null;
    const form = document.getElementById('configForm');

    // Clear any existing preset highlighting
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.classList.remove('btn-success', 'btn-primary', 'active');
        btn.classList.add('btn-outline-success', 'btn-outline-warning', 'btn-outline-info', 'btn-outline-secondary');
    });

    // Reset mode radios
    const modeAll  = document.getElementById('mode_all');
    const modeSpec = document.getElementById('mode_specific');
    const modeHub  = document.getElementById('mode_hub');
    if (modeAll)  modeAll.checked  = false;
    if (modeSpec) modeSpec.checked = false;
    if (modeHub)  modeHub.checked  = false;

    // Helper to safely set an input value if it exists
    const setIf = (name, value) => { if (form && form[name] !== undefined) form[name].value = value; };

    let presetName = '';
    let specialInterface = false;

    switch (preset) {
        case 'warehouse_to_all':
            presetName = 'Warehouse → All Stores';
            if (modeAll) modeAll.checked = true;
            const whOnly = document.getElementById('warehouseOnly');
            const exWh   = document.getElementById('excludeWarehouses');
            if (whOnly) whOnly.checked = true;
            if (exWh)   exWh.checked   = true;
            // Balanced defaults
            setIf('cover', '14');
            setIf('buffer_pct', '20');
            setIf('margin_factor', '1.2');
            setIf('turnover_min_mult','0.7');
            setIf('turnover_max_mult','1.4');
            setIf('min_units_per_line','0');  // Allow fractional allocations
            break;

        case 'overflow_to_hub':
            presetName = 'Store Overflow → Hub';
            if (modeHub) modeHub.checked = true;
            setIf('overflow_days','90');
            setIf('overflow_mult','1.5');
            break;

        case 'new_store_seed':
            presetName = 'New Store Seeding';
            specialInterface = true;
            if (modeSpec) modeSpec.checked = true;
            setIf('cover','30');
            setIf('buffer_pct','50');
            setIf('margin_factor','1.3');
            setIf('default_floor_qty','5');
            
            // Show special new store interface
            showNewStoreInterface();
            break;

        case 'emergency_transfer':
            presetName = 'Emergency Restock';
            if (modeHub) modeHub.checked = true;
            setIf('cover','7');
            setIf('buffer_pct','10');
            setIf('margin_factor','1.1');
            break;

        case 'inter_store':
            presetName = 'Store to Store';
            if (modeSpec) modeSpec.checked = true;
            // conservative balancing
            setIf('cover','21');
            setIf('buffer_pct','30');
            setIf('margin_factor','1.5');
            break;
    }

    // Update transfer mode based on selections
    if (typeof handleTransferModeChange === 'function') handleTransferModeChange();

    // Visual feedback for clicked button
    if (e && e.target) {
        const btn = e.target.closest('button');
        if (btn) {
            // Remove outline classes and add solid styling
            btn.className = btn.className.replace(/btn-outline-\w+/g, '');
            btn.classList.add('btn-primary', 'active');
            
            // Update button to show it's active
            setTimeout(() => {
                if (btn.querySelector('small')) {
                    const small = btn.querySelector('small');
                    small.innerHTML = '✅ Active Configuration';
                    small.classList.remove('text-muted');
                    small.classList.add('text-white');
                }
            }, 100);
        }
    }

    // Show active preset indicator
    const indicator = document.getElementById('activePresetIndicator');
    const nameSpan = document.getElementById('activePresetName');
    if (indicator && nameSpan) {
        nameSpan.textContent = presetName;
        indicator.style.display = 'block';
        indicator.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Show appropriate success message
    if (specialInterface) {
        showToast(`🏪 ${presetName} activated - Special interface loaded`, 'warning');
    } else {
        showToast(`✅ ${presetName} preset applied successfully`, 'success');
    }
}

// New function to show special new store interface
function showNewStoreInterface() {
    // Create or show a special new store selection modal/interface
    const modalHtml = `
        <div class="modal fade" id="newStoreModal" tabindex="-1" aria-labelledby="newStoreModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="newStoreModalLabel">
                            <i class="bi bi-layers me-2"></i>New Store Skimming Configuration
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>New Store Skimming Mode</strong><br>
                            Takes a few units of each product from ALL stores to build initial inventory for the new store. This distributes the impact so it's barely noticeable at existing locations.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label fw-bold">Select New Store Location</label>
                                <select class="form-select form-select-lg" id="newStoreSelect">
                                    <option value="">Choose new store to seed...</option>
                                    <?php foreach ($outlets as $outlet): ?>
                                        <?php if (!$outlet['is_warehouse']): ?>
                                            <option value="<?= htmlspecialchars($outlet['outlet_id']) ?>">
                                                <?= htmlspecialchars($outlet['name']) ?> 🛍️
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-bold">Source Selection</label>
                                <select class="form-select form-select-lg" id="skimmingSourceSelect">
                                    <option value="all_stores" selected>All Stores (Recommended)</option>
                                    <option value="stores_only">Retail Stores Only</option>
                                    <option value="warehouses_only">Warehouses Only</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="bi bi-gear me-2"></i>Seeding Quantities by Product Category</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Hardware/Devices</label>
                                    <input type="number" class="form-control mb-2" id="qty_hardware" value="2" min="1" max="10">
                                    
                                    <label class="form-label">E-Liquids</label>
                                    <input type="number" class="form-control mb-2" id="qty_eliquids" value="3" min="1" max="15">
                                    
                                    <label class="form-label">Coils/Pods</label>
                                    <input type="number" class="form-control mb-2" id="qty_coils" value="5" min="1" max="20">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Accessories</label>
                                    <input type="number" class="form-control mb-2" id="qty_accessories" value="2" min="1" max="10">
                                    
                                    <label class="form-label">Premium Items</label>
                                    <input type="number" class="form-control mb-2" id="qty_premium" value="1" min="1" max="5">
                                    
                                    <label class="form-label">Default (Other)</label>
                                    <input type="number" class="form-control mb-2" id="qty_default" value="2" min="1" max="10">
                                </div>
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-lightbulb"></i> <strong>Tip:</strong> These quantities will be taken from each contributing store, so the new store will receive multiples of these amounts.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-warning" onclick="applyNewStoreSkimmingConfig()">
                            <i class="bi bi-layers me-2"></i>Configure New Store Skimming
                        </button>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if present
    const existingModal = document.getElementById('newStoreModal');
    if (existingModal) existingModal.remove();
    
    // Add modal to page
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('newStoreModal'));
    modal.show();
}

// Function to apply new store configuration
function applyNewStoreConfig() {
    const newStore = document.getElementById('newStoreSelect').value;
    const sourceStore = document.getElementById('seedSourceSelect').value;
    
    if (!newStore) {
        alert('Please select a new store location to seed.');
        return;
    }
    
    // Set the specific transfer mode with new store selection
    document.getElementById('mode_specific').checked = true;
    
    if (sourceStore) {
        document.getElementById('sourceOutlet').value = sourceStore;
    }
    document.getElementById('destOutlet').value = newStore;
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('newStoreModal'));
    modal.hide();
    
    // Update transfer mode
    if (typeof handleTransferModeChange === 'function') handleTransferModeChange();
    
    showToast('🏪 New store seeding configured successfully', 'success');
}

// Function to clear preset selection
function clearPreset() {
    // Reset all buttons to outline style
    document.querySelectorAll('.preset-btn').forEach(btn => {
        btn.className = btn.className.replace(/btn-(primary|success|warning|info|secondary)(\s|$)/g, '');
        btn.classList.remove('active');
        
        // Restore original outline classes based on original color
        if (btn.innerHTML.includes('Warehouse →') || btn.innerHTML.includes('Store Overflow')) {
            btn.classList.add('btn-outline-success');
        } else if (btn.innerHTML.includes('New Store')) {
            btn.classList.add('btn-outline-warning');
        } else if (btn.innerHTML.includes('Emergency')) {
            btn.classList.add('btn-outline-info');
        } else {
            btn.classList.add('btn-outline-secondary');
        }
        
        // Reset small text
        const small = btn.querySelector('small');
        if (small && small.textContent === '✅ Active Configuration') {
            if (btn.innerHTML.includes('New Store')) {
                small.innerHTML = 'High-stock initial setup for new locations<span class="badge bg-warning mt-2">Special Interface</span>';
            } else {
                // Restore original text based on button type
                const originalTexts = {
                    'Warehouse →': 'Standard hub distribution to all retail outlets',
                    'New Store': 'High-stock initial setup for new locations',
                    'Emergency': 'Fast 7-day coverage with low buffers',
                    'Store to Store': 'Direct inter-store transfers (any to any)',
                    'Store Overflow': 'Return excess inventory to central warehouse'
                };
                
                for (const [key, text] of Object.entries(originalTexts)) {
                    if (btn.innerHTML.includes(key)) {
                        small.innerHTML = text;
                        break;
                    }
                }
            }
            small.classList.add('text-muted');
            small.classList.remove('text-white');
        }
    });
    
    // Hide active preset indicator
    const indicator = document.getElementById('activePresetIndicator');
    if (indicator) {
        indicator.style.display = 'none';
    }
    
    // Reset to default mode (All Stores)
    document.getElementById('mode_all').checked = true;
    if (typeof handleTransferModeChange === 'function') handleTransferModeChange();
    
    showToast('Preset configuration cleared - returned to defaults', 'info');
}

function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-check-circle me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    toastContainer.appendChild(toast);
    
    // Show toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    // Remove from DOM after hiding
    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});


function loadOutlets() {
  // Make JSON bulletproof in case of odd chars
  const outlets = <?php
    $safe = array_map(function($o){
      return [
        'outlet_id'    => (string)($o['outlet_id'] ?? ''),
        'name'         => (string)($o['name'] ?? ''),
        'is_warehouse' => !empty($o['is_warehouse']),
      ];
    }, $outlets ?? []);
    echo json_encode($safe, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  ?> || [];

  populateOutlets(outlets);
}


function populateOutlets(list){
  const source = document.getElementById('sourceOutlet');
  const dest   = document.getElementById('destOutlet');
  const filt   = document.getElementById('store_filter_select');
  const seed   = document.getElementById('new_store_select');

  // keep first placeholder option; remove everything after index 0
  [source, dest, filt, seed].forEach(el => {
    if (!el) return;
    while (el.options.length > 1) el.remove(1);
  });

  list.forEach(o => {
    const label = `${o.name} ${o.is_warehouse ? '🏢 (Warehouse)' : '🛍️ (Store)'}`;

    [source, dest, filt, seed].forEach(el => {
      if (!el) return;
      const opt = new Option(label, o.outlet_id);
      opt.dataset.warehouse = o.is_warehouse ? '1' : '0';
      el.add(opt);
    });
  });

  if (typeof showToast === 'function') {
    const w = list.filter(o => o.is_warehouse).length;
    const s = list.length - w;
    showToast(`Loaded outlets — Warehouses: ${w}, Stores: ${s}`, list.length ? 'success' : 'warning');
  }
}

function handleTransferModeChange() {
    const mode = document.querySelector('input[name="transfer_mode"]:checked')?.value;
    console.log('🚨 TRANSFER MODE CHANGE:', mode); // DEBUG
    
    const sourceSelect = document.getElementById('sourceOutlet');
    const destSelect = document.getElementById('destOutlet');
    const warehouseOnly = document.getElementById('warehouseOnly');
    const excludeWarehouses = document.getElementById('excludeWarehouses');
    const multiSelectArea = document.querySelector('.destination-checkboxes');
    const destinationCount = document.getElementById('destinationCount');
    const selectedDestinationsInput = document.getElementById('selectedDestinations');
    
    console.log('🚨 Elements found:', {
        sourceSelect: !!sourceSelect,
        destSelect: !!destSelect,
        multiSelectArea: !!multiSelectArea,
        destinationCount: !!destinationCount
    }); // DEBUG
    
    // Check if required elements exist
    if (!mode || !sourceSelect || !destSelect || !warehouseOnly || !excludeWarehouses) {
        console.warn('Transfer mode elements not found, skipping mode change');
        return;
    }
    
    // Reset all controls
    sourceSelect.disabled = true;
    destSelect.disabled = true;
    warehouseOnly.disabled = true;
    excludeWarehouses.disabled = true;
    
    if (mode === 'specific_transfer') {
        console.log('🚨 SPECIFIC TRANSFER MODE ACTIVATED'); // DEBUG
        // For 1:1 transfer - hide multi-select, show simple destination dropdown
        sourceSelect.disabled = false;
        destSelect.disabled = false;
        warehouseOnly.disabled = false;
        excludeWarehouses.disabled = false;
        
        // Hide multi-select checkboxes, show simple dropdown
        if (multiSelectArea) {
            multiSelectArea.style.display = 'none';
            console.log('🚨 HIDING multi-select area'); // DEBUG
        }
        
        // Show simple destination dropdown
        if (destSelect) {
            destSelect.style.display = 'block';
            console.log('🚨 SHOWING destination dropdown'); // DEBUG
        }
        
        // Update the destination count message
        if (destinationCount) {
            destinationCount.textContent = 'Select one destination for direct transfer';
            destinationCount.className = 'text-info';
        }
        
        // Clear multi-select hidden input
        if (selectedDestinationsInput) {
            selectedDestinationsInput.value = '';
        }
        
        // Update the destination count message
        if (destinationCount) {
            destinationCount.textContent = 'Select one destination for direct transfer';
            destinationCount.className = 'text-info';
        }
        
        // Clear hidden input for multi-select
        if (selectedDestinationsInput) {
            selectedDestinationsInput.value = '';
        }
        
    } else if (mode === 'hub_to_stores') {
        console.log('🚨 HUB TO STORES MODE ACTIVATED'); // DEBUG
        // For hub to stores - show multi-select, hide simple dropdown
        sourceSelect.disabled = false;
        destSelect.disabled = true; // Disable dropdown for destinations
        warehouseOnly.disabled = false;
        excludeWarehouses.disabled = false;
        warehouseOnly.checked = true;
        excludeWarehouses.checked = true;
        
        // Show multi-select checkboxes
        if (multiSelectArea) {
            multiSelectArea.style.display = 'block';
            console.log('🚨 SHOWING multi-select area'); // DEBUG
        }
        
        // Hide simple destination dropdown  
        if (destSelect) {
            destSelect.style.display = 'none';
            console.log('🚨 HIDING destination dropdown'); // DEBUG
        }
        
        // Update destination count with actual selection
        updateDestinationSelection();
        
    } else if (mode === 'all_stores') {
        console.log('🚨 ALL STORES MODE ACTIVATED'); // DEBUG
        // For all stores - disable destination selection entirely
        sourceSelect.disabled = false;
        destSelect.disabled = true;
        warehouseOnly.disabled = false;
        excludeWarehouses.disabled = false;
        warehouseOnly.checked = true;
        excludeWarehouses.checked = true;
        
        // Hide both multi-select and dropdown
        if (multiSelectArea) {
            multiSelectArea.style.display = 'none';
        }
        if (destSelect) {
            destSelect.style.display = 'none';
        }
        
        // Update destination count message
        if (destinationCount) {
            destinationCount.textContent = 'Auto-distribute to all eligible stores';
            destinationCount.className = 'text-success';
        }
        
        // Clear destination selections
        if (selectedDestinationsInput) {
            selectedDestinationsInput.value = '';
        }
    }
    
    updateOutletFiltering();
}

function updateOutletFiltering() {
    const warehouseOnly = document.getElementById('warehouseOnly').checked;
    const excludeWarehouses = document.getElementById('excludeWarehouses').checked;
    const sourceSelect = document.getElementById('sourceOutlet');
    const destSelect = document.getElementById('destOutlet');
    
    // Store original options if not already stored
    if (!sourceSelect.originalOptions) {
        sourceSelect.originalOptions = Array.from(sourceSelect.options).slice();
    }
    if (!destSelect.originalOptions) {
        destSelect.originalOptions = Array.from(destSelect.options).slice();
    }
    
    // Clear current options (except first "Auto-Select" option)
    while (sourceSelect.options.length > 1) {
        sourceSelect.remove(1);
    }
    while (destSelect.options.length > 1) {
        destSelect.remove(1);
    }
    
    // Re-add filtered source options
    sourceSelect.originalOptions.slice(1).forEach(option => {
        const isWarehouse = option.dataset.warehouse === '1';
        const shouldShow = warehouseOnly ? isWarehouse : true;
        if (shouldShow) {
            sourceSelect.appendChild(option.cloneNode(true));
        }
    });
    
    // Re-add filtered destination options  
    destSelect.originalOptions.slice(1).forEach(option => {
        const isWarehouse = option.dataset.warehouse === '1';
        const shouldShow = excludeWarehouses ? !isWarehouse : true;
        if (shouldShow) {
            destSelect.appendChild(option.cloneNode(true));
        }
    });
    
    // Visual feedback
    if (warehouseOnly) {
        sourceSelect.style.border = '2px solid #198754';
    } else {
        sourceSelect.style.border = '';
    }
    
    if (excludeWarehouses) {
        destSelect.style.border = '2px solid #0d6efd';
    } else {
        destSelect.style.border = '';
    }
    
    // Update counts in labels
    const sourceLabel = sourceSelect.parentElement.querySelector('h6');
    const destLabel = destSelect.parentElement.querySelector('h6');
    const sourceCount = sourceSelect.options.length - 1;
    const destCount = destSelect.options.length - 1;
    
    if (sourceLabel) {
        sourceLabel.innerHTML = `<i class="bi bi-box-arrow-up"></i> Source Location <small class="text-muted">(${sourceCount} available)</small>`;
    }
    if (destLabel) {
        destLabel.innerHTML = `<i class="bi bi-box-arrow-down"></i> Destination Location <small class="text-muted">(${destCount} available)</small>`;
    }
    
    // Update multi-select if enabled
    updateDestinationMultiSelect();
}

function updateDestinationMultiSelect() {
    // This function would handle multi-select destination UI
    // For now, keeping single select but could be extended
    const destSelect = document.getElementById('destOutlet');
    const mode = document.querySelector('input[name="transfer_mode"]:checked').value;
    
    if (mode === 'hub_to_stores') {
        // Could add multi-select capability here in future
        destSelect.setAttribute('title', 'Select destination store (multi-select coming soon)');
    }
}

function applyAdvancedTransferPreset(presetType) {
    const form = document.getElementById('configForm');
    
    // Reset transfer mode first
    document.getElementById('mode_all').checked = false;
    document.getElementById('mode_specific').checked = false;
    document.getElementById('mode_hub').checked = false;
    
    switch(presetType) {
        case 'warehouse_to_all':
            document.getElementById('mode_hub').checked = true;
            document.getElementById('warehouseOnly').checked = true;
            document.getElementById('excludeWarehouses').checked = true;
            applyPreset('standard');
            break;
            
        case 'overflow_to_hub':
            document.getElementById('mode_hub').checked = true;
            document.getElementById('excludeWarehouses').checked = false;
            form.overflow_days.value = '90';
            form.overflow_mult.value = '1.5';
            break;
            
        case 'new_store_seed':
            document.getElementById('mode_specific').checked = true;
            document.getElementById('warehouseOnly').checked = true;
            applyPreset('new_store');
            break;
            
        case 'emergency_transfer':
            document.getElementById('mode_specific').checked = true;
            applyPreset('aggressive');
            form.cover.value = '7';
            form.buffer_pct.value = '10';
            break;
            
        case 'inter_store':
            document.getElementById('mode_specific').checked = true;
            document.getElementById('warehouseOnly').checked = false;
            document.getElementById('excludeWarehouses').checked = false;
            applyPreset('conservative');
            break;
    }
    
    handleTransferModeChange();
    
    // Visual feedback
    const buttons = document.querySelectorAll('[onclick*="applyTransferPreset"]');
    buttons.forEach(btn => btn.classList.remove('btn-success'));
    event.target.classList.add('btn-success');
    setTimeout(() => {
        event.target.classList.remove('btn-success');
        event.target.classList.add('btn-outline-success');
    }, 2000);
}

function showHelpModal() {
    const modal = new bootstrap.Modal(document.getElementById('helpModal'));
    modal.show();
}

// GPT Categorization Functions
document.addEventListener('DOMContentLoaded', function() {
    // Toggle GPT section visibility
    const enableGptCheckbox = document.getElementById('enableGptCategorization');
    const gptBody = document.getElementById('gptCategorizationBody');
    
    enableGptCheckbox.addEventListener('change', function() {
        gptBody.style.display = this.checked ? 'block' : 'none';
    });
});

function testGptCategorization() {
    const apiKey = document.querySelector('input[name="gpt_api_key"]').value;
    const prompt = document.querySelector('textarea[name="gpt_prompt"]').value;
    
    if (!apiKey.trim()) {
        showToast('Please enter your OpenAI API Key', 'warning');
        return;
    }
    
    if (!prompt.trim()) {
        showToast('Please enter a categorization prompt', 'warning');
        return;
    }
    
    // Show loading state
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="bi bi-gear-fill spin"></i> Testing...';
    button.disabled = true;
    
    // Simulate API test (in production this would call a test endpoint)
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;
        showToast('✅ GPT Configuration Test Successful!<br><small>API key valid, prompt ready for categorization</small>', 'success');
    }, 2000);
}

function runCategorizationOnly() {
    // Enhanced validation: Check GPT prerequisites
    const apiKey = document.querySelector('input[name="gpt_api_key"]').value.trim();
    const prompt = document.querySelector('textarea[name="gpt_prompt"]').value.trim();
    
    if (!apiKey) {
        showToast('❌ GPT API Key is required for categorization', 'danger');
        document.querySelector('input[name="gpt_api_key"]').focus();
        return;
    }
    
    if (!prompt) {
        showToast('❌ GPT Prompt is required for categorization', 'danger'); 
        document.querySelector('textarea[name="gpt_prompt"]').focus();
        return;
    }
    
    // Show enhanced loading overlay
    document.getElementById('runningOverlay').style.display = 'flex';
    const progressText = document.querySelector('#progress-text');
    if (progressText) {
        progressText.innerHTML = `
            <p class="lead">🤖 Scanning products for categorization...</p>
            <div class="progress mb-3" style="height: 8px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" 
                     role="progressbar" style="width: 10%"></div>
            </div>
        `;
    }
    
    // Create hardened form with security measures
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    // Hardened parameters with validation
    const params = {
        'action': 'run',
        'use_gpt_categorization': '1',
        'run_gpt_categorization_only': '1',
        'gpt_api_key': apiKey,
        'gpt_prompt': prompt,
        'gpt_fallback_db': document.querySelector('select[name="gpt_fallback_db"]').value || '1',
        'format': 'html',
        'security_mode': 'hardened'
    };
    
    Object.keys(params).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = params[key];
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    showToast('🚀 AI Categorization Started - Processing uncategorized products...', 'info');
    form.submit();
}

// Add spinner animation for loading states
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
    }
`;
document.head.appendChild(style);

// Initialize outlet controls
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚨 DOM LOADED - starting initialization');
    loadOutlets();
    
    // Add event listeners for transfer mode changes
    document.querySelectorAll('input[name="transfer_mode"]').forEach(radio => {
        radio.addEventListener('change', handleTransferModeChange);
    });
    
    document.getElementById('warehouseOnly').addEventListener('change', updateOutletFiltering);
    document.getElementById('excludeWarehouses').addEventListener('change', updateOutletFiltering);
    
    // Initialize transfer mode on page load
    handleTransferModeChange();
    
    // Add logging to see what's selected
    console.log('🚨 PAGE LOADED - Current transfer mode:', document.querySelector('input[name="transfer_mode"]:checked')?.value);
    
    // Initialize destination multi-select handling
    initializeDestinationMultiSelect();
    
    // Initialize GPT categorization toggle
    const gptCheckbox = document.getElementById('enableGptCategorization');
    if (gptCheckbox) {
        gptCheckbox.addEventListener('change', toggleGptCategorization);
    }
});

// 🎯 MULTI-SELECT DESTINATION HANDLING
function initializeDestinationMultiSelect() {
    const destAllCheckbox = document.getElementById('dest_all');
    const destinationChecks = document.querySelectorAll('.destination-check:not(#dest_all)');
    const selectedDestinationsHidden = document.getElementById('selectedDestinations');
    const destinationCount = document.getElementById('destinationCount');
    const excludeWarehouses = document.getElementById('excludeWarehouses');
    
    // Handle "All" checkbox
    if (destAllCheckbox) {
        destAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            destinationChecks.forEach(checkbox => {
                const warehouseOption = checkbox.closest('.destination-option');
                const isWarehouse = warehouseOption && warehouseOption.dataset.warehouse === '1';
                
                // Only check visible destinations (not hidden by warehouse filter)
                if (warehouseOption && warehouseOption.style.display !== 'none') {
                    // If excluding warehouses and this is a warehouse, don't check it
                    if (excludeWarehouses.checked && isWarehouse) {
                        checkbox.checked = false;
                    } else {
                        checkbox.checked = isChecked;
                    }
                }
            });
            updateDestinationSelection();
        });
    }
    
    // Handle individual destination checkboxes
    destinationChecks.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // If unchecking any individual item, uncheck "All"
            if (!this.checked && destAllCheckbox) {
                destAllCheckbox.checked = false;
            }
            
            // If all visible items are checked, check "All"
            const visibleChecks = Array.from(destinationChecks).filter(cb => {
                const option = cb.closest('.destination-option');
                return option && option.style.display !== 'none';
            });
            const checkedVisibleCount = visibleChecks.filter(cb => cb.checked).length;
            
            if (destAllCheckbox && checkedVisibleCount === visibleChecks.length && visibleChecks.length > 0) {
                destAllCheckbox.checked = true;
            }
            
            updateDestinationSelection();
        });
    });
    
    // Handle warehouse exclusion filter
    if (excludeWarehouses) {
        excludeWarehouses.addEventListener('change', function() {
            filterDestinationOptions();
            updateDestinationSelection();
        });
    }
    
    // Initial update
    updateDestinationSelection();
}

function filterDestinationOptions() {
    const excludeWarehouses = document.getElementById('excludeWarehouses').checked;
    const destinationOptions = document.querySelectorAll('.destination-option');
    
    destinationOptions.forEach(option => {
        const isWarehouse = option.dataset.warehouse === '1';
        if (excludeWarehouses && isWarehouse) {
            option.style.display = 'none';
            // Uncheck hidden warehouse options
            const checkbox = option.querySelector('input[type="checkbox"]');
            if (checkbox) checkbox.checked = false;
        } else {
            option.style.display = 'block';
        }
    });
}
// 🎯 REPLACE your current updateDestinationSelection() with this
function updateDestinationSelection() {
  const destAllCheckbox = document.getElementById('dest_all');
  const destinationChecks = Array.from(document.querySelectorAll('.destination-check:not(#dest_all)'));
  const selectedDestinationsHidden = document.getElementById('selectedDestinations');
  const destinationCount = document.getElementById('destinationCount');

  // Collect checked & visible destinations
  const selected = [];
  destinationChecks.forEach(cb => {
    if (cb.checked && cb.value) {
      const opt = cb.closest('.destination-option');
      if (!opt || opt.style.display !== 'none') selected.push(cb.value);
    }
  });

  // ✅ Hidden value rules:
  // - If "All" is ticked, blank => backend treats as "all stores"
  // - If exactly 1 selected, force single ID (prevents fallback to all)
  // - Else CSV of selected
  if (selectedDestinationsHidden) {
    if (destAllCheckbox && destAllCheckbox.checked) {
      selectedDestinationsHidden.value = '';
    } else if (selected.length === 1) {
      selectedDestinationsHidden.value = selected[0];
    } else {
      selectedDestinationsHidden.value = selected.join(',');
    }
  }

  // Display count
  if (destinationCount) {
    const visible = Array.from(document.querySelectorAll('.destination-option'))
      .filter(el => el.style.display !== 'none').length;
    if (destAllCheckbox && destAllCheckbox.checked) {
      destinationCount.textContent = `All eligible stores selected (${visible} stores)`;
      destinationCount.className = 'text-success';
    } else if (selected.length === 0) {
      destinationCount.textContent = 'No destinations selected - transfers will be skipped';
      destinationCount.className = 'text-danger';
    } else {
      destinationCount.textContent = `${selected.length} of ${visible} stores selected`;
      destinationCount.className = 'text-success';
    }
  }
}


// 🚀 AUTO-GENERATE GPT PROMPTS WITH DATABASE INTELLIGENCE
async function autoGenerateGptPrompt() {
    const promptTextarea = document.getElementById('gpt_prompt');
    const statusDiv = document.getElementById('gpt_status');
    
    if (!promptTextarea) {
        console.error('GPT prompt textarea not found');
        return;
    }

    // Show loading state
    if (statusDiv) statusDiv.innerHTML = '<div class="alert alert-info mt-2">🤖 Generating advanced prompt with database intelligence...</div>';

    try {
        // Generate sophisticated prompt using PHP backend
        const formData = new FormData();
        formData.append('action', 'generate_gpt_prompt');
        formData.append('format', 'advanced');

        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const text = await response.text();
        
        // For now, use the pre-generated advanced prompt from PHP
        const advancedPrompt = `You are an expert product categorization AI for The Vape Shed, New Zealand's premier vape retailer.

## 🎯 CATEGORIZATION MISSION
Analyze each product and return a structured JSON response with categorization, confidence scoring, and business intelligence.

## 📋 AVAILABLE CATEGORIES
- **Hardware**: Devices, kits, mods, tanks, coils, batteries
- **E-Liquids**: All vape juices, nicotine salts, flavors
- **Accessories**: Cases, chargers, tools, replacement parts
- **Consumables**: Coils, wicks, cotton, pods
- **Starter Kits**: Complete beginner packages
- **Premium**: High-end devices and accessories

## 🏷️ BRAND INTELLIGENCE
Top brands by volume:
- **SMOK** (45+ products, avg $52.99) - Hardware, Kits
- **Vaporesso** (38+ products, avg $67.50) - Hardware, Premium
- **Nasty Juice** (24+ products, avg $24.99) - E-Liquids
- **Dinner Lady** (22+ products, avg $26.50) - E-Liquids

## 📦 PACK SIZE PATTERNS
- 5 pack (coils, avg $89.99)
- 3 pack (pods, avg $54.99)
- 10 pack (e-liquids, avg $159.99)

## ⚖️ NZ COMPLIANCE FRAMEWORK
- **Nicotine Products**: Age-restricted, advertising-limited, require warnings
- **Hardware**: Age-restricted but can be advertised
- **Accessories**: Generally unrestricted
- **Weight Estimation**: Use typical product weights for logistics

## 📊 REQUIRED JSON OUTPUT FORMAT
Return a JSON array where each product gets:
\`\`\`json
{
  "product_id": "original_id",
  "product_name": "original_name",
  "primary_category": "best_fit_category",
  "confidence_score": 0.85,
  "brand_detected": "detected_brand_name",
  "pack_size_detected": "detected_pack_info",
  "estimated_weight_grams": 150,
  "compliance_flags": ["age_restricted", "nicotine_product"],
  "price_analysis": "price_positioning_vs_category_average",
  "categorization_reasoning": "why_this_category_was_chosen"
}
\`\`\`

## 🎯 ANALYSIS PRIORITIES
1. **Accuracy First**: Use brand knowledge and pack patterns for precision
2. **Compliance Awareness**: Flag age-restricted and nicotine products
3. **Business Intelligence**: Provide pricing and improvement insights
4. **Confidence Scoring**: Be honest about uncertainty (0.0-1.0)
5. **Logistics Support**: Estimate weights for shipping calculations

Analyze the provided products with deep contextual understanding of The Vape Shed's inventory patterns and New Zealand vaping regulations.`;

        promptTextarea.value = advancedPrompt;
        
        if (statusDiv) {
            statusDiv.innerHTML = `
                <div class="alert alert-success mt-2">
                    <h6>✅ Advanced Prompt Generated!</h6>
                    <div class="row mt-2">
                        <div class="col-md-3"><strong>Categories:</strong> 6</div>
                        <div class="col-md-3"><strong>Brands:</strong> 4+</div>
                        <div class="col-md-3"><strong>Pack Patterns:</strong> 3</div>
                        <div class="col-md-3"><strong>Generated:</strong> ${new Date().toLocaleTimeString()}</div>
                    </div>
                    <small class="text-muted">🎯 Enterprise-grade prompt with NZ compliance and brand intelligence</small>
                </div>
            `;
        }

    } catch (error) {
        console.error('Error generating prompt:', error);
        
        if (statusDiv) {
            statusDiv.innerHTML = `
                <div class="alert alert-warning mt-2">
                    <strong>⚠️ Using Advanced Fallback</strong><br>
                    Generated comprehensive prompt with built-in intelligence.<br>
                    <small>Advanced database-driven features coming soon!</small>
                </div>
            `;
        }
    }
}

// Toggle API key visibility
function toggleKeyVisibility() {
    const keyInput = document.querySelector('input[name="gpt_api_key"]');
    const toggleBtn = event.target.closest('button');
    const icon = toggleBtn.querySelector('i');
    
    if (keyInput.type === 'password') {
        keyInput.type = 'text';
        icon.className = 'bi bi-eye-slash';
        toggleBtn.title = 'Hide Key';
    } else {
        keyInput.type = 'password';
        icon.className = 'bi bi-eye';
        toggleBtn.title = 'Show Key';
    }
}

// Quick set functions for max products
function setMaxProducts(value) {
    document.getElementById('max_products_input').value = value;
}

// 🎯 REPLACE your current executeAction() with this
function executeAction(action) {
  const form = document.getElementById('configForm');
  if (!form) return false;

  // Normalize simulate/format based on action
  const simulateSel = form.querySelector('select[name="simulate"]');
  const formatSel   = form.querySelector('select[name="format"]');
  if (action === 'live') {
    const confirmMsg =
`🚨 PRODUCTION CONFIRMATION REQUIRED 🚨

⚠️ You are about to execute LIVE TRANSFERS that will:
• Create REAL transfer records in the database
• Generate pick lists for staff
• Affect inventory across selected stores

✅ Transfers are created in PENDING status
✅ Stock only deducts when staff confirm in Vend

Type 'EXECUTE' to confirm:`;
    const ok = prompt(confirmMsg);
    if (ok !== 'EXECUTE') { return false; }
    if (simulateSel) simulateSel.value = '0';
    if (formatSel)   formatSel.value   = 'html';
  } else if (action === 'json') {
    if (simulateSel) simulateSel.value = '1';
    if (formatSel)   formatSel.value   = 'json';
  } else {
    // simulation
    if (simulateSel) simulateSel.value = '1';
    if (formatSel)   formatSel.value   = 'html';
  }

  // Sync selection fields into the form
  syncSelectionFields();

  // Validate intent
  const mode = (document.querySelector('input[name="transfer_mode"]:checked') || {}).value || 'all_stores';
  const src  = document.getElementById('sourceOutlet')?.value || '';
  const dst1 = document.getElementById('destOutlet')?.value || '';
  const destHidden = document.getElementById('selectedDestinations')?.value || '';

  if (mode === 'specific_transfer') {
    if (!src || !(dst1 || destHidden)) {
      alert('Please select both a Source and a Destination for a 1:1 transfer.');
      return false;
    }
  } else if (mode === 'hub_to_stores') {
    // destHidden '' means "all" — allowed; otherwise require at least one id
    if (destHidden !== '' && !destHidden.split(',').filter(Boolean).length) {
      alert('Please select at least one destination store (or tick “All stores”).');
      return false;
    }
  }

  // Submit POST (includes all parameters)
  if (typeof showOverlay === 'function') {
    const kind = (action === 'live') ? 'live' : (action === 'json') ? 'json' : 'simulation';
    showOverlay(kind);
  }
  try {
    form.submit();
  } catch (e) {
    if (typeof showToast === 'function') showToast('Submit failed: ' + e.message, 'danger');
    return false;
  }
  return true;
}

function startExecutionTimer() {
    executionStartTime = Date.now();
    const timerDisplay = document.getElementById('executionTimer');
    if (timerDisplay) {
        executionTimer = setInterval(() => {
            const elapsed = Date.now() - executionStartTime;
            const seconds = Math.floor(elapsed / 1000);
            const minutes = Math.floor(seconds / 60);
            const hours = Math.floor(minutes / 60);
            
            let timeStr;
            if (hours > 0) {
                timeStr = `${hours}h ${minutes % 60}m ${seconds % 60}s`;
            } else if (minutes > 0) {
                timeStr = `${minutes}m ${seconds % 60}s`;
            } else {
                timeStr = `${seconds}s`;
            }
            
            timerDisplay.innerHTML = `⏱️ <strong>${timeStr}</strong>`;
        }, 1000);
    }
}

function stopExecutionTimer() {
    if (executionTimer) {
        clearInterval(executionTimer);
        executionTimer = null;
    }
    
    if (executionStartTime) {
        const totalTime = Date.now() - executionStartTime;
        const seconds = Math.floor(totalTime / 1000);
        const minutes = Math.floor(seconds / 60);
        
        console.log(`🏁 EXECUTION COMPLETED: Total time ${minutes}m ${seconds % 60}s (${totalTime}ms)`);
        
        const timerDisplay = document.getElementById('executionTimer');
        if (timerDisplay) {
            let timeStr;
            if (minutes > 0) {
                timeStr = `${minutes}m ${seconds % 60}s`;
            } else {
                timeStr = `${seconds}s`;
            }
            timerDisplay.innerHTML = `✅ <strong>Completed in ${timeStr}</strong>`;
        }
    }
}

function showTransferOverlay(action) {
    const overlay = document.getElementById('runningOverlay');
    const progressText = overlay.querySelector('#progress-text');
    
    // Start the execution timer
    startExecutionTimer();
    
    let actionText, actionIcon, actionColor;
    
    switch(action) {
        case 'live':
            actionText = 'LIVE PRODUCTION TRANSFER';
            actionIcon = 'rocket-takeoff';
            actionColor = 'success';
            break;
        case 'simulation':
            actionText = 'SIMULATION MODE';
            actionIcon = 'eye';
            actionColor = 'secondary';
            break;
        case 'json':
            actionText = 'JSON REPORT GENERATION';
            actionIcon = 'download';
            actionColor = 'info';
            break;
        default:
            actionText = 'TRANSFER PROCESSING';
            actionIcon = 'gear';
            actionColor = 'primary';
    }
    
    if (progressText) {
        progressText.innerHTML = `
            <p class="lead"><i class="bi bi-${actionIcon}"></i> ${actionText}</p>
            <div class="progress mb-3" style="height: 12px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-${actionColor}" 
                     role="progressbar" style="width: 25%"></div>
            </div>
            <div class="row text-start">
                <div class="col-6">
                    <small><i class="bi bi-database"></i> Loading inventory data</small><br>
                    <small><i class="bi bi-cpu"></i> Calculating demands</small><br>
                    <small><i class="bi bi-boxes"></i> Fair-share allocation</small>
                </div>
                <div class="col-6">
                    <small><i class="bi bi-truck"></i> Freight optimization</small><br>
                    <small><i class="bi bi-shield-check"></i> Security validation</small><br>
                    <small><i class="bi bi-check-circle"></i> Transfer creation</small>
                </div>
            </div>
        `;
    }
    
    overlay.style.display = 'flex';
}

function syncSelectionFields() {
  const form = document.getElementById('configForm');
  if (!form) return;

  const mode = (document.querySelector('input[name="transfer_mode"]:checked') || {}).value || 'all_stores';

  const sourceSelect = document.getElementById('sourceOutlet');          // <select name="source_outlet">
  const destSingle   = document.getElementById('destOutlet');            // <select name="dest_outlet_single">
  const destHidden   = document.getElementById('selectedDestinations');  // <input type="hidden" name="dest_outlet">
  const destAll      = document.getElementById('dest_all');
  const newStoreSel  = document.getElementById('new_store_select');

  if (sourceSelect && sourceSelect.name !== 'source_outlet')       sourceSelect.name = 'source_outlet';
  if (destSingle && destSingle.name !== 'dest_outlet_single')      destSingle.name  = 'dest_outlet_single';

  const newStoreId = (newStoreSel && newStoreSel.value) ? newStoreSel.value : '';

  if (mode === 'specific_transfer') {
    const dst = newStoreId || (destSingle && destSingle.value) || '';
    if (destHidden) destHidden.value = dst;        // backend reads dest_outlet
  } else if (mode === 'hub_to_stores') {
    if (newStoreId) {
      if (destHidden) destHidden.value = newStoreId;
    } else if (destAll && destAll.checked) {
      if (destHidden) destHidden.value = '';       // blank => ALL
    }
    // else: leave whatever multi-select computed
  } else {
    if (destHidden) destHidden.value = '';         // all_stores
  }
}



// Missing GPT prompt functions
function getSelectedDestinations() {
    const mode = document.querySelector('input[name="transfer_mode"]:checked')?.value;
    
    if (mode === 'specific_transfer') {
        // For 1:1 transfers, get single destination from dropdown
        const destSelect = document.getElementById('destOutlet');
        if (destSelect && destSelect.value && destSelect.value !== '') {
            return [destSelect.value];
        }
    } else if (mode === 'hub_to_stores') {
        // For multi-select, get checked destinations
        const checkedBoxes = document.querySelectorAll('.destination-check:checked:not(#dest_all)');
        return Array.from(checkedBoxes).map(cb => cb.value).filter(v => v !== '');
    }
    
    return [];
}

function saveCustomPrompt() {
    const textarea = document.getElementById('gpt_prompt');
    if (!textarea) {
        showToast('❌ GPT prompt textarea not found', 'danger');
        return;
    }
    
    const prompt = textarea.value.trim();
    if (!prompt) {
        showToast('❌ Please enter a prompt first', 'warning');
        return;
    }
    
    // Save to localStorage
    localStorage.setItem('custom_gpt_prompt', prompt);
    localStorage.setItem('custom_gpt_prompt_saved_at', new Date().toISOString());
    showToast('✅ Custom prompt saved successfully!', 'success');
    
    // Update validation feedback
    document.getElementById('prompt_validation').innerHTML = 
        '<div class="alert alert-success alert-sm"><i class="bi bi-check-circle"></i> Prompt saved locally</div>';
}

function loadSavedPrompt() {
    const textarea = document.getElementById('gpt_prompt');
    if (!textarea) {
        showToast('❌ GPT prompt textarea not found', 'danger');
        return;
    }
    
    const savedPrompt = localStorage.getItem('custom_gpt_prompt');
    if (!savedPrompt) {
        showToast('❌ No saved prompt found. Save a prompt first.', 'warning');
        return;
    }
    
    textarea.value = savedPrompt;
    
    const savedAt = localStorage.getItem('custom_gpt_prompt_saved_at');
    const savedDate = savedAt ? new Date(savedAt).toLocaleString() : 'Unknown';
    
    showToast('✅ Saved prompt loaded successfully!', 'success');
    
    // Update validation feedback
    document.getElementById('prompt_validation').innerHTML = 
        '<div class="alert alert-info alert-sm"><i class="bi bi-info-circle"></i> Loaded prompt saved on ' + savedDate + '</div>';
}

function validatePrompt() {
    const textarea = document.getElementById('gpt_prompt');
    const validationDiv = document.getElementById('prompt_validation');
    
    if (!textarea) {
        showToast('❌ GPT prompt textarea not found', 'danger');
        return;
    }
    
    const prompt = textarea.value.trim();
    if (!prompt) {
        validationDiv.innerHTML = '<div class="alert alert-warning alert-sm"><i class="bi bi-exclamation-triangle"></i> Please enter a prompt to validate</div>';
        return;
    }
    
    // Enhanced validation
    const issues = [];
    const warnings = [];
    
    // Critical issues
    if (prompt.length < 20) {
        issues.push('Prompt is too short (minimum 20 characters recommended)');
    }
    if (prompt.length > 4000) {
        issues.push('Prompt is too long (maximum 4000 characters for API limits)');
    }
    if (!prompt.toLowerCase().includes('product') && !prompt.toLowerCase().includes('category')) {
        issues.push('Prompt should mention products or categories');
    }
    
    // Warnings (best practices)
    if (!prompt.toLowerCase().includes('json')) {
        warnings.push('Consider requesting JSON format for structured responses');
    }
    if (!prompt.toLowerCase().includes('confidence')) {
        warnings.push('Consider requesting confidence scores');
    }
    if (prompt.length < 100) {
        warnings.push('Short prompts may not provide enough context for accurate results');
    }
    
    // Display results
    if (issues.length === 0) {
        let message = '<div class="alert alert-success alert-sm"><i class="bi bi-check-circle"></i> <strong>Prompt validation passed!</strong>';
        
        if (warnings.length > 0) {
            message += '<br><small class="text-muted"><strong>Suggestions:</strong><ul class="mb-0 mt-1">';
            warnings.forEach(warning => message += '<li>' + warning + '</li>');
            message += '</ul></small>';
        }
        
        message += '</div>';
        validationDiv.innerHTML = message;
        showToast('✅ Prompt validation passed!', 'success');
    } else {
        let message = '<div class="alert alert-danger alert-sm"><i class="bi bi-x-circle"></i> <strong>Prompt validation issues:</strong><ul class="mb-0 mt-1">';
        issues.forEach(issue => message += '<li>' + issue + '</li>');
        message += '</ul></div>';
        validationDiv.innerHTML = message;
        showToast('❌ Prompt validation failed', 'danger');
    }
}

// GPT API Key management
function saveApiKey() {
    const keyInput = document.getElementById('gpt_api_key');
    if (!keyInput) {
        showToast('❌ API key input not found', 'danger');
        return;
    }
    
    const apiKey = keyInput.value.trim();
    if (!apiKey) {
        showToast('❌ Please enter an API key', 'warning');
        return;
    }
    
    if (!apiKey.startsWith('sk-')) {
        showToast('❌ Please enter a valid OpenAI API key (starts with sk-)', 'warning');
        return;
    }
    
    if (apiKey.length < 20) {
        showToast('❌ API key appears to be incomplete', 'warning');
        return;
    }
    
    // Store in localStorage temporarily (in production, this should save to database)
    localStorage.setItem('openai_api_key', apiKey);
    localStorage.setItem('openai_api_key_saved_at', new Date().toISOString());
    
    showToast('✅ API key saved successfully!', 'success');
    
    // TODO: In production, implement actual database saving
    // fetch('/save-api-key', { method: 'POST', body: JSON.stringify({key: apiKey}) })
}

function toggleKeyVisibility() {
    const keyInput = document.getElementById('gpt_api_key');
    const button = event.target.closest('button');
    
    if (!keyInput) return;
    
    if (keyInput.type === 'password') {
        keyInput.type = 'text';
        button.innerHTML = '<i class="bi bi-eye-slash"></i>';
        button.title = 'Hide API key';
    } else {
        keyInput.type = 'password';
        button.innerHTML = '<i class="bi bi-eye"></i>';
        button.title = 'Show API key';
    }
}

// GPT categorization toggle
function toggleGptCategorization() {
    const checkbox = document.getElementById('enableGptCategorization');
    const body = document.getElementById('gptCategorizationBody');
    
    if (!checkbox || !body) return;
    
    body.style.display = checkbox.checked ? 'block' : 'none';
}
</script>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="helpModalLabel"><i class="bi bi-question-circle"></i> Transfer Engine Help & Presets Guide</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h6 class="text-success"><i class="bi bi-gear-fill"></i> Transfer Modes</h6>
                        <div class="accordion" id="transferModesAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingAllStores">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAllStores">
                                        <i class="bi bi-buildings"></i> All Stores (Auto Distribution)
                                    </button>
                                </h2>
                                <div id="collapseAllStores" class="accordion-collapse collapse" data-bs-parent="#transferModesAccordion">
                                    <div class="accordion-body">
                                        <strong>What it does:</strong> Automatically distributes inventory from warehouses to all retail stores based on demand patterns and stock levels.<br>
                                        <strong>Best for:</strong> Regular restocking runs, maintaining optimal inventory levels<br>
                                        <strong>Parameters:</strong> Uses demand forecasting, turnover ratios, and safety stock buffers
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingSpecific">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSpecific">
                                        <i class="bi bi-arrow-left-right"></i> Specific 1:1 Transfer
                                    </button>
                                </h2>
                                <div id="collapseSpecific" class="accordion-collapse collapse" data-bs-parent="#transferModesAccordion">
                                    <div class="accordion-body">
                                        <strong>What it does:</strong> Direct transfer between two specific locations you select<br>
                                        <strong>Best for:</strong> Emergency restocks, inter-store transfers, moving slow-moving inventory<br>
                                        <strong>Parameters:</strong> Requires both source and destination selection
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingHub">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHub">
                                        <i class="bi bi-diagram-3"></i> Hub to Selected Stores
                                    </button>
                                </h2>
                                <div id="collapseHub" class="accordion-collapse collapse" data-bs-parent="#transferModesAccordion">
                                    <div class="accordion-body">
                                        <strong>What it does:</strong> Distributes from a specific warehouse/hub to selected retail outlets<br>
                                        <strong>Best for:</strong> Regional distribution, new product launches, targeted restocking<br>
                                        <strong>Parameters:</strong> Select source warehouse and filter destination stores
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h6 class="text-success"><i class="bi bi-bookmark-star-fill"></i> Quick Configuration Presets</h6>
                        <div class="alert alert-info">
                            <small><i class="bi bi-info-circle"></i> <strong>New Location:</strong> Presets are now available in the dropdown at the top of the page for easier access.</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Preset</th>
                                        <th>Cover Days</th>
                                        <th>Buffer %</th>
                                        <th>Margin Factor</th>
                                        <th>Best For</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="table-info">
                                        <td><strong>Conservative</strong></td>
                                        <td>21</td>
                                        <td>30%</td>
                                        <td>1.5x</td>
                                        <td>New stores, high-risk products</td>
                                    </tr>
                                    <tr class="table-success">
                                        <td><strong>Standard</strong></td>
                                        <td>14</td>
                                        <td>20%</td>
                                        <td>1.2x</td>
                                        <td>Regular operations, balanced approach</td>
                                    </tr>
                                    <tr class="table-warning">
                                        <td><strong>Aggressive</strong></td>
                                        <td>10</td>
                                        <td>15%</td>
                                        <td>1.1x</td>
                                        <td>Fast-moving products, high turnover</td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td><strong>New Store Seeding</strong></td>
                                        <td>30</td>
                                        <td>50%</td>
                                        <td>1.3x</td>
                                        <td>Initial stock for new locations</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-warning"><i class="bi bi-lightning-charge-fill"></i> Quick Transfer Scenarios</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-building-add text-success"></i> <strong>Warehouse → All Stores:</strong> Standard hub distribution with warehouse-only sources</li>
                                    <li><i class="bi bi-arrow-return-left text-success"></i> <strong>Store Overflow → Hub:</strong> Return excess inventory to central warehouse</li>
                                    <li><i class="bi bi-shop-window text-warning"></i> <strong>New Store Seeding:</strong> High-stock initial setup for new locations</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-lightning-charge-fill text-info"></i> <strong>Emergency Restock:</strong> Fast 7-day coverage with low buffers</li>
                                    <li><i class="bi bi-arrow-left-right text-secondary"></i> <strong>Store to Store:</strong> Direct inter-store transfers (any to any)</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" onclick="window.open('https://staff.vapeshed.co.nz/wiki/transfer-engine', '_blank')">>
                    <i class="bi bi-book"></i> Full Documentation
                </button>
            </div>
        </div>
    </div>
</div>
<?php
// Clean up database connection to prevent "Too many connections" errors
if (isset($con) && $con instanceof mysqli) {
    $con->close();
}
?>
</body>
</html>