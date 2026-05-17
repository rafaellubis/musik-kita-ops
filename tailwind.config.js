import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
                sans: ['"DM Sans"', ...defaultTheme.fontFamily.sans],
                serif: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                mk: {
                    bg:        '#1A0E06',
                    sidebar:   '#1C1410',
                    card:      '#241608',
                    cardHover: '#2E1C0E',
                    border:    'rgba(212,168,83,0.08)',
                    accent:    '#D4A853',
                    accentDim: 'rgba(212,168,83,0.15)',
                    text:      '#EDE0CC',
                    muted:     '#8A6848',
                    dim:       '#6B4A2A',
                },
            },
        },
    },

    plugins: [forms],
};
