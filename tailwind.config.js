import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Orange scale around the working-direction accent (#ff5734 at 500).
 * 700 and darker pass WCAG AA as text on white; 500 needs ink text on top.
 */
const orange_scale = {
    50: '#fff3ef',
    100: '#ffe4db',
    200: '#ffc6b4',
    300: '#ffa184',
    400: '#ff7a56',
    500: '#ff5734',
    600: '#ed3f1b',
    700: '#c22f12',
    800: '#9c2812',
    900: '#7e2514',
    950: '#440f06',
    DEFAULT: '#ff5734',
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
                // Working design direction, not the final brand (Made Easy's logo and colours are pending).
                // Orange is the one dominant colour for primary actions; purple and yellow are used sparingly.
                ink: '#151313',
                accent: orange_scale,
                secondary: '#be94f5',
                highlight: '#fccc42',
                surface: '#f7f7f5',
                // Older templates use brand-*: kept as the same orange so the whole site stays consistent.
                brand: orange_scale,
            },
            boxShadow: {
                // Hard offset shadow for clickable cards and primary buttons (flat illustration style).
                pop: '4px 4px 0 0 #151313',
                'pop-sm': '2px 2px 0 0 #151313',
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
