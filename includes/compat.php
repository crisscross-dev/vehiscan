<?php
declare(strict_types=1);

// Compatibility fallback: define small helper implementations when missing
// This prevents fatal errors on hosts running older or partial deployments.

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
