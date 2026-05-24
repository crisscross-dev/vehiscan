<?php

if (!function_exists('requireRequestMethod')) {
    function requireRequestMethod(string $method): void
    {
        $actual = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $expected = strtoupper($method);

        if ($actual !== $expected) {
            if (!headers_sent()) {
                http_response_code(405);
                header('Allow: ' . $expected);
                header('Content-Type: application/json');
            }

            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
            exit;
        }
    }
}
