<?php
// Security: Role-based access control
require_once __DIR__ . '/../../includes/session_admin_unified.php';
// Ensure CSP nonce helper is available for inline style/script nonces
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/fetch_auth.php';

requireRequestMethod('GET');

fetch_require_role(['super_admin', 'admin']);

$isSuperAdmin = ($_SESSION['role'] ?? '') === 'super_admin';

// admin/fetch/fetch_manage.php
require_once __DIR__ . '/../../db.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim((string)($_GET['search'] ?? ''));
$dateFromRaw = trim((string)($_GET['date_from'] ?? ''));
$dateToRaw = trim((string)($_GET['date_to'] ?? ''));
$allowedPerPage = [10, 25, 50, 100];
$perPage = (int)($_GET['per_page'] ?? 25);
if (!in_array($perPage, $allowedPerPage, true)) {
  $perPage = 25;
}
$allowedRfidStatus = ['all', 'bound', 'unbound'];
$rfidStatus = strtolower(trim((string)($_GET['rfid_status'] ?? 'all')));
if (!in_array($rfidStatus, $allowedRfidStatus, true)) {
  $rfidStatus = 'all';
}
$offset = ($page - 1) * $perPage;

$homeownerColumns = [];
try {
  $homeownerColumns = $pdo->query("SHOW COLUMNS FROM homeowners")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
  $homeownerColumns = [];
}
$hasSplitNames = in_array('first_name', $homeownerColumns, true) && in_array('last_name', $homeownerColumns, true);

// Auto-sync: ensure vehicles table has entries for all approved homeowners
try {
    $pdo->exec("
        INSERT IGNORE INTO vehicles (homeowner_id, plate_number, vehicle_type, color, is_primary, is_active, registered_at)
        SELECT h.id, h.plate_number, COALESCE(h.vehicle_type, 'Unknown'), COALESCE(h.color, 'Unknown'), 1, 1, NOW()
        FROM homeowners h
        LEFT JOIN vehicles v ON v.homeowner_id = h.id AND v.plate_number = h.plate_number AND v.is_active = 1
        WHERE h.account_status = 'approved'
          AND h.plate_number IS NOT NULL
          AND h.plate_number != ''
          AND v.id IS NULL
    ");
} catch (Exception $e) {
    error_log('[MANAGE] Vehicle sync error: ' . $e->getMessage());
}

$rfidCounts = ['all' => 0, 'bound' => 0, 'unbound' => 0];
try {
  $statsStmt = $pdo->query(" 
    SELECT
      COUNT(*) AS total_count,
      SUM(CASE WHEN v.rfid_uid IS NOT NULL AND v.rfid_uid != '' THEN 1 ELSE 0 END) AS bound_count,
      SUM(CASE WHEN v.rfid_uid IS NULL OR v.rfid_uid = '' THEN 1 ELSE 0 END) AS unbound_count
    FROM homeowners h
    LEFT JOIN vehicles v ON v.homeowner_id = h.id AND v.plate_number = h.plate_number AND v.is_active = 1
    WHERE h.account_status = 'approved'
  ");
  $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
  $rfidCounts['all'] = (int)($stats['total_count'] ?? 0);
  $rfidCounts['bound'] = (int)($stats['bound_count'] ?? 0);
  $rfidCounts['unbound'] = (int)($stats['unbound_count'] ?? 0);
} catch (Exception $e) {
  error_log('[MANAGE] RFID stats error: ' . $e->getMessage());
}

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
?>
<!-- Page Header -->
<div class="mb-6">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div class="flex items-center gap-3">
      <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-600 text-white shadow-lg">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
          </path>
        </svg>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Manage Records</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">View and manage approved homeowner accounts</p>
      </div>
    </div>
  </div>
</div>

<!-- Stat Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
  <div class="ta-stat-card">
    <div class="ta-stat-icon indigo">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Total Homeowners</p>
      <p class="ta-stat-value"><?php echo number_format($rfidCounts['all']); ?></p>
    </div>
  </div>
  <div class="ta-stat-card">
    <div class="ta-stat-icon blue">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Bound RFID</p>
      <p class="ta-stat-value text-blue-600"><?php echo number_format($rfidCounts['bound']); ?></p>
    </div>
  </div>
  <div class="ta-stat-card">
    <div class="ta-stat-icon red">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Unbound RFID</p>
      <p class="ta-stat-value text-red-600"><?php echo number_format($rfidCounts['unbound']); ?></p>
    </div>
  </div>
</div>


<!-- Action Bar -->
<div class="flex items-center gap-2 mb-4 flex-wrap">
  <button id="refreshBtn" class="ta-btn ta-btn-secondary">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
      </path>
    </svg>
    Refresh
  </button>
  <button id="openCreateBtn" class="ta-btn ta-btn-primary">
    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
    </svg>
    Add New
  </button>
  <div class="flex flex-wrap items-center gap-2">
    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">More Actions</span>
    <button id="exportManageBtn" class="ta-btn ta-btn-secondary">
      <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
        </path>
      </svg>
      Export CSV
    </button>
    <button id="qrRegistrationBtn" class="ta-btn ta-btn-secondary">
      <svg class="h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 3h7v7H3V3zm0 11h7v7H3v-7zm11-11h7v7h-7V3zm2 2v3h3V5h-3zM5 5v3h3V5H5zm0 11v3h3v-3H5zm9 1h1v1h-1v-1zm2 0h1v1h-1v-1zm-2 2h1v1h-1v-1zm2 0h1v1h-1v-1zm2-2h1v1h-1v-1zm0 2h1v1h-1v-1z">
        </path>
      </svg>
      QR Registration
    </button>
  </div>
  <div class="flex items-center gap-2 ml-auto">
    <div class="relative flex items-center">
      <svg class="absolute left-3 h-5 w-5 text-gray-500 dark:text-gray-400 pointer-events-none flex-shrink-0" fill="none" stroke="currentColor"
        viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
      </svg>
      <input type="text" id="searchInput"
        class="ta-input pl-10 min-w-[280px]"
        value="<?php echo htmlspecialchars($search); ?>"
        placeholder="Search records...">
    </div>
    <select id="managePerPage" class="ta-select" title="Rows per page">
      <?php foreach ($allowedPerPage as $opt): ?>
        <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?> / page</option>
      <?php endforeach; ?>
    </select>

    <div class="flex items-center gap-2">
      <input id="manageDateFrom" type="date" class="ta-input" value="<?php echo htmlspecialchars($dateFromRaw); ?>" title="From" />
      <input id="manageDateTo" type="date" class="ta-input" value="<?php echo htmlspecialchars($dateToRaw); ?>" title="To" />
    </div>

    <span id="searchCount" class="text-sm text-gray-600 font-medium whitespace-nowrap"></span>
  </div>
</div>

<?php
try {
  $params = [];
  $where = "WHERE h.account_status = 'approved'";
  if ($rfidStatus === 'bound') {
    $where .= " AND v.rfid_uid IS NOT NULL AND v.rfid_uid != ''";
  } elseif ($rfidStatus === 'unbound') {
    $where .= " AND (v.rfid_uid IS NULL OR v.rfid_uid = '')";
  }
  if ($search !== '') {
    if ($hasSplitNames) {
      $where .= " AND (h.name LIKE :search_name OR h.first_name LIKE :search_first_name OR h.last_name LIKE :search_last_name OR h.plate_number LIKE :search_plate OR h.vehicle_type LIKE :search_vehicle OR h.contact_number LIKE :search_contact)";
    } else {
      $where .= " AND (h.name LIKE :search_name OR h.plate_number LIKE :search_plate OR h.vehicle_type LIKE :search_vehicle OR h.contact_number LIKE :search_contact)";
    }
    $searchLike = "%{$search}%";
    $params = [
      ':search_name' => $searchLike,
      ':search_plate' => $searchLike,
      ':search_vehicle' => $searchLike,
      ':search_contact' => $searchLike,
    ];
    if ($hasSplitNames) {
      $params[':search_first_name'] = $searchLike;
      $params[':search_last_name'] = $searchLike;
    }
  }

  // Date range filter (created_at) - expect YYYY-MM-DD values from date inputs
  if ($dateFromRaw !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $dateFromRaw);
    if ($d !== false) {
      $params[':date_from'] = $d->format('Y-m-d') . ' 00:00:00';
      $where .= " AND h.created_at >= :date_from";
    }
  }

  if ($dateToRaw !== '') {
    $d2 = DateTime::createFromFormat('Y-m-d', $dateToRaw);
    if ($d2 !== false) {
      // include end of day
      $params[':date_to'] = $d2->format('Y-m-d') . ' 23:59:59';
      $where .= " AND h.created_at <= :date_to";
    }
  }

  $countStmt = $pdo->prepare(" 
    SELECT COUNT(*)
    FROM homeowners h
    LEFT JOIN vehicles v ON v.homeowner_id = h.id AND v.plate_number = h.plate_number AND v.is_active = 1
    {$where}
  ");
  $countStmt->execute($params);
  $totalRows = (int)$countStmt->fetchColumn();
  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
  }

  // Only show APPROVED homeowners in Manage Records
  // Pending accounts should only appear in Account Approvals
  // LEFT JOIN vehicles to get RFID binding status
  $stmt = $pdo->prepare(" 
      SELECT h.id, h.name, h.address, h.contact_number, h.plate_number, h.vehicle_type, h.account_status, h.created_at,
               v.id AS vehicle_id, v.rfid_uid, v.rfid_bound_at
        FROM homeowners h
        LEFT JOIN vehicles v ON v.homeowner_id = h.id AND v.plate_number = h.plate_number AND v.is_active = 1
        {$where}
        ORDER BY h.id DESC
        LIMIT :limit OFFSET :offset
    ");
  foreach ($params as $key => $param) {
    $stmt->bindValue($key, $param, PDO::PARAM_STR);
  }
  $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
  error_log("Error fetching homeowners: " . $e->getMessage());
  $rows = [];
  $totalRows = 0;
  $totalPages = 1;
  $page = 1;
  $offset = 0;
}
?>

<div class="ta-table-wrapper">
  <table id="homeownersTable" class="ta-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Plate</th>
        <th>Vehicle</th>
        <th>Registered</th>
        <th>Contact</th>
        <th class="text-center">RFID</th>
        <th class="text-center">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="7">
            <div class="ta-empty-state">
              <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
              </svg>
              <p>No approved homeowners found</p>
            </div>
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($rows as $r): ?>
        <tr class="hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors even:bg-slate-50 dark:even:bg-slate-800/50">
          <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars(ta_sentence_case($r['name'] ?? '')); ?></td>
          <td class="px-4 py-3 text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($r['plate_number'] ?? ''); ?></td>
          <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($r['vehicle_type'] ?? ''); ?></td>
          <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo !empty($r['created_at']) ? htmlspecialchars(date('M d, Y', strtotime($r['created_at']))) : '-'; ?></td>
          <td class="px-4 py-3 text-slate-600 dark:text-slate-400"><?php echo htmlspecialchars($r['contact_number'] ?? ''); ?></td>
          <td class="px-4 py-3 text-center">
            <div class="rfid-col-container">
              <?php if (!empty($r['rfid_uid'])): ?>
                <div class="rfid-badge-bound" title="UID: <?php echo htmlspecialchars($r['rfid_uid']); ?>">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  Bound
                </div>
                <button class="rfid-btn-unbind btn-unbind-rfid-manage"
                        data-vehicle-id="<?php echo $r['vehicle_id']; ?>"
                        data-plate="<?php echo htmlspecialchars($r['plate_number'] ?? ''); ?>">
                  Unbind Tag
                </button>
              <?php elseif (!empty($r['vehicle_id'])): ?>
                <button class="rfid-btn-bind btn-bind-rfid-manage"
                        data-vehicle-id="<?php echo $r['vehicle_id']; ?>"
                        data-plate="<?php echo htmlspecialchars($r['plate_number'] ?? ''); ?>"
                        data-owner="<?php echo htmlspecialchars($r['name'] ?? ''); ?>">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                  </svg>
                  Bind RFID
                </button>
              <?php else: ?>
                <span class="text-[10px] text-gray-400 italic">No Vehicle</span>
              <?php endif; ?>
            </div>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-center">
              <div class="ta-action-dropdown">
                <button type="button" class="ta-action-btn" aria-haspopup="menu" aria-expanded="false">
                  Actions
                  <svg class="ta-chevron" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                </button>
                <div class="ta-action-menu" role="menu" aria-hidden="true">
                  <button type="button" role="menuitem" class="ta-action-menu-item blue btn-view" data-id="<?php echo $r['id']; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7S3.732 16.057 2.458 12z"></path></svg>
                    View Profile
                  </button>
                  <?php if ($isSuperAdmin): ?>
                    <div class="ta-action-divider"></div>
                    <button type="button" role="menuitem" class="ta-action-menu-item red btn-delete" data-id="<?php echo $r['id']; ?>" data-name="<?php echo htmlspecialchars(ta_sentence_case($r['name'] ?? '')); ?>">
                      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-6 0h8"></path></svg>
                      Delete
                    </button>
                  <?php endif; ?>
                  <div class="ta-action-divider"></div>
                  <button type="button" role="menuitem" class="ta-action-menu-item btn-edit" data-id="<?php echo $r['id']; ?>">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                  </button>
                </div>
              </div>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="flex items-center justify-between mt-4">
  <div class="text-sm text-gray-600 dark:text-gray-400">
    Showing <?php echo $totalRows > 0 ? ($offset + 1) : 0; ?> to <?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> records
  </div>
  <div class="flex items-center gap-1">
    <?php if ($page > 1): ?>
      <button type="button" class="pagination-btn ta-btn ta-btn-outline-secondary ta-btn-sm" data-page="<?php echo $page - 1; ?>">Previous</button>
    <?php endif; ?>
    <?php
      $startPage = max(1, $page - 2);
      $endPage = min($totalPages, $page + 2);
      for ($i = $startPage; $i <= $endPage; $i++):
    ?>
      <button
        type="button"
        class="pagination-btn ta-btn ta-btn-sm <?php echo $i === $page ? 'ta-btn-primary' : 'ta-btn-outline-secondary'; ?>"
        data-page="<?php echo $i; ?>"
      ><?php echo $i; ?></button>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
      <button type="button" class="pagination-btn ta-btn ta-btn-outline-secondary ta-btn-sm" data-page="<?php echo $page + 1; ?>">Next</button>
    <?php endif; ?>
  </div>
</div>
