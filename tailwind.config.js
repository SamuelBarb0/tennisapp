import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    safelist: [
        'bg-blue-50', 'bg-green-50', 'bg-purple-50', 'bg-yellow-50', 'bg-red-50', 'bg-orange-50',
        'text-blue-500', 'text-green-500', 'text-purple-500', 'text-yellow-500', 'text-red-500', 'text-orange-500',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Helvetica Neue', 'Helvetica', 'Arial', ...defaultTheme.fontFamily.sans],
                mono: ['Roboto Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                'tc': {
                    'primary': '#1b3d5d',
                    'primary-hover': '#142d45',
                    'primary-dark': '#0e1f30',
                    'accent': '#eee539',
                    'gray-light': '#d8e3e5',
                    'gray': '#c5c5c5',
                    'gray-dark': '#413c42',
                    'australian': '#2980de',
                    'wimbledon': '#6b37b6',
                    'roland': '#c05c34',
                    'usopen': '#0a79c3',
                },
            },
        },
    },

    plugins: [forms],
};
