/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    "./admin/**/*.{php,html,js}",
    "./guard/**/*.{php,html,js}",
    "./homeowners/**/*.{php,html,js}",
    "./assets/**/*.{php,html,js}",
    "./auth/**/*.{php,html,js}",
    "./includes/**/*.{php,html,js}",
    "./api/**/*.{php,html,js}",
    "./visitor/**/*.{php,html,js}",
    "./utilities/**/*.{php,html,js}",
    "./index.php",
  ],
  theme: {
    extend: {
      colors: {
        // System-wide CSS variables (Shadcn-style)
        // These will map to bg-primary, text-primary, etc.
        primary: {
          DEFAULT: "hsl(var(--primary))",
          foreground: "hsl(var(--primary-foreground))",
        },
        secondary: {
          DEFAULT: "hsl(var(--secondary))",
          foreground: "hsl(var(--secondary-foreground))",
        },
        destructive: {
          DEFAULT: "hsl(var(--destructive))",
          foreground: "hsl(var(--destructive-foreground))",
        },
        muted: {
          DEFAULT: "hsl(var(--muted))",
          foreground: "hsl(var(--muted-foreground))",
        },
        accent: {
          DEFAULT: "hsl(var(--accent))",
          foreground: "hsl(var(--accent-foreground))",
        },
        border: "hsl(var(--border))",
        input: "hsl(var(--input))",
        ring: "hsl(var(--ring))",
        
        // Brand & Premium Colors (Used in Homeowner Portal)
        brand: {
          blue: {
            50: '#eff6ff',
            100: '#dbeafe',
            200: '#bfdbfe',
            300: '#93c5fd',
            400: '#60a5fa',
            500: '#3b82f6',
            600: '#2563eb',
            700: '#1d4ed8',
            800: '#1e40af',
            900: '#1e3a8a',
            950: '#172554',
          },
          emerald: '#10b981',
          amber: '#f59e0b',
          rose: '#f43f5e',
          slate: {
            900: '#0f172a',
            950: '#020617',
          }
        },

        // Salt & Pepper Theme (Core Identity)
        'salt': {
          white: '#FAFAFA',
          light: '#F5F5F5',
          gray: '#E5E7EB',
        },
        'pepper': {
          charcoal: '#1F2937',
          dark: '#111827',
          slate: '#374151',
        },
      },
      backdropBlur: {
        'sidebar': '14px',
      },
      animation: {
        'fadeIn': 'fadeIn 0.4s ease forwards',
        'spin': 'spin 1s linear infinite',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0', transform: 'translateY(-10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        }
      }
    },
  },
  plugins: [],
}
