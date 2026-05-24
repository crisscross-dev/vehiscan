# Deployment Guide: QR Code Hosting Configuration

## Quick Setup for InfinityFree Hosting

### Step 1: Create `.env` File on Your Server

1. Copy `.env.production` to your hosting root directory
2. Rename it to `.env` (remove the `.production` suffix)
3. Update `APP_URL` and `QR_BASE_URL` with your actual domain:

```env
APP_URL=https://yourdomain.infinityfreeapp.com
QR_BASE_URL=https://yourdomain.infinityfreeapp.com
```

### Step 2: Verify Configuration

The QR codes will automatically use your hosting domain because:
- `config.php` loads `.env` variables first
- `qr_helper.php` uses `APP_URL` constant (line 17)
- If no `.env` exists, it falls back to config defaults

### Step 3: Test QR Codes

1. Go to Admin Panel → Visitor Pass Management
2. Create a new visitor pass
3. Check the generated QR code URL - it should show your hosting domain
4. Scan the QR code with your phone to verify it points to the correct URL

### Configuration Files

- **`.env.production`** - Template for production hosting (InfinityFree)
- **`.env.example`** - Template for local development
- **`config.php`** - Loads environment variables
- **`admin/api/qr_helper.php`** - Generates QR codes using APP_URL

### Current Configuration

Your hosting details:
- **Domain**: `yourdomain.example.com` (update with actual domain)
- **Database Host**: `your-db-host.example.com`
- **Database**: `your_database_name`

### Troubleshooting

**QR codes still show local IP?**
1. Clear browser cache
2. Regenerate all QR codes from Admin Panel
3. Check `.env` file exists on server
4. Verify `APP_URL` in `.env` matches your domain

**Can't upload files?**
- InfinityFree has 10MB upload limit
- Check `MAX_FILE_SIZE` in `.env`

**Database connection fails?**
- Verify credentials in `.env` match your InfinityFree account
- Check database is active in hosting control panel
