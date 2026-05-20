<?php
// admin/diagnostics/image_diagnostic.php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/session_admin_unified.php';

// Only allow admin and super_admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo "<h1>Unauthorized</h1>";
    exit;
}

function checkPath($path) {
    $full = __DIR__ . '/../' . $path;
    return file_exists($full);
}

$missing = [
    'homeowners' => [],
    'vehicles' => [],
];

// Check homeowners images referenced in homeowners table
try {
    $stmt = $pdo->query("SELECT id, plate_number, owner_img, car_img FROM homeowners");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        // owner_img
        $owner = $r['owner_img'] ?? null;
        if ($owner) {
            $p = $owner;
            // Normalize stored path
            if (!preg_match('#^uploads/#', $p)) {
                if (preg_match('#homeowners/#', $p)) $p = 'uploads/' . preg_replace('#^/+#','', $p);
                elseif (preg_match('#vehicles/#', $p)) $p = 'uploads/' . preg_replace('#^/+#','', $p);
                else $p = 'uploads/homeowners/' . basename($p);
            }
            if (!checkPath($p)) {
                $missing['homeowners'][] = ['id'=>$r['id'],'plate'=>$r['plate_number'],'path'=>$p];
            }
        }
        // car_img
        $car = $r['car_img'] ?? null;
        if ($car) {
            $p = $car;
            if (!preg_match('#^uploads/#', $p)) {
                if (preg_match('#vehicles/#', $p)) $p = 'uploads/' . preg_replace('#^/+#','', $p);
                elseif (preg_match('#homeowners/#', $p)) $p = 'uploads/' . preg_replace('#^/+#','', $p);
                else $p = 'uploads/vehicles/' . basename($p);
            }
            if (!checkPath($p)) {
                $missing['vehicles'][] = ['id'=>$r['id'],'plate'=>$r['plate_number'],'path'=>$p];
            }
        }
    }
} catch (Exception $e) {
    $missing['error'] = $e->getMessage();
}

?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Image Diagnostic</title>
<style nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">body{font-family:Segoe UI,Arial;padding:20px;background:#f7f9fb}h1{color:#2c3e50}table{border-collapse:collapse;width:100%;background:#fff}th,td{padding:8px;border:1px solid #e6e6e6;text-align:left}th{background:#f1f5f9}</style>
</head>
<body>
<h1>Image Diagnostic</h1>
<p>Checking referenced homeowner and vehicle images in DB against filesystem (project uploads folder).</p>

<h2>Missing Homeowner Images (<?= count($missing['homeowners']) ?>)</h2>
<?php if (count($missing['homeowners'])===0): ?>
    <p>None found.</p>
<?php else: ?>
    <table>
        <thead><tr><th>ID</th><th>Plate</th><th>Expected Path</th></tr></thead>
        <tbody>
        <?php foreach($missing['homeowners'] as $m): ?>
            <tr><td><?= htmlspecialchars($m['id']) ?></td><td><?= htmlspecialchars($m['plate']) ?></td><td><?= htmlspecialchars($m['path']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2>Missing Vehicle Images (<?= count($missing['vehicles']) ?>)</h2>
<?php if (count($missing['vehicles'])===0): ?>
    <p>None found.</p>
<?php else: ?>
    <table>
        <thead><tr><th>ID</th><th>Plate</th><th>Expected Path</th></tr></thead>
        <tbody>
        <?php foreach($missing['vehicles'] as $m): ?>
            <tr><td><?= htmlspecialchars($m['id']) ?></td><td><?= htmlspecialchars($m['plate']) ?></td><td><?= htmlspecialchars($m['path']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (isset($missing['error'])): ?>
    <h3>Error</h3>
    <pre><?= htmlspecialchars($missing['error']) ?></pre>
<?php endif; ?>

</body>
</html>
