<?php
// admin/homeowners/homeowner_delete.php
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';

header('Content-Type: application/json');

requireRequestMethod('POST');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'], true)) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

if (($_SESSION['role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only Super Admin can delete homeowner records']);
    exit;
}

require_once __DIR__ . '/../../db.php';

$csrf = $_SESSION['csrf_token'] ?? '';
$posted = $_POST['csrf_token'] ?? '';
if (!InputSanitizer::validateCsrf((string)$posted)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit;
}
$confirmation = strtoupper(trim((string)($_POST['confirmation'] ?? '')));
if ($confirmation !== 'DELETE') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Confirmation text is required']); exit;
}
$id = intval($_POST['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Invalid ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Get all vehicle IDs for this homeowner to clear related records
    $stmtGetVehicles = $pdo->prepare("SELECT id FROM vehicles WHERE homeowner_id = ?");
    $stmtGetVehicles->execute([$id]);
    $vehicleIds = $stmtGetVehicles->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($vehicleIds)) {
        $placeholders = str_repeat('?,', count($vehicleIds) - 1) . '?';
        
        // Delete related rfid_binding_sessions
        $stmtBinding = $pdo->prepare("DELETE FROM rfid_binding_sessions WHERE target_type = 'vehicle' AND target_id IN ($placeholders)");
        $stmtBinding->execute($vehicleIds);
        
        // Delete associated vehicles
        $stmtVehicles = $pdo->prepare("DELETE FROM vehicles WHERE homeowner_id = ?");
        $stmtVehicles->execute([$id]);
    }

    // Delete visitor passes
    $stmtPasses = $pdo->prepare("DELETE FROM visitor_passes WHERE homeowner_id = ?");
    $stmtPasses->execute([$id]);

    // Delete profile update requests
    $stmtProfileReq = $pdo->prepare("DELETE FROM profile_update_requests WHERE homeowner_id = ?");
    $stmtProfileReq->execute([$id]);

    // Delete the homeowner
    $stmt = $pdo->prepare("DELETE FROM homeowners WHERE id = ?");
    $ok = $stmt->execute([$id]);

    $pdo->commit();
    if ($ok) {
        echo json_encode(['success' => true, 'message' => "Deleted homeowner #{$id} and all associated records"]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Delete failed']);
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[HOMEOWNER_DELETE] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'A database error occurred. Please try again.']);
}
