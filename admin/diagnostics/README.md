# Admin Diagnostics

This directory contains diagnostic and debugging tools for the Vehiscan RFID system.

---

## Files

### RFID_DIAGNOSTIC.php
**Purpose:** Comprehensive RFID simulator diagnostic tool 
**Access:** Direct browser access 
**URL:** `{APP_URL}/admin/diagnostics/RFID_DIAGNOSTIC.php` (configure APP_URL in .env)

**Checks:**
- Database connection status
- `recent_logs` table structure
- `homeowners` table records 
- RFID scan simulation test
- Session status
- Recent scans display

**Usage:** Run this when troubleshooting RFID simulator issues

---

### debug_image_paths.php
**Purpose:** Debug homeowner image paths and verify uploads 
**Access:** Requires admin session 
**Features:**
- Lists all homeowners with image paths
- Checks if image files exist on filesystem
- Shows full file paths and URLs
- Identifies missing images

**Usage:** Troubleshoot homeowner image upload/display issues

---

### image_diagnostic.php
**Purpose:** Visual diagnostic for homeowner images 
**Access:** Requires admin session 
**Features:**
- Displays all homeowner images with thumbnails
- Shows database paths vs actual file locations
- Highlights missing or broken images
- Provides image upload statistics

**Usage:** Visual verification of homeowner profile images

---

## Security

All diagnostic tools require:
- Admin session (`session_admin.php`)
- Database connection (`db.php`)
- Admin role verification

** Production Warning:** 
These tools should be restricted or removed in production environments for security.

---

## Adding New Diagnostics

When creating new diagnostic tools:

1. Place in this directory
2. Require proper session and authentication
3. Follow naming convention: `[feature]_diagnostic.php`
4. Update this README
5. Add proper error handling

---

**Last Updated:** November 12, 2025
