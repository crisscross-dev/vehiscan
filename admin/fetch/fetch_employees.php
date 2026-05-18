<?php
// Security: Role-based access control
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Unauthorized access']));
}

require_once __DIR__ . '/../../db.php';

$search = $_GET['search'] ?? '';
$role_filter_raw = trim((string)($_GET['role'] ?? ''));
$role_filter = $role_filter_raw === 'owner' ? 'homeowner' : $role_filter_raw;
$page = max(1, (int)($_GET['page'] ?? 1));
$allowedPerPage = [10, 25, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, $allowedPerPage, true)) {
  $perPage = 25;
}

$whereSql = " FROM users WHERE 1=1";
$params = [];

if ($search) {
  $whereSql .= " AND username LIKE :search";
    $params[':search'] = "%$search%";
}

if ($role_filter) {
  if ($role_filter === 'homeowner') {
    $whereSql .= " AND role IN (:role_homeowner, :role_owner)";
    $params[':role_homeowner'] = 'homeowner';
    $params[':role_owner'] = 'owner';
  } else {
    $whereSql .= " AND role = :role_filter";
    $params[':role_filter'] = $role_filter;
  }
}

$countSql = "SELECT COUNT(*)" . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalEmployees = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalEmployees / $perPage));
if ($page > $totalPages) {
  $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$sql = "SELECT id, username, role, account_status, created_at" . $whereSql . " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $param) {
  $stmt->bindValue($key, $param, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$roleSql = "SELECT role, COUNT(*) AS total" . $whereSql . " GROUP BY role";
$roleStmt = $pdo->prepare($roleSql);
$roleStmt->execute($params);
$roleCount = [];
foreach ($roleStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $roleCount[$row['role']] = (int)$row['total'];
}
?>

<!-- Page Header -->
<div class="mb-6">
  <div class="flex items-center gap-3 mb-2">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 text-white">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
      </svg>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Employee Management</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">View and manage all system employees</p>
    </div>
  </div>
</div>

<!-- Action Bar -->
<div class="flex items-center gap-2 mb-4 flex-wrap">
  <button id="createEmployeeBtn" class="ta-btn ta-btn-primary">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
    </svg>
    Add Employee
  </button>
  <button id="refreshEmployeesBtn" class="ta-btn ta-btn-secondary">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
    </svg>
    Refresh
  </button>
  <div class="flex items-center gap-2 ml-auto">
    <div class="relative flex items-center">
      <svg class="absolute left-3 h-5 w-5 text-gray-500 dark:text-gray-400 pointer-events-none flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
      </svg>
      <input type="text" id="employeeSearchInput" class="ta-input pl-10 min-w-[280px]" placeholder="Search employees..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <select id="employeeRoleFilter" class="ta-select">
      <option value="" <?= $role_filter === '' ? 'selected' : '' ?>>All Roles</option>
      <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
      <option value="guard" <?= $role_filter === 'guard' ? 'selected' : '' ?>>Guard</option>
      <option value="homeowner" <?= $role_filter === 'homeowner' ? 'selected' : '' ?>>Homeowner</option>
      <option value="super_admin" <?= $role_filter === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
    </select>
    <select id="employeesPerPage" class="ta-select" title="Rows per page">
      <?php foreach ($allowedPerPage as $opt): ?>
        <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?> / page</option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Bulk Actions Bar -->
<div id="bulkActionsBar" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 bg-slate-900 dark:bg-slate-800 text-white px-6 py-3 rounded-full shadow-2xl flex items-center gap-6 z-[1000] border border-slate-700 animate-bounce-subtle">
    <div class="flex items-center gap-3 pr-6 border-r border-slate-700">
        <span id="selectedCount" class="bg-blue-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">0</span>
        <span class="text-sm font-medium">Employees Selected</span>
    </div>
    <div class="flex items-center gap-3">
      <button type="button" data-action="processBulkAction" data-action-arg="suspend" class="flex items-center gap-2 px-3 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 text-amber-500 rounded-lg text-sm font-semibold transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            Suspend
        </button>
        <button type="button" data-action="processBulkAction" data-action-arg="active" class="flex items-center gap-2 px-3 py-1.5 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-500 rounded-lg text-sm font-semibold transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Activate
        </button>
    </div>
    <button type="button" data-action="clearSelection" class="text-slate-400 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

<!-- Employee Table -->
<div class="ta-table-wrapper">
  <table id="employeeTable" class="ta-table">
    <thead>
      <tr>
        <th class="w-10">
            <input type="checkbox" id="selectAllEmployees" data-action="toggleAllEmployees" data-action-arg="this" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-4 w-4">
        </th>
        <th>Username</th>
        <th>Role</th>
        <th>Status</th>
        <th>Created</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($employees)): ?>
        <tr><td colspan="5">
          <div class="ta-empty-state">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            <p>No employees found</p>
          </div>
        </td></tr>
      <?php else: ?>
        <?php foreach ($employees as $employee): ?>
          <tr class="hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors even:bg-slate-50 dark:even:bg-slate-800/50">
            <td class="px-4 py-3">
              <input type="checkbox" data-action="updateEmployeeBulkBar" class="employee-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500 h-4 w-4" data-id="<?= $employee['id'] ?>">
            </td>
            <td><?= htmlspecialchars($employee['username'] ?? '') ?></td>
            <td>
              <?php
              $displayRole = ($employee['role'] ?? '') === 'owner' ? 'homeowner' : ($employee['role'] ?? '');
              $badges = [
                'admin' => 'info',
                'guard' => 'success',
                'super_admin' => 'purple',
                'homeowner' => 'success'
              ];
              $badge = $badges[$displayRole] ?? 'neutral';
              ?>
              <span class="ta-badge <?= $badge ?>">
                <?= ucfirst(str_replace('_', ' ', htmlspecialchars($displayRole))) ?>
              </span>
            </td>
            <td>
              <?php
              // Role hierarchy: super_admin > admin > guard > homeowner
              // Show account_status badge — highlights pending/suspended accounts
              $acctStatus = strtolower(trim($employee['account_status'] ?? 'active'));
              $statusBadges = [
                'active'    => 'success',
                'approved'  => 'success',
                'pending'   => 'warning',
                'suspended' => 'danger',
                'rejected'  => 'danger',
              ];
              $statusBadge = $statusBadges[$acctStatus] ?? 'neutral';
              ?>
              <span class="ta-badge <?= $statusBadge ?>">
                <?= ucfirst(htmlspecialchars($acctStatus)) ?>
              </span>
            </td>
            <td class="muted"><?= date('M d, Y', strtotime($employee['created_at'])) ?></td>
            <td>
              <div class="flex items-center justify-center">
                <div class="ta-action-dropdown">
                  <button type="button" class="ta-action-btn" aria-haspopup="menu" aria-expanded="false">
                    Actions
                    <svg class="ta-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                  </button>
                  <div class="ta-action-menu" role="menu" aria-hidden="true">
                    <button type="button" role="menuitem" class="ta-action-menu-item blue editEmployeeBtn" data-id="<?= $employee['id'] ?>">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                      Edit
                    </button>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="flex flex-col gap-2 mt-4 md:flex-row md:items-center md:justify-between">
  <p class="text-sm text-slate-600 dark:text-slate-400">
    Showing <?php echo $totalEmployees > 0 ? ($offset + 1) : 0; ?>-<?php echo min($offset + $perPage, $totalEmployees); ?> of <?php echo $totalEmployees; ?> employees
  </p>
  <div class="flex items-center gap-1">
    <?php if ($page > 1): ?>
      <button type="button" class="employees-page-btn ta-btn ta-btn-outline-secondary ta-btn-sm" data-page="<?php echo $page - 1; ?>">Previous</button>
    <?php endif; ?>
    <?php
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $page + 2);
      for ($i = $startPage; $i <= $endPage; $i++):
    ?>
      <button type="button" class="employees-page-btn ta-btn ta-btn-sm <?php echo $i === $page ? 'ta-btn-primary' : 'ta-btn-outline-secondary'; ?>" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <button type="button" class="employees-page-btn ta-btn ta-btn-outline-secondary ta-btn-sm" data-page="<?php echo $page + 1; ?>">Next</button>
    <?php endif; ?>
  </div>
</div>

<!-- Stats Cards (TailAdmin Pattern) -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
  <div class="ta-stat-card">
    <div class="ta-stat-icon purple">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <div class="ta-stat-label">Total Employees</div>
      <div class="ta-stat-value"><?= $totalEmployees ?></div>
    </div>
  </div>
  
  <div class="ta-stat-card">
    <div class="ta-stat-icon indigo">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <div class="ta-stat-label">Admins</div>
      <div class="ta-stat-value"><?= $roleCount['admin'] ?? 0 ?></div>
    </div>
  </div>
  
  <div class="ta-stat-card">
    <div class="ta-stat-icon green">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <div class="ta-stat-label">Guards</div>
      <div class="ta-stat-value"><?= $roleCount['guard'] ?? 0 ?></div>
    </div>
  </div>
</div>
