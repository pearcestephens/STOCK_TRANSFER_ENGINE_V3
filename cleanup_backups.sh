#!/bin/bash

# Move all .bak.* files to ARCHIVE folder
echo "Moving backup files to ARCHIVE..."

# Create ARCHIVE/backups if it doesn't exist
mkdir -p ARCHIVE/backups

# Move all .bak files
find . -name "*.bak.*" -type f -exec mv {} ARCHIVE/backups/ \;

echo "Backup files moved to ARCHIVE/backups/"
ls -la ARCHIVE/backups/ | wc -l
echo "files archived."
