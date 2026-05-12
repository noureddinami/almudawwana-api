<?php
/**
 * Seed Database Script
 * URL: https://almodawana.dreamhosters.com/seed-database.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background: #000; color: #0f0; padding: 20px; font-family: monospace;'>";
echo "=== Seeding Database ===\n\n";

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

// Step 2: Run seeders
echo "2. Running database seeders\n";
try {
    $kernel = $app->make('Illuminate\Contracts\Console\Kernel');

    $input = new Symfony\Component\Console\Input\ArrayInput([
        'command' => 'db:seed',
        '--force' => true,
    ]);

    $output = new Symfony\Component\Console\Output\BufferedOutput();
    $status = $kernel->handle($input, $output);
    $result = $output->fetch();

    echo "   Seeder output:\n";
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
echo "Your database is now populated with initial data.\n";
echo "You can now delete this file.\n";
echo "</pre>";
?>
