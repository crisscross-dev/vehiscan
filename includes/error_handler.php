<?php
/**
 * Centralized Error Handler
 * Provides professional error handling with environment-aware display
 */

// Set error handler
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Log error
    error_log("[$errno] $errstr in $errfile:$errline");

    // Don't display errors for suppressed errors (@)
    if (error_reporting() === 0) {
        return false;
    }

    // Don't treat deprecation notices and minor warnings as fatal
    $nonFatalErrors = [E_DEPRECATED, E_USER_DEPRECATED, E_NOTICE, E_USER_NOTICE, E_STRICT];
    if (in_array($errno, $nonFatalErrors)) {
        // Log but don't display or kill the page
        return true;
    }

    // Display based on environment
    if (defined('APP_DEBUG') && APP_DEBUG) {
        // Development: Show detailed error
        echo "<div style='background:#fee; border:1px solid #c00; padding:10px; margin:10px; font-family:monospace;'>";
        echo "<strong>Error [$errno]:</strong> $errstr<br>";
        echo "<strong>File:</strong> $errfile<br>";
        echo "<strong>Line:</strong> $errline<br>";
        echo "</div>";
    } else {
        // Production: Show generic error page
        http_response_code(500);
        include __DIR__ . '/../error_pages/500.php';
        exit;
    }

    return true;
});

// Set exception handler
set_exception_handler(function ($exception) {
    // Log exception
    error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
    error_log("Stack trace: " . $exception->getTraceAsString());

    // Display based on environment
    if (defined('APP_DEBUG') && APP_DEBUG) {
        // Development: Show detailed exception
        echo "<div style='background:#fee; border:1px solid #c00; padding:10px; margin:10px; font-family:monospace;'>";
        echo "<strong>Exception:</strong> " . get_class($exception) . "<br>";
        echo "<strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "<br>";
        echo "<strong>File:</strong> " . $exception->getFile() . "<br>";
        echo "<strong>Line:</strong> " . $exception->getLine() . "<br>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        // Production: Show generic error page
        http_response_code(500);
        include __DIR__ . '/../error_pages/500.php';
        exit;
    }
});

// Set shutdown handler for fatal errors
register_shutdown_function(function () {
    $error = error_get_last();

    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log fatal error
        error_log("Fatal error: {$error['message']} in {$error['file']}:{$error['line']}");

        // Show error page
        if (!(defined('APP_DEBUG') && APP_DEBUG)) {
            http_response_code(500);
            include __DIR__ . '/../error_pages/500.php';
            exit;
        }
    }
});
?>