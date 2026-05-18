# API Endpoints Remediation Checklist

## 🔴 CRITICAL (Fix Before Deployment)

### Critical Issue 1: Database Variable Bug
- [ ] **File**: `homeowners/api/change_password.php`
- [ ] **Lines**: 41, 46
- [ ] **Change**: Replace `$conn` with `$pdo` (2 occurrences)
- [ ] **Test**: Change password functionality works
- [ ] **Status**: ⏳ TODO

### Critical Issue 2: Unsafe CSRF Comparison (Timing Attack)
- [ ] **File**: `admin/api/resolve_log_flag.php`
- [ ] **Line**: 27
- [ ] **Change**: Use `hash_equals()` for constant-time comparison
- [ ] **Test**: Flag resolution still works, security improved
- [ ] **Status**: ⏳ TODO

### Critical Issue 3: Missing Authentication
- [ ] **File**: `api/homeowners_get.php`
- [ ] **Lines**: 1-30
- [ ] **Issue**: No auth check, returns sensitive data
- [ ] **Options**: 
  - [ ] A) Add auth check for admin role
  - [ ] B) Document as intentionally public and add rate limiting
- [ ] **Status**: ⏳ TODO

### Critical Issue 4: Guard Auth Check Missing
- [ ] **File**: `guard/fetch/fetch_homeowners.php`
- [ ] **Lines**: 5-8
- [ ] **Change**: Add guard role verification
- [ ] **Test**: Only guards can access this data
- [ ] **Status**: ⏳ TODO

### Critical Issue 5: Guard Auth Check Missing
- [ ] **File**: `guard/fetch/fetch_vehicles.php`
- [ ] **Lines**: 1-5
- [ ] **Change**: Add guard/admin role verification
- [ ] **Test**: Only guards can access this data
- [ ] **Status**: ⏳ TODO

---

## 🟠 HIGH (Fix Within 1 Week)

### High Issue Set 1: Admin Fetch Files - Content-Type Mismatch
13 files claim `Content-Type: application/json` but return HTML fragments.

**Decision Required**: Choose ONE approach:
- [ ] **Option A**: Change to `Content-Type: text/html` (for HTML responses)
- [ ] **Option B**: Refactor to return JSON instead of HTML
- [ ] **Option C**: Use different endpoint pattern (e.g., `/api/fetch/` for JSON, `/fetch/` for HTML)

**Files to Fix**:
- [ ] admin/fetch/fetch_approvals.php
- [ ] admin/fetch/fetch_audit.php
- [ ] admin/fetch/fetch_audit_enhanced.php
- [ ] admin/fetch/fetch_dashboard.php
- [ ] admin/fetch/fetch_employees.php
- [ ] admin/fetch/fetch_logs.php
- [ ] admin/fetch/fetch_manage.php
- [ ] admin/fetch/fetch_profile_requests.php
- [ ] admin/fetch/fetch_rfid.php
- [ ] admin/fetch/fetch_simulator.php
- [ ] admin/fetch/fetch_visitors.php
- [ ] admin/fetch/fetch_visitor_logs.php
- [ ] admin/fetch/fetch_visitor_passes.php

### High Issue Set 2: Standardize Error Response Format
**Create unified format**:
```json
{
  "success": boolean,
  "message": "User-friendly message",
  "error": "Technical error code (optional)",
  "timestamp": "ISO8601 timestamp"
}
```

**Files to Update** (currently use 'error' field):
- [ ] admin/api/check_new_logs.php
- [ ] admin/api/get_homeowner_stats.php
- [ ] admin/api/get_pending_accounts.php
- [ ] admin/api/get_pending_approval_overview.php
- [ ] admin/api/get_pending_passes.php
- [ ] admin/api/get_visitor_activity.php
- [ ] guard/api/get_ui_preferences.php
- [ ] guard/fetch/fetch_visitor_scan_logs.php (partially)
- [ ] guard/fetch/fetch_visitors.php
- [ ] homeowners/api/get_my_activity.php

### High Issue Set 3: Inconsistent HTTP Status Codes
Add explicit `http_response_code(200)` for successful responses:

- [ ] admin/api/cancel_visitor_pass.php
- [ ] admin/api/check_new_logs.php
- [ ] admin/api/check_pending_approvals.php
- [ ] admin/api/get_homeowner_stats.php
- [ ] admin/api/get_pending_accounts.php

### High Issue Set 4: Content-Type Header Ordering
Move `header('Content-Type: application/json')` BEFORE auth checks:

- [ ] admin/fetch/delete_access_log.php - Line 15 (move to line 7)
- [ ] admin/api/get_visitor_pass_logs.php - Line 19 (move earlier)

### High Issue Set 5: Duplicate header() Calls
Remove duplicate Content-Type declarations:

- [ ] admin/api/approve_visitor_pass.php - Lines 8, 19 (remove one)
- [ ] admin/api/cancel_visitor_pass.php - Lines 8, 15 (remove one)
- [ ] admin/api/create_visitor_pass.php - Lines 8, 16 (remove one)
- [ ] admin/api/get_weekly_stats.php - Lines 14, 20 (remove one)

### High Issue 6: Missing Error Handling
- [ ] admin/fetch/fetch_homeowners.php
  - [ ] Add try-catch block for database operations
  - [ ] Wrap main query in try-catch
  - [ ] Return proper error responses

---

## 🟡 MEDIUM (Fix Within 2 Weeks)

### Medium Issue Set 1: Missing Input Validation
Add proper validation to these endpoints:

- [ ] api/check_plate.php - Validate plate format better
- [ ] guard/fetch/fetch_homeowners.php - Sanitize plate filter
- [ ] guard/fetch/fetch_visitors.php - Add input validation

### Medium Issue Set 2: Error Message Consistency
Review error messages for consistency and security:

- [ ] Ensure no sensitive info in error messages (no DB structure exposed)
- [ ] Audit all error_log() calls for sensitive data
- [ ] Add context IDs for debugging without exposing implementation

### Medium Issue Set 3: Success Status Consistency
Add explicit success indicators:

- [ ] API files should always return `"success": true/false`
- [ ] Fetch files should return status indicator
- [ ] CSV exports can use headers only

---

## 🟢 LOW (Nice to Have)

### Low Issue Set 1: Documentation
- [ ] Add PHPDoc blocks to all endpoint files
- [ ] Document request/response formats
- [ ] Add parameter descriptions
- [ ] Add example requests/responses

### Low Issue Set 2: Code Style
- [ ] Standardize exit() vs return patterns
- [ ] Consistent indentation (4 spaces)
- [ ] Consistent error logging format

### Low Issue Set 3: Monitoring
- [ ] Add request ID logging
- [ ] Add performance metrics
- [ ] Add security event logging

---

## Quick Fix Scripts

### Fix 1: Database Variable Bug (5 minutes)
```bash
# Edit homeowners/api/change_password.php
# Line 41: Change $conn->prepare to $pdo->prepare
# Line 46: Change $conn->prepare to $pdo->prepare
```

### Fix 2: CSRF Comparison (5 minutes)
```bash
# Edit admin/api/resolve_log_flag.php
# Line 27: Replace string comparison with hash_equals()
```

### Fix 3: Add Guard Auth Check (5 minutes each)
```bash
# Edit guard/fetch/fetch_homeowners.php
# Add before line 7:
# if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
#     http_response_code(401);
#     exit;
# }

# Edit guard/fetch/fetch_vehicles.php
# Add after line 2:
# if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'guard') {
#     http_response_code(401);
#     exit;
# }
```

### Fix 4: Add Auth to homeowners_get.php (10 minutes)
```bash
# Edit api/homeowners_get.php
# Add after session_start():
# require_once __DIR__ . '/../includes/session_admin_unified.php';
# if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
#     http_response_code(403);
#     exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
# }
```

---

## Testing Checklist

After each fix, test:

- [ ] **Unit Tests**: Individual endpoint still functions
- [ ] **Integration Tests**: Works with calling code
- [ ] **Security Tests**: Auth checks working
- [ ] **Error Tests**: Error responses proper format
- [ ] **Performance Tests**: No slowdown from fixes

---

## Deployment Checklist

Before going to production:

- [ ] All CRITICAL issues fixed (5/5)
- [ ] All HIGH issues fixed (6/6 issue sets)
- [ ] API documentation updated
- [ ] Error responses standardized
- [ ] Security review completed
- [ ] Load testing passed
- [ ] Error logging verified
- [ ] Backup taken
- [ ] Rollback plan ready

---

## Progress Tracking

| Priority | Total | Fixed | % Complete | Deadline |
|----------|-------|-------|-----------|----------|
| CRITICAL | 5 | 0 | 0% | May 11, 2026 |
| HIGH | 13 | 0 | 0% | May 15, 2026 |
| MEDIUM | 8 | 0 | 0% | May 22, 2026 |
| LOW | 5 | 0 | 0% | May 29, 2026 |
| **TOTAL** | **31** | **0** | **0%** | |

---

## Communication Template

### For Development Team
```
Subject: API Endpoints Security Fixes Required - 5 Critical Issues

Following comprehensive security audit of all 76 API endpoints, we have identified:
- 5 CRITICAL issues requiring immediate fix
- 13 HIGH priority issues for this week  
- 8 MEDIUM priority improvements for next 2 weeks

All issues documented in: docs/API_ENDPOINTS_VALIDATION_REPORT.md
Quick fixes available for CRITICAL items (estimated 30 minutes total)

CRITICAL fixes needed before next deployment.
```

### For QA/Testing
```
Subject: API Testing Required - Security & Error Handling Updates

After fixes are applied, please verify:
1. All error responses follow standard format
2. Auth checks prevent unauthorized access
3. Database connections working properly
4. CSV exports still download correctly
5. Visitor endpoints still public with rate limiting

Test cases in: docs/API_ENDPOINTS_VALIDATION_REPORT.md (Part 6)
```

---

## Questions?

For details on any finding:
1. See **docs/API_ENDPOINTS_VALIDATION_REPORT.md** (comprehensive report)
2. Check issue file directly for line numbers
3. Review code examples in report Part 7

---

**Last Updated**: May 10, 2026  
**Next Review**: June 10, 2026  
**Status**: Audit Complete - Awaiting Remediation
