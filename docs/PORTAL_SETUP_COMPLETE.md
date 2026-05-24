# User & Visitor Portal - Quick Start Guide

## Setup Complete!

### What Was Done:

1. ** Database Migration Executed**
   - Created `homeowner_auth` table
   - Created `visitor_auth_tokens` table
   - Enhanced `visitor_passes` table with approval workflow

2. ** Homeowner Accounts Created**
   - 11 homeowner accounts generated
   - Default password: `homeowner123`
   - Usernames auto-generated from names

3. ** All Portal Pages Ready**
   - Homeowner Portal
   - Visitor Portal
   - Admin Approval Dashboard

---

## 🔐 Test Accounts

### Sample Homeowner Logins:
| Username | Password | Name | Address |
|----------|----------|------|---------|
| `kyle_jansen` | `homeowner123` | KYLE JANSEN | B13 L42 Majestic St. |
| `dan_bringer` | `homeowner123` | dan bringer | basd njasd |
| `test` | `homeowner123` | test | test |
| `keyboard_mouse` | `homeowner123` | Keyboard Mouse | test |

---

## Access URLs

### For Homeowners:
```
http://localhost/Vehiscan-RFID/auth/login.php
```

### For Visitors (via QR code):
```
http://localhost/Vehiscan-RFID/visitor/view_pass.php?token={QR_TOKEN}
```

### For Admins (Approval Dashboard):
```
Admin Panel → Visitors Section
```

---

## Testing Workflow

### Step 1: Homeowner Creates Visitor Pass
1. Navigate to: `http://localhost/Vehiscan-RFID/auth/login.php`
2. Login with: `kyle_jansen` / `homeowner123`
3. Click **"+ Add Visitor Pass"**
4. Fill in:
   - Visitor Name: `John Doe`
   - Purpose: `Business meeting`
   - Plate Number: `ABC-1234` (optional)
   - Valid From: `[Today's date + time]`
   - Valid Until: `[Tomorrow's date + time]`
5. Click **Submit**
6. Status should show: **PENDING**

### Step 2: Admin Approves Pass
1. Login to Admin Panel
2. Navigate to **Visitor Passes** section
3. You'll see the pending request from KYLE JANSEN
4. Click ** Approve** button
5. Pass status changes to **APPROVED**

### Step 3: Visitor Views Pass
1. Get the QR token from database:
   ```sql
   SELECT qr_token FROM visitor_passes ORDER BY id DESC LIMIT 1;
   ```
2. Visit: `http://localhost/Vehiscan-RFID/visitor/view_pass.php?token={TOKEN}`
3. Visitor sees:
   - Pass status (ACTIVE/EXPIRED)
   - 📍 Homeowner's address
   - 📞 Contact information
   - Validity period

---

## Admin Features

### Approve Visitor Pass:
- Click **Approve** → Pass becomes active
- Visitor can now access via QR code

### Reject Visitor Pass:
- Click **Reject**
- Must provide rejection reason
- Homeowner sees reason on portal

---

## 🎯 Homeowner Features

### Profile View:
- Personal information
- Address
- Contact number
- Vehicle details (type, color, plate)

### Visitor Pass Management:
- Create new passes
- View all passes
- Track status (pending/approved/rejected/expired)
- See rejection reasons

---

## 👥 Visitor Portal Features

### What Visitors Can See:
- **Pass Status**: Active or Expired badge
- 📍 **Destination Address**: Full address only
- **Homeowner Name**: Who they're visiting
- 📞 **Contact**: Homeowner's phone (if available)
- **Validity Period**: Start and end date/time

### What Visitors CANNOT See:
- Homeowner's full profile
- Other visitors' information
- System internals

---

## Security Features

- Password hashing (bcrypt)
- CSRF token protection
- Session security
- SQL injection prevention
- Date validation (no past dates)
- Token-based visitor access
- Status-based access control

---

## Database Tables

### `homeowner_auth`
- Stores login credentials for homeowners
- Links to `homeowners` table

### `visitor_passes`
- Enhanced with approval workflow
- Status: pending → approved/rejected
- Tracks who approved and when

### `visitor_auth_tokens`
- Temporary tokens for visitor access
- Auto-expires based on pass validity

---

## Important Notes

1. **Default Password**: All accounts use `homeowner123`
   - Users should change passwords after first login
   - Password change feature to be implemented

2. **QR Code Generation**: Not yet implemented
   - Currently using token URL
   - Future: Auto-generate QR codes

3. **Notifications**: Not yet implemented
   - No email/SMS notifications yet
   - Future: Notify on approval/rejection

---

## Troubleshooting

### Can't login to homeowner portal?
```sql
-- Check if account exists
SELECT * FROM homeowner_auth WHERE username = 'kyle_jansen';

-- Reset password
UPDATE homeowner_auth 
SET password_hash = '$2y$10$YourHashHere' 
WHERE username = 'kyle_jansen';
```

### Visitor pass not showing?
```sql
-- Check pass status
SELECT * FROM visitor_passes ORDER BY created_at DESC LIMIT 5;
```

### Admin approval not working?
- Check admin session is active
- Verify `approved_by` column exists in `visitor_passes`

---

## 📞 Support

For issues or questions, check:
1. Browser console for JavaScript errors
2. PHP error logs in XAMPP
3. Database connection in `db.php`

---

## Success Checklist

- [x] Migration executed
- [x] 11 homeowner accounts created
- [x] Login page accessible
- [x] Portal page accessible
- [x] Visitor page accessible
- [x] Admin approval interface ready
- [ ] Test creating visitor pass
- [ ] Test admin approval
- [ ] Test visitor viewing pass
- [ ] Test rejection workflow

---

**Next: Start testing with the workflow above!** 
