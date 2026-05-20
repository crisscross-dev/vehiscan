<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
// Ensure CSP nonce helper is available for inline style/script nonces
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/fetch_auth.php';

requireRequestMethod('GET');

fetch_require_role(['super_admin', 'admin'], '<div class="p-6 text-center text-red-600">Unauthorized - Administrator access required</div>');

require_once __DIR__ . '/../../db.php';

// Include the visitor logs page component
include_once __DIR__ . '/../components/visitor_logs_page.php';
