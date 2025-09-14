<?php
/**
 * CLEANUP_NOW.php - Emergency cleanup to remove all the broken crap
 * 
 * This will DELETE all the test files, backups, and duplicates
 * and keep only the essential working files
 */

echo "🗑️  EMERGENCY CLEANUP STARTING...\n\n";

$base_dir = __DIR__;

// Files to DELETE (all the junk)
$delete_patterns = [
    // Test files
    'test_*.php',
    '*_test.php',
    'standalone_test.php',
    'operational_test.php',
    'nuclear_test_runner.php',
    'dashboard_test.php',
    'validation_test.php',
    'simple_validation.php',
    
    // Duplicate dashboards
    'dashboard_standalone.php',
    'dashboard_cis.php',
    'dashboard_cis_proper.php',
    'dashboard_complete_full.php',
    
    // Broken/incomplete files
    'TransferValidationTester.php',
    'TransferValidationEngine.php',
    'IterativeTransferValidator.php',
    'NewTransferV3NuclearTestSuite.php',
    
    // Old/backup files
    'index_mvc.php',
    'clean_cli_api.php',
    'enhanced_schema_implementation.sql',
    'migrate_database.sh',
    'quick_seed_store.sh',
    
    // Documentation that's outdated
    'NUCLEAR_TEST_GUIDE.md',
    'DASHBOARD_USAGE_GUIDE.md',
    'PRODUCTION_READY.md'
];

// Directories to DELETE completely
$delete_dirs = [
    'archive_20250913',
    'archive',
    'artifacts',
    'cleanup',
    'tests',
    'vendor',
    'bin'
];

echo "DELETING JUNK FILES:\n";
foreach ($delete_patterns as $pattern) {
    $files = glob($base_dir . '/' . $pattern);
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            echo "✓ Deleted: " . basename($file) . "\n";
        }
    }
}

echo "\nDELETING JUNK DIRECTORIES:\n";
foreach ($delete_dirs as $dir) {
    $full_path = $base_dir . '/' . $dir;
    if (is_dir($full_path)) {
        // Recursive delete
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($full_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($full_path);
        echo "✓ Deleted directory: $dir\n";
    }
}

echo "\n🎯 KEEPING THESE ESSENTIAL FILES:\n";

// Essential files to KEEP
$keep_files = [
    'index.php',           // Main transfer engine
    'dashboard_complete.php', // Working dashboard
    'cli_api.php',         // CLI interface
    'config.php',          // Configuration
    'bootstrap.php',       // Bootstrap
    'api.php',            // API interface
    'main.php',           // Main logic
    'report.php'          // Reporting
];

foreach ($keep_files as $file) {
    if (file_exists($base_dir . '/' . $file)) {
        echo "✓ Keeping: $file\n";
    } else {
        echo "⚠️  Missing: $file\n";
    }
}

echo "\n✅ CLEANUP COMPLETE!\n";
echo "Now you have a clean working directory with only essential files.\n\n";

// Show what's left
echo "📁 REMAINING FILES:\n";
$remaining = array_diff(scandir($base_dir), ['.', '..']);
foreach ($remaining as $item) {
    if (is_file($base_dir . '/' . $item)) {
        echo "   $item\n";
    }
}

echo "\n🚀 Ready to rebuild properly!\n";
