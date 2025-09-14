<?php
/**
 * Cleanup Duplicates - Remove backup and duplicate files
 */

$files_to_remove = [
    'api.php.bak.20250914_141831',
    'check_table_structure.php.bak.20250914_141831',
    'dashboard_body.html.bak.20250914_141831',
    'MANIFEST.md.bak.20250914_141831',
    'NewStoreSeederController.php.bak.20250914_141831',
    'PackRulesService.php.bak.20250914_141831',
    'operational_dashboard.html.bak.20250914_141831',
    'standalone_cli.php.bak.20250914_141831',
    
    // Duplicate/unused files
    'check_table_structure.php',
    'dashboard_body.html',
    'dashboard_complete.php',
    'dashboard_scripts_part1.js',
    'dashboard_scripts_part2.js', 
    'dashboard_scripts_part3.js',
    'dashboard_settings.html',
    'dashboard_styles.css',
    'dashboard.php',
    'db_check.php',
    'debug_inventory.php',
    'main.php',
    'neural_brain_integration.php',
    'new_store_seed.php',
    'operational_dashboard.html',
    'phpunit.xml',
    'report.php',
    'standalone_cli.php',
    'test_seeder.php',
    'transfer_control_center.php',
    'transfer_dashboard.php'
];

$removed = 0;
foreach ($files_to_remove as $file) {
    if (file_exists($file)) {
        unlink($file);
        echo "Removed: $file\n";
        $removed++;
    }
}

echo "\nCleaned $removed duplicate/backup files\n";
echo "✅ Core system ready!\n";
?>
