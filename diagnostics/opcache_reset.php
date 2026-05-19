<?php
// Temporary OPcache reset endpoint — remove after use
$token = 'vehiscan-temp-diagnostics';
if (!isset($_GET['key']) || $_GET['key'] !== $token) {
    http_response_code(403);
    echo "Forbidden. Provide ?key={$token}\n";
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "PHP Version: " . PHP_VERSION . "\n";
if (function_exists('opcache_reset')) {
    $ok = opcache_reset();
    echo $ok ? "OPcache cleared\n" : "OPcache reset reported failure\n";
} else {
    echo "OPcache not available on this build\n";
}

// Also try to clear APC user cache if present
if (function_exists('apc_clear_cache')) {
    @apc_clear_cache();
    @apc_clear_cache('user');
    echo "APC user cache cleared (if present)\n";
}

?>