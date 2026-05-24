<?php
require_once __DIR__ . '/../includes/session_admin_unified.php';
require_once __DIR__ . '/../includes/security_headers.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

require_once '../db.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $posted_csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf, (string)$posted_csrf)) {
        $message = 'Invalid security token. Please refresh and try again.';
    } else {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    if ($role === 'owner') {
        $role = 'homeowner';
    }
    $allowedRoles = ['admin', 'guard', 'homeowner', 'super_admin'];

    if (!$username || !$password || !$role) {
        $message = "All fields required.";
    } elseif (!in_array($role, $allowedRoles, true)) {
        $message = "Invalid role selected.";
    } elseif ($role === 'super_admin' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || $fullName === '')) {
        $message = "Super Admin requires valid email and full name.";
    } elseif (strlen($password) < (defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12)) {
        $minLen = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12;
        $message = "Password must be at least {$minLen} characters long.";
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $message = "Password must contain uppercase, lowercase, and a number.";
    } else {
        if ($role === 'super_admin') {
            $check = $pdo->prepare("SELECT id FROM super_admin WHERE username = ? OR email = ? LIMIT 1");
            $check->execute([$username, $email]);
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $check->execute([$username]);
        }

        if ($check->fetch()) {
            $message = $role === 'super_admin' ? "Username or email already exists." : "Username already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if ($role === 'super_admin') {
                $stmt = $pdo->prepare("INSERT INTO super_admin (username, email, full_name, password_hash, password_changed_at, is_setup_complete) VALUES (?, ?, ?, ?, NOW(), 1)");
                $stmt->execute([$username, $email, $fullName, $hash]);
                $message = "Super Admin account created successfully.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username,password,role) VALUES (?,?,?)");
                $stmt->execute([$username,$hash,$role]);
                $message = "Account created successfully.";
            }
        }
    }
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Create Admin</title>
<style>
body{font-family:Segoe UI;background:#f4f6f8;display:flex;justify-content:center;align-items:center;height:100vh;}
.container{background:white;padding:25px;border-radius:10px;box-shadow:0 4px 15px rgba(0,0,0,0.1);width:360px;}
input,select,button{width:100%;padding:10px;margin-top:10px;border-radius:6px;border:1px solid #ccc;}
button{background:#3498db;color:white;border:none;cursor:pointer;}
button:hover{background:#2980b9;}
.message{text-align:center;margin-top:10px;}
</style></head>
<body>
<div class="container">
<h2>Create Account</h2>
<?php if($message) echo "<p class='message'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
<form method="POST">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
  <input name="username" placeholder="Username" required>
  <input type="password" name="password" placeholder="Password" required>
  <select name="role" required>
    <option value="">Select Role</option>
    <option value="admin">Admin</option>
    <option value="guard">Guard</option>
        <option value="homeowner">Homeowner</option>
        <option value="super_admin">Super Admin</option>
  </select>
    <div id="superAdminFields" style="display:none;">
        <input name="full_name" placeholder="Full Name (Super Admin only)">
        <input type="email" name="email" placeholder="Email (Super Admin only)">
    </div>
  <button type="submit">Create</button>
  <button type="button" onclick="location.href='../admin/admin_panel.php'">Cancel</button>
</form>
</div>
<script>
    (function () {
        var roleSelect = document.querySelector('select[name="role"]');
        var superAdminFields = document.getElementById('superAdminFields');
        if (!roleSelect || !superAdminFields) return;

        function syncSuperAdminFields() {
            var isSuperAdmin = roleSelect.value === 'super_admin';
            superAdminFields.style.display = isSuperAdmin ? 'block' : 'none';
            var fullNameInput = superAdminFields.querySelector('input[name="full_name"]');
            var emailInput = superAdminFields.querySelector('input[name="email"]');
            if (fullNameInput) fullNameInput.required = isSuperAdmin;
            if (emailInput) emailInput.required = isSuperAdmin;
        }

        roleSelect.addEventListener('change', syncSuperAdminFields);
        syncSuperAdminFields();
    })();
</script>
</body></html>
