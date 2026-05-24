# System-Wide Improvements Implementation Guide

## 🎯 Overview
This document outlines the comprehensive system improvements for VehiScan, addressing login enhancements, QR customization, contact standardization, charts/visualization, security controls, and more.

---

## **COMPLETED: Admin Panel Charts Fix**

### Problem Diagnosed:
1. **Wrong API path** - `fetch('../admin/api/get_weekly_stats.php')` should be `fetch('api/get_weekly_stats.php')`
2. **Wrong database column** - Used `created_at` but table might have `log_time`
3. **No error handling** - Charts failed silently

### Fixed Files:
- `admin/fetch/fetch_dashboard.php` - Corrected path, added error handling
- `admin/api/get_weekly_stats.php` - Auto-detects correct column, added debug logging

### How to Test:
1. Go to Admin Panel → Dashboard
2. Open browser console (F12)
3. Look for `[Dashboard]` logs showing chart initialization
4. Charts should now display properly

---

## **Database Migrations Created**

All migrations are in `/migrations/` folder:

### 001: Email & Account Status
- **What**: Adds email login support and admin approval workflow
- **Tables**: `homeowners`, `guards`, `admins`
- **New Columns**:
  - `email` VARCHAR(255) UNIQUE
  - `account_status` ENUM('pending', 'approved', 'rejected')
  - `approved_by` INT (Foreign Key → admins.id)
  - `approved_at` DATETIME

### 002: Name Restructuring
- **What**: Splits `name` into structured fields
- **New Columns**:
  - `first_name` VARCHAR(100)
  - `middle_name` VARCHAR(100)
  - `last_name` VARCHAR(100)
  - `suffix` VARCHAR(20)
- **Migration**: Auto-splits existing names (First = first word, Last = last word)

### 003: Contact Formatting
- **What**: Standardizes contact number format (0912-345-6789)
- **Modification**: VARCHAR(15) with format comment
- **Tables**: `homeowners`, `guards`, `visitor_passes`

### 004: Subdivision Logo Customization
- **What**: Enables custom logos per subdivision for visitor passes
- **New Columns**:
  - `homeowners.subdivision_logo` VARCHAR(255) DEFAULT 'ville_de_palme.png'
  - `visitor_passes.logo_file` VARCHAR(255)
  - `visitor_passes.subdivision_name` VARCHAR(255)

### 005: Multiple Vehicles Per Homeowner
- **What**: Creates `vehicles` table for multiple vehicle registration
- **New Table**: `vehicles`
  - `vehicle_id` INT AUTO_INCREMENT PRIMARY KEY
  - `homeowner_id` INT (Foreign Key)
  - `plate_number` VARCHAR(50) UNIQUE
  - `vehicle_type`, `color`, `make`, `model`, `year`
  - `is_primary` BOOLEAN
  - `status` ENUM('active', 'inactive')
- **Migration**: Migrates existing vehicle data from `homeowners` table

---

## **How to Run Migrations**

### Option 1: Web Interface
```
1. Navigate to: http://localhost/Vehiscan-RFID/migrations/run_migrations.php
2. Click "Run All Migrations"
3. Review results
```

### Option 2: Manual (phpMyAdmin)
```
1. Open phpMyAdmin
2. Select vehiscan_vdp database
3. Go to SQL tab
4. Copy/paste each migration file content
5. Execute
```

### IMPORTANT:
- **Backup database first!**
- Run migrations in order (001 → 005)
- Check for errors after each migration

---

## **What Needs to Be Implemented Next**

### 1. Login & Registration Enhancements

#### Files to Modify:
- **`auth/login.php`** - Add email login support
- **`auth/register.php`** - Add email field, set status to 'pending'
- **`includes/session_*.php`** - Check account_status before allowing login

#### Implementation Steps:
```php
// In login.php - Support email OR username
$stmt = $pdo->prepare("
    SELECT * FROM {$table} 
    WHERE (username = :identifier OR email = :identifier) 
    AND account_status = 'approved'
");

// In register.php - Add email validation
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$account_status = 'pending'; // New accounts wait for approval

// Auto-redirect based on role
if ($_SESSION['role'] === 'admin') {
    header('Location: /admin/admin_panel.php');
} elseif ($_SESSION['role'] === 'guard') {
    header('Location: /guard/pages/guard_side.php');
} // etc.
```

#### New Feature: Pending Accounts Admin Panel
- Create `admin/pending_accounts.php`
- Show list of pending users
- Approve/Reject buttons
- Email notification on approval

---

### 2. QR Code Customization with Subdivision Logo

#### Files to Modify:
- **`admin/api/qr_helper.php`** - Add logo overlay to QR code
- **`admin/homeowners/homeowner_edit.php`** - Add logo upload field
- **`visitor/view_pass.php`** - Display custom logo on visitor pass

#### Implementation:
```php
// In qr_helper.php
function generateVisitorPassQR($passId, $token, $pdo, $logoFile = 'ville_de_palme.png') {
    // Generate QR code
    QRcode::png($verifyUrl, $tempFile, QR_ECLEVEL_M, 6, 2);
    
    // Load logo
    $logoPath = __DIR__ . '/../../assets/images/' . $logoFile;
    if (file_exists($logoPath)) {
        // Overlay logo on center of QR code using GD library
        $qr = imagecreatefrompng($tempFile);
        $logo = imagecreatefrompng($logoPath);
        
        // Resize logo to fit QR center
        $qrWidth = imagesx($qr);
        $logoWidth = $qrWidth / 4; // 25% of QR size
        
        $logoResized = imagescale($logo, $logoWidth, $logoWidth);
        
        // Center logo
        $x = ($qrWidth - $logoWidth) / 2;
        $y = ($qrWidth - $logoWidth) / 2;
        
        imagecopy($qr, $logoResized, $x, $y, 0, 0, $logoWidth, $logoWidth);
        imagepng($qr, $tempFile);
        
        imagedestroy($qr);
        imagedestroy($logo);
        imagedestroy($logoResized);
    }
    
    // Return base64...
}
```

---

### 3. Contact Number Auto-Formatting

#### Files to Modify:
- **`assets/js/contact-formatter.js`** (NEW)
- All registration forms

#### Implementation:
```javascript
// contact-formatter.js
function formatContactNumber(input) {
    // Remove all non-digits
    let value = input.value.replace(/\D/g, '');
    
    // Format as 0912-345-6789
    if (value.length >= 4) {
        value = value.slice(0, 4) + '-' + value.slice(4);
    }
    if (value.length >= 8) {
        value = value.slice(0, 8) + '-' + value.slice(8, 12);
    }
    
    input.value = value;
}

// Auto-attach to contact inputs
document.querySelectorAll('input[name="contact"]').forEach(input => {
    input.addEventListener('input', () => formatContactNumber(input));
    input.setAttribute('maxlength', '13'); // 0912-345-6789
    input.setAttribute('placeholder', '0912-345-6789');
});
```

---

### 4. Dashboard Charts & Visualization

#### New Charts to Implement:

**A. Homeowner Distribution Pie Chart**
```javascript
// admin/fetch/fetch_dashboard.php
const homeownersByBlock = await fetch('api/get_homeowner_distribution.php').then(r => r.json());

new Chart(ctx, {
    type: 'pie',
    data: {
        labels: homeownersByBlock.labels, // ['Block A', 'Block B', ...]
        datasets: [{
            data: homeownersByBlock.values, // [15, 23, 18, ...]
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
        }]
    }
});
```

**B. Visitor In/Out Line Graph**
```javascript
// Separate lines for IN and OUT
new Chart(ctx, {
    type: 'line',
    data: {
        labels: last7Days,
        datasets: [
            {
                label: 'Check In',
                data: inCounts,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)'
            },
            {
                label: 'Check Out',
                data: outCounts,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)'
            }
        ]
    }
});
```

#### Files to Create:
- `admin/api/get_homeowner_distribution.php`
- `admin/api/get_visitor_in_out_stats.php`

---

### 5. Guard Access Controls

#### Files to Modify:
- **`guard/js/guard_side.js`** - Hide delete buttons
- **`guard/fetch_visitor_passes.php`** - Filter to active passes only

#### Implementation:
```php
// guard/fetch_visitor_passes.php
$stmt = $pdo->prepare("
    SELECT * FROM visitor_passes 
    WHERE status = 'active' 
    AND expiration_date >= CURDATE()
    ORDER BY created_at DESC
");
```

```javascript
// guard/js/guard_side.js
// Remove delete log functionality
const deleteButtons = document.querySelectorAll('.delete-log-btn');
deleteButtons.forEach(btn => btn.remove());

// Keep audit trail intact - UI restriction only
```

---

### 6. Records Management (DataTables)

#### Files to Modify:
- **`admin/employee_list.php`** - Add DataTables
- **`admin/homeowners/homeowner_list.php`** - Enhance table
- All admin record lists

#### Implementation:
```javascript
$('#recordsTable').DataTable({
    ajax: 'api/get_records.php',
    columns: [
        { data: null, render: (data, type, row, meta) => meta.row + 1 }, // Row number (not ID)
        { data: 'name' },
        { data: 'contact' },
        { data: 'status' },
        {
            data: null,
            render: (data) => `
                <button class="btn-edit" data-id="${data.id}">Edit</button>
                <button class="btn-delete" data-id="${data.id}">Delete</button>
            `
        }
    ],
    order: [[1, 'asc']], // Sort by name
    pageLength: 25,
    responsive: true
});
```

---

### 7. Multiple Vehicles Per Homeowner

#### Files to Create:
- **`homeowners/my_vehicles.php`** - Vehicle management page
- **`homeowners/api/add_vehicle.php`** - Add vehicle endpoint
- **`homeowners/api/delete_vehicle.php`** - Remove vehicle

#### UI Design:
```html
<!-- homeowners/my_vehicles.php -->
<div class="vehicles-list">
    <div class="vehicle-card" data-vehicle-id="1">
        <div class="plate-badge primary">ABC-1234</div>
        <p>Type: Sedan | Color: White</p>
        <button class="set-primary">Set as Primary</button>
        <button class="delete-vehicle">Remove</button>
    </div>
    
    <button class="add-vehicle-btn">+ Add Vehicle</button>
</div>
```

---

## 🎨 **UI/UX Improvements**

### Standardize Button Colors:
```css
/* assets/css/system.css */
.btn-primary { background: #3b82f6; } /* Blue */
.btn-success { background: #10b981; } /* Green */
.btn-danger { background: #ef4444; } /* Red */
.btn-warning { background: #f59e0b; } /* Orange */
.btn-secondary { background: #6b7280; } /* Gray */
```

### Hide Record IDs:
- Use row numbers instead: `<?php echo $index + 1; ?>`
- Keep database IDs in `data-id` attributes
- Never display primary keys in UI

---

## 🔐 **Security Considerations**

1. **Email Verification**:
   - Add email verification token system
   - Send verification link on registration
   - Only allow login after email verified

2. **Account Approval Audit Trail**:
   - Log who approved/rejected accounts
   - Store approval reason
   - Timestamp all status changes

3. **Guard Permissions**:
   - Enforce at database level (views)
   - UI restrictions are not enough
   - Audit all guard actions

4. **Input Validation**:
   - Validate email format
   - Sanitize phone numbers
   - Prevent SQL injection in all queries

---

## **Implementation Checklist**

- [x] Fix admin panel charts (DONE)
- [x] Create database migrations (DONE)
- [ ] Run migrations on database
- [ ] Implement email login
- [ ] Add account approval workflow
- [ ] Implement QR logo customization
- [ ] Add contact number formatting
- [ ] Create homeowner distribution chart
- [ ] Create visitor in/out chart
- [ ] Add guard access restrictions
- [ ] Implement DataTables for all records
- [ ] Hide record IDs from UI
- [ ] Add multiple vehicles support
- [ ] Create vehicle management UI
- [ ] Add real-time updates for editable data
- [ ] Standardize button colors
- [ ] Test all features end-to-end

---

## **Testing Plan**

1. **Chart Testing**:
   - Load admin dashboard
   - Check both charts render
   - Verify data accuracy
   - Test with no data (empty charts)

2. **Login Testing**:
   - Test email login
   - Test username login
   - Test pending account rejection
   - Test auto-redirect by role

3. **QR Testing**:
   - Generate pass with custom logo
   - Scan QR code
   - Verify logo displays correctly
   - Test with different logo sizes

4. **Contact Formatting**:
   - Enter raw numbers
   - Verify auto-formatting
   - Test paste with spaces/dashes
   - Validate on submit

5. **Multi-Vehicle Testing**:
   - Add 3+ vehicles to one homeowner
   - Set primary vehicle
   - Delete non-primary vehicle
   - Verify access logs use correct vehicle

---

## 📞 **Support & Maintenance**

### If Charts Still Don't Work:
1. Check browser console for errors
2. Verify Chart.js loaded: `console.log(typeof Chart)`
3. Check API response: Network tab in DevTools
4. Verify database has data: `SELECT COUNT(*) FROM recent_logs`

### Common Migration Errors:
- **Duplicate column**: Column already exists (safe to ignore)
- **Foreign key fails**: Parent table missing data
- **Syntax error**: Check MySQL version compatibility

---

## 🎯 **Next Steps**

1. **Run migrations** on development database
2. **Test charts** in admin panel
3. **Implement login enhancements** (highest priority)
4. **Add QR logo customization** (user-requested feature)
5. **Deploy to staging** for testing
6. **Get user feedback** on new features
7. **Deploy to production** after approval

---

*Last Updated: December 14, 2025*
