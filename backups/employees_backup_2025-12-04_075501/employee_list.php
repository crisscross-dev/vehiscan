<?php
/**
 * Employee List
 * View and manage all employees
 */

// Check Super Admin or Admin session
session_name('vehiscan_superadmin');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    session_write_close();
    session_name('vehiscan_admin');
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../db.php';

$isSuperAdmin = ($_SESSION['role'] === 'super_admin');

// Fetch all employees
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';

$sql = "SELECT id, username, role, created_at FROM users WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND username LIKE ?";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
}

if ($role_filter) {
    $sql .= " AND role = ?";
    $params[] = $role_filter;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management — VehiScan</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css">
    <link rel="stylesheet" href="../assets/css/system.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/admin/admin.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="m-0 p-0 overflow-hidden bg-gray-50">
    <div class="flex h-screen w-full">
        <!-- Sidebar -->
        <aside id="sidebar" class="sidebar-transition sidebar-open relative flex flex-col border-r bg-sidebar text-sidebar-foreground overflow-x-hidden">
            <div class="flex h-14 items-center border-b border-sidebar-border px-4">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center">
                    <img src="../assets/images/vehiscan-logo.png" alt="VehiScan Logo" class="h-full w-full object-contain">
                </div>
                <span class="sidebar-text ml-3 text-left font-bold text-lg">VehiScan</span>
            </div>
            <div class="flex-1 overflow-y-auto hide-scrollbar py-2">
                <div class="mb-4 px-3">
                    <div class="sidebar-text mb-2 px-2 text-xs font-semibold text-sidebar-foreground opacity-70">MAIN MENU</div>
                    <div class="space-y-1">
                        <a href="../admin/admin_panel.php" class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-sidebar-foreground hover:bg-sidebar-accent">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                            <span class="sidebar-text">Dashboard</span>
                        </a>
                        <a href="employee_list.php" class="menu-item active flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all hover:bg-sidebar-accent">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <span class="sidebar-text">Employee Management</span>
                        </a>
                    </div>
                </div>
            </div>
            <div class="border-t border-sidebar-border p-4">
                <div class="flex items-center gap-3 px-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white font-semibold text-sm">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="sidebar-text flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?></p>
                        <p class="text-xs opacity-70"><?php echo ucfirst($_SESSION['role'] ?? 'admin'); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col overflow-hidden">
            <header class="border-b bg-white px-6 py-4 flex items-center gap-4">
                <h1 class="text-lg font-semibold text-gray-900">Employee Management</h1>
                <p class="text-sm text-gray-600">View and manage all system employees</p>
            </header>

            <div class="flex-1 overflow-y-auto p-6">
                <div class="max-w-7xl mx-auto space-y-6">
                    <!-- Search & Filter -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                        <form method="GET" class="flex gap-3 items-center flex-wrap">
                            <div class="flex-1 min-w-[250px]">
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       placeholder="Search by username, email, or name...">
                            </div>
                            <select name="role" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Roles</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="guard" <?= $role_filter === 'guard' ? 'selected' : '' ?>>Guard</option>
                                <option value="owner" <?= $role_filter === 'owner' ? 'selected' : '' ?>>Owner</option>
                            </select>
                            <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors">
                                Search
                            </button>
                            <?php if ($search || $role_filter): ?>
                                <a href="employee_list.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200 transition-colors">
                                    Clear
                                </a>
                            <?php endif; ?>
                            <a href="employee_registration.php" class="ml-auto px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg text-sm font-bold hover:from-blue-600 hover:to-blue-700 transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Add Employee
                            </a>
                        </form>
                    </div>

                    <!-- Employee Table -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b border-gray-200">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Created</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                                </tr>
                        </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($employees)): ?>
                                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No employees found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($employees as $employee): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4 text-sm text-gray-900 font-medium"><?= htmlspecialchars($employee['id']) ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($employee['username']) ?></td>
                                            <td class="px-6 py-4 text-sm">
                                                <?php
                                                $badges = [
                                                    'admin' => 'bg-purple-100 text-purple-800',
                                                    'guard' => 'bg-blue-100 text-blue-800',
                                                    'owner' => 'bg-green-100 text-green-800'
                                                ];
                                                $badge = $badges[$employee['role']] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge ?>">
                                                    <?= ucfirst(htmlspecialchars($employee['role'])) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600"><?= date('M d, Y', strtotime($employee['created_at'])) ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="employee_edit.php?id=<?= $employee['id'] ?>" 
                                                       class="inline-flex items-center px-3 py-1.5 bg-blue-500 text-white text-sm font-medium rounded-md hover:bg-blue-600 transition-colors gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                        Edit
                                                    </a>
                                                    <button onclick="deleteEmployee(<?= $employee['id'] ?>, '<?= htmlspecialchars($employee['username'], ENT_QUOTES) ?>')"
                                                            class="inline-flex items-center px-3 py-1.5 bg-red-500 text-white text-sm font-medium rounded-md hover:bg-red-600 transition-colors gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                    </table>
                </div>
            </div>

                    <!-- Summary Stats -->
                    <div class="grid grid-cols-3 gap-6">
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div class="text-sm font-semibold text-gray-600 mb-2">Total Employees</div>
                            <div class="text-3xl font-bold text-gray-900"><?= $totalEmployees ?></div>
                        </div>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div class="text-sm font-semibold text-gray-600 mb-2">Admins</div>
                            <div class="text-3xl font-bold text-purple-600"><?= $roleCount['admin'] ?? 0 ?></div>
                        </div>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <div class="text-sm font-semibold text-gray-600 mb-2">Guards</div>
                            <div class="text-3xl font-bold text-blue-600"><?= $roleCount['guard'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
        function deleteEmployee(id, username) {
            Swal.fire({
                title: 'Delete Employee?',
                text: `Delete "${username}"? This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel',
                heightAuto: false
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('employee_delete.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({id: id})
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({icon: 'success', title: 'Deleted!', text: 'Employee deleted.', heightAuto: false}).then(() => location.reload());
                        } else {
                            Swal.fire({icon: 'error', title: 'Error', text: data.message || 'Failed to delete.', heightAuto: false});
                        }
                    })
                    .catch(() => Swal.fire({icon: 'error', title: 'Error', text: 'An error occurred.', heightAuto: false}));
                }
            });
        }
    </script>
</body>
</html>
