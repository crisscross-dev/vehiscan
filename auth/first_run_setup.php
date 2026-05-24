<?php
/**
 * First-Run Setup Wizard
 * Creates the initial Super Admin account
 * Only accessible when system is not yet installed
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/security_headers.php';

// Check if already installed
try {
    $stmt = $pdo->query("SELECT is_installed FROM system_installation WHERE id = 1");
    $installation = $stmt->fetch();
    
    if ($installation && $installation['is_installed']) {
        header('Location: login.php?error=already_installed');
        exit();
    }
} catch (PDOException $e) {
    // Table doesn't exist yet - need to run migrations first
    header('Location: ../scripts/migrate.php');
    exit();
}

$errors = [];
$success = false;

// Session + CSRF for setup form
if (session_status() === PHP_SESSION_NONE) {
    $appSavePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vehiscan_sessions';
    if (!is_dir($appSavePath)) { mkdir($appSavePath, 0700, true); }
    ini_set('session.save_path', $appSavePath);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}
if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}
$setupCsrf = $_SESSION['setup_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($setupCsrf, $postedCsrf)) {
        $errors[] = 'Invalid security token. Please refresh and try again.';
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($fullName)) {
        $errors[] = "Full name is required";
    }
    
    // Password validation
    if (empty($password)) {
        $errors[] = "Password is required";
    } else {
        if (strlen($password) < 12) {
            $errors[] = "Password must be at least 12 characters";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, create super admin
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Hash password
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert super admin
            $stmt = $pdo->prepare("
                INSERT INTO super_admin (username, email, full_name, password_hash, password_changed_at, is_setup_complete) 
                VALUES (?, ?, ?, ?, NOW(), 1)
            ");
            $stmt->execute([$username, $email, $fullName, $passwordHash]);
            
            // Mark system as installed
            $stmt = $pdo->prepare("
                UPDATE system_installation 
                SET is_installed = 1, installed_at = NOW(), installed_by = ?, version = '2.0.0' 
                WHERE id = 1
            ");
            $stmt->execute([$username]);
            
            $pdo->commit();
            
            $success = true;
            
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php?setup=complete");
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            
            if ($e->getCode() == 23000) {
                $errors[] = "Username or email already exists";
            } else {
                error_log('[SETUP] Database error: ' . $e->getMessage());
                $errors[] = "An unexpected database error occurred. Please check the server logs.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>First-Run Setup - VehiScan RFID</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .logo img {
            width: 60px;
            height: 60px;
            object-fit: contain;
        }
        .setup-header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .setup-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        .setup-content {
            padding: 2rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2d3748;
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .hint {
            font-size: 0.8rem;
            color: #718096;
            margin-top: 0.25rem;
        }
        .error-list {
            background: #fff5f5;
            border-left: 4px solid #e53e3e;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        .error-list ul {
            margin-left: 1.5rem;
            color: #742a2a;
        }
        .error-list li {
            margin-bottom: 0.5rem;
        }
        .success-message {
            background: #f0fff4;
            border-left: 4px solid #38a169;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            color: #22543d;
            font-weight: 600;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .password-requirements {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        .password-requirements h4 {
            margin-bottom: 0.5rem;
            color: #2d3748;
            font-size: 0.9rem;
        }
        .password-requirements ul {
            margin-left: 1.5rem;
            color: #4a5568;
        }
        .password-requirements li {
            margin-bottom: 0.25rem;
        }
        .security-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <div class="logo">
                <img src="../assets/images/vehiscan-logo.png" alt="VehiScan" onerror="this.parentElement.innerHTML='<svg style=\'width:2rem;height:2rem;display:inline\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'currentColor\' stroke-width=\'2\'><path d=\'M5 17h14M6 17l1-5h10l1 5M8 12l1-4h6l1 4\'/><circle cx=\'7.5\' cy=\'17\' r=\'1.5\'/><circle cx=\'16.5\' cy=\'17\' r=\'1.5\'/></svg>'">
            </div>
            <h1><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M5 12h14M12 5l7 7-7 7'/></svg> Welcome to VehiScan</h1>
            <p>Let's set up your Super Admin account</p>
        </div>
        
        <div class="setup-content">
            <?php if ($success): ?>
                <div class="success-message">
                    <svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><circle cx='12' cy='12' r='10'/><path d='m9 12 2 2 4-4'/></svg> Super Admin account created successfully!<br>
                    <small>Redirecting to login page...</small>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="error-list">
                        <strong><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z'/><line x1='12' y1='9' x2='12' y2='13'/><line x1='12' y1='17' x2='12.01' y2='17'/></svg> Please fix the following errors:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($setupCsrf); ?>">
                    <div class="form-group">
                        <label for="username"><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'/><circle cx='12' cy='7' r='4'/></svg> Username</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores">
                        <div class="hint">Minimum 3 characters. Letters, numbers, and underscores only.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><rect x='2' y='4' width='20' height='16' rx='2'/><path d='m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7'/></svg> Email Address</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <div class="hint">For password recovery and system notifications.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name"><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><rect x='2' y='2' width='20' height='20' rx='2'/><path d='M7 10h10M7 14h6'/></svg> Full Name</label>
                        <input type="text" id="full_name" name="full_name" required 
                               value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        <div class="hint">Your full name as it will appear in the system.</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password"><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><rect x='3' y='11' width='18' height='11' rx='2'/><path d='M7 11V7a5 5 0 0 1 10 0v4'/></svg> Password</label>
                        <input type="password" id="password" name="password" required minlength="12">
                        <div class="password-requirements">
                            <h4>Password Requirements:</h4>
                            <ul>
                                <li>At least 12 characters</li>
                                <li>One uppercase letter (A-Z)</li>
                                <li>One lowercase letter (a-z)</li>
                                <li>One number (0-9)</li>
                                <li>One special character (!@#$%^&*)</li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password"><svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><rect x='3' y='11' width='18' height='11' rx='2'/><path d='M7 11V7a5 5 0 0 1 10 0v4'/></svg> Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="12">
                    </div>
                    
                    <button type="submit" class="btn">
                        <svg style='width:1em;height:1em;vertical-align:-0.15em;display:inline' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'><path d='M5 12h14M12 5l7 7-7 7'/></svg> Create Super Admin Account
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Real-time password validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePassword() {
            const val = password.value;
            const requirements = {
                length: val.length >= 12,
                uppercase: /[A-Z]/.test(val),
                lowercase: /[a-z]/.test(val),
                number: /[0-9]/.test(val),
                special: /[^A-Za-z0-9]/.test(val)
            };
            
            // Visual feedback (optional enhancement)
            const items = document.querySelectorAll('.password-requirements li');
            items[0].style.color = requirements.length ? '#38a169' : '#4a5568';
            items[1].style.color = requirements.uppercase ? '#38a169' : '#4a5568';
            items[2].style.color = requirements.lowercase ? '#38a169' : '#4a5568';
            items[3].style.color = requirements.number ? '#38a169' : '#4a5568';
            items[4].style.color = requirements.special ? '#38a169' : '#4a5568';
        }
        
        function validateMatch() {
            if (confirmPassword.value && password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        password.addEventListener('input', validatePassword);
        password.addEventListener('input', validateMatch);
        confirmPassword.addEventListener('input', validateMatch);
    </script>
</body>
</html>
