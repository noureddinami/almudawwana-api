<?php
/**
 * Simple .env checker
 * URL: https://almodawana.dreamhosters.com/check-env.php
 */

echo "<pre>";
echo "=== Checking .env File ===\n\n";

$env_file = '/home/dh_modawana/almodawana.dreamhosters.com/.env';

if (!file_exists($env_file)) {
    echo "❌ .env file NOT found at: $env_file\n";
    echo "Please upload .env file!\n";
    exit;
}

echo "✓ .env file found\n\n";

// Read and display database config
$content = file_get_contents($env_file);
$lines = explode("\n", $content);

echo "Database Configuration from .env:\n";
echo "================================\n";

foreach ($lines as $line) {
    if (strpos($line, 'DB_') === 0) {
        // Show DB_* lines but hide password
        if (strpos($line, 'DB_PASSWORD') !== false) {
            echo $line . " (masked for security)\n";
        } else {
            echo $line . "\n";
        }
    }
}

echo "\n";

// Test connection
echo "Testing Connection:\n";
echo "===================\n\n";

// Extract values
$db_host = '';
$db_port = '';
$db_database = '';
$db_username = '';
$db_password = '';

foreach ($lines as $line) {
    if (strpos($line, 'DB_HOST=') === 0) {
        $db_host = trim(str_replace('DB_HOST=', '', $line));
    } elseif (strpos($line, 'DB_PORT=') === 0) {
        $db_port = trim(str_replace('DB_PORT=', '', $line));
    } elseif (strpos($line, 'DB_DATABASE=') === 0) {
        $db_database = trim(str_replace('DB_DATABASE=', '', $line));
    } elseif (strpos($line, 'DB_USERNAME=') === 0) {
        $db_username = trim(str_replace('DB_USERNAME=', '', $line));
    } elseif (strpos($line, 'DB_PASSWORD=') === 0) {
        $db_password = trim(str_replace('DB_PASSWORD=', '', $line));
    }
}

echo "Parsed values:\n";
echo "Host: " . $db_host . "\n";
echo "Port: " . $db_port . "\n";
echo "Database: " . $db_database . "\n";
echo "Username: " . $db_username . "\n";
echo "Password: " . (strlen($db_password) > 0 ? str_repeat("*", strlen($db_password)) : "(empty)") . "\n\n";

// Test connection
try {
    echo "Attempting connection...\n";
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_database",
        $db_username,
        $db_password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Connection successful!\n";
    $pdo = null;
} catch (PDOException $e) {
    echo "❌ Connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nPossible solutions:\n";
    echo "1. Check that username and password are correct\n";
    echo "2. Verify database exists in DreamHost panel\n";
    echo "3. Verify user has access to database\n";
    echo "4. Contact DreamHost support\n";
}

echo "</pre>";
?>
