<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function readEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $values = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $values[trim($key)] = trim($value);
    }

    return $values;
}

function reportLine(string $status, string $label, string $detail = ''): void
{
    $suffix = $detail !== '' ? ' - ' . $detail : '';
    echo '[' . $status . '] ' . $label . $suffix . PHP_EOL;
}

function isPlaceholderValue(string $value, array $needles): bool
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return true;
    }

    $lower = strtolower($trimmed);
    foreach ($needles as $needle) {
        if ($needle !== '' && str_contains($lower, strtolower($needle))) {
            return true;
        }
    }

    return false;
}

function scanForExternalDependencies(string $root): array
{
    $matches = [];
    $skipDirectories = [
        $root . DIRECTORY_SEPARATOR . '.git',
        $root . DIRECTORY_SEPARATOR . 'docs',
        $root . DIRECTORY_SEPARATOR . 'backups',
        $root . DIRECTORY_SEPARATOR . '_archive',
        $root . DIRECTORY_SEPARATOR . 'scratch',
        $root . DIRECTORY_SEPARATOR . 'diagnostics',
        $root . DIRECTORY_SEPARATOR . 'node_modules',
        $root . DIRECTORY_SEPARATOR . 'vendor',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $filePath = $fileInfo->getPathname();
        foreach ($skipDirectories as $skipDirectory) {
            if (str_starts_with($filePath, $skipDirectory)) {
                continue 2;
            }
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (!in_array($extension, ['php', 'html', 'htm', 'js', 'md', 'txt'], true)) {
            continue;
        }

        $contents = @file_get_contents($filePath);
        if ($contents === false) {
            continue;
        }

        if (preg_match('/https?:\/\/(?:fonts\.googleapis\.com|fonts\.gstatic\.com|cdn\.jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com)/i', $contents)) {
            $matches[] = str_replace($root . DIRECTORY_SEPARATOR, '', $filePath);
        }
    }

    return array_values(array_unique($matches));
}

function normalizeRelativePath(string $path): string
{
    $segments = explode('/', str_replace('\\', '/', $path));
    $resolved = [];

    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }

        if ($segment === '..') {
            array_pop($resolved);
            continue;
        }

        $resolved[] = $segment;
    }

    return implode(DIRECTORY_SEPARATOR, $resolved);
}

function scanPageAssetReferences(string $root, array $pageFiles): array
{
    $missing = [];
    foreach ($pageFiles as $relativePagePath) {
        $pagePath = $root . DIRECTORY_SEPARATOR . $relativePagePath;
        if (!is_file($pagePath)) {
            continue;
        }

        $contents = @file_get_contents($pagePath);
        if ($contents === false) {
            continue;
        }

        $pageDir = dirname($pagePath);
        if (!preg_match_all('/\b(?:src|href)=(["\'])([^"\']+)\1/i', $contents, $matches)) {
            continue;
        }

        foreach ($matches[2] as $reference) {
            $reference = trim($reference);
            if ($reference === '' || $reference[0] === '#' || str_starts_with($reference, 'data:') || str_starts_with($reference, 'mailto:') || str_starts_with($reference, 'tel:') || str_starts_with($reference, 'javascript:')) {
                continue;
            }

            if (preg_match('/^https?:\/\//i', $reference)) {
                continue;
            }

            $referencePath = explode('?', $reference, 2)[0];
            $referencePath = explode('#', $referencePath, 2)[0];
            if ($referencePath === '') {
                continue;
            }

            $candidatePath = $referencePath;
            $isWindowsAbsolutePath = strlen($referencePath) >= 2 && ctype_alpha($referencePath[0]) && $referencePath[1] === ':';
            if (!str_starts_with($referencePath, DIRECTORY_SEPARATOR) && !$isWindowsAbsolutePath) {
                $candidatePath = $pageDir . DIRECTORY_SEPARATOR . $referencePath;
            }

            $candidatePath = normalizeRelativePath($candidatePath);
            if (!file_exists($candidatePath)) {
                $missing[] = str_replace($root . DIRECTORY_SEPARATOR, '', $pagePath) . ' -> ' . $referencePath;
            }
        }
    }

    return array_values(array_unique($missing));
}

$checks = [];

// CLI flags
$argv = $_SERVER['argv'] ?? [];
$allowLocal = in_array('--allow-local', $argv, true) || getenv('ALLOW_LOCAL_HOSTING') === '1';
$warnings = [];
$failedChecks = [];

$requiredFiles = [
    'index.php' => 'root entrypoint',
    '.htaccess' => 'root access guard',
    'config.php' => 'environment loader',
    'db.php' => 'database bootstrap',
    'public_html/index.php' => 'hosting front controller',
    'public_html/.htaccess' => 'hosting rewrite rules',
    'includes/security_headers.php' => 'security headers',
    'includes/session_helpers.php' => 'session helpers',
    'assets/css/tailwind.css' => 'compiled CSS bundle',
    'assets/js/libs/jquery-3.7.1.min.js' => 'local jQuery vendor file',
    'assets/js/libs/sweetalert2.all.min.js' => 'local SweetAlert2 vendor file',
    'assets/js/libs/html5-qrcode.min.js' => 'local QR scanner vendor file',
    'assets/js/libs/chart.umd.min.js' => 'local chart vendor file',
];

foreach ($requiredFiles as $relativePath => $label) {
    $fullPath = $root . DIRECTORY_SEPARATOR . $relativePath;
    if (is_file($fullPath)) {
        $checks[] = [$label, true, $relativePath];
    } else {
        $checks[] = [$label, false, $relativePath];
    }
}

$requiredDirectories = [
    'assets',
    'uploads',
    'backups',
    'admin',
    'auth',
    'guard',
    'homeowners',
    'visitor',
    'api',
    'includes',
    'middleware',
    'migrations',
];

foreach ($requiredDirectories as $relativePath) {
    $fullPath = $root . DIRECTORY_SEPARATOR . $relativePath;
    $checks[] = [$relativePath . ' directory', is_dir($fullPath), $relativePath];
}

$hostingEnv = readEnvFile($root . DIRECTORY_SEPARATOR . '.env.hosting');
$localEnv = readEnvFile($root . DIRECTORY_SEPARATOR . '.env');
$effectiveEnv = $hostingEnv !== [] ? $hostingEnv : $localEnv;

$effectiveEnvSource = $hostingEnv !== [] ? '.env.hosting' : '.env';

if ($hostingEnv === []) {
    $warnings[] = '.env.hosting is missing; deployment will fall back to .env if present.';
}

if (($effectiveEnv['APP_ENV'] ?? '') !== 'production') {
    $warnings[] = 'APP_ENV is not set to production in ' . $effectiveEnvSource . '.';
}

if (($effectiveEnv['SESSION_SECURE'] ?? '') !== 'true') {
    $warnings[] = 'SESSION_SECURE is not true in ' . $effectiveEnvSource . '.';
}

if (($effectiveEnv['SESSION_HTTPONLY'] ?? '') !== 'true') {
    $warnings[] = 'SESSION_HTTPONLY is not true in ' . $effectiveEnvSource . '.';
}

$appUrl = $effectiveEnv['APP_URL'] ?? '';
if ($appUrl === '') {
    $warnings[] = 'APP_URL is missing in ' . $effectiveEnvSource . '.';
} elseif (!str_starts_with($appUrl, 'https://')) {
    $warnings[] = 'APP_URL should use https:// for hosted deployment.';
} elseif (isPlaceholderValue($appUrl, ['your-domain.example.com', 'example.com', 'localhost', '127.0.0.1'])) {
    $warnings[] = 'APP_URL is still a placeholder in ' . $effectiveEnvSource . '.';
}

$requiredProdValues = [
    'DB_HOST' => ['127.0.0.1', 'localhost', 'example'],
    'DB_NAME' => ['vehiscan_vdp', 'example'],
    'DB_USER' => ['vehiscan_user', 'root', 'example'],
    'DB_PASS' => ['change_this_password', 'password', 'example'],
];

foreach ($requiredProdValues as $key => $needles) {
    $value = (string)($effectiveEnv[$key] ?? '');
    if ($allowLocal) {
        // In local/staging mode, only require a non-empty value; don't treat common local values as placeholders
        if (trim($value) === '') {
            $warnings[] = $key . ' is missing or still a placeholder in ' . $effectiveEnvSource . '.';
        }
    } else {
        if (isPlaceholderValue($value, $needles)) {
            $warnings[] = $key . ' is missing or still a placeholder in ' . $effectiveEnvSource . '.';
        }
    }
}

$allPassed = true;
foreach ($checks as [$label, $ok, $relativePath]) {
    reportLine($ok ? 'OK' : 'FAIL', $label, $relativePath);
    $allPassed = $allPassed && $ok;
    if (!$ok) {
        $failedChecks[] = $relativePath;
    }
}

foreach ($warnings as $warning) {
    reportLine('WARN', 'config', $warning);
}

$externalDependencies = scanForExternalDependencies($root);
if ($externalDependencies !== []) {
    foreach ($externalDependencies as $file) {
        reportLine('WARN', 'external dependency', $file);
    }
    $warnings[] = 'Active runtime files still reference external CDNs or hosted fonts.';
}

$pageAssetMissing = scanPageAssetReferences($root, [
    'admin/admin_panel.php',
    'admin/employee_registration.php',
    'admin/employee_list.php',
    'admin/employee_edit.php',
    'homeowners/portal.php',
    'guard/pages/guard_side.php',
    'auth/login.php',
    'auth/forgot-password.php',
    'auth/reset-password.php',
]);

if ($pageAssetMissing !== []) {
    foreach ($pageAssetMissing as $missingReference) {
        reportLine('WARN', 'missing page asset', $missingReference);
    }
    $warnings[] = 'One or more runtime pages reference missing local assets.';
}

$allPassed = $allPassed && $warnings === [];
// If running in local/staging allow mode, consider only missing files as fatal, not configuration placeholders
if ($allowLocal) {
    $allPassed = $allPassed && empty($failedChecks);
}
$summaryStatus = $allPassed ? 'PASS' : 'FAIL';
$summaryDetail = $allPassed
    ? 'all required filesystem checks passed'
    : (!empty($failedChecks)
        ? 'one or more required files or directories are missing'
        : 'one or more configuration or asset checks failed');
reportLine($summaryStatus, 'summary', $summaryDetail);

exit($allPassed ? 0 : 1);
