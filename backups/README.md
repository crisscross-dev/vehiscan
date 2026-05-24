# Backup Directory

This directory contains all database and system backups for the Vehiscan RFID Management System.

---

## Backup Archives

### Available Backups

1. **backups_2025-11-06_084804/**
   - Initial system backup
   - Date: November 6, 2025 - 08:48:04

2. **backups_emergency_2025-11-06_221104/**
   - Emergency backup before major changes
   - Date: November 6, 2025 - 22:11:04

3. **backups_features_2025-11-06_132807/**
   - Backup before feature implementation
   - Date: November 6, 2025 - 13:28:07

4. **backups_features_v2_2025-11-07_031215/**
   - Version 2 features backup
   - Date: November 7, 2025 - 03:12:15

5. **backups_guard_fix_2025-11-08_153448/**
   - Backup before guard panel fixes
   - Date: November 8, 2025 - 15:34:48

6. **backups_multitab_2025-11-07_112408/**
   - Multi-tab functionality backup
   - Date: November 7, 2025 - 11:24:08

---

## 🔐 Backup Contents

Each backup typically contains:
- Database SQL dumps
- Configuration files
- Critical system files
- Session management files

---

## Restore Instructions

### Database Restore
```bash
# Navigate to backup directory
cd backups/[backup_name]

# Import SQL file
mysql -u root -p vehiscan_vdp < database_backup.sql
```

### File Restore
```bash
# Copy files from backup to main directory
Copy-Item -Path "backups/[backup_name]/*" -Destination "../" -Recurse
```

---

## Important Notes

- **Always create a backup before major changes**
- **Test restored backups in a development environment first**
- **Keep at least 3 most recent backups**
- **Document what each backup contains**

---

## Creating New Backups

Use the admin panel's backup feature:
1. Login to admin panel
2. Navigate to Settings/Maintenance
3. Click "Create Database Backup"
4. Backup will be created in this directory

---

**Last Updated:** November 12, 2025
