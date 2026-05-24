# Admin API Endpoints

This directory contains admin-only API endpoints for visitor pass management.

---

## Files

### create_visitor_pass.php
**Purpose:** Create a new visitor pass 
**Method:** POST 
**Access:** Admin only (AJAX + CSRF protected) 
**Called by:** `admin/fetch/fetch_visitors.php`

**Parameters:**
```
visitor_name: string (required)
vehicle_plate: string (required)
purpose: string (required)
valid_from: datetime (required)
valid_until: datetime (required)
csrf: string (required)
```

**Response:**
```json
{
  "success": true,
  "message": "Visitor pass created successfully",
  "pass_id": 123
}
```

**Validation:**
- CSRF token verification
- Required fields check
- Date validation (valid_until > valid_from)
- Duplicate plate number check

**Database:**
- Inserts into `visitor_passes` table
- Sets status='active'
- Records created_at timestamp

---

### cancel_visitor_pass.php
**Purpose:** Cancel an existing visitor pass 
**Method:** POST 
**Access:** Admin only (AJAX + CSRF protected) 
**Called by:** `admin/fetch/fetch_visitors.php`

**Parameters:**
```
id: integer (required) - Pass ID
csrf: string (required)
```

**Response:**
```json
{
  "success": true,
  "message": "Visitor pass cancelled successfully"
}
```

**Actions:**
- Updates status to 'cancelled'
- Records cancellation timestamp
- Maintains audit trail

---

## 🔐 Security Features

### CSRF Protection
All endpoints validate CSRF tokens:
```php
$csrf = $_SESSION['csrf_token'] ?? '';
$posted = $_POST['csrf'] ?? '';
if (!hash_equals($csrf, (string)$posted)) {
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}
```

### Session Validation
```php
require_once __DIR__ . '/../../includes/session_admin.php';
if ($_SESSION['role'] !== 'admin') {
    exit(json_encode(['success' => false]));
}
```

### Input Sanitization
- All inputs trimmed and validated
- SQL injection protection via prepared statements
- XSS protection on output

---

## Integration

### Frontend Integration
Visitor pass modal in `admin/fetch/fetch_visitors.php` contains inline JavaScript that calls these endpoints:

**Create Flow:**
```javascript
const res = await fetch('../api/create_visitor_pass.php', {
    method: 'POST',
    body: formData
});
```

**Cancel Flow:**
```javascript
const res = await fetch('../api/cancel_visitor_pass.php', {
    method: 'POST',
    body: formData 
});
```

### Database Schema
```sql
visitor_passes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visitor_name VARCHAR(100),
    vehicle_plate VARCHAR(20),
    purpose TEXT,
    valid_from DATETIME,
    valid_until DATETIME,
    status ENUM('active', 'expired', 'cancelled'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    cancelled_at DATETIME NULL
)
```

---

## Status Values

- **active** - Pass is currently valid
- **expired** - Valid period has passed (auto-updated)
- **cancelled** - Manually cancelled by admin

---

## Testing

**Test File:**
- `_testing/test_features.html`

**Manual Testing:**
1. Login to admin panel
2. Navigate to Visitor Passes tab
3. Click "Create New Pass"
4. Fill form and submit
5. Verify pass appears in list
6. Test cancel functionality

---

## Troubleshooting

**"Invalid CSRF token" error:**
- Session may have expired
- Refresh page and try again
- Check browser console for token value

**"Unauthorized" error:**
- Not logged in as admin
- Session expired
- Try logging out and back in

**Pass not appearing:**
- Check database for successful insert
- Verify valid_from/valid_until dates
- Check status is 'active'

---

## Future Enhancements

Consider adding:
- [ ] QR code generation for passes
- [ ] Email notification on pass creation
- [ ] Pass expiration reminders
- [ ] Visitor check-in tracking
- [ ] Pass history/audit log

---

**Last Updated:** November 12, 2025
