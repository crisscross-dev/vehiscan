# VEHISCAN RFID SYSTEM - USER NAVIGATION GUIDE
**Version:** 1.0 
**Last Updated:** December 4, 2025 
**System Status:** Production Ready

---

## TABLE OF CONTENTS
1. [System Overview](#system-overview)
2. [Access Credentials](#access-credentials)
3. [Homeowner Portal](#homeowner-portal)
4. [Guard Panel](#guard-panel)
5. [Admin Panel](#admin-panel)
6. [Common Tasks](#common-tasks)
7. [Security Features](#security-features)
8. [Troubleshooting](#troubleshooting)

---

## SYSTEM OVERVIEW

VehiScan RFID is a comprehensive vehicle and visitor management system designed for residential communities. The system provides three main portals for different user roles:

- **🏠 Homeowner Portal** - For residents to manage their information and request visitor passes
- ** Guard Panel** - For security personnel to scan RFID/QR codes and monitor access
- ** Admin Panel** - For administrators to manage the entire system

**System URL:** `http://localhost/Vehiscan-RFID/`

---

## ACCESS CREDENTIALS

### Super Administrator
- **Username:** `Administrator`
- **Password:** Set during first-time setup
- **Access:** Full system control, all admin features

### Administrators
- **Role:** `admin`
- **Access:** User management, reports, system configuration
- **Created by:** Super Admin through Employee Management

### Guards
- **Role:** `guard`
- **Access:** Guard panel only - RFID scanning, access logs
- **Created by:** Admin through Employee Management

### Homeowners
- **Access:** Homeowner portal - profile management, visitor passes
- **Created by:** Admin through Manage Records or self-registration

---

## HOMEOWNER PORTAL

**Access URL:** `/homeowners/portal.php`

### Features Available:

#### 1. **Dashboard**
- View your registered information
- See QR codes for quick access
- Check account status

#### 2. **Profile Management**
- Update contact information
- Change password
- View registered images (owner photo & vehicle photo)

#### 3. **Visitor Pass Requests**
- Request visitor passes for guests
- Set visit date and time
- Add visitor details (name, contact)
- Set duration (3 hours, 1 day, 3 days, 7 days, custom)
- Track pass status (Pending, Approved, Rejected)

#### 4. **QR Code Access**
- Download your personal QR code
- Show QR code to guards for quick verification
- QR codes are unique per homeowner

### How to Use:

**Creating a Visitor Pass:**
1. Click "Create Visitor Pass" button
2. Fill in visitor information:
   - Visitor name (required)
   - Contact number
   - Purpose of visit
   - License plate (optional)
3. Select visit duration using quick buttons or custom dates
4. Click "Submit Request"
5. Wait for admin approval
6. Once approved, share the pass details with your visitor

**Viewing Your QR Code:**
1. Scroll to "Registered Images" section
2. Your QR code is displayed
3. Click to view full-size
4. Guards can scan this at the gate

---

## GUARD PANEL

**Access URL:** `/guard/pages/guard_side.php`

### Features Available:

#### 1. **RFID/QR Code Scanner**
- Scan RFID cards
- Scan QR codes via camera
- Instant access verification
- Displays homeowner information on successful scan

#### 2. **Access Logs**
- Real-time log of all entries/exits
- Search by name, plate number, date
- Filter by status (Allowed/Denied)
- Pagination for easy navigation

#### 3. **Visitor Pass Verification**
- Check visitor pass validity
- See pass details and duration
- Verify visitor identity

#### 4. **Export Functions**
- Export logs to CSV
- Download for reporting
- Delete logs with backup (CSV auto-downloads before deletion)

### How to Use:

**Scanning a Vehicle/Visitor:**
1. Click "Scan RFID/QR Code" button
2. Options:
   - **RFID Scanner:** Enter RFID tag number
   - **QR Code:** Click camera icon, allow camera access, show QR code to camera
3. System automatically verifies access
4. Green = Allowed, Red = Denied
5. Log is automatically recorded

**Viewing Access Logs:**
1. Logs display automatically on main page
2. Use search bar to find specific entries
3. Filter by date range
4. Click page numbers to navigate through logs

**Exporting Logs:**
1. Click "Export to CSV" to download current logs
2. Click "Delete Logs" to export and clear (requires confirmation)
3. CSV file automatically downloads before deletion

**Session Management:**
- Automatic timeout after 30 minutes of inactivity
- Warning appears 5 minutes before timeout
- Click "Stay Logged In" to extend session

---

## ADMIN PANEL

**Access URL:** `/admin/admin_panel.php`

### Navigation Menu:

#### **Dashboard**
- System overview statistics
- Recent activity
- Quick stats: Total homeowners, logs today, visitor passes
- Charts and analytics

#### 👥 **Manage Records**
- View all homeowners
- Add new homeowner
- Edit homeowner information
- Delete homeowners
- Search and filter functionality
- Pagination for large datasets

#### **Access Logs**
- View complete access history
- Filter by date, status, homeowner
- Search by name or plate
- Delete individual log entries
- Export to CSV

#### **Visitor Passes**
- View all visitor pass requests
- Approve pending requests
- Reject requests with reason
- Cancel approved passes
- See pass details and status

#### 🧑‍💼 **Employee Management**
- Create new employees (Admin/Guard roles)
- Edit employee information
- Change employee roles
- Reset employee passwords
- Delete employees

#### **RFID Simulator**
- Test RFID scanning without hardware
- Simulate vehicle entries
- Generate demo logs for testing
- View recent simulations

#### **Audit Logs**
- Track all administrative actions
- See who did what and when
- Monitor system changes
- Export audit trail

### How to Use:

**Creating a New Homeowner:**
1. Go to **Manage Records**
2. Click "Add New Homeowner"
3. Fill in all required information:
   - Name, contact, address
   - RFID tag number (unique)
   - Plate number (unique)
   - Upload owner photo (optional)
   - Upload vehicle photo (optional)
4. Set initial password
5. Click "Create Homeowner"
6. Homeowner can now log in with username and password

**Approving Visitor Passes:**
1. Go to **Visitor Passes**
2. Find pending requests (yellow badge)
3. Click "View Details" on the pass
4. Review visitor information
5. Click "Approve" to grant access
6. Or click "Reject" and provide reason
7. Homeowner is notified of decision

**Managing Employees:**
1. Go to **Employee Management**
2. Click "Create New Employee"
3. Set username and password
4. Select role:
   - **Admin:** Full access to admin panel
   - **Guard:** Access to guard panel only
   - **Homeowner:** Homeowner-level access
5. Click "Create Employee"

**Editing Employee Roles:**
1. Find employee in the list
2. Click "Edit" button
3. Change role from dropdown
4. Optional: Check "Reset Password" to set new password
5. Click "Update Employee"

**Running RFID Simulation:**
1. Go to **RFID Simulator**
2. Enter RFID tag number
3. Click "Simulate Scan"
4. System simulates gate entry
5. Check "Recent Logs" to see result

**Viewing Audit Logs:**
1. Go to **Audit Logs**
2. See chronological list of all admin actions
3. Filter by:
   - Username
   - Action type
   - Date range
4. Export for compliance/reporting

---

## COMMON TASKS

### For Homeowners:

** Requesting a Visitor Pass**
```
1. Login to Homeowner Portal
2. Navigate to Visitor Passes section
3. Click "Create New Pass"
4. Fill visitor details
5. Set valid dates
6. Submit request
7. Wait for approval (check status periodically)
```

** Updating Your Profile**
```
1. Login to portal
2. Click "Edit Profile"
3. Update information
4. Click "Save Changes"
```

** Changing Your Password**
```
1. Login to portal
2. Go to "Profile Settings"
3. Click "Change Password"
4. Enter current password
5. Enter new password (min 12 characters)
6. Confirm new password
7. Click "Update Password"
```

### For Guards:

** Processing a Vehicle Entry**
```
1. Login to Guard Panel
2. Click "Scan RFID/QR"
3. Scan the vehicle's RFID tag or homeowner's QR code
4. System shows access decision
5. Allow or deny entry based on result
6. Entry is automatically logged
```

** Verifying a Visitor Pass**
```
1. Ask visitor for pass details
2. Search in access logs or check visitor passes
3. Verify:
   - Pass is approved
   - Current date is within valid range
   - Visitor details match
4. Grant or deny entry
```

** Checking Recent Activity**
```
1. View main logs page
2. Recent entries appear at top
3. Use search to find specific vehicle
4. Check status column for access decisions
```

### For Admins:

** Adding a New Homeowner**
```
1. Login to Admin Panel
2. Go to "Manage Records"
3. Click "Add New Homeowner"
4. Fill complete form
5. Assign unique RFID tag
6. Upload photos (optional but recommended)
7. Set initial password
8. Save
```

** Handling Visitor Pass Requests**
```
1. Go to "Visitor Passes"
2. Look for "Pending" status
3. Review request details
4. If legitimate:
   - Click "Approve"
5. If not:
   - Click "Reject"
   - Provide reason for homeowner
```

** Creating Guard Accounts**
```
1. Go to "Employee Management"
2. Click "Create New Employee"
3. Set username (e.g., "Guard1")
4. Set strong password
5. Select role: "Guard"
6. Click "Create"
7. Provide credentials to guard staff
```

** Generating Reports**
```
1. Go to desired section (Logs, Audit, etc.)
2. Set date range filters
3. Click "Export to CSV"
4. Open CSV in Excel/Google Sheets
5. Analyze data as needed
```

---

## SECURITY FEATURES

### Authentication & Authorization

**Multi-Factor Security:**
- Unique usernames required
- Strong password requirements (min 12 characters)
- Password hashing with bcrypt
- Role-based access control (RBAC)
- Session-based authentication

**Account Lockout Protection:**
- Maximum 5 failed login attempts
- 30-minute automatic lockout
- Counter resets on successful login
- Protects against brute force attacks

### 🚦 Rate Limiting

**Registration Protection:**
- 3 registration attempts per hour per IP
- Prevents spam account creation

**Visitor Pass API:**
- 10 pass requests per hour per homeowner
- Prevents API abuse

**Login Attempts:**
- 5 attempts per 15 minutes per IP
- Works in conjunction with account lockout

### Session Management

**Automatic Timeout:**
- 30-minute inactivity timeout
- 5-minute advance warning
- Option to extend session
- Protects unattended workstations

**Session Security:**
- HTTP-only cookies (JavaScript can't access)
- Secure cookies over HTTPS (when enabled)
- CSRF token protection on all forms
- Session regeneration on login

### 🔐 Data Protection

**Input Validation:**
- SQL injection prevention (PDO prepared statements)
- XSS protection (output escaping)
- File upload validation (type, size limits)
- Plate number format validation

**Password Security:**
- Bcrypt hashing (cost factor 10)
- Passwords never stored in plain text
- Passwords never logged
- Reset password functionality with verification

### 📜 Audit Trail

**Comprehensive Logging:**
- All administrative actions logged
- User actions tracked with timestamp
- IP addresses recorded
- Database changes audited
- Failed login attempts logged

---

## TROUBLESHOOTING

### Common Issues & Solutions:

#### **"Invalid CSRF Token"**
**Problem:** Form submission rejected 
**Solution:**
- Refresh the page before submitting
- Clear browser cache and cookies
- Check if session hasn't expired

#### **"Account Locked"**
**Problem:** Too many failed login attempts 
**Solution:**
- Wait 30 minutes for automatic unlock
- Or contact administrator to manually unlock
- Ensure correct password before retrying

#### **"Rate Limit Exceeded"**
**Problem:** Too many requests in short time 
**Solution:**
- Wait for the cooldown period (shown in message)
- Check if you're submitting duplicate requests
- Contact admin if legitimate use is blocked

#### **"Session Expired"**
**Problem:** Logged out automatically 
**Solution:**
- Sessions timeout after 30 minutes of inactivity
- Log in again
- Use "Stay Logged In" when warning appears
- Enable "Remember Me" if available

#### **QR Code Scanner Not Working**
**Problem:** Camera doesn't activate 
**Solution:**
1. Check browser permissions (allow camera access)
2. Ensure using HTTPS (required for camera)
3. Try different browser (Chrome/Firefox recommended)
4. Check if camera is being used by another app

#### **Image Upload Fails**
**Problem:** Photos won't upload 
**Solution:**
- Check file size (max 5MB recommended)
- Use supported formats: JPG, JPEG, PNG
- Ensure `uploads/` directories have write permissions
- Check available disk space on server

#### **Visitor Pass Not Showing**
**Problem:** Approved pass doesn't appear 
**Solution:**
- Refresh the page
- Check if pass is within valid date range
- Verify pass was actually approved (check status)
- Contact admin to verify in database

#### **Can't Create New Employee**
**Problem:** Employee creation fails 
**Solution:**
- Ensure username is unique
- Password must be at least 8 characters
- Check network connection
- Verify you have admin/super_admin role
- Check console for specific errors

---

## KEYBOARD SHORTCUTS

### Global Shortcuts:
- `Ctrl + /` - Focus search bar
- `Esc` - Close modal/dialog
- `Ctrl + R` - Refresh current view

### Admin Panel:
- `Ctrl + Alt + D` - Go to Dashboard
- `Ctrl + Alt + M` - Go to Manage Records
- `Ctrl + Alt + L` - Go to Logs
- `Ctrl + Alt + E` - Go to Employees

---

## BEST PRACTICES

### For Security:
 **DO:**
- Log out when finished
- Use strong, unique passwords
- Change passwords regularly
- Review audit logs periodically
- Keep session timeout warnings enabled
- Verify visitor identities before approving passes

 **DON'T:**
- Share login credentials
- Leave workstation unattended while logged in
- Approve visitor passes without verification
- Use predictable passwords
- Disable security features

### For Data Management:
 **DO:**
- Export logs regularly for backup
- Keep homeowner information up to date
- Delete old logs periodically
- Use consistent naming conventions
- Verify RFID tags are unique before creating homeowners

 **DON'T:**
- Delete logs without exporting first
- Use duplicate RFID tags or plate numbers
- Create test accounts in production
- Modify database directly

### For Operations:
 **DO:**
- Train guards on proper scanning procedures
- Establish visitor pass approval workflow
- Monitor system activity regularly
- Test RFID scanner periodically
- Communicate with homeowners about pass process

 **DON'T:**
- Ignore pending visitor pass requests
- Grant blanket approvals without review
- Skip regular system checks
- Forget to clear old simulations

---

## SYSTEM REQUIREMENTS

### Server Requirements:
- **PHP:** 7.4 or higher (8.x recommended)
- **MySQL/MariaDB:** 5.7+ / 10.3+
- **Apache:** 2.4+ with mod_rewrite
- **Disk Space:** 500MB minimum
- **Memory:** 256MB PHP memory limit

### Client Requirements (Users):
- **Browser:** 
  - Chrome 90+ (recommended)
  - Firefox 88+
  - Safari 14+
  - Edge 90+
- **Screen Resolution:** 1280x720 minimum
- **Internet:** Stable connection required
- **Camera:** Required for QR code scanning (guards/mobile)

### Recommended:
- HTTPS enabled (SSL certificate)
- Regular database backups
- Server monitoring
- Log rotation configured

---

## SUPPORT & MAINTENANCE

### Regular Maintenance Tasks:

**Daily:**
- Monitor access logs for unusual activity
- Check pending visitor pass requests
- Verify guard shifts are covered

**Weekly:**
- Export and backup logs
- Review audit trail
- Check for failed login attempts
- Clear old simulation data

**Monthly:**
- Update system passwords
- Review and update homeowner records
- Generate activity reports
- Check disk space usage
- Update software if patches available

### Getting Help:

**For System Administrators:**
- Check `/docs` folder for technical documentation
- Review `SYSTEM_TEST_REPORT.md` for health checks
- See `SECURITY_IMPROVEMENTS_REPORT.md` for security details

**For Users:**
- Contact your community administrator
- Check this guide first
- Use in-app help buttons ( icons)

---

## PRODUCTION DEPLOYMENT CHECKLIST

Before going live, ensure:

- [ ] All security tests passing (94.4%+)
- [ ] HTTPS enabled with valid SSL certificate
- [ ] `.htaccess.production` renamed to `.htaccess`
- [ ] Database credentials secured in `.env`
- [ ] `APP_DEBUG=false` in `.env`
- [ ] File permissions set correctly (644 files, 755 dirs)
- [ ] Upload directories writable
- [ ] Super admin account created with strong password
- [ ] Test all three portals (Homeowner, Guard, Admin)
- [ ] Backup strategy in place
- [ ] Guards trained on system use
- [ ] Homeowners notified about registration process

---

## VERSION HISTORY

**v1.0 - December 4, 2025**
- Initial production release
- All core features implemented
- Security hardening complete
- Test pass rate: 94.4%
- Employee management functional
- Visitor pass system operational
- QR/RFID scanning working

---

## APPENDIX

### Default Roles:

| Role | Code | Access Level | Can Create |
|------|------|-------------|------------|
| Super Admin | `super_admin` | All features | Admins, Guards, Homeowners |
| Admin | `admin` | Admin panel | Guards, Homeowners |
| Guard | `guard` | Guard panel only | Nothing |
| Homeowner | `homeowner` | Homeowner portal | Visitor passes |

### Status Codes:

**Visitor Passes:**
- `pending` - Yellow - Awaiting approval
- `approved` - Green - Active and valid
- `rejected` - Red - Denied by admin
- `expired` - Gray - Past valid date

**Access Logs:**
- `ALLOWED` - Green - Entry granted
- `DENIED` - Red - Entry refused
- `EXPIRED` - Orange - Pass expired
- `INVALID` - Red - Unknown RFID/QR

### File Locations:

- **Uploads:** `/uploads/homeowner_images/`, `/uploads/vehicle_images/`
- **Logs:** `/admin/logs/`, `/_testing/employee_save_log.txt`
- **Config:** `/config.php`, `/.env`, `/.htaccess`
- **Backups:** `/backups/`
- **QR Codes:** `/phpqrcode/` library

---

**Document End**

*For technical support or system administration questions, refer to the technical documentation or contact your system administrator.*

**System Status:** OPERATIONAL 
**Last System Test:** December 4, 2025 - 94.4% Pass Rate 
**Security Score:** 95/100
