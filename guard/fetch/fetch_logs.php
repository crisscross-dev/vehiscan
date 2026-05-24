<?php
// Guard fetch logs with server-side pagination (matching admin panel architecture)
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/session_guard.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/pagination_helper.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
    http_response_code(401);
    header('Content-Type: application/json');
    exit(json_encode([
      'success' => false,
      'message' => 'Unauthorized access',
      'error' => 'Unauthorized access'
    ]));
}

requireRequestMethod('GET');

require_once __DIR__ . '/../../db.php';

$hasGuardLogFlagsTable = false;
try {
  $flagTableStmt = $pdo->query("SHOW TABLES LIKE 'guard_log_flags'");
  $hasGuardLogFlagsTable = (bool)$flagTableStmt->fetchColumn();
} catch (Throwable $e) {
  $hasGuardLogFlagsTable = false;
}

// Pagination parameters
$allowedPerPage = [10, 20, 25, 50, 100];
$pagination = normalizePagination($_GET['page'] ?? 1, $_GET['per_page'] ?? 10, $allowedPerPage, 10, 10000);
$page = $pagination['page'];
$per_page = $pagination['per_page'];
$offset = $pagination['offset'];

// Filter parameters
$filter = strtolower(trim($_GET['filter'] ?? ''));
$search = trim($_GET['search'] ?? '');
$plateFilter = trim($_GET['plate'] ?? '');
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$where = [];
$params = [];
$filterErrors = [];

// Date range validation
$parsedDateFrom = null;
$parsedDateTo = null;

if ($dateFrom !== '') {
  $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateFrom);
  if ($parsed && $parsed->format('Y-m-d') === $dateFrom) {
    $parsedDateFrom = $parsed->setTime(0, 0, 0);
  } else {
    $filterErrors[] = 'Invalid start date format.';
  }
}

if ($dateTo !== '') {
  $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateTo);
  if ($parsed && $parsed->format('Y-m-d') === $dateTo) {
    $parsedDateTo = $parsed->setTime(23, 59, 59);
  } else {
    $filterErrors[] = 'Invalid end date format.';
  }
}

if ($parsedDateFrom && $parsedDateTo) {
  if ($parsedDateFrom > $parsedDateTo) {
    $filterErrors[] = 'Start date must be earlier than or equal to end date.';
  } else {
    $daysDiff = (int)$parsedDateFrom->diff($parsedDateTo)->days;
    if ($daysDiff > 366) {
      $filterErrors[] = 'Date range cannot exceed 366 days.';
    }
  }
}

if ($filter === 'in') {
  $where[] = "al.status = ?";
  $params[] = 'IN';
} elseif ($filter === 'out') {
  $where[] = "al.status = ?";
  $params[] = 'OUT';
} elseif ($filter === 'today') {
  $where[] = "DATE(al.created_at) = CURDATE()";
}

if ($plateFilter !== '') {
  $normalizedPlate = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plateFilter));
  if ($normalizedPlate !== '') {
    $where[] = "REPLACE(REPLACE(UPPER(al.plate_number), '-', ''), ' ', '') = ?";
    $params[] = $normalizedPlate;
  }
}

if ($search !== '') {
  $searchLike = '%' . $search . '%';
  $where[] = "(
    COALESCE(h.name, h2.name, '') LIKE ? OR
    al.plate_number LIKE ? OR
    COALESCE(v.vehicle_type, h.vehicle_type, '') LIKE ? OR
    COALESCE(v.color, h.color, '') LIKE ? OR
    al.status LIKE ? OR
    COALESCE(al.rfid_uid, '') LIKE ? OR
    COALESCE(vp.visitor_name, '') LIKE ?
  )";
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
  $params[] = $searchLike;
}

// Add date range filters if no validation errors
if (empty($filterErrors)) {
  if ($parsedDateFrom) {
    $where[] = "al.created_at >= ?";
    $params[] = $parsedDateFrom->format('Y-m-d H:i:s');
  }
  if ($parsedDateTo) {
    $where[] = "al.created_at <= ?";
    $params[] = $parsedDateTo->format('Y-m-d H:i:s');
  }
}

$flagJoinSql = $hasGuardLogFlagsTable
  ? "LEFT JOIN guard_log_flags glf ON glf.log_id = al.log_id AND glf.status = 'open'"
  : '';

$flagSelectSql = $hasGuardLogFlagsTable
  ? "glf.id AS flag_id, glf.reason AS flag_reason, glf.created_at AS flag_created_at,"
  : "NULL AS flag_id, NULL AS flag_reason, NULL AS flag_created_at,";

$fromSql = "
  FROM recent_logs al
  LEFT JOIN homeowners h ON al.plate_number = h.plate_number
  LEFT JOIN vehicles v ON al.plate_number = v.plate_number AND v.is_active = 1
  LEFT JOIN homeowners h2 ON v.homeowner_id = h2.id
  LEFT JOIN visitor_passes vp ON al.plate_number = vp.visitor_plate AND vp.status = 'active'
  {$flagJoinSql}
";

$whereSql = '';
if (!empty($where)) {
  $whereSql = ' WHERE ' . implode(' AND ', $where);
}

try {
    // Get filtered total count
  $countStmt = $pdo->prepare("SELECT COUNT(*)" . $fromSql . $whereSql);
  $countStmt->execute($params);
  $total = (int)$countStmt->fetchColumn();
  $clamped = clampPaginationPage($page, $total, $per_page);
  $page = $clamped['page'];
  $total_pages = $clamped['total_pages'];
  $offset = $clamped['offset'];
    
    // Get paginated logs with homeowner info AND visitor pass info
    $stmt = $pdo->prepare("
        SELECT 
            al.log_id,
            al.plate_number,
            al.rfid_uid,
            al.status,
            al.created_at,
            DATE_FORMAT(al.created_at, '%h:%i %p') as time,
          COALESCE(h.id, h2.id) AS homeowner_id,
            COALESCE(h.name, h2.name) AS name,
            COALESCE(h.owner_img, h2.owner_img) AS owner_img,
            COALESCE(h.car_img, h2.car_img) AS car_img,
            COALESCE(v.vehicle_type, h.vehicle_type) AS vehicle_type,
            COALESCE(v.color, h.color) AS color,
            vp.id AS visitor_pass_id,
            vp.visitor_name,
            vp.purpose AS visitor_purpose,
            vp.status AS visitor_status,
            {$flagSelectSql}
            CASE
                WHEN al.status = 'IN' THEN (
                    SELECT next_out.created_at
                    FROM recent_logs next_out
                    WHERE next_out.plate_number = al.plate_number
                      AND next_out.status = 'OUT'
                      AND next_out.created_at > al.created_at
                    ORDER BY next_out.created_at ASC
                    LIMIT 1
                )
                WHEN al.status = 'OUT' THEN (
                    SELECT prev_in.created_at
                    FROM recent_logs prev_in
                    WHERE prev_in.plate_number = al.plate_number
                      AND prev_in.status = 'IN'
                      AND prev_in.created_at < al.created_at
                    ORDER BY prev_in.created_at DESC
                    LIMIT 1
                )
                ELSE NULL
            END AS paired_at
          " . $fromSql . $whereSql . "
        ORDER BY al.created_at DESC, al.log_id DESC
          LIMIT ? OFFSET ?
    ");

        $paramIndex = 1;
        foreach ($params as $paramValue) {
          $stmt->bindValue($paramIndex++, $paramValue);
        }
        $stmt->bindValue($paramIndex++, $per_page, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("[GUARD_FETCH_LOGS] Error: " . $e->getMessage());
    $logs = [];
    $total = 0;
    $total_pages = 1;
    $page = 1;
    $offset = 0;
}

// Calculate Live Stats for Today
$stats = [
    'entries_today' => 0,
    'exits_today' => 0,
    'active_visitors' => 0
];

try {
    $statsStmt = $pdo->query("SELECT 
        SUM(CASE WHEN status = 'IN' AND DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as entries,
        SUM(CASE WHEN status = 'OUT' AND DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as exits
        FROM recent_logs");
    $res = $statsStmt->fetch(PDO::FETCH_ASSOC);
    $stats['entries_today'] = (int)($res['entries'] ?? 0);
    $stats['exits_today'] = (int)($res['exits'] ?? 0);

    $visitorStmt = $pdo->query("SELECT COUNT(*) FROM visitor_passes WHERE status IN ('approved', 'active') AND NOW() BETWEEN valid_from AND valid_until");
    $stats['active_visitors'] = (int)$visitorStmt->fetchColumn();
} catch (Exception $e) {
    error_log("[GUARD_STATS] Error: " . $e->getMessage());
}

// Get last seen log ID from localStorage for "new" badge detection
$lastSeenLogId = 0; // Client will handle this via JavaScript
?>

<!-- Live Stats Data (Consumed by JS) -->
<script id="guardLiveStatsData" type="application/json">
<?php echo json_encode($stats); ?>
</script>

<!-- Logs Counter -->
<div class="logs-counter-container">
  <div id="logsCounter">
    <?php if ($total > 0): ?>
      Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $per_page, $total)); ?> of <?php echo number_format($total); ?> logs
    <?php else: ?>
      No logs to display
    <?php endif; ?>
  </div>
  <div class="text-xs text-gray-500 dark:text-gray-400">
    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
  </div>
</div>

<!-- Logs Table -->
<div class="logs-table-container">
  <?php if (empty($logs)): ?>
    <div class="text-center py-12">
      <div class="empty-state">
        <div class="empty-state-icon text-6xl mb-4"><svg class="w-16 h-16 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z"/></svg></div>
        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">No access logs found</h3>
        <p class="text-gray-500 dark:text-gray-400">Access logs will appear here when vehicles scan in/out.</p>
      </div>
    </div>
  <?php else: ?>
    <table id="logsTable" class="logs-table">
      <thead>
        <tr>
          <th>Homeowner</th>
          <th>Status</th>
          <th>Time</th>
          <th>Duration</th>
        </tr>
      </thead>
      <tbody>
    <?php foreach ($logs as $log): ?>
      <?php
        $isEntry = $log['status'] === 'IN';
        $statusIcon = $isEntry ? '<svg class="w-3.5 h-3.5 inline" viewBox="0 0 24 24" fill="#22c55e"><circle cx="12" cy="12" r="10"/></svg>' : '<svg class="w-3.5 h-3.5 inline" viewBox="0 0 24 24" fill="#ef4444"><circle cx="12" cy="12" r="10"/></svg>';
        $statusClass = $isEntry ? 'status-in' : 'status-out';
        $statusText = $log['status'];
        
        $userName = $log['name'] ?? 'Unknown';
        
        // Calculate duration based on IN/OUT pairing
        $durationText = '-';
        $durationClass = '';
        $stillInside = false;
        if (!empty($log['created_at'])) {
            $logTime = new DateTime($log['created_at']);
            $diff = null;
            
            if ($isEntry) {
                // IN entry: pair with next OUT, or show elapsed if still inside
                if (!empty($log['paired_at'])) {
                    $outTime = new DateTime($log['paired_at']);
                    $diff = $outTime->getTimestamp() - $logTime->getTimestamp();
                } else {
                    // Still inside — show elapsed time
                    $now = new DateTime();
                    $diff = $now->getTimestamp() - $logTime->getTimestamp();
                    $stillInside = true;
                }
            } else {
                // OUT entry: pair with preceding IN
                if (!empty($log['paired_at'])) {
                    $inTime = new DateTime($log['paired_at']);
                    $diff = $logTime->getTimestamp() - $inTime->getTimestamp();
                }
            }
            
            if ($diff !== null && $diff >= 0) {
                $diffMins = floor($diff / 60);
                $diffHours = floor($diffMins / 60);
                $mins = $diffMins % 60;
                
                if ($diffHours > 0) {
                    $durationText = "{$diffHours}h {$mins}m";
                    if ($diffHours >= 8) {
                        $durationClass = 'duration-long';
                    } elseif ($diffHours >= 4) {
                        $durationClass = 'duration-medium';
                    } else {
                        $durationClass = 'duration-short';
                    }
                } else {
                    $durationText = "{$diffMins}m";
                    $durationClass = 'duration-short';
                }
            }
        }
      ?>
        <tr class="log-row<?php echo !empty($log['visitor_pass_id']) ? ' has-visitor-pass' : ''; ?><?php echo !empty($log['flag_id']) ? ' log-row-flagged' : ''; ?>" 
          data-log-id="<?php echo $log['log_id']; ?>" 
          data-log-date="<?php echo $log['created_at']; ?>"
          data-homeowner-id="<?php echo (int)($log['homeowner_id'] ?? 0); ?>"
          data-plate="<?php echo htmlspecialchars($log['plate_number'] ?? ''); ?>"
          data-name="<?php echo htmlspecialchars($userName ?? ''); ?>"
          data-vehicle="<?php echo htmlspecialchars($log['vehicle_type'] ?? 'N/A'); ?>"
          data-color="<?php echo htmlspecialchars($log['color'] ?? 'N/A'); ?>"
          data-time="<?php echo htmlspecialchars($log['time'] ?? '-'); ?>"
          data-duration="<?php echo htmlspecialchars($durationText ?? '-'); ?>"
          data-owner-img="<?php echo htmlspecialchars($log['owner_img'] ?? ''); ?>"
          data-car-img="<?php echo htmlspecialchars($log['car_img'] ?? ''); ?>"
          data-status="<?php echo $log['status']; ?>"
          data-flag-id="<?php echo (int)($log['flag_id'] ?? 0); ?>"
          data-flag-reason="<?php echo htmlspecialchars($log['flag_reason'] ?? ''); ?>"
          data-visitor="<?php echo !empty($log['visitor_pass_id']) ? '1' : '0'; ?>">
        <td>
          <div class="user-cell">
            <div class="user-info">
              <div class="user-name">
                <?php echo htmlspecialchars($userName ?? ''); ?>
                <?php if (!empty($log['visitor_pass_id'])): ?>
                  <span class="badge-visitor" title="Visitor Pass: <?php echo htmlspecialchars($log['visitor_name'] ?? 'N/A'); ?>"><svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z"/></svg></span>
                <?php endif; ?>
              </div>
              <?php if (!empty($log['visitor_name'])): ?>
                <div class="text-xs text-gray-500 dark:text-gray-400">Visitor: <?php echo htmlspecialchars($log['visitor_name'] ?? ''); ?></div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td>
          <span class="status-badge <?php echo $statusClass; ?>">
            <span class="status-icon"><?php echo $statusIcon; ?></span>
            <?php echo $statusText; ?>
          </span>
        </td>
        <td class="time-cell"><?php echo $log['time'] ?? '-'; ?></td>
        <td>
          <?php if ($durationText !== '-'): ?>
            <span class="duration-badge <?php echo $durationClass; ?>" title="<?php echo $stillInside ? 'Still inside: ' : 'Visit duration: '; ?><?php echo $durationText; ?>">
              <?php echo $stillInside ? '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182"/></svg>' : '<svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>'; ?> <?php echo $durationText; ?>
            </span>
          <?php else: ?>
            <span class="text-muted">-</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Pagination Controls (Shadcn Design) -->
<?php if ($total_pages > 1): ?>
<div class="pagination-wrapper">
  <nav class="pagination-nav" role="navigation" aria-label="Pagination">
    <!-- Previous Button -->
    <button 
      type="button" 
      class="pagination-btn pagination-prev <?php echo $page <= 1 ? 'disabled' : ''; ?>" 
      data-page="<?php echo $page - 1; ?>"
      <?php echo $page <= 1 ? 'disabled' : ''; ?>
      aria-label="Go to previous page">
      <svg class="pagination-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
      </svg>
      <span>Previous</span>
    </button>
    
    <!-- Page Numbers -->
    <div class="pagination-pages">
      <?php
      // Calculate visible page range
      $start = max(1, $page - 1);
      $end = min($total_pages, $page + 1);
      
      // Always show first page
      if ($start > 1): ?>
        <button type="button" class="pagination-page" data-page="1">1</button>
        <?php if ($start > 2): ?>
          <span class="pagination-ellipsis">...</span>
        <?php endif; ?>
      <?php endif; ?>
      
      <!-- Current page and surrounding pages -->
      <?php for ($i = $start; $i <= $end; $i++): ?>
        <button 
          type="button" 
          class="pagination-page <?php echo $i === $page ? 'active' : ''; ?>" 
          data-page="<?php echo $i; ?>"
          <?php echo $i === $page ? 'aria-current="page"' : ''; ?>>
          <?php echo $i; ?>
        </button>
      <?php endfor; ?>
      
      <!-- Always show last page -->
      <?php if ($end < $total_pages): ?>
        <?php if ($end < $total_pages - 1): ?>
          <span class="pagination-ellipsis">...</span>
        <?php endif; ?>
        <button type="button" class="pagination-page" data-page="<?php echo $total_pages; ?>"><?php echo $total_pages; ?></button>
      <?php endif; ?>
    </div>
    
    <!-- Next Button -->
    <button 
      type="button" 
      class="pagination-btn pagination-next <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" 
      data-page="<?php echo $page + 1; ?>"
      <?php echo $page >= $total_pages ? 'disabled' : ''; ?>
      aria-label="Go to next page">
      <span>Next</span>
      <svg class="pagination-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
      </svg>
    </button>
  </nav>
</div>
<?php endif; ?>
