# Registration System Fix

## Issues Fixed

### 1. Regex Error in Phone Number Pattern
**Error:** `Uncaught SyntaxError: Invalid regular expression: /[0-9+\-\s()]{7,20}/v: Invalid character in character class`

**Root Cause:** The escape sequence `\-` inside the character class `[0-9+\-\s()]` was invalid in the new regex engine.

**Fix:** Changed pattern from `[0-9+\-\s()]{7,20}` to `[0-9+\s()\-]{7,20}` (moved hyphen to end of character class)

---

### 2. Login Failing with Correct Credentials
**Error:** "Invalid username or password" even when credentials are correct

**Root Cause:** Database architecture mismatch
- **Registration** was saving to `homeowners` table with `username`/`password_hash` columns
- **Login** was checking `homeowner_auth` table (separate authentication table)
- System uses a **two-table architecture**:
  - `homeowners` → vehicle and owner information
  - `homeowner_auth` → login credentials (linked via `homeowner_id`)

**Fix:** Updated registration to use proper two-table architecture:
1. Insert homeowner data into `homeowners` table
2. Insert credentials into `homeowner_auth` table with foreign key reference
3. Use transaction to ensure data consistency

---

## Database Architecture

### `homeowners` Table
Stores vehicle and owner information:
- id, name, contact, address
- vehicle_type, color, plate_number
- owner_img, car_img
- created_at

### `homeowner_auth` Table 
Stores authentication credentials:
- id, homeowner_id (FK)
- username, password_hash
- email, is_active
- created_at, last_login

---

## Testing

### Test Registration:
1. Go to `/homeowners/homeowner_registration.php`
2. Fill in all fields including phone number
3. Should NOT see regex error
4. Should successfully create account

### Test Login:
1. Go to `/auth/login.php`
2. Select "Homeowner" role
3. Use the credentials you just created
4. Should successfully login and redirect to homeowner portal

---

## Existing Homeowners

The following usernames already exist in `homeowner_auth`:
- kyle_jansen
- dan_bringer
- asdasd
- 123123
- asdwd
- (and 6 more)

Total: 11 existing homeowner accounts
