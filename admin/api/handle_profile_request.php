<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

header('Content-Type: application/json');

AuditLogger::init($pdo);

// Check if user is admin or super admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
$requestId = InputSanitizer::post('request_id', 'int');
$action = InputSanitizer::post('action', 'string');
$adminNotes = InputSanitizer::post('admin_notes', 'string');

if (!$requestId || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Whitelist validation for action
$validActions = ['acknowledged', 'completed', 'rejected'];
if (!in_array($action, $validActions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

try {
    $pdo->beginTransaction();

        // Verify request exists and is actionable
        $checkStmt = $pdo->prepare("
            SELECT id, status, homeowner_id, draft_payload 
            FROM profile_update_requests 
            WHERE id = ?
        ");
        $checkStmt->execute([$requestId]);
        $request = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Request not found']);
            exit();
        }

        // If action is "completed" and there's a draft_payload, apply the changes
            $oldHomeownerValues = null;
            $newHomeownerValues = null;

        if ($action === 'completed' && !empty($request['draft_payload'])) {
            $draftData = json_decode($request['draft_payload'], true);
            if (is_array($draftData)) {
                // Whitelist allowed fields to update
                $allowedFields = ['name', 'contact_number', 'address', 'plate_number', 'vehicle_type', 'color'];
                $updateFields = [];
                $updateParams = [];
                    $oldHomeownerStmt = $pdo->prepare("SELECT name, contact_number, address, plate_number, vehicle_type, color FROM homeowners WHERE id = ? LIMIT 1");
                    $oldHomeownerStmt->execute([$request['homeowner_id']]);
                    $oldHomeownerValues = $oldHomeownerStmt->fetch(PDO::FETCH_ASSOC) ?: null;
                
                foreach ($allowedFields as $field) {
                    if (isset($draftData[$field]) && !empty($draftData[$field])) {
                        $updateFields[] = "`{$field}` = ?";
                        $updateParams[] = $draftData[$field];
                    }
                }
                
                if (!empty($updateFields)) {
                    // Apply the draft changes to the homeowners table
                    $updateParams[] = $request['homeowner_id'];
                    $updateQuery = "UPDATE homeowners SET " . implode(', ', $updateFields) . " WHERE id = ?";
                    $updateHomeownerStmt = $pdo->prepare($updateQuery);
                    $updateHomeownerStmt->execute($updateParams);

                        $newHomeownerValues = array_merge($oldHomeownerValues ?: [], array_intersect_key($draftData, array_flip($allowedFields)));
                }
            }
        }

        // Update the request status
        $updateStmt = $pdo->prepare("
            UPDATE profile_update_requests 
            SET status = ?, admin_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$action, $adminNotes ?: null, $requestId]);

        // Log the action to audit logs
            try {
                AuditLogger::logDataChange('profile_request_' . $action, 'profile_update_requests', (int)$requestId, [
                    'homeowner_id' => $request['homeowner_id'],
                    'status' => $request['status'],
                    'admin_notes' => $request['admin_notes'] ?? null,
                ], [
                    'homeowner_id' => $request['homeowner_id'],
                    'status' => $action,
                    'admin_notes' => $adminNotes ?: null,
                    'draft_applied' => ($action === 'completed' && !empty($request['draft_payload'])),
                ]);

                if ($oldHomeownerValues && $newHomeownerValues) {
                    AuditLogger::logDataChange('profile_request_homeowner_update', 'homeowners', (int)$request['homeowner_id'], $oldHomeownerValues, $newHomeownerValues);
                }
            } catch (Exception $auditError) {
                error_log('Profile request audit error: ' . $auditError->getMessage());
            }

    $pdo->commit();

    $actionLabels = [
        'acknowledged' => 'acknowledged',
        'completed' => 'marked as completed',
        'rejected' => 'rejected'
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Request has been ' . ($actionLabels[$action] ?? $action)
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Profile request action error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
