<?php
/**
 * NewTransferV3 MVC Bootstrap
 * 
 * Production-ready entry point for inventory transfer system
 * Maintains backward compatibility with existing API
 */

// Set timezone and error reporting
date_default_timezone_set('Pacific/Auckland');
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'NewTransferV3\\';
    $baseDir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load configuration
$config = require_once __DIR__ . '/config.php';

// Initialize services
use NewTransferV3\Services\DatabaseService;
use NewTransferV3\Services\NeuralBrainService;
use NewTransferV3\Core\TransferEngine;
use NewTransferV3\Controllers\TransferController;

try {
    // Database service
    $db = new DatabaseService($config['database']);
    
    // Neural Brain service
    $neuralBrain = new NeuralBrainService($config['neural_brain'] ?? []);
    
    // Transfer engine
    $engine = new TransferEngine($db, $neuralBrain, $config['transfer'] ?? []);
    
    // Main controller
    $controller = new TransferController($engine, $db);
    
    // Handle request
    $response = $controller->handleRequest();
    
    // Output JSON response
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Emergency error response
    header('Content-Type: application/json');
    header('HTTP/1.1 500 Internal Server Error');
    
    echo json_encode([
        'success' => false,
        'error' => 'System initialization failed',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
    
    // Log critical error
    error_log("NewTransferV3 Bootstrap Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}
