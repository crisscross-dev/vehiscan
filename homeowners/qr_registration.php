<?php
// homeowners/qr_registration.php

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$httpHost = $_SERVER['HTTP_HOST'] ?? '';
$serverPort = $_SERVER['SERVER_PORT'] ?? null;

$hostName = $httpHost ?: ($_SERVER['SERVER_NAME'] ?? ($_SERVER['SERVER_ADDR'] ?? 'localhost'));
$port = null;

// Separate host and port if provided in HTTP_HOST
if (strpos($hostName, ':') !== false) {
    [$hostOnly, $portPart] = explode(':', $hostName, 2);
    $hostName = $hostOnly;
    if (is_numeric($portPart)) {
        $port = (int)$portPart;
    }
}

if ($port === null && $serverPort) {
    $port = (int)$serverPort;
}

$loopbackHosts = ['localhost', '127.0.0.1', '::1'];

if (in_array(strtolower($hostName), $loopbackHosts, true)) {
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';

    if ($serverAddr && !in_array($serverAddr, $loopbackHosts, true)) {
        $hostName = $serverAddr;
    } else {
        $lanIp = gethostbyname(gethostname());
        if ($lanIp && !in_array($lanIp, $loopbackHosts, true)) {
            $hostName = $lanIp;
        }
    }
}

$portSegment = '';
if ($port && !in_array($port, [80, 443], true)) {
    $portSegment = ':' . $port;
}

$projectRoot = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/\\');
$projectRoot = $projectRoot === '/' ? '' : $projectRoot;

$registrationUrl = sprintf(
    '%s://%s%s%s/homeowners/homeowner_registration.php',
    $scheme,
    $hostName,
    $portSegment,
    $projectRoot
);

$isLocalFallback = in_array($hostName, $loopbackHosts, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Homeowner Registration QR | VehiScan</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f8f9fa;
      color: #09090b;
      margin: 0;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 2rem 1rem;
      position: relative;
      overflow-x: hidden;
    }

    /* Wavy background decorations */
    .bg-decoration {
      position: fixed;
      border-radius: 50%;
      opacity: 0.08;
      pointer-events: none;
      z-index: 0;
    }

    .bg-decoration-1 {
      width: 600px;
      height: 600px;
      background: radial-gradient(circle, #000 0%, transparent 70%);
      top: -200px;
      left: -150px;
    }

    .bg-decoration-2 {
      width: 800px;
      height: 800px;
      border: 100px solid #000;
      border-style: dashed;
      bottom: -400px;
      right: -200px;
      opacity: 0.03;
    }

    .qr-shell {
      width: 100%;
      max-width: 480px;
      background: white;
      border-radius: 12px;
      padding: 2.5rem 2rem;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
      text-align: center;
      position: relative;
      z-index: 1;
    }

    .logo-container {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .logo-image {
      height: 64px;
      width: auto;
      max-width: 250px;
      object-fit: contain;
    }

    .qr-shell header {
      margin-bottom: 2rem;
    }

    .qr-shell h1 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 0.25rem;
      color: #000;
    }

    .qr-shell .subtitle {
      margin-top: 0.5rem;
      color: #666;
      font-size: 0.875rem;
    }

    .qr-card {
      background: #f8f9fa;
      border-radius: 12px;
      padding: 2rem;
      margin: 1.5rem auto;
      display: inline-block;
      border: 1px solid #e4e4e7;
    }

    .qr-card img {
      width: min(260px, 60vw);
      height: min(260px, 60vw);
      border-radius: 8px;
      border: 2px solid #e4e4e7;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .link-box {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 0.875rem 1rem;
      font-family: ui-monospace, "Courier New", monospace;
      font-size: 0.8125rem;
      color: #52525b;
      word-break: break-all;
      position: relative;
      margin-top: 1rem;
      border: 1px solid #e4e4e7;
    }

    .copy-btn {
      position: absolute;
      top: 50%;
      right: 12px;
      transform: translateY(-50%);
      border: 1px solid #e4e4e7;
      background: #ffffff;
      color: #000;
      border-radius: 6px;
      padding: 0.375rem 0.75rem;
      font-size: 0.75rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.15s;
    }

    .copy-btn:hover {
      background: #f8f9fa;
      border-color: #000;
    }

    .action-btn {
      display: inline-flex;
      gap: 0.5rem;
      align-items: center;
      justify-content: center;
      margin-top: 1.5rem;
      padding: 0.625rem 1.5rem;
      background: #000;
      color: #ffffff;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.875rem;
      transition: background 0.15s;
      border: none;
      cursor: pointer;
    }

    .action-btn:hover {
      background: #27272a;
    }

    .tips {
      margin-top: 1.5rem;
      text-align: left;
      font-size: 0.8125rem;
      color: #52525b;
      line-height: 1.6;
      background: #fafafa;
      border-radius: 8px;
      padding: 0.875rem 1rem;
      border: 1px solid #e4e4e7;
    }

    .tips strong {
      display: block;
      color: #09090b;
      margin-bottom: 0.5rem;
      font-weight: 600;
    }

    .tips ul {
      padding-left: 1.25rem;
      margin: 0;
    }

    .tips li {
      margin-bottom: 0.5rem;
    }

    .notice {
      margin-top: 1rem;
      font-size: 0.8125rem;
      color: #c2410c;
      background: #fff4ed;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      border: 1px solid #fed7aa;
    }

    @media (max-width: 480px) {
      body {
        padding: 1rem 0.5rem;
      }

      .qr-shell {
        padding: 2rem 1.5rem;
      }

      .logo-image {
        height: 48px;
      }

      .qr-shell h1 {
        font-size: 1.25rem;
      }

      .qr-card {
        padding: 1.5rem;
      }

      .action-btn {
        width: 100%;
      }

      .bg-decoration {
        display: none;
      }
    }

    @media print {
      body {
        background: #fff;
        padding: 0;
      }
      .bg-decoration, .copy-btn, .action-btn, .print-btn, .tips, .notice {
        display: none !important;
      }
      .qr-shell {
        box-shadow: none;
        padding: 1.5rem;
        max-width: 100%;
      }
      .link-box {
        border: 1px solid #ccc;
      }
    }
  </style>
</head>
<body>
  <div class="bg-decoration bg-decoration-1"></div>
  <div class="bg-decoration bg-decoration-2"></div>

  <main class="qr-shell">
    <div class="logo-container">
      <img src="../assets/images/vehiscan-logo.png" alt="VehiScan Logo" class="logo-image">
    </div>

    <header>
      <h1>Homeowner Registration</h1>
      <p class="subtitle">Scan the QR code or open the link to register your vehicle</p>
    </header>

    <section class="qr-card" aria-label="Homeowner registration QR code" style="position:relative;">
      <img
        src="../phpqrcode/generate_qr.php?url=<?= urlencode($registrationUrl) ?>"
        alt="QR code linking to homeowner registration"
      >
      <img src="../assets/images/ville_de_palme.png" alt="Logo" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:18%; height:18%; object-fit:contain; border-radius:50%; background:#fff; padding:4px; box-shadow:0 2px 8px rgba(0,0,0,0.15); pointer-events:none;">
    </section>

    <div class="link-box" aria-label="Direct registration link">
      <span><?= htmlspecialchars($registrationUrl ?? '') ?></span>
      <button class="copy-btn" type="button" data-copy="<?= htmlspecialchars($registrationUrl ?? '') ?>">COPY</button>
    </div>

    <a class="action-btn" href="<?= htmlspecialchars($registrationUrl ?? '') ?>" target="_blank" rel="noopener">
      <span>Open Registration Form</span>
      <span aria-hidden="true">↗</span>
    </a>

    <button class="action-btn print-btn" type="button" onclick="window.print()" style="background:#3b82f6;">
      <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm0-16h6v4H9V3z"/></svg>
      <span>Print</span>
    </button>

    <section class="tips" aria-label="Usage tips">
      <strong>Tips:</strong>
      <ul>
        <li>Scan with your phone camera or a QR scanner app.</li>
        <li>Ensure this device and your phone share the same network.</li>
        <li>If the QR uses an unreachable address, replace "localhost" with your computer's LAN IP (e.g., 192.168.x.x).</li>
      </ul>
    </section>

    <?php if ($isLocalFallback): ?>
      <div class="notice">
        The link currently points to a local address. For mobile devices on the same WiFi, use this computer's LAN IP instead of "localhost" if the page fails to load.
      </div>
    <?php endif; ?>
  </main>

  <script>
    document.querySelector('.copy-btn')?.addEventListener('click', (event) => {
      const button = event.currentTarget;
      const value = button.dataset.copy;

      if (!navigator.clipboard) {
        const temp = document.createElement('textarea');
        temp.value = value;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
      } else {
        navigator.clipboard.writeText(value).catch(() => {});
      }

      button.textContent = 'COPIED';
      button.classList.add('copied');
      setTimeout(() => {
        button.textContent = 'COPY';
        button.classList.remove('copied');
      }, 1600);
    });
  </script>
</body>
</html>