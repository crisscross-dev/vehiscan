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

// Rate limit: 30 account approvals/rejections per minute per user
$rateLimiter = new RateLimiter($pdo);
$approverId = (int)($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0);
$approverName = trim((string)($_SESSION['username'] ?? ''));
if ($approverId <= 0 && $approverName === '') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid approver session']);
    exit();
}
$approverRateKey = $approverId > 0 ? "approver_{$approverId}" : ('approver_' . md5((string)($_SESSION['username'] ?? session_id())));
$limitCheck = $rateLimiter->check($approverRateKey, 'account_approval', 30, 1);
if (!$limitCheck['allowed']) {
    http_response_code(429); // Too Many Requests
    echo json_encode([
        'success' => false, 
        'message' => 'Too many account approvals. Please try again later.',
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
    
// Sanitize inputs
$userId = InputSanitizer::post('user_id', 'int');
$accountType = strtolower((string)InputSanitizer::post('account_type', 'string'));
$action = InputSanitizer::post('action', 'string');
$reason = InputSanitizer::post('reason', 'string');

if (!$userId || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Whitelist validation for action
if (!in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

if ($accountType !== '' && !in_array($accountType, ['homeowner', 'user'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid account type']);
    exit();
}

try {
    $pdo->beginTransaction();

    $homeowner = null;
    $user = null;

    // Determine target table. Account type is preferred to avoid ID collisions.
    if ($accountType === 'homeowner') {
        $isHomeowner = true;
    } elseif ($accountType === 'user') {
        $isHomeowner = false;
    } else {
        // Backward compatibility path for older clients not sending account_type.
        $checkStmt = $pdo->prepare("SELECT id, account_status FROM homeowners WHERE id = ? LIMIT 1 FOR UPDATE");
        $checkStmt->execute([$userId]);
        $homeownerRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $isHomeowner = (bool)$homeownerRecord;
    }

    if ($isHomeowner) {
        $lockedStmt = $pdo->prepare("SELECT id, email, first_name, last_name, account_status FROM homeowners WHERE id = ? LIMIT 1 FOR UPDATE");
        $lockedStmt->execute([$userId]);
        $homeowner = $lockedStmt->fetch(PDO::FETCH_ASSOC);

        if (!$homeowner) {
            throw new RuntimeException('Homeowner account not found');
        }

        if (($homeowner['account_status'] ?? '') !== 'pending') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Account has already been processed']);
            exit();
        }
    } else {
        $lockedStmt = $pdo->prepare("SELECT id, email, username, account_status FROM users WHERE id = ? LIMIT 1 FOR UPDATE");
        $lockedStmt->execute([$userId]);
        $user = $lockedStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException('User account not found');
        }

        if (($user['account_status'] ?? '') !== 'pending') {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Account has already been processed']);
            exit();
        }
    }
        
        if ($action === 'approve') {
            if ($isHomeowner) {
                // Approve homeowner account
                $stmt = $pdo->prepare("
                    UPDATE homeowners 
                    SET account_status = 'approved'
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Homeowner approval update failed');
                }
                
                // Activate homeowner auth
                $stmt = $pdo->prepare("
                    UPDATE homeowner_auth 
                    SET is_active = 1
                    WHERE homeowner_id = ?
                ");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Homeowner auth activation failed');
                }
                
                // Locked homeowner row already fetched above for notification.
                
                // Log the approval
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO account_approval_log (user_id, user_type, action, approved_by, reason)
                        VALUES (?, 'homeowner', 'approved', ?, ?)
                    ");
                    $stmt->execute([$userId, $approverId, $reason]);
                } catch (PDOException $e) {
                    // Table may not exist, continue anyway
                    error_log('Could not log approval: ' . $e->getMessage());
                }
                
                $message = 'Homeowner account approved successfully';
                
            } else {
                // Approve regular user account
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET account_status = 'approved',
                        approved_by = ?,
                        approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$approverId, $userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('User approval update failed');
                }
                
                // Log the approval
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO account_approval_log (user_id, user_type, action, approved_by, reason)
                        VALUES (?, 'user', 'approved', ?, ?)
                    ");
                    $stmt->execute([$userId, $approverId, $reason]);
                } catch (PDOException $e) {
                    error_log('Could not log approval: ' . $e->getMessage());
                }
                
                $message = 'Account approved successfully';
            }
            
        } elseif ($action === 'reject') {
            if ($isHomeowner) {
                // Reject homeowner account
                $stmt = $pdo->prepare("
                    UPDATE homeowners 
                    SET account_status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Homeowner rejection update failed');
                }
                
                // Deactivate homeowner auth
                $stmt = $pdo->prepare("
                    UPDATE homeowner_auth 
                    SET is_active = 0
                    WHERE homeowner_id = ?
                ");
                $stmt->execute([$userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Homeowner auth deactivation failed');
                }
                
                // Locked homeowner row already fetched above for notification.
                
                // Log the rejection
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO account_approval_log (user_id, user_type, action, approved_by, reason)
                        VALUES (?, 'homeowner', 'rejected', ?, ?)
                    ");
                    $stmt->execute([$userId, $approverId, $reason]);
                } catch (PDOException $e) {
                    error_log('Could not log rejection: ' . $e->getMessage());
                }
                
                $message = 'Homeowner account rejected';
                
            } else {
                // Reject regular user
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET account_status = 'rejected',
                        approved_by = ?,
                        approved_at = NOW(),
                        rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$approverId, $reason, $userId]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('User rejection update failed');
                }
                
                // Log the rejection
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO account_approval_log (user_id, user_type, action, approved_by, reason)
                        VALUES (?, 'user', 'rejected', ?, ?)
                    ");
                    $stmt->execute([$userId, $approverId, $reason]);
                } catch (PDOException $e) {
                    error_log('Could not log rejection: ' . $e->getMessage());
                }
                
                $message = 'Account rejected';
            }
            
        } else {
            throw new Exception('Invalid action');
        }
        
    $pdo->commit();

    if ($isHomeowner) {
        $homeownerEmail = trim((string)($homeowner['email'] ?? ''));
        $homeownerName = trim((string)(($homeowner['first_name'] ?? '') . ' ' . ($homeowner['last_name'] ?? '')));
        if ($homeownerName === '') {
            $homeownerName = $homeownerEmail !== '' ? $homeownerEmail : 'Homeowner';
        }

        $loginUrl = rtrim(getAppUrl(), '/') . '/auth/login.php';

        try {
            if ($homeownerEmail !== '') {
                if ($action === 'approve') {
                    EmailService::send(
                        $homeownerEmail,
                        'Account Approved — VehiScan RFID',
                        EmailTemplates::accountApprovedEmail($homeownerName, $loginUrl)
                    );
                } elseif ($action === 'reject') {
                    EmailService::send(
                        $homeownerEmail,
                        'Account Rejected — VehiScan RFID',
                        EmailTemplates::accountRejectedEmail($homeownerName, (string)$reason)
                    );
                }
            }
        } catch (Throwable $emailError) {
            error_log('Approve user account email error: ' . $emailError->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Approve user account error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
