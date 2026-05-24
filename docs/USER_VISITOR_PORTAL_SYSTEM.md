# User & Visitor Portal System

## Overview
This system provides separate portals for homeowners (users) and visitors with distinct functionalities.

## Features Implemented

### Homeowner Portal (`/homeowners/portal.php`)
**Access**: Requires login credentials

**Features:**
1. **Profile View**
   - View personal information (name, address, contact, vehicle details)
   - See plate number and vehicle info

2. **Visitor Pass Management**
   - Create visitor pass requests
   - View all visitor passes (pending, approved, rejected, expired)
   - Track pass status in real-time
   - See rejection reasons if applicable

3. **Pass Creation Form**
   - Visitor name (required)
   - Purpose of visit (required)
   - Plate number (optional)
   - Valid from date/time (required)
   - Valid until date/time (required)

### Visitor Portal (`/visitor/view_pass.php?token=xxx`)
**Access**: Via QR code token (no login required)

**Features:**
1. **Pass Information Display**
   - Visitor name and purpose
   - Validity period with status (ACTIVE/EXPIRED)
   - Optional plate number

2. **Homeowner Address**
   - View destination address
   - Homeowner name and contact (if available)
   - Clear visual status indicators

### Admin Portal Integration
**Location**: Admin panel → Visitor Passes section

**Features:**
1. **Pending Requests View**
   - See all pending visitor pass requests
   - View homeowner and visitor details
   - Check validity dates and purpose

2. **Approval System**
   - **Approve Button**: Grants access to visitor
   - **Reject Button**: Denies with mandatory reason
   - Real-time status updates

## Database Structure

### New Tables Created:
```sql
-- Homeowner authentication
homeowner_auth (id, homeowner_id, username, password_hash, email, is_active)

-- Visitor access tokens 
visitor_auth_tokens (id, visitor_pass_id, token, expires_at)
```

### Enhanced Tables:
```sql
-- visitor_passes enhancements
status ENUM('pending', 'approved', 'rejected', 'expired', 'used')
approved_by INT (admin who approved/rejected)
approved_at TIMESTAMP
rejection_reason TEXT
notes TEXT
```

## Setup Instructions

### 1. Run Database Migration
```bash
# Execute the migration file
mysql -u root vehiscan_vdp < migrations/003_create_user_visitor_portals.sql
```

### 2. Create Homeowner Accounts
```sql
-- Example: Create login for existing homeowner
INSERT INTO homeowner_auth (homeowner_id, username, password_hash, email) 
VALUES (1, 'john_doe', '$2y$10$...', 'john@example.com');
```

### 3. Access Points
- **Homeowner Login**: `/auth/login.php`
- **Homeowner Portal**: `/homeowners/portal.php`
- **Visitor View**: `/visitor/view_pass.php?token={QR_TOKEN}`
- **Admin Approval**: Admin Panel → Visitor Passes

## Workflow

### Creating a Visitor Pass:
1. Homeowner logs in to portal
2. Clicks "Add Visitor Pass"
3. Fills form with visitor details
4. Submits → Status: **PENDING**
5. Admin receives notification in admin panel
6. Admin approves/rejects with reason
7. Homeowner sees updated status
8. If approved, visitor can access via QR code

### Visitor Access:
1. Homeowner shares QR code (contains token)
2. Visitor scans QR → opens `/visitor/view_pass.php?token=xxx`
3. Visitor sees:
   - Their pass details and validity
   - Homeowner's address
   - Contact information
4. Guard can verify pass status visually

## Future Additions (To Be Implemented)

### Homeowner Portal:
- [ ] Edit profile information
- [ ] Change password functionality
- [ ] Visitor history and analytics
- [ ] Recurring visitor templates
- [ ] Notification preferences
- [ ] Multiple vehicle management
- [ ] Emergency contact management

### Visitor Portal:
- [ ] Visitor check-in confirmation
- [ ] Directions/map integration
- [ ] Chat with homeowner
- [ ] Pass extension requests
- [ ] Digital signature on arrival
- [ ] Photo capture on entry

### Admin Features:
- [ ] Bulk approve/reject
- [ ] Pass statistics dashboard
- [ ] Automated approval rules
- [ ] Visitor blacklist management
- [ ] Export pass reports
- [ ] Email/SMS notifications
- [ ] Visitor photo verification
- [ ] Integration with guard QR scanner

### Guard Integration:
- [ ] QR scanner in guard panel
- [ ] Real-time pass validation
- [ ] Manual check-in/check-out
- [ ] Visitor photo capture
- [ ] License plate verification
- [ ] Pass override capability

## Security Features
- CSRF token protection
- SQL injection prevention (prepared statements)
- Password hashing (bcrypt)
- Session security
- Date validation (past dates rejected)
- Token-based visitor access
- Status-based access control

## API Endpoints

### Homeowner APIs:
- `POST /homeowners/api/create_visitor_pass.php` - Create new pass

### Admin APIs:
- `GET /admin/api/get_pending_passes.php` - List pending requests
- `POST /admin/api/approve_visitor_pass.php` - Approve pass
- `POST /admin/api/reject_visitor_pass.php` - Reject with reason

## Notes
- Visitor passes expire automatically based on `valid_until` timestamp
- QR tokens are unique 32-character hex strings
- Admin approval required before visitor can access
- Rejected passes show reason to homeowner
- Active/expired status calculated in real-time
