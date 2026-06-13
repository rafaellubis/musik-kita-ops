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
                sans: ['"Hanken Grotesk"', '"DM Sans"', ...defaultTheme.fontFamily.sans],
                serif: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                surface: '#f6faf8',
                'surface-dim': '#d7dbd9',
                'surface-bright': '#f6faf8',
                'surface-container-lowest': '#ffffff',
                'surface-container-low': '#f0f4f2',
                'surface-container': '#ebefed',
                'surface-container-high': '#e5e9e7',
                'surface-container-highest': '#dfe3e1',
                'on-surface': '#181d1c',
                'on-surface-variant': '#544341',
                'inverse-surface': '#2d3130',
                'inverse-on-surface': '#eef2f0',
                outline: '#877270',
                'outline-variant': '#dac1bf',
                'surface-tint': '#954742',
                primary: '#2a0002',
                'on-primary': '#ffffff',
                'primary-container': '#4a0e0e',
                'on-primary-container': '#cc726d',
                'inverse-primary': '#ffb3ad',
                secondary: '#2c694e',
                'on-secondary': '#ffffff',
                'secondary-container': '#aeeecb',
                'on-secondary-container': '#316e52',
                tertiary: '#00120c',
                'on-tertiary': '#ffffff',
                'tertiary-container': '#132821',
                'on-tertiary-container': '#799087',
                error: '#ba1a1a',
                'on-error': '#ffffff',
                'error-container': '#ffdad6',
                'on-error-container': '#93000a',
                'primary-fixed': '#ffdad7',
                'primary-fixed-dim': '#ffb3ad',
                'on-primary-fixed': '#3d0506',
                'on-primary-fixed-variant': '#77302d',
                'secondary-fixed': '#b1f0ce',
                'secondary-fixed-dim': '#95d4b3',
                'on-secondary-fixed': '#002114',
                'on-secondary-fixed-variant': '#0e5138',
                'tertiary-fixed': '#cfe8dd',
                'tertiary-fixed-dim': '#b3ccc1',
                'on-tertiary-fixed': '#091f19',
                'on-tertiary-fixed-variant': '#354b43',
                background: '#f6faf8',
                'on-background': '#181d1c',
                'surface-variant': '#dfe3e1',
                mk: {
                    bg:          '#F5F9F7',              // canvas utama (light, sedikit mint)
                    sidebar:     '#4A1F0A',              // mahoni gelap — sidebar + primary button
                    card:        '#FFFFFF',              // surface card / bg putih
                    cardHover:   '#EAF5EF',              // hover mint terang
                    surface:     'rgba(93,184,144,0.06)',// table header / section bg
                    surfaceHover:'rgba(93,184,144,0.10)',// row hover bg
                    border:      'rgba(93,184,144,0.22)',// mint border standar
                    borderLight: 'rgba(93,184,144,0.12)',// border lebih halus
                    liburStatus: 'rgba(206, 131, 250, 0.3)',              // status libur (biru)
                    accent:      '#5DB890',              // mint accent
                    accentDim:   'rgba(93,184,144,0.15)',// mint redup
                    text:        '#3A2015',              // teks utama konten
                    muted:       '#7A3818',              // teks sekunder
                    dim:         '#C47A45',              // teks redup (tanggal, hint)
                },
            },
        },
    },

    plugins: [forms],
};
