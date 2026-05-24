<?php
/**
 * Reset Password Page
 * Validates token from URL, allows user to set a new password
 */
ob_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/password_reset.php';
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/input_sanitizer.php';

if (session_status() === PHP_SESSION_NONE) {
    $appSavePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vehiscan_sessions';
    if (!is_dir($appSavePath)) { mkdir($appSavePath, 0700, true); }
    ini_set('session.save_path', $appSavePath);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$tokenValid = false;
$resetComplete = false;

$handler = new PasswordResetHandler($pdo);

// Validate token on page load
if ($token) {
    $validation = $handler->validateToken($token);
    $tokenValid = $validation['valid'];
    if (!$tokenValid) {
        $message = $validation['error'];
        $messageType = 'error';
    }
} else {
    $message = 'No reset token provided. Please use the link from your email.';
    $messageType = 'error';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!InputSanitizer::validateCsrf((string)$submittedToken)) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $minLen = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12;
        if (strlen($newPassword) < $minLen) {
            $message = "Password must be at least $minLen characters long.";
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
        } else {
            $result = $handler->resetPassword($token, $newPassword);
            $message = $result['message'];
            $messageType = $result['success'] ? 'success' : 'error';
            if ($result['success']) {
                $resetComplete = true;
                $tokenValid = false; // Hide form
            }
        }
    }
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — VehiScan</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="../assets/css/tailadmin-components.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/tailadmin-components.css'); ?>">
    <link rel="stylesheet" href="../assets/css/system.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/system.css'); ?>">
    <script src="../assets/js/libs/sweetalert2.all.min.js"></script>
    <style>
      body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
      @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
      .auth-animate { animation: fadeInUp 0.4s ease-out; }
      .grid-bg { background-image: radial-gradient(circle, #e2e8f0 1px, transparent 1px); background-size: 24px 24px; }
      @media (prefers-color-scheme: dark) { .grid-bg { background-image: radial-gradient(circle, #334155 1px, transparent 1px); } }
    </style>
</head>
<body class="auth-page min-h-screen flex items-center justify-center" style="background: var(--auth-bg);">
    <div class="fixed inset-0 grid-bg opacity-40 pointer-events-none"></div>

    <div class="relative z-10 w-full max-w-md px-4 auth-animate">
        <div class="rounded-xl border shadow-lg overflow-hidden" style="background: var(--auth-card-bg); border-color: var(--auth-card-border);">
            <div class="flex flex-col items-center pt-8 pb-4 px-6">
                <img src="../assets/images/ville_de_palme.png" alt="VehiScan Logo" class="h-16 w-auto mb-4 object-contain">
                <h1 class="text-xl font-bold" style="color: var(--auth-text);">Reset Password</h1>
                <p class="text-sm mt-1" style="color: var(--auth-text-secondary);"><?php echo $tokenValid ? 'Enter your new password below' : ($resetComplete ? 'Password updated' : 'Reset link validation'); ?></p>
            </div>

            <?php if ($message): ?>
                <div class="mx-6 mb-4 ta-alert <?php echo $messageType === 'success' ? 'ta-alert-success' : 'ta-alert-danger'; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $messageType === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; ?>"/></svg>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($tokenValid): ?>
            <form method="POST" class="px-6 pb-6 space-y-4" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="ta-form-group" style="margin-bottom:0;">
                    <label for="password" class="ta-label" style="color: var(--auth-text);">New Password <span class="text-red-500">*</span></label>
                    <div class="ta-input-wrapper relative">
                        <span class="ta-input-icon">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </span>
                        <input id="password" name="password" type="password" class="ta-input" placeholder="Enter new password" required
                            minlength="<?= defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12 ?>" autocomplete="new-password"
                            style="background: var(--auth-input-bg); border-color: var(--auth-input-border); color: var(--auth-text); padding-left: 2.5rem;">
                    </div>
                    <div class="ta-password-meter mt-2" id="pwMeter" data-strength="">
                        <div class="ta-meter-bar"></div>
                        <div class="ta-meter-bar"></div>
                        <div class="ta-meter-bar"></div>
                    </div>
                    <p class="ta-password-label" id="pwLabel"></p>
                </div>

                <div class="ta-form-group" style="margin-bottom:0;">
                    <label for="confirm_password" class="ta-label" style="color: var(--auth-text);">Confirm Password <span class="text-red-500">*</span></label>
                    <div class="ta-input-wrapper">
                        <span class="ta-input-icon">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </span>
                        <input id="confirm_password" name="confirm_password" type="password" class="ta-input" placeholder="Confirm new password" required
                            minlength="<?= defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12 ?>" autocomplete="new-password"
                            style="background: var(--auth-input-bg); border-color: var(--auth-input-border); color: var(--auth-text); padding-left: 2.5rem;">
                    </div>
                </div>

                <p class="text-xs" style="color: var(--auth-text-secondary);">Password must be at least <?= defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12 ?> characters.</p>

                <button type="submit" id="submitBtn"
                    class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg font-medium text-sm text-white transition-all hover:opacity-90"
                    style="background: var(--auth-accent);">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    <span>Reset Password</span>
                </button>
            </form>
            <?php elseif ($resetComplete): ?>
            <div class="px-6 pb-6 text-center">
                <a href="login.php" class="ta-btn ta-btn-primary w-full justify-center">Sign in Now</a>
            </div>
            <?php else: ?>
            <div class="px-6 pb-6 text-center">
                <a href="forgot-password.php" class="font-medium text-sm hover:underline" style="color: var(--auth-accent);">Request a new reset link</a>
            </div>
            <?php endif; ?>

            <div class="px-6 pb-6 text-center text-sm" style="color: var(--auth-text-secondary);">
                <a href="login.php" class="font-medium hover:underline" style="color: var(--auth-accent);">Back to Sign in</a>
            </div>
        </div>
    </div>

    <script>
    // Password strength meter
    const minPasswordLength = <?= (int) (defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 12) ?>;
    const pwInput = document.getElementById('password');
    if (pwInput) {
        pwInput.addEventListener('input', function() {
            const val = this.value;
            const meter = document.getElementById('pwMeter');
            const label = document.getElementById('pwLabel');
            let strength = '';
            if (val.length === 0) { strength = ''; label.textContent = ''; }
            else if (val.length < minPasswordLength) { strength = 'weak'; label.textContent = 'Weak'; label.className = 'ta-password-label weak'; }
            else if (!/[A-Z]/.test(val) || !/[0-9]/.test(val)) { strength = 'fair'; label.textContent = 'Fair'; label.className = 'ta-password-label fair'; }
            else { strength = 'strong'; label.textContent = 'Strong'; label.className = 'ta-password-label strong'; }
            meter.dataset.strength = strength;
        });
    }
    </script>
</body>
</html>
