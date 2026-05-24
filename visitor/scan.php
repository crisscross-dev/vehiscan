<?php
/**
 * QR Redirect Handler with anti-enumeration protections.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

$token = trim((string)($_GET['token'] ?? $_GET['t'] ?? ''));
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// 3 attempts per minute per IP.
$rateLimiter = new RateLimiter($pdo);
$rate = $rateLimiter->check($ipAddress, 'qr_scan', 3, 1);
if (!$rate['allowed']) {
    http_response_code(429);
    echo '<!DOCTYPE html><html><head><title>Too Many Requests</title></head><body><h1>Too Many Requests</h1><p>Please wait a minute and try scanning again.</p></body></html>';
    exit;
}

if ($token === '' || !preg_match('/^[a-fA-F0-9]{32,64}$/', $token)) {
    $rateLimiter->recordAttempt($ipAddress, 'qr_scan', ['error' => 'invalid_format']);
    http_response_code(400);
    echo '<!DOCTYPE html><html><head><title>Invalid QR Code</title></head><body><h1>Invalid QR Code</h1><p>The QR code is invalid or missing. Please try scanning again.</p></body></html>';
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id FROM visitor_passes WHERE qr_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $exists = (bool)$stmt->fetchColumn();
    if (!$exists) {
        $rateLimiter->recordAttempt($ipAddress, 'qr_scan', ['error' => 'token_not_found']);
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>Invalid QR Code</title></head><body><h1>Invalid QR Code</h1><p>The QR code is invalid or expired.</p></body></html>';
        exit;
    }
} catch (Throwable $e) {
    error_log('[VISITOR_SCAN] ' . $e->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>System Error</title></head><body><h1>System Error</h1><p>Please try again later.</p></body></html>';
    exit;
}

$redirectUrl = 'view_pass.php?token=' . urlencode($token);
header('Location: ' . $redirectUrl);
exit;
