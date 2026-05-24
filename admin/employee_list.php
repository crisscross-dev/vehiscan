<?php
/**
 * Employee List - Admin Panel
 * View and manage all system employees
 */
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/session_admin_unified.php';

// TODO [DEPRECATION NOTICE]:
// This standalone employee_list.php file appears to be superseded by the routing 
// in admin_panel.php -> fetch_employees.php. It should be reviewed for removal 
// to prevent maintainers from editing the wrong file.

$isSuperAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../auth/login.php');
    exit();
}

$csrf = $_SESSION['csrf_token'];

require_once __DIR__ . '/../db.php';

$search = $_GET['search'] ?? '';
$role_filter_raw = trim((string)($_GET['role'] ?? ''));
$role_filter = $role_filter_raw === 'owner' ? 'homeowner' : $role_filter_raw;

$sql = "SELECT id, username, role, created_at FROM users WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND username LIKE ?";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
}

if ($role_filter) {
    if ($role_filter === 'homeowner') {
        $sql .= " AND role IN (?, ?)";
        $params[] = 'homeowner';
        $params[] = 'owner';
    } else {
        $sql .= " AND role = ?";
        $params[] = $role_filter;
    }
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalEmployees = count($employees);
$roleCount = array_count_values(array_column($employees, 'role'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management — VehiScan</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="../assets/css/tailadmin-components.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/tailadmin-components.css'); ?>">
    <link rel="stylesheet" href="../assets/css/system.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/system.css'); ?>">
    <link rel="stylesheet" href="../assets/css/admin/admin.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/admin/admin.css'); ?>">
    <script src="../assets/js/libs/sweetalert2.all.min.js"></script>
</head>
<body class="m-0 p-0 overflow-hidden bg-gray-100 dark:bg-slate-950">
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
                    <div class="sidebar-text mb-2 px-2 text-xs font-semibold text-sidebar-foreground opacity-70">Main menu</div>
                    <div class="space-y-1">
                        <a href="admin_panel.php" class="menu-item flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-all text-sidebar-foreground hover:bg-sidebar-accent">
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
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-600 text-white font-semibold text-sm">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="sidebar-text flex-1 min-w-0">
                        <?php
                            $sessionRole = (string)($_SESSION['role'] ?? 'admin');
                            $roleDisplay = $sessionRole === 'owner' ? 'homeowner' : $sessionRole;
                        ?>
                        <p class="text-sm font-semibold truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?></p>
                        <p class="text-xs opacity-70"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $roleDisplay))); ?></p>
                    </div>
                </div>
            </div>
        </aside>

        <main class="flex-1 flex flex-col overflow-hidden">
            <header class="border-b bg-white dark:bg-slate-900 dark:border-slate-700 px-6 py-4 flex items-center gap-4">
                <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Employee Management</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">View and manage all system employees</p>
            </header>

            <div class="flex-1 overflow-y-auto p-6">
                <div class="max-w-7xl mx-auto space-y-6">
                    <!-- Search & Filter -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-4">
                        <form method="GET" class="flex gap-3 items-center flex-wrap">
                            <div class="flex-1 min-w-[250px]">
                                <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                                       placeholder="Search by username...">
                            </div>
                            <select name="role" class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">All Roles</option>
                                <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="guard" <?= $role_filter === 'guard' ? 'selected' : '' ?>>Guard</option>
                                <option value="homeowner" <?= $role_filter === 'homeowner' ? 'selected' : '' ?>>Homeowner</option>
                            </select>
                            <button type="submit" class="px-6 py-2 bg-blue-500 text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors">
                                Search
                            </button>
                            <?php if ($search || $role_filter): ?>
                                <a href="employee_list.php" class="px-6 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-semibold hover:bg-gray-200 transition-colors">
                                    Clear
                                </a>
                            <?php endif; ?>
                            <a href="employee_registration.php" class="ml-auto px-6 py-2 bg-gray-700 text-white rounded-lg text-sm font-bold hover:bg-gray-800 transition-all shadow-md hover:shadow-lg flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                Add Employee
                            </a>
                        </form>
                    </div>

                    <!-- Employee Table -->
                    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-slate-700 border-b border-gray-200 dark:border-slate-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Role</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Created</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                <?php if (empty($employees)): ?>
                                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No employees found</td></tr>
                                <?php else: ?>
                                    <?php 
                                    $row_num = 1;
                                    foreach ($employees as $employee): 
                                    ?>
                                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors">
                                            <td class="px-6 py-4 text-sm text-gray-500 font-medium"><?= $row_num++ ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($employee['username'] ?? '') ?></td>
                                            <td class="px-6 py-4 text-sm">
                                                <?php
                                                $displayRole = ($employee['role'] ?? '') === 'owner' ? 'homeowner' : ($employee['role'] ?? '');
                                                $badges = [
                                                    'admin' => 'bg-gray-200 text-gray-800',
                                                    'guard' => 'bg-gray-200 text-gray-800',
                                                    'homeowner' => 'bg-emerald-100 text-emerald-800'
                                                ];
                                                $badge = $badges[$displayRole] ?? 'bg-gray-100 text-gray-800';
                                                ?>
                                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge ?>">
                                                    <?= ucfirst(htmlspecialchars($displayRole)) ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-600"><?= date('M d, Y', strtotime($employee['created_at'])) ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="employee_edit.php?id=<?= $employee['id'] ?>" 
                                                       class="inline-flex items-center px-3 py-1.5 bg-gray-700 text-white text-sm font-medium rounded-md hover:bg-gray-800 transition-colors gap-1">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                                        Edit
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-6">
                        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                            <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">Total Employees</div>
                            <div class="text-3xl font-bold text-gray-900 dark:text-white"><?= $totalEmployees ?></div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                            <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">Admins</div>
                            <div class="text-3xl font-bold text-purple-600"><?= $roleCount['admin'] ?? 0 ?></div>
                        </div>
                        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                            <div class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-2">Guards</div>
                            <div class="text-3xl font-bold text-blue-600"><?= $roleCount['guard'] ?? 0 ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const csrfToken = <?= json_encode($csrf ?? '') ?>;
        // Delete functionality removed - admins cannot delete employee records
    </script>

    <!-- TailAdmin Action Dropdown Handler -->
    <script>
    (function(){
      function closeDropdown(dd) {
        dd.classList.remove('open');
        const menu = dd.querySelector('.ta-action-menu');
        if (menu) menu.removeAttribute('style');
      }
      function closeAllDropdowns(except) {
        document.querySelectorAll('.ta-action-dropdown.open').forEach(function(d) {
          if (d !== except) closeDropdown(d);
        });
      }
      function positionDrop(dd) {
        const menu = dd.querySelector('.ta-action-menu');
        const trigger = dd.querySelector('.ta-action-btn');
        if (!menu || !trigger) return;
        // Measure actual menu width by temporarily showing it off-screen
        menu.style.cssText = 'position:fixed;visibility:hidden;display:block;right:auto;width:auto;left:-9999px;top:0;';
        var menuWidth = Math.max(menu.offsetWidth, 160);
        var rect = trigger.getBoundingClientRect();
        var spaceBelow = window.innerHeight - rect.bottom;
        var dropUp = spaceBelow < 180 && rect.top > 180;
        var leftPos = rect.right - menuWidth;
        if (leftPos < 4) leftPos = 4;
        if (leftPos + menuWidth > window.innerWidth - 4) leftPos = window.innerWidth - menuWidth - 4;
        menu.style.cssText = [
          'position:fixed', 'z-index:9980', 'width:' + menuWidth + 'px', 'right:auto', 'margin:0',
          'left:' + leftPos + 'px',
          dropUp
            ? 'top:auto;bottom:' + (window.innerHeight - rect.top + 4) + 'px'
            : 'top:' + (rect.bottom + 4) + 'px;bottom:auto'
        ].join(';');
        dd.classList.toggle('drop-up', dropUp);
      }
      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.ta-action-btn');
        if (btn) {
          e.stopPropagation();
          const dd = btn.closest('.ta-action-dropdown');
          const wasOpen = dd.classList.contains('open');
          closeAllDropdowns(dd);
          if (wasOpen) { closeDropdown(dd); } else { dd.classList.add('open'); positionDrop(dd); }
          return;
        }
        const item = e.target.closest('.ta-action-menu-item');
        if (item) { closeDropdown(item.closest('.ta-action-dropdown')); return; }
        closeAllDropdowns(null);
      });
      ['scroll', 'resize'].forEach(function(ev) {
        window.addEventListener(ev, function() {
          const open = document.querySelector('.ta-action-dropdown.open');
          if (open) positionDrop(open);
        }, { passive: true });
      });
    })();
    </script>
</body>
</html>
