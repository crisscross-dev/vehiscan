# VehiScan RFID System

A comprehensive RFID-based vehicle access control system for gated communities with homeowner management, guard monitoring, and admin control panels.

## Project Structure

```
Vehiscan-RFID/
│
├── index.php # Main entry point
├── db.php # Database configuration
├── .env # Environment variables
├── .htaccess # Apache configuration
├── package.json # npm dependencies & scripts
│
├── admin/ # Admin Panel
│ ├── admin_panel.php # Main admin interface
│ ├── api/ # Admin API endpoints
│ ├── diagnostics/ # System diagnostics
│ ├── fetch/ # Data fetching endpoints
│ ├── homeowners/ # Homeowner management
│ ├── simulation/ # RFID simulator
│ └── utilities/ # Admin utilities
│
├── guard/ # Guard Panel
│ ├── pages/ # Guard interface pages
│ ├── css/ # Guard-specific styles
│ ├── js/ # Guard-specific scripts
│ ├── fetch_logs.php # Fetch recent access logs
│ ├── notifications.php # Notification system
│ └── check_visitor_pass.php # Visitor pass validation
│
├── homeowners/ # Homeowner Portal
│ ├── homeowner_registration.php # Registration form
│ ├── qr_registration.php # QR-based registration
│ └── homeowner_register_action.php # Registration handler
│
├── auth/ # Authentication
│ ├── login.php # Login page
│ ├── logout.php # Logout handler
│ └── admin_create.php # Admin creation
│
├── api/ # Global API Endpoints
│ ├── homeowners_get.php # Get homeowner data
│ └── homeowner_save.php # Save homeowner data
│
├── assets/ # Static Assets
│ ├── css/ # Compiled CSS
│ │ ├── tailwind.css # Production CSS (43KB)
│ │ └── tailwind-input.css # Source CSS
│ └── js/ # JavaScript files
│
├── includes/ # Shared PHP Includes
│ ├── session_config.php # Session configuration
│ ├── session_admin_unified.php # Admin/super-admin session management
│ ├── session_guard.php # Guard session management
│ ├── security_headers.php # Security headers
│ ├── rate_limiter.php # Rate limiting
│ ├── file_validator.php # File upload validation
│ ├── upload_helper.php # Upload utilities
│ └── helpers.php # Helper functions
│
├── phpqrcode/ # QR Code Library
│ ├── generate_qr.php # QR generation
│ └── phpqrcode.php # Main library
│
├── uploads/ # User Uploads
│ └── homeowners/ # Homeowner images
│
├── backups/ # Database Backups
│ └── README.md # Backup procedures
│
├── scripts/ # Build Scripts
│ ├── build.bat # Windows quick build
│ ├── build-production.ps1 # PowerShell build script
│ └── README.md # Scripts documentation
│
├── config/ # Configuration Files
│ ├── tailwind.config.js # Tailwind CSS config
│ ├── .env (copy) # Environment backup
│ ├── db.php (copy) # Database config backup
│ └── README.md # Config documentation
│
├── documentation/ # Documentation
│ ├── SETUP_COMPLETE.md # Complete setup guide
│ ├── TAILWIND_SETUP.md # Tailwind installation
│ ├── CSS_BUILD_GUIDE.md # CSS build guide
│ └── README.md # Documentation index
│
├── docs/ # Technical Documentation
│ ├── ADMIN_REORGANIZATION.md
│ ├── GUARD_FIXES.md
│ ├── GUARD_PANEL_TESTING.md
│ ├── IMPROVEMENT_PLAN.md
│ ├── MULTI_TAB_GUIDE.md
│ ├── NEW_FEATURES_GUIDE.md
│ ├── PROJECT_REORGANIZATION.md
│ ├── QUICK_START.md
│ ├── RFID_FLOW_VERIFIED.md
│ ├── SESSION_FIX_GUIDE.md
│ └── SYSTEM_ANALYSIS.md
│
└── _testing/ # Testing Files
    ├── test_rfid_flow.php
    ├── test_features.html
    ├── analyze_database.php
    └── README.md
```

## Quick Start

### Prerequisites
- **XAMPP** (Apache, MySQL, PHP 7.4+)
- **Node.js** v14+ and npm
- **Modern Browser** (Chrome, Firefox, Edge)

### Installation

1. **Clone/Download** the project to `C:\xampp\htdocs\Vehiscan-RFID`

2. **Install Dependencies**
   ```bash
   npm install
   ```

3. **Database Setup**
   - Import database: `vehiscan_vdp`
   - Configure credentials in `db.php` and `.env`

4. **Build CSS**
   ```bash
   npm run build
   ```

5. **Start Apache & MySQL** in XAMPP

6. **Access the System**
   - Admin: `http://localhost/Vehiscan-RFID/admin/admin_panel.php`
   - Guard: `http://localhost/Vehiscan-RFID/guard/pages/guard_side.php`
   - Registration: `http://localhost/Vehiscan-RFID/homeowners/homeowner_registration.php`

## Development

### Build Commands

```bash
# Development mode (watch for changes)
npm run dev

# Production build (minified CSS)
npm run build

# Or use the Windows batch file
.\scripts\build.bat
```

### CSS Compilation
- **Source**: `assets/css/tailwind-input.css`
- **Output**: `assets/css/tailwind.css` (43KB minified)
- **Config**: `config/tailwind.config.js`
- **Build Time**: ~5-6 seconds

## Documentation

- **Setup Guide**: `documentation/SETUP_COMPLETE.md`
- **Tailwind Setup**: `documentation/TAILWIND_SETUP.md`
- **CSS Build Guide**: `documentation/CSS_BUILD_GUIDE.md`
- **Feature Guides**: `docs/` folder
- **Scripts**: `scripts/README.md`
- **Configuration**: `config/README.md`

## Features

### Admin Panel
- Dashboard with analytics
- 👥 Homeowner management (CRUD)
- 🎯 RFID simulator for testing
- Export data (CSV, PDF)
- 🔔 Real-time notifications
- 🔐 Visitor pass management

### Guard Panel
- 📜 Real-time access logs
- Homeowner lookup
- Visitor pass validation
- Status dashboard
- 🔔 Notification system
- Mobile-responsive design

### Homeowner Portal
- Online registration
- Vehicle information
- Image uploads (car, owner)
- Form validation
- CSRF protection
- Responsive forms

## Security Features

- CSRF token protection
- Session management
- Rate limiting
- SQL injection prevention (PDO prepared statements)
- File upload validation
- XSS protection
- Secure password hashing

## Tech Stack

**Backend:**
- PHP 7.4+
- MySQL/MariaDB
- PDO for database

**Frontend:**
- Vanilla JavaScript ES6+
- Tailwind CSS 3.4.1 (compiled)
- SweetAlert2 (modals)
- Chart.js (analytics)
- Font Awesome 6.4.0

**Build Tools:**
- Node.js & npm
- Tailwind CLI
- PostCSS & Autoprefixer

## License

Proprietary - VehiScan RFID System

## 👥 Support

For issues or questions, refer to the documentation in `/documentation` and `/docs` folders.

---

**Last Updated**: January 2025 
**Version**: 1.0.0 
**Build System**: Tailwind CSS 3.4.1 with npm
