#!/bin/bash

# ================================================
# NewTransferV3 Project Cleanup & Organization
# ================================================

echo "🧹 Starting NewTransferV3 Project Cleanup..."

# Create organized directory structure
mkdir -p ARCHIVE/{backups,old-docs,duplicates,temp-files}
mkdir -p docs/{architecture,api,database,deployment}
mkdir -p logs/{transfer,system,debug}

# ================================================
# STEP 1: Archive backup files
# ================================================
echo "📁 Moving backup files..."
find . -name "*.bak.*" -type f -exec mv {} ARCHIVE/backups/ \; 2>/dev/null
echo "   → Backup files archived"

# ================================================
# STEP 2: Archive duplicate and old files
# ================================================
echo "🗂️  Archiving duplicates and old files..."

# Move cleanup-related temp files
mv cleanup.sh ARCHIVE/temp-files/ 2>/dev/null
mv cleanup_duplicates.php ARCHIVE/temp-files/ 2>/dev/null
mv CLEANUP_NOW.php ARCHIVE/temp-files/ 2>/dev/null

# Move old documentation that's been superseded
mv CLEANUP_ANALYSIS.md ARCHIVE/old-docs/ 2>/dev/null
mv CLEANUP_COMPLETED.md ARCHIVE/old-docs/ 2>/dev/null
mv FINAL_CLEANUP_PLAN.md ARCHIVE/old-docs/ 2>/dev/null

# Move test and debug files
mv test_*.php ARCHIVE/temp-files/ 2>/dev/null
mv debug_*.php ARCHIVE/temp-files/ 2>/dev/null
mv *_test.php ARCHIVE/temp-files/ 2>/dev/null

echo "   → Duplicate files archived"

# ================================================
# STEP 3: Organize logs
# ================================================
echo "📋 Organizing log files..."
find . -name "*.log" -type f -exec mv {} logs/system/ \; 2>/dev/null
echo "   → Log files organized"

# ================================================
# STEP 4: Count results
# ================================================
echo ""
echo "📊 Cleanup Summary:"
echo "   Backup files: $(ls -1 ARCHIVE/backups/ 2>/dev/null | wc -l) archived"
echo "   Old docs: $(ls -1 ARCHIVE/old-docs/ 2>/dev/null | wc -l) archived"
echo "   Temp files: $(ls -1 ARCHIVE/temp-files/ 2>/dev/null | wc -l) archived"
echo "   Log files: $(ls -1 logs/system/ 2>/dev/null | wc -l) organized"

echo ""
echo "✅ Cleanup completed successfully!"
echo "🎯 Project workspace is now clean and organized."
