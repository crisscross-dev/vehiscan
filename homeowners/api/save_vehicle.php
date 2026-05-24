<?php
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');
requireRequestMethod('POST');

// Hardened: this legacy endpoint is intentionally retired to avoid overlapping write logic.
http_response_code(410);
echo json_encode([
    'success' => false,
    'message' => 'This endpoint is deprecated. Use api/add_vehicle.php for vehicle creation.'
]);
