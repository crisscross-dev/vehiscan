# VehiScan RFID - Complete System Refactoring Roadmap

## Overview
This document outlines the complete refactoring plan to transform VehiScan RFID into a production-ready, secure, and unified system.

**Estimated Timeline:** 3-4 weeks for full implementation
**Priority:** High-security features first, UI unification second

---

## 🎯 Refactoring Goals

### 1. **Security Hardening** 
- Replace multi-admin with Single Super Admin role
- Remove all default credentials
- Implement first-run setup wizard
- Add HTTPS enforcement
- Enhanced CSRF protection
- SQL injection prevention (prepared statements everywhere)
- XSS protection with Content Security Policy
- Password policy enforcement
- Rate limiting on auth endpoints
- Brute force protection

### 2. **Full-System Backup** 
- Database backup with compression
- File uploads backup (images, documents)
- Configuration files backup
- AES-256 encryption for backup files
- Automated backup scheduling
- Backup restoration functionality
- Cloud storage integration (optional)
- Backup integrity verification

### 3. **Unified UI/UX** 🎨
- Apply Shadcn/Tailwind to Guard panel (match Admin)
- Consistent logo across all interfaces
- Responsive design system
- Dark mode support everywhere
- Unified component library
- Consistent navigation patterns
- Mobile-first approach

### 4. **Enhanced Audit System** 
- Comprehensive activity logging
- User action tracking
- Failed login attempts
- Configuration changes
- Data modifications
- Backup operations
- Export operations

### 5. **Database Migrations** 🗄
- Super admin table structure
- Enhanced audit logs
- Backup metadata table
- Security settings table
- Session management table
- Failed login attempts table

---

## Implementation Phases

### **PHASE 1: CRITICAL SECURITY (Week 1)** 

#### 1.1 Super Admin System
**Files to Create:**
- `/config/security_config.php` - Security settings
- `/auth/first_run_setup.php` - Initial setup wizard
- `/includes/super_admin.php` - Super admin session handler
- `/admin/utilities/security_manager.php` - Security management

**Files to Modify:**
- `/db.php` - Add security options
- `/auth/login.php` - Super admin authentication
- `/auth/logout.php` - Enhanced session destruction
- `/includes/session_admin.php` - Integrate super admin checks

**Database Changes:**
```sql
-- Drop old users table approach, create super_admin table
CREATE TABLE super_admin (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  password_changed_at TIMESTAMP NULL,
  require_password_change BOOLEAN DEFAULT 0,
  failed_login_attempts INT DEFAULT 0,
  locked_until TIMESTAMP NULL,
  two_factor_secret VARCHAR(100),
  two_factor_enabled BOOLEAN DEFAULT 0
);

CREATE TABLE security_settings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  setting_key VARCHAR(100) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE failed_login_attempts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50),
  ip_address VARCHAR(45),
  user_agent TEXT,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_username (username),
  INDEX idx_ip (ip_address),
  INDEX idx_attempted (attempted_at)
);
```

**Key Features:**
- No default credentials
- First-run setup wizard
- Strong password requirements (12+ chars, special, number, upper, lower)
- Account lockout after 5 failed attempts
- Password expiry policy (90 days)
- 2FA support (TOTP)

---

#### 1.2 HTTPS Enforcement
**Files to Create:**
- `/includes/https_enforcer.php`
- `/.htaccess` - Apache rewrite rules

**Implementation:**
```php
// includes/https_enforcer.php
<?php
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    if (php_sapi_name() !== 'cli') {
        $redirectUrl = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $redirectUrl", true, 301);
        exit();
    }
}

// Set security headers
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Content Security Policy
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
       "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
       "img-src 'self' data: blob:; " .
       "font-src 'self' data:; " .
       "connect-src 'self'; " .
       "frame-ancestors 'none';";
header("Content-Security-Policy: $csp");
?>
```

---

#### 1.3 Enhanced CSRF Protection
**Files to Modify:**
- All POST endpoints
- `/includes/csrf.php` (new)

**Implementation:**
```php
// includes/csrf.php
<?php
class CSRF {
    private static $tokenKey = 'csrf_token';
    private static $tokenExpiry = 3600; // 1 hour
    
    public static function generateToken() {
        if (!isset($_SESSION[self::$tokenKey]) || 
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > self::$tokenExpiry) {
            
            $_SESSION[self::$tokenKey] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION[self::$tokenKey];
    }
    
    public static function validateToken($token) {
        if (!isset($_SESSION[self::$tokenKey]) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        if ((time() - $_SESSION['csrf_token_time']) > self::$tokenExpiry) {
            self::regenerateToken();
            return false;
        }
        
        return hash_equals($_SESSION[self::$tokenKey], $token);
    }
    
    public static function regenerateToken() {
        unset($_SESSION[self::$tokenKey]);
        unset($_SESSION['csrf_token_time']);
        return self::generateToken();
    }
}
?>
```

---

### **PHASE 2: FULL-SYSTEM BACKUP (Week 1-2)** 

#### 2.1 Backup System Architecture
**Files to Create:**
- `/admin/utilities/backup_manager.php` - Main backup controller
- `/admin/utilities/backup_encryption.php` - AES-256 encryption
- `/admin/utilities/backup_scheduler.php` - Cron job handler
- `/admin/utilities/backup_restore.php` - Restoration functionality
- `/admin/api/backup_api.php` - AJAX endpoints

**Database Changes:**
```sql
CREATE TABLE backup_metadata (
  id INT PRIMARY KEY AUTO_INCREMENT,
  backup_type ENUM('manual', 'scheduled', 'pre-update') NOT NULL,
  backup_path VARCHAR(500) NOT NULL,
  file_size BIGINT NOT NULL,
  is_encrypted BOOLEAN DEFAULT 1,
  encryption_method VARCHAR(50) DEFAULT 'AES-256-CBC',
  checksum VARCHAR(64), -- SHA-256 hash
  created_by VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL,
  restore_tested BOOLEAN DEFAULT 0,
  restore_tested_at TIMESTAMP NULL,
  notes TEXT,
  INDEX idx_created (created_at),
  INDEX idx_type (backup_type)
);
```

**Backup Components:**
1. **Database Backup**
   - All tables (structure + data)
   - Stored procedures, triggers, views
   - Compressed with gzip
   
2. **File System Backup**
   - `/uploads/*` - All uploaded images
   - `/config/*` - Configuration files
   - `/assets/images/*` - Logo and assets
   - `.env` file (if exists)
   
3. **Encryption**
   - AES-256-CBC encryption
   - Unique encryption key per backup
   - Key stored securely (not in backup)
   - Key backup to secure location

**Backup Features:**
```php
// admin/utilities/backup_manager.php
class BackupManager {
    private $backupDir;
    private $encryptionKey;
    private $pdo;
    
    public function createFullBackup($type = 'manual') {
        $timestamp = date('Y-m-d_His');
        $backupId = uniqid('backup_', true);
        
        // 1. Create backup directory
        $backupPath = $this->backupDir . '/' . $backupId;
        mkdir($backupPath, 0755, true);
        
        // 2. Backup database
        $dbFile = $this->backupDatabase($backupPath);
        
        // 3. Backup files
        $filesArchive = $this->backupFiles($backupPath);
        
        // 4. Create manifest
        $manifest = $this->createManifest($backupPath, $dbFile, $filesArchive);
        
        // 5. Compress everything
        $archive = $this->createArchive($backupPath, $backupId);
        
        // 6. Encrypt archive
        $encryptedFile = $this->encryptBackup($archive);
        
        // 7. Generate checksum
        $checksum = hash_file('sha256', $encryptedFile);
        
        // 8. Save metadata
        $this->saveBackupMetadata([
            'backup_type' => $type,
            'backup_path' => $encryptedFile,
            'file_size' => filesize($encryptedFile),
            'checksum' => $checksum,
            'created_by' => $_SESSION['username']
        ]);
        
        // 9. Cleanup temp files
        $this->cleanup($backupPath);
        
        return [
            'success' => true,
            'backup_id' => $backupId,
            'file' => basename($encryptedFile),
            'size' => $this->formatBytes(filesize($encryptedFile)),
            'checksum' => $checksum
        ];
    }
    
    public function restoreBackup($backupId, $password) {
        // Implementation for restore
    }
    
    public function scheduleBackup($frequency, $retention) {
        // Implementation for scheduling
    }
}
```

---

### **PHASE 3: UI UNIFICATION (Week 2-3)** 

#### 3.1 Guard Panel Redesign
**Files to Modify:**
- `/guard/pages/guard_side.php` - Apply Shadcn sidebar
- `/guard/css/guard_side.css` - Match admin theme
- `/guard/js/guard_side.js` - Update for new UI

**Files to Create:**
- `/guard/components/sidebar.php` - Reusable sidebar
- `/guard/components/header.php` - Unified header
- `/assets/css/guard/guard_unified.css` - New styles

**Key Changes:**
```html
<!-- New Guard Sidebar (match Admin) -->
<aside id="sidebar" class="sidebar-transition sidebar-open">
  <!-- Brand Header -->
  <div class="flex h-14 items-center border-b px-4">
    <img src="../assets/images/vehiscan-logo.png" class="h-9 w-9" alt="VehiScan">
    <span class="sidebar-text ml-3 text-lg font-bold">VehiScan</span>
  </div>
  
  <!-- Navigation -->
  <div class="flex-1 overflow-y-auto py-2">
    <div class="space-y-1 px-3">
      <a href="#dashboard" class="menu-item active">
        <svg>...</svg>
        <span class="sidebar-text">Dashboard</span>
      </a>
      <a href="#logs" class="menu-item">
        <svg>...</svg>
        <span class="sidebar-text">Access Logs</span>
      </a>
      <!-- More menu items -->
    </div>
  </div>
  
  <!-- User Section -->
  <div class="mt-auto border-t p-4">
    <button id="user-trigger" class="flex w-full items-center gap-3">
      <div class="h-8 w-8 rounded-full bg-sidebar-accent">
        <svg>...</svg>
      </div>
      <div class="sidebar-text">
        <span><?php echo $_SESSION['username']; ?></span>
        <span class="text-xs">Guard</span>
      </div>
      <svg id="chevron">...</svg>
    </button>
  </div>
</aside>
```

#### 3.2 Login Page Redesign
**Files to Modify:**
- `/auth/login.php` - Add logo, modern design

**New Design:**
```html
<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
    <!-- Logo -->
    <div class="flex justify-center mb-6">
      <img src="../assets/images/vehiscan-logo.png" class="h-20 w-20" alt="VehiScan">
    </div>
    
    <!-- Title -->
    <h1 class="text-3xl font-bold text-center text-gray-900 mb-2">VehiScan RFID</h1>
    <p class="text-center text-gray-600 mb-8">Sign in to your account</p>
    
    <!-- Form -->
    <form method="POST" class="space-y-6">
      <!-- Username -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
        <input type="text" name="username" required 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
      </div>
      
      <!-- Password -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
        <input type="password" name="password" required 
               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
      </div>
      
      <!-- Submit -->
      <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-indigo-600 text-white py-3 rounded-lg font-semibold hover:from-blue-600 hover:to-indigo-700 transition-all">
        Sign In
      </button>
    </form>
  </div>
</div>
```

---

### **PHASE 4: ENHANCED AUDIT SYSTEM (Week 3)** 🔵

#### 4.1 Comprehensive Audit Logging
**Files to Create:**
- `/includes/audit_logger.php` - Central logging class
- `/admin/fetch/fetch_audit_enhanced.php` - Enhanced audit view

**Database Changes:**
```sql
CREATE TABLE audit_logs_enhanced (
  id INT PRIMARY KEY AUTO_INCREMENT,
  event_type ENUM('auth', 'data', 'config', 'backup', 'security') NOT NULL,
  action VARCHAR(100) NOT NULL,
  username VARCHAR(50),
  user_role VARCHAR(20),
  table_name VARCHAR(100),
  record_id INT,
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  request_method VARCHAR(10),
  request_uri TEXT,
  severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
  status ENUM('success', 'failure', 'warning') DEFAULT 'success',
  error_message TEXT,
  session_id VARCHAR(100),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_event (event_type),
  INDEX idx_username (username),
  INDEX idx_created (created_at),
  INDEX idx_severity (severity),
  INDEX idx_status (status)
);
```

**Audit Logger Implementation:**
```php
// includes/audit_logger.php
class AuditLogger {
    private static $pdo;
    
    public static function init($pdo) {
        self::$pdo = $pdo;
    }
    
    public static function log($eventType, $action, $data = []) {
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO audit_logs_enhanced (
                    event_type, action, username, user_role, table_name, 
                    record_id, old_values, new_values, ip_address, 
                    user_agent, request_method, request_uri, severity, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $eventType,
                $action,
                $_SESSION['username'] ?? 'anonymous',
                $_SESSION['role'] ?? 'unknown',
                $data['table_name'] ?? null,
                $data['record_id'] ?? null,
                isset($data['old_values']) ? json_encode($data['old_values']) : null,
                isset($data['new_values']) ? json_encode($data['new_values']) : null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                $_SERVER['REQUEST_URI'] ?? 'unknown',
                $data['severity'] ?? 'low',
                $data['status'] ?? 'success'
            ]);
        } catch (Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
    
    // Specific logging methods
    public static function logAuth($action, $success, $username = null) {
        self::log('auth', $action, [
            'severity' => $success ? 'low' : 'high',
            'status' => $success ? 'success' : 'failure'
        ]);
    }
    
    public static function logDataChange($action, $table, $recordId, $oldValues, $newValues) {
        self::log('data', $action, [
            'table_name' => $table,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'severity' => 'medium'
        ]);
    }
    
    public static function logBackup($action, $backupSize, $success) {
        self::log('backup', $action, [
            'severity' => 'high',
            'status' => $success ? 'success' : 'failure'
        ]);
    }
    
    public static function logSecurityEvent($action, $severity = 'high') {
        self::log('security', $action, [
            'severity' => $severity,
            'status' => 'warning'
        ]);
    }
}
```

---

### **PHASE 5: MIGRATION SCRIPTS (Week 3-4)** ⚪

#### 5.1 Database Migration System
**Files to Create:**
- `/scripts/migrate.php` - Migration runner
- `/migrations/001_create_super_admin.sql`
- `/migrations/002_create_security_tables.sql`
- `/migrations/003_create_backup_metadata.sql`
- `/migrations/004_enhance_audit_logs.sql`
- `/migrations/005_add_indices.sql`

**Migration Runner:**
```php
// scripts/migrate.php
<?php
require_once __DIR__ . '/../db.php';

class MigrationRunner {
    private $pdo;
    private $migrationsDir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->migrationsDir = __DIR__ . '/../migrations';
        $this->ensureMigrationsTable();
    }
    
    private function ensureMigrationsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT PRIMARY KEY AUTO_INCREMENT,
                migration_name VARCHAR(255) UNIQUE NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function run() {
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);
        
        foreach ($files as $file) {
            $name = basename($file);
            
            // Check if already executed
            $stmt = $this->pdo->prepare("SELECT id FROM migrations WHERE migration_name = ?");
            $stmt->execute([$name]);
            
            if ($stmt->fetch()) {
                echo "⏭ Skipping: $name (already executed)\n";
                continue;
            }
            
            echo " Running: $name\n";
            
            try {
                $sql = file_get_contents($file);
                $this->pdo->exec($sql);
                
                // Mark as executed
                $stmt = $this->pdo->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
                $stmt->execute([$name]);
                
                echo " Completed: $name\n";
            } catch (Exception $e) {
                echo " Failed: $name - " . $e->getMessage() . "\n";
                break;
            }
        }
    }
    
    public function rollback($steps = 1) {
        // Implementation for rollback
    }
}

// Run migrations
$runner = new MigrationRunner($pdo);
$runner->run();
```

---

### **PHASE 6: TESTING & DOCUMENTATION (Week 4)** 🟣

#### 6.1 Test Suite
**Files to Create:**
- `/tests/BackupTest.php` - Backup/restore tests
- `/tests/AuthTest.php` - Authentication flow tests
- `/tests/CSRFTest.php` - CSRF protection tests
- `/tests/AuditTest.php` - Audit logging tests
- `/tests/RoleAccessTest.php` - Role-based access tests

#### 6.2 Documentation Updates
**Files to Update:**
- `/README.md` - Complete rewrite
- `/SECURITY.md` - Security guidelines
- `/DEPLOYMENT.md` - Production deployment guide
- `/CHANGELOG.md` - Version history

---

## Implementation Priority Order

1. **IMMEDIATE (Do First):**
   - Remove default credentials
   - Implement Super Admin system
   - HTTPS enforcement
   - Enhanced CSRF protection
   - First-run setup wizard

2. **HIGH (Week 1-2):**
   - Backup system with encryption
   - Enhanced audit logging
   - Password policies
   - Rate limiting

3. **MEDIUM (Week 2-3):**
   - UI unification
   - Logo implementation
   - Guard panel redesign
   - Login page redesign

4. **NORMAL (Week 3-4):**
   - Migration scripts
   - Test suite
   - Documentation
   - Code review

---

## Breaking Changes

### For Existing Installations:
1. **Database Schema Changes** - Requires migration
2. **Session Management** - All users must re-login
3. **Authentication Flow** - New Super Admin role
4. **API Endpoints** - Some endpoints restructured
5. **File Structure** - New directories and files

### Migration Path:
```bash
# 1. Backup current system
php scripts/backup_before_upgrade.php

# 2. Run migrations
php scripts/migrate.php

# 3. Run first-time setup
# Access: https://your-domain/auth/first_run_setup.php

# 4. Test all functionality
php scripts/test_runner.php
```

---

## Deliverables

### Code:
- Super Admin authentication system
- Full-system backup with encryption
- Unified UI components
- Enhanced security infrastructure
- Comprehensive audit logging
- Migration scripts
- Test suite

### Documentation:
- Updated README
- Security guidelines
- Deployment guide
- API documentation
- Migration guide
- Troubleshooting guide

### Testing:
- Unit tests
- Integration tests
- Security tests
- Performance tests
- UI/UX tests

---

## 🎓 Development Guidelines

### Code Style:
- PSR-12 for PHP
- ESLint for JavaScript
- Consistent naming conventions
- Comprehensive comments
- Type hints where possible

### Security:
- Never trust user input
- Always use prepared statements
- Validate and sanitize everything
- Use HTTPS everywhere
- Implement CSP headers
- Regular security audits

### Git Workflow:
```bash
# Feature branch naming
feature/super-admin-system
feature/backup-encryption
fix/csrf-vulnerability

# Commit message format
feat: Add super admin authentication
fix: Resolve CSRF token expiration
docs: Update security guidelines
test: Add backup system tests
```

---

## Estimated Effort

| Phase | Effort | Priority |
|-------|--------|----------|
| Phase 1: Security | 3-4 days | CRITICAL |
| Phase 2: Backup | 3-4 days | HIGH |
| Phase 3: UI | 4-5 days | MEDIUM |
| Phase 4: Audit | 2-3 days | HIGH |
| Phase 5: Migration | 2-3 days | MEDIUM |
| Phase 6: Testing | 3-4 days | HIGH |
| **Total** | **17-23 days** | - |

---

## 🚦 Go-Live Checklist

- [ ] All tests passing
- [ ] Security audit completed
- [ ] Backup system tested
- [ ] Documentation updated
- [ ] Code review approved
- [ ] Migration scripts tested
- [ ] Performance benchmarks met
- [ ] User acceptance testing done
- [ ] Rollback plan prepared
- [ ] Monitoring configured

---

**Last Updated:** November 20, 2025
**Version:** 1.0.0
**Status:** Planning Phase
