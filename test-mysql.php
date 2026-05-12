<?php
/**
 * MySQL Diagnostic Script for DreamHost
 * Finds the correct MySQL hostname and credentials
 * URL: https://almodawana.dreamhosters.com/test-mysql.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background: #000; color: #0f0; padding: 20px; font-family: monospace;'>";
echo "=== MySQL Diagnostic Tool ===\n\n";

// Common DreamHost MySQL hostnames to test
$hostnames_to_test = [
    'mysql.almodawana.dreamhosters.com',
    'db.almodawana.dreamhosters.com',
    'localhost',
    '127.0.0.1',
    'mysql.dreamhost.com',
    'pdx1.mysql.dreamhost.com',
    'pdx1-mysql.dreamhost.com',
];

// DreamHost credentials (from .env)
$username = 'db_modawana';
$password = 'pdx1-shared-a4-08';
$database = 'almudawwana_pro';

echo "Testing credentials:\n";
echo "Username: $username\n";
echo "Password: " . substr($password, 0, 3) . "***\n";
echo "Database: $database\n\n";

echo "Testing MySQL hostnames:\n";
echo "=".str_repeat("=", 70)."\n\n";

$found = false;

foreach ($hostnames_to_test as $host) {
    echo "Testing: $host ... ";

    try {
        $pdo = new PDO(
            "mysql:host=$host;port=3306;dbname=$database",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );

        echo "✅ SUCCESS!\n";
        echo "   Connection established!\n";
        echo "   Use this hostname in .env:\n";
        echo "   DB_HOST=$host\n\n";

        // Test query
        $result = $pdo->query("SELECT VERSION()")->fetch();
        echo "   MySQL Version: " . $result[0] . "\n";

        $found = true;
        $pdo = null;

    } catch (PDOException $e) {
        $error_code = $e->getCode();
        echo "❌ Failed\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

if (!$found) {
    echo "\n⚠️  Could not connect with tested hostnames.\n\n";
    echo "SOLUTIONS:\n";
    echo "1. Check DreamHost Panel → Databases → MySQL\n";
    echo "   Look for 'Hostname' field (might be different)\n\n";
    echo "2. Try these alternative hostnames:\n";
    echo "   - mysql.dreamhost.com\n";
    echo "   - localhost (if PHP on same server)\n";
    echo "   - The exact hostname from DreamHost panel\n\n";
    echo "3. Verify credentials:\n";
    echo "   - Username: Check DreamHost panel\n";
    echo "   - Password: Might not be 'pdx1-shared-a4-08'\n";
    echo "   - Database: Check exact name in DreamHost panel\n\n";
    echo "4. Contact DreamHost support for correct hostname\n";
}

echo "\n=== End of Test ===\n";
echo "</pre>";
?>
