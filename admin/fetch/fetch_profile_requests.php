<?php
// Security: Role-based access control
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/common_utilities.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    exit(json_encode(['error' => 'Unauthorized access']));
}

require_once __DIR__ . '/../../db.php';

// Check if profile_update_requests table exists
try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'profile_update_requests'");
    $tableExists = $tableCheck->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

// Pagination
$page = max(1, intval($_GET['p'] ?? 1));
$page = min($page, 10000);
$allowedPerPage = [10, 15, 25, 50];
$perPage = (int)($_GET['per_page'] ?? 15);
if (!in_array($perPage, $allowedPerPage, true)) {
    $perPage = 15;
}
$offset = ($page - 1) * $perPage;

// Search
$search = trim($_GET['search'] ?? '');

// Status filter
$statusFilter = $_GET['status'] ?? '';

// Fetch data
$requests = [];
$totalRequests = 0;
$pendingCount = 0;
$acknowledgedCount = 0;
$completedCount = 0;
$rejectedCount = 0;
$dbError = false;

if ($tableExists) {
    try {
        // Get counts by status
        $countStmt = $pdo->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'acknowledged' THEN 1 ELSE 0 END) as acknowledged,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM profile_update_requests
        ");
        $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
        $pendingCount = (int)($counts['pending'] ?? 0);
        $acknowledgedCount = (int)($counts['acknowledged'] ?? 0);
        $completedCount = (int)($counts['completed'] ?? 0);
        $rejectedCount = (int)($counts['rejected'] ?? 0);

        // Build query with filters
        $sql = "
            SELECT pur.*, h.name as homeowner_name, h.first_name, h.last_name,
                   h.plate_number, h.contact_number, h.owner_img
            FROM profile_update_requests pur
            LEFT JOIN homeowners h ON pur.homeowner_id = h.id
            WHERE 1=1
        ";
        $params = [];

        if ($search) {
            $sql .= " AND (h.name LIKE ? OR h.first_name LIKE ? OR h.last_name LIKE ? OR pur.request_text LIKE ?)";
            $searchParam = "%{$search}%";
            $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        }

        if ($statusFilter && in_array($statusFilter, ['pending', 'acknowledged', 'completed', 'rejected'])) {
            $sql .= " AND pur.status = ?";
            $params[] = $statusFilter;
        }

        // Count for pagination
        $countSql = "SELECT COUNT(*) FROM (" . $sql . ") as filtered";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRequests = (int)$countStmt->fetchColumn();

        // Get paginated results
        $sql .= " ORDER BY FIELD(pur.status, 'pending', 'acknowledged', 'completed', 'rejected'), pur.created_at DESC";
        $sql .= " LIMIT " . intval($perPage) . " OFFSET " . intval($offset);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        error_log("Profile requests fetch error: " . $e->getMessage());
        $dbError = true;
    }
}

$totalPages = max(1, ceil($totalRequests / $perPage));
$grandTotal = $pendingCount + $acknowledgedCount + $completedCount + $rejectedCount;
$hasActiveFilters = ($search !== '' || $statusFilter !== '');
$statusLabels = [
    'pending' => 'Pending',
    'acknowledged' => 'Acknowledged',
    'completed' => 'Completed',
    'rejected' => 'Rejected'
];
$activeStatusLabel = $statusLabels[$statusFilter] ?? 'All';

// Helper: generate initials from name
function getInitials($name) {
    $parts = array_filter(explode(' ', trim($name)));
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

// Helper: status badge classes
function getStatusBadge($status) {
    $badges = [
        'pending'      => ['bg' => 'bg-yellow-100 text-yellow-800', 'dot' => 'bg-yellow-500', 'label' => 'Pending'],
        'acknowledged' => ['bg' => 'bg-blue-100 text-blue-800', 'dot' => 'bg-blue-500', 'label' => 'Acknowledged'],
        'completed'    => ['bg' => 'bg-green-100 text-green-800', 'dot' => 'bg-green-500', 'label' => 'Completed'],
        'rejected'     => ['bg' => 'bg-red-100 text-red-800', 'dot' => 'bg-red-500', 'label' => 'Rejected'],
    ];
    return $badges[$status] ?? $badges['pending'];
}
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-orange-500 to-orange-600 text-white">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Profile Update Requests</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Review and manage homeowner profile change requests</p>
        </div>
    </div>
</div>

<?php if ($dbError): ?>
    <div class="ta-alert ta-alert-danger mb-4">
        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
        </svg>
        <span class="font-medium">Database error occurred while loading requests.</span>
    </div>
<?php elseif (!$tableExists): ?>
    <div class="ta-alert ta-alert-warning mb-4">
        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <span class="font-medium">The profile_update_requests table has not been created yet. Run migrations first.</span>
    </div>
<?php else: ?>

    <!-- Stat Cards Row -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4 mb-6 animate-fade-in-up animate-delay-1">
        <button type="button" class="ta-stat-card glass stat-filter-btn cursor-pointer text-left <?php echo $statusFilter === '' ? 'ring-2 ring-blue-500' : ''; ?>"
            data-status="">
            <div class="ta-stat-icon indigo">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <p class="ta-stat-label">Total</p>
                <p class="ta-stat-value"><?php echo $grandTotal; ?></p>
            </div>
        </button>
        <button type="button" class="ta-stat-card glass stat-filter-btn cursor-pointer text-left <?php echo $statusFilter === 'pending' ? 'ring-2 ring-amber-500' : ''; ?>"
            data-status="pending">
            <div class="ta-stat-icon amber">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <p class="ta-stat-label">Pending</p>
                <p class="ta-stat-value"><?php echo $pendingCount; ?></p>
            </div>
        </button>
        <button type="button" class="ta-stat-card glass stat-filter-btn cursor-pointer text-left <?php echo $statusFilter === 'acknowledged' ? 'ring-2 ring-blue-500' : ''; ?>"
            data-status="acknowledged">
            <div class="ta-stat-icon blue">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <p class="ta-stat-label">Acknowledged</p>
                <p class="ta-stat-value"><?php echo $acknowledgedCount; ?></p>
            </div>
        </button>
        <button type="button" class="ta-stat-card glass stat-filter-btn cursor-pointer text-left <?php echo $statusFilter === 'completed' ? 'ring-2 ring-green-500' : ''; ?>"
            data-status="completed">
            <div class="ta-stat-icon green">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <p class="ta-stat-label">Completed</p>
                <p class="ta-stat-value"><?php echo $completedCount; ?></p>
            </div>
        </button>
        <button type="button" class="ta-stat-card glass stat-filter-btn cursor-pointer text-left <?php echo $statusFilter === 'rejected' ? 'ring-2 ring-red-500' : ''; ?>"
            data-status="rejected">
            <div class="ta-stat-icon red">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </div>
            <div class="ta-stat-content">
                <p class="ta-stat-label">Rejected</p>
                <p class="ta-stat-value"><?php echo $rejectedCount; ?></p>
            </div>
        </button>
    </div>


    <div class="mb-4 glass border-blue-100/50 dark:border-slate-700/50 px-4 py-4 rounded-2xl animate-fade-in-up animate-delay-2">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center text-blue-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-sm font-bold text-blue-900 dark:text-slate-100">Workflow Optimization Guide</p>
                    <p class="text-xs text-blue-700/70 dark:text-slate-400 mt-0.5">Follow this path for consistent request handling.</p>
                </div>
            </div>
            <div class="text-xs text-blue-700 dark:text-slate-400 bg-blue-100/50 dark:bg-slate-700/50 px-2 py-1 rounded-lg">
                Quick Tip: Click status cards to filter.
            </div>
        </div>
        <div class="mt-4 flex items-center gap-2 flex-wrap text-[10px] font-bold uppercase tracking-wider">
            <span class="inline-flex items-center rounded-full bg-yellow-100 text-yellow-800 px-3 py-1.5 border border-yellow-200">Pending</span>
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="inline-flex items-center rounded-full bg-blue-100 text-blue-800 px-3 py-1.5 border border-blue-200">Acknowledge</span>
            <span class="text-gray-400 font-normal normal-case">or</span>
            <span class="inline-flex items-center rounded-full bg-red-100 text-red-800 px-3 py-1.5 border border-red-200">Reject</span>
            <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="inline-flex items-center rounded-full bg-green-100 text-green-800 px-3 py-1.5 border border-green-200">Complete</span>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="flex items-center gap-2 mb-2 flex-wrap">
        <button id="refreshProfileReqs" class="ta-btn ta-btn-secondary">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Refresh
        </button>
        <?php if ($hasActiveFilters): ?>
            <button id="profileReqClearFilters" class="ta-btn ta-btn-secondary" title="Clear search and status filters">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Clear Filters
            </button>
        <?php endif; ?>
        <div class="flex items-center gap-2 ml-auto">
            <div class="relative flex items-center flex-1 min-w-0 sm:max-w-md">
                <svg class="absolute left-3 h-5 w-5 text-gray-500 dark:text-gray-400 pointer-events-none flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" id="profileReqSearch"
                    class="h-10 px-4 pl-12 w-full border border-gray-300 dark:border-slate-600 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all dark:bg-slate-700 dark:text-gray-200"
                    placeholder="Search requests..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select id="profileReqPerPage" class="ta-select" title="Rows per page">
                <?php foreach ($allowedPerPage as $opt): ?>
                    <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?> / page</option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="mb-4 flex items-center justify-between flex-wrap gap-2 text-xs">
        <div class="text-gray-600 dark:text-gray-300">
            Showing <?php echo $totalRequests; ?> of <?php echo $grandTotal; ?> requests
        </div>
        <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400">
            <span class="inline-flex items-center rounded-full border border-gray-300 dark:border-slate-600 px-2.5 py-1">
                Status: <?php echo htmlspecialchars($activeStatusLabel); ?>
            </span>
            <?php if ($search !== ''): ?>
                <span class="inline-flex items-center rounded-full border border-gray-300 dark:border-slate-600 px-2.5 py-1">
                    Search: <?php echo htmlspecialchars($search); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Requests Table -->
    <div class="ta-table-wrapper glass animate-fade-in-up animate-delay-3 overflow-x-auto">
        <table class="ta-table w-full">
            <thead>
                <tr>
                    <th class="text-left">Homeowner</th>
                    <th class="text-left">Request</th>
                    <th class="text-center whitespace-nowrap">Status</th>
                    <th class="text-left whitespace-nowrap">Date</th>
                    <th class="text-center whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="ta-empty-state">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p>
                                    <?php echo $search ? 'No matching requests found' : ($statusFilter ? 'No ' . $statusFilter . ' requests' : 'No profile update requests yet'); ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $r):
                        $badge = getStatusBadge($r['status']);
                        $displayName = htmlspecialchars($r['homeowner_name'] ?? ($r['first_name'] . ' ' . $r['last_name']) ?? 'Unknown');
                        $initials = getInitials($displayName);
                    ?>
                        <tr class="hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                            <!-- Homeowner -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($r['owner_img'])): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($r['owner_img']); ?>"
                                            alt="" class="h-9 w-9 rounded-full object-cover flex-shrink-0">
                                    <?php else: ?>
                                        <div class="h-9 w-9 rounded-full bg-gray-200 flex items-center justify-center text-xs font-semibold text-gray-600 flex-shrink-0">
                                            <?php echo $initials; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-slate-900 dark:text-slate-200 truncate"><?php echo $displayName; ?></p>
                                        <?php if (!empty($r['plate_number'])): ?>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($r['plate_number']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <!-- Request Text -->
                            <td class="px-4 py-3">
                                <p class="text-slate-700 dark:text-slate-300 max-w-md whitespace-normal break-words leading-5" title="<?php echo htmlspecialchars($r['request_text']); ?>">
                                    <?php echo htmlspecialchars($r['request_text']); ?>
                                </p>
                                <?php if (!empty($r['admin_notes'])): ?>
                                    <p class="text-xs text-slate-400 mt-1 truncate" title="<?php echo htmlspecialchars($r['admin_notes']); ?>">
                                        Admin: <?php echo htmlspecialchars($r['admin_notes']); ?>
                                    </p>
                                <?php endif; ?>
                            </td>
                            <!-- Status -->
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium <?php echo $badge['bg']; ?>">
                                    <span class="h-1.5 w-1.5 rounded-full <?php echo $badge['dot']; ?>"></span>
                                    <?php echo $badge['label']; ?>
                                </span>
                            </td>
                            <!-- Date -->
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs whitespace-nowrap">
                                <?php echo formatDisplayDateTime($r['created_at']); ?>
                            </td>
                            <!-- Actions -->
                            <td class="px-4 py-3 text-center">
                                <?php if ($r['status'] === 'pending'): ?>
                                        <div class="ta-action-dropdown">
                                            <button type="button" class="ta-action-btn" aria-haspopup="menu" aria-expanded="false">
                                                Actions
                                                <svg class="ta-chevron" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                                            </button>
                                            <div class="ta-action-menu" role="menu" aria-hidden="true">
                                                <button type="button" role="menuitem" class="ta-action-menu-item blue action-btn"
                                                    data-id="<?php echo $r['id']; ?>" data-action="acknowledged" data-homeowner="<?php echo htmlspecialchars($displayName, ENT_QUOTES); ?>" title="Acknowledge">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Acknowledge
                                                </button>
                                                <div class="ta-action-divider"></div>
                                                <button type="button" role="menuitem" class="ta-action-menu-item red action-btn"
                                                    data-id="<?php echo $r['id']; ?>" data-action="rejected" data-homeowner="<?php echo htmlspecialchars($displayName, ENT_QUOTES); ?>" title="Reject">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    Reject
                                                </button>
                                            </div>
                                        </div>
                                    <?php elseif ($r['status'] === 'acknowledged'): ?>
                                        <div class="ta-action-dropdown">
                                            <button type="button" class="ta-action-btn" aria-haspopup="menu" aria-expanded="false">
                                                Actions
                                                <svg class="ta-chevron" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                                            </button>
                                            <div class="ta-action-menu" role="menu" aria-hidden="true">
                                                <button type="button" role="menuitem" class="ta-action-menu-item green action-btn"
                                                    data-id="<?php echo $r['id']; ?>" data-action="completed" data-homeowner="<?php echo htmlspecialchars($displayName, ENT_QUOTES); ?>" title="Mark Complete">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                    Mark Complete
                                                </button>
                                                <div class="ta-action-divider"></div>
                                                <button type="button" role="menuitem" class="ta-action-menu-item red action-btn"
                                                    data-id="<?php echo $r['id']; ?>" data-action="rejected" data-homeowner="<?php echo htmlspecialchars($displayName, ENT_QUOTES); ?>" title="Reject">
                                                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    Reject
                                                </button>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400">—</span>
                                    <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between mt-4 text-sm">
            <p class="text-gray-500">
                Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $perPage, $totalRequests); ?> of <?php echo $totalRequests; ?> requests
            </p>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <button class="page-btn px-3 py-1.5 rounded-md border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" data-page="<?php echo $page - 1; ?>">
                        Previous
                    </button>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <button class="page-btn px-3 py-1.5 rounded-md border transition-colors
                        <?php echo $i === $page ? 'bg-gray-900 dark:bg-slate-600 text-white border-gray-900 dark:border-slate-600' : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700'; ?>"
                        data-page="<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <button class="page-btn px-3 py-1.5 rounded-md border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors" data-page="<?php echo $page + 1; ?>">
                        Next
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<!-- Action Modal -->
<div id="profileReqModal" class="hidden fixed inset-0 flex items-center justify-center bg-black/40 p-3" style="z-index: 10050;">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg w-full p-4 max-h-[85vh] overflow-y-auto" style="max-width: 520px;">
        <h3 id="modalTitle" class="text-base font-semibold text-gray-900 dark:text-white mb-3">Process Request</h3>
        <p id="modalContext" class="text-sm text-gray-600 dark:text-gray-300 mb-3"></p>
        <div class="mb-3">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Notes (optional)</label>
            <textarea id="modalNotes" rows="2"
                class="w-full rounded-lg border border-gray-300 dark:border-slate-600 px-3 py-2 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-slate-700 dark:text-gray-200"
                placeholder="Add any notes about this action..."></textarea>
        </div>
        <div class="flex items-center justify-end gap-2">
            <button id="modalCancel"
                class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-700 border border-gray-300 dark:border-slate-600 rounded-lg hover:bg-gray-50 dark:hover:bg-slate-600 transition-colors">
                Cancel
            </button>
            <button id="modalConfirm"
                class="px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors">
                Confirm
            </button>
        </div>
    </div>
</div>


