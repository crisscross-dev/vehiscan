# Admin Utilities

This directory contains utility scripts for database operations, reports, and maintenance tasks.

---

## Files

### backup_database.php
**Purpose:** Create complete database backup 
**Method:** POST 
**Access:** Admin only (AJAX) 
**Called by:** `assets/js/admin/admin_panel.js` (Backup button)

**Response:**
```json
{
  "success": true,
  "message": "Backup created successfully",
  "filename": "backup_vehiscan_vdp_2025-11-12_083045.sql",
  "size": "245.3 KB"
}
```

**Features:**
- Exports all database tables to SQL file
- Saves to `backups/` directory
- Includes timestamps in filename
- Shows file size on completion
- Uses mysqldump command

**Backup Location:**
```
/backups/backup_vehiscan_vdp_YYYY-MM-DD_HHMMSS.sql
```

**Database Configuration:**
- Host: localhost
- Database: vehiscan_vdp
- Credentials: From `db.php`

---

### generate_report.php
**Purpose:** Generate various system reports 
**Method:** GET 
**Access:** Admin only 
**Parameters:**
- `type` - Report type (daily, weekly, monthly)
- `format` - Output format (html, pdf, csv)
- `from` - Start date (YYYY-MM-DD)
- `to` - End date (YYYY-MM-DD)

**Report Types:**

**Daily Report:**
- Access logs for specific date
- Entry/exit counts
- Most active times
- Vehicle type breakdown

**Weekly Report:**
- 7-day activity summary
- Daily comparisons
- Peak usage analysis
- Homeowner activity

**Monthly Report:**
- 30-day overview
- Trends and patterns
- Total transactions
- System usage statistics

**Output Formats:**
- **HTML** - Browser display with charts
- **PDF** - Downloadable document (future)
- **CSV** - Excel-compatible export

---

## Usage

### Database Backup
**From Admin Panel:**
1. Click " Database Backup" in sidebar
2. Confirm backup creation
3. Wait for completion
4. Check `backups/` directory

**Manual Execution:**
```bash
# Via browser (use your configured APP_URL from .env)
{APP_URL}/admin/utilities/backup_database.php

# Via command line
php backup_database.php
```

---

### Report Generation
**From Admin Panel:**
```
{APP_URL}/admin/utilities/generate_report.php?type=daily&format=html&from=2025-11-12&to=2025-11-12
```

**Parameters:**
```
type=daily Report timeframe
format=html Output format
from=2025-11-12 Start date
to=2025-11-12 End date
```

---

## Technical Details

### Backup Process
```php
1. Validate admin session
2. Get database credentials from db.php
3. Construct mysqldump command
4. Execute shell command
5. Save to backups/ directory
6. Return file info as JSON
```

### Backup Command
```bash
mysqldump -h localhost -u root -p[password] vehiscan_vdp > backup_file.sql
```

### Security Considerations
- Admin authentication required
- CSRF protection (for POST requests)
- File path validation
- Command injection prevention

---

## Database Tables Backed Up

All tables in `vehiscan_vdp`:
- `users` - Admin/guard accounts
- `homeowners` - Resident information
- `recent_logs` - Access logs
- `visitor_passes` - Visitor records
- `audit_logs` - System audit trail
- `rfid_simulator` - Simulation history

---

## Troubleshooting

### Backup Fails
**Possible Issues:**
- mysqldump not in system PATH
- Incorrect database credentials
- Write permissions on backups/ directory
- Disk space full

**Solutions:**
```bash
# Check mysqldump availability
mysqldump --version

# Verify backups directory exists
mkdir backups

# Check permissions
chmod 755 backups/
```

### Report Shows No Data
- Verify date range has activity
- Check database connection
- Ensure recent_logs table has entries
- Validate date format (YYYY-MM-DD)

---

## Directory Structure

```
admin/utilities/
├── backup_database.php # Database backup
├── generate_report.php # Report generation
└── README.md # This file
```

---

## Future Enhancements

**Backup Features:**
- [ ] Automatic scheduled backups
- [ ] Backup rotation (keep last N backups)
- [ ] Cloud storage integration
- [ ] Incremental backups
- [ ] Restore functionality

**Report Features:**
- [ ] PDF export
- [ ] Email delivery
- [ ] Scheduled reports
- [ ] Custom date ranges
- [ ] Advanced filtering
- [ ] Chart visualizations
- [ ] Export to Excel

---

## Important Notes

**Backup Storage:**
- Backups can grow large over time
- Monitor disk space regularly
- Implement backup rotation
- Consider offsite storage

**Security:**
- Backup files contain sensitive data
- Secure backups/ directory
- Add to .gitignore
- Restrict access in production

**Performance:**
- Large databases take longer to backup
- Run during off-peak hours
- Consider incremental backups
- Monitor server resources

---

**Last Updated:** November 12, 2025
