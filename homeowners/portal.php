<?php
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/session_homeowner.php';
require_once __DIR__ . '/../includes/common_utilities.php';
require_once __DIR__ . '/../db.php';

$allowedHomeownerPages = ['dashboard', 'passes', 'vehicles', 'activity', 'profile'];
$homeownerActivePage = strtolower(trim((string)($_GET['hpage'] ?? 'dashboard')));
if (!in_array($homeownerActivePage, $allowedHomeownerPages, true)) {
    $homeownerActivePage = 'dashboard';
}
$homeownerPageTitle = [
    'dashboard' => 'Dashboard',
    'passes' => 'Visitor Passes',
    'vehicles' => 'My Vehicles',
    'activity' => 'Vehicle Activity',
    'profile' => 'My Profile'
][$homeownerActivePage] ?? 'Dashboard';

// Get homeowner data
$stmt = $pdo->prepare("
    SELECT h.* 
    FROM homeowners h
    WHERE h.id = ?
");
$stmt->execute([$_SESSION['homeowner_id']]);
$homeowner = $stmt->fetch();

if (!$homeowner) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Get visitor passes for statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'active' AND NOW() BETWEEN valid_from AND valid_until THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status IN ('active','approved') AND NOW() > valid_until THEN 1 ELSE 0 END) as expired
    FROM visitor_passes 
    WHERE homeowner_id = ?
");
$stmt->execute([$_SESSION['homeowner_id']]);
$stats = $stmt->fetch();

// Fetch homeowner's recent profile update requests
$profileRequests = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, request_text, status, admin_notes, created_at
        FROM profile_update_requests
        WHERE homeowner_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['homeowner_id']]);
    $profileRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table may not exist yet — degrade silently
    $profileRequests = [];
}

$openProfileRequestsCount = 0;
foreach ($profileRequests as $requestItem) {
    if (in_array($requestItem['status'] ?? '', ['pending', 'acknowledged'], true)) {
        $openProfileRequestsCount++;
    }
}

$ownedVehicles = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            v.id AS id,
            v.plate_number AS plate_number,
            v.vehicle_type AS vehicle_type,
            v.color AS color,
            v.is_primary AS is_primary,
            h.car_img AS vehicle_img
        FROM vehicles v
        LEFT JOIN homeowners h ON h.id = v.homeowner_id
        WHERE v.homeowner_id = ? AND v.is_active = 1
        ORDER BY v.is_primary DESC, v.registered_at DESC
    ");
    $stmt->execute([$_SESSION['homeowner_id']]);
    $ownedVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log("Failed to load owned vehicles: " . $e->getMessage());
    $ownedVehicles = [];
}

if (empty($ownedVehicles) && !empty($homeowner['plate_number'])) {
    $ownedVehicles[] = [
        'id' => null,
        'plate_number' => (string)($homeowner['plate_number'] ?? ''),
        'vehicle_type' => (string)($homeowner['vehicle_type'] ?? ''),
        'color' => (string)($homeowner['color'] ?? ''),
        'is_primary' => 1,
        'vehicle_img' => (string)($homeowner['car_img'] ?? '')
    ];
}

// Only generate CSRF token if not already set (prevents multi-tab breakage)
if (empty($_SESSION['csrf_token'])) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
} else {
    $csrf_token = $_SESSION['csrf_token'];
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="<?php echo $csrf_token; ?>">
    <title>Homeowner Portal — VehiScan</title>
    <link rel="stylesheet" href="../assets/css/tailwind.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/tailwind.css'); ?>">
    <link rel="stylesheet" href="../assets/css/system.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/system.css'); ?>">
    <link rel="stylesheet" href="../assets/css/tailadmin-components.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/tailadmin-components.css'); ?>">
    <link rel="stylesheet" href="css/homeowner.css?v=<?php echo filemtime(__DIR__ . '/css/homeowner.css'); ?>">
    <link rel="stylesheet" href="../assets/css/premium-polish.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/premium-polish.css'); ?>">
    <script src="../assets/js/libs/sweetalert2.all.min.js"></script>
    <script src="../assets/js/libs/chart.umd.min.js"></script>
    <script>
        window.csrfToken = "<?php echo $csrf_token; ?>";
        window.__HOMEOWNER_NOTIF__ = {
            pendingPasses: <?php echo (int)($stats['pending'] ?? 0); ?>,
            openProfileRequests: <?php echo (int)$openProfileRequestsCount; ?>
        };
    </script>
    <script src="../assets/js/toast.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/toast.js'); ?>"></script>
    <script src="../assets/js/session-timeout.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/session-timeout.js'); ?>"></script>
</head>

<body class="m-0 p-0 overflow-hidden bg-gray-50 dark:bg-slate-900 transition-colors duration-300">

    <!-- User Dropdown (Fixed Position) -->
    <div id="user-dropdown" class="hidden fixed w-56 rounded-md border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-lg"
        style="z-index: 9999;" role="menu" aria-labelledby="user-trigger" aria-hidden="true">
        <div class="p-1">
            <button id="signOutBtn" type="button" role="menuitem"
                class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 font-medium transition-colors">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                    </path>
                </svg>
                <span>Sign Out</span>
            </button>
        </div>
    </div>

    <div class="flex h-screen w-full">
        <!-- Sidebar -->
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <!-- Mobile Overlay -->
        <div id="mobile-overlay"></div>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-hidden bg-gray-50 dark:bg-slate-950 transition-colors duration-300">
            <!-- Header -->
            <?php include __DIR__ . '/includes/header.php'; ?>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-6 homeowner-content-area">
                <!-- Dashboard Page -->
                <div id="page-dashboard" class="page-content<?php echo $homeownerActivePage === 'dashboard' ? ' active' : ''; ?>">
                    <div class="space-y-6 homeowner-dashboard-stack">
                        <!-- Welcome Card -->
                        <div class="ta-card homeowner-welcome-card">
                            <div class="welcome-gradient-overlay"></div>
                            <div class="absolute -right-20 -top-20 w-64 h-64 bg-white/10 rounded-full blur-3xl opacity-50"></div>
                            
                            <div class="welcome-content-wrapper">
                                <div class="flex flex-col md:flex-row items-center gap-6">
                                    <div class="h-20 w-20 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30 shadow-xl" aria-hidden="true">
                                        <svg class="h-10 w-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>

                                    <div class="text-center md:text-left">
                                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-3 mb-2">
                                            <h2 class="text-2xl md:text-3xl">
                                                Welcome back, <?= htmlspecialchars($homeowner['name'] ?? '') ?>!
                                            </h2>
                                            <?php
                                            $acctStatus = strtolower(trim($homeowner['account_status'] ?? 'approved'));
                                            $statusConfig = [
                                                'approved' => ['label' => 'Verified'],
                                                'pending' => ['label' => 'Pending Review'],
                                                'rejected' => ['label' => 'Account Rejected']
                                            ];
                                            $cfg = $statusConfig[$acctStatus] ?? ['label' => ucfirst($acctStatus)];
                                            ?>
                                            <span class="status-pill">
                                                <?= $cfg['label'] ?>
                                            </span>
                                        </div>
                                        <p class="text-lg opacity-90 max-w-xl">
                                            Manage your community access, visitor passes, and vehicle information from your secure portal.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Statistics Cards -->
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 lg:gap-6">
                            <div class="ta-stat-card">
                                <div class="ta-stat-icon blue">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                                    </svg>
                                </div>
                                <div class="ta-stat-content">
                                    <p class="ta-stat-label">Total Passes</p>
                                    <p class="ta-stat-value"><?= $stats['total'] ?? 0 ?></p>
                                    <p class="ta-stat-description mt-2">All time requests</p>
                                </div>
                            </div>

                            <div class="ta-stat-card">
                                <div class="ta-stat-icon green">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ta-stat-content">
                                    <p class="ta-stat-label">Active Passes</p>
                                    <p class="ta-stat-value text-emerald-600"><?= $stats['active'] ?? 0 ?></p>
                                    <p class="ta-stat-description mt-2">Currently valid</p>
                                </div>
                            </div>

                            <div class="ta-stat-card">
                                <div class="ta-stat-icon amber">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ta-stat-content">
                                    <p class="ta-stat-label">Pending Approval</p>
                                    <p class="ta-stat-value text-amber-600"><?= $stats['pending'] ?? 0 ?></p>
                                    <p class="ta-stat-description mt-2">Awaiting review</p>
                                </div>
                            </div>

                            <div class="ta-stat-card">
                                <div class="ta-stat-icon red">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ta-stat-content">
                                    <p class="ta-stat-label">Rejected</p>
                                    <p class="ta-stat-value text-rose-600"><?= $stats['rejected'] ?? 0 ?></p>
                                    <p class="ta-stat-description mt-2">Not approved</p>
                                </div>
                            </div>

                            <div class="ta-stat-card">
                                <div class="ta-stat-icon indigo">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ta-stat-content">
                                    <p class="ta-stat-label">Expired</p>
                                    <p class="ta-stat-value text-slate-500"><?= $stats['expired'] ?? 0 ?></p>
                                    <p class="ta-stat-description mt-2">Validity ended</p>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="ta-card homeowner-quick-actions-card">
                            <div class="ta-card-header homeowner-section-header">
                                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                <h3 class="ta-card-title">Quick Actions</h3>
                            </div>
                            <div class="ta-card-body homeowner-quick-actions-body">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-5 homeowner-qa-grid">
                                    <button type="button" data-action="showAddVisitorPassModal" class="group w-full text-left homeowner-qa-btn homeowner-qa-btn-primary">
                                        <div class="homeowner-qa-icon bg-blue-600 text-white">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                </svg>
                                        </div>
                                        <div class="homeowner-qa-copy">
                                            <p class="homeowner-qa-title text-gray-900 dark:text-white">Create Visitor Pass</p>
                                            <p class="homeowner-qa-subtitle text-gray-600 dark:text-gray-300">Request a new visitor pass for guests.</p>
                                        </div>
                                    </button>

                                    <button type="button" data-action="loadPage" data-page="passes" class="group w-full text-left homeowner-qa-btn homeowner-qa-btn-secondary">
                                        <div class="homeowner-qa-icon bg-slate-700 text-white">
                                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                                </svg>
                                        </div>
                                        <div class="homeowner-qa-copy">
                                            <p class="homeowner-qa-title text-gray-900 dark:text-white">View All Passes</p>
                                            <p class="homeowner-qa-subtitle text-gray-600 dark:text-gray-300">Open and manage your visitor pass requests.</p>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Pass Activity -->
                        <div class="ta-card homeowner-activity-card">
                            <div class="ta-card-header homeowner-section-header">
                                <svg class="h-5 w-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10m-12 8h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                <h3 class="ta-card-title">Recent Pass Activity</h3>
                            </div>
                            <div class="ta-card-body" id="recentPassActivity">
                                <div class="loading">Loading recent pass activity...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Visitor Passes Page -->
                <div id="page-passes" class="page-content<?php echo $homeownerActivePage === 'passes' ? ' active' : ''; ?>">
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">My Visitor Passes</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Request and manage visitor passes for your guests
                                </p>
                            </div>
                            <button type="button" data-action="showAddVisitorPassModal"
                                class="ta-btn ta-btn-primary flex items-center gap-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                <span>Add Visitor Pass</span>
                            </button>
                        </div>

                        <div class="ta-table-wrapper p-4">
                            <div class="flex flex-wrap items-center gap-3 justify-between">
                                <div class="relative flex items-center min-w-[240px] flex-1 max-w-md">
                                    <svg class="absolute left-4 h-4 w-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                    <input type="text" id="passesSearchInput" class="ta-input pl-12" placeholder="Search by visitor, purpose, or plate...">
                                </div>
                                <span id="passesResultCount" class="text-sm text-gray-600 dark:text-gray-300"></span>
                            </div>
                            <div class="ta-pill-tabs inline-flex mt-3" id="passesStatusFilter" role="tablist" aria-label="Visitor pass status filter">
                                <button type="button" class="ta-pill-tab active" data-pass-status="all">All</button>
                                <button type="button" class="ta-pill-tab" data-pass-status="pending">Pending</button>
                                <button type="button" class="ta-pill-tab" data-pass-status="active">Active</button>
                                <button type="button" class="ta-pill-tab" data-pass-status="approved">Approved</button>
                                <button type="button" class="ta-pill-tab" data-pass-status="rejected">Rejected</button>
                                <button type="button" class="ta-pill-tab" data-pass-status="expired">Expired</button>
                            </div>
                        </div>

                        <!-- Passes List Container -->
                        <div id="passes-list" class="space-y-3">
                            <div class="loading">Loading visitor passes...</div>
                        </div>
                    </div>
                </div>

                <!-- My Vehicles Page -->
                <div id="page-vehicles" class="page-content<?php echo $homeownerActivePage === 'vehicles' ? ' active' : ''; ?>">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">My Vehicles</h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Manage your registered vehicles</p>
                            </div>
                            <button id="addVehicleBtn"
                                class="ta-btn ta-btn-primary flex items-center gap-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                <span>Add Vehicle</span>
                            </button>
                        </div>

                        <div id="vehiclesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="loading col-span-full text-center py-8">Loading vehicles...</div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Activity Page -->
                <div id="page-activity" class="page-content animate-fade-in-up <?php echo $homeownerActivePage === 'activity' ? ' active' : ''; ?>">
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Vehicle Activity</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Track your vehicle entry and exit history</p>
                        </div>

                        <!-- Time Period Selector -->
                        <div class="ta-pill-tabs inline-flex glass">
                            <button class="ta-pill-tab active px-4 py-2 rounded-md text-sm font-medium"
                                data-period="week">This Week</button>
                            <button class="ta-pill-tab px-4 py-2 rounded-md text-sm font-medium" data-period="day">Today</button>
                            <button class="ta-pill-tab px-4 py-2 rounded-md text-sm font-medium" data-period="month">This
                                Month</button>
                        </div>

                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 animate-fade-in-up animate-delay-1">
                            <div class="ta-stat-card glass">
                                <div class="ta-stat-icon green">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                </div>
                                <div class="ta-stat-content">
                                    <p class="ta-stat-title">Total Entries</p>
                                    <p class="ta-stat-value" id="totalEntries">0</p>
                                </div>
                            </div>

                            <div class="ta-stat-card glass">
                                <div class="ta-stat-icon red">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                                    </svg>
                                </div>
                                <div class="ta-stat-content">
                                    <p class="ta-stat-title">Total Exits</p>
                                    <p class="ta-stat-value" id="totalExits">0</p>
                                </div>
                            </div>

                            <div class="ta-stat-card glass">
                                <div class="ta-stat-icon blue">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <div class="ta-stat-content">
                                    <p class="ta-stat-title">Total Activity</p>
                                    <p class="ta-stat-value" id="totalActivity">0</p>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Chart -->
                        <div class="ta-card">
                            <div class="ta-card-header">
                                <h3 class="ta-card-title">Activity Timeline</h3>
                            </div>
                            <div class="ta-card-body">
                                <div class="relative" style="height: 256px; width: 100%;">
                                    <canvas id="activityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Page -->
                <div id="page-profile" class="page-content<?php echo $homeownerActivePage === 'profile' ? ' active' : ''; ?>">
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">My Profile</h2>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Your personal and vehicle information</p>
                        </div>

                        <!-- Images Section -->
                        <div class="ta-card">
                            <div class="ta-card-header flex items-center gap-2">
                                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                                <h3 class="ta-card-title">Registered Images</h3>
                            </div>
                            <div class="ta-card-body">
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 lg:gap-6 w-full">
                                    <!-- Owner Image -->
                                    <div class="ta-card shadow-none h-full flex flex-col">
                                        <div class="ta-card-header flex items-center justify-between gap-2">
                                            <h4 class="ta-card-title">Owner Photo</h4>
                                            <span class="ta-badge neutral">Profile</span>
                                        </div>
                                        <div class="ta-card-body flex-1 flex flex-col">
                                            <div class="relative group flex-1">
                                                <?php if (!empty($homeowner['owner_img'])): ?>
                                                    <?php
                                                    $ownerImgPath = '../uploads/' . $homeowner['owner_img'];
                                                    if (file_exists(__DIR__ . '/../uploads/' . $homeowner['owner_img'])):
                                                        ?>
                                                        <button type="button" class="relative aspect-square overflow-hidden rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 shadow-sm transition-shadow duration-300 hover:shadow-md focus:outline-none"
                                                            data-zoom-src="<?= htmlspecialchars($ownerImgPath ?? '') ?>" data-zoom-title="Owner Photo">
                                                            <img src="<?= htmlspecialchars($ownerImgPath ?? '') ?>" alt="Owner Photo"
                                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                                        </button>
                                                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                            <button type="button"
                                                                data-zoom-src="<?= htmlspecialchars($ownerImgPath ?? '') ?>" data-zoom-title="Owner Photo"
                                                                class="ta-btn ta-btn-secondary h-9 w-9 justify-center rounded-lg bg-white/90 px-0 shadow-lg backdrop-blur hover:bg-white dark:bg-slate-800/90 dark:hover:bg-slate-700"
                                                                aria-label="View owner photo">
                                                                <svg class="h-5 w-5 text-gray-700 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="w-full aspect-square rounded-xl border-2 border-dashed border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-800 flex items-center justify-center">
                                                            <div class="text-center p-4">
                                                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                </svg>
                                                                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Image file not found</p>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="w-full aspect-square rounded-xl border-2 border-dashed border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-800 flex items-center justify-center">
                                                        <div class="text-center p-4">
                                                            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                            </svg>
                                                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">No owner photo</p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Vehicle Image -->
                                    <div class="ta-card shadow-none h-full flex flex-col">
                                        <div class="ta-card-header flex items-center justify-between gap-2">
                                            <h4 class="ta-card-title">Vehicle Photo</h4>
                                            <span class="ta-badge neutral">Profile</span>
                                        </div>
                                        <div class="ta-card-body flex-1 flex flex-col">
                                            <div class="relative group flex-1">
                                                <?php if (!empty($homeowner['car_img'])): ?>
                                                    <?php
                                                    $carImgPath = '../uploads/' . $homeowner['car_img'];
                                                    if (file_exists(__DIR__ . '/../uploads/' . $homeowner['car_img'])):
                                                        ?>
                                                        <button type="button" class="relative aspect-square overflow-hidden rounded-xl border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800 shadow-sm transition-shadow duration-300 hover:shadow-md focus:outline-none"
                                                            data-zoom-src="<?= htmlspecialchars($carImgPath ?? '') ?>" data-zoom-title="Vehicle Photo">
                                                            <img src="<?= htmlspecialchars($carImgPath ?? '') ?>" alt="Vehicle Photo"
                                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                                        </button>
                                                        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                            <button type="button"
                                                                data-zoom-src="<?= htmlspecialchars($carImgPath ?? '') ?>" data-zoom-title="Vehicle Photo"
                                                                class="ta-btn ta-btn-secondary h-9 w-9 justify-center rounded-lg bg-white/90 px-0 shadow-lg backdrop-blur hover:bg-white dark:bg-slate-800/90 dark:hover:bg-slate-700"
                                                                aria-label="View vehicle photo">
                                                                <svg class="h-5 w-5 text-gray-700 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"></path>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="w-full aspect-square rounded-xl border-2 border-dashed border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-800 flex items-center justify-center">
                                                            <div class="text-center p-4">
                                                                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                </svg>
                                                                <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Image file not found</p>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <div class="w-full aspect-square rounded-xl border-2 border-dashed border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-800 flex items-center justify-center">
                                                        <div class="text-center p-4">
                                                            <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                                                            </svg>
                                                            <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">No vehicle photo</p>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- View All Vehicles -->
                                    <div class="ta-card shadow-none h-full flex flex-col">
                                        <div class="ta-card-header flex items-center justify-between gap-2">
                                            <h4 class="ta-card-title">My Vehicles</h4>
                                            <div class="flex items-center gap-2">
                                                <span class="ta-badge neutral"><?= count($ownedVehicles) ?> Registered</span>
                                                <button type="button" class="ta-btn ta-btn-primary ta-btn-sm" data-action="showAddVehicleModal">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                                    Add New
                                                </button>
                                            </div>
                                        </div>
                                        <div class="ta-card-body flex-1 flex flex-col">
                                            <?php if (empty($ownedVehicles)): ?>
                                                <div class="w-full min-h-[220px] rounded-xl border-2 border-dashed border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-800 flex items-center justify-center">
                                                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">No registered vehicles found</p>
                                                </div>
                                            <?php else: ?>
                                                <div class="space-y-2 max-h-[320px] overflow-y-auto pr-1">
                                                    <?php foreach ($ownedVehicles as $vehicle): ?>
                                                        <?php
                                                        $vehicleImg = trim((string)($vehicle['vehicle_img'] ?? ''));
                                                        $vehicleImgPath = '';
                                                        if ($vehicleImg !== '') {
                                                            $vehicleImgClean = ltrim($vehicleImg, '/');
                                                            if (stripos($vehicleImgClean, 'uploads/') !== 0) {
                                                                $vehicleImgClean = 'uploads/' . $vehicleImgClean;
                                                            }
                                                            if (file_exists(__DIR__ . '/../' . $vehicleImgClean)) {
                                                                $vehicleImgPath = '../' . $vehicleImgClean;
                                                            }
                                                        }
                                                        ?>
                                                        <div class="rounded-lg border border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800/80 px-3 py-2.5 flex items-center justify-between gap-3">
                                                            <div class="min-w-0">
                                                                <div class="flex items-center gap-2 flex-wrap">
                                                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 leading-tight"><?= htmlspecialchars((string)($vehicle['plate_number'] ?? '')) ?></p>
                                                                    <?php if (!empty($vehicle['is_primary'])): ?>
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Primary</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5"><?= htmlspecialchars((string)($vehicle['vehicle_type'] ?? 'Unknown Type')) ?> • <?= htmlspecialchars((string)($vehicle['color'] ?? 'Unknown Color')) ?></p>
                                                            </div>
                                                            <div class="flex items-center gap-2">
                                                                <?php if ($vehicleImgPath !== ''): ?>
                                                                    <button type="button"
                                                                        data-zoom-src="<?= htmlspecialchars($vehicleImgPath ?? '') ?>" data-zoom-title="Vehicle Image - <?= htmlspecialchars((string)($vehicle['plate_number'] ?? '')) ?>"
                                                                        class="ta-btn ta-btn-secondary h-8 px-2.5 text-xs whitespace-nowrap"
                                                                        aria-label="View vehicle image">
                                                                        View image
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap hidden sm:inline">No image</span>
                                                                <?php endif; ?>
                                                                <button type="button" data-action="deleteVehicle" data-vehicle-id="<?= (int)$vehicle['id'] ?>" class="ta-btn ta-btn-secondary h-8 w-8 px-0 flex items-center justify-center text-red-500 hover:bg-red-50 hover:border-red-200" title="Remove Vehicle">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Personal Information -->
                        <div class="ta-card">
                            <div class="ta-card-header flex items-center gap-2">
                                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                <h3 class="ta-card-title">Personal Information</h3>
                            </div>
                            <div class="ta-card-body">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="ta-card shadow-none border-gray-200 dark:border-slate-700 bg-gray-50/60 dark:bg-slate-800/60">
                                        <div class="ta-card-body p-4">
                                            <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Full Name</span>
                                            <p class="text-base font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($homeowner['name'] ?? '') ?></p>
                                        </div>
                                    </div>
                                    <div class="ta-card shadow-none border-gray-200 dark:border-slate-700 bg-gray-50/60 dark:bg-slate-800/60">
                                        <div class="ta-card-body p-4">
                                            <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Address</span>
                                            <p class="text-base font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($homeowner['address'] ?? '') ?></p>
                                        </div>
                                    </div>
                                    <div class="ta-card shadow-none border-gray-200 dark:border-slate-700 bg-gray-50/60 dark:bg-slate-800/60">
                                        <div class="ta-card-body p-4">
                                            <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Contact Number</span>
                                            <p class="text-base font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($homeowner['contact_number'] ?? 'N/A') ?></p>
                                        </div>
                                    </div>
                                    <div class="ta-card shadow-none border-gray-200 dark:border-slate-700 bg-gray-50/60 dark:bg-slate-800/60">
                                        <div class="ta-card-body p-4">
                                            <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Plate Number</span>
                                            <p class="inline-flex text-base font-mono font-medium text-gray-900 dark:text-gray-100 bg-white dark:bg-slate-700 px-3 py-2 rounded-lg border border-gray-200 dark:border-slate-600"><?= htmlspecialchars($homeowner['plate_number'] ?? '') ?></p>
                                        </div>
                                    </div>
                                    <div class="ta-card shadow-none border-gray-200 dark:border-slate-700 bg-gray-50/60 dark:bg-slate-800/60">
                                        <div class="ta-card-body p-4">
                                            <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Vehicle Type</span>
                                            <p class="text-base font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($homeowner['vehicle_type'] ?? '') ?></p>
                                        </div>
                                    </div>
                                    <div class="ta-card shadow-none border-gray-200 dark:border-slate-700 bg-gray-50/60 dark:bg-slate-800/60">
                                        <div class="ta-card-body p-4">
                                            <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Vehicle Color</span>
                                            <p class="text-base font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($homeowner['color'] ?? '') ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Request Profile Changes -->
                        <div class="ta-card">
                            <div class="ta-card-header flex items-center gap-2">
                                <svg class="h-5 w-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                <h3 class="ta-card-title">Request Profile Changes</h3>
                            </div>

                            <div class="ta-card-body space-y-4">
                                <div class="ta-card shadow-none border-blue-200 dark:border-blue-800 bg-blue-50/70 dark:bg-slate-800/70">
                                    <div class="ta-card-body flex items-start gap-3 p-4">
                                        <svg class="h-5 w-5 flex-shrink-0 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        <p class="text-sm text-blue-900 dark:text-blue-100 leading-6">
                                            Need to correct information on your profile, such as your name, address, plate number, or other registration details? Submit a request below and an administrator will review it.
                                        </p>
                                    </div>
                                </div>

                                <?php
                                $openRequest = null;
                                foreach ($profileRequests as $req) {
                                    if (in_array($req['status'], ['pending', 'acknowledged'])) {
                                        $openRequest = $req;
                                        break;
                                    }
                                }
                                $statusBadgeClasses = [
                                    'pending'      => 'warning',
                                    'acknowledged' => 'info',
                                    'completed'    => 'success',
                                    'rejected'     => 'danger',
                                ];
                                ?>

                                <?php if ($openRequest): ?>
                                <!-- Open request notice -->
                                <div class="ta-card border-amber-200 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20">
                                    <div class="flex items-start gap-3">
                                        <svg class="h-5 w-5 text-amber-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                        </svg>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">You have an open request</p>
                                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5 break-words"><?= htmlspecialchars(mb_strimwidth($openRequest['request_text'], 0, 120, '...')) ?></p>
                                            <div class="flex items-center gap-2 mt-2">
                                                <span class="ta-badge <?= $statusBadgeClasses[$openRequest['status']] ?? 'neutral' ?>">
                                                    <?= ucfirst($openRequest['status']) ?>
                                                </span>
                                                <span class="text-xs text-gray-500"><?= formatDisplayDateTime($openRequest['created_at'], false) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <!-- Draft profile editor -->
                                <form id="profileRequestForm" class="ta-card shadow-none border-gray-200 dark:border-slate-700 bg-gray-50/70 dark:bg-slate-800/70 space-y-3" data-homeowner-id="<?= (int)($_SESSION['homeowner_id'] ?? 0) ?>">
                                    <div class="ta-card-body space-y-4 p-4">
                                        <div class="rounded-xl border border-blue-200 bg-blue-50/70 px-4 py-3 text-sm text-blue-900 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-100">
                                            Edit your profile locally first. Nothing is applied globally until you submit the request for approval.
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div class="ta-form-group">
                                                <label for="draftName" class="ta-label">Full Name</label>
                                                <input id="draftName" class="ta-input" type="text" maxlength="120" value="<?= htmlspecialchars($homeowner['name'] ?? '') ?>">
                                            </div>
                                            <div class="ta-form-group">
                                                <label for="draftContact" class="ta-label">Contact Number</label>
                                                <input id="draftContact" class="ta-input" type="text" maxlength="30" value="<?= htmlspecialchars($homeowner['contact_number'] ?? '') ?>">
                                            </div>
                                            <div class="ta-form-group md:col-span-2">
                                                <label for="draftAddress" class="ta-label">Address</label>
                                                <textarea id="draftAddress" class="ta-input resize-y" rows="3" maxlength="255"><?= htmlspecialchars($homeowner['address'] ?? '') ?></textarea>
                                            </div>
                                            <div class="ta-form-group">
                                                <label for="draftPlate" class="ta-label">Plate Number</label>
                                                <input id="draftPlate" class="ta-input uppercase" type="text" maxlength="15" value="<?= htmlspecialchars($homeowner['plate_number'] ?? '') ?>" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z0-9- ]/g, '').slice(0, 15)">
                                            </div>
                                            <div class="ta-form-group">
                                                <label for="draftVehicleType" class="ta-label">Vehicle Type</label>
                                                <input id="draftVehicleType" class="ta-input" type="text" maxlength="40" value="<?= htmlspecialchars($homeowner['vehicle_type'] ?? '') ?>">
                                            </div>
                                            <div class="ta-form-group">
                                                <label for="draftColor" class="ta-label">Vehicle Color</label>
                                                <input id="draftColor" class="ta-input" type="text" maxlength="30" value="<?= htmlspecialchars($homeowner['color'] ?? '') ?>">
                                            </div>
                                            <div class="ta-form-group">
                                                <label for="draftOwnerImg" class="ta-label">Update Owner Photo</label>
                                                <input id="draftOwnerImg" name="owner_img" type="file" accept="image/*" class="ta-input py-1.5 text-xs">
                                            </div>
                                            <div class="ta-form-group">
                                                <label for="draftCarImg" class="ta-label">Update Vehicle Photo</label>
                                                <input id="draftCarImg" name="car_img" type="file" accept="image/*" class="ta-input py-1.5 text-xs">
                                            </div>
                                        </div>

                                        <div class="rounded-xl border border-gray-200 bg-white/80 px-4 py-3 text-sm text-gray-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-gray-200">
                                            <p class="font-medium">Profile state</p>
                                            <p class="text-xs mt-1" id="profileDraftState">Draft saved locally only.</p>
                                        </div>

                                        <input type="hidden" id="profileRequestText" name="request_text" maxlength="2000">
                                        <input type="hidden" id="profileRequestDraft" name="draft_payload">

                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <p class="text-xs text-gray-500 dark:text-gray-400">You will review the draft below before submitting it for approval.</p>
                                            <div class="flex gap-2">
                                                <button type="button" id="saveProfileDraftBtn" class="ta-btn ta-btn-secondary">
                                                    Save Draft
                                                </button>
                                                <button type="submit" id="submitProfileReqBtn" class="ta-btn ta-btn-primary">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                                    </svg>
                                                    Submit Request
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <?php endif; ?>

                                <?php if (!empty($profileRequests)): ?>
                                <!-- Request history -->
                                <div class="mt-2">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Recent Requests</h4>
                                    <div class="space-y-3">
                                        <?php foreach ($profileRequests as $req): ?>
                                        <div class="ta-card shadow-none border-gray-200 dark:border-slate-700 bg-gray-50/70 dark:bg-slate-800/70">
                                            <div class="ta-card-body p-4">
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm text-gray-800 dark:text-gray-200 break-words"><?= htmlspecialchars(mb_strimwidth($req['request_text'], 0, 100, '...')) ?></p>
                                                        <?php if ($req['admin_notes']): ?>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><span class="font-medium">Admin note:</span> <?= htmlspecialchars(mb_strimwidth($req['admin_notes'], 0, 100, '...')) ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="flex-shrink-0 flex flex-col items-end gap-1">
                                                        <span class="ta-badge <?= $statusBadgeClasses[$req['status']] ?? 'neutral' ?>">
                                                            <?= ucfirst($req['status']) ?>
                                                        </span>
                                                        <span class="text-xs text-gray-400"><?= formatDisplayDateTime($req['created_at'], false) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Security Settings -->
                        <div class="ta-card mt-6">
                            <div class="ta-card-header flex items-center gap-2">
                                <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                <h3 class="ta-card-title">Security & Password</h3>
                            </div>
                            <div class="ta-card-body">
                                <form id="changePasswordForm" class="space-y-4 max-w-lg">
                                    <div class="ta-form-group">
                                        <label for="current_password" class="ta-label">Current Password</label>
                                        <input type="password" id="current_password" name="current_password" required class="ta-input" placeholder="••••••••">
                                    </div>
                                    <div class="ta-grid-2">
                                        <div class="ta-form-group">
                                            <label for="new_password" class="ta-label">New Password</label>
                                            <input type="password" id="new_password" name="new_password" required class="ta-input" placeholder="••••••••" minlength="8">
                                        </div>
                                        <div class="ta-form-group">
                                            <label for="confirm_password" class="ta-label">Confirm New Password</label>
                                            <input type="password" id="confirm_password" name="confirm_password" required class="ta-input" placeholder="••••••••" minlength="8">
                                        </div>
                                    </div>
                                    <div class="flex justify-end pt-2">
                                        <button type="submit" id="changePasswordBtn" class="ta-btn ta-btn-primary">
                                            Update Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Vehicle Modal -->
    <div id="addVehicleModal" class="hidden fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <button type="button" class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity focus:outline-none" aria-hidden="true" aria-label="Close add vehicle modal" data-action="closeAddVehicleModal"></button>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-slate-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <form id="addVehicleForm" action="api/add_vehicle.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <div class="bg-white dark:bg-slate-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">Register New Vehicle</h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Plate Number <span class="text-red-500">*</span></label>
                                <input type="text" name="plate_number" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-slate-700 dark:text-white px-3 py-2 border" placeholder="e.g. ABC-1234">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Type <span class="text-red-500">*</span></label>
                                <select name="vehicle_type" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-slate-700 dark:text-white px-3 py-2 border">
                                    <option value="sedan">Sedan</option>
                                    <option value="suv">SUV</option>
                                    <option value="motorcycle">Motorcycle</option>
                                    <option value="truck">Truck</option>
                                    <option value="van">Van</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Color <span class="text-red-500">*</span></label>
                                <input type="text" name="color" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-slate-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm dark:bg-slate-700 dark:text-white px-3 py-2 border" placeholder="e.g. Red, Blue, Silver">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Vehicle Photo (Optional)</label>
                                <input type="file" name="vehicle_img" accept="image/*" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-slate-700 dark:file:text-blue-400">
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-slate-900/40 px-4 py-3 sm:px-6 flex flex-row-reverse gap-2">
                        <button type="submit" id="addVehicleSubmitBtn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Add Vehicle
                        </button>
                        <button type="button" data-action="closeAddVehicleModal" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-slate-600 shadow-sm px-4 py-2 bg-white dark:bg-slate-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/utils/html-escape.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/utils/html-escape.js'); ?>"></script>
    <script src="../assets/js/keyboard-shortcuts.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/keyboard-shortcuts.js'); ?>"></script>
    <script src="../assets/js/mobile-gestures.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/mobile-gestures.js'); ?>"></script>
    <script src="../assets/js/table-enhancer.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/table-enhancer.js'); ?>"></script>
    <script src="js/homeowner.js?v=<?php echo filemtime(__DIR__ . '/js/homeowner.js'); ?>"></script>
    <script src="js/homeowner-dark-mode.js?v=<?php echo filemtime(__DIR__ . '/js/homeowner-dark-mode.js'); ?>"></script>
    <script src="js/vehicle-management.js?v=<?php echo filemtime(__DIR__ . '/js/vehicle-management.js'); ?>"></script>

    <script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const homeownerId = document.getElementById('profileRequestForm')?.dataset.homeownerId || '';
        const draftStorageKey = homeownerId ? `vehiscan.profileDraft.${homeownerId}` : 'vehiscan.profileDraft';

        const draftInputs = {
            name: document.getElementById('draftName'),
            contact: document.getElementById('draftContact'),
            address: document.getElementById('draftAddress'),
            plate: document.getElementById('draftPlate'),
            vehicleType: document.getElementById('draftVehicleType'),
            color: document.getElementById('draftColor')
        };
        const profileRequestText = document.getElementById('profileRequestText');
        const profileRequestDraft = document.getElementById('profileRequestDraft');
        const profileDraftState = document.getElementById('profileDraftState');
        const saveDraftBtn = document.getElementById('saveProfileDraftBtn');

        const defaultDraft = {
            name: <?= json_encode($homeowner['name'] ?? '') ?>,
            contact: <?= json_encode($homeowner['contact_number'] ?? '') ?>,
            address: <?= json_encode($homeowner['address'] ?? '') ?>,
            plate: <?= json_encode($homeowner['plate_number'] ?? '') ?>,
            vehicleType: <?= json_encode($homeowner['vehicle_type'] ?? '') ?>,
            color: <?= json_encode($homeowner['color'] ?? '') ?>
        };

        const readDraft = () => {
            try {
                const saved = localStorage.getItem(draftStorageKey);
                if (!saved) return defaultDraft;
                return { ...defaultDraft, ...JSON.parse(saved) };
            } catch (error) {
                return defaultDraft;
            }
        };

        const writeDraft = () => {
            const payload = {
                name: draftInputs.name?.value.trim() || '',
                contact: draftInputs.contact?.value.trim() || '',
                address: draftInputs.address?.value.trim() || '',
                plate: draftInputs.plate?.value.trim().toUpperCase() || '',
                vehicleType: draftInputs.vehicleType?.value.trim() || '',
                color: draftInputs.color?.value.trim() || ''
            };
            localStorage.setItem(draftStorageKey, JSON.stringify(payload));
            if (profileDraftState) {
                const changed = Object.entries(payload).some(([key, value]) => value !== (defaultDraft[key] || ''));
                profileDraftState.textContent = changed ? 'Draft saved locally. Submit when ready for admin and super admin approval.' : 'No local changes yet.';
            }
            return payload;
        };

        const applyDraft = (draft) => {
            if (draftInputs.name) draftInputs.name.value = draft.name || '';
            if (draftInputs.contact) draftInputs.contact.value = draft.contact || '';
            if (draftInputs.address) draftInputs.address.value = draft.address || '';
            if (draftInputs.plate) draftInputs.plate.value = (draft.plate || '').toUpperCase();
            if (draftInputs.vehicleType) draftInputs.vehicleType.value = draft.vehicleType || '';
            if (draftInputs.color) draftInputs.color.value = draft.color || '';
        };

        applyDraft(readDraft());

        Object.values(draftInputs).forEach((input) => {
            input?.addEventListener('input', writeDraft);
            input?.addEventListener('change', writeDraft);
        });

        if (saveDraftBtn) {
            saveDraftBtn.addEventListener('click', () => {
                writeDraft();
                Swal.fire({ icon: 'success', title: 'Draft saved', text: 'Your local profile draft has been saved in this browser.', confirmButtonColor: '#3b82f6' });
            });
        }

        // Profile request form: submit handler
        const form = document.getElementById('profileRequestForm');
        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const payload = writeDraft();
                const summaryParts = [];

                if ((payload.name || '') !== (defaultDraft.name || '')) summaryParts.push(`Name: ${defaultDraft.name || '—'} -> ${payload.name || '—'}`);
                if ((payload.contact || '') !== (defaultDraft.contact || '')) summaryParts.push(`Contact: ${defaultDraft.contact || '—'} -> ${payload.contact || '—'}`);
                if ((payload.address || '') !== (defaultDraft.address || '')) summaryParts.push(`Address updated`);
                if ((payload.plate || '') !== (defaultDraft.plate || '')) summaryParts.push(`Plate: ${defaultDraft.plate || '—'} -> ${payload.plate || '—'}`);
                if ((payload.vehicleType || '') !== (defaultDraft.vehicleType || '')) summaryParts.push(`Vehicle type updated`);
                if ((payload.color || '') !== (defaultDraft.color || '')) summaryParts.push(`Vehicle color updated`);

                const ownerImg = document.getElementById('draftOwnerImg')?.files[0];
                const carImg = document.getElementById('draftCarImg')?.files[0];

                if (ownerImg) summaryParts.push(`New owner photo uploaded`);
                if (carImg) summaryParts.push(`New vehicle photo uploaded`);

                if (summaryParts.length === 0) {
                    Swal.fire({ icon: 'warning', title: 'No changes detected', text: 'Edit at least one field before submitting for approval.', confirmButtonColor: '#3b82f6' });
                    return;
                }

                const text = `Profile change request: ${summaryParts.join('; ')}.`;
                if (profileRequestText) {
                    profileRequestText.value = text;
                }
                if (profileRequestDraft) {
                    profileRequestDraft.value = JSON.stringify(payload);
                }

                const btn = document.getElementById('submitProfileReqBtn');
                if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }

                try {
                    const fd = new FormData();
                    fd.append('request_text', text);
                    fd.append('draft_payload', JSON.stringify(payload));
                    fd.append('csrf_token', csrf);
                    
                    if (ownerImg) fd.append('owner_img', ownerImg);
                    if (carImg) fd.append('car_img', carImg);

                    const fetchJson = window.homeownerFetchJson || (async (url, options = {}) => {
                        const response = await fetch(url, {
                            credentials: 'same-origin',
                            ...options,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                ...(options.headers || {})
                            }
                        });
                        const contentType = String(response.headers.get('content-type') || '').toLowerCase();
                        if (!contentType.includes('application/json')) {
                            throw new Error(`Unexpected server response (${response.status})`);
                        }
                        const payload = await response.json();
                        if (!response.ok) {
                            throw new Error(payload.message || payload.error || `Request failed (${response.status})`);
                        }
                        return payload;
                    });

                    const data = await fetchJson('api/submit_profile_request.php', { method: 'POST', body: fd });

                    if (data.success) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Request Submitted',
                            text: data.message,
                            confirmButtonColor: '#3b82f6'
                        });
                        // Reload the portal to show the "open request" notice
                        window.location.reload();
                    } else {
                        const isCooldown = res.status === 429;
                        const message = data.message || 'Please wait before submitting another request.';
                        Swal.fire({
                            icon: isCooldown ? 'warning' : 'error',
                            title: isCooldown ? 'Request Cooldown' : 'Could not submit',
                            text: message,
                            confirmButtonColor: '#3b82f6'
                        });
                        if (btn) { btn.disabled = false; btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Submit Request'; }
                    }
                } catch (err) {
                    console.error('[ProfileRequest] submit error:', err);
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Something went wrong. Please try again.', confirmButtonColor: '#ef4444' });
                    if (btn) { btn.disabled = false; btn.innerHTML = '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Submit Request'; }
                }
            });
        }
    })();
    </script>

    <!-- Image Zoom Modal -->
    <div id="imageZoomModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center p-4 hidden z-50" role="dialog" aria-modal="true" aria-label="Image zoom viewer" aria-hidden="true">
        <button type="button" class="absolute top-4 right-4 text-white text-4xl hover:text-gray-300 transition-colors z-10" onclick="closeImageZoom()" aria-label="Close image zoom">&times;</button>
        <img id="zoomedImage" src="" alt="Zoomed" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
    </div>

</body>

</html>
