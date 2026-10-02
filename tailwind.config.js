import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Brand palette, sampled from the Made Easy logo (public/images/logo.png).
 *
 * Each scale puts the logo colour at 500 and is generated from it by moving HSL
 * lightness towards white (50-400) or black (600-950), so every tint and shade
 * stays on the same hue. Regenerate all steps together if a base colour changes.
 *
 * Contrast (WCAG AA): white on accent 7.6:1, accent on white 7.6:1, ink on accent
 * only 2.3:1, so accent backgrounds take white text. Ink on secondary (6.4:1) and
 * on highlight (7.7:1) pass; white on either fails, so those keep ink text.
 */
const ink = '#001848'; // deep navy: text, borders, dark buttons, the footer

const blue_scale = {
    50: '#eff4fc',
    100: '#d7e3f8',
    200: '#adc7f4',
    300: '#72a0f1',
    400: '#216df5',
    500: '#0048c8',
    600: '#003daa',
    700: '#00328c',
    800: '#00286e',
    900: '#001d50',
    950: '#001232',
    DEFAULT: '#0048c8',
};

const green_scale = {
    50: '#f6fcee',
    100: '#e9f8d4',
    200: '#d4f3a7',
    300: '#b8f067',
    400: '#97f40f',
    500: '#68b000',
    600: '#589600',
    700: '#497b00',
    800: '#396100',
    900: '#2a4600',
    950: '#1a2c00',
    DEFAULT: '#68b000',
};

const gold_scale = {
    50: '#fcf9f1',
    100: '#f9f0dc',
    200: '#f5e1b6',
    300: '#f3cf80',
    400: '#f6bb38',
    500: '#e8a000',
    600: '#c58800',
    700: '#a27000',
    800: '#805800',
    900: '#5d4000',
    950: '#3a2800',
    DEFAULT: '#e8a000',
};

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Headings only (h1-h3 get it from resources/css/app.css). Never for prices: its ₦ reads like ₳.
                display: ['Kodchasan', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Blue is the one dominant colour for primary actions; green and gold are used sparingly.
                ink,
                accent: blue_scale,
                secondary: green_scale,
                highlight: gold_scale,
                surface: '#f7f7f5',
                // Older templates use brand-*: kept as the same blue so the whole site stays consistent.
                brand: blue_scale,
            },
            boxShadow: {
                // Hard offset shadow for clickable cards and primary buttons (flat illustration style).
                pop: `4px 4px 0 0 ${ink}`,
                'pop-sm': `2px 2px 0 0 ${ink}`,
            },
            keyframes: {
                'rise-in': {
                    from: { opacity: '0', transform: 'translateY(12px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                // The homepage hero's one motion moment; always used with motion-safe:.
                'rise-in': 'rise-in 500ms ease-out both',
            },
        },
    },

    plugins: [forms],
};
