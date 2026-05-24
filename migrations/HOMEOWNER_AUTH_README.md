# Homeowner Authentication Migration

## Overview
This migration adds login credentials (username and password) to the homeowners table, enabling homeowners to log into their portal.

## Files Created
1. **004_add_homeowner_auth.sql** - SQL migration script
2. **apply_homeowner_auth.php** - PHP migration runner with UI

## How to Apply

### Option 1: Via Web Interface (Recommended)
1. Navigate to: `http://192.168.1.39/Vehiscan-RFID/migrations/apply_homeowner_auth.php`
2. The migration will run automatically and show results
3. Verify that username and password_hash columns were added

### Option 2: Via MySQL Command Line
```bash
mysql -u root vehiscan < migrations/004_add_homeowner_auth.sql
```

## Changes Made

### Database Schema
- Added `username` VARCHAR(50) NOT NULL UNIQUE to `homeowners` table
- Added `password_hash` VARCHAR(255) NOT NULL to `homeowners` table
- Added index on `username` for faster lookups

### Registration Form Updates
**File: `homeowners/homeowner_registration.php`**
- Added "Username" field (letters, numbers, underscores only)
- Added "Password" field (minimum 6 characters)
- Added "Confirm Password" field for validation
- Backend validates:
  - Password match
  - Password length (≥6 characters)
  - Username uniqueness
  - Passwords are hashed using `password_hash()` before storage

### Login Updates
**File: `assets/js/login.js`**
- "Create an account" link now redirects to homeowner registration when "Homeowner" role is selected
- Supports both 'user' and 'homeowner' role values

## Existing Homeowners
If you have existing homeowners in the database:
- They will be assigned temporary usernames: `homeowner_1`, `homeowner_2`, etc.
- Default password: `password`
- **Action Required**: Existing homeowners should update their credentials upon first login

## Security Notes
- Passwords are hashed using PHP's `password_hash()` with bcrypt
- Usernames must be unique
- Password minimum length: 6 characters
- Username pattern: alphanumeric and underscores only

## Testing
1. Go to login page and select "Homeowner" role
2. Click "Create an account" - should redirect to registration
3. Fill in all fields including username and password
4. Submit and verify account creation
5. Login with new credentials

## Rollback (if needed)
```sql
ALTER TABLE `homeowners` 
DROP COLUMN `username`,
DROP COLUMN `password_hash`;
```

## Status
[x] Migration ready to apply
[x] CSS errors fixed
[x] Form fields added
[x] Backend validation implemented
[x] Redirect functionality added
