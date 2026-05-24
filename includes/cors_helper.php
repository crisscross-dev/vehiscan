<?php

if (!function_exists('applyTrustedCors')) {
    /**
     * Apply trusted-origin CORS headers for API endpoints.
     */
    function applyTrustedCors(array $allowedMethods, array $allowedHeaders = ['Content-Type']): void
    {
        $allowedOrigins = ['http://localhost', 'https://localhost', 'http://127.0.0.1'];
        $wifiIp = getenv('WIFI_IP');
        if ($wifiIp) {
            $allowedOrigins[] = 'http://' . $wifiIp;
            $allowedOrigins[] = 'https://' . $wifiIp;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }

        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
    }
}

if (!function_exists('handleCorsPreflight')) {
    /**
     * Handle OPTIONS preflight requests.
     */
    function handleCorsPreflight(): bool
    {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'OPTIONS') {
            http_response_code(204);
            return true;
        }

        return false;
    }
}
