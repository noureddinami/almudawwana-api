<?php
/**
 * Detailed API Diagnostics
 * URL: https://almodawana.dreamhosters.com/test-api-detailed.php
 */

echo "<pre style='background: #000; color: #0f0; padding: 20px; font-family: monospace;'>";
echo "=== Detailed API Diagnostics ===\n\n";

$api_url = 'https://almodawana.dreamhosters.com/api/v1/codes';

echo "Testing: $api_url\n\n";

// Create context with error reporting
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

// Test 1: Simple request
echo "1. Testing with file_get_contents\n";
$response = @file_get_contents($api_url, false, $context);
$headers = $http_response_header ?? [];

echo "   Headers:\n";
foreach ($headers as $header) {
    echo "   - $header\n";
}

if ($response === false) {
    echo "\n   ❌ Failed to get response\n";
} else {
    echo "\n   ✓ Got response\n";
    echo "   Length: " . strlen($response) . " bytes\n";
    echo "   First 200 chars:\n";
    echo "   " . substr($response, 0, 200) . "\n";
}

echo "\n";

// Test 2: Using curl if available
echo "2. Testing with curl\n";
if (function_exists('curl_init')) {
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "   HTTP Status: $http_code\n";

    if (!empty($error)) {
        echo "   Error: $error\n";
    } else {
        echo "   ✓ Request successful\n";
        echo "   Response length: " . strlen($response) . " bytes\n";

        if ($response) {
            echo "   First 200 chars:\n";
            echo "   " . substr($response, 0, 200) . "\n";
        }
    }
} else {
    echo "   ⚠️  curl not available\n";
}

echo "\n";

// Test 3: Check if routes exist
echo "3. Checking Laravel Routes\n";

$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';
chdir($repo_dir);

if (file_exists($repo_dir . '/routes/api.php')) {
    echo "   ✓ routes/api.php exists\n";

    $content = file_get_contents($repo_dir . '/routes/api.php');
    if (strpos($content, 'codes') !== false) {
        echo "   ✓ 'codes' route found\n";
    } else {
        echo "   ❌ 'codes' route NOT found\n";
    }
} else {
    echo "   ❌ routes/api.php NOT found\n";
}

echo "\n";

// Test 4: Check Laravel logs
echo "4. Checking Laravel Logs\n";
$log_file = $repo_dir . '/storage/logs/laravel.log';

if (file_exists($log_file)) {
    echo "   ✓ laravel.log exists\n";

    // Get last 10 lines
    $lines = array_slice(explode("\n", file_get_contents($log_file)), -10);
    echo "   Recent errors:\n";

    foreach ($lines as $line) {
        if (!empty($line) && (strpos($line, 'ERROR') !== false || strpos($line, 'error') !== false)) {
            echo "   - " . substr($line, 0, 150) . "\n";
        }
    }
} else {
    echo "   ⚠️  laravel.log not found\n";
}

echo "\n=== End of Diagnostics ===\n";
echo "</pre>";
?>
