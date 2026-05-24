<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/email_templates.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Rate limit: 10 bulk operations per minute per user (more restrictive than single approvals)
$rateLimiter = new RateLimiter($pdo);
$approverId = (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
$approverName = trim((string)($_SESSION['username'] ?? ''));
if ($approverId <= 0 && $approverName === '') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid approver session']);
    exit();
}
$approverRateKey = $approverId > 0 ? "approver_{$approverId}" : ('approver_' . md5((string)($_SESSION['username'] ?? session_id())));
$limitCheck = $rateLimiter->check($approverRateKey, 'bulk_account_approval', 10, 1);
if (!$limitCheck['allowed']) {
    http_response_code(429); // Too Many Requests
    echo json_encode([
        'success' => false, 
        'message' => 'Too many bulk operations. Please try again later.',
        'retryAfter' => $limitCheck['reset_time']
    ]);
    exit();
}

requireRequestMethod('POST');

// Validate CSRF token
$csrfToken = InputSanitizer::post('csrf_token', 'string');
if (!InputSanitizer::validateCsrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Get the raw POST data
$input = json_decode(file_get_contents('php://input'), true);
$ids = isset($input['ids']) ? (array)$input['ids'] : [];
$action = isset($input['action']) ? (string)$input['action'] : '';
$reason = isset($input['reason']) ? (string)$input['reason'] : '';

if (empty($ids) || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid parameters']);
    exit();
}

$successCount = 0;
$errors = [];

foreach ($ids as $idData) {
    $userId = (int)($idData['id'] ?? 0);
    $accountType = strtolower((string)($idData['type'] ?? 'homeowner'));

    if (!$userId) continue;

    try {
        $pdo->beginTransaction();

        $user = null;
        $isHomeowner = ($accountType === 'homeowner');

        if ($isHomeowner) {
            $lockedStmt = $pdo->prepare("SELECT id, email, first_name, last_name, account_status FROM homeowners WHERE id = ? LIMIT 1 FOR UPDATE");
            $lockedStmt->execute([$userId]);
            $user = $lockedStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException('Homeowner account not found');
            }

            if (($user['account_status'] ?? '') !== 'pending') {
                throw new RuntimeException('Homeowner account has already been processed');
            }
        } else {
            $lockedStmt = $pdo->prepare("SELECT id, email, username, account_status FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
            $lockedStmt->execute([$userId]);
            $user = $lockedStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException('User account not found');
            }

            if (($user['account_status'] ?? '') !== 'pending') {
                throw new RuntimeException('User account has already been processed');
            }
        }
        
        if ($action === 'approve') {
            if ($isHomeowner) {
                $stmt = $pdo->prepare("UPDATE homeowners SET account_status = 'approved' WHERE id = ? AND account_status = 'pending'");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Homeowner approval update failed');
                }
                
                $stmt = $pdo->prepare("UPDATE homeowner_auth SET is_active = 1 WHERE homeowner_id = ?");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Homeowner auth activation failed');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET account_status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND account_status = 'pending'");
                $stmt->execute([$approverId, $userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('User approval update failed');
                }
            }
        } else { // reject
            if ($isHomeowner) {
                $stmt = $pdo->prepare("UPDATE homeowners SET account_status = 'rejected' WHERE id = ? AND account_status = 'pending'");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Homeowner rejection update failed');
                }
                
                $stmt = $pdo->prepare("UPDATE homeowner_auth SET is_active = 0 WHERE homeowner_id = ?");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Homeowner auth deactivation failed');
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET account_status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ? AND account_status = 'pending'");
                $stmt->execute([$approverId, $reason, $userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('User rejection update failed');
                }
            }
        }

        // Log action
        try {
            $stmt = $pdo->prepare("INSERT INTO account_approval_log (user_id, user_type, action, approved_by, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $accountType, $action === 'approve' ? 'approved' : 'rejected', $approverId, $reason]);
        } catch (PDOException $e) {}

        $pdo->commit();
        $successCount++;

        // Send Email (Non-blocking if possible, but here it's linear)
        if ($user && !empty($user['email'])) {
            try {
                $name = trim($user['first_name'] . ' ' . $user['last_name']);
                if (empty($name)) $name = $user['email'];
                $loginUrl = rtrim(getAppUrl(), '/') . '/auth/login.php';

                if ($action === 'approve') {
                    EmailService::send($user['email'], 'Account Approved — VehiScan RFID', EmailTemplates::accountApprovedEmail($name, $loginUrl));
                } else {
                    EmailService::send($user['email'], 'Account Rejected — VehiScan RFID', EmailTemplates::accountRejectedEmail($name, $reason));
                }
            } catch (Exception $e) {
                error_log("Bulk approval email error for ID $userId: " . $e->getMessage());
            }
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Bulk approval processing error for ID $userId: " . $e->getMessage());
        $errors[] = "ID $userId: Failed to process this account.";
    }
}

echo json_encode([
    'success' => $successCount > 0,
    'message' => "Successfully processed $successCount accounts." . (!empty($errors) ? " Errors: " . count($errors) : ""),
    'processed' => $successCount,
    'errors' => $errors
]);
