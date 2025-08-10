<?php
// Central bootstrap: sessions, error reporting, path constants, env, db connect
if (session_status() === PHP_SESSION_NONE) { session_start(); }
ini_set('display_errors', 0);
ini_set('log_errors', 1);
if (!is_dir(__DIR__ . '/logs')) { @mkdir(__DIR__ . '/logs', 0755, true); }
ini_set('error_log', __DIR__ . '/logs/php-error.log');

define('ROOT_PATH', __DIR__);
define('PAGES_PATH', __DIR__ . '/pages');
define('ADMIN_PATH', __DIR__ . '/admin');
define('ASSETS_PATH', __DIR__ . '/assets');

// BASE_URL builder without double slashes
function base_url(string $path = ''): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $prefix = rtrim('/', '/'); // noop
    $path = '/' . ltrim($path, '/');
    return $scheme . '://' . $host . $path;
}

// Load env (try outside public_html for security)
$env_candidates = [
    dirname(__DIR__) . '/.env.php',
    __DIR__ . '/.env.php',
    dirname(__DIR__, 2) . '/.env.php'
];
foreach ($env_candidates as $env) {
    if (file_exists($env)) { include $env; break; }
}

// Simple DB connector via PDO if variables exist
try {
    if (!empty($DB_HOST) && !empty($DB_DATABASE)) {
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_DATABASE};charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USERNAME ?? '', $DB_PASSWORD ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
} catch (Throwable $e) {
    error_log('DB connect failed: ' . $e->getMessage());
}
