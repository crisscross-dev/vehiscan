<?php
/**
 * Employee Registration
 * Super Admin can create new employees and assign roles
 */
require_once __DIR__ . '/../includes/session_admin_unified.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_once __DIR__ . '/../includes/input_sanitizer.php';

// Initialize audit logger
try {
    AuditLogger::init($pdo);
} catch (Exception $e) {
    // Audit logger not available
}

// Generate CSRF token using InputSanitizer
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = InputSanitizer::generateCsrf();
}
$csrf = $_SESSION['csrf_token'];

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token using InputSanitizer
    $posted_csrf = InputSanitizer::post('csrf_token', 'string');
    if (!InputSanitizer::validateCsrf($posted_csrf)) {
        $error = 'Invalid security token. Please refresh and try again.';
    } else {
    // Sanitize inputs
    $username = InputSanitizer::post('username', 'string');
    $password = InputSanitizer::post('password', 'string');
    $confirm_password = InputSanitizer::post('confirm_password', 'string');
    $roleRaw = InputSanitizer::post('role', 'string');
    $role = $roleRaw === 'owner' ? 'homeowner' : $roleRaw;
    
    // Validation
    if (empty($username) || empty($password) || empty($role)) {
        $error = "All fields are required.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters.";
    } elseif (strlen($password) < 12) {
        $error = "Password must be at least 12 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!in_array($role, ['admin', 'guard', 'homeowner'])) {
        $error = "Invalid role selected.";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists.";
        } else {
            // Create employee
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, created_at) VALUES (?, ?, ?, NOW())");
            
            if ($stmt->execute([$username, $password_hash, $role])) {
                $employeeId = $pdo->lastInsertId();
                $success = "Employee created successfully!";
                
                // Log to audit system
                try {
                    AuditLogger::logEmployee('employee_created', $employeeId, [
                        'new_values' => [
                            'username' => $username,
                            'role' => $role
                        ]
                    ]);
                } catch (Exception $e) {
                    // Audit logger not available
                }
                
                // Clear form
                $username = $role = '';
            } else {
                $error = "Failed to create employee.";
            }
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
    <title>Employee Registration - VehiScan</title>
    <script src="../assets/js/libs/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="../assets/css/system.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/system.css'); ?>">
    <style>
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
                        <h1 class="text-3xl font-bold text-gray-900">Employee Registration</h1>
                        <p class="text-gray-600 mt-2">Create new employee accounts and assign roles</p>
                    </div>
                    <a href="employee_list.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        View Employees
                    </a>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="bg-white rounded-2xl shadow-lg p-8">
                <form method="POST" id="employeeForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <!-- Username -->
                    <div class="mb-6">
                        <label for="username" class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                        <input type="text" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Enter username (min 3 characters)" required>
                    </div>

                    <!-- Role Selection -->
                    <div class="mb-6">
                        <label for="role" class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                        <select id="role" name="role" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="">Select role...</option>
                            <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="guard" <?= ($role ?? '') === 'guard' ? 'selected' : '' ?>>Guard</option>
                            <option value="homeowner" <?= in_array(($role ?? ''), ['homeowner', 'owner'], true) ? 'selected' : '' ?>>Homeowner</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-2">
                            <strong>Admin:</strong> Full access to system settings<br>
                            <strong>Guard:</strong> Access to guard panel and logs<br>
                            <strong>Homeowner:</strong> Access to homeowner registration
                        </p>
                    </div>

                    <!-- Password -->
                    <div class="mb-6">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input type="password" id="password" name="password" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Enter password (min 12 characters)" required minlength="12">
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-8">
                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Confirm password" required minlength="12">
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end space-x-4">
                        <button type="reset" class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                            Clear Form
                        </button>
                        <button type="submit" class="px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition-all shadow-lg font-medium">
                            Create Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
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
                confirmButtonColor: '#ef4444'
            });
        <?php endif; ?>

        // Form validation
        document.getElementById('employeeForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Passwords do not match!',
                    confirmButtonColor: '#ef4444'
                });
            }
        });
    </script>
</body>
</html>
