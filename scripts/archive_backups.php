<?php
// archive_backups.php
// Move non-essential backups from backups/ into diagnostics_archived/backups_TIMESTAMP

$root = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR;
$backups = $root . 'backups' . DIRECTORY_SEPARATOR;
$archiveRoot = $root . 'diagnostics_archived' . DIRECTORY_SEPARATOR;
if (!is_dir($archiveRoot)) mkdir($archiveRoot, 0755, true);

if (!is_dir($backups)) {
    echo "No backups directory found.\n";
    exit(0);
}

$items = scandir($backups);
$moved = [];
$ts = date('Ymd_His');
$dest = $archiveRoot . 'backups_' . $ts . DIRECTORY_SEPARATOR;
if (!is_dir($dest)) mkdir($dest, 0755, true);

foreach ($items as $item) {
    if ($item === '.' || $item === '..') continue;
    if (in_array($item, ['.htaccess', 'README.md'])) continue;
    $src = $backups . $item;
    $dst = $dest . $item;
    if (@rename($src, $dst)) {
        $moved[] = $item;
    }
}

if (empty($moved)) {
    echo "No backup files moved.\n";
    exit(0);
}

foreach ($moved as $m) echo "Moved: $m -> diagnostics_archived/backups_$ts/" . PHP_EOL;
echo "Done. Keep backups outside webroot and update your restore process accordingly.\n";
