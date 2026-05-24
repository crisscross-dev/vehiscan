<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

requireRequestMethod('GET');

require_once __DIR__ . '/../../includes/session_homeowner.php';

// Check if session is valid (session_homeowner.php already handles timeout + role check)
$valid = isset($_SESSION['homeowner_id']) && 
         isset($_SESSION['role']) && $_SESSION['role'] === 'homeowner' &&
         isset($_SESSION['last_activity']);

echo json_encode([
    'valid' => $valid,
    'timestamp' => time(),
    'remaining_time' => $valid && isset($_SESSION['last_activity']) 
        ? (1800 - (time() - $_SESSION['last_activity'])) 
        : 0
]);
