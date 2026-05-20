<?php
// Security: Role-based access control
require_once __DIR__ . '/../../includes/session_admin_unified.php';
// Ensure CSP nonce helper is available for inline style/script nonces
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/fetch_auth.php';

requireRequestMethod('GET');

fetch_require_role(['super_admin', 'admin']);

// admin/fetch/fetch_dashboard.php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../includes/query_cache.php';

// Cache key for dashboard stats
$cacheKey = 'dashboard_stats_' . ($_SESSION['user_id'] ?? 'guest');
$stats = QueryCache::get($cacheKey);

$guardLogFlagsExists = false;
try {
  $guardLogFlagsExists = (bool)$pdo->query("SHOW TABLES LIKE 'guard_log_flags'")->fetchColumn();
} catch (Exception $e) {
  $guardLogFlagsExists = false;
}

if (!$stats) {
  // Fetch fresh data
  try {
    // Dashboard Overview Stats
    $totalHomeowners = $pdo->query("SELECT COUNT(*) FROM homeowners")->fetchColumn();
  } catch (Exception $e) {
    $totalHomeowners = 'N/A';
  }

  try {
    $recentLogsCount = $pdo->query("SELECT COUNT(*) FROM recent_logs WHERE created_at >= (NOW() - INTERVAL 1 DAY)")->fetchColumn();
  } catch (Exception $e) {
    $recentLogsCount = 'N/A';
  }

  try {
    // Analytics Dashboard Stats
    $totalLogs = $pdo->query("SELECT COUNT(*) FROM recent_logs")->fetchColumn();
    $logsToday = $pdo->query("SELECT COUNT(*) FROM recent_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $allowedToday = $pdo->query("SELECT COUNT(*) FROM recent_logs WHERE DATE(created_at) = CURDATE() AND status = 'IN'")->fetchColumn();
    $deniedToday = $pdo->query("SELECT COUNT(*) FROM recent_logs WHERE DATE(created_at) = CURDATE() AND status = 'OUT'")->fetchColumn();

    // Homeowner Status Distribution
    $homeownerStatusStmt = $pdo->query("SELECT account_status, COUNT(*) as count FROM homeowners GROUP BY account_status");
    $homeownerStatuses = $homeownerStatusStmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $totalLogs = 'N/A';
    $logsToday = 'N/A';
    $allowedToday = 'N/A';
    $deniedToday = 'N/A';
    $homeownerStatuses = [];
  }

  // Cache the stats for 2 minutes
  $stats = compact('totalHomeowners', 'recentLogsCount', 'totalLogs', 'logsToday', 'allowedToday', 'deniedToday', 'homeownerStatuses');
  QueryCache::set($cacheKey, $stats, 120);
} else {
  // Extract from cache
  extract($stats);
}
?>
<!-- Dashboard Header -->
<div class="mb-6 animate-fade-in-up">
  <div class="flex items-center gap-3 mb-2">
    <div
      class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-blue-200 dark:shadow-none shadow-lg">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
        </path>
      </svg>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard Overview</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Real-time system statistics and activity</p>
    </div>
  </div>
</div>

<!-- Main Dashboard Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 animate-fade-in-up animate-delay-1">
  <div class="ta-stat-card glass">
    <div class="ta-stat-icon blue">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
        </path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Total Homeowners</p>
      <p class="ta-stat-value"><?php echo htmlspecialchars($totalHomeowners ?? ''); ?></p>
      <p class="ta-stat-trend neutral text-xs mt-1">Registered residents</p>
    </div>
  </div>

  <div class="ta-stat-card glass">
    <div class="ta-stat-icon purple">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
        </path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">24h Access Logs</p>
      <p class="ta-stat-value"><?php echo htmlspecialchars($recentLogsCount ?? ''); ?></p>
      <p class="ta-stat-trend neutral text-xs mt-1">Activity in last 24h</p>
    </div>
  </div>
</div>

<!-- Analytics Dashboard -->
<div class="mb-4 animate-fade-in-up animate-delay-2">
  <div class="flex items-center gap-2 mb-1">
    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-gradient-to-br from-emerald-500 to-emerald-600 shadow-emerald-200 dark:shadow-none shadow-md">
      <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
        </path>
      </svg>
    </div>
    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Today's Analytics</h2>
  </div>
  <p class="text-sm text-gray-500">Real-time access control statistics</p>
</div>

<?php
// Check for flagged logs when the table exists on this deployment
$flaggedCount = 0;
if ($guardLogFlagsExists) {
  try {
    $flaggedStmt = $pdo->query("SELECT COUNT(*) FROM guard_log_flags WHERE status = 'open'");
    $flaggedCount = (int)$flaggedStmt->fetchColumn();
  } catch (Exception $e) {
    $flaggedCount = 0;
  }
}

if ($flaggedCount > 0): ?>
<div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-500 rounded-r-lg flex items-center justify-between animate-pulse shadow-sm animate-fade-in-up animate-delay-2">
    <div class="flex items-center gap-3">
        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-800 text-amber-600 dark:text-amber-200">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        <div>
            <h3 class="text-sm font-bold text-amber-800 dark:text-amber-200 uppercase tracking-wider">Attention Required</h3>
            <p class="text-amber-700 dark:text-amber-300 text-sm font-medium">There are <span class="font-bold underline"><?= $flaggedCount ?></span> flagged access logs requiring review.</p>
        </div>
    </div>
    <button type="button" data-action="loadPage" data-action-arg="logs" class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg transition-all hover:scale-105 active:scale-95 shadow-sm">
      Review Logs
    </button>
</div>
<?php endif; ?>


<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 animate-fade-in-up animate-delay-3">
  <div class="ta-stat-card glass">
    <div class="ta-stat-icon blue">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
        </path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Total Homeowners</p>
      <p class="ta-stat-value"><?php echo is_numeric($totalHomeowners) ? number_format($totalHomeowners) : $totalHomeowners; ?></p>
    </div>
  </div>

  <div class="ta-stat-card glass">
    <div class="ta-stat-icon green">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Entries Today</p>
      <p class="ta-stat-value"><?php echo is_numeric($allowedToday) ? number_format($allowedToday) : $allowedToday; ?></p>
    </div>
  </div>

  <div class="ta-stat-card glass">
    <div class="ta-stat-icon red">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Exits Today</p>
      <p class="ta-stat-value"><?php echo is_numeric($deniedToday) ? number_format($deniedToday) : $deniedToday; ?></p>
    </div>
  </div>

  <div class="ta-stat-card glass">
    <div class="ta-stat-icon purple">
      <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
        </path>
      </svg>
    </div>
    <div class="ta-stat-content">
      <p class="ta-stat-label">Total Logs Today</p>
      <p class="ta-stat-value"><?php echo is_numeric($logsToday) ? number_format($logsToday) : $logsToday; ?></p>
    </div>
  </div>
</div>

<!-- Analytics Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6 animate-fade-in-up animate-delay-4">
  <!-- Status Distribution Pie Chart -->
  <div class="ta-chart-card glass p-6">
    <div class="flex items-center gap-2 mb-4">
      <div class="flex h-8 w-8 items-center justify-center rounded-md bg-blue-100 dark:bg-blue-900/30">
        <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Today's Access Status</h3>
    </div>
    <div class="relative" style="height: 256px; width: 100%;">
      <canvas id="statusPieChart" style="max-height: 256px; display: block;"></canvas>
    </div>
  </div>

  <!-- Weekly Activity Line Chart -->
  <div class="ta-chart-card glass p-6">
    <div class="flex items-center gap-2 mb-4">
      <div class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-100 dark:bg-emerald-900/30">
        <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">7-Day Activity Trend</h3>
    </div>
    <div class="relative" style="height: 256px; width: 100%;">
      <canvas id="weeklyLineChart" style="max-height: 256px; display: block;"></canvas>
    </div>
  </div>

  <!-- Homeowner Status Distribution Pie Chart -->
  <div class="ta-chart-card glass p-6">
    <div class="flex items-center gap-2 mb-4">
      <div class="flex h-8 w-8 items-center justify-center rounded-md bg-amber-100 dark:bg-amber-900/30">
        <svg class="h-5 w-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Homeowner Status Distribution</h3>
    </div>
    <div class="relative" style="height: 256px; width: 100%;">
      <canvas id="homeownerStatusPieChart" style="max-height: 256px; display: block;"></canvas>
    </div>
  </div>
</div>

<div id="dashboardChartData"
  data-allowed="<?php echo is_numeric($allowedToday) ? (int)$allowedToday : 0; ?>"
  data-denied="<?php echo is_numeric($deniedToday) ? (int)$deniedToday : 0; ?>"
  data-homeowner-statuses='<?php echo htmlspecialchars(json_encode($homeownerStatuses), ENT_QUOTES, "UTF-8"); ?>'
  class="hidden" aria-hidden="true"></div>
<?php
// Get last 6 months of data for stacked bar charts
$months = [];
for ($i = 5; $i >= 0; $i--) {
  $months[] = date('Y-m', strtotime("-$i months"));
}

// Homeowner registrations (approved vs pending)
$homeownerStats = [];
foreach ($months as $month) {
  $stmt = $pdo->prepare("
        SELECT 
            COALESCE(account_status, 'approved') as status,
            COUNT(*) as count
        FROM homeowners
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        GROUP BY status
    ");
  $stmt->execute([$month]);
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $approved = 0;
  $pending = 0;
  foreach ($results as $row) {
    if ($row['status'] === 'approved')
      $approved = (int) $row['count'];
    elseif ($row['status'] === 'pending')
      $pending = (int) $row['count'];
  }

  $homeownerStats[] = [
    'month' => date('M', strtotime($month . '-01')),
    'approved' => $approved,
    'pending' => $pending
  ];
}

// Access logs (entries vs exits) - using recent_logs table
$accessStats = [];
foreach ($months as $month) {
  $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM recent_logs
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
        GROUP BY status
    ");
  $stmt->execute([$month]);
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $entries = 0;
  $exits = 0;
  foreach ($results as $row) {
    if ($row['status'] === 'IN')
      $entries = (int) $row['count'];
    elseif ($row['status'] === 'OUT')
      $exits = (int) $row['count'];
  }

  $accessStats[] = [
    'month' => date('M', strtotime($month . '-01')),
    'entries' => $entries,
    'exits' => $exits
  ];
}

// Vehicle registrations - check if table exists first
$vehicleStats = [];
try {
  // Check if homeowner_vehicles table exists
  $tableCheck = $pdo->query("SHOW TABLES LIKE 'homeowner_vehicles'")->fetch();

  if ($tableCheck) {
    foreach ($months as $month) {
      $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM homeowner_vehicles
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
                AND is_active = TRUE
            ");
      $stmt->execute([$month]);
      $count = $stmt->fetchColumn();

      $vehicleStats[] = [
        'month' => date('M', strtotime($month . '-01')),
        'count' => (int) $count
      ];
    }
  } else {
    // Table doesn't exist, return zeros
    foreach ($months as $month) {
      $vehicleStats[] = [
        'month' => date('M', strtotime($month . '-01')),
        'count' => 0
      ];
    }
  }
} catch (Exception $e) {
  // Fallback: return zeros if error
  foreach ($months as $month) {
    $vehicleStats[] = [
      'month' => date('M', strtotime($month . '-01')),
      'count' => 0
    ];
  }
}
?>

<!-- Stacked Bar Charts Section -->
<div class="mt-8" style="clear: both;">
  <div class="flex items-center justify-between mb-4 flex-wrap gap-4">
    <div class="flex items-center gap-2">
      <div class="flex h-8 w-8 items-center justify-center rounded-md bg-gradient-to-br from-violet-500 to-violet-600">
        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
          </path>
        </svg>
      </div>
      <h2 class="text-xl font-bold text-gray-900 dark:text-white">6-Month Trends</h2>
    </div>
    
    <!-- Tab Navigation -->
    <div class="flex bg-gray-200/50 dark:bg-slate-800 p-1 rounded-lg border border-gray-200 dark:border-slate-700" id="dashboard-tabs">
      <button data-tab-btn="homeowners"
        class="flex-1 px-4 py-1.5 rounded-md text-sm font-semibold transition-all duration-200 cursor-pointer bg-white shadow-sm dark:bg-slate-600 text-blue-600 dark:text-white ring-1 ring-black/5"
        >
        Homeowners
      </button>
      <button data-tab-btn="access"
        class="flex-1 px-4 py-1.5 rounded-md text-sm font-semibold transition-all duration-200 cursor-pointer text-gray-600 hover:text-gray-900 hover:bg-white/50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-slate-700">
        Access
      </button>
      <button data-tab-btn="vehicles"
        class="flex-1 px-4 py-1.5 rounded-md text-sm font-semibold transition-all duration-200 cursor-pointer text-gray-600 hover:text-gray-900 hover:bg-white/50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-slate-700">
        Vehicles
      </button>
    </div>

  </div>
  <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Visual analytics for system activity</p>

  <!-- Homeowner Registrations Chart -->
  <div data-tab-content="homeowners" class="ta-chart-card glass p-6 mb-6">
    <div class="border-b border-gray-200 dark:border-slate-700 pb-4 mb-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Homeowner Registrations</h3>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Last 6 months - Approved vs Pending</p>
    </div>
    <div class="relative" style="height: 300px; width: 100%;">
      <canvas id="homeownerChart" style="max-height: 300px; display: block;"></canvas>
    </div>
  </div>

  <!-- Access Logs Chart -->
  <div data-tab-content="access" style="display: none;" class="ta-chart-card glass p-6 mb-6">
    <div class="border-b border-gray-200 dark:border-slate-700 pb-4 mb-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Access Activity</h3>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Last 6 months - Entries vs Exits</p>
    </div>
    <div class="relative" style="height: 300px; width: 100%;">
      <canvas id="accessChart" style="max-height: 300px; display: block;"></canvas>
    </div>
  </div>

  <!-- Vehicle Registrations Chart -->
  <div data-tab-content="vehicles" style="display: none;" class="ta-chart-card glass p-6 mb-6">
    <div class="border-b border-gray-200 dark:border-slate-700 pb-4 mb-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Vehicle Registrations</h3>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Last 6 months</p>
    </div>
    <div class="relative" style="height: 300px; width: 100%;">
      <canvas id="vehicleChart" style="max-height: 300px; display: block;"></canvas>
    </div>
  </div>

</div>

<style nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
  .chart-tooltip {
    background: #1f2937;
    color: white;
  }
  body.dark .chart-tooltip,
  body.dark .chart-tooltip {
    background: #0f172a;
    color: #f1f5f9;
    border: 1px solid #334155;
  }
</style>
<div id="dashboardStackedData"
  data-homeowner='<?php echo htmlspecialchars(json_encode($homeownerStats), ENT_QUOTES, "UTF-8"); ?>'
  data-access='<?php echo htmlspecialchars(json_encode($accessStats), ENT_QUOTES, "UTF-8"); ?>'
  data-vehicle='<?php echo htmlspecialchars(json_encode($vehicleStats), ENT_QUOTES, "UTF-8"); ?>'
  class="hidden" aria-hidden="true"></div>
