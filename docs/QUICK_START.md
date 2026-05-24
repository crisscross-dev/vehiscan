# Quick Start Guide - Super Admin System

## What's Been Completed

### Phase 1A & 1B: Super Admin Authentication (DONE)

The system now has a **secure Super Admin authentication** system with:

- **No default credentials** - First-run setup required
- **Strong password enforcement** (12+ chars, complexity requirements)
- **Account lockout** after 5 failed attempts (30 minutes)
- **Failed login tracking** with IP and user agent logging
- **Separate session management** for Super Admin vs regular users
- **Enhanced security** with session regeneration

---

## 🎯 How to Use Your New System

### 1. **If You Already Created a Super Admin Account:**

Simply login at:
```
http://localhost/Vehiscan-RFID/auth/login.php
```

Use the credentials you created during first-run setup.

### 2. **If You Haven't Set Up Yet:**

Run the migration first:
```
http://localhost/Vehiscan-RFID/scripts/migrate.php
```

Then complete the first-run setup:
```
http://localhost/Vehiscan-RFID/auth/first_run_setup.php
```

---

## 🔐 Security Features Now Active

### Account Lockout Protection
- **5 failed login attempts** = 30-minute lockout
- Automatic unlock after timeout
- All failed attempts logged with IP address

### Password Requirements
- Minimum 12 characters
- At least one uppercase letter (A-Z)
- At least one lowercase letter (a-z)
- At least one number (0-9)
- At least one special character (!@#$%^&*)

### Session Security
- 30-minute timeout for inactivity
- Session ID regeneration every 5 minutes
- HTTPOnly and SameSite cookies
- Separate session namespaces (super_admin, admin, guard)

---

## Database Tables Created

### `super_admin`
Stores Super Admin accounts with:
- Username, email, full name
- Password hash (bcrypt)
- Failed login attempts counter
- Lock status and timestamps
- 2FA support (ready for future implementation)

### `security_settings`
Configurable security policies:
- Password requirements
- Session timeout
- Lockout duration
- Backup encryption settings

### `failed_login_attempts`
Audit log of all failed login attempts:
- Username, IP address, user agent
- Timestamp and failure reason
- Used for security monitoring

### `system_installation`
Tracks system setup status:
- Is system installed?
- Installation date and installer
- System version

---

## Authentication Flow

```
┌─────────────────────────────────────────┐
│ User Enters Credentials │
└────────────────┬────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────┐
│ Check Super Admin Table First │
│ SELECT * FROM super_admin │
└────────┬──────────────┬─────────────────┘
         │ │
    Found│ │Not Found
         │ │
         ▼ ▼
┌──────────────┐ ┌────────────────────┐
│ Super Admin │ │ Check Users Table │
│ Account │ │ (guards/homeowners)│
└───────┬──────┘ └─────────┬──────────┘
        │ │
        ▼ ▼
┌────────────────┐ ┌──────────────────┐
│ Check Lockout │ │ Regular User │
│ Status │ │ Authentication │
└───────┬────────┘ └────────┬─────────┘
        │ │
   Not Locked │
        │ │
        ▼ ▼
┌────────────────────────────────────────┐
│ Verify Password (bcrypt) │
└────────┬───────────────────────────────┘
         │
    Valid│
         ▼
┌─────────────────────────────────────────┐
│ Create Session: │
│ - vehiscan_superadmin (Super Admin) │
│ - vehiscan_admin (Admin) │
│ - vehiscan_guard (Guard) │
└────────┬────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────┐
│ Redirect to Appropriate Panel │
│ - Admin Panel (Super Admin & Admin) │
│ - Guard Panel (Guards) │
└─────────────────────────────────────────┘
```

---

## Admin Panel Access

The admin panel now supports **both**:
- **Super Admin** (from `super_admin` table)
- **Regular Admin** (from `users` table with role='admin')

Both can access the admin panel with full privileges.

---

## 🚨 Troubleshooting

### "Login Failed" Error
**Solution:** 
1. Make sure you ran the migration: `scripts/migrate.php`
2. Complete first-run setup: `auth/first_run_setup.php`
3. Use the exact credentials you created

### Account Locked Message
**Solution:**
- Wait 30 minutes for automatic unlock
- Or manually reset in database:
```sql
UPDATE super_admin 
SET failed_login_attempts = 0, locked_until = NULL 
WHERE username = 'your_username';
```

### Can't Access Admin Panel
**Solution:**
1. Clear browser cookies
2. Logout completely: `auth/logout.php`
3. Login again with Super Admin credentials

---

## What's Next (Remaining Work)

### Phase 1C: Security Hardening (2 days)
- HTTPS enforcement
- CSP headers
- Enhanced CSRF protection

### Phase 2: Full-System Backup (4 days)
- Database + files backup
- AES-256 encryption
- Restore functionality
- Scheduled backups

### Phase 3: UI Unification (4 days)
- Guard panel redesign (match Admin UI)
- Login page modernization
- Logo integration everywhere
- Dark mode consistency

### Phase 4: Enhanced Audit System (2 days)
- Comprehensive activity logging
- Enhanced audit logs table
- Security event tracking

### Phase 5-6: Testing & Documentation (5 days)
- Complete migration system
- PHPUnit test suite
- README and security docs

**Total Remaining:** ~17 days of development

---

## Development Tips

### Testing Super Admin Login
```php
// Check current session in browser console
console.log(document.cookie);
// Should see: vehiscan_superadmin=...
```

### Viewing Failed Login Attempts
```sql
SELECT * FROM failed_login_attempts 
ORDER BY attempted_at DESC 
LIMIT 10;
```

### Checking Super Admin Status
```sql
SELECT username, email, failed_login_attempts, 
       locked_until, last_login 
FROM super_admin;
```

### Resetting Everything (Dev Only)
```sql
-- Reset super admin account
DELETE FROM super_admin;

-- Reset installation status
UPDATE system_installation 
SET is_installed = 0 
WHERE id = 1;

-- Clear failed attempts
DELETE FROM failed_login_attempts;
```

Then access `auth/first_run_setup.php` again.

---

## Quick Links

- **Migration Runner:** `scripts/migrate.php`
- **First-Run Setup:** `auth/first_run_setup.php`
- **Login Page:** `auth/login.php`
- **Admin Panel:** `admin/admin_panel.php`
- **Guard Panel:** `guard/pages/guard_side.php`
- **Logout:** `auth/logout.php`

---

## 📞 Support

For issues or questions:
1. Check the main **REFACTORING_ROADMAP.md** for complete documentation
2. Review database logs in `failed_login_attempts` table
3. Check PHP error logs for detailed errors

---

**Last Updated:** November 20, 2025 
**System Version:** 2.0.0 (Super Admin Edition) 
**Status:** Phase 1A & 1B Complete 
