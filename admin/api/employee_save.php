<?php
ob_start();
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/audit_logger.php';
ob_end_clean();

header('Content-Type: application/json');

requireRequestMethod('POST');

AuditLogger::init($pdo);

// Authorization check
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // CSRF validation using InputSanitizer
    $postCsrf = InputSanitizer::post('csrf_token', 'string');
    
    if (!InputSanitizer::validateCsrf($postCsrf)) {
        throw new Exception('Invalid CSRF token', 403);
    }
    
    // Sanitize all inputs
    $id = InputSanitizer::post('id', 'int');
    $username = InputSanitizer::post('username', 'string');
    $password = InputSanitizer::post('password', 'string');
    $confirmPassword = InputSanitizer::post('confirm_password', 'string');
    $newPassword = InputSanitizer::post('new_password', 'string');
    $resetPassword = isset($_POST['reset_password']);
    $roleRaw = InputSanitizer::post('role', 'string');
    $role = $roleRaw === 'owner' ? 'homeowner' : $roleRaw;
    
    // Validation
    if (empty($username)) {
        throw new Exception('Username is required', 400);
    }
    
    if (strlen($username) < 3) {
        throw new Exception('Username must be at least 3 characters', 400);
    }
    
    if (empty($role)) {
        throw new Exception('Role is required', 400);
    }
    
    if (!in_array($role, ['admin', 'guard', 'homeowner'])) {
        throw new Exception('Invalid role', 400);
    }

    if ($role === 'admin' && ($_SESSION['role'] ?? '') !== 'super_admin') {
        throw new Exception('Only Super Admin can assign Admin role', 403);
    }
    
    if ($id) {
        $targetStmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ? LIMIT 1");
        $targetStmt->execute([$id]);
        $targetEmployee = $targetStmt->fetch(PDO::FETCH_ASSOC);
        if (!$targetEmployee) {
            throw new Exception('Employee not found', 404);
        }

        if (($targetEmployee['role'] ?? '') === 'super_admin' && ($_SESSION['role'] ?? '') !== 'super_admin') {
            throw new Exception('Only Super Admin can modify Super Admin accounts', 403);
        }

        if (($targetEmployee['role'] ?? '') === 'super_admin') {
            throw new Exception('Super Admin accounts are managed separately', 403);
        }

        // Update existing employee
        $passwordUpdate = false;
        $hashedPassword = null;
        
        // Check if password reset is requested (edit mode)
        if ($resetPassword && empty($newPassword)) {
            throw new Exception('New password is required when resetting password', 400);
        }

        if ($resetPassword && !empty($newPassword)) {
            if (strlen($newPassword) < 12) {
                throw new Exception('Password must be at least 12 characters', 400);
            }
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $passwordUpdate = true;
        }
        
        // Prevent self-demotion
        if (($_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null) == $id && $_SESSION['role'] !== $role) {
            throw new Exception('You cannot change your own role', 403);
        }
        
        if ($passwordUpdate) {
            $stmt = $pdo->prepare("UPDATE users SET role = ?, password = ? WHERE id = ?");
            $success = $stmt->execute([$role, $hashedPassword, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $success = $stmt->execute([$role, $id]);
        }
        
        try {
            AuditLogger::logDataChange('employee_update', 'users', (int)$id, [
                'username' => $targetEmployee['username'] ?? $username,
                'role' => $targetEmployee['role'] ?? null,
            ], [
                'username' => $targetEmployee['username'] ?? $username,
                'role' => $role,
                'password_reset' => $passwordUpdate,
            ]);
        } catch (Exception $e) {
            // Silently continue if audit logging fails
        }
        
        echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
        
    } else {
        // Create new employee
        if (empty($password)) {
            throw new Exception('Password is required for new employee', 400);
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('Passwords do not match', 400);
        }
        
        if (strlen($password) < 12) {
            throw new Exception('Password must be at least 12 characters', 400);
        }
        
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new Exception('Username already exists', 409);
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $hashedPassword, $role]);
        
        $newId = $pdo->lastInsertId();
        
        try {
            AuditLogger::logDataChange('employee_create', 'users', (int)$newId, null, [
                'username' => $username,
                'role' => $role,
            ]);
        } catch (Exception $e) {
            // Silently continue if audit logging fails
        }
        
        echo json_encode(['success' => true, 'message' => 'Employee created successfully']);
    }
    
} catch (PDOException $e) {
    error_log('Employee save DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again later.']);
} catch (Exception $e) {
    error_log('Employee save error: ' . $e->getMessage());
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
