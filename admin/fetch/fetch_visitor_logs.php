<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'], true)) {
  http_response_code(403);
  exit('Unauthorized');
}

require_once __DIR__ . '/../../db.php';

// Include the visitor logs page component
include_once __DIR__ . '/../components/visitor_logs_page.php';
