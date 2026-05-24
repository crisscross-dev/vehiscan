<?php
declare(strict_types=1);

// Compatibility fallback: define small helper implementations when missing
// This prevents fatal errors on hosts running older or partial deployments.

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $needleLength = strlen($needle);
        return substr($haystack, -$needleLength) === $needle;
    }
}

if (!function_exists('vehiscanIsHttpsRequest')) {
    function vehiscanIsHttpsRequest(): bool
    {
        $https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
        $serverPort = (string)($_SERVER['SERVER_PORT'] ?? '');
        $forwardedProto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $remoteAddr = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        $trustedProxyCsv = (string)(getenv('TRUSTED_PROXIES') ?: '');
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', $trustedProxyCsv))));
        $isTrustedProxy = in_array($remoteAddr, $trustedProxies, true);

        return ($https !== '' && $https !== 'off')
            || $serverPort === '443'
            || ($isTrustedProxy && $forwardedProto === 'https');
    }
}
