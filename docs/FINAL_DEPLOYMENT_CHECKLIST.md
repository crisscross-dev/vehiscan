# Final Deployment & Rollback Checklist

This file lists the final steps to deploy VehiScan to staging/production and rollback guidance.

## Pre-deploy (on your workstation)
- [ ] Ensure working tree is clean and all migrations are committed.
- [ ] Create a release branch or tag.
- [ ] Run tests locally: `php tests/run_hardening_checks.php` (expect PASS).
- [ ] Backup production DB and code before deployment.

## Deploy to Staging (recommended first)
1. Copy repository to staging host or push via CI.
2. Copy `.env.hosting.example` → `.env` and update values:
   - `APP_URL=https://staging.your-domain.example`
   - `SESSION_SECURE=true`
   - DB credentials
3. Ensure `backups_db/` is writable by PHP (0700).
4. Configure webserver for HTTPS and enable PHP-FPM (or equivalent).
5. Install TLS certificate (Let's Encrypt recommended).
6. Set proper permissions:
   - `chown -R www-data:www-data .`
   - `find . -type d -exec chmod 0755 {} \;`
   - `find . -type f -exec chmod 0644 {} \;`
7. Run DB backup via `scripts/backup_db.php` or host control panel.
8. Run `run_migrations.php` from the admin panel (admin-only).
9. Run `php tests/run_hardening_checks.php` on staging.
10. Perform manual camera E2E tests per `docs/STAGING_TLS_E2E.md`.

## Production Deploy
- Follow the same steps as staging with production `.env`.
- Point DNS to production server after smoke tests pass on staging.

## Post-deploy verification
- Verify login, visitor pass creation, and QR generation.
- Verify guard scanner access and QR scan flows (HTTPS required).
- Check `php -l` for recent PHP files if edits were made on server.
- Monitor logs for errors: `/var/log/apache2/error.log` or PHP logs.

## Rollback Plan
1. If catastrophic failure, restore DB from the most recent backup in `backups_db/`.
2. Revert to previous release tag/branch and re-deploy code.
3. If needed, clear sessions by rotating session cookie name (short term) or deleting session files.

## Security Reminders
- Remove `run_migrations.php` and `scripts/backup_db.php` from web-accessible locations after use, or protect them behind strict admin checks (they already require admin session).
- Keep `.env` out of version control.
- Use strong credentials and rotate DB user passwords periodically.

---

If you want, I can also scaffold a basic CI/CD workflow (GitHub Actions) to automate deployment and run tests before promoting to production.