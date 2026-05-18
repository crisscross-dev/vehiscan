<?php
header('Content-Type: application/json');
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
require_once __DIR__ . '/qr_helper.php';

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

if (!$pass_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid pass ID']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Require a linked, approved homeowner. Unlinked passes must not be approvable.
    $getPass = $pdo->prepare("SELECT vp.id, vp.qr_token, vp.homeowner_id, vp.visitor_name, vp.valid_from, vp.valid_until, h.account_status, h.email AS homeowner_email, h.name AS homeowner_name FROM visitor_passes vp INNER JOIN homeowners h ON vp.homeowner_id = h.id WHERE vp.id = ? AND vp.status = 'pending' FOR UPDATE");
    $getPass->execute([$pass_id]);
    $pass = $getPass->fetch(PDO::FETCH_ASSOC);

    if (!$pass) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pass not found or already processed']);
        exit();
    }

    if (empty($pass['homeowner_id']) || ($pass['account_status'] ?? '') !== 'approved') {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot approve pass: linked homeowner account is not approved.'
        ]);
        exit();
    }

    $approverId = (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
    if ($approverId <= 0) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Missing approver session. Please log in again.']);
        exit();
    }

    $approvedByValue = $approverId;
    try {
        $fkTargetStmt = $pdo->query("\n            SELECT REFERENCED_TABLE_NAME\n            FROM information_schema.KEY_COLUMN_USAGE\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'visitor_passes'\n              AND COLUMN_NAME = 'approved_by'\n              AND REFERENCED_TABLE_NAME IS NOT NULL\n            LIMIT 1\n        ");
        $fkTargetTable = strtolower((string)($fkTargetStmt->fetchColumn() ?: ''));

        if ($fkTargetTable === 'users') {
            $approverExistsStmt = $pdo->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
            $approverExistsStmt->execute([$approverId]);
            if (!$approverExistsStmt->fetchColumn()) {
                $approvedByValue = null;
            }
        } elseif ($fkTargetTable !== '') {
            // Legacy schemas may still point to non-users tables (e.g., super_admin).
            // Store NULL to avoid FK violations until migration repair is applied.
            $approvedByValue = null;
        }
    } catch (Throwable $fkCheckError) {
        error_log('Approve pass FK target check warning: ' . $fkCheckError->getMessage());
    }

    // Generate QR code
    $qrCode = generateVisitorPassQR($pass_id, $pass['qr_token'], $pdo);

    // Update the pass with active status and QR code
    $stmt = $pdo->prepare("
        UPDATE visitor_passes 
        SET status = 'active', 
            approved_by = ?, 
            approved_at = NOW(),
            qr_code = ?
        WHERE id = ?
    ");

    $stmt->execute([
            $approvedByValue,
        $qrCode,
        $pass_id
    ]);


    if ($stmt->rowCount() === 1) {
        $pdo->commit();

        try {
            AuditLogger::logDataChange('visitor_pass_approved', 'visitor_passes', (int)$pass_id, [
                'status' => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ], [
                'status' => 'active',
                'approved_by' => $approvedByValue,
                'approved_at' => date('Y-m-d H:i:s'),
                'qr_code' => !empty($qrCode),
            ]);
        } catch (Exception $auditError) {
            error_log('Approve visitor pass audit error: ' . $auditError->getMessage());
        }

        // Invalidate caches
        CacheInvalidator::invalidatePasses();

        $homeownerEmail = trim((string)($pass['homeowner_email'] ?? ''));
        $homeownerName = trim((string)($pass['homeowner_name'] ?? ''));
        if ($homeownerName === '') {
            $homeownerName = 'Homeowner';
        }

        if ($homeownerEmail !== '') {
            try {
                $passUrl = rtrim(getAppUrl(), '/') . '/visitor/view_pass.php?token=' . urlencode((string)$pass['qr_token']);
                EmailService::send(
                    $homeownerEmail,
                    'Visitor Pass Approved - VehiScan RFID',
                    EmailTemplates::visitorPassApprovedEmail(
                        $homeownerName,
                        (string)($pass['visitor_name'] ?? 'Visitor'),
                        (string)($pass['valid_from'] ?? ''),
                        (string)($pass['valid_until'] ?? ''),
                        $passUrl
                    )
                );
            } catch (Throwable $emailError) {
                error_log('Approve visitor pass email error: ' . $emailError->getMessage());
            }
        }

        echo json_encode(['success' => true, 'message' => 'Pass activated successfully']);
    } else {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Failed to update pass']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Approve pass error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again later.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Approve pass error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
