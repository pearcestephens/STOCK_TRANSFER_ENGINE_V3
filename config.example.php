<?php
/**
 * NewTransferV3 Configuration Example
 * 
 * Copy this file to config.php and update with your actual credentials
 * 
 * SECURITY: Never commit config.php with real credentials to version control
 */

return [
    'database' => [
        'host' => 'localhost',
        'database' => 'your_database_name',
        'username' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ],
    
    'neural_brain' => [
        'enabled' => true,
        'api_url' => 'https://your-domain.com/assets/functions/',
        'timeout' => 30,
        'session_prefix' => 'transfer_'
    ],
    
    'transfer' => [
        'cover_days' => 14,              // Demand forecast period (days)
        'buffer_pct' => 20,              // Safety stock percentage
        'default_floor_qty' => 2,        // Minimum transfer quantity
        'margin_factor' => 1.2,          // Profitability multiplier
        'max_products' => 0,             // Product limit (0 = unlimited)
        'rounding_mode' => 'nearest'     // Pack outer rounding: nearest|up|down|smart
    ],
    
    'system' => [
        'timezone' => 'Pacific/Auckland',
        'log_level' => 'INFO',           // DEBUG|INFO|WARNING|ERROR
        'max_execution_time' => 900,     // Maximum script execution (seconds)
        'memory_limit' => '3072M'        // Memory allocation for large operations
    ],
    
    'security' => [
        'validate_outlets' => true,       // Validate outlet IDs exist
        'require_simulation' => false,    // Force simulation mode
        'log_all_operations' => true,     // Log all transfer operations
        'enable_debug_mode' => false     // Enable debug information
    ]
];