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

// admin/fetch/fetch_logs.php
require_once __DIR__ . '/../../db.php';

$guardLogFlagsExists = false;
try {
  $guardLogFlagsExists = (bool)$pdo->query("SHOW TABLES LIKE 'guard_log_flags'")->fetchColumn();
} catch (Exception $e) {
  $guardLogFlagsExists = false;
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$page = min($page, 10000);
$allowedPageSizes = [25, 50, 100, 200];
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 50;
if (!in_array($per_page, $allowedPageSizes, true)) {
  $per_page = 50;
}
$offset = ($page - 1) * $per_page;

if (!function_exists('ta_sentence_case')) {
  function ta_sentence_case($value)
  {
    $value = trim((string)$value);
    if ($value === '') {
      return '';
    }

    return ucwords(strtolower($value));
  }
}

// Filters
$plateFilter = strtoupper(trim($_GET['plate'] ?? ''));
$plateCanonical = '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$dateFromObj = null;
$dateToObj = null;

if ($plateFilter !== '') {
  $plateFilter = preg_replace('/[^A-Z0-9\- ]/', '', $plateFilter);
  $plateCanonical = str_replace([' ', '-'], '', $plateFilter);
}

// Validate and parse date filters
if ($dateFrom !== '') {
  try {
    $dateFromObj = new DateTime($dateFrom);
    $dateFromObj->setTime(0, 0, 0);
  } catch (Exception $e) {
    $dateFrom = '';
  }
}

if ($dateTo !== '') {
  try {
    $dateToObj = new DateTime($dateTo);
    $dateToObj->setTime(23, 59, 59);
  } catch (Exception $e) {
    $dateTo = '';
  }
}

// Validate date range (max 366 days)
if ($dateFromObj && $dateToObj) {
  if ($dateFromObj > $dateToObj) {
    $dateFrom = '';
    $dateTo = '';
    $dateFromObj = null;
    $dateToObj = null;
  } else {
    $interval = $dateFromObj->diff($dateToObj);
    if ($interval->days > 366) {
      $dateFrom = '';
      $dateTo = '';
      $dateFromObj = null;
      $dateToObj = null;
    }
  }
}

$where = [];
$params = [];

if ($plateCanonical !== '') {
  // Normalize spaces/hyphens so filter works regardless of plate formatting style.
  $where[] = "REPLACE(REPLACE(UPPER(r.plate_number), ' ', ''), '-', '') LIKE :plate";
  $params[':plate'] = '%' . $plateCanonical . '%';
}

if ($dateFromObj) {
  $where[] = "r.created_at >= :date_from";
  $params[':date_from'] = $dateFromObj->format('Y-m-d H:i:s');
}

if ($dateToObj) {
  $where[] = "r.created_at <= :date_to";
  $params[':date_to'] = $dateToObj->format('Y-m-d H:i:s');
}

$whereSql = '';
if (!empty($where)) {
  $whereSql = ' WHERE ' . implode(' AND ', $where);
}

try {
  $guardLogFlagsJoin = $guardLogFlagsExists
    ? "LEFT JOIN guard_log_flags glf ON glf.log_id = r.log_id AND glf.status = 'open'"
    : "";
  $flaggedCountSql = $guardLogFlagsExists
    ? "(SELECT COUNT(*) FROM guard_log_flags WHERE status = 'open') as flagged"
    : "0 as flagged";

  // Get total count
  $countSql = "SELECT COUNT(*) FROM recent_logs r" . $whereSql;
  $countStmt = $pdo->prepare($countSql);
  foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v, PDO::PARAM_STR);
  }
  $countStmt->execute();
  $total = (int)$countStmt->fetchColumn();
  $total_pages = max(1, (int)ceil($total / $per_page));

  if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
  }

  // Get paginated logs with explicit INTEGER binding for LIMIT/OFFSET
  $stmt = $pdo->prepare("SELECT r.log_id, r.log_time, r.plate_number, r.status, r.created_at, h.name, h.vehicle_type,
                                glf.id AS flag_id, glf.reason AS flag_reason
                         FROM recent_logs r
                         LEFT JOIN homeowners h ON r.plate_number = h.plate_number
            {$guardLogFlagsJoin}
                         " . $whereSql . "
                         ORDER BY r.created_at DESC, r.log_id DESC
                         LIMIT :limit OFFSET :offset");

  foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
  }

  $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Fetch summary stats for cards
  $statsStmt = $pdo->query("
    SELECT 
      COUNT(*) as total,
      SUM(CASE WHEN status = 'IN' THEN 1 ELSE 0 END) as entries,
      SUM(CASE WHEN status = 'OUT' THEN 1 ELSE 0 END) as exits,
      {$flaggedCountSql}
    FROM recent_logs
    WHERE DATE(created_at) = CURDATE()
  ");
  $logStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'entries' => 0, 'exits' => 0, 'flagged' => 0];

} catch (Exception $e) {
  error_log("[LOGS] Fatal error: " . $e->getMessage());
  $logs = [];
  $total = 0;
  $total_pages = 0;
  $logStats = ['total' => 0, 'entries' => 0, 'exits' => 0, 'flagged' => 0];
}
?>
<!-- Page Header -->
<div class="mb-6">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div class="flex items-center gap-3">
      <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-500 to-cyan-600 text-white shadow-lg">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
          </path>
        </svg>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Access Activity</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Review and audit all vehicle entry/exit records</p>
      </div>
    </div>
  </div>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
  <div class="ta-stat-card">
    <div class="ta-stat-icon indigo">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Logs Today</p>
      <p class="ta-stat-value"><?php echo number_format($logStats['total'] ?? 0); ?></p>
    </div>
  </div>
  <div class="ta-stat-card">
    <div class="ta-stat-icon green">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Entries Today</p>
      <p class="ta-stat-value text-emerald-600"><?php echo number_format($logStats['entries'] ?? 0); ?></p>
    </div>
  </div>
  <div class="ta-stat-card">
    <div class="ta-stat-icon red">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3 3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Exits Today</p>
      <p class="ta-stat-value text-rose-600"><?php echo number_format($logStats['exits'] ?? 0); ?></p>
    </div>
  </div>
  <div class="ta-stat-card">
    <div class="ta-stat-icon amber">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Active Flags</p>
      <p class="ta-stat-value text-amber-600"><?php echo number_format($logStats['flagged'] ?? 0); ?></p>
    </div>
  </div>
</div>


<!-- Action Bar -->
<div class="ta-toolbar mb-4">
  <div class="ta-toolbar-start">
    <button id="refreshLogsBtn" class="ta-btn ta-btn-secondary">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
      </path>
    </svg>
    Refresh
    </button>
    <button id="exportLogsBtn" class="ta-btn ta-btn-success">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
      </path>
    </svg>
    Export CSV
    </button>
    <input type="text" id="logsPlateFilter" class="ta-input max-w-[180px] uppercase" placeholder="Plate (e.g. ABC1234)" value="<?php echo htmlspecialchars($plateFilter); ?>">
    <input type="date" id="logsDateFrom" class="ta-input max-w-[150px]" value="<?php echo htmlspecialchars($dateFrom); ?>">
    <input type="date" id="logsDateTo" class="ta-input max-w-[150px]" value="<?php echo htmlspecialchars($dateTo); ?>">
    <select id="logsPerPage" class="ta-select">
    <?php foreach ($allowedPageSizes as $size): ?>
      <option value="<?php echo $size; ?>" <?php echo $per_page === $size ? 'selected' : ''; ?>><?php echo $size; ?> / page</option>
    <?php endforeach; ?>
    </select>
    <button type="button" id="applyLogsFiltersBtn" class="ta-btn ta-btn-primary">Apply</button>
    <button type="button" id="clearLogsFiltersBtn" class="ta-btn ta-btn-secondary">Clear</button>
  </div>

  <div class="ta-toolbar-end">
    <div class="relative flex items-center">
      <svg class="absolute left-3 h-5 w-5 text-gray-500 dark:text-gray-400 pointer-events-none flex-shrink-0" fill="none" stroke="currentColor"
        viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
      </svg>
      <input type="text" id="logsSearchInput"
        class="ta-input pl-10 min-w-[280px]"
        placeholder="Search logs...">
    </div>
    <span id="logsSearchCount" class="text-sm text-gray-600 font-medium whitespace-nowrap"></span>
  </div>
</div>

<div class="ta-table-wrapper">
  <table id="logsTable" class="ta-table">
    <thead>
      <tr>
        <th>Date/Time</th>
        <th>Plate</th>
        <th>Status</th>
        <th>Owner</th>
        <th>Vehicle</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr>
          <td colspan="5">
            <div class="ta-empty-state">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"></path>
              </svg>
              <p>No access logs found</p>
              <p style="font-size: 0.75rem; margin-top: 0.25rem;">Logs will appear once vehicles scan in or out</p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
          <tr class="hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors even:bg-slate-50 dark:even:bg-slate-800/50 <?= !empty($log['flag_id']) ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' ?>">
            <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
              <div class="flex items-center gap-2">
                <?php if (!empty($log['flag_id'])): ?>
                  <span class="text-amber-500" title="Flagged: <?= htmlspecialchars($log['flag_reason']) ?>">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 6a3 3 0 013-3h10a1 1 0 01.8 1.6L14.25 8l2.55 3.4a1 1 0 01-.8 1.6H6a1 1 0 01-1-1V7a1 1 0 00-1-1H3z" clip-rule="evenodd"></path></svg>
                  </span>
                <?php endif; ?>
                <?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?>
              </div>
            </td>
            <td class="px-4 py-3 font-mono text-sm text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($log['plate_number'] ?? ''); ?></td>
            <td class="px-4 py-3">
              <?php
              $statusBadge = strtolower($log['status'] ?? '') === 'in' ? 'success' : 'danger';
              ?>
              <span class="ta-badge <?= $statusBadge ?>">
                <?php echo htmlspecialchars($log['status'] ?? ''); ?>
              </span>
            </td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars(ta_sentence_case($log['name'] ?? 'Unknown')); ?></td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($log['vehicle_type'] ?? '-'); ?></td>
            <td class="px-4 py-3">
              <div class="flex items-center justify-center gap-2">
                <?php if (!empty($log['flag_id'])): ?>
                  <button type="button" class="ta-btn ta-btn-sm ta-btn-outline-success resolveFlagBtn" 
                          data-flag-id="<?= $log['flag_id'] ?>" 
                          data-log-id="<?= $log['log_id'] ?>"
                          title="Mark flag as resolved">
                    Resolve Flag
                  </button>
                <?php else: ?>
                  <span class="text-xs text-slate-400 italic">No actions</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
  <div class="flex items-center justify-between mt-6">
    <div class="text-sm text-gray-600">
      Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total); ?> of
      <?php echo number_format($total); ?> logs
    </div>

    <div class="flex items-center gap-2">
      <?php if ($page > 1): ?>
        <button class="ta-btn ta-btn-sm ta-btn-secondary pagination-btn" data-page="<?php echo $page - 1; ?>">« Previous</button>
      <?php endif; ?>

      <?php
      // Show page numbers
      $start = max(1, $page - 2);
      $end = min($total_pages, $page + 2);

      if ($start > 1): ?>
        <button class="ta-btn ta-btn-sm ta-btn-secondary pagination-btn" data-page="1">1</button>
        <?php if ($start > 2): ?>
          <span class="px-2 text-gray-500">...</span>
        <?php endif; ?>
      <?php endif; ?>

      <?php for ($i = $start; $i <= $end; $i++): ?>
        <button class="ta-btn ta-btn-sm <?php echo $i === $page ? 'ta-btn-primary' : 'ta-btn-secondary'; ?> pagination-btn"
          data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
      <?php endfor; ?>

      <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?>
          <span class="px-2 text-gray-500">...</span>
        <?php endif; ?>
        <button class="ta-btn ta-btn-sm ta-btn-secondary pagination-btn"
          data-page="<?php echo $total_pages; ?>"><?php echo $total_pages; ?></button>
      <?php endif; ?>

      <?php if ($page < $total_pages): ?>
        <button class="ta-btn ta-btn-sm ta-btn-secondary pagination-btn" data-page="<?php echo $page + 1; ?>">Next »</button>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>