<?php
/**
 * Direct Migration Script (without using Laravel env())
 * URL: https://almodawana.dreamhosters.com/migrate-direct.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background: #000; color: #0f0; padding: 20px; font-family: monospace;'>";
echo "=== Direct Laravel Migration ===\n\n";

$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';
$env_file = $repo_dir . '/.env';

// Step 1: Read .env directly
echo "1. Reading .env file directly\n";
if (!file_exists($env_file)) {
    echo "   ❌ .env not found\n";
    exit;
}

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

echo "   ✓ Read " . count($env_vars) . " variables from .env\n\n";

// Step 2: Test database connection directly
echo "2. Testing database connection\n";
$db_host = $env_vars['DB_HOST'] ?? '';
$db_port = $env_vars['DB_PORT'] ?? '3306';
$db_database = $env_vars['DB_DATABASE'] ?? '';
$db_username = $env_vars['DB_USERNAME'] ?? '';
$db_password = $env_vars['DB_PASSWORD'] ?? '';

echo "   Host: $db_host\n";
echo "   Database: $db_database\n";
echo "   Username: $db_username\n";

try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_database",
        $db_username,
        $db_password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "   ✓ Database connection successful\n\n";
} catch (PDOException $e) {
    echo "   ❌ Connection failed: " . $e->getMessage() . "\n";
    exit;
}

// Step 3: Load Laravel
echo "3. Loading Laravel\n";
chdir($repo_dir);

if (!file_exists($repo_dir . '/vendor/autoload.php')) {
    echo "   ❌ vendor/autoload.php not found\n";
    exit;
}

require_once $repo_dir . '/vendor/autoload.php';
echo "   ✓ Autoloader loaded\n";

try {
    $app = require_once $repo_dir . '/bootstrap/app.php';
    echo "   ✓ Laravel app loaded\n\n";
} catch (Exception $e) {
    echo "   ❌ Failed to load app: " . $e->getMessage() . "\n";
    exit;
}

// Step 4: Run migrations
echo "4. Running migrations\n";
try {
    $kernel = $app->make('Illuminate\Contracts\Console\Kernel');

    $input = new Symfony\Component\Console\Input\ArrayInput([
        'command' => 'migrate',
        '--force' => true,
    ]);

    $output = new Symfony\Component\Console\Output\BufferedOutput();
    $status = $kernel->handle($input, $output);
    $result = $output->fetch();

    echo "   Migration output:\n";
    foreach (explode("\n", $result) as $line) {
        if (!empty($line)) {
            echo "   " . $line . "\n";
        }
    }

    if ($status === 0) {
        echo "\n   ✓ Migrations completed successfully!\n";
    }

} catch (Exception $e) {
    echo "   ❌ Migration failed: " . $e->getMessage() . "\n";
    exit;
}

echo "\n=== Success! ===\n";
echo "You can now delete this file and all test scripts.\n";
echo "</pre>";
?>
