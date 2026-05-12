<?php
/**
 * Git Setup Script for DreamHost (v2 - Handles existing files)
 *
 * Run this ONCE via browser to initialize the git repository
 * URL: https://almodawana.dreamhosters.com/setup-git-v2.php
 *
 * SECURITY: Delete this file after running!
 */

// Configuration
$REPO_DIR = '/home/dh_modawana/almodawana.dreamhosters.com';
$REPO_URL = 'https://github.com/noureddinami/almudawwana-api.git';
$LOG_FILE = $REPO_DIR . '/setup.log';

echo "<pre style='background: #1e1e1e; color: #00ff00; padding: 20px; font-family: monospace; line-height: 1.5;'>";
echo "=== Git Setup v2 for Al-Mudawwana API ===\n";
echo "Repository: $REPO_DIR\n";
echo "URL: $REPO_URL\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Check if directory exists
if (!is_dir($REPO_DIR)) {
    echo "❌ ERROR: Repository directory not found: $REPO_DIR\n";
    exit;
}

echo "✓ Repository directory found\n";

// Check if git is installed
$git_check = shell_exec('which git 2>&1');
if (!$git_check) {
    echo "❌ ERROR: Git is not installed on this server\n";
    exit;
}

echo "✓ Git version: " . trim(shell_exec('git --version')) . "\n\n";

// Change to repo directory
chdir($REPO_DIR);

// Check if .git directory already exists
if (is_dir($REPO_DIR . '/.git')) {
    echo "⚠️  Git repository already exists\n";
    echo "Updating from origin...\n\n";

    echo "Running: git fetch origin\n";
    $output = [];
    exec('git fetch origin 2>&1', $output);
    foreach ($output as $line) {
        echo "  " . $line . "\n";
    }

    echo "\nRunning: git reset --hard origin/main\n";
    $output = [];
    exec('git reset --hard origin/main 2>&1', $output);
    foreach ($output as $line) {
        echo "  " . $line . "\n";
    }

    echo "\n✓ Repository updated\n\n";
} else {
    echo "Initializing git repository in existing directory...\n\n";

    // Initialize git locally
    echo "Running: git init\n";
    $output = [];
    exec('git init 2>&1', $output);
    foreach ($output as $line) {
        echo "  " . $line . "\n";
    }

    // Configure git user
    echo "\nRunning: git config user.name\n";
    exec("git config user.name 'DreamHost Deploy'");
    echo "  user.name = DreamHost Deploy\n";

    echo "\nRunning: git config user.email\n";
    exec("git config user.email 'deploy@almodawana.dreamhosters.com'");
    echo "  user.email = deploy@almodawana.dreamhosters.com\n";

    // Add remote
    echo "\nRunning: git remote add origin\n";
    exec("git remote add origin $REPO_URL 2>&1", $output);
    foreach ($output as $line) {
        if (!empty($line)) echo "  " . $line . "\n";
    }

    // Fetch from remote
    echo "\nRunning: git fetch origin\n";
    $output = [];
    exec('git fetch origin 2>&1', $output);
    foreach ($output as $line) {
        echo "  " . $line . "\n";
    }

    // Reset to origin/main
    echo "\nRunning: git reset --hard origin/main\n";
    $output = [];
    exec('git reset --hard origin/main 2>&1', $output);
    foreach ($output as $line) {
        echo "  " . $line . "\n";
    }

    // Checkout main branch
    echo "\nRunning: git checkout -b main origin/main\n";
    $output = [];
    $code = 0;
    exec('git checkout -b main origin/main 2>&1', $output, $code);
    if ($code !== 0) {
        echo "\nRunning: git checkout main (already exists)\n";
        exec('git checkout main 2>&1', $output);
    }
    foreach ($output as $line) {
        if (!empty($line)) echo "  " . $line . "\n";
    }

    echo "\n✓ Git repository initialized\n\n";
}

// Verify setup
echo "=== Verification ===\n";
$git_version = trim(shell_exec('git --version'));
$remote_url = trim(shell_exec('git config --get remote.origin.url'));
$current_branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));
$latest_commit = trim(shell_exec('git log -1 --oneline'));

echo "Git version: $git_version\n";
echo "Remote URL: $remote_url\n";
echo "Current branch: $current_branch\n";
echo "Latest commit: $latest_commit\n";

echo "\n✓ Setup complete!\n";
echo "The deploy.php script should now work correctly.\n";
echo "Test it by pushing code to GitHub.\n\n";

echo "<strong style='color: #ff6b6b;'>⚠️  SECURITY WARNING:</strong>\n";
echo "Delete this file immediately after setup:\n";
echo "  https://almodawana.dreamhosters.com/setup-git-v2.php\n";
echo "(This file is a security risk if left on the server)\n";

echo "</pre>";

// Log the setup
$message = "[" . date('Y-m-d H:i:s') . "] Git setup v2 completed via PHP - SUCCESS\n";
file_put_contents($LOG_FILE, $message, FILE_APPEND);
?>
