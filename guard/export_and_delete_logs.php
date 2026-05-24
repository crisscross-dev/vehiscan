<?php
/**
 * Export and Delete Logs
 * 
 * Security: Only administrators can delete logs.
 * This endpoint is deprecated for guard access.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/session_guard.php';

// SECURITY: Guards can no longer delete logs - only administrators
// This endpoint is deprecated and will return 403 for all guard access
http_response_code(403);
exit(json_encode([
    'success' => false,
    'message' => 'Access denied. Only administrators can delete logs.'
]));
