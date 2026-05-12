<?php
/**
 * Check Database Data Script
 * URL: https://almodawana.dreamhosters.com/check-data.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background: #000; color: #0f0; padding: 20px; font-family: monospace;'>";
echo "=== Checking Database Data ===\n\n";

$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';
$env_file = $repo_dir . '/.env';

// Read .env
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

// Connect
echo "1. Connecting to database\n";
try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_database",
        $db_username,
        $db_password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "   ✓ Connected\n\n";
} catch (PDOException $e) {
    echo "   ❌ Connection failed: " . $e->getMessage() . "\n";
    exit;
}

// Check tables and data
echo "2. Checking tables and data count\n";
echo "====================================\n\n";

$tables = [
    'codes' => 'Legal Codes',
    'books' => 'Books',
    'sections' => 'Sections',
    'articles' => 'Articles',
    'commentaries' => 'Commentaries',
    'tags' => 'Tags',
    'users' => 'Users',
];

$total_records = 0;

foreach ($tables as $table => $label) {
    try {
        $result = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
        $total_records += $count;

        if ($count > 0) {
            echo "✓ $label ($table):         $count records\n";
        } else {
            echo "⚠ $label ($table):         EMPTY!\n";
        }
    } catch (Exception $e) {
        echo "❌ $label ($table):         Error - " . $e->getMessage() . "\n";
    }
}

echo "\n====================================\n";
echo "Total records: $total_records\n\n";

if ($total_records == 0) {
    echo "⚠️  NO DATA FOUND!\n";
    echo "Solutions:\n";
    echo "1. Run seed-database.php again\n";
    echo "2. Check if CodesSeeder has data\n";
    echo "3. Run seeders manually via CLI\n";
} else {
    echo "✓ Data exists in database!\n";
    echo "If not showing in app, check:\n";
    echo "1. API endpoints (test /api/v1/codes)\n";
    echo "2. Browser console for errors\n";
    echo "3. CORS configuration\n";
}

$pdo = null;
echo "</pre>";
?>
