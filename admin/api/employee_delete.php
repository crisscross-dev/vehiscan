<?php
/**
 * ============================================================================
 * Canonical employee delete endpoint.
 *
 * Compatibility wrapper: admin/employee_delete.php now forwards here so there
 * is a single maintained delete implementation.
 * ============================================================================
 */
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

header('Content-Type: application/json');

requireRequestMethod('POST');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only Super Admin can delete employee accounts']);
    exit;
}

try {
    // CSRF validation using InputSanitizer
    $csrfToken = InputSanitizer::post('csrf_token', 'string');
    if (!InputSanitizer::validateCsrf($csrfToken)) {
        throw new Exception('Invalid CSRF token', 403);
    }
    
    $id = InputSanitizer::post('id', 'int');
    
    if (!$id) {
        throw new Exception('Employee ID is required', 400);
    }

    $confirmation = strtoupper(trim((string)InputSanitizer::post('confirmation', 'string')));
    if ($confirmation !== 'DELETE') {
        throw new Exception('Confirmation text is required', 400);
    }
    
    // Get employee details before deletion
    $stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        throw new Exception('Employee not found', 404);
    }
    
    // Only super admin can delete super_admin accounts
    if ($employee['role'] === 'super_admin' && $_SESSION['role'] !== 'super_admin') {
        throw new Exception('Only Super Admin can delete Super Admin accounts', 403);
    }
    
    // Prevent self-deletion
    if ($id == ($_SESSION['user_id'] ?? $_SESSION['admin_id'])) {
        throw new Exception('You cannot delete your own account', 400);
    }
    
    // Delete employee
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    
    // Audit log
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, 'employee_delete', ?)");
    $stmt->execute([
        $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null,
        json_encode(['employee_id' => $id, 'username' => $employee['username'], 'role' => $employee['role']])
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
    
} catch (PDOException $e) {
    error_log('Employee delete DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again later.']);
} catch (Exception $e) {
    error_log('Employee delete error: ' . $e->getMessage());
    $code = (int)$e->getCode();
    if ($code < 400 || $code > 599) {
        $code = 400;
    }
    http_response_code($code);
    $safeMessage = $code >= 500
        ? 'An unexpected server error occurred. Please try again later.'
        : 'The request could not be processed. Please verify your input and try again.';
    echo json_encode(['success' => false, 'message' => $safeMessage]);
}
