# Vehiscan-RFID Audit Summary - Quick Reference (May 2026)

## Executive Overview
- **Status**: Foundation solid, edge cases remain
- **Audit Date**: May 13, 2026
- **Total Issues Found**: 50+ (5 critical, 18 high, 20+ medium)
- **Previous Fixes Applied**: 8 (May 2026)
- **Remaining Work**: ~40 issues documented

---

## TOP 10 CRITICAL ISSUES TO FIX NOW

### 1. 🔴 HTTP 200 on JSON Errors (48+ endpoints)
**Impact**: CRITICAL - Clients can't distinguish success from failure
- validate_qr.php: Returns 200 for invalid/expired passes (security issue)
- change_password.php: Returns 200 for auth/validation failures
- cancel_visitor_pass.php: Returns 200 on DB errors
- ~10 more endpoints with same issue

**Fix Time**: 4-6 hours (systematic endpoint review)
**Severity**: CRITICAL

### 2. 🔴 CSRF Token Exposed in Window Scope
**Location**: guard/pages/guard_side.php (line 1850)
**Issue**: `window.csrfToken` readable by any XSS in page
**Impact**: CSRF protection bypassed on XSS
**Fix Time**: 30 minutes
**Severity**: CRITICAL

### 3. 🔴 Approval Workflow Race Conditions
**Location**: admin/api/approve_user_account.php
**Issue**: No transaction atomicity - approvals can partially succeed
**Impact**: Inconsistent approval state, duplicate notifications
**Fix Time**: 1-2 hours
**Severity**: CRITICAL

### 4. 🔴 SQL Injection in Dynamic Columns
**Location**: admin/fetch/fetch_manage.php + 3 more files
**Issue**: sort/order parameters not validated
**Impact**: SQL injection possible in sort/order
**Fix Time**: 1 hour
**Severity**: CRITICAL

### 5. 🟠 No Explicit Session Timeout
**Location**: includes/session_guard.php
**Issue**: Relies on PHP gc_maxlifetime, no server-side timeout
**Impact**: Sessions last longer than intended
**Fix Time**: 1 hour
**Severity**: HIGH

### 6. 🟠 N+1 Database Queries
**Location**: admin/fetch/fetch_manage.php, guard/fetch/fetch_vehicles.php
**Issue**: Loops through records querying sub-records
**Impact**: 100 homeowners = 101 DB queries per page load
**Fix Time**: 2-3 hours
**Severity**: HIGH (Performance)

### 7. 🟠 No Account Lockout on Failed Logins
**Location**: auth/login.php
**Issue**: No limit on login attempts
**Impact**: Brute force password guessing possible
**Fix Time**: 2 hours
**Severity**: HIGH

### 8. 🟠 Missing Rate Limiting
**Location**: Multiple endpoints
**Issue**: No rate limiting on: login, forgot-password, validate_qr, check_plate
**Impact**: Brute force, DoS on sensitive endpoints
**Fix Time**: 2 hours
**Severity**: HIGH

### 9. 🟠 Inconsistent Plate Normalization
**Location**: Multiple fetch endpoints
**Issue**: Plate searches fail when format (ABC1234 vs ABC-1234) doesn't match
**Impact**: Guard can't find vehicles, false negatives in access logs
**Fix Time**: 1-2 hours
**Severity**: HIGH

### 10. 🟠 Cross-Portal Access Edge Case
**Location**: All portal role checks
**Issue**: If admin changes user role in DB, session not invalidated
**Impact**: User retains old role in session until logout
**Fix Time**: 2-3 hours
**Severity**: HIGH

---

## ISSUE CATEGORIES AT A GLANCE

### Security (🔴 Critical: 5, 🟠 High: 8)
1. HTTP status code consistency (5 CRITICAL files)
2. CSRF token exposure (1 file)
3. Race conditions in approval (1 file)
4. SQL injection in sort/order (4 files)
5. Account lockout missing (auth/login.php)
6. Rate limiting gaps (5 endpoints)
7. Session timeout not enforced (1 file)
8. Authorization edge cases (3 files)

### Reliability (🟠 High: 5, 🟡 Medium: 8)
1. N+1 query problems (2 files)
2. Incomplete transaction handling (3 files)
3. RFID error handling incomplete (1 file)
4. Pagination max offset not enforced (4 files)
5. Directory traversal in file uploads (1 file)
6. Empty response handling inconsistent (10+ files)
7. Response format inconsistency (20 files)
8. No idempotency on RFID scans (1 file)

### Performance (🟡 Medium: 3)
1. N+1 queries (2 files)
2. Missing database indexes (multiple tables)
3. No caching strategy

### Design (🟡 Medium: 4)
1. No API versioning
2. Tight frontend/backend coupling
3. Hardcoded magic numbers
4. Session variant confusion

---

## ESTIMATED REMEDIATION EFFORT

| Phase | Priority | Issues | Files | Time | Status |
|-------|----------|--------|-------|------|--------|
| 1 | CRITICAL | 5 | Core security | 6-8h | 🔴 TODO |
| 2 | HIGH | 10 | APIs + RBAC | 8-10h | 🔴 TODO |
| 3 | MEDIUM | 15+ | Polish | 4-6h | 🔴 TODO |
| 4 | LOW | 3 | Future | 2-4h | 🟢 BACKLOG |
| **Total** | **All** | **50+** | **76 files** | **20-30h** | **🔴 INPROGRESS** |

---

## MOST CRITICAL FILE LIST

### Must Fix This Week
1. validate_qr.php (guard/api) - Add status codes
2. change_password.php (homeowners/api) - Add status codes
3. cancel_visitor_pass.php (admin/api) - Add status codes
4. guard/pages/guard_side.php - Remove window.csrfToken
5. admin/api/approve_user_account.php - Add transactions
6. admin/fetch/fetch_manage.php - Validate sort/order, fix N+1
7. includes/session_guard.php - Add session timeout
8. auth/login.php - Add account lockout

### Should Fix This Sprint
9. guard/fetch/fetch_vehicles.php - Fix N+1 queries
10. homeowners/api/add_vehicle.php - Normalize plate format
11. admin/fetch/fetch_logs.php - Normalize plate format
12. guard/fetch/fetch_logs.php - Add status codes, date range
13. api/check_plate.php - Add status codes
14. homeowners/api/get_visitor_passes.php - Add status codes
15. guard/fetch/fetch_homeowners.php - Add status codes (done May 2026)

---

## PREVIOUSLY COMPLETED FIXES (May 2026)

✅ Session fixation (auth/login.php)
✅ File upload RCE (homeowners/api/add_vehicle.php, homeowner_create/edit.php)
✅ SQL injection column whitelist (homeowner_edit.php)
✅ CSRF timing attack (admin/api/resolve_log_flag.php)
✅ Authorization checks verified (all admin/fetch files)
✅ Rate limiting on approvals (admin/api/approve_user_account.php)
✅ XSS in error messages (api/homeowner_save.php)
✅ Guard role authorization (guard/fetch/fetch_homeowners.php)

---

## QUICK REFERENCE: HTTP STATUS CODE NEEDS

### Files Needing 403 Forbidden (CSRF/Auth)
- validate_qr.php: Line 37 (invalid)
- change_password.php: Line 15 (CSRF)
- cancel_visitor_pass.php: Line 18 (CSRF)
- get_visitor_passes.php: Auth failures
- fetch_homeowners.php: Session expiry

### Files Needing 404 Not Found
- validate_qr.php: Line 37 (invalid QR)
- cancel_visitor_pass.php: Line 32 (pass not found)
- add_vehicle.php: Not applicable

### Files Needing 400 Bad Request (Validation)
- validate_qr.php: Line 51 (not valid yet)
- change_password.php: Lines 25, 30, 35 (validation)
- add_vehicle.php: Duplicate check

### Files Needing 401 Unauthorized (Auth)
- change_password.php: Line 46 (wrong password)
- get_visitor_passes.php: Session failures
- fetch_homeowners.php: Session expiry

### Files Needing 409 Conflict (Duplicate/State)
- validate_qr.php: Line 47 (pending)
- add_vehicle.php: Line 77 (duplicate plate)
- cancel_visitor_pass.php: Line 40 (state conflict)

### Files Needing 410 Gone (Expired/Deleted)
- validate_qr.php: Line 42 (rejected)
- validate_qr.php: Line 56 (expired)

### Files Needing 405 Method Not Allowed
- change_password.php: Line 9 (not POST)

### Files Needing 500 Server Error (DB/Exception)
- change_password.php: Line 59
- cancel_visitor_pass.php: Line 40
- get_visitor_passes.php: Line 48
- fetch_rfid_scan.php: Lines 90, 122, 131, 141, 161, 181, 205

---

## KEY LEARNINGS & PATTERNS

### Security Best Practices Verified ✅
- Password hashing uses password_hash/verify
- CSRF tokens use hash_equals() (post-May fix)
- File uploads validate MIME type
- Role checks implemented in core endpoints
- PDO with prepared statements used

### Security Anti-Patterns Found ❌
- CSRF token in window scope (global variable exposure)
- HTTP 200 on JSON errors (client confusion)
- No race condition protection (multi-thread issues)
- Sort/order parameters not validated (SQL injection)
- Session timeout not enforced (long-lived sessions)
- Account lockout not implemented (brute force)

### Performance Anti-Patterns Found ❌
- N+1 queries in admin/guard panels (100+ queries per page)
- No OFFSET limit (pagination DoS)
- No database indexes on filtered columns
- No caching layer (repeated calculations)

---

## TESTING CHECKLIST

- [ ] Invalid QR code returns 404, not 200
- [ ] Expired pass returns 410, not 200
- [ ] Wrong password returns 401, not 200
- [ ] Invalid CSRF returns 403, not 200
- [ ] Concurrent approvals don't duplicate state
- [ ] Plate normalization works across search endpoints
- [ ] Session expires after 30 minutes of inactivity
- [ ] Login fails after 5 failed attempts
- [ ] Sort/order parameters can't inject SQL
- [ ] Role changes invalidate session

---

## FILES BY RISK LEVEL

### 🔴 CRITICAL RISK (6)
1. validate_qr.php
2. change_password.php
3. cancel_visitor_pass.php
4. guard/pages/guard_side.php
5. approve_user_account.php
6. auth/login.php

### 🟠 HIGH RISK (12)
1. fetch_manage.php
2. fetch_vehicles.php
3. session_guard.php
4. forgot-password.php
5. get_vehicle_activity.php
6. fetch_homeowners.php
7. add_vehicle.php
8. fetch_rfid_scan.php
9. check_plate.php
10. homeowner_edit.php
11. fetch_logs.php
12. admin_panel.php

### 🟡 MEDIUM RISK (18)
[Multiple fetch/API files with response format, pagination, or caching issues]

---

**Report Generated**: May 13, 2026
**Next Review**: After Phase 1 critical fixes (estimated 8 hours)
**Estimated Total Remediation**: 20-30 hours across all phases
