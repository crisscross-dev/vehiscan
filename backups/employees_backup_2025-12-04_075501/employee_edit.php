<?php
/**
 * Employee Edit
 * Edit employee details and role
 */

// Check Super Admin or Admin session
session_name('vehiscan_superadmin');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    session_write_close();
    session_name('vehiscan_admin');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/audit_logger.php';

// Initialize audit logger
try {
    AuditLogger::init($pdo);
} catch (Exception $e) {
    // Audit logger not available
}

$employeeId = $_GET['id'] ?? null;

if (!$employeeId) {
    header("Location: employee_list.php");
    exit();
}

// Fetch employee details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$employeeId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employee) {
    header("Location: employee_list.php?error=not_found");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $posted_csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($csrf, (string)$posted_csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
    $role = $_POST['role'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $reset_password = isset($_POST['reset_password']);
    
    // Store old values for audit
    $oldValues = [
        'role' => $employee['role']
    ];
    
    // Validation
    if (empty($role)) {
        $error = "Role is required.";
    } elseif (!in_array($role, ['admin', 'guard', 'owner'])) {
        $error = "Invalid role selected.";
    } elseif ($reset_password && strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        // Update employee
        if ($reset_password && $new_password) {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET role = ?, password = ? WHERE id = ?");
            $success = $stmt->execute([$role, $password_hash, $employeeId]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $success = $stmt->execute([$role, $employeeId]);
        }
        
        if ($success) {
            $newValues = [
                'role' => $role
            ];
            
            if ($reset_password) {
                $newValues['password'] = 'RESET';
            }
            
            // Log to audit system
            try {
                AuditLogger::logDataChange('update', 'users', $employeeId, $oldValues, $newValues);
            } catch (Exception $e) {
                // Audit logger not available
            }
            
            $success = "Employee updated successfully!";
            
            // Refresh employee data
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$employeeId]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Failed to update employee.";
        }
    }
    }
}

$isSuperAdmin = ($_SESSION['role'] === 'super_admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - VehiScan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Include sidebar from admin panel -->
    <?php include __DIR__ . '/components/sidebar.php'; ?>
    
    <div class="ml-64 p-8">
        <div class="max-w-3xl mx-auto">
            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Edit Employee</h1>
                        <p class="text-gray-600 mt-2">Update employee details and role</p>
                    </div>
                    <a href="employee_list.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to List
                    </a>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <form method="POST" id="employeeForm">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <!-- Username (Read-only) -->
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                        <input type="text" value="<?= htmlspecialchars($employee['username']) ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100" 
                               disabled>
                        <p class="text-sm text-gray-500 mt-1">Username cannot be changed</p>
                    </div>

                    <!-- Role Selection -->
                    <div class="mb-6">
                        <label for="role" class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                        <select id="role" name="role" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="admin" <?= $employee['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="guard" <?= $employee['role'] === 'guard' ? 'selected' : '' ?>>Guard</option>
                            <option value="owner" <?= $employee['role'] === 'owner' ? 'selected' : '' ?>>Homeowner</option>
                        </select>
                    </div>

                    <!-- Password Reset Section -->
                    <div class="mb-8 p-6 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <div class="flex items-start mb-4">
                            <input type="checkbox" id="reset_password" name="reset_password" class="mt-1 mr-3 w-4 h-4 text-blue-600">
                            <div>
                                <label for="reset_password" class="block text-sm font-semibold text-gray-900 cursor-pointer">Reset Password</label>
                                <p class="text-sm text-gray-600">Check this box to set a new password for this employee</p>
                            </div>
                        </div>
                        <div id="passwordField" class="hidden">
                            <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                                   placeholder="Enter new password (min 8 characters)">
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end space-x-4">
                        <a href="employee_list.php" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg hover:from-blue-600 hover:to-indigo-700 transition-all shadow-lg font-medium">
                            Update Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
        <?php if ($success): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?= $success ?>',
                confirmButtonColor: '#3b82f6'
            });
        <?php endif; ?>

        <?php if ($error): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= $error ?>',
                confirmButtonColor: '#3b82f6'
            });
        <?php endif; ?>

        // Toggle password field
        document.getElementById('reset_password').addEventListener('change', function() {
            document.getElementById('passwordField').classList.toggle('hidden', !this.checked);
            document.getElementById('new_password').required = this.checked;
        });
    </script>
</body>
</html>
