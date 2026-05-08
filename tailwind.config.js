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
                    bg:        '#0F1117',
                    sidebar:   '#161B2E',
                    card:      '#1E2235',
                    cardHover: '#252B42',
                    border:    'rgba(255,255,255,0.06)',
                    accent:    '#D4A853',
                    accentDim: 'rgba(212,168,83,0.15)',
                    text:      '#E8EAF0',
                    muted:     '#8B92A8',
                    dim:       '#555D7A',
                },
            },
        },
    },

    plugins: [forms],
};
