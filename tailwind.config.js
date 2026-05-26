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
