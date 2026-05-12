<?php
/**
 * API Proxy - Direct access to API data
 * URL: https://almodawana.dreamhosters.com/api-proxy.php?endpoint=codes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';

// Load Laravel
chdir($repo_dir);
require_once $repo_dir . '/vendor/autoload.php';

try {
    $app = require_once $repo_dir . '/bootstrap/app.php';
    $kernel = $app->make('Illuminate\Contracts\Console\Kernel');
} catch (Exception $e) {
    echo json_encode(['error' => 'Failed to load Laravel: ' . $e->getMessage()]);
    exit;
}

// Get requested endpoint
$endpoint = $_GET['endpoint'] ?? 'codes';

// Load the appropriate model
switch ($endpoint) {
    case 'codes':
        $model = 'App\Models\Code';
        break;
    case 'articles':
        $model = 'App\Models\Article';
        break;
    case 'books':
        $model = 'App\Models\Book';
        break;
    default:
        echo json_encode(['error' => 'Unknown endpoint: ' . $endpoint]);
        exit;
}

try {
    // Load model
    if (!class_exists($model)) {
        echo json_encode(['error' => 'Model not found: ' . $model]);
        exit;
    }

    // Get data
    $data = $model::limit(100)->get();

    echo json_encode([
        'success' => true,
        'endpoint' => $endpoint,
        'count' => $data->count(),
        'data' => $data,
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
