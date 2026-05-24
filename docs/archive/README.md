# Testing & Development Utilities

This folder contains test files and development utilities moved from the root directory to keep the project organized.

## Test Files

### Feature Testing
- **test_features.html** - Comprehensive feature testing dashboard (Access Logs, Visitor Passes)
- **test_delete_export.html** - Test delete and export functionality
- **test_new_log_detection.html** - Test NEW log detection with visual feedback
- **test_guard_logs.html** - Guard logs API tester
- **test_rfid_flow.php** - RFID simulator flow validator
- **test_multitab.php** - Multi-tab session testing

### Database Utilities
- **check_tables.php** - Database table structure checker
- **check_logs_table.php** - Recent logs table inspector
- **check_recent_logs.php** - Log entry viewer
- **analyze_database.php** - Complete database structure analyzer

### Setup Scripts
- **setup_admin_features.php** - Admin features setup script

## Usage

These files can be accessed directly via:
```
http://localhost/Vehiscan-RFID/_testing/[filename]
```

For example:
```
http://localhost/Vehiscan-RFID/_testing/test_features.html
http://localhost/Vehiscan-RFID/_testing/analyze_database.php
```

## Note

These files are for development and testing purposes only. They should not be deployed to production environments.

To run database analysis:
```bash
php analyze_database.php
```

To check table structure:
```bash
php check_tables.php
```

## Security

 **Important:** If deploying to production, ensure this directory is blocked in your web server configuration or remove it entirely.

Add to `.htaccess` in production:
```apache
# Block access to testing directory
<Directory "_testing">
    Require all denied
</Directory>
```
