<?php
/**
 * API Proxy - Extended with Search & Articles Support
 * URL: https://almodawana.dreamhosters.com/api-proxy.php
 *
 * Supports:
 * - /codes (list)
 * - /codes/{slug} (single)
 * - /codes/{slug}/articles (articles in a code)
 * - /articles (list)
 * - /articles/{slug} (single)
 * - /search (search articles)
 */

// Set headers FIRST
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 3600');

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================================
// CONFIGURATION
// ============================================================================

$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';
$env_file = $repo_dir . '/.env';

function load_env_file($file) {
    $vars = [];
    if (!file_exists($file)) return $vars;

    foreach (file($file) as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === '#') continue;

        if (strpos($line, '#') !== false) {
            $line = substr($line, 0, strpos($line, '#'));
            $line = trim($line);
        }

        if (strpos($line, '=') === false) continue;

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes
        if (($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }

        $vars[$key] = $value;
    }
    return $vars;
}

$env_vars = load_env_file($env_file);
$db_host = $env_vars['DB_HOST'] ?? 'localhost';
$db_port = (int)($env_vars['DB_PORT'] ?? 3306);
$db_database = $env_vars['DB_DATABASE'] ?? '';
$db_username = $env_vars['DB_USERNAME'] ?? '';
$db_password = $env_vars['DB_PASSWORD'] ?? '';

// ============================================================================
// DATABASE CONNECTION
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
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// ============================================================================
// PARSE REQUEST
// ============================================================================

// Get the actual path (handle both ?endpoint= and /api/v1/ style URLs)
$path = $_SERVER['REQUEST_URI'];
$path = parse_url($path, PHP_URL_PATH);

// Extract endpoint and slug from path
// Support both: ?endpoint=codes&slug=... and path-based routing
$endpoint = $_GET['endpoint'] ?? null;
$slug = $_GET['slug'] ?? null;

// Parse path-based URLs: /codes, /codes/{slug}, /codes/{slug}/articles, etc.
if (!$endpoint) {
    $parts = array_filter(explode('/', trim($path, '/')));

    if (count($parts) >= 2 && $parts[count($parts)-1] === 'api-proxy.php') {
        array_pop($parts);
    }

    // Handle /api/v1/codes, /codes, etc.
    $first = end($parts);

    if ($first === 'codes' || $first === 'articles' || $first === 'books' || $first === 'search') {
        $endpoint = $first;
    }
}

// Get pagination parameters
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = min(200, max(1, intval($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $per_page;

// ============================================================================
// ROUTE HANDLING
// ============================================================================

try {
    switch ($endpoint) {
        // ────────────────────────────────────────────────────────────────
        // CODES ENDPOINT
        // ────────────────────────────────────────────────────────────────
        case 'codes':
            if ($slug) {
                // GET /codes/{slug}
                $stmt = $pdo->prepare('SELECT * FROM codes WHERE slug = ? LIMIT 1');
                $stmt->execute([$slug]);
                $code = $stmt->fetch();

                if (!$code) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Code not found']);
                    exit;
                }

                echo json_encode($code);
            } elseif (isset($_GET['slug'])) {
                // GET /codes/{slug}/articles
                $code_slug = $_GET['slug'];

                // Get code
                $stmt = $pdo->prepare('SELECT id, slug, title_ar, title_fr FROM codes WHERE slug = ? LIMIT 1');
                $stmt->execute([$code_slug]);
                $code = $stmt->fetch();

                if (!$code) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Code not found']);
                    exit;
                }

                // Get articles for this code
                $sql = sprintf(
                    'SELECT * FROM articles WHERE code_id = ? ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $per_page, $offset
                );
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$code['id']]);
                $articles = $stmt->fetchAll();

                // Count total
                $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM articles WHERE code_id = ?');
                $stmt->execute([$code['id']]);
                $total = (int)$stmt->fetch()['count'];
                $last_page = (int)ceil($total / $per_page);

                echo json_encode([
                    'data' => $articles,
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'last_page' => $last_page,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $per_page, $total),
                ]);
            } else {
                // GET /codes (list)
                $sql = sprintf(
                    'SELECT * FROM codes ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $per_page, $offset
                );
                $stmt = $pdo->query($sql);
                $codes = $stmt->fetchAll();

                $stmt = $pdo->query('SELECT COUNT(*) as count FROM codes');
                $total = (int)$stmt->fetch()['count'];
                $last_page = (int)ceil($total / $per_page);

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

        // ────────────────────────────────────────────────────────────────
        // ARTICLES ENDPOINT
        // ────────────────────────────────────────────────────────────────
        case 'articles':
            if ($slug) {
                // GET /articles/{slug}
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

                // Load related section
                if ($article['section_id']) {
                    $stmt = $pdo->prepare('SELECT id, number, title_ar FROM sections WHERE id = ? LIMIT 1');
                    $stmt->execute([$article['section_id']]);
                    $article['section'] = $stmt->fetch();
                }

                echo json_encode($article);
            } else {
                // GET /articles (list)
                $sql = sprintf(
                    'SELECT * FROM articles ORDER BY created_at DESC LIMIT %d OFFSET %d',
                    $per_page, $offset
                );
                $stmt = $pdo->query($sql);
                $articles = $stmt->fetchAll();

                // Load codes for each article
                foreach ($articles as &$article) {
                    if ($article['code_id']) {
                        $stmt = $pdo->prepare('SELECT id, slug, title_ar, title_fr FROM codes WHERE id = ? LIMIT 1');
                        $stmt->execute([$article['code_id']]);
                        $article['code'] = $stmt->fetch();
                    }
                }

                $stmt = $pdo->query('SELECT COUNT(*) as count FROM articles');
                $total = (int)$stmt->fetch()['count'];
                $last_page = (int)ceil($total / $per_page);

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

        // ────────────────────────────────────────────────────────────────
        // SEARCH ENDPOINT
        // ────────────────────────────────────────────────────────────────
        case 'search':
            $query = trim($_GET['q'] ?? $_GET['kw'] ?? '');

            if (empty($query)) {
                echo json_encode([
                    'query' => '',
                    'results' => [
                        'data' => [],
                        'current_page' => 1,
                        'per_page' => $per_page,
                        'total' => 0,
                        'last_page' => 0,
                    ]
                ]);
                exit;
            }

            // Search in articles (content or title)
            $search_term = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

            $sql = sprintf(
                'SELECT * FROM articles
                 WHERE content_ar LIKE ? OR content_fr LIKE ? OR number LIKE ?
                 ORDER BY created_at DESC
                 LIMIT %d OFFSET %d',
                $per_page, $offset
            );

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$search_term, $search_term, $search_term]);
            $articles = $stmt->fetchAll();

            // Load codes
            foreach ($articles as &$article) {
                if ($article['code_id']) {
                    $s = $pdo->prepare('SELECT id, slug, title_ar, title_fr FROM codes WHERE id = ? LIMIT 1');
                    $s->execute([$article['code_id']]);
                    $article['code'] = $s->fetch();
                }
            }

            // Count results
            $count_sql = 'SELECT COUNT(*) as count FROM articles
                         WHERE content_ar LIKE ? OR content_fr LIKE ? OR number LIKE ?';
            $stmt = $pdo->prepare($count_sql);
            $stmt->execute([$search_term, $search_term, $search_term]);
            $total = (int)$stmt->fetch()['count'];
            $last_page = (int)ceil($total / $per_page);

            echo json_encode([
                'query' => $query,
                'results' => [
                    'data' => $articles,
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'last_page' => $last_page,
                    'from' => $total > 0 ? $offset + 1 : 0,
                    'to' => min($offset + $per_page, $total),
                ]
            ]);
            break;

        // ────────────────────────────────────────────────────────────────
        // BOOKS ENDPOINT
        // ────────────────────────────────────────────────────────────────
        case 'books':
            $sql = sprintf(
                'SELECT * FROM books ORDER BY created_at DESC LIMIT %d OFFSET %d',
                $per_page, $offset
            );
            $stmt = $pdo->query($sql);
            $books = $stmt->fetchAll();

            $stmt = $pdo->query('SELECT COUNT(*) as count FROM books');
            $total = (int)$stmt->fetch()['count'];
            $last_page = (int)ceil($total / $per_page);

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

        // ────────────────────────────────────────────────────────────────
        // DEFAULT / NOT FOUND
        // ────────────────────────────────────────────────────────────────
        default:
            http_response_code(400);
            echo json_encode([
                'error' => 'Unknown endpoint',
                'endpoint' => $endpoint,
                'available' => ['codes', 'articles', 'books', 'search']
            ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
