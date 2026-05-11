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
                sans:    ['Barlow', ...defaultTheme.fontFamily.sans],
                bc:      ['"Barlow Condensed"', ...defaultTheme.fontFamily.sans],
                figtree: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                pitch: {
                    950: '#020817',
                    900: '#0a1628',
                    800: '#0f2140',
                    700: '#163058',
                },
                gold: {
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                },
                bolao: {
                    bg:      '#0d0f12',
                    bg2:     '#13161b',
                    bg3:     '#1c2029',
                    bg4:     '#252b38',
                    accent:  '#f5a623',
                    accent2: '#e8930d',
                    muted:   '#7a8394',
                    muted2:  '#4a5568',
                    green:   '#22c55e',
                    red:     '#ef4444',
                    blue:    '#3b82f6',
                },
            },
            animation: {
                'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                'fade-in':    'fadeIn 0.18s ease-out',
                'slide-up':   'slideUp 0.18s ease-out',
                'live-blink': 'liveBlink 1.5s ease-in-out infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%':   { opacity: '0', transform: 'translateY(4px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideUp: {
                    '0%':   { opacity: '0', transform: 'translateY(8px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                liveBlink: {
                    '0%, 100%': { opacity: '1' },
                    '50%':      { opacity: '0.3' },
                },
            },
        },
    },

    plugins: [forms],
};
