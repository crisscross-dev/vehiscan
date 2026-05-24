# Hardening Summary - 2026-03-31

## Scope Completed
- Method handling normalization for safe single-method endpoints.
- Mixed-flow endpoint hardening (method allowlists and status-code consistency).
- Safety cleanup and validation pass (lint + diagnostics).

## Key Improvements

### 0) Shared CORS Helper Consolidation
Added a reusable helper:
- includes/cors_helper.php

Refactored endpoints to use it:
- api/rfid/scan.php
- api/rfid/validate.php

What it centralizes:
- Trusted-origin CORS header application
- OPTIONS preflight handling (204)

Benefit:
- Reduced duplicated CORS code and lower maintenance risk for future CORS policy changes.

### 1) Method Guard Standardization (POST-only)
Replaced manual request-method checks with `requireRequestMethod('POST')` in:
- admin/api/approve_user_account.php
- admin/api/employee_delete.php
- admin/api/handle_profile_request.php
- homeowners/api/add_vehicle.php
- homeowners/api/delete_vehicle.php
- homeowners/api/save_vehicle.php
- homeowners/api/submit_profile_request.php
- api/homeowner_save.php

Benefit:
- Consistent 405 behavior and reduced duplicated method-check logic.

### 2) Mixed-Flow Endpoint Hardening
Applied explicit allowlists where GET/POST action flows are required.

Updated:
- admin/api/visitor_pass_form.php
  - Added method allowlist (GET, POST) with 405 + Allow header for unsupported methods.
  - Added explicit status codes on POST failures:
    - 403 for CSRF failure
    - 400 for missing/invalid input
    - 409 for unapproved/missing homeowner link
    - 500 for server exceptions

- api/rfid/bind.php
  - Added method allowlist (GET, POST) with 405 + Allow header.
  - Added explicit status codes in status handler:
    - 404 when session id is not found
    - 500 on DB error path

- api/rfid/scan.php
  - Preserved existing success behavior for scanner flows.
  - Added explicit status codes for error outcomes:
    - 409 when scanned UID is already bound during bind flow
    - 403 when access denied due to unapproved homeowner

## Endpoints Intentionally Keeping Manual Method Branching
These are intentionally not converted to `requireRequestMethod()` because they must support CORS preflight or mixed flow:
- admin/api/visitor_pass_form.php
- api/rfid/bind.php
- api/rfid/scan.php
- api/rfid/validate.php

## Validation Results
- PHP lint checks: passed on all edited files.
- Workspace diagnostics: no errors found after changes.

## Focused Live Smoke Checks (localhost)
Executed a targeted runtime probe for highest-risk flows.

RFID endpoints:
- `OPTIONS /api/rfid/scan.php` => 204
- `GET /api/rfid/scan.php` => 405
- `POST /api/rfid/scan.php` (no auth) => 403
- `OPTIONS /api/rfid/validate.php` => 204
- `GET /api/rfid/validate.php` => 405
- `POST /api/rfid/validate.php` (missing API key) => 400
- `GET /api/rfid/bind.php` (unauth) => 403
- `PUT /api/rfid/bind.php` => 403 (server-level HTML 403 page from Apache error handler; request blocked before endpoint JSON path)

Visitor pass form endpoint:
- `GET /admin/api/visitor_pass_form.php` (unauth) => 403
- `POST /admin/api/visitor_pass_form.php` (unauth) => 403
- `DELETE /admin/api/visitor_pass_form.php` (unauth) => 405

## Operational Impact
- No API contract changes for successful paths.
- Clearer and more standard HTTP response semantics for failure paths.
- Lower risk of inconsistent behavior between related endpoints.

## Additional Consistency Fixes (2026-04-11)
- Enforced super-admin-only access in `admin/api/check_pending_approvals.php` to align with the approvals workflow policy.
- Enforced super-admin-only access in `admin/api/get_pending_approval_overview.php` to keep approvals dashboard counters policy-consistent.
- Updated `assets/js/admin/realtime-updates.js` to poll approval counts only when the approvals badge is present (super-admin UI context).
- Removed duplicate approvals badge polling from `admin/admin_panel.php` to avoid redundant requests; polling is now centralized in `assets/js/admin/realtime-updates.js`.
- Previously applied: dashboard weekly stats fetch path corrected in `admin/fetch/fetch_dashboard.php`.
- Added `tests/regression_approvals_runtime.php` for automated runtime verification of role behavior across approvals endpoints.

## Recommended Next Steps
1. Add endpoint-level smoke tests for method/auth/csrf/input failure branches.
2. Expand runtime regression coverage to include additional admin-only/super-admin-only flows.
3. Document expected status-code contract in API docs for admin and RFID modules.
