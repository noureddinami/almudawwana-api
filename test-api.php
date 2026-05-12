<?php
/**
 * Test API Endpoints
 * URL: https://almodawana.dreamhosters.com/test-api.php
 */

echo "<pre style='background: #000; color: #0f0; padding: 20px; font-family: monospace;'>";
echo "=== Testing API Endpoints ===\n\n";

$api_base = 'https://almodawana.dreamhosters.com/api/v1';

// Test 1: Get all codes
echo "1. Testing GET /api/v1/codes\n";
echo "   URL: $api_base/codes\n";

$response = @file_get_contents("$api_base/codes");

if ($response === false) {
    echo "   ❌ Failed to fetch\n";
} else {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "   ❌ Invalid JSON response\n";
        echo "   Response: " . substr($response, 0, 200) . "...\n";
    } else {
        if (isset($data['data'])) {
            $count = count($data['data']);
            echo "   ✓ Success!\n";
            echo "   Total codes: $count\n";

            if ($count > 0) {
                echo "\n   First code:\n";
                $first = $data['data'][0];
                echo "   - Title: " . ($first['title_fr'] ?? $first['title_ar'] ?? 'N/A') . "\n";
                echo "   - Slug: " . ($first['slug'] ?? 'N/A') . "\n";
                echo "   - Status: " . ($first['status'] ?? 'N/A') . "\n";
            } else {
                echo "   ⚠️  No codes found!\n";
            }
        } else {
            echo "   ⚠️  Unexpected response format\n";
            echo "   Keys: " . implode(', ', array_keys($data)) . "\n";
        }
    }
}

echo "\n";

// Test 2: Get all articles
echo "2. Testing GET /api/v1/articles\n";
echo "   URL: $api_base/articles\n";

$response = @file_get_contents("$api_base/articles");

if ($response === false) {
    echo "   ❌ Failed to fetch\n";
} else {
    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "   ❌ Invalid JSON response\n";
    } else {
        if (isset($data['data'])) {
            $count = count($data['data']);
            echo "   ✓ Success!\n";
            echo "   Total articles: $count\n";
        } else {
            echo "   ⚠️  Unexpected response format\n";
        }
    }
}

echo "\n";

// Test 3: Check database directly
echo "3. Checking Database Directly\n";

$env_file = '/home/dh_modawana/almodawana.dreamhosters.com/.env';
$env_vars = [];
$lines = file($env_file);
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || $line[0] === '#') continue;
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $env_vars[trim($key)] = trim($value);
    }
}

$db_host = $env_vars['DB_HOST'] ?? '';
$db_port = $env_vars['DB_PORT'] ?? '3306';
$db_database = $env_vars['DB_DATABASE'] ?? '';
$db_username = $env_vars['DB_USERNAME'] ?? '';
$db_password = $env_vars['DB_PASSWORD'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_database",
        $db_username,
        $db_password
    );

    // Count codes
    $result = $pdo->query("SELECT COUNT(*) as count FROM codes");
    $codes_count = $result->fetch(PDO::FETCH_ASSOC)['count'];

    // Count articles
    $result = $pdo->query("SELECT COUNT(*) as count FROM articles");
    $articles_count = $result->fetch(PDO::FETCH_ASSOC)['count'];

    echo "   ✓ Database connected\n";
    echo "   Codes in DB: $codes_count\n";
    echo "   Articles in DB: $articles_count\n";

    $pdo = null;
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "</pre>";
?>
