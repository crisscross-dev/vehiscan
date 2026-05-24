# COMPREHENSIVE SYSTEM IMPROVEMENTS - IMPLEMENTATION GUIDE

## Table of Contents
1. [Database Migrations](#database-migrations)
2. [Login & Registration Enhancements](#login--registration-enhancements)
3. [QR Code & Visitor Pass Improvements](#qr-code--visitor-pass-improvements)
4. [Contact & User Information Standardization](#contact--user-information-standardization)
5. [Dashboard Charts & Data Visualization](#dashboard-charts--data-visualization)
6. [Guard Access & Security Controls](#guard-access--security-controls)
7. [Records Management & UI Cleanup](#records-management--ui-cleanup)
8. [Homeowner & Vehicle Management](#homeowner--vehicle-management)
9. [Real-Time System Behavior](#real-time-system-behavior)

---

## 🗄 Database Migrations

### Step 1: Run Comprehensive Migration

**File:** `migrations/comprehensive_system_migration.php`

Run this file to apply all database changes:

```bash
php migrations/comprehensive_system_migration.php
```

### Changes Applied:

1. **Users Table Enhancements:**
   - Added `email` VARCHAR(255) UNIQUE
   - Added `account_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
   - Added `email_verified` BOOLEAN DEFAULT FALSE
   - Added `verification_token` VARCHAR(64)
   - Added `approved_by`, `approved_at`, `rejection_reason`
   - Restructured name fields: `first_name`, `middle_name`, `last_name`, `suffix`

2. **Homeowners Table Enhancements:**
   - Added `email`, `contact_number`, `subdivision`
   - Restructured name fields to match users

3. **Vehicles Table (NEW):**
   - Multi-vehicle support per homeowner
   - Fields: `plate_number`, `vehicle_type`, `color`, `brand`, `model`, `year`
   - Auto-migrates existing vehicle data from homeowners table

4. **Visitor_Passes Table Enhancements:**
   - Added `subdivision`, `logo_path`, `is_active`, `contact_number`

5. **Email Verification Tokens Table (NEW):**
   - Manages email verification flow

6. **Account Approval Log Table (NEW):**
   - Tracks all admin approval/rejection actions

7. **Subdivision Settings Table (NEW):**
   - Customizable per-subdivision settings
   - Logo management for visitor passes

---

## 🔐 Login & Registration Enhancements

### Requirement 1: Simplify Login Page

**File:** `auth/login.php`

**Changes Needed:**
1. Remove role selection buttons (auto-detect instead)
2. Add email/username support
3. Add account status checking

**Current Implementation:**
```php
// Lines 14-28: Modified to support email OR username
$usernameOrEmail = trim($_POST['username'] ?? '');

// Check username OR email
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
$stmt->execute([$usernameOrEmail, $usernameOrEmail]);
```

**Required Changes:**
```php
// Remove role selection from login form (lines 200-250)
// Replace with auto-detection after successful authentication

if ($authenticatedUser) {
    // Auto-redirect based on role
    switch($userRole) {
        case 'super_admin':
        case 'admin':
            header("Location: ../admin/admin_panel.php");
            break;
        case 'guard':
            header("Location: ../guard/pages/guard_side.php");
            break;
        case 'homeowner':
            header("Location: ../homeowners/portal.php");
            break;
    }
    exit();
}
```

### Requirement 2: Enhanced Registration with Email

**File:** `auth/register.php` (CREATED)

**Features Implemented:**
- Email registration
- Structured name fields (first, middle, last, suffix)
- Auto-formatted contact numbers (0912-345-6789)
- Account status defaults to 'pending'
- Email verification token generation
- Role-based field visibility

**Usage:**
Users register → Account created with status='pending' → Admin approves → User can login

### Requirement 3: Account Approval Workflow

**File to CREATE:** `admin/api/approve_user_account.php`

```php
<?php
 require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';

$userId = $_POST['user_id'] ?? 0;
$action = $_POST['action'] ?? ''; // 'approve' or 'reject'
$reason = $_POST['reason'] ?? '';

try {
    if ($action === 'approve') {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET account_status = 'approved',
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ? AND account_status = 'pending'
        ");
        $stmt->execute([$_SESSION['admin_id'], $userId]);
        
        // Log approval
        $pdo->prepare("
            INSERT INTO account_approval_log (user_id, user_type, action, approved_by)
            VALUES (?, 'user', 'approved', ?)
        ")->execute([$userId, $_SESSION['admin_id']]);
        
        echo json_encode(['success' => true, 'message' => 'Account approved']);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET account_status = 'rejected',
                approved_by = ?,
                approved_at = NOW(),
                rejection_reason = ?
            WHERE id = ? AND account_status = 'pending'
        ");
        $stmt->execute([$_SESSION['admin_id'], $reason, $userId]);
        
        // Log rejection
        $pdo->prepare("
            INSERT INTO account_approval_log (user_id, user_type, action, approved_by, reason)
            VALUES (?, 'user', 'rejected', ?, ?)
        ")->execute([$userId, $_SESSION['admin_id'], $reason]);
        
        echo json_encode(['success' => true, 'message' => 'Account rejected']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```

**File to UPDATE:** `admin/admin_panel.php`

Add pending accounts section:

```php
<!-- Pending Account Approvals -->
<div class="dashboard-card">
    <h3>Pending Account Approvals</h3>
    <div id="pendingAccounts"></div>
</div>

<script>
async function loadPendingAccounts() {
    const response = await fetch('api/get_pending_accounts.php');
    const accounts = await response.json();
    
    const container = document.getElementById('pendingAccounts');
    container.innerHTML = accounts.map(acc => `
        <div class="pending-account-card">
            <p><strong>${acc.first_name} ${acc.last_name}</strong> (${acc.email})</p>
            <p>Role: ${acc.role}</p>
            <button onclick="approveAccount(${acc.id})">Approve</button>
            <button onclick="rejectAccount(${acc.id})">Reject</button>
        </div>
    `).join('');
}

async function approveAccount(userId) {
    const response = await fetch('api/approve_user_account.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({user_id: userId, action: 'approve'})
    });
    loadPendingAccounts(); // Reload
}
</script>
```

---

## QR Code & Visitor Pass Improvements

### Requirement 4: Customizable QR with Subdivision Logo

**File to UPDATE:** `admin/api/qr_helper.php`

**Current Code:**
```php
function generateVisitorPassQR($passId, $token, $pdo) {
    // ... existing code ...
    $verifyUrl = "$baseUrl/visitor/scan.php?token=$token";
    QRcode::png($verifyUrl, $tempFile, QR_ECLEVEL_M, 6, 2);
}
```

**Enhanced Implementation:**
```php
function generateVisitorPassQR($passId, $token, $pdo, $subdivision = 'Ville de Palme') {
    try {
        // Get subdivision settings
        $stmt = $pdo->prepare("SELECT logo_path FROM subdivision_settings WHERE subdivision_name = ?");
        $stmt->execute([$subdivision]);
        $settings = $stmt->fetch();
        $logoPath = $settings['logo_path'] ?? '/ville_de_palme.png';
        
        // Generate QR code
        $qrDir = __DIR__ . '/../../uploads/qr_codes';
        if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);
        
        $baseUrl = rtrim(APP_URL, '/');
        $verifyUrl = "$baseUrl/visitor/scan.php?token=$token";
        
        $tempQR = $qrDir . "/temp_qr_$passId.png";
        QRcode::png($verifyUrl, $tempQR, QR_ECLEVEL_H, 10, 2);
        
        // Overlay logo on QR code
        $finalQR = addLogoToQR($tempQR, __DIR__ . '/../../' . ltrim($logoPath, '/'), $qrDir . "/pass_$passId.png");
        
        // Convert to base64
        $imageData = file_get_contents($finalQR);
        $base64 = base64_encode($imageData);
        $qrCodeData = 'data:image/png;base64,' . $base64;
        
        // Cleanup
        if (file_exists($tempQR)) unlink($tempQR);
        if (file_exists($finalQR)) unlink($finalQR);
        
        return $qrCodeData;
    } catch (Exception $e) {
        error_log("QR generation error: " . $e->getMessage());
        return null;
    }
}

function addLogoToQR($qrPath, $logoPath, $outputPath) {
    // Load QR code image
    $qr = imagecreatefrompng($qrPath);
    $qrWidth = imagesx($qr);
    $qrHeight = imagesy($qr);
    
    // Load logo
    $logoInfo = getimagesize($logoPath);
    $logoType = $logoInfo[2];
    
    switch($logoType) {
        case IMAGETYPE_PNG:
            $logo = imagecreatefrompng($logoPath);
            break;
        case IMAGETYPE_JPEG:
            $logo = imagecreatefromjpeg($logoPath);
            break;
        default:
            return $qrPath; // Logo format not supported, return original QR
    }
    
    // Calculate logo size (20% of QR size)
    $logoWidth = imagesx($logo);
    $logoHeight = imagesy($logo);
    $targetLogoWidth = $qrWidth * 0.2;
    $targetLogoHeight = $logoHeight * ($targetLogoWidth / $logoWidth);
    
    // Create white background for logo
    $logoX = ($qrWidth - $targetLogoWidth) / 2;
    $logoY = ($qrHeight - $targetLogoHeight) / 2;
    $white = imagecolorallocate($qr, 255, 255, 255);
    imagefilledrectangle($qr, $logoX - 5, $logoY - 5, $logoX + $targetLogoWidth + 5, $logoY + $targetLogoHeight + 5, $white);
    
    // Overlay logo
    imagecopyresampled($qr, $logo, $logoX, $logoY, 0, 0, $targetLogoWidth, $targetLogoHeight, $logoWidth, $logoHeight);
    
    // Save final image
    imagepng($qr, $outputPath);
    imagedestroy($qr);
    imagedestroy($logo);
    
    return $outputPath;
}
```

**Update Visitor Pass Creation:**
```php
// File: admin/api/create_visitor_pass.php
$subdivision = $_POST['subdivision'] ?? 'Ville de Palme';

$stmt = $pdo->prepare("
    INSERT INTO visitor_passes (homeowner_id, subdivision, visitor_name, visitor_plate, ...)
    VALUES (?, ?, ?, ?, ...)
");
$stmt->execute([$homeownerId, $subdivision, $visitorName, ...]);

$qrCode = generateVisitorPassQR($passId, $qrToken, $pdo, $subdivision);
```

### Requirement 5: Active Pass Filtering

**File to UPDATE:** `guard/fetch/fetch_visitors.php`

```php
// Current query
$stmt = $pdo->prepare("
    SELECT vp.*, h.name as homeowner_name, h.address
    FROM visitor_passes vp
    LEFT JOIN homeowners h ON vp.homeowner_id = h.id
    WHERE vp.is_active = TRUE 
        AND vp.status = 'approved'
        AND (vp.valid_until IS NULL OR vp.valid_until >= NOW())
    ORDER BY vp.created_at DESC
");
```

---

## 📞 Contact & User Information Standardization

### Requirement 6: Auto-Format Contact Numbers

**JavaScript Implementation (All Forms):**

```javascript
// File: assets/js/contact-formatter.js
function formatContactNumber(input) {
    let value = input.value.replace(/\D/g, ''); // Remove non-digits
    
    if (value.length > 4 && value.length <= 7) {
        value = value.slice(0, 4) + '-' + value.slice(4);
    } else if (value.length > 7) {
        value = value.slice(0, 4) + '-' + value.slice(4, 7) + '-' + value.slice(7, 11);
    }
    
    input.value = value;
}

// Auto-attach to all contact number inputs
document.querySelectorAll('input[name="contact_number"], input[type="tel"]').forEach(input => {
    input.addEventListener('input', () => formatContactNumber(input));
});
```

**PHP Validation:**
```php
// File: includes/validators.php
function validateContactNumber($number) {
    $cleaned = preg_replace('/\D/', '', $number);
    
    // Philippine mobile: 11 digits starting with 09
    if (!preg_match('/^09\d{9}$/', $cleaned)) {
        return false;
    }
    
    return substr($cleaned, 0, 4) . '-' . substr($cleaned, 4, 3) . '-' . substr($cleaned, 7);
}
```

### Requirement 7: Name Field Restructuring

All forms updated to use:
- `first_name` (required)
- `middle_name` (optional)
- `last_name` (required)
- `suffix` (optional)

Display format: `{first_name} {middle_name} {last_name} {suffix}`

---

## Dashboard Charts & Data Visualization

### Requirement 8: Replace Summary Cards with Charts

**File to UPDATE:** `admin/admin_panel.php`

**Install Chart.js:**
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
```

**Homeowner Distribution Pie Chart:**
```html
<div class="dashboard-card">
    <h3>Homeowner Distribution by Subdivision</h3>
    <canvas id="homeownerPieChart" width="400" height="400"></canvas>
</div>

<script>
async function loadHomeownerChart() {
    const response = await fetch('api/get_homeowner_stats.php');
    const data = await response.json();
    
    new Chart(document.getElementById('homeownerPieChart'), {
        type: 'pie',
        data: {
            labels: data.map(d => d.subdivision),
            datasets: [{
                data: data.map(d => d.count),
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {position: 'right'}
            }
        }
    });
}
</script>
```

**Visitor Activity Line Chart:**
```html
<div class="dashboard-card">
    <h3>Visitor Activity (Last 7 Days)</h3>
    <canvas id="visitorActivityChart" width="800" height="400"></canvas>
</div>

<script>
async function loadVisitorActivityChart() {
    const response = await fetch('api/get_visitor_activity.php?days=7');
    const data = await response.json();
    
    new Chart(document.getElementById('visitorActivityChart'), {
        type: 'line',
        data: {
            labels: data.map(d => d.date),
            datasets: [
                {
                    label: 'Check-in',
                    data: data.map(d => d.in_count),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true
                },
                {
                    label: 'Check-out',
                    data: data.map(d => d.out_count),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {beginAtZero: true}
            }
        }
    });
}
</script>
```

**API Endpoint:** `admin/api/get_visitor_activity.php`
```php
<?php
 require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';

$days = $_GET['days'] ?? 7;

$stmt = $pdo->prepare("
    SELECT 
        DATE(timestamp) as date,
        SUM(CASE WHEN status = 'IN' THEN 1 ELSE 0 END) as in_count,
        SUM(CASE WHEN status = 'OUT' THEN 1 ELSE 0 END) as out_count
    FROM access_logs
    WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
    GROUP BY DATE(timestamp)
    ORDER BY date ASC
");
$stmt->execute([$days]);
echo json_encode($stmt->fetchAll());
```

---

## Guard Access & Security Controls

### Requirement 9: Restrict Guard Permissions

**File to UPDATE:** `guard/clear_all_logs.php`

```php
// REMOVE THIS FILE or add permission check
<?php
require_once __DIR__ . '/../includes/session_guard.php';

// Guards cannot delete logs - redirect
header('HTTP/1.1 403 Forbidden');
echo json_encode(['success' => false, 'message' => 'Permission denied']);
exit();
```

**File to UPDATE:** `guard/js/guard_side.js`

```javascript
// Remove delete logs button from guard interface
// Lines 1510-1630: Comment out or remove clearAllLogsBtn handler

// Only show active visitor passes
async function loadVisitorPasses() {
    const response = await fetch('fetch/fetch_visitors.php?active_only=true');
    const passes = await response.json();
    // ... render passes
}
```

---

## Records Management & UI Cleanup

### Requirement 10: Searchable Data Tables

**Install DataTables:**
```html
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
```

**Update Tables:**
```javascript
$('#homeownersTable').DataTable({
    responsive: true,
    pageLength: 25,
    order: [[1, 'asc']], // Sort by name
    columnDefs: [
        {targets: [0], visible: false} // Hide ID column
    ]
});
```

### Requirement 11: Hide Record IDs

**Display Format:**
```php
// Instead of: ID #123
// Use: Record #00123
<?php
function formatRecordNumber($id) {
    return str_pad($id, 5, '0', STR_PAD_LEFT);
}
?>

<!-- Display -->
<span class="record-number">Record #<?= formatRecordNumber($record['id']) ?></span>
```

### Requirement 12: Standardize Button Colors

**File to CREATE:** `assets/css/button-standards.css`

```css
/* Primary Actions (Create, Submit, Save) */
.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    color: white;
}

/* Success Actions (Approve, Confirm) */
.btn-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

/* Danger Actions (Delete, Reject) */
.btn-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

/* Secondary Actions (Cancel, Back) */
.btn-secondary {
    background: #6b7280;
    color: white;
}

/* Info Actions (View, Details) */
.btn-info {
    background: linear-gradient(135deg, #06b6d4, #0891b2);
    color: white;
}
```

---

## Homeowner & Vehicle Management

### Requirement 13: Multi-Vehicle Support

**File to CREATE:** `homeowners/api/add_vehicle.php`

```php
<?php
require_once __DIR__ . '/../../includes/session_homeowner.php';
require_once __DIR__ . '/../../db.php';

$homeownerId = $_SESSION['homeowner_id'];
$plateNumber = strtoupper(trim($_POST['plate_number']));
$vehicleType = trim($_POST['vehicle_type']);
$color = trim($_POST['color']);
$brand = trim($_POST['brand'] ?? '');
$model = trim($_POST['model'] ?? '');
$year = intval($_POST['year'] ?? 0);

try {
    $stmt = $pdo->prepare("
        INSERT INTO vehicles (homeowner_id, plate_number, vehicle_type, color, brand, model, year)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$homeownerId, $plateNumber, $vehicleType, $color, $brand, $model, $year]);
    
    echo json_encode(['success' => true, 'message' => 'Vehicle added successfully']);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) { // Duplicate entry
        echo json_encode(['success' => false, 'error' => 'Plate number already registered']);
    } else {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
```

**Homeowner Profile UI:**
```html
<div class="vehicles-section">
    <h3>My Vehicles</h3>
    <button onclick="showAddVehicleModal()">+ Add Vehicle</button>
    
    <div id="vehiclesList"></div>
</div>

<script>
async function loadVehicles() {
    const response = await fetch('api/get_my_vehicles.php');
    const vehicles = await response.json();
    
    document.getElementById('vehiclesList').innerHTML = vehicles.map((v, index) => `
        <div class="vehicle-card">
            <div class="vehicle-icon"></div>
            <div>
                <h4>${v.plate_number} ${v.is_primary ? '<span class="badge-primary">Primary</span>' : ''}</h4>
                <p>${v.brand} ${v.model} ${v.year ? `(${v.year})` : ''}</p>
                <p>${v.vehicle_type} - ${v.color}</p>
            </div>
            <div class="vehicle-actions">
                ${!v.is_primary ? `<button onclick="setPrimaryVehicle(${v.id})">Set as Primary</button>` : ''}
                <button onclick="viewVehicleActivity(${v.id})">View Activity</button>
                <button onclick="removeVehicle(${v.id})" class="btn-danger">Remove</button>
            </div>
        </div>
    `).join('');
}
</script>
```

### Requirement 14: Vehicle Activity Logs

**File to CREATE:** `homeowners/api/get_vehicle_activity.php`

```php
<?php
require_once __DIR__ . '/../../includes/session_homeowner.php';
require_once __DIR__ . '/../../db.php';

$vehicleId = $_GET['vehicle_id'] ?? 0;
$days = $_GET['days'] ?? 30;

// Verify vehicle belongs to logged-in homeowner
$stmt = $pdo->prepare("SELECT homeowner_id FROM vehicles WHERE id = ?");
$stmt->execute([$vehicleId]);
$vehicle = $stmt->fetch();

if (!$vehicle || $vehicle['homeowner_id'] != $_SESSION['homeowner_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get activity logs
$stmt = $pdo->prepare("
    SELECT 
        al.timestamp,
        al.status,
        al.plate_number,
        v.vehicle_type,
        v.color
    FROM access_logs al
    JOIN vehicles v ON al.plate_number = v.plate_number
    WHERE v.id = ? AND al.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ORDER BY al.timestamp DESC
");
$stmt->execute([$vehicleId, $days]);
echo json_encode($stmt->fetchAll());
```

**Chart Implementation:**
```javascript
async function loadVehicleActivityChart(vehicleId) {
    const response = await fetch(`api/get_vehicle_activity.php?vehicle_id=${vehicleId}&days=30`);
    const data = await response.json();
    
    // Group by date
    const grouped = {};
    data.forEach(log => {
        const date = log.timestamp.split(' ')[0];
        if (!grouped[date]) grouped[date] = {in: 0, out: 0};
        grouped[date][log.status.toLowerCase()]++;
    });
    
    const dates = Object.keys(grouped).sort();
    
    new Chart(document.getElementById('vehicleActivityChart'), {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Entries',
                    data: dates.map(d => grouped[d].in),
                    borderColor: '#10b981',
                    fill: false
                },
                {
                    label: 'Exits',
                    data: dates.map(d => grouped[d].out),
                    borderColor: '#ef4444',
                    fill: false
                }
            ]
        }
    });
}
```

---

## ⚡ Real-Time System Behavior

### Requirement 15: Real-Time Updates without Page Refresh

**File to CREATE:** `assets/js/realtime-updates.js`

```javascript
class RealtimeUpdater {
    constructor(endpoint, callback, interval = 5000) {
        this.endpoint = endpoint;
        this.callback = callback;
        this.interval = interval;
        this.lastUpdate = null;
        this.timer = null;
    }
    
    start() {
        this.poll();
        this.timer = setInterval(() => this.poll(), this.interval);
    }
    
    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }
    
    async poll() {
        try {
            const url = this.lastUpdate 
                ? `${this.endpoint}?since=${this.lastUpdate}`
                : this.endpoint;
                
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.updates && data.updates.length > 0) {
                this.lastUpdate = data.timestamp;
                this.callback(data.updates);
            }
        } catch (error) {
            console.error('Realtime update error:', error);
        }
    }
}

// Usage Example: Live Visitor Pass Updates
const visitorPassUpdater = new RealtimeUpdater(
    'api/get_visitor_pass_updates.php',
    (updates) => {
        updates.forEach(pass => {
            updateVisitorPassCard(pass);
        });
    },
    3000 // Poll every 3 seconds
);

visitorPassUpdater.start();
```

**API Endpoint:** `admin/api/get_visitor_pass_updates.php`

```php
<?php
 require_once __DIR__ . '/../../includes/session_admin_unified.php';
require_once __DIR__ . '/../../db.php';

$since = $_GET['since'] ?? null;

$query = "SELECT * FROM visitor_passes WHERE 1=1";
$params = [];

if ($since) {
    $query .= " AND updated_at > ?";
    $params[] = $since;
}

$query .= " ORDER BY updated_at DESC LIMIT 20";

$stmt = $pdo->prepare($query);
$stmt->execute($params);

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'updates' => $stmt->fetchAll()
]);
```

---

## Implementation Checklist

### Phase 1: Database & Backend (Week 1)
- [ ] Run comprehensive migration
- [ ] Test all new tables created successfully
- [ ] Update all API endpoints for new fields
- [ ] Implement email verification flow
- [ ] Create account approval endpoints

### Phase 2: Authentication & Registration (Week 1-2)
- [ ] Update login.php for auto-role detection
- [ ] Deploy new register.php
- [ ] Test email/username login
- [ ] Implement account status checking
- [ ] Build admin approval interface

### Phase 3: Visitor Pass Enhancements (Week 2)
- [ ] Update QR generation with logo overlay
- [ ] Add subdivision support
- [ ] Test active pass filtering in guard panel
- [ ] Verify pass customization works

### Phase 4: UI/UX Improvements (Week 2-3)
- [ ] Deploy contact number auto-formatting
- [ ] Update all forms with new name structure
- [ ] Implement DataTables for all records
- [ ] Standardize button colors
- [ ] Hide record IDs from UI

### Phase 5: Multi-Vehicle & Charts (Week 3)
- [ ] Deploy vehicles table
- [ ] Build vehicle management UI
- [ ] Implement Chart.js visualizations
- [ ] Add vehicle activity tracking
- [ ] Test multi-vehicle scenarios

### Phase 6: Real-Time Features (Week 4)
- [ ] Deploy real-time update system
- [ ] Test polling intervals
- [ ] Optimize for performance
- [ ] Add visual update indicators

### Phase 7: Security & Testing (Week 4)
- [ ] Remove guard delete permissions
- [ ] Test all role-based access controls
- [ ] Audit all endpoints for security
- [ ] Load testing
- [ ] User acceptance testing

---

## 🎯 Quick Start Guide

1. **Backup your database first!**
   ```bash
   mysqldump -u root vehiscan_vdp > backup_before_migration.sql
   ```

2. **Run migrations:**
   ```bash
   php migrations/comprehensive_system_migration.php
   ```

3. **Deploy new files:**
   - Upload all new files from this implementation
   - Update existing files per instructions

4. **Test critical paths:**
   - Register new account → Verify pending status
   - Admin approves account → User can login
   - Create visitor pass → QR has logo
   - Guard views only active passes

5. **Monitor logs:**
   - Check error_log for any issues
   - Verify audit trails working
   - Test real-time updates

---

## Important Notes

- **Data Safety:** All migrations preserve existing data
- **Audit Trails:** Never delete audit_logs or access_logs
- **Primary Keys:** Database IDs remain intact (only hidden in UI)
- **Real-Time:** Start with 5-10 second polling intervals, optimize later
- **Testing:** Test each phase on staging before production

---

## 📞 Support & Questions

For implementation questions:
1. Check error logs first
2. Review this documentation
3. Test in isolated environment
4. Document any issues encountered
