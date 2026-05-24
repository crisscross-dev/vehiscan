<?php
// Generate QR code for visitor pass
require_once __DIR__ . '/../../phpqrcode/qrlib.php';

function resolveVisitorQrBaseUrl()
{
    if (!function_exists('config')) {
        require_once __DIR__ . '/../../config.php';
    }

    $envQrUrl = function_exists('config') ? config('QR_BASE_URL', null) : null;
    if ($envQrUrl) {
        return rtrim((string)$envQrUrl, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $httpHost = (string)($_SERVER['HTTP_HOST'] ?? '');
    $serverPort = $_SERVER['SERVER_PORT'] ?? null;

    $hostName = $httpHost !== ''
        ? $httpHost
        : (string)($_SERVER['SERVER_NAME'] ?? ($_SERVER['SERVER_ADDR'] ?? 'localhost'));
    $port = null;

    if (strpos($hostName, ':') !== false) {
        [$hostOnly, $portPart] = explode(':', $hostName, 2);
        $hostName = $hostOnly;
        if (is_numeric($portPart)) {
            $port = (int)$portPart;
        }
    }

    if ($port === null && $serverPort !== null && is_numeric((string)$serverPort)) {
        $port = (int)$serverPort;
    }

    $loopbackHosts = ['localhost', '127.0.0.1', '::1'];
    if (in_array(strtolower($hostName), $loopbackHosts, true)) {
        $serverAddr = (string)($_SERVER['SERVER_ADDR'] ?? '');

        if ($serverAddr !== '' && !in_array($serverAddr, $loopbackHosts, true)) {
            $hostName = $serverAddr;
        } else {
            $lanIp = @gethostbyname(gethostname());
            if (
                $lanIp
                && !in_array($lanIp, $loopbackHosts, true)
                && filter_var($lanIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ) {
                $hostName = $lanIp;
            }
        }
    }

    $portSegment = '';
    if ($port && !in_array($port, [80, 443], true)) {
        $portSegment = ':' . $port;
    }

    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = dirname($script);
    $basePath = preg_replace('#/(admin|guard|visitor|homeowners|auth|api|pages|utilities|includes).*$#', '', $basePath);
    $basePath = rtrim((string)$basePath, '/');

    return sprintf('%s://%s%s%s', $scheme, $hostName, $portSegment, $basePath);
}

function generateVisitorPassQR($passId, $token, $pdo)
{
    try {
        // Create QR code directory if it doesn't exist
        $qrDir = __DIR__ . '/../../uploads/qr_codes';
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0755, true);
        }

        // Match homeowner registration QR behavior for LAN/mobile accessibility.
        $baseUrl = resolveVisitorQrBaseUrl();
        $verifyUrl = "$baseUrl/visitor/scan.php?token=$token";

        // Generate QR code with logo overlay
        $tempFile = $qrDir . "/pass_$passId.png";
        $finalFile = $qrDir . "/pass_{$passId}_final.png";

        // Generate base QR code
        QRcode::png($verifyUrl, $tempFile, QR_ECLEVEL_H, 8, 2); // Higher error correction for logo

        // Add logo overlay
        $logoPath = __DIR__ . '/../../assets/images/ville_de_palme.png';
        if (file_exists($logoPath)) {
            addLogoToQR($tempFile, $logoPath, $finalFile);
            // Use the final file with logo
            $imageData = file_get_contents($finalFile);
            unlink($tempFile);
            unlink($finalFile);
        } else {
            // No logo, use plain QR
            $imageData = file_get_contents($tempFile);
            unlink($tempFile);
        }

        // Convert to base64
        $base64 = base64_encode($imageData);
        $qrCodeData = 'data:image/png;base64,' . $base64;

        return $qrCodeData;

    } catch (Exception $e) {
        error_log("QR generation error: " . $e->getMessage());
        return null;
    }
}

function addLogoToQR($qrPath, $logoPath, $outputPath)
{
    // Load images
    $qr = imagecreatefrompng($qrPath);
    $logo = imagecreatefrompng($logoPath);

    if (!$qr || !$logo) {
        copy($qrPath, $outputPath);
        return;
    }

    // Get dimensions
    $qrWidth = imagesx($qr);
    $qrHeight = imagesy($qr);
    $logoWidth = imagesx($logo);
    $logoHeight = imagesy($logo);

    // Calculate logo size (15% of QR code)
    $logoQrWidth = $qrWidth / 6;
    $logoQrHeight = $logoHeight * ($logoQrWidth / $logoWidth);

    // Create white background for logo
    $logoX = ($qrWidth - $logoQrWidth) / 2;
    $logoY = ($qrHeight - $logoQrHeight) / 2;
    $whiteBg = imagecolorallocate($qr, 255, 255, 255);
    imagefilledrectangle($qr, $logoX - 5, $logoY - 5, $logoX + $logoQrWidth + 5, $logoY + $logoQrHeight + 5, $whiteBg);

    // Copy logo onto QR code
    imagecopyresampled($qr, $logo, $logoX, $logoY, 0, 0, $logoQrWidth, $logoQrHeight, $logoWidth, $logoHeight);

    // Save
    imagepng($qr, $outputPath);

    // Free memory
    imagedestroy($qr);
    imagedestroy($logo);
}

function generateSecureToken()
{
    return bin2hex(random_bytes(32));
}
