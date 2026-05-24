<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
  http_response_code(403);
  exit('Unauthorized');
}
require_once __DIR__ . '/../../db.php';

$hasEnhancedAudit = false;
try {
  $enhancedCheck = $pdo->query("SHOW TABLES LIKE 'audit_logs_enhanced'");
  $hasEnhancedAudit = (bool)$enhancedCheck->fetchColumn();
} catch (Throwable $e) {
  $hasEnhancedAudit = false;
}

function summarizeAuditValues($oldValues, $newValues): string {
  $oldIsArray = is_array($oldValues);
  $newIsArray = is_array($newValues);

  if (!$oldIsArray && !$newIsArray) {
    $oldText = trim((string)($oldValues ?? ''));
    $newText = trim((string)($newValues ?? ''));
    if ($oldText === '' && $newText === '') {
      return '';
    }
    if ($oldText === $newText) {
      return $newText;
    }
    return ($oldText !== '' ? $oldText : '—') . ' → ' . ($newText !== '' ? $newText : '—');
  }

  $keys = array_unique(array_merge(array_keys($oldIsArray ? $oldValues : []), array_keys($newIsArray ? $newValues : [])));
  $parts = [];
  foreach ($keys as $key) {
    $old = $oldIsArray && array_key_exists($key, $oldValues) ? $oldValues[$key] : null;
    $new = $newIsArray && array_key_exists($key, $newValues) ? $newValues[$key] : null;
    if ($old === $new) {
      continue;
    }
    $oldText = is_bool($old) ? ($old ? 'true' : 'false') : (is_scalar($old) ? (string)$old : json_encode($old));
    $newText = is_bool($new) ? ($new ? 'true' : 'false') : (is_scalar($new) ? (string)$new : json_encode($new));
    $parts[] = $key . ': ' . ($oldText !== '' ? $oldText : '—') . ' → ' . ($newText !== '' ? $newText : '—');
  }

  return implode('; ', $parts);
}

$filter_action = trim((string)($_GET['action'] ?? ''));
$filter_user = trim((string)($_GET['user'] ?? ''));
$filter_date_from = trim((string)($_GET['date_from'] ?? ''));
$filter_date_to = trim((string)($_GET['date_to'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));

$allowedLimits = [100, 200, 500, 1000];
$filter_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if (!in_array($filter_limit, $allowedLimits, true)) {
  $filter_limit = 200;
}
$offset = ($page - 1) * $filter_limit;

$where = [];
$params = [];
$filterErrors = [];

$parsedDateFrom = null;
$parsedDateTo = null;

if ($filter_date_from !== '') {
  $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $filter_date_from);
  if ($parsed && $parsed->format('Y-m-d') === $filter_date_from) {
    $parsedDateFrom = $parsed->setTime(0, 0, 0);
  } else {
    $filterErrors[] = 'Invalid start date format.';
  }
}

if ($filter_date_to !== '') {
  $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $filter_date_to);
  if ($parsed && $parsed->format('Y-m-d') === $filter_date_to) {
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

if ($filter_action !== '') {
  $where[] = 'action = :action';
  $params[':action'] = $filter_action;
}

if ($filter_user !== '') {
  $where[] = 'username LIKE :username';
  $params[':username'] = '%' . $filter_user . '%';
}

if (empty($filterErrors)) {
  if ($parsedDateFrom) {
    $where[] = 'created_at >= :date_from';
    $params[':date_from'] = $parsedDateFrom->format('Y-m-d H:i:s');
  }
  if ($parsedDateTo) {
    $where[] = 'created_at <= :date_to';
    $params[':date_to'] = $parsedDateTo->format('Y-m-d H:i:s');
  }
}

$whereSql = '';
if (!empty($where)) {
  $whereSql = ' WHERE ' . implode(' AND ', $where);
}

try {
  $auditTable = $hasEnhancedAudit ? 'audit_logs_enhanced' : 'audit_logs';
  $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $auditTable . $whereSql);
  foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val, PDO::PARAM_STR);
  }
  $countStmt->execute();
  $totalRows = (int)$countStmt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $filter_limit));

  if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $filter_limit;
  }

  if ($hasEnhancedAudit) {
    $sql = 'SELECT id, username, action, table_name, ip_address, created_at, old_values, new_values FROM audit_logs_enhanced' . $whereSql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
  } else {
    $sql = 'SELECT id, username, action, table_name, ip_address, created_at, NULL AS old_values, NULL AS new_values FROM audit_logs' . $whereSql . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
  }
  $stmt = $pdo->prepare($sql);
  foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val, PDO::PARAM_STR);
  }
  $stmt->bindValue(':limit', $filter_limit, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('[AUDIT] Fetch error: ' . $e->getMessage());
  $logs = [];
  $totalRows = 0;
  $totalPages = 1;
  $page = 1;
  $offset = 0;
}

$actions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="mb-6">
  <div class="flex items-center gap-3 mb-2">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 text-white">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Audit Logs</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Track administrative actions and system changes</p>
    </div>
  </div>
</div>

<div class="ta-toolbar mb-4">
  <div class="ta-toolbar-start">
    <select id="actionFilter" class="ta-select filter-select">
      <option value="">All Actions</option>
      <?php foreach ($actions as $action): ?>
        <option value="<?php echo htmlspecialchars($action ?? ''); ?>" <?php echo $action === $filter_action ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($action ?? ''); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <input type="text" id="userFilter" class="ta-input min-w-[180px]" placeholder="Filter by user..." value="<?php echo htmlspecialchars($filter_user); ?>">
    <input type="date" id="auditDateFrom" class="ta-input" value="<?php echo htmlspecialchars($filter_date_from); ?>" aria-label="Audit start date">
    <input type="date" id="auditDateTo" class="ta-input" value="<?php echo htmlspecialchars($filter_date_to); ?>" aria-label="Audit end date">
    <select id="auditLimit" class="ta-select">
      <?php foreach ($allowedLimits as $limit): ?>
        <option value="<?php echo $limit; ?>" <?php echo $filter_limit === $limit ? 'selected' : ''; ?>><?php echo $limit; ?> / page</option>
      <?php endforeach; ?>
    </select>

    <button id="applyFilters" class="ta-btn ta-btn-primary">Apply</button>
    <button id="clearFilters" class="ta-btn ta-btn-secondary">Clear</button>
    <button id="exportAuditBtn" class="ta-btn ta-btn-success">
      <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
      Export CSV
    </button>
  </div>

  <div class="ta-toolbar-end">
    <div class="relative flex items-center">
      <svg class="absolute left-3 h-5 w-5 text-gray-500 dark:text-gray-400 pointer-events-none flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
      </svg>
      <input type="text" id="auditSearchInput" class="ta-input pl-12 min-w-[260px]" placeholder="Search logs...">
    </div>
    <span id="auditSearchCount" class="text-sm text-gray-600 font-medium whitespace-nowrap"></span>
  </div>
</div>

<?php if (!empty($filterErrors)): ?>
  <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    <?php echo htmlspecialchars(implode(' ', $filterErrors)); ?>
  </div>
<?php endif; ?>

<div class="ta-table-wrapper">
  <table id="auditTable" class="ta-table">
    <thead>
      <tr>
        <th>Time</th>
        <th>Actor</th>
        <th>Action</th>
        <th>Table</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr>
          <td colspan="5">
            <div class="ta-empty-state">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
              </svg>
              <p>No audit logs found</p>
            </div>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
          <tr class="hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors even:bg-slate-50 dark:even:bg-slate-800/50">
            <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?php echo date('M d, Y h:i:s A', strtotime($log['created_at'])); ?></td>
            <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-200">
              <div><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></div>
              <div class="text-xs text-slate-500 dark:text-slate-400">IP: <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></div>
            </td>
            <td class="px-4 py-3">
              <?php
                $actionText = strtoupper((string)($log['action'] ?? ''));
                $badgeClass = 'info';
                if (str_contains($actionText, 'DELETE') || str_contains($actionText, 'REJECTED') || str_contains($actionText, 'CANCELLED') || str_contains($actionText, 'UNBOUND')) {
                  $badgeClass = 'danger';
                } elseif (str_contains($actionText, 'CREATE') || str_contains($actionText, 'INITIATED')) {
                  $badgeClass = 'success';
                } elseif (str_contains($actionText, 'UPDATE') || str_contains($actionText, 'COMPLETED')) {
                  $badgeClass = 'warning';
                }
              ?>
              <span class="ta-badge <?= $badgeClass ?>"><?php echo htmlspecialchars($log['action'] ?? ''); ?></span>
            </td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($log['table_name'] ?? '-'); ?></td>
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-sm max-w-[24rem]">
              <?php
                $details = '';
                if ($hasEnhancedAudit) {
                  $oldValues = $log['old_values'] ? json_decode((string)$log['old_values'], true) : null;
                  $newValues = $log['new_values'] ? json_decode((string)$log['new_values'], true) : null;
                  $details = summarizeAuditValues($oldValues, $newValues);
                }
                echo htmlspecialchars($details !== '' ? $details : '-');
              ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
  <div class="flex items-center justify-between mt-4">
    <p class="text-sm text-slate-600 dark:text-slate-400">
      Showing <?php echo $totalRows > 0 ? ($offset + 1) : 0; ?>-<?php echo min($offset + $filter_limit, $totalRows); ?> of <?php echo $totalRows; ?> logs
    </p>
    <div class="flex items-center gap-1">
      <?php if ($page > 1): ?>
        <button class="pagination-btn ta-btn ta-btn-sm ta-btn-outline-secondary" data-page="<?php echo $page - 1; ?>">Previous</button>
      <?php endif; ?>
      <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        for ($i = $startPage; $i <= $endPage; $i++):
      ?>
        <button class="pagination-btn ta-btn ta-btn-sm <?php echo $i === $page ? 'ta-btn-primary' : 'ta-btn-outline-secondary'; ?>" data-page="<?php echo $i; ?>"><?php echo $i; ?></button>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <button class="pagination-btn ta-btn ta-btn-sm ta-btn-outline-secondary" data-page="<?php echo $page + 1; ?>">Next</button>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>