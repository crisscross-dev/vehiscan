<?php
require_once __DIR__ . '/../includes/session_guard.php';
require_once __DIR__ . '/../db.php';

// Restrict access to guards only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    header("Location: ../auth/login.php");
    exit();
}

$guardId = $_SESSION['guard_id'] ?? $_SESSION['user_id'] ?? 0;

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

// Handle mark-all-as-read (POST with CSRF + user scope)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf, $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid security token');
    }
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id IS NULL");
    $stmt->execute([$guardId]);
    header("Location: notifications.php");
    exit();
}

// Handle mark single notification as read (POST only with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf, $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid security token');
    }
    $id = intval($_POST['mark_read']);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    $stmt->execute([$id, $guardId]);
    header("Location: notifications.php");
    exit();
}

// Fetch notifications scoped to this guard
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC");
$stmt->execute([$guardId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Notifications</title>
<style>
    body {
        font-family: "Segoe UI", sans-serif;
        background-color: #f4f6f8;
        margin: 20px;
    }
    h2 {
        margin-bottom: 15px;
    }
    .top-bar {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    th, td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        font-size: 14px;
        text-align: left;
    }
    th {
        background-color: #f5f5f5;
        font-weight: 600;
    }
    .status-unread {
        color: #e74c3c;
        font-weight: bold;
    }
    .status-read {
        color: #7f8c8d;
    }
    .btn {
        padding: 6px 12px;
        font-size: 13px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    .btn-read {
        background-color: #3498db;
        color: white;
    }
    .btn-all-read {
        background-color: #2ecc71;
        color: white;
    }
    .btn-back {
        background-color: #7f8c8d;
        color: white;
    }
</style>
</head>
<body>

<h2>All Notifications</h2>

<div class="top-bar">
    <form method="POST" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
        <button type="submit" name="mark_all_read" class="btn btn-all-read">
            Mark All as Read
        </button>
    </form>
    <a href="pages/guard_side.php" class="btn btn-back">← Back</a>
</div>

<table>
    <thead>
        <tr>
            <th>Type</th>
            <th>Message</th>
            <th>Date</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($notifications)): ?>
            <tr>
                <td colspan="5" style="text-align:center;">No notifications found.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <tr>
                    <td><?php echo htmlspecialchars($n['type'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($n['message'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($n['created_at'] ?? ''); ?></td>
                    <td class="<?php echo $n['is_read'] ? 'status-read' : 'status-unread'; ?>">
                        <?php echo $n['is_read'] ? 'Read' : 'Unread'; ?>
                    </td>
                    <td>
                        <?php if (!$n['is_read']): ?>
                            <form method="POST" style="display:inline;margin:0;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                                <input type="hidden" name="mark_read" value="<?php echo $n['id']; ?>">
                                <button type="submit" class="btn btn-read">Mark as Read</button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

</body>
</html>
