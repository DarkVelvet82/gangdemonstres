<?php
// Lightweight DB migration runner (web-triggered)
// Security: requires a valid token; compares against a SHA-256 hash stored server-side

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2); // gang-de-monstres-standalone
$tokenFile = $root . '/config/migrations.token.php';

if (!file_exists($tokenFile)) {
    http_response_code(403);
    echo json_encode([ 'ok' => false, 'error' => 'Token file missing' ]);
    exit;
}

require $tokenFile; // defines MIGRATION_TOKEN_SHA256

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
if (!defined('MIGRATION_TOKEN_SHA256') || $token === '') {
    http_response_code(403);
    echo json_encode([ 'ok' => false, 'error' => 'Missing token' ]);
    exit;
}

// Constant-time compare
function hash_equals_safe($known_hash, $user_input) {
    if (!is_string($known_hash) || !is_string($user_input)) return false;
    if (function_exists('hash_equals')) return hash_equals($known_hash, $user_input);
    if (strlen($known_hash) !== strlen($user_input)) return false;
    $res = 0; $len = strlen($known_hash);
    for ($i = 0; $i < $len; $i++) { $res |= ord($known_hash[$i]) ^ ord($user_input[$i]); }
    return $res === 0;
}

$tokenHash = hash('sha256', $token);
if (!hash_equals_safe(MIGRATION_TOKEN_SHA256, $tokenHash)) {
    http_response_code(403);
    echo json_encode([ 'ok' => false, 'error' => 'Invalid token' ]);
    exit;
}

// Get PDO from app config
$pdo = require $root . '/config/database.php';

// Ensure schema_migrations table
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

// Load applied migrations
$applied = [];
foreach ($pdo->query('SELECT name FROM schema_migrations') as $row) {
    $applied[$row['name']] = true;
}

$migrationsDir = $root . '/db/migrations';
if (!is_dir($migrationsDir)) { mkdir($migrationsDir, 0775, true); }
$files = glob($migrationsDir . '/*.sql');
sort($files, SORT_STRING);

$appliedNow = [];

// Naive SQL splitter: split on ';' and execute non-empty statements
function run_sql_file(PDO $pdo, string $path) {
    $sql = file_get_contents($path);
    // Normalize line endings
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    $buffer = '';
    $inString = false; $stringChar = '';
    $stmts = [];
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $buffer .= $ch;
        if ($inString) {
            if ($ch === $stringChar) {
                // handle escaped quotes
                $escaped = ($i > 0 && $sql[$i-1] === '\\');
                if (!$escaped) { $inString = false; $stringChar = ''; }
            }
            continue;
        }
        if ($ch === '\'' || $ch === '"') { $inString = true; $stringChar = $ch; continue; }
        if ($ch === ';') { $stmts[] = trim($buffer);
            $buffer = ''; }
    }
    $last = trim($buffer);
    if ($last !== '') $stmts[] = $last;

    $pdo->beginTransaction();
    try {
        foreach ($stmts as $stmt) {
            if ($stmt === '' || stripos($stmt, '--') === 0 || stripos($stmt, '/*') === 0) continue;
            $pdo->exec($stmt);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) continue;
    try {
        run_sql_file($pdo, $file);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)');
        $stmt->execute([$name]);
        $appliedNow[] = $name;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Migration failed',
            'file' => $name,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

echo json_encode([
    'ok' => true,
    'applied' => $appliedNow,
    'count' => count($appliedNow),
]);
?>

