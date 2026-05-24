# Staging TLS & Camera E2E Test Plan

This document explains how to prepare a staging environment with TLS and run camera/QR E2E checks for html5-qrcode flows.

## Goals
- Verify camera QR scanning works under secure context (HTTPS).
- Confirm guard-side scanner, homeowner QR flows, and html5-qrcode operate correctly.

## Requirements
- Staging host with a valid TLS certificate (Let's Encrypt, commercial cert, or self-signed/mkcert for local).
- `APP_URL` set to the staging HTTPS URL in `.env`.
- `SESSION_SECURE=true` in `.env`.
- Browser that supports WebRTC camera APIs (Chrome, Edge, Firefox on secure origins).

## Quick setups

### Option A — Real staging host (recommended)
1. Provision a staging domain: `staging.your-domain.example`.
2. Point DNS to your staging server.
3. Install TLS cert (Let's Encrypt recommended) and configure your webserver (Apache/Nginx) to serve the app over HTTPS.
4. Copy `.env.hosting.example` to `.env` and update:
   - `APP_URL=https://staging.your-domain.example`
   - `SESSION_SECURE=true`
   - DB credentials as appropriate
5. Restart PHP/Apache and test site load.

### Option B — Local HTTPS with mkcert (dev-friendly)
1. Install `mkcert` for your OS and create a local CA.
2. Generate certs for `localhost` or a chosen local domain (e.g., `vehiscan.local`).
3. Configure Apache to use the cert and host the project at `https://vehiscan.local`.
4. Add `vehiscan.local` to your hosts file and copy `.env.hosting.example` to `.env` with `APP_URL=https://vehiscan.local`.

### Option C — Tunnel with ngrok (fast)
1. Start your local server on port 80/443 and run `ngrok http 80` or `ngrok http 443`.
2. Use the generated `https://*.ngrok.io` URL as `APP_URL` in `.env` and set `SESSION_SECURE=true`.
3. Note: ngrok imposes rate limits and is temporary — good for quick validation only.

## E2E Camera Test Steps (manual)
1. Open the staging URL in a browser that supports camera access over HTTPS.
2. Login as a guard or admin with scanner access.
3. Navigate to the guard scanner page (`guard/pages/guard_side.php`).
4. Grant camera permission when prompted.
5. Point the camera at a test QR code printed or displayed on another device.
6. Confirm scan completes and the expected visitor/vehicle lookup appears.
7. Test 'rescan', 'cancel', and 'open pass' flows.
8. Repeat on mobile (Chrome on Android, Safari on iOS via a real domain) — iOS requires Safari and real TLS cert.

## Automated smoke (optional)
- Browser-based automation for camera is tricky; prefer manual tests.
- For CI, validate html5-qrcode library loads over HTTPS and that no CSP/script blocking occurs:
  - Run automated unit/regression tests: `php tests/run_hardening_checks.php`
  - Verify network console for `html5-qrcode` loading from local assets (no 4xx/5xx).

## Troubleshooting
- If camera not accessible: confirm site served over HTTPS and `getUserMedia` is allowed.
- If QR library fails to load: confirm CSP allows `script-src 'self'` (no `'unsafe-inline'`) and assets/js/html5-qrcode is present.
- If scanned data not processed: check console for JS errors, ensure delegate `data-action` handlers are present and referenced JS files are loaded.

## Acceptance Criteria
- Camera permission prompt appears and allows camera access.
- QR scans successfully and triggers backend lookup without CORS/CSP failures.
- All related hardening tests continue to pass after deployment.

---

Once you confirm staging TLS is working, run the manual E2E steps and report any failures to continue remediation.