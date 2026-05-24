<?php
require_once __DIR__ . '/../db.php';

$id = 66;
$stmt = $pdo->prepare('SELECT id, name, owner_img, car_img FROM homeowners WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo "HOMEOWNER #$id\n";
echo json_encode($row, JSON_PRETTY_PRINT) . PHP_EOL;

$paths = [];
if (!empty($row['owner_img'])) {
    $raw = $row['owner_img'];
    $paths[] = __DIR__ . '/../uploads/' . ltrim($raw, '/');
    $paths[] = __DIR__ . '/../' . ltrim($raw, '/');
    if (strpos($raw, 'uploads/') !== 0) {
        $paths[] = __DIR__ . '/../uploads/' . ltrim($raw, '/');
    }
}

echo "\nOwner image candidates:\n";
foreach (array_unique($paths) as $p) {
    echo (file_exists($p) ? '[EXISTS] ' : '[MISSING] ') . $p . PHP_EOL;
}
