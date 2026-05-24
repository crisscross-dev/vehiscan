<?php
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../db.php';
header('Content-Type: application/json');

// Security: Only authenticated guards can check visitor passes
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(403);
    echo json_encode(['has_pass' => false, 'error' => 'Unauthorized']);
    exit;
}

$plate = strtoupper(trim($_GET['plate'] ?? ''));

if (!$plate) {
    echo json_encode(['has_pass' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT vp.*, h.name as homeowner_name
        FROM visitor_passes vp
        JOIN homeowners h ON vp.homeowner_id = h.id
        WHERE vp.visitor_plate = ?
        AND vp.status = 'active'
        AND NOW() BETWEEN vp.valid_from AND vp.valid_until
        LIMIT 1
    ");
    $stmt->execute([$plate]);
    $pass = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pass) {
        // Return pass info without automatically marking as used
        // Only the RFID scan endpoint should mark passes as used
        echo json_encode([
            'has_pass' => true,
            'visitor_name' => $pass['visitor_name'],
            'homeowner' => $pass['homeowner_name'],
            'purpose' => $pass['purpose'],
            'valid_until' => $pass['valid_until']
        ]);
    } else {
        echo json_encode(['has_pass' => false]);
    }
} catch (Exception $e) {
    error_log("Visitor check error: " . $e->getMessage());
    echo json_encode(['has_pass' => false]);
}