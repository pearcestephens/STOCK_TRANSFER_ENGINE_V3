<?php
/**
 * QUICK ENGINE STATUS CHECK
 * Fast status without heavy database operations
 */

echo "⚡ QUICK TRANSFER ENGINE STATUS\n";
echo "==============================\n\n";

// Check 1: Core Files
echo "1️⃣ CORE FILES CHECK:\n";
$files = [
    'index.php' => filesize('index.php') ?? 0,
    'cli_api.php' => filesize('cli_api.php') ?? 0,
    'NewStoreSeeder.php' => filesize('NewStoreSeeder.php') ?? 0,
    'WORKING_DASHBOARD.html' => filesize('WORKING_DASHBOARD.html') ?? 0
];

foreach ($files as $file => $size) {
    $status = $size > 0 ? '✅' : '❌';
    echo "$status $file (" . number_format($size) . " bytes)\n";
}

echo "\n2️⃣ QUICK DATABASE TEST:\n";
try {
    // Simple connection test
    $config_exists = file_exists('../../functions/mysql.php');
    echo ($config_exists ? '✅' : '❌') . " MySQL functions available\n";
    
    if ($config_exists) {
        require_once '../../functions/mysql.php';
        $connected = connectToSQL();
        echo ($connected ? '✅' : '❌') . " Database connection\n";
        
        if ($connected) {
            global $con;
            $result = $con->query("SELECT 1");
            echo ($result ? '✅' : '❌') . " Database query test\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n3️⃣ CLASS LOADING TEST:\n";
try {
    require_once 'NewStoreSeeder.php';
    echo "✅ NewStoreSeeder class loaded\n";
    
    if (isset($con)) {
        $seeder = new NewStoreSeeder($con);
        echo "✅ NewStoreSeeder instance created\n";
    } else {
        echo "⚠️ No database connection for seeder test\n";
    }
} catch (Exception $e) {
    echo "❌ NewStoreSeeder error: " . $e->getMessage() . "\n";
}

echo "\n📊 OVERALL STATUS:\n";
$all_files_exist = $files['index.php'] > 0 && $files['cli_api.php'] > 0 && 
                   $files['NewStoreSeeder.php'] > 0;

if ($all_files_exist && isset($connected) && $connected) {
    echo "🚀 TRANSFER ENGINE: READY TO RUN\n";
    echo "✅ All core components operational\n";
    echo "✅ Database connected\n";
    echo "✅ Classes loaded successfully\n\n";
    
    echo "🎯 NEXT STEPS:\n";
    echo "1. Open WORKING_DASHBOARD.html in browser\n";
    echo "2. Or run: php RUN_DEBUG.php for full test\n";
    echo "3. Or use CLI: php cli_api.php action=get_outlets\n";
} else {
    echo "⚠️ TRANSFER ENGINE: ISSUES DETECTED\n";
    echo "Please check the failed components above\n";
}
?>
