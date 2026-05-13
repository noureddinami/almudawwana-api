<?php
/**
 * API Proxy - Direct Database Access (ULTRA ROBUST)
 * URL: https://almodawana.dreamhosters.com/api-proxy.php?endpoint=codes
 * Supports pagination with &page=1&per_page=20
 *
 * Loads configuration from .env file and connects directly to MySQL
 */

// Set headers FIRST before any output
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';
$env_file = $repo_dir . '/.env';

// ============================================================================
// LOAD ENVIRONMENT VARIABLES FROM .env
// ============================================================================

function load_env_file($file) {
    $vars = [];
    if (!file_exists($file)) {
        return $vars;
    }

    $lines = file($file);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comments
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        // Skip lines with comments after the value
        if (strpos($line, '#') !== false) {
            $line = substr($line, 0, strpos($line, '#'));
            $line = trim($line);
        }
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remove quotes if present
            if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                $value = substr($value, 1, -1);
            }
            $vars[$key] = $value;
        }
    }
    return $vars;
}

$env_vars = load_env_file($env_file);

// Get database configuration
$db_host = $env_vars['DB_HOST'] ?? 'localhost';
$db_port = (int)($env_vars['DB_PORT'] ?? 3306);
$db_database = $env_vars['DB_DATABASE'] ?? '';
$db_username = $env_vars['DB_USERNAME'] ?? '';
$db_password = $env_vars['DB_PASSWORD'] ?? '';

// ============================================================================
// CONNECT TO DATABASE
// ============================================================================

try {
    $pdo = new PDO(
        "mysql:host=$db_host;port=$db_port;dbname=$db_database;charset=utf8mb4",
        $db_username,
        $db_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ]);
    exit;
}

// ============================================================================
// GET REQUEST PARAMETERS
// ============================================================================

$endpoint = $_GET['endpoint'] ?? 'codes';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = min(200, max(1, intval($_GET['per_page'] ?? 20)));
$slug = $_GET['slug'] ?? null;

// ============================================================================
// HANDLE REQUESTS
// ============================================================================

try {
    switch ($endpoint) {
        // ── CODES ────────────────────────────────────────────────────────
        case 'codes':
            if ($slug) {
                // Single code by slug
                $stmt = $pdo->prepare('SELECT * FROM codes WHERE slug = ? LIMIT 1');
                $stmt->execute([$slug]);
                $code = $stmt->fetch();

                if (!$code) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Code not found']);
                    exit;
                }

                echo json_encode($code);
            } else {
                // List codes with pagination
                $offset = ($page - 1) * $per_page;

                // Get total count
                $stmt = $pdo->query('SELECT COUNT(*) as count FROM codes');
                $total = $stmt->fetch()['count'];
                $last_page = ceil($total / $per_page);

                // Get paginated data
                $stmt = $pdo->prepare('
                    SELECT * FROM codes
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?
                ');
                $stmt->execute([$per_page, $offset]);
                $codes = $stmt->fetchAll();

                echo json_encode([
                    'data' => $codes,
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'last_page' => $last_page,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $per_page, $total),
                ]);
            }
            break;

        // ── ARTICLES ──────────────────────────────────────────────────────
        case 'articles':
            if ($slug) {
                // Single article by slug
                $stmt = $pdo->prepare('SELECT * FROM articles WHERE slug = ? LIMIT 1');
                $stmt->execute([$slug]);
                $article = $stmt->fetch();

                if (!$article) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Article not found']);
                    exit;
                }

                // Load related code
                if ($article['code_id']) {
                    $stmt = $pdo->prepare('SELECT id, slug, title_ar, title_fr FROM codes WHERE id = ? LIMIT 1');
                    $stmt->execute([$article['code_id']]);
                    $article['code'] = $stmt->fetch();
                }

                echo json_encode($article);
            } else {
                // List articles with pagination
                $offset = ($page - 1) * $per_page;

                // Get total count
                $stmt = $pdo->query('SELECT COUNT(*) as count FROM articles');
                $total = $stmt->fetch()['count'];
                $last_page = ceil($total / $per_page);

                // Get paginated data
                $stmt = $pdo->prepare('
                    SELECT * FROM articles
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?
                ');
                $stmt->execute([$per_page, $offset]);
                $articles = $stmt->fetchAll();

                // Load related codes for each article
                foreach ($articles as &$article) {
                    if ($article['code_id']) {
                        $stmt = $pdo->prepare('SELECT id, slug, title_ar, title_fr FROM codes WHERE id = ? LIMIT 1');
                        $stmt->execute([$article['code_id']]);
                        $article['code'] = $stmt->fetch();
                    }
                }

                echo json_encode([
                    'data' => $articles,
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'last_page' => $last_page,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $per_page, $total),
                ]);
            }
            break;

        // ── BOOKS ────────────────────────────────────────────────────────
        case 'books':
            $offset = ($page - 1) * $per_page;

            // Get total count
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM books');
            $total = $stmt->fetch()['count'];
            $last_page = ceil($total / $per_page);

            // Get paginated data
            $stmt = $pdo->prepare('
                SELECT * FROM books
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ');
            $stmt->execute([$per_page, $offset]);
            $books = $stmt->fetchAll();

            echo json_encode([
                'data' => $books,
                'current_page' => $page,
                'per_page' => $per_page,
                'total' => $total,
                'last_page' => $last_page,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $per_page, $total),
            ]);
            break;

        // ── DEFAULT ──────────────────────────────────────────────────────
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown endpoint: ' . $endpoint]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
