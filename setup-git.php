<?php
/**
 * Git Setup Script for DreamHost (PHP Version)
 *
 * Run this ONCE via browser to initialize the git repository
 * URL: https://almodawana.dreamhosters.com/setup-git.php
 *
 * SECURITY: Delete this file after running!
 */

// Configuration
$REPO_DIR = '/home/dh_modawana/almodawana.dreamhosters.com';
$REPO_URL = 'https://github.com/noureddinami/almudawwana-api.git';
$LOG_FILE = $REPO_DIR . '/setup.log';

echo "<pre style='background: #1e1e1e; color: #00ff00; padding: 20px; font-family: monospace;'>";
echo "=== Git Setup for Al-Mudawwana API ===\n";
echo "Repository: $REPO_DIR\n";
echo "URL: $REPO_URL\n\n";

// Check if directory exists and accessible
if (!is_dir($REPO_DIR)) {
    echo "ERROR: Repository directory not found: $REPO_DIR\n";
    echo "Please create the directory first.\n";
    exit;
}

echo "✓ Repository directory found\n\n";

// Check if git is installed
$git_check = shell_exec('which git 2>&1');
if (!$git_check) {
    echo "ERROR: Git is not installed on this server\n";
    exit;
}

echo "✓ Git found: " . trim(shell_exec('git --version')) . "\n\n";

// Change to repo directory
chdir($REPO_DIR);
echo "Working directory: " . getcwd() . "\n\n";

// Check if .git directory exists
if (is_dir($REPO_DIR . '/.git')) {
    echo "⚠ Git repository already initialized\n";
    echo "Running: git fetch origin && git reset --hard origin/main\n\n";

    $output = [];
    exec('git fetch origin 2>&1', $output);
    foreach ($output as $line) {
        echo "GIT: $line\n";
    }

    exec('git reset --hard origin/main 2>&1', $output);
    foreach ($output as $line) {
        echo "GIT: $line\n";
    }
} else {
    echo "Cloning repository...\n\n";

    $output = [];
    $return_code = 0;
    exec("git clone $REPO_URL . 2>&1", $output, $return_code);

    foreach ($output as $line) {
        echo "GIT: $line\n";
    }

    if ($return_code !== 0) {
        echo "\nERROR: Failed to clone repository (code: $return_code)\n";
        exit;
    }

    echo "\n✓ Repository cloned successfully\n\n";
}

// Configure git user
echo "Configuring Git user...\n";
exec("git config user.name 'DreamHost Deploy'");
exec("git config user.email 'deploy@almodawana.dreamhosters.com'");
echo "✓ Git user configured\n\n";

// Verify setup
echo "=== Verification ===\n";
echo "Git version: " . trim(shell_exec('git --version')) . "\n";
echo "Remote URL: " . trim(shell_exec('git config --get remote.origin.url')) . "\n";
echo "Current branch: " . trim(shell_exec('git rev-parse --abbrev-ref HEAD')) . "\n";
echo "Recent commits:\n";
echo trim(shell_exec('git log --oneline -3')) . "\n";

echo "\n✓ Setup complete!\n";
echo "The deploy.php script should now work correctly.\n";
echo "Test it by pushing code to GitHub.\n\n";

echo "<strong style='color: #ff6b6b;'>IMPORTANT: Delete this file after setup:</strong>\n";
echo "Delete: https://almodawana.dreamhosters.com/setup-git.php\n";
echo "(This file is a security risk if left on the server)\n";

echo "</pre>";

// Log the setup
file_put_contents($LOG_FILE, "[" . date('Y-m-d H:i:s') . "] Git setup completed via PHP\n", FILE_APPEND);
?>
