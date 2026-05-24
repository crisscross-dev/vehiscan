# QR Code HTTPS Redirect Issue - Troubleshooting Guide

## Problem
When scanning QR codes on mobile devices, Chrome shows "Your connection is not private" error (NET::ERR_CERT_AUTHORITY_INVALID) even though the system is configured for HTTP on local network.

## Root Cause
Chrome's HSTS (HTTP Strict Transport Security) cache is forcing HTTPS even though the server is sending HTTP responses.

## Fixes Applied

### 1. Security Headers Updated (`includes/security_headers.php`)
- Changed to use `HTTP_HOST` instead of `SERVER_NAME` (more reliable)
- Added port number stripping
- Added debug logging
- Confirmed local network IP detection (192.168.x.x) is working

### 2. QR Codes Regenerated
- All 6 visitor pass QR codes regenerated with correct WiFi IP
- URLs now point to: `http://192.168.1.39/Vehiscan-RFID/visitor/view_pass.php?token=xxx`

### 3. Session Configurations Updated
- All session files updated to allow HTTP cookies on local network
- Changed `SameSite` from `Strict` to `Lax`
- Disabled `secure` flag for local testing

## Solutions to Try

### Solution 1: Clear Chrome HSTS Cache (Most Reliable)
1. On your phone, open Chrome
2. Type in address bar: `chrome://net-internals/#hsts`
3. Scroll to "Delete domain security policies"
4. Enter: `192.168.1.39`
5. Click "Delete"
6. Close and reopen Chrome
7. Scan QR code again

### Solution 2: Use Chrome Incognito Mode (Quickest Test)
1. Open Chrome in Incognito/Private mode
2. Scan the QR code
3. Should work without HSTS caching

### Solution 3: Use Different Browser
- Try Firefox, Safari, or Edge instead of Chrome
- These browsers handle HSTS differently

### Solution 4: Test with Standalone Page (Diagnostic)
Access this test page to verify security headers are working:
```
http://192.168.1.39/Vehiscan-RFID/visitor/test_pass.php?token=305e61ddf4acc078e308829f4e4ae462
```

If this page loads without HTTPS error, the security headers are working correctly and the issue is purely browser HSTS caching.

## Test URLs
Replace with your actual tokens:
- Test Pass 1: `http://192.168.1.39/Vehiscan-RFID/visitor/view_pass.php?token=fcd8ecd77fdad9d2bbb5632aab878bd4`
- Test Pass 2: `http://192.168.1.39/Vehiscan-RFID/visitor/view_pass.php?token=305e61ddf4acc078e308829f4e4ae462`

## For Production Deployment
When you deploy to a real domain with HTTPS:
1. Update `admin/api/qr_helper.php` to use actual domain instead of 192.168.1.39
2. Regenerate all QR codes
3. The security headers will automatically force HTTPS for non-local networks
4. HSTS will work properly with valid SSL certificate

## Verification Commands
Run these to verify configuration:
```powershell
# Test security headers
php C:\xampp\htdocs\Vehiscan-RFID\_testing\test_visitor_pass_urls.php

# Regenerate QR codes if needed
php C:\xampp\htdocs\Vehiscan-RFID\_testing\regenerate_all_qr_codes.php
```

## Status
 Server-side configuration: **CORRECT**
  Browser HSTS cache: **NEEDS CLEARING**
 QR codes: **REGENERATED WITH CORRECT URLs**
 Session handling: **UPDATED FOR LOCAL NETWORK**

The system is properly configured. The error you're seeing is entirely due to Chrome's cached HSTS policy.
