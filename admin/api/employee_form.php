<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/input_sanitizer.php';

$id = InputSanitizer::get('id', 'int');
$employee = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
}

$isEdit = !empty($employee);
?>

<div class="p-2 md:p-3">
    <h3 class="text-2xl font-bold text-gray-900 mb-6"><?= $isEdit ? 'Edit Employee' : 'Create New Employee' ?></h3>

    <form id="employeeForm" class="space-y-6" action="api/employee_save.php" method="POST">
        <input type="hidden" name="id" value="<?= $employee['id'] ?? '' ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
        
        <!-- Username -->
        <div>
            <label for="employee_username" class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
            <input type="text" 
                   id="employee_username"
                   name="username" 
                   value="<?= htmlspecialchars($employee['username'] ?? '') ?>" 
                   class="ta-input" 
                   placeholder="Enter username (min 3 characters)"
                   autocomplete="username"
                   required <?= $isEdit ? 'readonly' : '' ?>>
            <?php if ($isEdit): ?>
            <p class="text-sm text-gray-500 mt-1">Username cannot be changed</p>
            <?php endif; ?>
        </div>
        
        <!-- Role Selection -->
        <div>
            <label for="employee_role" class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
            <select id="employee_role" name="role" class="ta-select" autocomplete="off" required>
                <option value="">Select role...</option>
                <option value="admin" <?= ($employee['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="guard" <?= ($employee['role'] ?? '') === 'guard' ? 'selected' : '' ?>>Guard</option>
                <option value="homeowner" <?= in_array(($employee['role'] ?? ''), ['homeowner', 'owner'], true) ? 'selected' : '' ?>>Homeowner</option>
            </select>
            <p class="text-sm text-gray-500 mt-2">
                <strong>Admin:</strong> Full access • <strong>Guard:</strong> Guard panel • <strong>Homeowner:</strong> Registration only
            </p>
        </div>
        
        <?php if (!$isEdit): ?>
        <!-- Password (Create Only) -->
        <div>
            <label for="employee_password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
            <input type="password" 
                   id="employee_password"
                   name="password" 
                   class="ta-input" 
                   placeholder="Enter password (min 12 characters)"
                   autocomplete="new-password"
                   required minlength="12">
        </div>
        
                <div>
            <label for="employee_confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
            <input type="password" 
                   id="employee_confirm_password"
                   name="confirm_password" 
                                     class="ta-input" 
                   placeholder="Confirm password"
                   autocomplete="new-password"
                     required minlength="12">
        </div>
        
        <?php else: ?>
        <!-- Password Reset Section (Edit Only) -->
        <div class="p-6 bg-yellow-50 border border-yellow-200 rounded-lg">
            <div class="flex items-start mb-4">
                <input type="checkbox" id="reset_password" name="reset_password" class="mt-1 mr-3 w-4 h-4 text-blue-600">
                <div>
                    <label for="reset_password" class="block text-sm font-semibold text-gray-900 cursor-pointer">Reset Password</label>
                    <p class="text-sm text-gray-600">Check this box to set a new password for this employee</p>
                </div>
            </div>
            <div id="passwordField" class="hidden space-y-4">
                <div>
                    <label for="new_password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                    <input type="password" id="new_password" name="new_password" 
                           class="ta-input" 
                           placeholder="Enter new password (min 12 characters)"
                           minlength="12">
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Submit Buttons -->
        <div class="form-actions">
            <button type="button" data-action="closeModal" class="ta-btn ta-btn-secondary cancel-btn">
                Cancel
            </button>
            <button type="submit" class="ta-btn ta-btn-primary">
                <?= $isEdit ? 'Update Employee' : 'Create Employee' ?>
            </button>
        </div>
    </form>
</div>

<script nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
<?php if ($isEdit): ?>
<script src="../../assets/js/admin/employee-form.js?v=<?php echo safeFileTime(__DIR__ . '/../../assets/js/admin/employee-form.js'); ?>"></script>
<?php endif; ?>
