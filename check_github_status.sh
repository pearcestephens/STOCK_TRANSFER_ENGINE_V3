#!/bin/bash

echo "🔍 GITHUB STATUS CHECKER"
echo "========================"
echo

# Check current directory
echo "📁 Current Directory: $(pwd)"
echo

# Check if .git exists
if [ -d ".git" ]; then
    echo "✅ Git is INITIALIZED in this directory"
    echo
    
    # Check remote connections
    echo "🔗 Remote Connections:"
    if git remote -v 2>/dev/null | grep -q "origin"; then
        echo "✅ GitHub repository connected:"
        git remote -v
    else
        echo "❌ No GitHub repository connected yet"
    fi
    echo
    
    # Check status
    echo "📊 Git Status:"
    git status --short 2>/dev/null || echo "❌ Git status unavailable"
    
else
    echo "❌ Git is NOT initialized yet"
    echo "   Run: git init"
fi

echo
echo "📋 WHAT TO DO NEXT:"
echo "==================="

if [ ! -d ".git" ]; then
    echo "1. First, initialize Git: git init"
    echo "2. Then visit: https://github.com/new"
    echo "3. Create a repository called: NewTransferV3-Enterprise"
    echo "4. Come back and tell me your GitHub username!"
elif ! git remote -v 2>/dev/null | grep -q "origin"; then
    echo "1. Go to GitHub: https://github.com"
    echo "2. Sign in to your account"
    echo "3. Check if you have any existing repositories"
    echo "4. Tell me your GitHub username and repository name!"
else
    echo "✅ You're all set up! Your code can be pushed to GitHub."
    echo "   Run: git add . && git commit -m 'Update' && git push"
fi

echo
echo "🆘 NEED HELP? Just tell me:"
echo "   - Your GitHub username (if you have one)"
echo "   - Whether you want to create a new repository" 
echo "   - If you found any existing repositories"