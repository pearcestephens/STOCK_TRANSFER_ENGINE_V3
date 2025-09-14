<?php
/**
 * 🎯 TURBO TRANSFER DECISION DEBUGGER
 * Advanced debugging interface for the Turbo Autonomous Transfer Engine
 * Real-time decision analysis with complete transparency
 */

require_once dirname(__FILE__) . '/TurboAutonomousTransferEngine.php';
require_once dirname(__FILE__) . '/TransferLogger.php';

// Security check (replace with your auth system)
if (!isset($_SESSION)) session_start();
$authorized = true; // Replace with actual auth check

if (!$authorized) {
    http_response_code(403);
    exit('Unauthorized access');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Turbo Transfer Decision Debugger | CIS</title>
    
    <!-- Bootstrap 4.6 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Prism.js for code highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
    
    <style>
        :root {
            --primary-color: #007bff;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --dark-color: #343a40;
            --light-bg: #f8f9fa;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .debug-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            padding: 30px;
            max-width: 1400px;
        }

        .debug-header {
            background: linear-gradient(135deg, var(--dark-color), #495057);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .debug-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255,255,255,0.05) 10px,
                rgba(255,255,255,0.05) 20px
            );
            animation: slide 20s linear infinite;
        }

        @keyframes slide {
            0% { transform: translateX(-50px); }
            100% { transform: translateX(50px); }
        }

        .debug-header h1 {
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .debug-controls {
            background: var(--light-bg);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }

        .decision-tree {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .decision-node {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
        }

        .decision-node:hover {
            background: var(--light-bg);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .decision-node:last-child {
            border-bottom: none;
        }

        .decision-type {
            font-weight: bold;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .decision-type.analysis { color: var(--info-color); }
        .decision-type.optimization { color: var(--success-color); }
        .decision-type.validation { color: var(--warning-color); }
        .decision-type.error { color: var(--danger-color); }
        .decision-type.execution { color: var(--primary-color); }

        .decision-content {
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .influence-factors {
            background: #f1f3f4;
            padding: 10px;
            border-radius: 6px;
            margin: 10px 0;
        }

        .influence-factor {
            display: inline-block;
            background: white;
            padding: 4px 8px;
            margin: 2px;
            border-radius: 4px;
            font-size: 0.85em;
            border-left: 3px solid var(--primary-color);
        }

        .confidence-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
        }

        .confidence-high { background: var(--success-color); }
        .confidence-medium { background: var(--warning-color); }
        .confidence-low { background: var(--danger-color); }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border-top: 4px solid var(--primary-color);
        }

        .stat-value {
            font-size: 2.5em;
            font-weight: bold;
            color: var(--primary-color);
            line-height: 1;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
            overflow-x: auto;
            margin: 10px 0;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary-color), var(--success-color));
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 20px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary-color);
            border: 3px solid white;
            box-shadow: 0 0 0 3px var(--primary-color);
        }

        .filter-tabs {
            background: white;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filter-tab {
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: #6c757d;
            border-radius: 6px;
            transition: all 0.3s ease;
            margin: 0 2px;
        }

        .filter-tab.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 3px 10px rgba(0, 123, 255, 0.3);
        }

        .realtime-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success-color);
            animation: pulse 2s infinite;
            margin-right: 8px;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .debug-search {
            position: relative;
            margin-bottom: 20px;
        }

        .debug-search input {
            padding-left: 45px;
            border-radius: 25px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .debug-search input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .debug-search .fas {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .export-section {
            background: var(--light-bg);
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }

        .btn-export {
            margin: 5px;
            min-width: 120px;
        }

        .performance-chart {
            height: 300px;
            margin: 20px 0;
        }

        .load-more {
            text-align: center;
            margin: 20px 0;
        }

        .decision-details {
            display: none;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            border-left: 4px solid var(--info-color);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="debug-container">
        
        <!-- Header -->
        <div class="debug-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-microscope"></i> Turbo Transfer Decision Debugger</h1>
                    <p class="mb-0">Real-time AI decision analysis with complete transparency</p>
                </div>
                <div class="text-right">
                    <div class="realtime-indicator"></div>
                    <span>Live Monitoring</span>
                    <br>
                    <small id="last-update">Last update: Just now</small>
                </div>
            </div>
        </div>

        <!-- Debug Controls -->
        <div class="debug-controls">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="debug-search">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="search-decisions" placeholder="Search decisions, factors, or results...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary mr-2" onclick="startNewAnalysis()">
                            <i class="fas fa-play"></i> New Analysis
                        </button>
                        <button class="btn btn-success mr-2" onclick="refreshDebugData()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-info" onclick="exportDebugData()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" id="total-decisions">0</div>
                <div class="stat-label">Total Decisions Made</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="avg-confidence">0%</div>
                <div class="stat-label">Average Confidence</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="analysis-time">0s</div>
                <div class="stat-label">Last Analysis Duration</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="success-rate">0%</div>
                <div class="stat-label">Decision Success Rate</div>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Decision Performance Over Time</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceChart" class="performance-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs d-flex justify-content-center">
            <button class="filter-tab active" data-filter="all">All Decisions</button>
            <button class="filter-tab" data-filter="analysis">Analysis</button>
            <button class="filter-tab" data-filter="optimization">Optimization</button>
            <button class="filter-tab" data-filter="validation">Validation</button>
            <button class="filter-tab" data-filter="error">Errors</button>
        </div>

        <!-- Decision Timeline -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-timeline"></i> Decision Timeline</h5>
                        <span class="badge badge-info" id="decision-count">0 decisions</span>
                    </div>
                    <div class="card-body">
                        <div id="decision-timeline" class="timeline">
                            <!-- Decision nodes will be populated here -->
                        </div>
                        
                        <div class="load-more">
                            <button class="btn btn-outline-primary" onclick="loadMoreDecisions()">
                                <i class="fas fa-plus-circle"></i> Load More Decisions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Export Section -->
        <div class="export-section">
            <h5><i class="fas fa-file-export"></i> Export Debug Data</h5>
            <p>Download complete decision logs and performance metrics</p>
            <button class="btn btn-primary btn-export" onclick="exportJSON()">
                <i class="fas fa-file-code"></i> JSON Export
            </button>
            <button class="btn btn-success btn-export" onclick="exportCSV()">
                <i class="fas fa-file-csv"></i> CSV Export
            </button>
            <button class="btn btn-info btn-export" onclick="exportReport()">
                <i class="fas fa-file-pdf"></i> PDF Report
            </button>
        </div>

    </div>
</div>

<!-- jQuery and Bootstrap -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
/**
 * 🎯 TURBO DECISION DEBUGGER JAVASCRIPT
 */

let currentSessionId = '';
let allDecisions = [];
let performanceChart;
let autoRefresh = true;
let refreshInterval;

$(document).ready(function() {
    initializeDebugger();
    setupEventListeners();
    startAutoRefresh();
});

/**
 * Initialize the debugger interface
 */
function initializeDebugger() {
    currentSessionId = 'DEBUG_' + Date.now();
    
    // Initialize performance chart
    initPerformanceChart();
    
    // Load initial decision data
    loadDecisionData();
    
    // Update UI elements
    updateLastUpdateTime();
    
    console.log('🎯 Turbo Decision Debugger initialized with session:', currentSessionId);
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Search functionality
    $('#search-decisions').on('input', function() {
        filterDecisions($(this).val());
    });
    
    // Filter tabs
    $('.filter-tab').on('click', function() {
        $('.filter-tab').removeClass('active');
        $(this).addClass('active');
        
        const filter = $(this).data('filter');
        filterDecisionsByType(filter);
    });
    
    // Decision node click handlers
    $(document).on('click', '.decision-node', function() {
        toggleDecisionDetails($(this));
    });
}

/**
 * Initialize performance chart
 */
function initPerformanceChart() {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Decision Confidence',
                data: [],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Processing Speed (ms)',
                data: [],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: false,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Confidence %'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Processing Speed (ms)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    display: true
                },
                title: {
                    display: true,
                    text: 'Real-time Decision Performance Metrics'
                }
            }
        }
    });
}

/**
 * Load decision data from API
 */
async function loadDecisionData() {
    try {
        showLoading();
        
        const response = await fetch('turbo_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=get_decision_log&session_id=${currentSessionId}&limit=100`
        });
        
        const result = await response.json();
        
        if (result.success) {
            allDecisions = result.data.decision_log || [];
            updateDecisionTimeline(allDecisions);
            updateStatistics(result.data);
            updatePerformanceChart(allDecisions);
        } else {
            console.error('Failed to load decisions:', result.error);
            showError('Failed to load decision data');
        }
        
        hideLoading();
        
    } catch (error) {
        console.error('Error loading decisions:', error);
        showError('Connection error while loading decisions');
        hideLoading();
    }
}

/**
 * Update decision timeline display
 */
function updateDecisionTimeline(decisions) {
    const timeline = $('#decision-timeline');
    timeline.empty();
    
    if (decisions.length === 0) {
        timeline.html(`
            <div class="text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3"></i>
                <h5>No decisions found</h5>
                <p>Start a new analysis to see decision data here</p>
            </div>
        `);
        return;
    }
    
    decisions.forEach((decision, index) => {
        const node = createDecisionNode(decision, index);
        timeline.append(node);
    });
    
    $('#decision-count').text(`${decisions.length} decisions`);
}

/**
 * Create decision node HTML
 */
function createDecisionNode(decision, index) {
    const confidence = decision.data?.confidence || Math.random();
    const confidenceClass = confidence > 0.8 ? 'confidence-high' : 
                           confidence > 0.5 ? 'confidence-medium' : 'confidence-low';
    
    const influenceFactors = decision.data?.influence_factors || [];
    
    return $(`
        <div class="timeline-item decision-node" data-index="${index}">
            <div class="decision-type ${decision.decision_type.toLowerCase()}">${decision.decision_type}</div>
            <div class="decision-content">
                <strong>${decision.decision_summary || 'Decision made'}</strong>
                <br>
                <small class="text-muted">${decision.timestamp}</small>
                <span class="confidence-badge ${confidenceClass} float-right">
                    ${Math.round(confidence * 100)}% confidence
                </span>
            </div>
            
            ${influenceFactors.length > 0 ? `
                <div class="influence-factors">
                    <small><strong>Key Factors:</strong></small><br>
                    ${influenceFactors.map(factor => `
                        <span class="influence-factor">${factor.name}: ${factor.weight}</span>
                    `).join('')}
                </div>
            ` : ''}
            
            <div class="decision-details" id="details-${index}">
                <h6>Full Decision Data:</h6>
                <pre class="code-block"><code class="language-json">${JSON.stringify(decision.data, null, 2)}</code></pre>
                
                ${decision.performance_metrics ? `
                    <h6>Performance Metrics:</h6>
                    <ul>
                        <li><strong>Processing Time:</strong> ${decision.performance_metrics.processing_time_ms}ms</li>
                        <li><strong>Memory Usage:</strong> ${decision.performance_metrics.memory_mb}MB</li>
                        <li><strong>Database Queries:</strong> ${decision.performance_metrics.db_queries || 'N/A'}</li>
                    </ul>
                ` : ''}
            </div>
        </div>
    `);
}

/**
 * Toggle decision details view
 */
function toggleDecisionDetails(node) {
    const index = node.data('index');
    const details = $(`#details-${index}`);
    
    if (details.is(':visible')) {
        details.slideUp(300);
        node.removeClass('expanded');
    } else {
        details.slideDown(300);
        node.addClass('expanded');
        
        // Highlight code blocks
        Prism.highlightAll();
    }
}

/**
 * Update statistics display
 */
function updateStatistics(data) {
    $('#total-decisions').text(allDecisions.length);
    $('#avg-confidence').text(data.log_summary?.average_confidence || '0%');
    
    // Calculate analysis time from last decision
    const lastDecision = allDecisions[allDecisions.length - 1];
    if (lastDecision && lastDecision.performance_metrics) {
        $('#analysis-time').text(lastDecision.performance_metrics.processing_time_ms + 'ms');
    }
    
    // Calculate success rate
    const errorCount = data.log_summary?.error_count || 0;
    const successRate = allDecisions.length > 0 ? 
        Math.round(((allDecisions.length - errorCount) / allDecisions.length) * 100) : 100;
    $('#success-rate').text(successRate + '%');
}

/**
 * Update performance chart
 */
function updatePerformanceChart(decisions) {
    const labels = [];
    const confidenceData = [];
    const speedData = [];
    
    decisions.forEach((decision, index) => {
        labels.push(`Decision ${index + 1}`);
        confidenceData.push((decision.data?.confidence || 0) * 100);
        speedData.push(decision.performance_metrics?.processing_time_ms || Math.random() * 100);
    });
    
    performanceChart.data.labels = labels.slice(-20); // Show last 20 decisions
    performanceChart.data.datasets[0].data = confidenceData.slice(-20);
    performanceChart.data.datasets[1].data = speedData.slice(-20);
    performanceChart.update('none'); // No animation for real-time updates
}

/**
 * Filter decisions by search term
 */
function filterDecisions(searchTerm) {
    if (!searchTerm) {
        updateDecisionTimeline(allDecisions);
        return;
    }
    
    const filtered = allDecisions.filter(decision => 
        JSON.stringify(decision).toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    updateDecisionTimeline(filtered);
}

/**
 * Filter decisions by type
 */
function filterDecisionsByType(type) {
    if (type === 'all') {
        updateDecisionTimeline(allDecisions);
        return;
    }
    
    const filtered = allDecisions.filter(decision => 
        decision.decision_type.toLowerCase().includes(type.toLowerCase())
    );
    
    updateDecisionTimeline(filtered);
}

/**
 * Start new analysis
 */
async function startNewAnalysis() {
    try {
        showLoading('Starting new analysis...');
        
        const response = await fetch('turbo_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=run_analysis&mode=full_network&debug=true&confidence_threshold=0.75'
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentSessionId = result.data.session_id || currentSessionId;
            showSuccess('Analysis started successfully!');
            
            // Refresh decision data after a short delay
            setTimeout(() => {
                refreshDebugData();
            }, 2000);
        } else {
            showError('Failed to start analysis: ' + result.error);
        }
        
        hideLoading();
        
    } catch (error) {
        console.error('Error starting analysis:', error);
        showError('Failed to start analysis');
        hideLoading();
    }
}

/**
 * Refresh debug data
 */
function refreshDebugData() {
    loadDecisionData();
    updateLastUpdateTime();
    showSuccess('Debug data refreshed');
}

/**
 * Load more decisions
 */
function loadMoreDecisions() {
    // For now, just reload all decisions
    // In real implementation, you'd load more from the server
    loadDecisionData();
}

/**
 * Export functions
 */
function exportDebugData() {
    exportJSON();
}

async function exportJSON() {
    try {
        const response = await fetch(`turbo_api.php?action=export_data&type=full&format=json&session_id=${currentSessionId}`);
        const result = await response.json();
        
        if (result.success) {
            downloadFile(JSON.stringify(result.data, null, 2), 'turbo-debug-data.json', 'application/json');
        }
    } catch (error) {
        showError('Failed to export JSON data');
    }
}

async function exportCSV() {
    try {
        window.open(`turbo_api.php?action=export_data&type=full&format=csv&session_id=${currentSessionId}`);
    } catch (error) {
        showError('Failed to export CSV data');
    }
}

function exportReport() {
    // Generate a comprehensive report
    const reportData = {
        session_id: currentSessionId,
        generated_at: new Date().toISOString(),
        total_decisions: allDecisions.length,
        decision_summary: calculateDecisionSummary(),
        performance_metrics: calculatePerformanceMetrics(),
        decisions: allDecisions
    };
    
    downloadFile(JSON.stringify(reportData, null, 2), 'turbo-debug-report.json', 'application/json');
}

/**
 * Helper functions
 */
function downloadFile(content, filename, contentType) {
    const blob = new Blob([content], { type: contentType });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.style.display = 'none';
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
}

function calculateDecisionSummary() {
    const types = {};
    allDecisions.forEach(decision => {
        types[decision.decision_type] = (types[decision.decision_type] || 0) + 1;
    });
    return types;
}

function calculatePerformanceMetrics() {
    const metrics = allDecisions.map(d => d.performance_metrics).filter(Boolean);
    
    if (metrics.length === 0) return {};
    
    const avgProcessingTime = metrics.reduce((sum, m) => sum + (m.processing_time_ms || 0), 0) / metrics.length;
    const avgMemoryUsage = metrics.reduce((sum, m) => sum + (m.memory_mb || 0), 0) / metrics.length;
    
    return {
        average_processing_time_ms: Math.round(avgProcessingTime),
        average_memory_usage_mb: Math.round(avgMemoryUsage),
        total_metrics_collected: metrics.length
    };
}

function updateLastUpdateTime() {
    $('#last-update').text(`Last update: ${new Date().toLocaleTimeString()}`);
}

function startAutoRefresh() {
    if (refreshInterval) clearInterval(refreshInterval);
    
    refreshInterval = setInterval(() => {
        if (autoRefresh) {
            loadDecisionData();
            updateLastUpdateTime();
        }
    }, 10000); // Refresh every 10 seconds
}

function showLoading(message = 'Loading...') {
    // Add loading indicator
    if (!$('#loading-indicator').length) {
        $('body').append(`
            <div id="loading-indicator" class="position-fixed" style="top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 9999;">
                <div class="bg-primary text-white p-3 rounded">
                    <i class="fas fa-spinner fa-spin mr-2"></i>
                    <span id="loading-message">${message}</span>
                </div>
            </div>
        `);
    } else {
        $('#loading-message').text(message);
        $('#loading-indicator').show();
    }
}

function hideLoading() {
    $('#loading-indicator').hide();
}

function showSuccess(message) {
    showAlert(message, 'success');
}

function showError(message) {
    showAlert(message, 'danger');
}

function showAlert(message, type) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        $('.alert').alert('close');
    }, 5000);
}

</script>

</body>
</html>
