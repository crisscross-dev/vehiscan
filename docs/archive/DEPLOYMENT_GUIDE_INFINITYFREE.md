# VEHISCAN DEPLOYMENT GUIDE - INFINITYFREE HOSTING
**Target Platform:** InfinityFree 
**Deployment Date:** December 4, 2025 
**Estimated Time:** 30-45 minutes

---

## PREREQUISITES

Before starting, have ready:
- Email address for InfinityFree account
- Desired subdomain name (e.g., `vehiscan-demo`)
- FTP client installed (FileZilla recommended) OR use web File Manager
- Your project files ready

---

## PHASE 1: INFINITYFREE ACCOUNT SETUP

### Step 1: Create Account

1. **Visit:** https://infinityfree.net
2. **Click:** "Sign Up Now" (green button)
3. **Fill in:**
   - Email address
   - Password (strong, save it!)
4. **Verify email** - Check inbox and click verification link
5. **Login** to your new account

### Step 2: Create Your Website

1. **In Dashboard**, click **"Create Account"**
2. **Choose subdomain:**
   - Enter your desired name (e.g., `vehiscan-demo`)
   - Choose from available domains (`.infinityfreeapp.com` recommended)
   - Example: `vehiscan-demo.infinityfreeapp.com`
3. **Set password** for your hosting account
4. **Click "Create Account"**
5. **Wait 2-5 minutes** for account activation
6. **Note down:**
   - Your website URL
   - FTP hostname
   - FTP username
   - FTP password

---

## PHASE 2: DATABASE SETUP

### Step 1: Create MySQL Database

1. **Go to Control Panel** (VistaPanel)
2. **Find "MySQL Databases"** section
3. **Click "MySQL Databases"**
4. **Create new database:**
   - Database name will be auto-prefixed (e.g., `ifxxxx_vehiscan`)
   - Click "Create Database"
5. **Create database user:**
   - Username will be auto-prefixed (e.g., `ifxxxx_user`)
   - Generate strong password
   - Click "Create User"
6. **Link user to database:**
   - Select database from dropdown
   - Select user from dropdown
   - Grant ALL PRIVILEGES
   - Click "Add User to Database"

### Step 2: Note Your Database Credentials

**Save these details - you'll need them:**
```
Database Host: sqlXXX.infinityfree.net (check your control panel)
Database Name: ifXXXXX_vehiscan (your actual db name)
Database User: ifXXXXX_user (your actual username)
Database Password: [password you set]
Database Port: 3306
```

### Step 3: Import Database Structure

1. **In Control Panel**, find **phpMyAdmin**
2. **Click "phpMyAdmin"**
3. **Login** (uses same credentials as database)
4. **Select your database** from left sidebar
5. **Click "Import" tab**
6. **Choose SQL file:**
   - Use your database export OR
   - Use the migration scripts
7. **Click "Go"**
8. **Wait for import** to complete

**If you don't have SQL export yet:**
```sql
-- Run these SQL commands in phpMyAdmin SQL tab
-- Copy from your local database or use migration scripts
```

---

## PHASE 3: PREPARE FILES FOR UPLOAD

### Step 1: Update Configuration Files

**Create/Update `.env` file in your project root:**

```env
# InfinityFree Database Configuration
DB_HOST=sql123.infinityfree.net
DB_NAME=ifxxxxx_vehiscan
DB_USER=ifxxxxx_user
DB_PASS=your_database_password
DB_CHARSET=utf8mb4

# Application Settings
APP_ENV=production
APP_DEBUG=false

# Security (optional - will be auto-detected)
FORCE_HTTPS=true
```

**Update `config.php` if needed:**
Make sure it reads from `.env` file properly.

### Step 2: Prepare .htaccess

1. **Rename** `.htaccess.production` to `.htaccess`
2. **Edit if needed** - InfinityFree specific settings:

```apache
# InfinityFree Compatible .htaccess

# Security Headers
<IfModule mod_headers.c>
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Deny access to .env files
<FilesMatch "^\.env">
    Require all denied
</FilesMatch>

# Disable directory browsing
Options -Indexes

# PHP Settings (if allowed)
<IfModule mod_php7.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 300
    php_value max_input_time 300
    php_value memory_limit 256M
</IfModule>

# Error Pages
ErrorDocument 404 /404.html
ErrorDocument 403 /403.html
ErrorDocument 500 /500.html
```

### Step 3: Create Upload Directories Structure

Create these folders if they don't exist:
- `uploads/`
- `uploads/homeowner_images/`
- `uploads/vehicle_images/`
- `uploads/qr_codes/`

Add `.htaccess` in each upload folder:
```apache
# uploads/.htaccess
# Prevent PHP execution in uploads
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    Require all denied
</FilesMatch>
```

---

## PHASE 4: FILE UPLOAD

### Option A: Using FTP (FileZilla Recommended)

#### Install FileZilla:
1. Download from: https://filezilla-project.org/
2. Install on your computer

#### Connect to Server:
1. **Open FileZilla**
2. **Enter connection details:**
   - **Host:** `ftpupload.net` or FTP hostname from InfinityFree
   - **Username:** Your FTP username (from InfinityFree)
   - **Password:** Your FTP password
   - **Port:** 21
3. **Click "Quickconnect"**
4. **Accept certificate** if prompted

#### Upload Files:
1. **Navigate** to `/htdocs/` folder on the server (right panel)
2. **Select all project files** from local computer (left panel)
3. **Drag and drop** OR right-click → Upload
4. **Wait for upload** to complete (may take 10-30 minutes)
5. **Set folder permissions** (right-click folder → File permissions):
   - `uploads/` → 755 or 777
   - `uploads/homeowner_images/` → 755 or 777
   - `uploads/vehicle_images/` → 755 or 777

#### Files to EXCLUDE from upload:
- `/.git/` folder
- `/node_modules/` folder
- `/_testing/` folder (optional, but recommended for production)
- `/backups/` folder (optional)
- `/.env.example` (optional)
- `/package.json` (if you have one)
- `/composer.json` (if not needed)
- Large backup files
- Local development files

### Option B: Using Web File Manager

1. **In Control Panel**, click **"Online File Manager"**
2. **Navigate** to `/htdocs/` folder
3. **Click "Upload"** button
4. **Select files** (Note: Can only upload smaller files)
5. **For large projects**, create a ZIP file first:
   - Compress your project locally
   - Upload ZIP file
   - Extract in File Manager
6. **Set permissions** (right-click folders → Change Permissions)

---

## PHASE 5: DATABASE IMPORT

### Option 1: Using phpMyAdmin

1. **Access phpMyAdmin** from control panel
2. **Select your database**
3. **Go to "Import" tab**
4. **Choose your SQL file**
5. **Settings:**
   - Format: SQL
   - Character set: utf8mb4
   - Format-specific options: defaults are fine
6. **Click "Go"**
7. **Wait for completion**

### Option 2: Using Migration Scripts

If you have migration files in `/migrations/`:

1. **Navigate to:** `https://your-site.infinityfreeapp.com/scripts/migrate.php`
2. **Run migrations** through web interface
3. **Check results**

### Verify Database Import:

1. **In phpMyAdmin**, click your database
2. **Check tables exist:**
   - `audit_logs`
   - `homeowner_auth`
   - `homeowners`
   - `recent_logs`
   - `rfid_simulator`
   - `super_admin`
   - `users`
   - `visitor_passes`
   - `rate_limits`
   - `failed_login_attempts`
3. **Check sample data** - click Browse on any table

---

## PHASE 6: CONFIGURATION & TESTING

### Step 1: Test Database Connection

**Navigate to:** `https://your-site.infinityfreeapp.com/`

If you see connection errors:
1. Double-check database credentials in `.env`
2. Ensure database host is correct (check control panel)
3. Verify database user has ALL privileges

### Step 2: Create Super Admin Account

**Option A: Via First Run Setup**
1. Navigate to: `https://your-site.infinityfreeapp.com/auth/first_run_setup.php`
2. Fill in super admin details
3. Create account

**Option B: Using phpMyAdmin**
```sql
-- Run in phpMyAdmin SQL tab
INSERT INTO super_admin (username, password) 
VALUES ('Administrator', '$2y$10$YourBcryptHashHere');

-- Generate hash by running locally:
-- php -r "echo password_hash('YourStrongPassword', PASSWORD_BCRYPT);"
```

### Step 3: Test All Portals

**Test Homeowner Portal:**
- URL: `https://your-site.infinityfreeapp.com/homeowners/portal.php`
- Try login (if you have homeowner accounts)
- Check functionality

**Test Guard Panel:**
- URL: `https://your-site.infinityfreeapp.com/guard/pages/guard_side.php`
- Try login (if you have guard accounts)
- Test QR scanning

**Test Admin Panel:**
- URL: `https://your-site.infinityfreeapp.com/admin/admin_panel.php`
- Login with super admin credentials
- Check all features:
  - Dashboard loads
  - Manage Records works
  - Employee Management works
  - Logs display
  - Visitor passes show

### Step 4: Test Core Features

 **Authentication:**
- Login to each portal
- Logout works
- Session timeout (wait 30 minutes)

 **CRUD Operations:**
- Create new homeowner
- Edit existing homeowner
- Delete homeowner (test account)
- Create employee
- Edit employee role

 **Visitor Passes:**
- Request visitor pass (as homeowner)
- Approve pass (as admin)
- Check pass appears in guard panel

 **File Uploads:**
- Upload homeowner image
- Upload vehicle image
- Verify images display

 **QR Codes:**
- Check if QR codes generate
- Try scanning with phone camera

---

## PHASE 7: ENABLE SSL (HTTPS)

### Step 1: Enable SSL Certificate

1. **In Control Panel**, find **"SSL Certificates"**
2. **Click "Manage SSL"**
3. **Enable SSL** for your domain
4. **Wait 10-60 minutes** for activation
5. **Certificate type:** Let's Encrypt (Free)

### Step 2: Force HTTPS (After SSL Active)

**Option A: Via .htaccess** (Uncomment these lines):
```apache
# Force HTTPS Redirect
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Option B: Via PHP** (already in your code):
The session files already check for HTTPS and set secure cookies.

### Step 3: Update Links

1. **Test HTTPS version:** `https://your-site.infinityfreeapp.com`
2. **Update any hardcoded HTTP links** in your code
3. **Clear browser cache**
4. **Test all portals** again with HTTPS

---

## PHASE 8: POST-DEPLOYMENT CHECKS

### Security Checklist:

- [ ] `.env` file is NOT publicly accessible
  - Test: `https://your-site.com/.env` should show 403 error
- [ ] `config.php` is NOT directly accessible
  - Test: Should show 403 or blank page
- [ ] Database credentials are secure
- [ ] APP_DEBUG is set to `false`
- [ ] HTTPS is working
- [ ] Session cookies are secure
- [ ] File upload directories have correct permissions
- [ ] Sensitive files are protected
- [ ] Directory listing is disabled

### Performance Checks:

- [ ] Pages load within 3 seconds
- [ ] Images load properly
- [ ] No broken links
- [ ] Database queries are fast
- [ ] QR codes generate successfully

### Functionality Checks:

- [ ] All three portals accessible
- [ ] Login/logout working
- [ ] CRUD operations functional
- [ ] Visitor pass workflow complete
- [ ] Employee management working
- [ ] Logs displaying correctly
- [ ] CSV exports working
- [ ] QR/RFID scanning operational

---

## TROUBLESHOOTING COMMON ISSUES

### Issue: "500 Internal Server Error"

**Solution:**
1. Check `.htaccess` syntax
2. Look at error logs in Control Panel → Error Logs
3. Disable .htaccess temporarily (rename to `.htaccess.bak`)
4. Test if site loads
5. Add directives back one by one

### Issue: "Database Connection Failed"

**Solution:**
1. Verify database credentials in `.env`
2. Check database host (might be `sqlXXX.infinityfree.net` not `localhost`)
3. Ensure database user has privileges
4. Test connection using phpMyAdmin
5. Check if database exists

### Issue: "File Upload Not Working"

**Solution:**
1. Check folder permissions (755 or 777)
2. Verify folders exist:
   - `uploads/homeowner_images/`
   - `uploads/vehicle_images/`
3. Check PHP upload limits in .htaccess
4. Test with small image first (< 1MB)

### Issue: "Session Not Persisting"

**Solution:**
1. Check if cookies are enabled in browser
2. Verify session files can be written
3. Check session.cookie_secure setting
4. Clear browser cookies
5. Try different browser

### Issue: "CSS/JS Not Loading"

**Solution:**
1. Check file paths (relative vs absolute)
2. Verify files were uploaded to correct location
3. Check browser console for 404 errors
4. Clear browser cache (Ctrl + Shift + R)
5. Check .htaccess isn't blocking files

### Issue: "QR Codes Not Generating"

**Solution:**
1. Check if `/phpqrcode/` library uploaded
2. Verify write permissions on upload folders
3. Check PHP GD extension enabled
4. Test with simple QR code first
5. Check error logs

### Issue: "Rate Limiting Not Working"

**Solution:**
1. Check `rate_limits` table exists
2. Verify RateLimiter class file uploaded
3. Check database connection
4. Test with multiple requests
5. Check timestamps are correct

---

## MAINTENANCE & MONITORING

### Daily Checks:
- Monitor access logs for errors
- Check disk space usage (Control Panel → Statistics)
- Verify backups are running

### Weekly Tasks:
- Export database backup
- Review error logs
- Check for failed login attempts
- Update software if needed

### Monthly Tasks:
- Clear old logs
- Review user accounts
- Check security settings
- Test disaster recovery

---

## BACKUP STRATEGY

### Automated Backups (Recommended):

**Option 1: Use InfinityFree Backups**
- Control Panel → Backups
- Download full backup weekly
- Store locally or in cloud (Google Drive, Dropbox)

**Option 2: Database Auto-Export**
Create a cron job (if available) or manual export:
1. phpMyAdmin → Export
2. Format: SQL
3. Save with date: `vehiscan_backup_2025-12-04.sql`

### Manual Backup Checklist:
- [ ] Database export (SQL file)
- [ ] Uploads folder (`/uploads/`)
- [ ] Configuration files (`.env`)
- [ ] Custom modifications

---

## UPGRADING TO PAID HOSTING

If you outgrow the free tier:

**InfinityFree Premium:** ~$3-8/month
- No hit limits
- Better support
- More resources
- Custom nameservers

**Migration is easy:**
1. Same control panel
2. Just upgrade account
3. No file transfer needed

---

## SUPPORT RESOURCES

### InfinityFree Support:
- **Forum:** https://forum.infinityfree.net
- **Knowledge Base:** https://infinityfree.net/support
- **Discord:** Community support available

### VehiScan Documentation:
- `USER_NAVIGATION_GUIDE.md` - User instructions
- `SYSTEM_TEST_REPORT.md` - System health
- `SECURITY_IMPROVEMENTS_REPORT.md` - Security details

### Emergency Contact:
- InfinityFree support via forum
- Check status: https://status.infinityfree.net

---

## QUICK REFERENCE

### Important URLs:
```
Control Panel: https://dash.infinityfree.net
phpMyAdmin: [Link in control panel]
File Manager: [Link in control panel]
Your Site: https://your-subdomain.infinityfreeapp.com

Admin Panel: /admin/admin_panel.php
Guard Panel: /guard/pages/guard_side.php
Homeowner Portal: /homeowners/portal.php
```

### FTP Details:
```
Host: ftpupload.net
Port: 21
Username: ifxxxxx_xxxxxxx
Password: [from infinityfree]
```

### Database Details:
```
Host: sqlXXX.infinityfree.net
Port: 3306
Name: ifxxxxx_vehiscan
User: ifxxxxx_user
Pass: [your password]
```

---

## DEPLOYMENT CHECKLIST

Use this checklist to track your progress:

**Account Setup:**
- [ ] InfinityFree account created
- [ ] Website created with subdomain
- [ ] Control panel accessed

**Database:**
- [ ] MySQL database created
- [ ] Database user created and linked
- [ ] Database structure imported
- [ ] Sample data loaded (if needed)
- [ ] Connection tested

**Files:**
- [ ] Configuration files updated (`.env`)
- [ ] .htaccess renamed and configured
- [ ] All files uploaded via FTP/File Manager
- [ ] Folder permissions set (755/777)
- [ ] Upload directories created

**Testing:**
- [ ] Database connection works
- [ ] Super admin account created
- [ ] Homeowner portal loads
- [ ] Guard panel loads
- [ ] Admin panel loads
- [ ] Login/logout working
- [ ] CRUD operations tested
- [ ] File uploads working
- [ ] QR codes generating

**Security:**
- [ ] SSL certificate installed
- [ ] HTTPS redirect enabled
- [ ] Sensitive files protected
- [ ] APP_DEBUG set to false
- [ ] Session security configured

**Final:**
- [ ] All features tested
- [ ] Performance verified
- [ ] Backup created
- [ ] Documentation reviewed
- [ ] Users notified of new URL

---

**Deployment Complete!** 

Your VehiScan RFID system is now live on InfinityFree.

**Next Steps:**
1. Share the URL with test users
2. Gather feedback
3. Monitor for issues
4. Plan for production deployment when ready

---

**Document Version:** 1.0 
**Last Updated:** December 4, 2025 
**Status:** Ready for Deployment
