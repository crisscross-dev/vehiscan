<?php
/**
 * Delete (deactivate) vehicle
 */
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_homeowner.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['homeowner_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'error' => 'Unauthorized']);
    exit();
}

requireRequestMethod('POST');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $vehicleId = isset($data['vehicle_id']) ? (int)$data['vehicle_id'] : 0;

    // Validate CSRF token
    $csrfToken = $data['csrf_token'] ?? '';
    if (!InputSanitizer::validateCsrf((string)$csrfToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token', 'error' => 'Invalid CSRF token']);
        exit();
    }

    if ($vehicleId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Vehicle ID required', 'error' => 'Vehicle ID required']);
        exit();
    }

    $confirmation = strtoupper(trim((string)($data['confirmation'] ?? '')));
    if ($confirmation !== 'DELETE') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Confirmation text is required', 'error' => 'Confirmation text is required']);
        exit();
    }



    // Verify ownership and check if it's the only vehicle
    $stmt = $pdo->prepare("\n        SELECT COUNT(*) as total
        FROM vehicles
        WHERE homeowner_id = ? AND is_active = 1
    ");
    $stmt->execute([$_SESSION['homeowner_id']]);
    $result = $stmt->fetch();

    if (($result['total'] ?? 0) <= 1) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cannot delete your only vehicle. Please add another vehicle first.', 'error' => 'Cannot delete your only vehicle. Please add another vehicle first.']);
        exit();
    }

    // Validate target vehicle belongs to homeowner and is active
    $stmt = $pdo->prepare("
        SELECT id
        FROM vehicles
        WHERE id = ? AND homeowner_id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$vehicleId, $_SESSION['homeowner_id']]);
    if (!$stmt->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Vehicle not found or access denied', 'error' => 'Vehicle not found or access denied']);
        exit();
    }

    $pdo->beginTransaction();

    // Soft delete and clear primary flag on the removed vehicle.
    $stmt = $pdo->prepare("
        UPDATE vehicles
        SET is_active = 0, is_primary = 0
        WHERE id = ? AND homeowner_id = ?
    ");
    $stmt->execute([$vehicleId, $_SESSION['homeowner_id']]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Vehicle not found or access denied', 'error' => 'Vehicle not found or access denied']);
        exit();
    }

    // Keep one active primary vehicle for consistent behavior.
    $stmt = $pdo->prepare("
        SELECT id
        FROM vehicles
        WHERE homeowner_id = ? AND is_active = 1 AND is_primary = 1
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['homeowner_id']]);
    $currentPrimaryId = (int)($stmt->fetchColumn() ?: 0);

    if ($currentPrimaryId <= 0) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM vehicles
            WHERE homeowner_id = ? AND is_active = 1
            ORDER BY registered_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['homeowner_id']]);
        $newPrimaryId = (int)($stmt->fetchColumn() ?: 0);

        if ($newPrimaryId > 0) {
            $pdo->prepare("UPDATE vehicles SET is_primary = FALSE WHERE homeowner_id = ? AND is_active = 1")
                ->execute([$_SESSION['homeowner_id']]);
            $pdo->prepare("UPDATE vehicles SET is_primary = TRUE WHERE id = ? AND homeowner_id = ?")
                ->execute([$newPrimaryId, $_SESSION['homeowner_id']]);
            $currentPrimaryId = $newPrimaryId;
        }
    }

    // Sync homeowners table with the current primary vehicle details
    if ($currentPrimaryId > 0) {
        $stmt = $pdo->prepare("SELECT plate_number, vehicle_type, color FROM vehicles WHERE id = ?");
        $stmt->execute([$currentPrimaryId]);
        $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($vehicle) {
            $updateHomeowner = $pdo->prepare("
                UPDATE homeowners 
                SET plate_number = ?, vehicle_type = ?, color = ? 
                WHERE id = ?
            ");
            $updateHomeowner->execute([
                $vehicle['plate_number'],
                $vehicle['vehicle_type'],
                $vehicle['color'],
                $_SESSION['homeowner_id']
            ]);
        }
    }



    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Vehicle removed successfully'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete vehicle error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete vehicle. Please try again later.',
        'error' => 'Failed to delete vehicle. Please try again later.'
    ]);
}
