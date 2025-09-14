<?php
/**
 * SIMPLE TEST RUNNER
 * Executes the engine debug and captures results
 */

echo "🔍 RUNNING TRANSFER ENGINE DEBUG...\n";
echo "====================================\n\n";

// Capture output from the debug script
ob_start();
try {
    include __DIR__ . '/ENGINE_DEBUG.php';
    $debug_output = ob_get_clean();
    echo $debug_output;
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ Debug script failed: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";
    echo "📍 Line: " . $e->getLine() . "\n";
}

echo "\n";
echo "🏁 DEBUG COMPLETE - Check results above\n";
echo "========================================\n";

// Quick file status check
echo "\n📁 CORE FILES STATUS:\n";
$core_files = [
    'index.php' => 'Main Transfer Engine',
    'cli_api.php' => 'CLI API Interface', 
    'NewStoreSeeder.php' => 'Store Seeding Engine',
    'WORKING_DASHBOARD.html' => 'Web Dashboard',
    'STATUS.php' => 'System Status Page'
];

foreach ($core_files as $file => $description) {
    $status = file_exists($file) ? '✅' : '❌';
    $size = file_exists($file) ? '(' . number_format(filesize($file)) . ' bytes)' : '';
    echo "$status $file - $description $size\n";
}
?>
