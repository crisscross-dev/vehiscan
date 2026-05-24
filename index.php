<?php
// Detect active session and redirect to appropriate dashboard
// Isolate from other XAMPP apps to prevent cross-app GC
$appSavePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vehiscan_sessions';
if (!is_dir($appSavePath)) { mkdir($appSavePath, 0700, true); }
ini_set('session.save_path', $appSavePath);
ini_set('session.gc_maxlifetime', 3600);

// Check each role-specific session cookie
$redirectMap = [
    'vehiscan_superadmin' => 'admin/admin_panel.php',
    'vehiscan_admin'      => 'admin/admin_panel.php',
    'vehiscan_guard'      => 'guard/pages/guard_side.php',
    'vehiscan_homeowner'  => 'homeowners/portal.php',
];

foreach ($redirectMap as $cookieName => $target) {
    if (isset($_COOKIE[$cookieName])) {
        ini_set('session.use_strict_mode', 1);
        session_name($cookieName);
        session_start();
        if (isset($_SESSION['role']) && isset($_SESSION['user_id'])) {
            header("Location: $target");
            exit;
        }
        session_write_close();
    }
}

// No valid session found — redirect to login
header("Location: auth/login.php");
exit;
?>
