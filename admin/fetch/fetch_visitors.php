<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
// Ensure CSP nonce helper is available for inline style/script nonces
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/fetch_auth.php';

requireRequestMethod('GET');

fetch_require_role(['super_admin', 'admin'], '<div class="p-6 text-center text-red-600">Unauthorized - Administrator access required</div>');
require_once __DIR__ . '/../../db.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$page = min($page, 10000);
$search = trim((string)($_GET['search'] ?? ''));
$allowedPerPage = [10, 25, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, $allowedPerPage, true)) {
  $perPage = 25;
}
$offset = ($page - 1) * $perPage;

$scanLogsExistsStmt = $pdo->query("SHOW TABLES LIKE 'visitor_pass_scan_logs'");
$hasScanLogs = (bool)$scanLogsExistsStmt->fetchColumn();

$scanJoin = '';
$usedWhenClause = '';
if ($hasScanLogs) {
  $scanJoin = "
    LEFT JOIN (
      SELECT visitor_pass_id, COUNT(*) AS scan_count
      FROM visitor_pass_scan_logs
      GROUP BY visitor_pass_id
    ) vpsl ON vpsl.visitor_pass_id = vp.id
  ";
  $usedWhenClause = "WHEN vp.status IN ('active','approved') AND COALESCE(vpsl.scan_count, 0) > 0 THEN 'used'";
}

// Get all homeowners for dropdown
$homeowners = $pdo->query("SELECT id, name FROM homeowners ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get pending visitor passes
$pendingPasses = $pdo->query("
    SELECT vp.*, h.name as homeowner_name
    FROM visitor_passes vp
    INNER JOIN homeowners h ON vp.homeowner_id = h.id
    WHERE vp.status = 'pending' AND h.account_status = 'approved'
    ORDER BY vp.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Get paginated visitor passes (with computed display_status for expired/upcoming)
$where = "";
$params = [];
if ($search !== '') {
  $where = "WHERE vp.visitor_name LIKE :search_visitor_name OR vp.visitor_plate LIKE :search_visitor_plate OR h.name LIKE :search_homeowner OR vp.status LIKE :search_status";
  $searchLike = "%{$search}%";
  $params = [
    ':search_visitor_name' => $searchLike,
    ':search_visitor_plate' => $searchLike,
    ':search_homeowner' => $searchLike,
    ':search_status' => $searchLike,
  ];
}

$countSql = "
  SELECT COUNT(*)
  FROM visitor_passes vp
  LEFT JOIN homeowners h ON vp.homeowner_id = h.id
  {$scanJoin}
  {$where}
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
  $page = $totalPages;
  $offset = ($page - 1) * $perPage;
}

$sql = "
  SELECT vp.*, h.name as homeowner_name,
    CASE
      {$usedWhenClause}
      WHEN vp.status IN ('active','approved') AND NOW() > vp.valid_until THEN 'expired'
      WHEN vp.status IN ('active','approved') AND NOW() < vp.valid_from THEN 'upcoming'
      ELSE vp.status
    END AS display_status
  FROM visitor_passes vp
  LEFT JOIN homeowners h ON vp.homeowner_id = h.id
  {$scanJoin}
  {$where}
  ORDER BY vp.created_at DESC
  LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $param) {
  $stmt->bindValue($key, $param, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$passes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!function_exists('ta_title_case')) {
  function ta_title_case($value)
  {
    $value = trim((string)$value);
    if ($value === '') {
      return '';
    }

    if (function_exists('mb_convert_case')) {
      return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    return ucwords(strtolower($value));
  }
}
?>

<style nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
.visitor-card {
    background: linear-gradient(to bottom, #ffffff, #fafafa);
}
.dark .visitor-card {
    background: linear-gradient(to bottom, #1e293b, #1e293b);
}
.visitor-card:hover {
    background: linear-gradient(to bottom, #ffffff, #f0f9ff);
}
.dark .visitor-card:hover {
    background: linear-gradient(to bottom, #1e293b, #1e3a5f);
}
.info-grid {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
}
.dark .info-grid {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
}
</style>

<h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-6 flex items-center gap-3">
    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
    </svg>
    Visitor Pass Management
</h2>



<!-- All Visitor Passes Table -->

<div class="flex items-center gap-3 mb-4 flex-wrap">
  <button id="createPassBtn" class="ta-btn ta-btn-primary">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    Create Visitor Pass
  </button>
  <button id="refreshPassesBtn" class="ta-btn ta-btn-secondary">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
    Refresh
  </button>
  <button id="exportPassesBtn" class="ta-btn ta-btn-success">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
    Export CSV
  </button>
  <div class="flex items-center gap-2 ml-auto">
    <div class="relative flex items-center">
      <svg class="absolute left-3 h-5 w-5 text-gray-500 dark:text-gray-400 pointer-events-none flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
      </svg>
      <input type="text" id="visitorsSearchInput"
        class="h-10 px-4 pl-12 border border-gray-300 dark:border-slate-600 rounded-lg min-w-[280px] text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all dark:bg-slate-700 dark:text-gray-200"
        value="<?php echo htmlspecialchars($search); ?>"
        placeholder="Search visitor passes...">
    </div>
    <select id="visitorsPerPage" class="ta-select" title="Rows per page">
      <?php foreach ($allowedPerPage as $opt): ?>
        <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?> / page</option>
      <?php endforeach; ?>
    </select>
    <span id="visitorsSearchCount" class="text-sm text-gray-600 font-medium whitespace-nowrap"></span>
  </div>
</div>

<div class="ta-table-wrapper">
  <table id="passesTable" class="ta-table">
    <thead>
      <tr>
        <th>Visitor Name</th>
        <th>Plate Number</th>
        <th>Homeowner</th>
        <th class="text-center">QR Code</th>
        <th>Valid From</th>
        <th>Valid Until</th>
        <th>Status</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
      <?php if (empty($passes)): ?>
        <tr>
          <td colspan="8">
            <div class="ta-empty-state">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
              </svg>
              <p>No visitor passes yet</p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($passes as $p): ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
            <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars(ta_title_case($p['visitor_name'] ?? '')); ?></td>
            <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($p['visitor_plate'] ?? ''); ?></td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars(ta_title_case($p['homeowner_name'] ?? '')); ?></td>
            <td class="px-4 py-3 text-center">
              <?php if (!empty($p['qr_code'])): ?>
                <img src="<?php echo htmlspecialchars($p['qr_code'] ?? ''); ?>" alt="QR Code" class="w-16 h-16 mx-auto qr-clickable" style="image-rendering: pixelated;">
              <?php else: ?>
                <span class="text-slate-400 text-xs">No QR</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo date('M d, Y h:i A', strtotime($p['valid_from'])); ?></td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo date('M d, Y h:i A', strtotime($p['valid_until'])); ?></td>
            <td class="px-4 py-3">
              <?php
                $displayStatus = $p['display_status'] ?? $p['status'];
                $badgeMap = ['active' => 'success', 'approved' => 'success', 'used' => 'info', 'pending' => 'warning', 'expired' => 'neutral', 'upcoming' => 'info', 'cancelled' => 'danger', 'rejected' => 'danger'];
                $badgeColor = $badgeMap[$displayStatus] ?? 'neutral';
              ?>
              <span class="ta-badge <?php echo $badgeColor; ?>">
                <?php echo htmlspecialchars(ucfirst($displayStatus)); ?>
              </span>
            </td>
            <td class="px-4 py-3 text-center">
              <?php if ($p['status'] === 'pending'): ?>
                <div class="ta-action-dropdown">
                  <button type="button" class="ta-action-btn" aria-haspopup="menu" aria-expanded="false">
                    Actions
                    <svg class="ta-chevron" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                  </button>
                  <div class="ta-action-menu" role="menu" aria-hidden="true">
                    <button type="button" role="menuitem" class="ta-action-menu-item blue editPassBtn" data-id="<?php echo $p['id']; ?>">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4h2m5.5 0a2.121 2.121 0 013 3L9 19.5 4 21l1.5-5L18.5 4z"/></svg>
                      Edit
                    </button>
                    <div class="ta-action-divider"></div>
                    <button type="button" role="menuitem" class="ta-action-menu-item green" data-action="approveVisitorPass" data-action-arg="<?php echo $p['id']; ?>">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                      Approve
                    </button>
                    <div class="ta-action-divider"></div>
                    <button type="button" role="menuitem" class="ta-action-menu-item red" data-action="rejectVisitorPass" data-action-arg="<?php echo $p['id']; ?>">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                      Reject
                    </button>
                  </div>
                </div>
              <?php elseif (($p['status'] === 'approved' || $p['status'] === 'active') && $displayStatus !== 'expired'): ?>
                <div class="ta-action-dropdown">
                  <button type="button" class="ta-action-btn" aria-haspopup="menu" aria-expanded="false">
                    Actions
                    <svg class="ta-chevron" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                  </button>
                  <div class="ta-action-menu" role="menu" aria-hidden="true">
                    <button type="button" role="menuitem" class="ta-action-menu-item blue editPassBtn" data-id="<?php echo $p['id']; ?>">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4h2m5.5 0a2.121 2.121 0 013 3L9 19.5 4 21l1.5-5L18.5 4z"/></svg>
                      Edit
                    </button>
                    <div class="ta-action-divider"></div>
                    <button type="button" role="menuitem" class="ta-action-menu-item red cancelPassBtn" data-id="<?php echo $p['id']; ?>">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                      Cancel Pass
                    </button>
                  </div>
                </div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="flex flex-col gap-2 mt-4 md:flex-row md:items-center md:justify-between">
  <p class="text-sm text-slate-600 dark:text-slate-400">
    Showing <?php echo $totalRows > 0 ? ($offset + 1) : 0; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> passes
  </p>
  <div class="flex items-center gap-1">
    <?php if ($page > 1): ?>
      <button type="button" class="visitors-page-btn ta-btn ta-btn-outline-secondary ta-btn-sm" data-page="<?php echo $page - 1; ?>">Previous</button>
    <?php endif; ?>
    <?php
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $page + 2);
      for ($i = $startPage; $i <= $endPage; $i++):
    ?>
      <button type="button" class="visitors-page-btn ta-btn ta-btn-sm <?php echo $i === $page ? 'ta-btn-primary' : 'ta-btn-outline-secondary'; ?>" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <button type="button" class="visitors-page-btn ta-btn ta-btn-outline-secondary ta-btn-sm" data-page="<?php echo $page + 1; ?>">Next</button>
    <?php endif; ?>
  </div>
</div>
