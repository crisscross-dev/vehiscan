<?php
// archive_diagnostics.php
// Move likely-sensitive diagnostic and capture files out of webroot into diagnostics_archived/.
// Safe to run multiple times.

$root = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
$archiveDir = $root . 'diagnostics_archived' . DIRECTORY_SEPARATOR;
if (!is_dir($archiveDir)) {
    mkdir($archiveDir, 0755, true);
}

$patterns = [
    '/^cookie/i',
    '/cookiejar/i',
    '/headers?/i',
    '/resp/i',
    '/\.body$/i',
    '/\.bak$/i',
    '/\.backup$/i',
    '/\.log$/i',
    '/\.txt$/i',
    '/\.sql$/i',
];

$moved = [];
$iterator = new DirectoryIterator($root);
foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) continue;
    $name = $fileInfo->getFilename();
    // skip files we do not want to touch
    if (in_array($name, ['README.md', '.gitignore', '.htaccess', 'composer.json', 'package.json', 'robots.txt'])) continue;

    foreach ($patterns as $pat) {
        if (preg_match($pat, $name)) {
            $src = $fileInfo->getPathname();
            $dst = $archiveDir . $name;
            // avoid overwriting existing archived copy
            if (file_exists($dst)) {
                $dst = $archiveDir . time() . '_' . $name;
            }
            if (@rename($src, $dst)) {
                $moved[] = [$name, $dst];
            }
            break;
        }
    }
}

if (empty($moved)) {
    echo "No diagnostic/capture files found in project root to archive.\n";
    exit(0);
}

foreach ($moved as [$n, $d]) {
    echo "Archived: $n -> diagnostics_archived/" . basename($d) . "\n";
}

echo "Done. Keep diagnostics_archived/.htaccess in place to prevent web access.\n";
