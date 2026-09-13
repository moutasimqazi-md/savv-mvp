import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Heebo', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                display: ['"Holtwood One SC"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                savv: {
                    orange: '#FE7B49',
                    blue: '#1592FF',
                    navy: '#0F2A44',
                    light: '#F7F7F7',
                    graylight: '#EEEEEE',
                    gray: '#8D8D8D',
                    darkgray: '#484848',
                    green: '#2DD654',
                    error: '#E90000',
                },
            },
        },
    },
    plugins: [forms],
};
