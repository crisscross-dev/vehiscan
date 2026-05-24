Summary of `.style` and inline `style=` hotspots

Scan summary:
- ~200 `element.style` usages found (grep capped at 200). Many are in app JS, templates, and vendor libs.
- ~200 inline HTML `style=` attributes found across PHP/HTML templates and injected fragments.

High-priority app-level files to fix (high confidence - safe conversions):
- assets/js/admin/admin_panel.js — many `style.display`, `style.transform`, `style.left/top/width`, `style.color` occurrences.
- assets/js/admin/approvals-page.js — `style.color`, `row.style.display`.
- assets/js/auth/admin-create.js — `superAdminFields.style.display`.
- homeowners/js/homeowner.js — inline `style=` in fragment HTML and `userDropdown.style.*`.
- guard/js/guard_side.js — dropdown positioning and card min-height, transforms.
- assets/js/login.js — svg/button innerHTML with inline `style` attributes.
- assets/js/session-timeout.js — inline modal paragraph/styles (some already converted).
- assets/js/table-enhancer.js — row display toggles (many converted already).
- admin/fetch/*.php (fetch_dashboard.php, fetch_rfid.php, fetch_profile_requests.php) — inline `style` on wrappers and canvas elements.
- homeowner_registration.php and other PHP templates — progress bar, preview divs, many `style="display: ..."` usages.
- admin/components/*.php — header/sidebar image fallbacks using `onerror` attributes that set `style.display`.

Vendor libraries (recommend special handling):
- assets/js/libs/html5-qrcode.min.js — heavy programmatic `element.style.*` usage (canvas/video UI). Class: VENDOR.
- assets/js/libs/alpine.min.js and alpine.csp.min.js — runtime style.setProperty usage inside library. Class: VENDOR.
- assets/js/libs/sweetalert2.all.min.js — writes and strips inline style attributes; CSP-aware usage possible but treat as VENDOR.
- assets/js/libs/chart.umd.min.js — canvas drawing libraries (mostly internal), many `style` snippets in generated HTML. Class: VENDOR.
- other libs: dataTables, html5-qrcode, html5-qrcode styles — treat as VENDOR.

Classification guidance (how to prioritize fixes):
- Class A (app-level, easy): replace `element.style.display = 'none'|'block'`, `.style.color` for status badges, `.style.opacity/transform` toggles with class toggles and CSS helper classes (e.g., `.vs-hidden`, `.vs-visible`, `.vs-transition`). These are safe and non-breaking.
- Class B (positional/dynamic pixels): `userDropdown.style.left/top/width` and camera/video sizing. These require careful replacement: either compute and set CSS variables on a parent/class (prefer) or keep minimal inline styles but ensure fragment `<style>` tags receive nonce (we already added nonce normalizer). Decide per-case.
- Class C (vendor): leave vendor libs as exceptions or replace with CSP-friendly forks or wrapper components that render CSS via external stylesheets or nonce-tagged fragments. Options:
  - Ship patched vendor (minified) with class-based changes (costly).
  - Load vendor via subresource that the CSP allows (hosted script/style) or add per-vendor nonce/hash exceptions.
  - Replace vendor with alternatives that are CSP-friendly.

Recommended next steps (I can run these):
1. Auto-fix Class A items across app JS files (replace `style.display`/`style.color`/`style.opacity` with class toggles and CSS helpers). — Automated, high-confidence.
2. For Class B items, open PRs per-file to convert to CSS-variable approach or add well-scoped inline-style allowances in fragment nonces if necessary. — Manual review required per-file.
3. Create a vendor handling plan: patch vendors where feasible, or list vendor files to accept as exceptions and document rationale. — needs decision.
4. After fixes, run authenticated smoke tests (requires local Apache/XAMPP or staging URL). I can run tests if you start Apache or provide a reachable URL.

Notes:
- I already converted many Class A occurrences (admin table, session-timeout, realtime-updates, dropdown fallbacks). The scan confirms remaining hotspots concentrated in `admin_panel.js`, `homeowner.js`, `guard_side.js`, plus vendor libs.
- If you want, I can start auto-fixing Class A files now and open commits for review.

What do you want me to do next?
- Option 1: Auto-fix all Class A hotspots now (recommended).
- Option 2: Produce a per-file PR plan (diffs) and proceed after your approval.
- Option 3: Pause and run hosted smoke tests (please start Apache or provide staging URL).