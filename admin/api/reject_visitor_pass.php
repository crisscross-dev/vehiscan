<?php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
requireRequestMethod('POST');
if (!in_array($_SESSION['role'] ?? '', ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/cache_invalidator.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/email_templates.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

header('Content-Type: application/json');

AuditLogger::init($pdo);

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = [];
}

// CSRF validation
$csrf = $data['csrf_token'] ?? '';
if (!InputSanitizer::validateCsrf((string)$csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$pass_id = isset($data['pass_id']) ? InputSanitizer::sanitizeInt($data['pass_id']) : 0;
$reason = isset($data['reason']) ? InputSanitizer::sanitizeString($data['reason']) : '';

if (!$pass_id || !$reason) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

try {
    $approverId = (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
    if ($approverId <= 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Missing approver session. Please log in again.']);
        exit();
    }

    $stmt = $pdo->prepare("
        UPDATE visitor_passes 
        SET status = 'rejected', 
            rejection_reason = ?,
            approved_by = ?, 
            approved_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");

    $stmt->execute([$reason, $approverId, $pass_id]);

    if ($stmt->rowCount() > 0) {
        try {
            AuditLogger::logDataChange('visitor_pass_rejected', 'visitor_passes', (int)$pass_id, [
                'status' => 'pending',
                'approved_by' => null,
                'rejection_reason' => null,
            ], [
                'status' => 'rejected',
                'approved_by' => $approverId,
                'rejection_reason' => $reason,
            ]);
        } catch (Exception $auditError) {
            error_log('Reject visitor pass audit error: ' . $auditError->getMessage());
        }

        // Invalidate caches
        CacheInvalidator::invalidatePasses();
        CacheInvalidator::invalidateDashboard();

        if (function_exists('logAudit')) {
            logAudit('VISITOR_PASS_REJECTED', 'visitor_passes', $pass_id, "Rejected visitor pass #$pass_id: $reason");
        }

        $homeownerEmailStmt = $pdo->prepare("SELECT h.email, h.name, vp.visitor_name FROM visitor_passes vp INNER JOIN homeowners h ON h.id = vp.homeowner_id WHERE vp.id = ? LIMIT 1");
        $homeownerEmailStmt->execute([$pass_id]);
        $passOwner = $homeownerEmailStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $homeownerEmail = trim((string)($passOwner['email'] ?? ''));
        if ($homeownerEmail !== '') {
            try {
                EmailService::send(
                    $homeownerEmail,
                    'Visitor Pass Rejected - VehiScan RFID',
                    EmailTemplates::visitorPassRejectedEmail(
                        (string)($passOwner['name'] ?? 'Homeowner'),
                        (string)($passOwner['visitor_name'] ?? 'Visitor'),
                        $reason
                    )
                );
            } catch (Throwable $emailError) {
                error_log('Reject visitor pass email error: ' . $emailError->getMessage());
            }
        }

        echo json_encode(['success' => true]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pass not found or already processed']);
    }

} catch (PDOException $e) {
    error_log("Reject pass error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
