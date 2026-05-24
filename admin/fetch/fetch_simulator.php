<?php
require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../includes/request_method_helper.php';

requireRequestMethod('GET');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
  http_response_code(403);
  exit('Unauthorized');
}
require_once __DIR__ . '/../../db.php';

// Get all homeowners for dropdown
try {
    $stmt = $pdo->query("SELECT id, name, plate_number, vehicle_type FROM homeowners ORDER BY name ASC");
    $homeowners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $homeowners = [];
}

// Get recent simulations
try {
    $stmt = $pdo->query("
        SELECT rl.plate_number, rl.created_at, rl.status, h.name, h.vehicle_type
        FROM recent_logs rl
        LEFT JOIN homeowners h ON rl.plate_number = h.plate_number
        ORDER BY rl.created_at DESC, rl.log_id DESC
        LIMIT 10
    ");
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recent = [];
}
?>
<!-- Page Header -->
<div class="mb-6">
  <div class="flex items-center gap-3 mb-2">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 text-white">
      <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
      </svg>
    </div>
    <div>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">RFID Scanner Simulator</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Test the RFID scanning system without physical hardware</p>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="ta-card">
    <div class="ta-card-header">
      <h3 class="ta-card-title">Scan Vehicle</h3>
    </div>
    <div class="ta-card-body">
      <!-- Scan Mode Toggle -->
      <div class="flex bg-gray-100 dark:bg-slate-700 rounded-lg p-1 mb-4">
        <button type="button" id="modePlate" class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition-all bg-white dark:bg-slate-600 text-gray-900 dark:text-white shadow-sm" onclick="setScanMode('plate')">
          By Plate Number
        </button>
        <button type="button" id="modeRfid" class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition-all text-gray-500 dark:text-gray-400" onclick="setScanMode('rfid')">
          By RFID UID
        </button>
      </div>
      
      <!-- Plate Number Mode -->
      <div id="plateModePanel">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select a registered vehicle to simulate RFID scan</p>
        <select id="vehicleSelect" class="ta-select w-full mb-4">
          <option value="">-- Select Vehicle to Scan --</option>
          <?php foreach ($homeowners as $h): ?>
            <option value="<?php echo htmlspecialchars($h['plate_number'] ?? ''); ?>" 
                    data-name="<?php echo htmlspecialchars($h['name'] ?? ''); ?>"
                    data-type="<?php echo htmlspecialchars($h['vehicle_type'] ?? ''); ?>">
              <?php echo htmlspecialchars($h['plate_number'] ?? ''); ?> - <?php echo htmlspecialchars($h['name'] ?? ''); ?> (<?php echo htmlspecialchars($h['vehicle_type'] ?? ''); ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <!-- RFID UID Mode -->
      <div id="rfidModePanel" class="hidden">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Enter an RFID UID to simulate a tag scan (hex characters, e.g. A1B2C3D4)</p>
        <input type="text" id="rfidUidInput" 
               class="ta-input w-full font-mono uppercase mb-4"
               placeholder="Enter RFID UID (e.g. A1B2C3D4E5F6)"
               maxlength="32"
               pattern="[A-Fa-f0-9]*">
        <p class="text-xs text-gray-400 dark:text-gray-500 -mt-2 mb-4">This mode also completes active binding sessions</p>
      </div>
      
      <button id="scanBtn" class="ta-btn ta-btn-primary ta-btn-lg w-full justify-center gap-2 py-4 text-lg" disabled>
        <span class="scan-icon text-xl"><svg style="width:1.25em;height:1.25em;vertical-align:-0.2em;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 1 0 20M12 2a10 10 0 0 0 0 20M12 2v20M2 12h20"/></svg></span> <span>Simulate Scan</span>
      </button>
      
      <div id="scanResult" class="mt-4 p-4 rounded-lg border-2 hidden scan-result">
        <div class="result-icon text-4xl mb-2"></div>
        <div class="result-text text-sm dark:text-gray-200"></div>
      </div>
      
      <div class="mt-6 ta-alert ta-alert-info">
        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
          <h4 class="font-bold mb-1">How it works:</h4>
          <ul class="text-sm space-y-1 list-none pl-0">
            <li><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline;color:#10b981" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg> Select a vehicle from the dropdown</li>
            <li><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline;color:#10b981" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg> Click "Simulate Scan" button</li>
            <li><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline;color:#10b981" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg> System will create a log entry automatically</li>
            <li><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline;color:#10b981" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg> Guard panel will update in real-time (2s delay)</li>
            <li><svg style="width:1em;height:1em;vertical-align:-0.15em;display:inline;color:#10b981" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg> Perfect for testing without hardware!</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
  
  <div class="ta-card">
    <div class="ta-card-header">
      <h3 class="ta-card-title">Recent Simulations</h3>
    </div>
    <div class="ta-card-body p-0">
      <div class="ta-table-wrapper" style="border:none;box-shadow:none;">
        <table class="ta-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Plate</th>
              <th>Owner</th>
              <th>Vehicle</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="recentScans">
            <?php if (empty($recent)): ?>
              <tr><td colspan="5">
                <div class="ta-empty-state">
                  <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>
                  <p>No simulations yet</p>
                </div>
              </td></tr>
            <?php else: ?>
              <?php foreach ($recent as $r): ?>
                <tr>
                  <td class="muted"><?php echo date('h:i:s A', strtotime($r['created_at'])); ?></td>
                  <td><?php echo htmlspecialchars($r['plate_number'] ?? ''); ?></td>
                  <td class="muted"><?php echo htmlspecialchars($r['name'] ?? 'Unknown'); ?></td>
                  <td class="muted"><?php echo htmlspecialchars($r['vehicle_type'] ?? '-'); ?></td>
                  <td>
                    <span class="ta-badge <?php echo $r['status'] === 'IN' ? 'success' : 'danger'; ?>">
                      <?php echo $r['status'] === 'IN' ? 'IN' : 'OUT'; ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
.scan-icon.scanning {
  animation: rotate 1s linear infinite;
}

@keyframes rotate {
  to { transform: rotate(360deg); }
}

.scan-result {
  display: flex;
  gap: 12px;
  align-items: center;
  animation: slideIn 0.3s ease;
}

.scan-result.success {
  background: rgb(209 250 229 / 1);
  border-color: rgb(16 185 129);
}

.scan-result.error {
  background: rgb(254 226 226 / 1);
  border-color: rgb(239 68 68);
}

.dark .scan-result.success {
  background: rgb(6 78 59 / 0.3);
  border-color: rgb(16 185 129 / 0.5);
}

.dark .scan-result.error {
  background: rgb(127 29 29 / 0.3);
  border-color: rgb(239 68 68 / 0.5);
}

.result-icon {
  flex-shrink: 0;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

