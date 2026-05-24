Deployment checklist — Production readiness for VehiScan

1) TLS / Domain
- Point your domain to the host and provision TLS (Let's Encrypt or managed cert).
- Confirm site loads at `https://your-domain.example.com`.
- If behind a load balancer / proxy, set `TRUSTED_PROXIES` and ensure `X-Forwarded-Proto` is passed.

2) Environment (.env)
- Create `.env` from `.env.hosting.example` and set values:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_URL=https://your-domain.example.com`
  - `SESSION_SECURE=true`
  - `DB_*` credentials
  - `RFID_API_KEY`, `RFID_READER_ID` (if using middleware)
- Keep `.env` out of version control.

3) Sessions & Cookies
- Ensure `SESSION_SECURE=true` in `.env` so cookies use Secure flag.
- Confirm `SESSION_HTTPONLY=true`.

4) Content Security Policy / Localized libs
- App sends CSP from `includes/security_headers.php` (script-src 'self').
- Verify all third-party scripts/styles/fonts are vendored under `assets/js/libs` or explicitly allowed in CSP if intentionally external.
- Already vendored: `html5-qrcode`, `sweetalert2`, Chart.js, Alpine, DataTables. Replace other CDN references found in backups/docs only.

5) CORS
- Use `includes/cors_helper.php` to allow any trusted origins. Add production origins to allowed list if cross-origin clients exist.

6) Trusted proxies & HTTPS redirects
- If using proxies, set `TRUSTED_PROXIES` env to proxy IPs and verify `includes/security_headers.php` recognizes HTTPS via `X-Forwarded-Proto`.
- Confirm the app redirects HTTP → HTTPS in non-local environments.

7) File storage & permissions
- Ensure `uploads/` directory is writable by the web server user and not web-browsable (restrict listing via .htaccess).
- Keep backups and exports in `backups/` with limited access.

8) Database & Migrations
- Run migrations: `php run_migrations.php` (or follow docs/run_migrations steps).
- Create a DB user with limited privileges (no DROP unless necessary).

9) Background services & RFID middleware
- Set `APP_URL` env for `middleware/rfid_reader.js` and other middleware.
- Configure middleware to run as a persistent service (systemd) on the host; ensure it can reach `APP_URL` and include any API key.

10) Logging & monitoring
- Enable error logging (already toggled via `config.php` when `APP_ENV=production`).
- Configure log rotation and monitoring/alerts.

11) Anti-passback & rate limits
- Anti-passback enforced server-side: 60s cooldown (`api/rfid/scan.php`). Adjust if required for your workflow.

12) Final functional checks (must test on staging HTTPS domain)
- QR flow: camera permission grant/deny, scan success/failure, rescan retry.
- Manual log entry: create entry, verify in logs and DB.
- Visitor request: create and approve flows.
- Admin approvals: single and bulk operations.
- Session expiry and login flow.

13) Optional hardening
- Remove `unsafe-inline` from CSP after migrating remaining inline handlers.
- Migrate inline `on*` attributes to JS-bound listeners (priority: pages that handle security-sensitive actions).
- Lock down `.env` file via server-level denies.

14) Post-deploy tasks
- Run `php tests/run_hardening_checks.php` on the server (if PHP CLI available and DB configured) to verify runtime checks.
- Schedule regular DB backups and secure storage of backups.

If you want, I can:
- Migrate the remaining inline `onclick`/`on*` handlers across the repo (I already converted guard and key admin approvals handlers). I can produce a prioritized list and patch them incrementally.
- Create a small deployment shell script with commands to run migrations, set permissions, and restart middleware.
