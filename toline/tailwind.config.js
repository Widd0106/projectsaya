import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
    extend: {
      fontFamily: {
        sans: ['Figtree', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        toline: {
          DEFAULT: '#4C3FE0',
          dark: '#3A2FB8',
          darker: '#241C7A',
          light: '#EEEBFF',
        },
      },
      borderRadius: {
        xl2: '1rem',
      },
    },
  },

    plugins: [forms],
};
