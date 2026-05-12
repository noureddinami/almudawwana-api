#!/bin/bash
# Git Setup Script for DreamHost
# Run this ONCE to initialize the repository
# bash setup-git.sh

REPO_DIR="/home/dh_modawana/almodawana.dreamhosters.com"
REPO_URL="https://github.com/noureddinami/almudawwana-api.git"
LOG_FILE="$REPO_DIR/setup.log"

echo "=== Git Setup for Al-Mudawwana API ===" | tee $LOG_FILE
echo "Repository: $REPO_DIR" | tee -a $LOG_FILE
echo "URL: $REPO_URL" | tee -a $LOG_FILE
echo "" | tee -a $LOG_FILE

# Check if git is installed
if ! command -v git &> /dev/null; then
    echo "ERROR: Git is not installed on this server" | tee -a $LOG_FILE
    exit 1
fi

echo "✓ Git found: $(git --version)" | tee -a $LOG_FILE

# Backup existing files if directory not empty
if [ "$(ls -A $REPO_DIR 2>/dev/null)" ]; then
    echo "⚠ Directory not empty, backing up..." | tee -a $LOG_FILE
    mv $REPO_DIR $REPO_DIR.backup.$(date +%s)
    mkdir -p $REPO_DIR
fi

# Clone repository
echo "" | tee -a $LOG_FILE
echo "Cloning repository..." | tee -a $LOG_FILE
cd $REPO_DIR
git clone $REPO_URL . 2>&1 | tee -a $LOG_FILE

if [ $? -ne 0 ]; then
    echo "ERROR: Failed to clone repository" | tee -a $LOG_FILE
    exit 1
fi

echo "✓ Repository cloned successfully" | tee -a $LOG_FILE

# Configure git user
echo "" | tee -a $LOG_FILE
echo "Configuring Git user..." | tee -a $LOG_FILE
git config user.name "DreamHost Deploy" | tee -a $LOG_FILE
git config user.email "deploy@almodawana.dreamhosters.com" | tee -a $LOG_FILE
echo "✓ Git user configured" | tee -a $LOG_FILE

# Set git to use SSH (optional, if SSH keys are set up)
# git config url."git@github.com:".insteadOf "https://github.com/" 2>&1 | tee -a $LOG_FILE

# Verify setup
echo "" | tee -a $LOG_FILE
echo "=== Verification ===" | tee -a $LOG_FILE
echo "Git version: $(git --version)" | tee -a $LOG_FILE
echo "Remote URL: $(git config --get remote.origin.url)" | tee -a $LOG_FILE
echo "Local branch: $(git branch)" | tee -a $LOG_FILE
echo "Recent commits:" | tee -a $LOG_FILE
git log --oneline -3 2>&1 | tee -a $LOG_FILE

echo "" | tee -a $LOG_FILE
echo "✓ Setup complete!" | tee -a $LOG_FILE
echo "The deploy.php script should now work correctly." | tee -a $LOG_FILE
echo "Test it by pushing code to GitHub." | tee -a $LOG_FILE
echo "" | tee -a $LOG_FILE
