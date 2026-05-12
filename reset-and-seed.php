<?php
/**
 * Reset and Seed Database Script
 * ⚠️ WARNING: This will DELETE ALL DATA and restart fresh!
 * URL: https://almodawana.dreamhosters.com/reset-and-seed.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background: #000; color: #ff6b6b; padding: 20px; font-family: monospace;'>";
echo "⚠️  WARNING: This will RESET and reseed the entire database!\n";
echo "All current data will be DELETED!\n\n";
echo "</pre>";

echo "<pre style='background: #000; color: #0f0; padding: 20px; font-family: monospace;'>";
echo "=== Resetting and Seeding Database ===\n\n";

$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';

// Step 1: Load Laravel
echo "1. Loading Laravel\n";
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

// Step 2: Migrate fresh
echo "2. Running migrate:fresh (reset database)\n";
try {
    $kernel = $app->make('Illuminate\Contracts\Console\Kernel');

    $input = new Symfony\Component\Console\Input\ArrayInput([
        'command' => 'migrate:fresh',
        '--force' => true,
    ]);

    $output = new Symfony\Component\Console\Output\BufferedOutput();
    $status = $kernel->handle($input, $output);
    $result = $output->fetch();

    foreach (explode("\n", $result) as $line) {
        if (!empty($line)) {
            echo "   " . $line . "\n";
        }
    }

    if ($status !== 0) {
        echo "\n   ❌ Migration fresh failed with status: $status\n";
        exit;
    }
    echo "   ✓ Database reset complete\n\n";

} catch (Exception $e) {
    echo "   ❌ Failed: " . $e->getMessage() . "\n";
    exit;
}

// Step 3: Run seeders
echo "3. Running seeders\n";
try {
    $input = new Symfony\Component\Console\Input\ArrayInput([
        'command' => 'db:seed',
        '--force' => true,
    ]);

    $output = new Symfony\Component\Console\Output\BufferedOutput();
    $status = $kernel->handle($input, $output);
    $result = $output->fetch();

    foreach (explode("\n", $result) as $line) {
        if (!empty($line)) {
            echo "   " . $line . "\n";
        }
    }

    if ($status === 0) {
        echo "\n   ✓ Database seeding completed successfully!\n";
    } else {
        echo "\n   ⚠️  Seeding completed with status: $status\n";
    }

} catch (Exception $e) {
    echo "   ❌ Seeding failed: " . $e->getMessage() . "\n";
    exit;
}

echo "\n=== Success! ===\n";
echo "✓ Database has been reset and populated with data.\n";
echo "✓ You can now see the data in your application.\n";
echo "You can delete this file.\n";
echo "</pre>";
?>
