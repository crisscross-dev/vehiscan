# Role Colors and Dark Mode Configuration Guide

## 🎨 How to Edit Role Badge Colors

Role badges appear in the Employee Management section. You can customize the colors for each role by editing this file:

### Location: `admin/fetch/fetch_employees.php`

**Lines 105-109:**

```php
$badges = [
  'admin' => 'bg-purple-100 text-purple-800',
  'guard' => 'bg-blue-100 text-blue-800',
  'super_admin' => 'bg-orange-100 text-orange-800',
  'homeowner' => 'bg-emerald-100 text-emerald-800'
];
```

### Available Tailwind Color Options

Replace the color names in the format `bg-{color}-100 text-{color}-800`:

#### Light Backgrounds (bg-{color}-100):
- `bg-red-100` - Light Red
- `bg-orange-100` - Light Orange (current super_admin)
- `bg-amber-100` - Light Amber
- `bg-yellow-100` - Light Yellow
- `bg-lime-100` - Light Lime
- `bg-green-100` - Light Green
- `bg-emerald-100` - Light Emerald
- `bg-teal-100` - Light Teal
- `bg-cyan-100` - Light Cyan
- `bg-sky-100` - Light Sky
- `bg-blue-100` - Light Blue (current guard)
- `bg-indigo-100` - Light Indigo
- `bg-violet-100` - Light Violet
- `bg-purple-100` - Light Purple (current admin)
- `bg-fuchsia-100` - Light Fuchsia
- `bg-pink-100` - Light Pink
- `bg-rose-100` - Light Rose

#### Text Colors (text-{color}-800):
- Use the same color name but change `text-{color}-800`
- Example: `text-emerald-800` for dark emerald text

### Example Customizations

**Elegant Professional Colors:**
```php
$badges = [
  'admin' => 'bg-indigo-100 text-indigo-800', // Indigo (professional)
  'guard' => 'bg-emerald-100 text-emerald-800', // Emerald (security)
  'super_admin' => 'bg-rose-100 text-rose-800' // Rose (executive)
];
```

**Vibrant Modern Colors:**
```php
$badges = [
  'admin' => 'bg-violet-100 text-violet-800', // Violet
  'guard' => 'bg-cyan-100 text-cyan-800', // Cyan
  'super_admin' => 'bg-amber-100 text-amber-800' // Amber
];
```

**Minimalist Colors:**
```php
$badges = [
  'admin' => 'bg-slate-100 text-slate-800', // Slate gray
  'guard' => 'bg-gray-100 text-gray-800', // Neutral gray
  'super_admin' => 'bg-zinc-100 text-zinc-800' // Zinc gray
];
```

---

## Dark Mode Toggle CSS Review

The dark mode toggle combines Tailwind CSS utility classes with custom CSS for smooth animations.

### HTML Structure (`admin/admin_panel.php` Line 197)

```html
<button id="darkModeToggle" 
        class="relative inline-flex h-9 w-16 items-center rounded-full 
               bg-gray-300 border-2 border-gray-400 
               transition-colors duration-200 hover:bg-gray-400" 
        aria-label="Toggle dark mode">
  <span class="toggle-thumb inline-block h-7 w-7 transform rounded-full 
               bg-white shadow-md transition-transform duration-200 
               translate-x-1">
  </span>
</button>
```

#### Tailwind Classes Used:
- **Layout:** `relative`, `inline-flex`, `items-center`
- **Size:** `h-9`, `w-16` (button), `h-7`, `w-7` (thumb)
- **Shape:** `rounded-full`
- **Colors:** `bg-gray-300`, `border-gray-400`, `bg-white`
- **Effects:** `shadow-md`, `transition-colors`, `transition-transform`
- **Animation:** `translate-x-1` (default), `translate-x-7` (when active)
- **Interaction:** `hover:bg-gray-400`

### JavaScript Toggle Logic (`assets/js/admin/admin_panel.js` Lines 42-55)

```javascript
darkModeToggle?.addEventListener('click', () => {
  isDarkMode = !isDarkMode;
  localStorage.setItem('adminDarkMode', isDarkMode);
  localStorage.setItem('adminDarkModeManual', Date.now().toString());
  document.body.classList.toggle('dark');
  
  // Toggle button colors (Active = Blue, Inactive = Gray)
  if (isDarkMode) {
    darkModeToggle.classList.add('bg-blue-600', 'border-blue-700');
    darkModeToggle.classList.remove('bg-gray-300', 'border-gray-400');
  } else {
    darkModeToggle.classList.remove('bg-blue-600', 'border-blue-700');
    darkModeToggle.classList.add('bg-gray-300', 'border-gray-400');
  }
  
  // Slide thumb animation
  if (darkModeIcon) {
    darkModeIcon.classList.toggle('translate-x-7');
  }
});
```

#### CSS Classes Toggled Dynamically:
- **Inactive State:** `bg-gray-300`, `border-gray-400`
- **Active State:** `bg-blue-600`, `border-blue-700`
- **Thumb Position:** `translate-x-1` → `translate-x-7`

### Custom CSS (None Required!)

The dark mode toggle uses **ONLY Tailwind CSS utility classes**. No custom CSS is needed because:

1. **Positioning:** Handled by `relative` and `absolute` (or `inline-flex`)
2. **Animation:** Built-in Tailwind transitions (`transition-colors`, `transition-transform`)
3. **Colors:** Tailwind color utilities (`bg-*`, `text-*`, `border-*`)
4. **Sizing:** Tailwind spacing scale (`h-*`, `w-*`)

### Dark Mode Detection (`assets/js/admin/admin_panel.js` Lines 25-38)

```javascript
// Initialize dark mode from localStorage or system preference
let isDarkMode;
const savedDarkMode = localStorage.getItem('adminDarkMode');

if (savedDarkMode === null) {
  // First visit - detect system preference
  isDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;
  localStorage.setItem('adminDarkMode', isDarkMode);
} else {
  isDarkMode = savedDarkMode === 'true';
}

// Listen for system preference changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
  // Auto-adjust only if no manual toggle in last hour
  const lastManualToggle = localStorage.getItem('adminDarkModeManual');
  if (!lastManualToggle || Date.now() - parseInt(lastManualToggle) > 3600000) {
    isDarkMode = e.matches;
    localStorage.setItem('adminDarkMode', isDarkMode);
    document.body.classList.toggle('dark', isDarkMode);
    // Update button appearance...
  }
});
```

---

## 🎨 Dark Mode Global Styles (`assets/css/system.css` Lines 398-507)

All dark mode styles use the `body.dark` selector and Tailwind-compatible CSS variables:

### Core Variables
```css
body.dark {
  --bg: #0f172a; /* Slate 900 */
  --text: #f1f5f9; /* Slate 100 */
  --border: #334155; /* Slate 700 */
  background-color: #0f172a;
  color: #f1f5f9;
}
```

### Component-Specific Dark Mode Styles

#### Tables
```css
body.dark table {
  background: #1e293b !important; /* Slate 800 */
  color: #f1f5f9 !important;
}

body.dark table th {
  background: #1e293b !important;
  border-color: #334155 !important;
  color: #f1f5f9 !important;
}

body.dark table tr:hover {
  background: #334155 !important; /* Slate 700 */
}
```

#### Sidebar & Navigation
```css
body.dark #sidebar,
body.dark aside,
body.dark nav {
  background: #1e293b !important; /* Slate 800 */
  border-color: #334155 !important;
}

body.dark #sidebar a:hover {
  background: #334155 !important; /* Slate 700 */
  color: #f1f5f9 !important;
}
```

#### Forms & Inputs
```css
body.dark input,
body.dark select,
body.dark textarea {
  background: #1e293b !important;
  color: #f1f5f9 !important;
  border-color: #475569 !important; /* Slate 600 */
}

body.dark input:focus {
  border-color: #3b82f6 !important; /* Blue 500 */
  outline-color: #3b82f6 !important;
}
```

#### Modals
```css
body.dark .modal-content,
body.dark #editModal .modal-body {
  background: #1e293b !important;
  color: #f1f5f9 !important;
}
```

---

## Summary

### Tailwind + Plain CSS Combination Strategy

1. **Tailwind CSS is used for:**
   - Layout utilities (`flex`, `grid`, `relative`)
   - Spacing (`p-*`, `m-*`, `gap-*`)
   - Sizing (`h-*`, `w-*`)
   - Colors (`bg-*`, `text-*`, `border-*`)
   - Transitions (`transition-*`, `duration-*`)
   - Transforms (`translate-x-*`, `scale-*`)

2. **Plain CSS (system.css) is used for:**
   - Dark mode overrides (`body.dark` selectors)
   - Complex animations (toast slide-in, fade-out)
   - Component-specific !important overrides
   - CSS variables for consistent theming

3. **Why This Approach:**
   - **Performance:** Tailwind generates only used utilities
   - **Maintainability:** Dark mode in one place (system.css)
   - **Flexibility:** Can override Tailwind when needed
   - **Consistency:** CSS variables ensure uniform theming

### To Customize:

1. **Role Colors:** Edit `admin/fetch/fetch_employees.php` lines 105-109
2. **Dark Mode Colors:** Edit `assets/css/system.css` body.dark selectors
3. **Toggle Button Colors:** Edit `assets/js/admin/admin_panel.js` lines 48-52
4. **After Changes:** Run `cd config && npm run build` to rebuild Tailwind CSS

---

## Quick Customization Examples

### Make Admin Role Green Instead of Purple:
```php
// In admin/fetch/fetch_employees.php line 106
'admin' => 'bg-green-100 text-green-800',
```

### Make Super Admin Gold/Amber:
```php
// In admin/fetch/fetch_employees.php line 108
'super_admin' => 'bg-amber-100 text-amber-800',
```

### Make Dark Mode Toggle Green When Active:
```javascript
// In assets/js/admin/admin_panel.js line 48-49
darkModeToggle.classList.add('bg-green-600', 'border-green-700');
darkModeToggle.classList.remove('bg-gray-300', 'border-gray-400');
```

### Make Dark Mode Backgrounds Darker:
```css
/* In assets/css/system.css */
body.dark {
  --bg: #020617; /* Slate 950 - Almost black */
  background-color: #020617;
}

body.dark #sidebar,
body.dark .bg-white {
  background: #0f172a !important; /* Slate 900 - Very dark */
}
```

Then rebuild: `cd config && npm run build`

---

## Guard Panel Dark Mode (Separate System)

The guard panel has its own **dedicated dark mode system** that is independent from the admin panel.

### Files:
- **CSS:** `guard/css/guard-dark-mode.css`
- **JavaScript:** `guard/js/guard-dark-mode.js`
- **Storage Key:** `guardDarkMode` (separate from admin's `adminDarkMode`)

### Toggle Button (`guard/pages/guard_side.php`):

```html
<button 
  id="guardDarkModeToggle" 
  role="switch"
  aria-checked="false"
  class="relative inline-flex h-6 w-11 items-center rounded-full 
         bg-gray-200 transition-colors duration-200 
         focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500">
  <span id="guardDarkModeThumb" 
        class="inline-block h-5 w-5 transform rounded-full bg-white shadow-lg 
               transition-transform duration-200 ease-in-out translate-x-0.5">
  </span>
</button>
```

### Why Separate?
- **Independent Settings:** Guards and admins can have different dark mode preferences
- **Different Layouts:** Guard panel has different UI components (camera feed, visitor passes)
- **No Conflicts:** Prevents localStorage and CSS conflicts between panels
- **Better Performance:** Each panel only loads its own dark mode styles

### Guard Dark Mode Features:
- Styles for camera feed, log entries, visitor pass cards
- Filter toggle buttons maintain their color schemes
- Toast notifications adapt to dark mode
- Live camera overlay stays visible
- Automatic detection of system preference on first visit

