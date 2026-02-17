/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Satoshi', 'system-ui', 'sans-serif'],
      },
      colors: {
        primary: 'var(--color-primary)',
        secondary: 'var(--color-secondary)',
        accent: 'var(--color-accent)',
        'accent-foreground': 'var(--color-accent-foreground)',
        destructive: 'var(--color-destructive)',
        'destructive-foreground': 'var(--color-destructive-foreground)',
        background: 'var(--color-background)',
        foreground: 'var(--color-foreground)',
        card: 'var(--color-card)',
        'card-foreground': 'var(--color-card-foreground)',
        popover: 'var(--color-popover)',
        'popover-foreground': 'var(--color-popover-foreground)',
        muted: 'var(--color-muted)',
        'muted-foreground': 'var(--color-muted-foreground)',
        border: 'var(--color-border)',
        input: 'var(--color-input)',
        ring: 'var(--color-ring)',
        blue: {
          100: 'var(--color-blue-100)',
          200: 'var(--color-blue-200)',
          brand: 'var(--color-blue-brand)',
        },
        pink: {
          100: 'var(--color-pink-100)',
          200: 'var(--color-pink-200)',
        },
        green: {
          100: 'var(--color-green-100)',
          200: 'var(--color-green-200)',
        },
        yellow: {
          100: 'var(--color-yellow-100)',
        },
      },
      borderRadius: {
        DEFAULT: '0.75rem',
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
}
