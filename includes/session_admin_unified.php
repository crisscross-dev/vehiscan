<?php
require_once __DIR__ . '/session_helpers.php';
// Ensure small compatibility helpers exist in case of partial deploys
require_once __DIR__ . '/compat.php';
// Unified session handler for both admin and super_admin
// Resolves the correct session without cross-role collision

// Removed aggressive cookie cleanup to allow simultaneous multi-role sessions (Guard, Admin, Homeowner).
// Each role now has its own unique session cookie name.
/* 
$isAjaxRequest = (!empty($_SERVER['HTTP_X_REQUEST_WITH']) && strtolower($_SERVER['HTTP_X_REQUEST_WITH']) == 'xmlhttprequest') || 
                 (isset($_GET['ajax']) && $_GET['ajax'] == '1') ||
                 (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);

if (!$isAjaxRequest) {
    $allSessionNames = ['vehiscan_superadmin', 'vehiscan_admin', 'vehiscan_guard', 'vehiscan_homeowner', 'vehiscan_session'];
    foreach ($allSessionNames as $sName) {
        if (($sName === 'vehiscan_admin' || $sName === 'vehiscan_superadmin')) continue;
        if (isset($_COOKIE[$sName])) {
            setcookie($sName, '', time() - 3600, '/');
            unset($_COOKIE[$sName]);
        }
    }
}
*/

if (session_status() === PHP_SESSION_NONE) {
    initializeVehiscanSessionPath();
    // Secure session settings
    ini_set('session.cookie_secure', vehiscanIsHttpsRequest() ? '1' : '0');
    ini_set('session.gc_maxlifetime', 3600); // 1 hour — must exceed the 30-min timeout

    $sessionStarted = false;
    $hasSuperCookie = isset($_COOKIE['vehiscan_superadmin']);
    $hasAdminCookie = isset($_COOKIE['vehiscan_admin']);
    // Detect AJAX early so cleanup decisions can avoid removing cookies mid-AJAX
    $isAjaxRequestGlobal = function_exists('vehiscanIsAjaxRequest') ? vehiscanIsAjaxRequest() : false;

    // CASE 1: Only one cookie exists — use it directly (most common case)
    if ($hasSuperCookie && !$hasAdminCookie) {
        session_name('vehiscan_superadmin');
        session_start();
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
            $sessionStarted = true;
            } else {
            // Cookie exists but session is invalid/empty — clean it up
            $_SESSION = [];
            session_destroy();
            // Avoid removing role cookies during AJAX calls to prevent
            // transient cookie loss when multiple requests happen in parallel.
            if (!$isAjaxRequestGlobal) {
                setcookie('vehiscan_superadmin', '', time() - 3600, '/');
            }
        }
    } elseif ($hasAdminCookie && !$hasSuperCookie) {
        session_name('vehiscan_admin');
        session_start();
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $sessionStarted = true;
        } else {
            $_SESSION = [];
            session_destroy();
            if (!$isAjaxRequestGlobal) {
                setcookie('vehiscan_admin', '', time() - 3600, '/');
            }
        }
    } elseif ($hasSuperCookie && $hasAdminCookie) {
        // CASE 2: Both cookies exist — anomalous state.
        // Temporarily disable strict_mode so session_id() + session_start() always
        // opens the exact session file the cookie points to (or creates an empty one
        // with the same ID if the file was GC'd). This avoids strict_mode silently
        // generating a new random ID and creating orphaned sessions.
        ini_set('session.use_strict_mode', 0);

        $superLogin = 0;
        $adminLogin = 0;

        session_name('vehiscan_superadmin');
        session_id($_COOKIE['vehiscan_superadmin']);
        session_start();
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin') {
            $superLogin = $_SESSION['created'] ?? $_SESSION['last_activity'] ?? 0;
        }
        session_write_close();

        session_name('vehiscan_admin');
        session_id($_COOKIE['vehiscan_admin']);
        session_start();
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $adminLogin = $_SESSION['created'] ?? $_SESSION['last_activity'] ?? 0;
        }
        session_write_close();

        // Pick the session with the most recent LOGIN (not activity)
            if ($superLogin > 0 && $superLogin >= $adminLogin) {
            // Super admin session is more recent — destroy the stale admin session
            session_name('vehiscan_admin');
            session_id($_COOKIE['vehiscan_admin']);
            session_start();
            $_SESSION = [];
            session_destroy();
            if (!$isAjaxRequestGlobal) {
                setcookie('vehiscan_admin', '', time() - 3600, '/');
            }
            unset($_COOKIE['vehiscan_admin']);

            session_name('vehiscan_superadmin');
            session_id($_COOKIE['vehiscan_superadmin']);
            session_start();
            $sessionStarted = true;
        } elseif ($adminLogin > 0) {
            // Admin session is more recent — destroy the stale super_admin session
            session_name('vehiscan_superadmin');
            session_id($_COOKIE['vehiscan_superadmin']);
            session_start();
            $_SESSION = [];
            session_destroy();
            if (!$isAjaxRequestGlobal) {
                setcookie('vehiscan_superadmin', '', time() - 3600, '/');
            }
            unset($_COOKIE['vehiscan_superadmin']);

            session_name('vehiscan_admin');
            session_id($_COOKIE['vehiscan_admin']);
            session_start();
            $sessionStarted = true;
        } else {
            // Neither session is valid — clean up both
            foreach (['vehiscan_superadmin', 'vehiscan_admin'] as $sName) {
                if (isset($_COOKIE[$sName])) {
                    session_name($sName);
                    session_id($_COOKIE[$sName]);
                    session_start();
                    $_SESSION = [];
                    session_destroy();
                    setcookie($sName, '', time() - 3600, '/');
                    unset($_COOKIE[$sName]);
                }
            }
        }

        // Re-enable strict mode for all subsequent session operations
        ini_set('session.use_strict_mode', 1);
    }

    // CASE 3: No cookies at all — start a fresh empty admin session (will fail auth check downstream)
    if (!$sessionStarted) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        session_name('vehiscan_admin');
        session_start();
    }
}

// Session timeout check (30 minutes = 1800 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    // Expire the session cookie BEFORE destroying the session.
    // Without this, the stale cookie persists in the browser after timeout,
    // and if the user logs in as a different role, both cookies coexist
    // causing CASE 2 (role confusion) on the next request.
    $expiredName = session_name();
    setcookie($expiredName, '', time() - 3600, '/');

    session_unset();
    session_destroy();

    // Check if it's an AJAX request
    $isAjax = vehiscanIsAjaxRequest();

    if ($isAjax) {
        vehiscanJsonExit(403, [
            'error' => 'Session expired',
            'redirect' => vehiscanLoginPath('timeout=1')
        ]);
    }

    header('Location: ' . vehiscanLoginPath('timeout=1'));
    exit;
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

if (!in_array(($_SESSION['role'] ?? ''), ['admin', 'super_admin'], true)) {
    session_unset();
    setcookie(session_name(), '', time() - 3600, '/');
    session_destroy();

    $isAjax = vehiscanIsAjaxRequest();

    if ($isAjax) {
        vehiscanJsonExit(403, [
            'error' => 'Unauthorized',
            'redirect' => vehiscanLoginPath()
        ]);
    }

    header('Location: ' . vehiscanLoginPath());
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = vehiscanGenerateCsrfToken();
}

// Expose current CSRF token via response header so JS can auto-refresh
// after session rebuild (e.g. GC → strict_mode → new session → new token).
// The fetch interceptor reads this and updates the cached JS variable.
header('X-CSRF-Token: ' . $_SESSION['csrf_token']);

if (!function_exists('logAudit')) {
    function logAudit($action, $table = null, $record_id = null, $details = null) {
        if (!isset($_SESSION['username'])) return;
        global $pdo;
        if (!isset($pdo)) return;
        try {
            $check = $pdo->query("SHOW TABLES LIKE 'audit_logs'")->fetch();
            if (!$check) return;
            $stmt = $pdo->prepare("INSERT INTO audit_logs (username, action, table_name, record_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['username'], $action, $table, $record_id, $details, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
        } catch (Exception $e) {}
    }
}
