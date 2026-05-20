<?php
// Security: Role-based access control
require_once __DIR__ . '/../../includes/session_admin_unified.php';
// Ensure CSP nonce helper is available for inline style/script nonces
require_once __DIR__ . '/../../includes/security_headers.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';
require_once __DIR__ . '/../../includes/fetch_auth.php';

requireRequestMethod('GET');

fetch_require_role(['super_admin', 'admin']);

require_once __DIR__ . '/../../db.php';

// Ensure vehicles table has entries synced from homeowners
try {
    // Auto-sync: create vehicle entries for homeowners that don't have one yet
    $pdo->exec("
        INSERT IGNORE INTO vehicles (homeowner_id, plate_number, vehicle_type, color, is_primary, is_active, registered_at)
        SELECT h.id, h.plate_number, COALESCE(h.vehicle_type, 'Unknown'), COALESCE(h.color, 'Unknown'), 1, 1, NOW()
        FROM homeowners h
        LEFT JOIN vehicles v ON v.homeowner_id = h.id AND v.plate_number = h.plate_number
        WHERE h.account_status = 'approved' 
          AND h.plate_number IS NOT NULL 
          AND h.plate_number != ''
          AND v.id IS NULL
    ");
} catch (Exception $e) {
    error_log('[RFID_PAGE] Sync error: ' . $e->getMessage());
}

// Get all vehicles with RFID status
try {
    $stmt = $pdo->query("
        SELECT v.id AS vehicle_id, v.plate_number, v.vehicle_type, v.color, v.brand, v.model,
               v.rfid_uid, v.rfid_bound_at, v.rfid_bound_by, v.is_active,
               h.id AS homeowner_id, h.name AS owner_name, h.contact_number, h.address
        FROM vehicles v
        LEFT JOIN homeowners h ON v.homeowner_id = h.id
        WHERE v.is_active = 1
        ORDER BY v.rfid_uid IS NULL DESC, h.name ASC
    ");
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('[RFID_PAGE] Error: ' . $e->getMessage());
    $vehicles = [];
}

// Count stats
$totalVehicles = count($vehicles);
$boundCount = count(array_filter($vehicles, fn($v) => !empty($v['rfid_uid'])));
$unboundCount = $totalVehicles - $boundCount;

// Check for active binding session
$activeSession = null;
try {
    $stmt = $pdo->query("
        SELECT bs.id, bs.target_id, bs.status, bs.expires_at, bs.created_at,
               v.plate_number, h.name
        FROM rfid_binding_sessions bs
        LEFT JOIN vehicles v ON bs.target_id = v.id AND bs.target_type = 'vehicle'
        LEFT JOIN homeowners h ON v.homeowner_id = h.id
        WHERE bs.status = 'pending' AND bs.expires_at > NOW()
        ORDER BY bs.created_at DESC
        LIMIT 1
    ");
    $activeSession = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get recent scan logs
try {
    $recentScans = $pdo->query("
        SELECT sl.rfid_uid, sl.scan_result, sl.input_source, sl.scanned_at, sl.error_message,
               v.plate_number, h.name as owner_name
        FROM rfid_scan_log sl
        LEFT JOIN vehicles v ON sl.vehicle_id = v.id
        LEFT JOIN homeowners h ON v.homeowner_id = h.id
        ORDER BY sl.scanned_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentScans = [];
}
?>

<!-- Page Header -->
<div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z">
                </path>
            </svg>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">RFID Management</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Bind, manage, and monitor RFID tags for vehicles</p>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <div class="ta-stat-card">
        <div class="ta-stat-icon blue">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
        </div>
        <div class="ta-stat-content">
            <p class="ta-stat-label">Total Vehicles</p>
            <p class="ta-stat-value"><?= $totalVehicles ?></p>
        </div>
    </div>
    <div class="ta-stat-card">
        <div class="ta-stat-icon green">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
        </div>
        <div class="ta-stat-content">
            <p class="ta-stat-label">RFID Bound</p>
            <p class="ta-stat-value" style="color: #059669;"><?= $boundCount ?></p>
        </div>
    </div>
    <div class="ta-stat-card">
        <div class="ta-stat-icon amber">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
        </div>
        <div class="ta-stat-content">
            <p class="ta-stat-label">Unbound</p>
            <p class="ta-stat-value" style="color: #d97706;"><?= $unboundCount ?></p>
        </div>
    </div>
</div>

<!-- Active Binding Session Banner -->
<div id="bindingBanner" class="mb-6 <?= $activeSession ? '' : 'hidden' ?>">
    <div class="bg-blue-50 border-2 border-blue-300 rounded-xl p-5 relative overflow-hidden">
        <div class="absolute top-0 left-0 h-full w-1.5 bg-blue-500"></div>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <div class="h-12 w-12 rounded-full bg-blue-500 flex items-center justify-center text-white text-2xl rfid-pulse">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.651a3.75 3.75 0 010-5.303m5.304 0a3.75 3.75 0 010 5.303m-7.425 2.122a6.75 6.75 0 010-9.546m9.546 0a6.75 6.75 0 010 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.789m13.788 0c3.808 3.808 3.808 9.981 0 13.79M12 12h.008v.007H12V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-blue-800">Waiting for RFID Scan...</h3>
                    <p class="text-sm text-blue-600" id="bindingTarget">
                        <?php if ($activeSession): ?>
                            Binding to: <?= htmlspecialchars($activeSession['plate_number'] ?? '') ?> (<?= htmlspecialchars($activeSession['name'] ?? '') ?>)
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-blue-500 mt-1">
                        Time remaining: <span id="bindingTimer" class="font-mono font-bold"><?php 
                            if ($activeSession) {
                                $remaining = max(0, strtotime($activeSession['expires_at']) - time());
                                echo gmdate('i:s', $remaining);
                            } else {
                                echo '05:00';
                            }
                        ?></span>
                    </p>
                    <!-- Hidden input for USB HID RFID scanners -->
                    <input type="hidden" id="csrfTokenInput" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" />
                    <input type="text" id="rfidHidInput" autocomplete="off" style="position:absolute;left:-9999px;top:-9999px;" tabindex="-1" />
                </div>
            </div>
            <button id="cancelBindingBtn" class="px-4 py-2 bg-red-500 text-white rounded-lg text-sm font-medium hover:bg-red-600 transition-colors"
                    data-session-id="<?= $activeSession ? $activeSession['id'] : '' ?>">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Action Bar -->
<div class="flex items-center gap-2 mb-4 flex-wrap">
    <button id="rfidRefreshBtn" class="ta-btn ta-btn-secondary">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
        </svg>
        Refresh
    </button>
    <div class="flex items-center gap-2 ml-auto">
        <div class="relative flex items-center">
            <svg class="absolute left-3 h-5 w-5 text-gray-500 dark:text-gray-400 pointer-events-none flex-shrink-0" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" id="rfidSearchInput"
                class="h-10 px-4 pl-12 border border-gray-300 dark:border-slate-600 rounded-lg min-w-[280px] text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all dark:bg-slate-700 dark:text-gray-200"
                placeholder="Search vehicles or owners...">
        </div>
        <span id="rfidSearchCount" class="text-sm text-gray-600 font-medium whitespace-nowrap"></span>
    </div>
</div>

<!-- Main Table: Vehicles with RFID Status -->
<div class="ta-table-wrapper" style="margin-bottom: 1.5rem;">
    <table id="rfidTable" class="ta-table">
        <thead>
            <tr>
                <th>Owner</th>
                <th>Plate</th>
                <th>Vehicle</th>
                <th class="text-center">RFID Status</th>
                <th>RFID UID</th>
                <th>Bound Date</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($vehicles)): ?>
                <tr><td colspan="7"><div class="ta-empty-state"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path></svg><p>No vehicles found</p></div></td></tr>
            <?php else: ?>
                <?php foreach ($vehicles as $v): ?>
                    <tr class="hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors even:bg-slate-50 dark:even:bg-slate-800/50" data-vehicle-id="<?= $v['vehicle_id'] ?>">
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-medium"><?= htmlspecialchars($v['owner_name'] ?? 'Unknown') ?></td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300 font-mono"><?= htmlspecialchars($v['plate_number'] ?? '') ?></td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                            <?= htmlspecialchars($v['vehicle_type'] ?? '') ?>
                            <?php if (!empty($v['color'])): ?>
                                <span class="text-xs text-gray-400">(<?= htmlspecialchars($v['color']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <?php if (!empty($v['rfid_uid'])): ?>
                                <span class="ta-badge success">Bound</span>
                            <?php else: ?>
                                <span class="ta-badge warning">Unbound</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400 font-mono text-xs">
                            <?= !empty($v['rfid_uid']) ? htmlspecialchars($v['rfid_uid']) : '<span class="text-gray-400">—</span>' ?>
                        </td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-400 text-xs">
                            <?= !empty($v['rfid_bound_at']) ? date('M j, Y g:ia', strtotime($v['rfid_bound_at'])) : '<span class="text-gray-400">—</span>' ?>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <?php if (empty($v['rfid_uid'])): ?>
                                    <button class="ta-btn ta-btn-primary ta-btn-sm btn-bind-rfid"
                                            data-vehicle-id="<?= $v['vehicle_id'] ?>"
                                            data-plate="<?= htmlspecialchars($v['plate_number'] ?? '') ?>"
                                            data-owner="<?= htmlspecialchars($v['owner_name'] ?? '') ?>">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                        </svg>
                                        Bind RFID
                                    </button>
                                <?php else: ?>
                                    <button class="ta-btn ta-btn-danger ta-btn-sm btn-unbind-rfid"
                                            data-vehicle-id="<?= $v['vehicle_id'] ?>"
                                            data-plate="<?= htmlspecialchars($v['plate_number'] ?? '') ?>"
                                            data-uid="<?= htmlspecialchars($v['rfid_uid'] ?? '') ?>">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                        </svg>
                                        Unbind
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Recent RFID Scan Log -->
<div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4 flex items-center gap-2">
        <svg class="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        Recent RFID Scans
    </h3>
    <div class="ta-table-wrapper">
        <table class="ta-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>RFID UID</th>
                    <th>Vehicle</th>
                    <th>Owner</th>
                    <th class="text-center">Result</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody id="recentScanLog">
                <?php if (empty($recentScans)): ?>
                    <tr><td colspan="6"><div class="ta-empty-state"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><p>No scan history yet</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($recentScans as $scan): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-700">
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400 text-xs whitespace-nowrap">
                                <?= date('M j, g:i:sa', strtotime($scan['scanned_at'])) ?>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-300"><?= htmlspecialchars($scan['rfid_uid'] ?? '') ?></td>
                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300"><?= htmlspecialchars($scan['plate_number'] ?? '—') ?></td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($scan['owner_name'] ?? '—') ?></td>
                            <td class="px-3 py-2 text-center">
                                <?php
                                $resultColors = [
                                    'access_granted' => 'bg-green-100 text-green-700',
                                    'access_denied' => 'bg-red-100 text-red-700',
                                    'uid_bound' => 'bg-blue-100 text-blue-700',
                                    'unknown_uid' => 'bg-gray-100 text-gray-700',
                                    'binding_failed' => 'bg-orange-100 text-orange-700',
                                    'error' => 'bg-red-100 text-red-700',
                                ];
                                $color = $resultColors[$scan['scan_result']] ?? 'bg-gray-100 text-gray-700';
                                $label = str_replace('_', ' ', ucfirst($scan['scan_result'] ?? ''));
                                ?>
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $color ?>">
                                    <?= $label ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500"><?= htmlspecialchars($scan['input_source'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style nonce="<?php echo htmlspecialchars(vehiscanGetCspNonce() ?? ''); ?>">
@keyframes rfidPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); }
    50% { box-shadow: 0 0 0 12px rgba(59, 130, 246, 0); }
}
.rfid-pulse {
    animation: rfidPulse 2s ease-in-out infinite;
}
</style>
<?php
// ... end of file remains as is but without the trailing script block.
?>

