<?php
// Fetch approvals page component
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo '<div class="p-6 text-center text-red-600">Unauthorized - Super admin access required</div>';
    exit();
}

require_once __DIR__ . '/../components/approvals_page.php';
