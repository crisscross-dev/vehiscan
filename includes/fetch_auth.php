<?php
// Centralized auth helper for admin/fetch AJAX fragments
// Detects AJAX requests and returns JSON 401/403 for unauthorized requests.

if (session_status() === PHP_SESSION_NONE) {
    if (function_exists('initializeVehiscanSessionPath')) {
        initializeVehiscanSessionPath();
    }
    session_start();
}

require_once __DIR__ . '/session_helpers.php';

function is_ajax_request_fetch(): bool {
    $xrw = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $hasCsrfHeader = !empty($_SERVER['HTTP_X_CSRF_TOKEN']);
    return (strtolower($xrw) === 'xmlhttprequest') || (stripos($accept, 'application/json') !== false) || $hasCsrfHeader;
}

function fetch_require_role(array $roles, ?string $htmlFallback = null)
{
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles, true)) {
        if (is_ajax_request_fetch()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'unauthorized']);
            exit();
        }

        // Non-AJAX: if caller provided an HTML fallback, render that; otherwise redirect to login
        if ($htmlFallback !== null) {
            http_response_code(403);
            echo $htmlFallback;
            exit();
        }

        $loginPath = function_exists('vehiscanLoginPath') ? vehiscanLoginPath() : '/auth/login.php';
        header('Location: ' . $loginPath);
        exit();
    }
}
