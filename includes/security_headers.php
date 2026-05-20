<?php
/**
 * Security Headers
 * Sets HTTP security headers to protect against common web vulnerabilities
 */

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
    $hostWithoutPort = preg_replace('/:\\d+$/', '', $host);

    // Guard redirect targets against malformed/untrusted Host header values.
    $isValidHost = (bool)preg_match('/^[a-z0-9.-]+$/', $hostWithoutPort)
        || filter_var($hostWithoutPort, FILTER_VALIDATE_IP);
    if (!$isValidHost) {
        $fallbackHost = strtolower((string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
        $hostWithoutPort = $fallbackHost !== '' ? $fallbackHost : 'localhost';
    }

    $isLocalhost = in_array($hostWithoutPort, ['localhost', '127.0.0.1', '::1'], true);

    $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $trustedProxyCsv = (string)(getenv('TRUSTED_PROXIES') ?: '');
    $trustedProxies = array_values(array_filter(array_map('trim', explode(',', $trustedProxyCsv))));
    $isTrustedProxy = in_array($remoteAddr, $trustedProxies, true);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') ||
               ($isTrustedProxy && $forwardedProto === 'https');

    // Enforce HTTPS in non-local environments.
    if (!$isLocalhost && !$isHttps && !empty($_SERVER['REQUEST_URI'])) {
        $redirectHost = $hostWithoutPort !== '' ? $hostWithoutPort : 'localhost';
        $redirectUrl = 'https://' . $redirectHost . (string)$_SERVER['REQUEST_URI'];
        $redirectCode = in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD'], true) ? 301 : 307;
        header('Location: ' . $redirectUrl, true, $redirectCode);
        exit;
    }
}

// Safe wrapper for filemtime() used across many entry pages to avoid fatal warnings
if (!function_exists('safeFileTime')) {
    function safeFileTime(string $path): int {
        return file_exists($path) ? @filemtime($path) : 0;
    }
}

// Prevent clickjacking
header('X-Frame-Options: SAMEORIGIN');

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Enable XSS protection
header('X-XSS-Protection: 1; mode=block');

// Content Security Policy — mitigate XSS, data injection, click-jacking
// Content Security Policy with per-request nonce for inline scripts/styles.
// Pages should echo the nonce via the global helper `vehiscanGetCspNonce()`
// in inline `<script nonce="...">` and `<style nonce="...">` tags.
if (!function_exists('vehiscanGetCspNonce')) {
    $csp_nonce = bin2hex(random_bytes(16));
    $GLOBALS['VEHISCAN_CSP_NONCE'] = $csp_nonce;
    function vehiscanGetCspNonce(): string {
        return $GLOBALS['VEHISCAN_CSP_NONCE'] ?? '';
    }
}

$nonce = vehiscanGetCspNonce();
// For hardened deployment: prefer local assets only.
// Remove external CDN/style hosts to minimize external dependencies.
$script_src = "'self' 'nonce-$nonce'";
$style_src = "'self' 'nonce-$nonce'";

header("Content-Security-Policy: default-src 'self'; script-src $script_src; style-src $style_src; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'self';");

// Control referrer information
header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions policy - restrict browser features
header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');

// HSTS - enforce HTTPS when deployed over TLS
if (!$isCli && $isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

// Cache control for authenticated pages.
// Some entry points include this file before session bootstrap; in that case,
// rely on role-cookie presence to keep protected pages out of browser caches.
$hasAuthSessionCookie = isset($_COOKIE['vehiscan_superadmin'])
    || isset($_COOKIE['vehiscan_admin'])
    || isset($_COOKIE['vehiscan_guard'])
    || isset($_COOKIE['vehiscan_homeowner']);

if ((session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['username'])) || $hasAuthSessionCookie) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
?>