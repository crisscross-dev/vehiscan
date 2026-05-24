<?php
/**
 * Forgot Password Page
 * Accepts email, sends password reset link
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

$message = '';
$messageType = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!InputSanitizer::validateCsrf((string)$submittedToken)) {
        $message = 'Invalid form submission. Please try again.';
        $messageType = 'error';
    } else {
        $email = trim($_POST['email'] ?? '');

        // Rate limit (IP-based, on top of the token-based limit in the handler)
        require_once __DIR__ . '/../includes/rate_limiter.php';
        $rateLimiter = new RateLimiter($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateCheck = $rateLimiter->check($ip, 'password_reset_page', 5, 15);

        if (!$rateCheck['allowed']) {
            $message = 'Too many attempts. Please try again in a few minutes.';
            $messageType = 'error';
        } else {
            $rateLimiter->recordAttempt($ip, 'password_reset_page'); // count every attempt

            $handler = new PasswordResetHandler($pdo);
            $result = $handler->requestReset($email);

            $message = $result['message'];
            $messageType = 'success'; // Always show success to prevent enumeration
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
    <title>Forgot Password — VehiScan</title>
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
                <h1 class="text-xl font-bold" style="color: var(--auth-text);">Forgot Password</h1>
                <p class="text-sm mt-1" style="color: var(--auth-text-secondary);">Enter your email to receive a reset link</p>
            </div>

            <?php if ($message): ?>
                <div class="mx-6 mb-4 ta-alert <?php echo $messageType === 'success' ? 'ta-alert-success' : 'ta-alert-danger'; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $messageType === 'success' ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; ?>"/></svg>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" class="px-6 pb-6 space-y-4" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="ta-form-group" style="margin-bottom:0;">
                    <label for="email" class="ta-label" style="color: var(--auth-text);">Email Address <span class="text-red-500">*</span></label>
                    <div class="ta-input-wrapper">
                        <span class="ta-input-icon">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </span>
                        <input id="email" name="email" type="email" class="ta-input" placeholder="your@email.com" required autocomplete="email"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                            style="background: var(--auth-input-bg); border-color: var(--auth-input-border); color: var(--auth-text); padding-left: 2.5rem;">
                    </div>
                </div>

                <button type="submit" id="submitBtn"
                    class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg font-medium text-sm text-white transition-all hover:opacity-90"
                    style="background: var(--auth-accent);">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>Send Reset Link</span>
                </button>
            </form>

            <div class="px-6 pb-6 text-center text-sm" style="color: var(--auth-text-secondary);">
                Remember your password? <a href="login.php" class="font-medium hover:underline" style="color: var(--auth-accent);">Sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
