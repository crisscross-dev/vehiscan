# RFID Simulation

This directory contains all RFID simulator functionality for testing and development.

---

## Files

### simulate_rfid_scan.php
**Purpose:** Backend API for RFID scan simulation 
**Method:** POST 
**Access:** Admin only (AJAX) 
**Called by:** `assets/js/admin/admin_panel.js`

**Parameters:**
- `plate_number` - Vehicle plate number to simulate

**Response:**
```json
{
  "success": true,
  "message": "Scan successful",
  "homeowner": {
    "name": "John Doe",
    "plate_number": "ABC123",
    "vehicle_type": "SUV"
  },
  "log_id": 123
}
```

**Features:**
- Validates plate number exists in database
- Creates entry in `recent_logs` table with status='IN'
- Returns homeowner information
- Logs all simulation activity

---

### get_recent_simulations.php
**Purpose:** Fetch recent RFID simulations for display 
**Method:** GET 
**Access:** Admin only (AJAX) 
**Called by:** `assets/js/admin/admin_panel.js`

**Response:**
```json
{
  "success": true,
  "scans": [
    {
      "time": "14:30:45",
      "plate_number": "ABC123",
      "name": "John Doe",
      "vehicle_type": "SUV"
    }
  ]
}
```

**Features:**
- Returns last 10 simulations
- Includes homeowner details via JOIN
- Formatted timestamps
- Real-time updates

---

### get_recent_simulation.php
**Purpose:** Single simulation data retrieval (deprecated/alternate version) 
**Method:** GET 
**Access:** Admin only 
**Status:** Legacy file, consider consolidating

---

### generate_demo_logs.php
**Purpose:** Generate multiple demo access logs for testing 
**Method:** POST 
**Access:** Admin only 
**Use Case:** Populate system with test data

**Features:**
- Creates multiple random access logs
- Uses existing homeowner data
- Randomizes timestamps
- Useful for testing pagination/filtering

---

## Data Flow

```
User clicks "Scan RFID"
        ↓
admin_panel.js sends POST to simulate_rfid_scan.php
        ↓
simulate_rfid_scan.php:
  - Validates plate number
  - Inserts into recent_logs (status='IN')
  - Returns success + homeowner info
        ↓
admin_panel.js displays result
        ↓
Calls get_recent_simulations.php to refresh table
        ↓
Guard panel detects NEW log via fetch_logs.php
```

---

## Technical Notes

### Database Tables Used
- `homeowners` - Source of valid plate numbers
- `recent_logs` - Where simulations are stored
- `rfid_simulator` - Simulation history tracking

### Status Values
- `IN` - Vehicle entering (what simulations create)
- `OUT` - Vehicle exiting
- Status must match ENUM in database

### Session Requirements
All files require:
```php
require_once __DIR__ . '/../../includes/session_admin.php';
require_once __DIR__ . '/../../db.php';
```

---

## Troubleshooting

**Simulator not working?**
1. Check `admin/diagnostics/RFID_DIAGNOSTIC.php`
2. Verify plate number exists in homeowners table
3. Check browser console for errors
4. Verify status='IN' in database

**Logs not showing in guard panel?**
1. Verify `recent_logs` table has entries
2. Check status='IN' (not 'GRANTED')
3. Test `guard/fetch_logs.php` directly
4. Check NEW log detection in localStorage

---

## Testing

**Test URLs:**
- `_testing/admin/test_rfid_simulator.html`
- `_testing/test_rfid_flow.php`
- `_testing/test_new_log_detection.html`

---

**Last Updated:** November 12, 2025
