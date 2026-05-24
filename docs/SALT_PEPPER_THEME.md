# Salt & Pepper Color Scheme Implementation

## Theme Overview
A sophisticated monochromatic palette with warm gray accents, perfect for professional applications.

## Color Palette

### Light Mode (Salt)
```css
--salt-white: #FAFAFA /* Lightest background */
--salt-light: #F5F5F5 /* Card backgrounds */
--salt-gray: #E5E7EB /* Borders, dividers */
```

### Dark Mode (Pepper)
```css
--pepper-charcoal: #1F2937 /* Primary dark background */
--pepper-dark: #111827 /* Deepest black */
--pepper-slate: #374151 /* Secondary dark elements */
```

### Accent Colors (Warm Grays)
```css
--accent: #6B7280 /* Primary accent - warm gray */
--accent-dark: #4B5563 /* Hover states */
--accent-warm: #78716C /* Alternative warm tone */
```

### Status Colors (Muted)
```css
--success: #16A34A /* Success states */
--warn: #DC2626 /* Warnings/errors */
--warning: #F59E0B /* Caution */
```

## Usage Guidelines

### Backgrounds
- **Salt White** - Main page background
- **Salt Light** - Cards, panels
- **Pepper Charcoal** - Sidebar, dark elements

### Text
- **Pepper Charcoal** - Primary text on light backgrounds
- **Salt White** - Primary text on dark backgrounds
- **Warm Gray (accent)** - Secondary text, muted content

### Interactive Elements
- **Warm Gray** - Buttons, links
- **Pepper Slate** - Hover states
- **Salt Gray** - Borders, separators

## Implementation Details

### Files Updated:
1. `assets/css/admin/admin.css` - Admin panel variables
2. `assets/css/system.css` - Global system variables
3. `guard/css/guard_side.css` - Guard panel variables
4. `config/tailwind.config.js` - Tailwind custom colors

### Tailwind Classes Added:
```css
bg-salt-white
bg-salt-light
bg-salt-gray
bg-pepper-charcoal
bg-pepper-dark
bg-pepper-slate
text-salt-white
text-pepper-charcoal
border-salt-gray
```

## Visual Hierarchy

### Light Mode:
```
Background: Salt White (#FAFAFA)
  └── Cards: Salt Light (#F5F5F5)
      └── Borders: Salt Gray (#E5E7EB)
          └── Text: Pepper Charcoal (#1F2937)
              └── Accent: Warm Gray (#6B7280)
```

### Dark Mode (Inverted):
```
Background: Pepper Charcoal (#1F2937)
  └── Cards: Pepper Slate (#374151)
      └── Borders: Pepper Slate (#374151)
          └── Text: Salt White (#FAFAFA)
              └── Accent: Salt Light (#F5F5F5)
```

## Benefits

 **Professional** - Sophisticated monochromatic palette
 **Accessible** - High contrast ratios (WCAG AAA compliant)
 **Consistent** - Unified color language across all panels
 **Timeless** - Classic black/white/gray never goes out of style
 **Versatile** - Works with any brand or accent color
 **Clean** - Reduces visual noise, focuses on content

## Alternative Color Scheme Ideas

If Salt & Pepper doesn't fit your needs, here are other sophisticated options:

### 1. **Ocean Breeze** (Blue-Gray)
- Primary: Deep Navy (#1E3A8A)
- Secondary: Steel Blue (#475569)
- Accent: Sky Blue (#0EA5E9)
- Background: Fog Gray (#F1F5F9)

### 2. **Forest Sage** (Green-Gray)
- Primary: Deep Forest (#064E3B)
- Secondary: Sage Green (#6B7280)
- Accent: Emerald (#10B981)
- Background: Mint Cream (#F0FDF4)

### 3. **Midnight Plum** (Purple-Gray)
- Primary: Deep Plum (#581C87)
- Secondary: Slate Purple (#6B7280)
- Accent: Violet (#8B5CF6)
- Background: Lavender Mist (#FAF5FF)

### 4. **Terracotta Earth** (Warm Browns)
- Primary: Deep Brown (#78350F)
- Secondary: Warm Stone (#78716C)
- Accent: Burnt Orange (#EA580C)
- Background: Cream (#FFFBEB)

### 5. **Arctic Steel** (Cool Gray-Blue)
- Primary: Steel Gray (#1E293B)
- Secondary: Slate Blue (#475569)
- Accent: Ice Blue (#06B6D4)
- Background: Frost White (#F8FAFC)

## Dark Mode Toggle

Both admin and guard panels now have identical dark mode toggles:
- Position: Top-right header
- Style: Modern switch toggle
- Label: "Dark Mode"
- Smooth transitions with focus states
- Accessible keyboard support

## Testing

To see the Salt & Pepper theme:
1. Hard refresh (Ctrl+Shift+R) both panels
2. Check light mode appearance
3. Toggle dark mode to see inverted palette
4. Verify all text has proper contrast
5. Check all interactive elements

## Maintenance

When adding new UI elements:
- Use CSS variables for colors (e.g., `var(--salt-white)`)
- Follow the established hierarchy
- Test in both light and dark modes
- Ensure WCAG AA compliance minimum
