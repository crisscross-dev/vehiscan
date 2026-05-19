<?php
// Temporary host-side error logger. Writes fatal errors and uncaught exceptions
// to diagnostics/last_host_error.log for debugging hosted 500s. Remove after use.

declare(strict_types=1);

$logFile = __DIR__ . '/last_host_error.log';

function writeHostLog($message)
{
    global $logFile;
    $ts = date('c');
    $line = "[{$ts}] " . $message . "\n";
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    $msg = "PHP Error ({$errno}) in {$errfile}:{$errline} - {$errstr}";
    writeHostLog($msg);
    // Let normal error handling continue for fatal types
    return false;
});

set_exception_handler(function ($e) {
    $msg = "Uncaught Exception: " . get_class($e) . " - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\nStack: " . $e->getTraceAsString();
    writeHostLog($msg);
    // Do not reveal details to clients
    http_response_code(500);
    exit;
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
        $msg = "Shutdown fatal: " . ($err['message'] ?? '') . " in " . ($err['file'] ?? '') . ":" . ($err['line'] ?? '');
        writeHostLog($msg);
    }
});

// Log basic request context for correlation
try {
    $context = sprintf("REQUEST %s %s", $_SERVER['REQUEST_METHOD'] ?? 'CLI', $_SERVER['REQUEST_URI'] ?? '');
    writeHostLog($context);
    if (!empty($_SESSION)) {
        writeHostLog('SESSION: ' . json_encode(array_intersect_key($_SESSION, array_flip(array('role','user_id','username')))));
    }
} catch (Throwable $e) {
    // ignore
}

return;

?>
