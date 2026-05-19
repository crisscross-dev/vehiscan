<?php
// Session diagnostics — prints session.save_path and writability. Remove after use.
$token = 'vehiscan-temp-diagnostics';
if (!isset($_GET['key']) || $_GET['key'] !== $token) {
    http_response_code(403);
    echo "Forbidden. Provide ?key={$token}\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$inc = __DIR__ . '/../includes/session_helpers.php';
if (!file_exists($inc)) {
    echo "Missing includes/session_helpers.php at expected path: {$inc}\n";
    exit;
}

require_once $inc;

// Run initializer which will attempt to create the directory
initializeVehiscanSessionPath();
$path = ini_get('session.save_path') ?: sys_get_temp_dir();

echo "session.save_path={$path}\n";
echo "exists=" . (is_dir($path) ? 'yes' : 'no') . "\n";
echo "writable=" . (is_writable($path) ? 'yes' : 'no') . "\n";
echo "owner: ";
if (is_dir($path)) {
    $stat = @stat($path);
    if ($stat) {
        $uid = $stat['uid'] ?? 'n/a';
        $gid = $stat['gid'] ?? 'n/a';
        echo "uid={$uid}, gid={$gid}\n";
    } else {
        echo "unknown\n";
    }
    echo "\nDirectory listing (first 50 entries):\n";
    $files = array_slice(scandir($path), 0, 50);
    foreach ($files as $f) {
        echo $f . "\n";
    }
}

?>