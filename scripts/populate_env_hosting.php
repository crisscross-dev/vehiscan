<?php
// populate_env_hosting.php
// Safely copy non-placeholder values from `.env` into `.env.hosting` for local testing.

$root = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
$envPath = $root . '.env';
$hostingPath = $root . '.env.hosting';

if (!file_exists($hostingPath)) {
    fwrite(STDERR, "ERROR: .env.hosting not found in project root.\n");
    exit(2);
}

$env = parseEnvFile($envPath);
$hosting = parseEnvFile($hostingPath);

// Optionally use a local hosting override file for safe staging/testing.
$localPath = $root . '.env.hosting.local';
$local = [];
if (file_exists($localPath)) {
    $local = parseEnvFile($localPath);
}

$backup = $hostingPath . '.bak.' . date('Ymd_His');
if (!copy($hostingPath, $backup)) {
    fwrite(STDERR, "ERROR: failed to create backup at $backup\n");
    exit(3);
}

$changed = false;
$replacements = [];
foreach ($hosting as $k => $v) {
    if (isPlaceholder($v)) {
        if (isset($env[$k]) && !isPlaceholder($env[$k])) {
            $replacements[$k] = $env[$k];
            $changed = true;
            echo "[REPLACE] $k will be filled from .env\n";
        } elseif (isset($local[$k]) && !isPlaceholder($local[$k])) {
            $replacements[$k] = $local[$k];
            $changed = true;
            echo "[REPLACE] $k will be filled from .env.hosting.local\n";
        } else {
            echo "[SKIP] $k remains placeholder (no usable value in .env or .env.hosting.local)\n";
        }
    }
}

if ($changed) {
    $lines = file($hostingPath, FILE_IGNORE_NEW_LINES);
    $applied = [];
    foreach ($lines as &$line) {
        if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', $line, $m)) {
            $key = $m[1];
            if (array_key_exists($key, $replacements)) {
                $line = $key . '=' . $replacements[$key];
                $applied[$key] = true;
            }
        }
    }
    // Append any replacement keys that weren't present in the original file
    foreach ($replacements as $k => $v) {
        if (!isset($applied[$k])) {
            $lines[] = $k . '=' . $v;
        }
    }
    file_put_contents($hostingPath, implode(PHP_EOL, $lines) . PHP_EOL);
    echo "Updated .env.hosting (backup at: $backup)\n";
    exit(0);
} else {
    echo "No changes made to .env.hosting\n";
    exit(0);
}

function parseEnvFile($path) {
    $res = [];
    if (!file_exists($path)) return $res;
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l === '' || strpos($l, '#') === 0) continue;
        if (strpos($l, '=') !== false) {
            list($k, $v) = explode('=', $l, 2);
            $res[trim($k)] = trim($v);
        }
    }
    return $res;
}

function isPlaceholder($v) {
    if ($v === null) return true;
    $v = trim($v, " \t\n\r\0\x0B\"'");
    if ($v === '') return true;
    $low = strtolower($v);
    if (in_array($low, ['changeme','replace_me','replace-me','replace','todo','none','n/a'])) return true;
    if (strpos($low, 'example') !== false) return true;
    if (strpos($low, '<') !== false || strpos($low, '>') !== false) return true;
    return false;
}
