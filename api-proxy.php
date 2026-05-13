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

// Handle OPTIONS (preflight requests)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session management for authentication
session_start();

// ============================================================================
// CONFIGURATION
// ============================================================================

// Determine if running locally or on DreamHost
$repo_dir = '/home/dh_modawana/almodawana.dreamhosters.com';
$local_env_file = __DIR__ . '/.env';
$env_file = $repo_dir . '/.env';

// For local development, use .env in the same directory as this script
if (file_exists($local_env_file)) {
    $env_file = $local_env_file;
}

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

        // Remove quotes if value is not empty
        if (!empty($value) && strlen($value) > 1) {
            if (($value[0] === '"' && substr($value, -1) === '"') ||
                ($value[0] === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
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

// Extract endpoint and slug from query parameters first
$endpoint = $_GET['endpoint'] ?? null;
$slug = $_GET['slug'] ?? null;
$sub_resource = $_GET['sub'] ?? null;  // For ?sub=articles

// Parse path-based URLs: /codes, /codes/{slug}, /codes/{slug}/articles, etc.
if (!$endpoint) {
    $parts = array_filter(explode('/', trim($path, '/')));

    // Remove 'api-proxy.php' from parts if present
    if (count($parts) > 0 && $parts[count($parts)-1] === 'api-proxy.php') {
        array_pop($parts);
    }

    // Also skip /api/v1/ prefix if present
    if (count($parts) > 0 && $parts[0] === 'api') {
        array_shift($parts);
    }
    if (count($parts) > 0 && $parts[0] === 'v1') {
        array_shift($parts);
    }

    // Now parse the remaining path
    // /codes -> endpoint=codes
    // /codes/code-de-famille -> endpoint=codes, slug=code-de-famille
    // /codes/code-de-famille/articles -> endpoint=codes, slug=code-de-famille, sub_resource=articles
    // /articles/article-1 -> endpoint=articles, slug=article-1
    // /search -> endpoint=search

    if (count($parts) > 0) {
        $first = $parts[0];

        if ($first === 'codes' || $first === 'articles' || $first === 'books' || $first === 'search' || $first === 'auth' || $first === 'me' || $first === 'admin') {
            $endpoint = $first;

            // Extract slug if present
            if (count($parts) > 1) {
                $slug = $parts[1];

                // Extract sub-resource if present (e.g., 'articles', 'pdfs')
                if (count($parts) > 2) {
                    $sub_resource = $parts[2];
                }
            }
        }
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
            if ($slug && $sub_resource === 'articles') {
                // GET /codes/{slug}/articles - Return articles for a specific code
                $stmt = $pdo->prepare('SELECT id, slug, title_ar, title_fr FROM codes WHERE slug = ? LIMIT 1');
                $stmt->execute([$slug]);
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
            } elseif ($slug && !$sub_resource) {
                // GET /codes/{slug} - Return single code details
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
                // GET /codes - Return list of all codes
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
        // AUTHENTICATION ENDPOINT
        // ────────────────────────────────────────────────────────────────
        case 'auth':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_GET['action'] ?? 'login';

                if ($action === 'login') {
                    // Get email and password from POST
                    $input = file_get_contents('php://input');
                    $data = json_decode($input, true);

                    $email = trim($data['email'] ?? '');
                    $password = trim($data['password'] ?? '');

                    if (empty($email) || empty($password)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Email and password are required']);
                        exit;
                    }

                    // Find user by email
                    $stmt = $pdo->prepare('SELECT id, full_name, email, password, role, status FROM users WHERE email = ? LIMIT 1');
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if (!$user) {
                        http_response_code(401);
                        echo json_encode(['error' => 'Invalid email or password']);
                        exit;
                    }

                    // Verify password
                    if (!password_verify($password, $user['password'])) {
                        http_response_code(401);
                        echo json_encode(['error' => 'Invalid email or password']);
                        exit;
                    }

                    // Check if user is active
                    if ($user['status'] !== 'active') {
                        http_response_code(403);
                        echo json_encode(['error' => 'User account is not active']);
                        exit;
                    }

                    // Check if user is admin
                    if ($user['role'] !== 'admin') {
                        http_response_code(403);
                        echo json_encode(['error' => 'Only admins can login']);
                        exit;
                    }

                    // Generate token with user info embedded (base64 encoded)
                    $token_data = [
                        'user_id' => $user['id'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'created_at' => time(),
                        'nonce' => bin2hex(random_bytes(16))
                    ];
                    $token = base64_encode(json_encode($token_data));

                    // Also store in session for backward compatibility
                    $_SESSION['token'] = $token;
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];

                    // Return response
                    echo json_encode([
                        'token' => $token,
                        'user' => [
                            'id' => $user['id'],
                            'full_name' => $user['full_name'],
                            'email' => $user['email'],
                            'role' => $user['role']
                        ]
                    ]);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Unknown auth action']);
                }
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // ────────────────────────────────────────────────────────────────
        // GET CURRENT USER ENDPOINT
        // ────────────────────────────────────────────────────────────────
        case 'me':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                // Check if user is logged in (via token)
                $token = null;
                $token_data = null;
                $debug_info = [];

                // Try to get token from Authorization header (case-insensitive)
                $headers = getallheaders();
                $debug_info['all_headers'] = array_keys($headers);

                foreach ($headers as $name => $value) {
                    if (strtolower($name) === 'authorization') {
                        $matches = [];
                        if (preg_match('/Bearer\s+(\S+)/i', $value, $matches)) {
                            $token = $matches[1];
                            $debug_info['token_source'] = 'authorization_header';
                            break;
                        }
                    }
                }

                // Fall back to query parameter
                if (!$token && isset($_GET['token'])) {
                    $token = $_GET['token'];
                    $debug_info['token_source'] = 'query_param';
                }

                // Fall back to session token
                if (!$token && isset($_SESSION['token'])) {
                    $token = $_SESSION['token'];
                    $debug_info['token_source'] = 'session';
                }

                if (!$token) {
                    http_response_code(401);
                    echo json_encode([
                        'error' => 'Unauthorized - no token provided',
                        'request_method' => $_SERVER['REQUEST_METHOD'],
                        'request_uri' => $_SERVER['REQUEST_URI'],
                        'debug' => $debug_info
                    ]);
                    exit;
                }

                $debug_info['token_received'] = true;
                $debug_info['token_length'] = strlen($token);

                // Decode token to get user_id
                // Support both new format (base64 JSON) and old format (plain hex)
                $user_id = null;

                try {
                    // Try new format first (base64 encoded JSON)
                    $decoded = json_decode(base64_decode($token), true);
                    if ($decoded && isset($decoded['user_id'])) {
                        $token_data = $decoded;
                        $user_id = $token_data['user_id'];
                    } else {
                        // If new format doesn't work, try to find token in session
                        // (for backward compatibility with old sessions)
                        if (isset($_SESSION['token']) && $_SESSION['token'] === $token && isset($_SESSION['user_id'])) {
                            $user_id = $_SESSION['user_id'];
                        } else {
                            http_response_code(401);
                            echo json_encode(['error' => 'Invalid token format - please login again']);
                            exit;
                        }
                    }
                } catch (Exception $e) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid token - ' . $e->getMessage()]);
                    exit;
                }

                if (!$user_id) {
                    http_response_code(401);
                    echo json_encode(['error' => 'No user_id in token']);
                    exit;
                }

                // Get user from database
                $stmt = $pdo->prepare('SELECT id, full_name, email, role, status FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();

                if (!$user) {
                    http_response_code(401);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }

                if ($user['status'] !== 'active') {
                    http_response_code(403);
                    echo json_encode(['error' => 'User account is not active']);
                    exit;
                }

                // Return user info
                echo json_encode([
                    'user' => [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]
                ]);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;

        // ────────────────────────────────────────────────────────────────
        // ADMIN ENDPOINTS (Direct Database Implementation)
        // ────────────────────────────────────────────────────────────────
        case 'admin':
            // All admin endpoints require authentication
            $token = null;
            $user_id = null;
            $headers = getallheaders();

            foreach ($headers as $name => $value) {
                if (strtolower($name) === 'authorization') {
                    if (preg_match('/Bearer\s+(\S+)/i', $value, $matches)) {
                        $token = $matches[1];
                        break;
                    }
                }
            }

            // Verify token and get user
            if (!$token) {
                http_response_code(401);
                echo json_encode(['error' => 'Unauthorized - no token']);
                exit;
            }

            try {
                $decoded = json_decode(base64_decode($token), true);
                if (!$decoded || !isset($decoded['user_id'])) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Invalid token']);
                    exit;
                }
                $user_id = $decoded['user_id'];
            } catch (Exception $e) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid token format']);
                exit;
            }

            // Verify user is admin
            $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if (!$user || !in_array($user['role'], ['admin', 'moderator'])) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden - admin access required']);
                exit;
            }

            // Route to specific admin endpoint
            if ($slug === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                // GET /admin/stats - Dashboard statistics
                $stats = [
                    'users' => [
                        'total' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                        'active' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn(),
                        'new_week' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn(),
                        'new_month' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn(),
                    ],
                    'codes' => [
                        'total' => (int) $pdo->query('SELECT COUNT(*) FROM codes')->fetchColumn(),
                        'in_force' => (int) $pdo->query('SELECT COUNT(*) FROM codes WHERE status = "in_force"')->fetchColumn(),
                    ],
                    'articles' => [
                        'total' => (int) $pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
                        'in_force' => (int) $pdo->query('SELECT COUNT(*) FROM articles WHERE status = "in_force"')->fetchColumn(),
                        'amended' => (int) $pdo->query('SELECT COUNT(*) FROM articles WHERE status = "amended"')->fetchColumn(),
                        'abrogated' => (int) $pdo->query('SELECT COUNT(*) FROM articles WHERE status = "abrogated"')->fetchColumn(),
                        'draft' => (int) $pdo->query('SELECT COUNT(*) FROM articles WHERE status = "draft"')->fetchColumn(),
                        'total_views' => (int) $pdo->query('SELECT COALESCE(SUM(view_count), 0) FROM articles')->fetchColumn(),
                    ],
                    'comments' => [
                        'total' => 0,
                        'pending' => 0,
                        'approved' => 0,
                        'rejected' => 0,
                    ],
                    'notes' => [
                        'total' => 0,
                    ],
                    'top_viewed' => [],
                    'codes_breakdown' => [],
                    'recent_users' => [],
                    'pending_comments' => [],
                    'activity_week' => [],
                ];

                // Try to load comments data if table exists
                try {
                    $stats['comments']['total'] = (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
                    $stats['comments']['pending'] = (int) $pdo->query('SELECT COUNT(*) FROM comments WHERE status = "pending"')->fetchColumn();
                    $stats['comments']['approved'] = (int) $pdo->query('SELECT COUNT(*) FROM comments WHERE status = "approved"')->fetchColumn();
                    $stats['comments']['rejected'] = (int) $pdo->query('SELECT COUNT(*) FROM comments WHERE status = "rejected"')->fetchColumn();
                } catch (PDOException $e) {
                    // Table doesn't exist, keep default zeros
                }

                // Try to load notes data if table exists
                try {
                    $stats['notes']['total'] = (int) $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();
                } catch (PDOException $e) {
                    // Table doesn't exist, keep default zero
                }

                echo json_encode($stats);

            } elseif ($slug === 'users' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                // GET /admin/users - List users with pagination
                $page = (int) ($_GET['page'] ?? 1);
                $per_page = (int) ($_GET['per_page'] ?? 15);
                $offset = ($page - 1) * $per_page;

                $total = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $stmt = $pdo->prepare('SELECT id, full_name, email, role, status, created_at FROM users LIMIT ? OFFSET ?');
                $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
                $stmt->bindValue(2, $offset, PDO::PARAM_INT);
                $stmt->execute();
                $users = $stmt->fetchAll();

                echo json_encode([
                    'data' => $users,
                    'current_page' => $page,
                    'per_page' => $per_page,
                    'total' => $total,
                    'last_page' => ceil($total / $per_page),
                ]);

            } elseif ($slug && !strpos($slug, '/') && $_SERVER['REQUEST_METHOD'] === 'GET') {
                // GET /admin/{slug} - Get specific resource
                if ($slug === 'codes') {
                    $page = (int) ($_GET['page'] ?? 1);
                    $per_page = (int) ($_GET['per_page'] ?? 15);
                    $offset = ($page - 1) * $per_page;

                    $total = (int) $pdo->query('SELECT COUNT(*) FROM codes')->fetchColumn();
                    $stmt = $pdo->prepare('SELECT id, title_ar, title_fr, slug, type, status, official_number, promulgation_date, total_articles, created_at FROM codes LIMIT ? OFFSET ?');
                    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
                    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
                    $stmt->execute();
                    $codes = $stmt->fetchAll();

                    echo json_encode([
                        'data' => $codes,
                        'current_page' => $page,
                        'per_page' => $per_page,
                        'total' => $total,
                        'last_page' => ceil($total / $per_page),
                    ]);

                } elseif ($slug === 'articles' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                    // GET /admin/articles - List articles
                    $page = (int) ($_GET['page'] ?? 1);
                    $per_page = (int) ($_GET['per_page'] ?? 15);
                    $offset = ($page - 1) * $per_page;

                    $total = (int) $pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn();
                    $stmt = $pdo->prepare('SELECT id, code_id, number, number_int, slug, status, view_count, comment_count, created_at FROM articles LIMIT ? OFFSET ?');
                    $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
                    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
                    $stmt->execute();
                    $articles = $stmt->fetchAll();

                    echo json_encode([
                        'data' => $articles,
                        'current_page' => $page,
                        'per_page' => $per_page,
                        'total' => $total,
                        'last_page' => ceil($total / $per_page),
                    ]);

                } elseif ($slug === 'comments' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                    // GET /admin/comments - List comments
                    $page = (int) ($_GET['page'] ?? 1);
                    $per_page = (int) ($_GET['per_page'] ?? 15);
                    $offset = ($page - 1) * $per_page;

                    try {
                        $total = (int) $pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
                        $stmt = $pdo->prepare('SELECT id, article_id, author_id, content_ar, type, status, rejection_reason, upvotes, created_at FROM comments LIMIT ? OFFSET ?');
                        $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
                        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
                        $stmt->execute();
                        $comments = $stmt->fetchAll();

                        echo json_encode([
                            'data' => $comments,
                            'current_page' => $page,
                            'per_page' => $per_page,
                            'total' => $total,
                            'last_page' => ceil($total / $per_page),
                        ]);
                    } catch (PDOException $e) {
                        // Table doesn't exist, return empty list
                        echo json_encode([
                            'data' => [],
                            'current_page' => $page,
                            'per_page' => $per_page,
                            'total' => 0,
                            'last_page' => 1,
                        ]);
                    }

                } elseif ($slug === 'pdfs' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                    // GET /admin/pdfs - List PDFs
                    $page = (int) ($_GET['page'] ?? 1);
                    $per_page = (int) ($_GET['per_page'] ?? 15);
                    $offset = ($page - 1) * $per_page;

                    try {
                        $total = (int) $pdo->query('SELECT COUNT(*) FROM pdfs')->fetchColumn();
                        $stmt = $pdo->prepare('SELECT id, code_id, title_ar, title_fr, original_filename, stored_filename, file_size, document_type, status, articles_extracted, extraction_log, source_url, is_public, created_at FROM pdfs LIMIT ? OFFSET ?');
                        $stmt->bindValue(1, $per_page, PDO::PARAM_INT);
                        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
                        $stmt->execute();
                        $pdfs = $stmt->fetchAll();

                        echo json_encode([
                            'data' => $pdfs,
                            'current_page' => $page,
                            'per_page' => $per_page,
                            'total' => $total,
                            'last_page' => ceil($total / $per_page),
                        ]);
                    } catch (PDOException $e) {
                        // Table doesn't exist, return empty list
                        echo json_encode([
                            'data' => [],
                            'current_page' => $page,
                            'per_page' => $per_page,
                            'total' => 0,
                            'last_page' => 1,
                        ]);
                    }

                } elseif ($slug === 'code-types' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                    // GET /admin/code-types - List code types
                    try {
                        $stmt = $pdo->query('SELECT id, slug, name_ar, name_fr, color, sort_order FROM code_types ORDER BY sort_order ASC');
                        $code_types = $stmt->fetchAll();
                        echo json_encode($code_types);
                    } catch (PDOException $e) {
                        // Table doesn't exist, return empty array
                        echo json_encode([]);
                    }

                } elseif ($slug === 'articles' && $sub_resource === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                    // GET /admin/articles/stats
                    $stats = [
                        'total' => (int) $pdo->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
                        'in_force' => (int) $pdo->query('SELECT COUNT(*) FROM articles WHERE status = "in_force"')->fetchColumn(),
                        'abrogated' => (int) $pdo->query('SELECT COUNT(*) FROM articles WHERE status = "abrogated"')->fetchColumn(),
                        'amended' => (int) $pdo->query('SELECT COUNT(*) FROM articles WHERE status = "amended"')->fetchColumn(),
                        'by_code' => [],
                    ];

                    echo json_encode($stats);

                } elseif ($slug === 'users' && $sub_resource === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
                    // GET /admin/users/stats
                    $stats = [
                        'total' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                        'active' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn(),
                        'suspended' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE status = "suspended"')->fetchColumn(),
                        'by_role' => [
                            'admin' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn(),
                            'moderator' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "moderator"')->fetchColumn(),
                            'user' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn(),
                        ],
                        'new_today' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
                        'new_week' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn(),
                    ];

                    echo json_encode($stats);

                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Admin endpoint not found', 'slug' => $slug, 'sub_resource' => $sub_resource, 'method' => $_SERVER['REQUEST_METHOD']]);
                }
            }
            break;

        // ────────────────────────────────────────────────────────────────
        // DEBUG ENDPOINT - Test Admin API Connectivity
        // ────────────────────────────────────────────────────────────────
        case 'test-admin':
            // Simple endpoint to test if we can reach the Laravel API
            $target_url = 'http://almodawana.dreamhosters.com/api/v1/admin/stats';

            // Try with a test token
            $test_token = isset($_GET['token']) ? $_GET['token'] : null;

            $request_headers = [
                'Content-Type: application/json',
                'Accept: application/json',
            ];
            if ($test_token) {
                $request_headers[] = "Authorization: Bearer {$test_token}";
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $target_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose mode for debugging

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_info = curl_getinfo($ch);
            curl_close($ch);

            echo json_encode([
                'test' => 'Admin API connectivity',
                'target_url' => $target_url,
                'http_code' => $http_code,
                'curl_error' => $curl_error,
                'response_received' => !empty($response),
                'response_length' => strlen($response ?? ''),
                'response_preview' => substr($response ?? '', 0, 200),
                'curl_info' => [
                    'connect_time' => $curl_info['connect_time'] ?? null,
                    'total_time' => $curl_info['total_time'] ?? null,
                    'http_code' => $curl_info['http_code'] ?? null,
                    'request_size' => $curl_info['request_size'] ?? null,
                ],
                'has_token' => !!$test_token,
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
                'available' => ['codes', 'articles', 'books', 'search', 'auth', 'me', 'admin', 'test-admin']
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
