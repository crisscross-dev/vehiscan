<?php
require_once __DIR__ . '/qrlib.php';

function buildFallbackUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');

    if (strpos($host, ':') !== false) {
        [$hostOnly, $portPart] = explode(':', $host, 2);
        if (is_numeric($portPart)) {
            $port = (int)$portPart;
            $host = $hostOnly;
        } else {
            $port = (int)($_SERVER['SERVER_PORT'] ?? 80);
        }
    } else {
        $port = (int)($_SERVER['SERVER_PORT'] ?? 80);
    }

    // Resolve LAN IP when host is loopback (so QR works on mobile devices)
    $loopbackHosts = ['localhost', '127.0.0.1', '::1'];
    if (in_array(strtolower($host), $loopbackHosts, true)) {
        $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
        if ($serverAddr && !in_array($serverAddr, $loopbackHosts, true)) {
            $host = $serverAddr;
        } else {
            $lanIp = @gethostbyname(gethostname());
            if ($lanIp && !in_array($lanIp, $loopbackHosts, true) && filter_var($lanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $host = $lanIp;
            }
        }
    }

    $portSegment = ($port && !in_array($port, [80, 443], true)) ? ':' . $port : '';

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $projectRoot = rtrim(dirname(dirname($script)), '/\\');
    if ($projectRoot === '/' || $projectRoot === '\\') {
        $projectRoot = '';
    }

    $path = $projectRoot . '/homeowners/homeowner_registration.php';
    $path = preg_replace('#/+#', '/', '/' . ltrim($path, '/'));

    return sprintf('%s://%s%s%s', $scheme, $host, $portSegment, $path);
}

$urlParam = $_GET['url'] ?? '';
$targetUrl = '';

if ($urlParam !== '') {
    $decoded = urldecode($urlParam);

    if (!preg_match('#^https?://#i', $decoded)) {
        $fallback = buildFallbackUrl();
        $decoded = rtrim($fallback, '/') . '/' . ltrim($decoded, '/');
    }

    if (filter_var($decoded, FILTER_VALIDATE_URL)) {
        $targetUrl = $decoded;
    }
}

if ($targetUrl === '') {
    $targetUrl = buildFallbackUrl();
}

$targetUrl = preg_replace('#(?<!:)//+#', '/', $targetUrl);
$targetUrl = str_replace(':/', '://', $targetUrl);

$targetUrl .= (str_contains($targetUrl, '?') ? '&' : '?') . 'v=' . time();

QRcode::png($targetUrl, false, 'M', 8, 2);
exit;
?>
