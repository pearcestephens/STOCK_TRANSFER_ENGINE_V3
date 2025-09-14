#!/bin/bash

# CLEANUP SCRIPT - Moving rubbish to archive

echo "🧹 STARTING COMPREHENSIVE CLEANUP..."

# Create archive directories
mkdir -p ARCHIVE/backup_files
mkdir -p ARCHIVE/demo_files  
mkdir -p ARCHIVE/old_docs
mkdir -p ARCHIVE/experimental

echo "📁 Archive directories created"

# Move all .bak backup files
echo "🗂️ Moving backup files..."
mv *.bak.* ARCHIVE/backup_files/ 2>/dev/null || echo "No .bak files found"

# Move demo HTML files
echo "🎭 Moving demo HTML files..."
mv dashboard_*.html ARCHIVE/demo_files/ 2>/dev/null
mv operational_dashboard.html* ARCHIVE/demo_files/ 2>/dev/null
mv production_dashboard.html ARCHIVE/demo_files/ 2>/dev/null
mv turbo_dashboard.html ARCHIVE/demo_files/ 2>/dev/null
mv WORKING_DASHBOARD.html ARCHIVE/demo_files/ 2>/dev/null
mv QUICK_LINKS.html ARCHIVE/demo_files/ 2>/dev/null

# Move experimental PHP files
echo "🧪 Moving experimental files..."
mv neural_brain_integration.php ARCHIVE/experimental/ 2>/dev/null
mv TurboAutonomousTransferEngine.php ARCHIVE/experimental/ 2>/dev/null
mv AutonomousTransferEngine.php ARCHIVE/experimental/ 2>/dev/null
mv AITransferOrchestrator.php ARCHIVE/experimental/ 2>/dev/null
mv transfer_command_center.php ARCHIVE/experimental/ 2>/dev/null
mv transfer_control_center.php ARCHIVE/experimental/ 2>/dev/null
mv turbo_*.php ARCHIVE/experimental/ 2>/dev/null

# Move old documentation
echo "📚 Moving old documentation..."
mv COMPLETE_SYSTEM_ARCHITECTURE.md ARCHIVE/old_docs/ 2>/dev/null
mv COMPLETE_DASHBOARD_MANIFEST.md ARCHIVE/old_docs/ 2>/dev/null  
mv TURBO_IMPLEMENTATION_GUIDE.md ARCHIVE/old_docs/ 2>/dev/null
mv PROJECT_ROADMAP.md ARCHIVE/old_docs/ 2>/dev/null
mv IMPLEMENTATION_GUIDE.md ARCHIVE/old_docs/ 2>/dev/null
mv PRODUCTION_READY.md ARCHIVE/old_docs/ 2>/dev/null
mv SYSTEM_DOCUMENTATION.md ARCHIVE/old_docs/ 2>/dev/null

# Move debug and test files
echo "🐛 Moving debug files..."
mv debug_inventory.php ARCHIVE/experimental/ 2>/dev/null
mv test_seeder.php ARCHIVE/experimental/ 2>/dev/null
mv TestSuite.php ARCHIVE/experimental/ 2>/dev/null
mv RUN_DEBUG.php ARCHIVE/experimental/ 2>/dev/null
mv ENGINE_DEBUG.php ARCHIVE/experimental/ 2>/dev/null
mv turbo_debugger.php ARCHIVE/experimental/ 2>/dev/null

# Move utility files
echo "🔧 Moving utility files..."
mv cleanup_duplicates.php ARCHIVE/experimental/ 2>/dev/null
mv check_table_structure.php* ARCHIVE/experimental/ 2>/dev/null
mv db_check.php ARCHIVE/experimental/ 2>/dev/null
mv report.php ARCHIVE/experimental/ 2>/dev/null
mv standalone_cli.php* ARCHIVE/experimental/ 2>/dev/null

# Move dashboard fragments
echo "🎨 Moving dashboard fragments..."
mv dashboard_*.js ARCHIVE/demo_files/ 2>/dev/null
mv dashboard_*.css ARCHIVE/demo_files/ 2>/dev/null
mv dashboard_*.html ARCHIVE/demo_files/ 2>/dev/null

echo "✅ CLEANUP COMPLETE!"
echo "📊 Generating cleanup report..."

# Count archived files
backup_count=$(find ARCHIVE/backup_files -type f 2>/dev/null | wc -l)
demo_count=$(find ARCHIVE/demo_files -type f 2>/dev/null | wc -l)
doc_count=$(find ARCHIVE/old_docs -type f 2>/dev/null | wc -l)
exp_count=$(find ARCHIVE/experimental -type f 2>/dev/null | wc -l)

echo ""
echo "📈 CLEANUP SUMMARY:"
echo "   Backup files archived: $backup_count"
echo "   Demo files archived: $demo_count" 
echo "   Old docs archived: $doc_count"
echo "   Experimental archived: $exp_count"
echo ""
echo "🎯 Workspace is now clean and organized!"
