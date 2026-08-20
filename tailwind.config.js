import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Support/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                canvas: 'var(--ui-bg)',
                surface: 'var(--ui-surface)',
                elevated: 'var(--ui-elevated)',
                ink: 'var(--ui-ink)',
                muted: 'var(--ui-muted)',
                faint: 'var(--ui-faint)',
                line: 'var(--ui-line)',
                brand: {
                    DEFAULT: 'var(--ui-brand)',
                    hover: 'var(--ui-brand-hover)',
                    soft: 'var(--ui-brand-soft)',
                },
                navy: {
                    50: '#F4F6F8',
                    100: '#E8ECF1',
                    200: '#C9D3DE',
                    300: '#9AADC0',
                    400: '#6B849E',
                    500: '#3D5A73',
                    600: '#2A4158',
                    700: '#1C3147',
                    800: '#142536',
                    900: '#0F1E2D',
                    950: '#0B1622',
                },
                success: {
                    DEFAULT: 'var(--ui-success)',
                    soft: 'var(--ui-success-soft)',
                    ink: 'var(--ui-success-ink)',
                },
                warning: {
                    DEFAULT: 'var(--ui-warning)',
                    soft: 'var(--ui-warning-soft)',
                    ink: 'var(--ui-warning-ink)',
                },
                danger: {
                    DEFAULT: 'var(--ui-danger)',
                    soft: 'var(--ui-danger-soft)',
                    ink: 'var(--ui-danger-ink)',
                },
                info: {
                    DEFAULT: 'var(--ui-info)',
                    soft: 'var(--ui-info-soft)',
                    ink: 'var(--ui-info-ink)',
                },
            },
            borderRadius: {
                input: '8px',
                btn: '8px',
                card: '12px',
                modal: '16px',
            },
            boxShadow: {
                card: '0 1px 2px rgba(15, 23, 42, 0.04)',
                pop: '0 8px 24px rgba(15, 23, 42, 0.08)',
                focus: '0 0 0 3px var(--ui-focus-ring)',
            },
            spacing: {
                sidebar: '16.5rem',
                'sidebar-collapsed': '4.5rem',
                topbar: '4rem',
            },
            fontSize: {
                'display': ['2rem', { lineHeight: '2.5rem', fontWeight: '700', letterSpacing: '-0.03em' }],
                'title': ['1.5rem', { lineHeight: '2rem', fontWeight: '650', letterSpacing: '-0.02em' }],
                'section': ['1.125rem', { lineHeight: '1.75rem', fontWeight: '600', letterSpacing: '-0.01em' }],
            },
            maxWidth: {
                content: '80rem',
            },
            transitionDuration: {
                ui: '180ms',
            },
        },
    },

    plugins: [forms],
};
