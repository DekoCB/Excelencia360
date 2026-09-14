import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Helvetica Neue', 'Arial', 'sans-serif'],
                display: ['Quantico', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Arial', 'sans-serif'],
                // Títulos de la web pública (marca Excelencia 360); el cuerpo sigue en Inter.
                brand: ['Plus Jakarta Sans', 'Inter', 'ui-sans-serif', 'system-ui', 'Segoe UI', 'Arial', 'sans-serif'],
                mono: ['ui-monospace', 'Cascadia Code', 'SFMono-Regular', 'Consolas', 'Liberation Mono', 'monospace'],
            },
            colors: {
                // Identidad Excelencia 360 (ver :root --e360-* en app.css).
                e360: {
                    primary: 'rgb(var(--e360-primary) / <alpha-value>)',
                    'primary-dark': 'rgb(var(--e360-primary-dark) / <alpha-value>)',
                    'primary-deep': 'rgb(var(--e360-primary-deep) / <alpha-value>)',
                    'primary-deeper': 'rgb(var(--e360-primary-deeper) / <alpha-value>)',
                    'primary-tint': 'rgb(var(--e360-primary-tint) / <alpha-value>)',
                    secondary: 'rgb(var(--e360-secondary) / <alpha-value>)',
                    'secondary-dark': 'rgb(var(--e360-secondary-dark) / <alpha-value>)',
                    'secondary-tint': 'rgb(var(--e360-secondary-tint) / <alpha-value>)',
                    accent: 'rgb(var(--e360-accent) / <alpha-value>)',
                    'accent-light': 'rgb(var(--e360-accent-light) / <alpha-value>)',
                    'accent-deep': 'rgb(var(--e360-accent-deep) / <alpha-value>)',
                    'accent-deeper': 'rgb(var(--e360-accent-deeper) / <alpha-value>)',
                    'accent-tint': 'rgb(var(--e360-accent-tint) / <alpha-value>)',
                    white: 'rgb(var(--e360-white) / <alpha-value>)',
                    text: 'rgb(var(--e360-text) / <alpha-value>)',
                    heading: 'rgb(var(--e360-heading) / <alpha-value>)',
                    muted: 'rgb(var(--e360-muted) / <alpha-value>)',
                    surface: 'rgb(var(--e360-surface) / <alpha-value>)',
                    border: 'rgb(var(--e360-border) / <alpha-value>)',
                },
                bg: 'rgb(var(--color-bg) / <alpha-value>)',
                'bg-secondary': 'rgb(var(--color-bg-secondary) / <alpha-value>)',
                surface: 'rgb(var(--color-surface) / <alpha-value>)',
                'surface-2': 'rgb(var(--color-surface-2) / <alpha-value>)',
                'surface-elevated': 'rgb(var(--color-surface-elevated) / <alpha-value>)',
                border: 'rgb(var(--color-border) / <alpha-value>)',
                ink: 'rgb(var(--color-text) / <alpha-value>)',
                'ink-dim': 'rgb(var(--color-text-dim) / <alpha-value>)',
                'ink-faint': 'rgb(var(--color-text-faint) / <alpha-value>)',
                accent: 'rgb(var(--color-accent) / <alpha-value>)',
                'accent-soft': 'rgb(var(--color-accent-soft) / <alpha-value>)',
                ok: 'rgb(var(--color-ok) / <alpha-value>)',
                warn: 'rgb(var(--color-warn) / <alpha-value>)',
                danger: 'rgb(var(--color-danger) / <alpha-value>)',
                info: 'rgb(var(--color-info) / <alpha-value>)',
            },
        },
    },

    plugins: [forms],
};
