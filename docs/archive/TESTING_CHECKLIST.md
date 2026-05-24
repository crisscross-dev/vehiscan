# Testing Checklist - Dark Mode & QR Modal Fixes

## Issues Fixed
1. JavaScript syntax errors (undefined variable, missing closing brace)
2. Added QR click handlers in attachVisitorsControls()
3. Enhanced dark mode toggle visibility with border and shadow
4. Removed 192 lines of inline CSS/JS code
5. Created organized external CSS/JS files

## Testing Steps

### 1. Test Dark Mode Toggle (Standalone)
**URL:** `http://localhost/Vehiscan-RFID/_testing/test_dark_mode_qr.html`

**Steps:**
1. Open the URL in browser
2. Open DevTools Console (F12)
3. Check if toggle button is visible with border
4. Click the toggle button
   - Should log: "Toggle clicked!"
   - Should log: "Enabling dark mode..."
   - Status should show: "Dark Mode: ON"
   - Background should turn dark
5. Click QR test image
   - Should log: "openQRZoom function: function"
   - Modal should open with zoomed QR
   - Can close with X button or ESC or clicking outside

**If this works:** Base functionality is correct, issue is in admin panel integration
**If this fails:** CSS/JS files have errors, check console

---

### 2. Test Admin Panel Dark Mode
**URL:** `http://localhost/Vehiscan-RFID/admin/admin_panel.php`

**Steps:**
1. Login to admin panel
2. Check top-right header for dark mode toggle
   - Should see toggle button with gray border
   - Button should be next to "Dark Mode" label
3. Click the toggle
   - Should enable dark mode (background turns dark)
   - Toggle thumb should slide to right
   - Toggle background should change color
4. Refresh page
   - Dark mode should persist (from localStorage)

**Check Console for:**
- No JavaScript errors
- No 404 errors for CSS/JS files
- Should see: "[DARK MODE] Toggle initialized"

---

### 3. Test Admin Panel QR Modal
**URL:** `http://localhost/Vehiscan-RFID/admin/admin_panel.php`

**Steps:**
1. Login to admin panel
2. Navigate to "Visitor Passes" in left sidebar
3. Wait for page to load
4. Check console logs:
   - Should see: "[Visitors] Attaching controls"
   - Should see: "[Visitors] Attaching QR click handlers"
   - Should see: "[Visitors] Found X QR images" (where X is number of QR codes)
5. Click on any QR code image
   - Should log: "[Visitors] QR image clicked, src: ..."
   - Modal should open with zoomed QR code
   - Can close modal with:
     - X button in top-right
     - ESC key
     - Clicking outside modal

**Check Console for:**
- No errors about "openQRZoom is not defined"
- No 404 errors for visitor-passes.css or qr-modal.js
- Console should show QR click events

---

### 4. Test Guard Panel Dark Mode
**URL:** `http://localhost/Vehiscan-RFID/guard/pages/guard_side.php`

**Steps:**
1. Login as guard
2. Check top-right header for dark mode toggle
   - Should see toggle button with gray border
   - Button should be next to "Dark" label (on desktop)
3. Click the toggle
   - Should enable dark mode
   - Toggle should animate
4. Refresh page
   - Dark mode should persist

**Check Console for:**
- No JavaScript errors
- No 404 errors for guard CSS/JS files
- Should see guard dark mode logs

---

### 5. Verify File Loading (Network Tab)
**Steps:**
1. Open admin panel
2. Open DevTools → Network tab
3. Refresh page
4. Filter by "CSS" and verify these load with 200 status:
   - `visitor-passes.css` (200 OK)
   - `system.css` (200 OK)
   - `tailwind.css` (200 OK)
5. Filter by "JS" and verify these load with 200 status:
   - `qr-modal.js` (200 OK)
   - `admin_panel.js` (200 OK)

**If 404 errors:**
- Check file paths in admin_panel.php
- Verify files exist in correct directories

---

## Expected Console Logs (Admin Panel)

### On Page Load:
```
[DARK MODE] Toggle initialized
[NAV] All navigation listeners attached
[NAV] Active class set on: dashboard
[NAV] Page title updated to: Dashboard
```

### When Clicking Visitor Passes:
```
[NAV] Link clicked for page: visitors
[NAV] Active class set on: visitors
[NAV] Page title updated to: Visitor Passes
[Visitors] Attaching controls
[Visitors] Attaching QR click handlers
[Visitors] Found 3 QR images
```

### When Clicking QR Code:
```
[Visitors] QR image clicked, src: http://localhost/Vehiscan-RFID/uploads/qr/...
```

---

## Troubleshooting

### Dark Mode Toggle Not Visible
**Possible Causes:**
1. CSS not loading (check Network tab)
2. Tailwind classes not generated (run npm build)
3. Z-index conflict with other elements

**Fix:**
- Check if `system.css` loads
- Inspect element and check computed styles
- Look for `z-index: 50` and `border: 2px solid`

---

### QR Click Not Working
**Possible Causes:**
1. `qr-modal.js` not loading (404 error)
2. Modal HTML not created
3. Click handlers not attached after AJAX load
4. `openQRZoom` function not defined

**Debug:**
```javascript
// In browser console:
typeof window.openQRZoom // Should be "function"
document.getElementById('qrZoomModal') // Should be HTMLElement, not null
document.querySelectorAll('.qr-clickable').length // Should be > 0
```

**Fix:**
- Check Network tab for qr-modal.js (should be 200)
- Check if modal exists in DOM
- Verify attachVisitorsControls() is called
- Check console for "[Visitors] Found X QR images"

---

### AJAX Content Not Loading
**Possible Causes:**
1. Path wrong in loadPage() function
2. PHP file missing or renamed
3. Session expired

**Debug:**
- Check Network tab for `fetch_visitors.php` request
- Should return 200 with HTML content
- If 403: session expired, logout and login again
- If 404: file path is wrong

---

## Files Modified in This Session

### JavaScript:
- `assets/js/admin/admin_panel.js` (fixed syntax errors, added QR handlers)

### CSS:
- `assets/css/system.css` (enhanced toggle visibility)
- `admin/css/visitor-passes.css` (created new)
- `guard/css/guard-components.css` (created new)

### PHP:
- `admin/admin_panel.php` (added CSS/JS includes)
- `admin/fetch/fetch_visitors.php` (removed inline code)
- `guard/pages/guard_side.php` (removed inline code)

### New Files Created:
- `admin/js/qr-modal.js` (QR zoom functionality)
- `guard/js/guard-config.js` (guard configuration)
- `guard/js/guard-dark-mode.js` (guard dark mode)
- `_testing/test_dark_mode_qr.html` (debugging tool)
- `_testing/TESTING_CHECKLIST.md` (this file)

---

## Success Criteria

### Dark Mode Toggle:
- Toggle button visible with gray border
- Clicking toggle changes theme
- Theme persists after page refresh
- Works in both admin and guard panels
- No console errors

### QR Modal:
- Clicking QR code opens modal
- Modal shows zoomed QR image
- Can close with X, ESC, or outside click
- Works on AJAX-loaded visitor passes page
- No console errors about undefined functions

### Code Quality:
- No inline CSS/JS in PHP files
- All styles in external CSS files
- All scripts in external JS files
- No JavaScript syntax errors
- All files load without 404 errors

---

## Browser Testing

**Recommended Browsers:**
- Chrome/Edge (primary)
- Firefox
- Safari (if on Mac)

**Clear Cache:**
Before testing, clear browser cache or use Ctrl+Shift+R to hard refresh

**Test Responsive:**
- Desktop (1920x1080)
- Tablet (768px width)
- Mobile (375px width)

---

## Next Steps If Issues Persist

1. **Check Browser Console** - Look for specific error messages
2. **Check Network Tab** - Verify all CSS/JS files load with 200 status
3. **Test Standalone Page** - Use test_dark_mode_qr.html to isolate issues
4. **Inspect Elements** - Use DevTools to check computed styles
5. **Verify localStorage** - Check if dark mode preference is stored
6. **Test Event Handlers** - Use console to verify functions exist

---

**Last Updated:** 2025-01-XX (Session completion)
**Status:** Fixes implemented, ready for testing
