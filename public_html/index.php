<?php

/**
 * VehiScan Bootstrap for Hostinger
 * Routes requests from public_html to the app folder outside web root
 * Uses internal routing to avoid redirect loops
 * Serves static assets from vehiscan folder
 */

define('APP_ROOT', __DIR__ . '/../vehiscan');

// Verify app exists
if (!is_dir(APP_ROOT) || !is_file(APP_ROOT . '/index.php')) {
    http_response_code(500);
    die('VehiScan app folder not found at: ' . htmlspecialchars(APP_ROOT));
}

// Set working directory and include path for relative requires
chdir(APP_ROOT);
set_include_path(APP_ROOT . PATH_SEPARATOR . get_include_path());

// Determine which file to load based on REQUEST_URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$basePath = parse_url($requestUri, PHP_URL_PATH);

// Allow direct access to lightweight diagnostic endpoints so front-controller
// routing does not mask deployment and connectivity issues during triage.
$diagnosticFiles = [
    'test_ping.php',
    'test_db_public.php',
    'test_db_public_v2.php',
    'test_db.php',
];

// Remove public_html if it's in the path (shouldn't be, but be safe)
$basePath = str_replace('/public_html', '', $basePath);

// Strip leading slash for file lookup
$targetFile = ltrim($basePath, '/');

// Handle static assets (CSS, JS, images) directly from vehiscan folder
$staticDirs = ['assets', 'uploads', 'phpqrcode'];
foreach ($staticDirs as $staticDir) {
    if (strpos($targetFile, $staticDir . '/') === 0) {
        $staticPath = APP_ROOT . '/' . $targetFile;

        // Security: prevent directory traversal
        $staticPath = str_replace('..', '', $staticPath);

        if (file_exists($staticPath) && is_file($staticPath)) {
            // Serve with appropriate content type
            $ext = pathinfo($staticPath, PATHINFO_EXTENSION);
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
            ];
            $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $contentType);
            readfile($staticPath);
            exit;
        }
    }
}

// Load config and bootstrap for dynamic content
require_once APP_ROOT . '/config.php';

// Default to auth/login.php if root or empty
if ($basePath === '/' || $basePath === '' || $basePath === '/index.php') {
    $targetFile = 'auth/login.php';
}

// Security: prevent directory traversal
$targetFile = str_replace('..', '', $targetFile);

if (in_array($targetFile, $diagnosticFiles, true)) {
    $diagnosticPath = APP_ROOT . '/' . $targetFile;

    if (file_exists($diagnosticPath) && is_file($diagnosticPath)) {
        include $diagnosticPath;
        exit;
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Diagnostic endpoint missing: {$targetFile}\n";
    echo "Expected at: {$diagnosticPath}\n";
    exit;
}

// Resolve to full path
$fullPath = APP_ROOT . '/' . $targetFile;

// Check if file exists in app
if (file_exists($fullPath) && is_file($fullPath)) {
    // Include the file (will output its content)
    include $fullPath;
    exit;
}

// If not found, try loading index.php of subdirectories or redirect to login
if (is_dir($fullPath)) {
    $indexPath = $fullPath . '/index.php';
    if (file_exists($indexPath)) {
        include $indexPath;
        exit;
    }
}

// Fallback: redirect to login
header("Location: " . getAppUrl() . '/auth/login.php');
exit;
