import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import plugin from 'tailwindcss/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/css/**/*.{css,scss}',
        "./src/**/*.{html,js,ts,jsx,tsx}",
    ],

    theme: {
        extend: {
            colors: {
                primary: {
                    50:  "#E6F7ED",
                    100: "#C1EAD4",
                    200: "#99DDBA",
                    300: "#72D09F",
                    400: "#4BC486",
                    500: "#278F5A",  
                    600: "#207A4C",
                    700: "#18603B",
                    800: "#10452A",
                    900: "#082918",
                    950 : "#04150E"
                },
                gray: {
                    50:  "#f9fafb",
                    100: "#f3f4f6",
                    200: "#e5e7eb",
                    300: "#cfd2d6",
                    400: "#a9adb3",
                    500: "#181818",
                    600: "#141414",
                    700: "#101010",
                    800: "#0c0c0c",
                    900: "#080808",
                    950: "#030303"
                },
                background: "#F8F8F8",
                text: "#333333"        
              },
            fontFamily: {
                inter: ["Inter", "sans-serif"],
            },
            boxShadow: {
                sm: "0 1px 1px 0 rgb(0 0 0 / 0.05), 0 1px 2px 0 rgb(0 0 0 / 0.02)",
            },
            screens: {
                xs: "480px",
            },
        },
    },

    plugins: [forms, typography, plugin(function ({ addVariant }) {
        addVariant("dark", "&:is(.dark *)");
        addVariant("sidebar-expanded", "&:is(.sidebar-expanded *)");
    })],
};
