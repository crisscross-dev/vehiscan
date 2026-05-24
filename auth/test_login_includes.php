<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing login.php includes...\n\n";

try {
    echo "1. Loading security_headers.php... ";
    require_once __DIR__ . '/../includes/security_headers.php';
    echo "OK\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "2. Loading config.php... ";
    require_once __DIR__ . '/../config.php';
    echo "OK\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "3. Loading db.php... ";
    require_once __DIR__ . '/../db.php';
    echo "OK\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "4. Loading audit_logger.php... ";
    require_once __DIR__ . '/../includes/audit_logger.php';
    echo "OK\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "5. Loading rate_limiter.php... ";
    require_once __DIR__ . '/../includes/rate_limiter.php';
    echo "OK\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "6. Loading input_sanitizer.php... ";
    require_once __DIR__ . '/../includes/input_sanitizer.php';
    echo "OK\n";
} catch (Exception $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n[OK] All includes loaded successfully!\n";
echo "\nChecking constants:\n";
echo "MAX_LOGIN_ATTEMPTS: " . (defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 'NOT DEFINED') . "\n";
echo "LOGIN_LOCKOUT_MINUTES: " . (defined('LOGIN_LOCKOUT_MINUTES') ? LOGIN_LOCKOUT_MINUTES : 'NOT DEFINED') . "\n";
echo "\nDatabase connection: " . (isset($pdo) ? 'Connected' : 'Not connected') . "\n";
