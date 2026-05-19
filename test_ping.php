<?php
// Simple PHP availability check
http_response_code(200);
header('Content-Type: text/plain');
echo "OK\n";
// Log remote info for debug
error_log('test_ping accessed from: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
