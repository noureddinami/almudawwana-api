<?php
/**
 * GitHub Webhook Deployment Script for Al-Mudawwana API
 *
 * This script receives GitHub webhooks and automatically pulls the latest code
 * Place this file in the root of your API deployment directory on DreamHost
 * Make it accessible via: https://almodawana.dreamhosters.com/deploy.php
 */

// Configuration
$repo_dir = __DIR__;
$webhook_secret = 'almudawwana-webhook-secret-2026';  // Change this!
$log_file = $repo_dir . '/deploy.log';

// Function to log messages
function write_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Start logging
write_log("=== Webhook Received ===");

// Verify GitHub signature
$payload = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_HUB_SIGNATURE_256']) ? $_SERVER['HTTP_X_HUB_SIGNATURE_256'] : '';

if (empty($signature)) {
    write_log("ERROR: No signature provided");
    http_response_code(403);
    echo json_encode(['error' => 'No signature']);
    exit;
}

// Verify HMAC
$expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
if (!hash_equals($expected_signature, $signature)) {
    write_log("ERROR: Invalid signature. Expected: $expected_signature, Got: $signature");
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

write_log("✓ Signature verified");

// Parse JSON payload
$data = json_decode($payload, true);
if (!$data) {
    write_log("ERROR: Could not parse JSON payload");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Check if this is a push event
if (!isset($data['ref'])) {
    write_log("WARNING: Not a push event, ignoring");
    echo json_encode(['message' => 'Not a push event']);
    exit;
}

$branch = str_replace('refs/heads/', '', $data['ref']);
write_log("Push detected on branch: $branch");

// Only deploy if pushing to main branch
if ($branch !== 'main') {
    write_log("INFO: Not main branch, skipping deployment");
    echo json_encode(['message' => 'Not main branch']);
    exit;
}

write_log("Deploying main branch...");

// Change to repository directory
if (!is_dir($repo_dir)) {
    write_log("ERROR: Repository directory not found: $repo_dir");
    http_response_code(500);
    echo json_encode(['error' => 'Repo dir not found']);
    exit;
}

chdir($repo_dir);
write_log("Working directory: " . getcwd());

// Pull latest code
write_log("Executing: git fetch origin && git reset --hard origin/main");
$output = [];
$return_code = 0;
exec('cd ' . escapeshellarg($repo_dir) . ' && git fetch origin && git reset --hard origin/main 2>&1', $output, $return_code);

foreach ($output as $line) {
    write_log("GIT: $line");
}

if ($return_code !== 0) {
    write_log("ERROR: Git pull failed with return code $return_code");
    http_response_code(500);
    echo json_encode(['error' => 'Git pull failed', 'code' => $return_code]);
    exit;
}

write_log("✓ Git pull successful");

// Optional: Run Laravel post-deployment commands
write_log("Executing Laravel post-deploy commands...");

// Composer install (if needed)
if (file_exists($repo_dir . '/composer.json')) {
    write_log("Executing: composer install --no-dev");
    exec('cd ' . escapeshellarg($repo_dir) . ' && composer install --no-dev 2>&1', $composer_output, $composer_code);
    foreach ($composer_output as $line) {
        write_log("COMPOSER: $line");
    }
}

// Database migrations (if needed)
if (file_exists($repo_dir . '/artisan')) {
    write_log("Executing: php artisan migrate --force");
    exec('cd ' . escapeshellarg($repo_dir) . ' && php artisan migrate --force 2>&1', $migrate_output, $migrate_code);
    foreach ($migrate_output as $line) {
        write_log("MIGRATE: $line");
    }
}

// Clear cache
if (file_exists($repo_dir . '/artisan')) {
    write_log("Executing: php artisan cache:clear");
    exec('cd ' . escapeshellarg($repo_dir) . ' && php artisan cache:clear 2>&1', $cache_output, $cache_code);
    foreach ($cache_output as $line) {
        write_log("CACHE: $line");
    }
}

write_log("✓ Deployment complete!");
write_log("=== End ===\n");

// Return success response
http_response_code(200);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Deployment completed',
    'branch' => $branch,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>
