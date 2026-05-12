<?php
/**
 * Laravel Migration Script - Improved Version
 * URL: https://almodawana.dreamhosters.com/migrate-debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background: #000; color: #0f0; padding: 20px; font-family: monospace;'>";
echo "=== Laravel Migration Debug ===\n\n";

$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';

// Test 1: Check directory
echo "1. Checking repository directory\n";
if (!is_dir($repo_dir)) {
    echo "   ❌ Directory NOT found: $repo_dir\n";
    exit;
}
echo "   ✓ Directory found: $repo_dir\n\n";

// Test 2: Check .env file
echo "2. Checking .env file\n";
$env_file = $repo_dir . '/.env';
if (!file_exists($env_file)) {
    echo "   ❌ .env NOT found at: $env_file\n";
    echo "   Please upload .env file to DreamHost\n";
    exit;
}
echo "   ✓ .env file found\n";

// Test 3: Load .env
echo "3. Loading .env configuration\n";
try {
    $env_contents = file_get_contents($env_file);
    $lines = explode("\n", $env_contents);
    $db_found = false;
    foreach ($lines as $line) {
        if (strpos($line, 'DB_DATABASE') !== false) {
            echo "   ✓ Database config found: " . trim($line) . "\n";
            $db_found = true;
        }
    }
    if (!$db_found) {
        echo "   ⚠ No database config found in .env\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error reading .env: " . $e->getMessage() . "\n";
    exit;
}

// Test 4: Check vendor/autoload.php
echo "\n4. Checking Composer dependencies\n";
$autoload = $repo_dir . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "   ❌ vendor/autoload.php NOT found\n";
    echo "   Composer dependencies not installed!\n\n";
    echo "   SOLUTION:\n";
    echo "   1. Install locally on your computer:\n";
    echo "      composer install --no-dev\n";
    echo "   2. Upload the entire 'vendor' folder to DreamHost\n";
    echo "   3. Re-run this script\n";
    exit;
}
echo "   ✓ vendor/autoload.php found\n\n";

// Test 5: Load Laravel
echo "5. Loading Laravel application\n";
try {
    chdir($repo_dir);
    require_once $autoload;
    echo "   ✓ Autoloader loaded\n";

    $app = require_once $repo_dir . '/bootstrap/app.php';
    echo "   ✓ Laravel app loaded\n";

    // Test 6: Database connection
    echo "\n6. Testing database connection\n";
    try {
        $pdo = new PDO(
            'mysql:host=' . env('DB_HOST') . ';port=' . env('DB_PORT') . ';dbname=' . env('DB_DATABASE'),
            env('DB_USERNAME'),
            env('DB_PASSWORD')
        );
        echo "   ✓ Database connection successful\n";
        $pdo = null;
    } catch (PDOException $e) {
        echo "   ❌ Database connection failed:\n";
        echo "   Error: " . $e->getMessage() . "\n\n";
        echo "   Check your .env database settings:\n";
        echo "   DB_HOST: " . env('DB_HOST') . "\n";
        echo "   DB_DATABASE: " . env('DB_DATABASE') . "\n";
        echo "   DB_USERNAME: " . env('DB_USERNAME') . "\n";
        exit;
    }

    // Test 7: Run migrations
    echo "\n7. Running migrations\n";
    $kernel = $app->make('Illuminate\Contracts\Console\Kernel');

    $input = new Symfony\Component\Console\Input\ArrayInput([
        'command' => 'migrate',
        '--force' => true,
    ]);

    $output = new Symfony\Component\Console\Output\BufferedOutput();

    try {
        $status = $kernel->handle($input, $output);
        $result = $output->fetch();

        echo "   Output:\n";
        foreach (explode("\n", $result) as $line) {
            if (!empty($line)) {
                echo "   " . $line . "\n";
            }
        }

        if ($status === 0) {
            echo "\n   ✓ Migrations completed successfully!\n";
        } else {
            echo "\n   ⚠ Migrations completed with status: $status\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Migration failed:\n";
        echo "   Error: " . $e->getMessage() . "\n";
        echo "   File: " . $e->getFile() . "\n";
        echo "   Line: " . $e->getLine() . "\n";
        exit;
    }

} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
    exit;
}

echo "\n=== All checks passed! ===\n";
echo "You can now delete this file.\n";
echo "</pre>";
?>
