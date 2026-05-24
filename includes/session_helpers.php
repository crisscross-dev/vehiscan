<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

function initializeVehiscanSessionPath(): void
{
    $appSavePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'vehiscan_sessions';
    if (!is_dir($appSavePath)) {
        mkdir($appSavePath, 0700, true);
    }

    ini_set('session.save_path', $appSavePath);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', vehiscanIsHttpsRequest() ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
}

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

function vehiscanStartNamedSession(string $name): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_name($name);
    session_start();
}

function vehiscanIsAjaxRequest(): bool
{
    $isHeaderAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $isQueryAjax = isset($_GET['ajax']) && (string)$_GET['ajax'] === '1';
    $isApiRequest = strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/api/') !== false;

    return $isHeaderAjax || $isQueryAjax || $isApiRequest;
}

function vehiscanJsonExit(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    exit(json_encode($payload));
}

function vehiscanDestroyCurrentSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION = [];
    session_destroy();
}

function vehiscanClearSessionAndCookie(?string $name = null): void
{
    vehiscanDestroyCurrentSession();
    vehiscanExpireSessionCookie($name);
}

function vehiscanExpireSessionCookie(?string $name = null): void
{
    $cookieName = $name ?: session_name();
    if ($cookieName !== '') {
        setcookie($cookieName, '', time() - 3600, '/');
        unset($_COOKIE[$cookieName]);
    }
}

function vehiscanGenerateCsrfToken(): string
{
    return bin2hex(random_bytes(32));
}

function vehiscanAppBasePath(): string
{
    $scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = dirname($scriptName);
    $basePath = preg_replace('#/(admin|guard|visitor|homeowners|auth|api|pages|utilities|includes).*$#', '', (string)$basePath);
    $basePath = rtrim((string)$basePath, '/');

    return $basePath === '' ? '' : $basePath;
}

function vehiscanLoginPath(string $query = ''): string
{
    $path = vehiscanAppBasePath() . '/auth/login.php';
    if ($query !== '') {
        $separator = strpos($path, '?') === false ? '?' : '&';
        $path .= $separator . ltrim($query, '?&');
    }

    return $path;
}
