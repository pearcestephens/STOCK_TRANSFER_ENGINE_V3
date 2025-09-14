#!/bin/bash

echo "🔍 DIAGNOSING GITHUB SETUP STATUS"
echo "================================="
echo

# Navigate to project directory
cd /home/master/applications/jcepnzzkmj/public_html/assets/cron/NewTransferV3

echo "📁 Current directory: $(pwd)"
echo

# Check if .git exists
if [ -d ".git" ]; then
    echo "✅ Git repository exists"
    echo
    
    # Check Git configuration
    echo "👤 Git configuration:"
    git config --list | grep -E "(user\.name|user\.email)" || echo "❌ No Git user configured"
    echo
    
    # Check remote connections
    echo "🔗 Remote connections:"
    if git remote -v 2>/dev/null | grep -q "origin"; then
        git remote -v
    else
        echo "❌ No remote repository connected"
    fi
    echo
    
    # Check current branch
    echo "🌿 Current branch:"
    git branch 2>/dev/null || echo "❌ No branches found"
    echo
    
    # Check git status
    echo "📊 Git status:"
    git status 2>/dev/null || echo "❌ Git status unavailable"
    echo
    
    # Check commit history
    echo "📜 Commit history:"
    git log --oneline -5 2>/dev/null || echo "❌ No commits found"
    echo
    
else
    echo "❌ Git repository NOT FOUND - need to initialize"
    echo "   Run: git init"
    echo
fi

# Check if important files exist
echo "📋 Important files check:"
files=(".gitignore" "README.md" "config.example.php" "LICENSE" "config.php")
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file exists"
    else
        echo "❌ $file missing"
    fi
done
echo

# Check file permissions
echo "🔐 Script permissions:"
if [ -f "setup_git.sh" ]; then
    ls -la setup_git.sh
else
    echo "❌ setup_git.sh not found"
fi

if [ -f "upload_to_github.sh" ]; then
    ls -la upload_to_github.sh  
else
    echo "❌ upload_to_github.sh not found"
fi
echo

echo "🎯 RECOMMENDED NEXT STEPS:"
echo "=========================="

if [ ! -d ".git" ]; then
    echo "1. Initialize Git: git init"
    echo "2. Add files: git add ."
    echo "3. Make first commit: git commit -m 'Initial commit'"
    echo "4. Connect to GitHub: git remote add origin https://github.com/pearcestephens/STOCK_TRANSFER_ENGINE_V3.git"
    echo "5. Push to GitHub: git push -u origin main"
elif ! git remote -v 2>/dev/null | grep -q "origin"; then
    echo "1. Connect to GitHub: git remote add origin https://github.com/pearcestephens/STOCK_TRANSFER_ENGINE_V3.git"  
    echo "2. Push to GitHub: git push -u origin main"
elif ! git log --oneline -1 2>/dev/null | grep -q "commit"; then
    echo "1. Add files: git add ."
    echo "2. Make first commit: git commit -m 'Initial commit: NewTransferV3 AI System'"
    echo "3. Push to GitHub: git push -u origin main"
else
    echo "✅ Repository seems ready - try: git push -u origin main"
fi

echo
echo "🆘 If you see errors, copy and paste them so I can help fix them!"