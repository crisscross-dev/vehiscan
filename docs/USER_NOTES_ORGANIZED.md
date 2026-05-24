# System Improvements - User Notes (Organized & Clarified)

**Date Compiled:** April 19, 2026  
**Source:** User browser notes and feedback  
**Status:** Pending implementation verification

---

## ORGANIZED FEATURE REQUESTS & BUG FIXES

### **CATEGORY 1: DATA FORMATTING & DISPLAY CONSISTENCY**

| # | Item | Description | Priority | Status |
|---|------|-------------|----------|--------|
| 1.1 | Sentence Case Formatting | All names, labels, and text across entire system should use sentence case (not ALL CAPS, not lowercase) - applies to data tables, admin interface, guard interface, homeowner portal | HIGH | ⏳ PENDING |
| 1.2 | 12-Hour Time Format (AM/PM) | All timestamps in data tables and system-wide should display in 12-hour format with AM/PM (e.g., "2:30 PM" not "14:30") | HIGH | ⏳ PENDING |
| 1.3 | Audit Logs Formatting | Audit log entries (old/new changes) should use sentence case and proper formatting | MEDIUM | ⏳ PENDING |

---

### **CATEGORY 2: AUTHENTICATION & ACCESS CONTROL**

| # | Item | Description | Priority | Status |
|---|------|-------------|----------|--------|
| 2.1 | Email-Only Login | Remove username login option; use email address as the sole login credential across all roles | HIGH | ⏳ PENDING |
| 2.2 | Unique Contact Number Enforcement | System should enforce one contact number per user/homeowner; no duplicate contact numbers allowed | MEDIUM | ⏳ PENDING |
| 2.3 | Admin Delete Restrictions | Remove all delete buttons from admin interface; admins cannot delete records from "Manage Records" | HIGH | ⏳ PENDING |

---

### **CATEGORY 3: VEHICLE MANAGEMENT**

| # | Item | Description | Priority | Status |
|---|------|-------------|----------|--------|
| 3.1 | Vehicle Photo Required | During homeowner/vehicle registration, vehicle photo upload should be mandatory (not optional) | HIGH | ⏳ PENDING |
| 3.2 | Plate Number Duplication Check | During registration flow, validate plate number against existing plates in system before allowing registration to proceed to next step | HIGH | ⏳ PENDING |
| 3.3 | Multiple Vehicles Visibility (Homeowner) | Add button/control on homeowner portal allowing them to view all their registered vehicles and see if other users share access to same vehicle | MEDIUM | ⏳ PENDING |
| 3.4 | Multiple Vehicles in Guard Entry Details | On guard side entry details panel, add scrollable/switchable view to see if homeowner has multiple vehicles attached to their profile | MEDIUM | ⏳ PENDING |

---

### **CATEGORY 4: HOMEOWNER REGISTRATION & FORMS**

| # | Item | Description | Priority | Status |
|---|------|-------------|----------|--------|
| 4.1 | Fix "Use Camera" in Registration | [NEEDS CLARIFICATION] - Fix broken camera functionality in homeowner registration form | HIGH | ⏳ PENDING |

---

### **CATEGORY 5: VISITOR PASS MANAGEMENT**

| # | Item | Description | Priority | Status |
|---|------|-------------|----------|--------|
| 5.1 | Expired Pass Indication | Add visual indicator on visitor passes showing they are expired (status badge, strikethrough, red highlight, etc.) | MEDIUM | ⏳ PENDING |
| 5.2 | Pass Approval Notification | When admin approves a visitor pass, send notification to requesting homeowner (on homeowner portal and optionally via email) | MEDIUM | ⏳ PENDING |
| 5.3 | QR Code Scan Tracking & Notification | When visitor pass QR code is scanned: (a) Notify homeowner via email, (b) Record scan in database for audit/tracking, (c) Track usage status (scanned/used/tagged) | HIGH | ⏳ PENDING |
| 5.4 | Visitor Pass Logs/Access Logs Integration | Create either: (a) New dedicated "Visitor Logs" page showing all QR scans, OR (b) Add visitor pass QR scans to existing access logs page for unified tracking | HIGH | ⏳ PENDING |

---

### **CATEGORY 6: AUDIT & ADMIN TRANSPARENCY**

| # | Item | Description | Priority | Status |
|---|------|-------------|----------|--------|
| 6.1 | Audit Logs Enhancement | Add ability to track who performed each edit/action (currently missing); add who performed the action | HIGH | ⏳ PENDING |
| 6.2 | Audit Logs Date Filter | Add date range filter (from/to) for audit logs to filter by when changes occurred | HIGH | ⏳ PENDING |
| 6.3 | Account Approval Tracking | In admin panel account approvals and view profile, display: (a) When account was approved (timestamp), (b) Which admin approved it | MEDIUM | ⏳ PENDING |

---

### **CATEGORY 7: HOMEOWNER PORTAL NOTIFICATIONS**

| # | Item | Description | Priority | Status |
|---|------|-------------|----------|--------|
| 7.1 | Multi-Vehicle Visibility | Add button/feature for homeowner to see: (a) All their own vehicles, (b) Other users who have access/share same vehicle (for security/clarification) | MEDIUM | ⏳ PENDING |

---

## SUMMARY BY STATUS

### ✅ **FIXED/IMPLEMENTED**
*(None yet - awaiting verification)*

### ⏳ **PENDING IMPLEMENTATION**
- All 16 items above

### ❌ **CANNOT FIX / CLARIFICATION NEEDED**
| Item | Reason |
|------|--------|
| 4.1 - Fix "Use Camera" | **NEEDS USER CLARIFICATION** - What specific camera issue exists in registration? |

---

## NEXT STEPS

1. **Clarify item 4.1** - Describe the camera issue in registration
2. **Prioritize items** - Which items are most critical to implement first?
3. **Assign/Start implementation** - Begin with HIGH priority items
4. **Track completion** - Mark each item as FIXED when implemented and tested

---

**Total Items:** 16  
**HIGH Priority:** 6  
**MEDIUM Priority:** 7  
**NEEDS CLARIFICATION:** 1
