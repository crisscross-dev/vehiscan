<?php
/**
 * Configuration Loader
 * Loads environment variables from .env file if it exists
 * Falls back to default values for development
 */

// Choose environment file priority depending on runtime context.
// By default prefer `.env.hosting` for production deployment. When running
// locally (CLI or loopback), prefer local env files so developers can test
// without relying on remote hosting DB credentials.
$isCli = (php_sapi_name() === 'cli');
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
$httpHost = $_SERVER['HTTP_HOST'] ?? null;
$isLoopback = $remoteAddr === '127.0.0.1' || $remoteAddr === '::1' || (is_string($httpHost) && (str_starts_with($httpHost, 'localhost') || preg_match('/^\d+\.\d+\.\d+\.\d+$/', $httpHost)));

$envFiles = [];
if ($isCli || $isLoopback) {
    // Local development / test runner priority
    if (file_exists(__DIR__ . '/.env.hosting.local')) {
        $envFiles[] = __DIR__ . '/.env.hosting.local';
    }
    $envFiles[] = __DIR__ . '/.env';
    $envFiles[] = __DIR__ . '/.env.hosting';
    $envFiles[] = __DIR__ . '/.env.production';
} else {
    // Production / hosting priority
    $envFiles = [
        __DIR__ . '/.env.hosting',
        __DIR__ . '/.env.production',
        __DIR__ . '/.env',
    ];
}

$isPlaceholderEnvValue = static function (string $key, string $value): bool {
    $trimmedValue = trim($value);
    if ($trimmedValue === '') {
        return false;
    }

    $lowerValue = strtolower($trimmedValue);
    $placeholderNeedles = [
        'APP_URL' => ['your-domain.example.com', 'example.com', 'localhost', '127.0.0.1'],
        'DB_HOST' => ['127.0.0.1', 'localhost', 'example'],
        'DB_NAME' => ['vehiscan_vdp', 'example'],
        'DB_USER' => ['vehiscan_user', 'root', 'example'],
        'DB_PASS' => ['change_this_password', 'example'],
    ];

    foreach (($placeholderNeedles[$key] ?? []) as $needle) {
        if ($needle !== '' && str_contains($lowerValue, strtolower($needle))) {
            return true;
        }
    }

    return false;
};

foreach ($envFiles as $envFile) {
    if (!file_exists($envFile)) {
        continue;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        // Skip comments and malformed entries.
        if ($trimmedLine === '' || strpos($trimmedLine, '#') === 0 || strpos($trimmedLine, '=') === false) {
            continue;
        }

        // Parse KEY=VALUE.
        list($key, $value) = explode('=', $trimmedLine, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        $currentValue = getenv($key);
        $currentIsPlaceholder = $currentValue !== false && $isPlaceholderEnvValue($key, (string) $currentValue);
        // When running locally treat existing env values as authoritative so they
        // won't be clobbered by later scaffold files.
        if ($isCli || $isLoopback) {
            $currentIsPlaceholder = false;
        }
        // When running locally, consider incoming values as real (not scaffold placeholders)
        // so developers can use local defaults without them being treated as placeholders.
        if ($isCli || $isLoopback) {
            $newIsPlaceholder = false;
        } else {
            $newIsPlaceholder = $isPlaceholderEnvValue($key, $value);
        }

        // Allow later files to replace scaffold/placeholder values from earlier files,
        // but only when the new value is not itself a scaffold placeholder. This
        // prevents an intermediate placeholder file (like .env) from clobbering
        // a valid local `.env.hosting.local` value.
        if ($currentValue === false || ($currentIsPlaceholder && !$newIsPlaceholder) || (!$newIsPlaceholder && trim((string) $currentValue) === '')) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Initialize error handler
require_once __DIR__ . '/includes/error_handler.php';

// Helper function to get config values with defaults
function config($key, $default = null)
{
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// Helper function to get application URL
function getAppUrl()
{
    // Check if already set in environment
    $envUrl = config('APP_URL', null);
    if ($envUrl) {
        return rtrim($envUrl, '/');
    }

    // Auto-detect from server variables
    if (php_sapi_name() === 'cli') {
        // CLI mode - return localhost default (CLI-only fallback for development)
        return 'http://localhost';
    }

    // Web mode - detect from request
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    // Extract base path by removing file and subdirectories
    $basePath = dirname($script);
    $basePath = preg_replace('#/(admin|guard|visitor|homeowners|auth|api|pages|utilities|includes).*$#', '', $basePath);
    $basePath = rtrim($basePath, '/');

    return "$protocol://$host$basePath";
}

// Database configuration
define('DB_HOST', config('DB_HOST', 'localhost'));
define('DB_PORT', config('DB_PORT', '3306'));
define('DB_NAME', config('DB_NAME', 'vehiscan_vdp'));
define('DB_USER', config('DB_USER', 'root'));
define('DB_PASS', config('DB_PASS', ''));
define('DB_CHARSET', config('DB_CHARSET', 'utf8mb4'));

// Application settings
define('APP_ENV', config('APP_ENV', 'development'));
define('APP_DEBUG', config('APP_DEBUG', 'false') === 'true');
define('APP_TIMEZONE', config('APP_TIMEZONE', 'Asia/Manila'));

// Keep timezone consistent across public and authenticated endpoints.
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(APP_TIMEZONE);
}

// Enforce safe display_errors in production
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

// Session settings
define('SESSION_LIFETIME', (int) config('SESSION_LIFETIME', 3600));
define('SESSION_SECURE', config('SESSION_SECURE', 'false') === 'true');
define('SESSION_HTTPONLY', config('SESSION_HTTPONLY', 'true') === 'true');

// Security settings
define('CSRF_TOKEN_LENGTH', (int) config('CSRF_TOKEN_LENGTH', 32));
define('PASSWORD_MIN_LENGTH', (int) config('PASSWORD_MIN_LENGTH', 12));
define('MAX_LOGIN_ATTEMPTS', (int) config('MAX_LOGIN_ATTEMPTS', 5));
define('LOGIN_LOCKOUT_MINUTES', (int) config('LOGIN_LOCKOUT_MINUTES', 15));

// Upload settings
define('MAX_FILE_SIZE', (int) config('MAX_FILE_SIZE', 5242880)); // 5MB
define('ALLOWED_IMAGE_TYPES', config('ALLOWED_IMAGE_TYPES', 'image/jpeg,image/png,image/jpg'));

// QR Code settings
define('QR_CODE_SIZE', (int) config('QR_CODE_SIZE', 10));
define('QR_CODE_ERROR_CORRECTION', config('QR_CODE_ERROR_CORRECTION', 'L'));

/**
 * Get WiFi-aware URL for QR codes
 * Returns local WiFi IP if accessed from same network, otherwise returns hosting domain
 */
function getQrCodeUrl()
{
    // Check for forced QR URL in environment
    $envQrUrl = config('QR_BASE_URL', null);
    if ($envQrUrl) {
        return rtrim($envQrUrl, '/');
    }

    // Detect if request is from local network
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';

    // Check if accessed via local IP or local network
    $isLocalNetwork = (
        preg_match('/^192\.168\./', $remoteAddr) ||
        preg_match('/^10\./', $remoteAddr) ||
        preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $remoteAddr) ||
        $remoteAddr === '127.0.0.1' ||
        $remoteAddr === '::1'
    );

    $isLocalHost = (
        preg_match('/^192\.168\./', $httpHost) ||
        preg_match('/^10\./', $httpHost) ||
        strpos($httpHost, 'localhost') === 0 ||
        strpos($httpHost, '127.0.0.1') === 0
    );

    // If accessed from local network, use WiFi IP
    if ($isLocalNetwork || $isLocalHost) {
        $protocol = 'http'; // Always use HTTP for local network

        // Try to get WiFi IP from config
        $wifiIp = config('WIFI_IP', null);
        $port = '';

        if (!$wifiIp) {
            // Auto-detect: prioritize actual IP over localhost
            $loopbackHosts = ['localhost', '127.0.0.1', '::1'];

            if (preg_match('/^\d+\.\d+\.\d+\.\d+/', $httpHost) && !preg_match('/^127\./', $httpHost)) {
                // HTTP_HOST is a valid LAN IP (not 127.x.x.x)
                // Extract IP and port (if present)
                $parts = explode(':', $httpHost);
                $wifiIp = $parts[0];
                if (isset($parts[1]) && $parts[1] !== '80') {
                    $port = ':' . $parts[1]; // Preserve non-standard port
                }
            } elseif ($serverAddr && !preg_match('/^127\./', $serverAddr) && !in_array($serverAddr, $loopbackHosts, true)) {
                // SERVER_ADDR is available and not loopback
                $wifiIp = $serverAddr;
                // Try to get port from SERVER_PORT if not 80
                $serverPort = $_SERVER['SERVER_PORT'] ?? '80';
                if ($serverPort !== '80') {
                    $port = ':' . $serverPort;
                }
            } else {
                // Resolve actual LAN IP via hostname (same as registration QR)
                $lanIp = @gethostbyname(gethostname());
                if ($lanIp && !in_array($lanIp, $loopbackHosts, true) && filter_var($lanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $wifiIp = $lanIp;
                    $serverPort = $_SERVER['SERVER_PORT'] ?? '80';
                    if ($serverPort !== '80') {
                        $port = ':' . $serverPort;
                    }
                } else {
                    // Last resort fallback
                    $wifiIp = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? 'localhost';
                }
            }
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $basePath = dirname($script);
        $basePath = preg_replace('#/(admin|guard|visitor|homeowners|auth|api|pages|utilities|includes).*$#', '', $basePath);
        $basePath = rtrim($basePath, '/');

        return "$protocol://$wifiIp$port$basePath";
    }

    // Otherwise, use the regular APP_URL (for hosting/internet access)
    return getAppUrl();
}
