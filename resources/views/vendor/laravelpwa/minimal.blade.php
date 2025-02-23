<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>

    <style>
        /* Farbschema als CSS-Variablen */
        :root {
            --color-gray-50: #f9fafb;
            --color-gray-100: #f0f1f3;
            --color-gray-200: #e1e3e6;
            --color-gray-300: #d2d4d7;
            --color-gray-400: #b3b6ba;
            --color-gray-500: #5a5a5a;
            --color-gray-600: #484848;
            --color-gray-700: #363636;
            --color-gray-800: #242424;
            --color-gray-900: #080808;
            --color-gray-950: #010101;
        }

        /* Basis-Stile */
        html {
            line-height: 1.5;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            margin: 0;
        }

        a {
            background-color: transparent;
            color: inherit;
            text-decoration: none;
        }

        [hidden] {
            display: none;
        }

        /* Utility-Klassen */
        .antialiased {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .relative {
            position: relative;
        }

        .flex {
            display: flex;
        }

        .items-top {
            align-items: flex-start;
        }

        .items-center {
            align-items: center;
        }

        .justify-center {
            justify-content: center;
        }

        .min-h-screen {
            min-height: 100vh;
        }

        .mx-auto {
            margin-left: auto;
            margin-right: auto;
        }

        .mb-5 {
            margin-bottom: 1.25rem;
        }

        .mt-2 {
            margin-top: 0.5rem;
        }

        .ml-4 {
            margin-left: 1rem;
        }

        .block {
            display: block;
        }

        .h-16 {
            height: 4rem;
        }

        .pt-8 {
            padding-top: 2rem;
        }

        .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }

        .px-6 {
            padding-left: 1.5rem;
            padding-right: 1.5rem;
        }

        .text-lg {
            font-size: 1.125rem;
        }

        .uppercase {
            text-transform: uppercase;
        }

        .tracking-wider {
            letter-spacing: 0.05em;
        }

        .border-r {
            border-right: 1px solid;
        }

        .flex-col {
            flex-direction: column;
        }

        /* Farben */
        .bg-gray-100 {
            background-color: var(--color-gray-100);
        }

        .text-gray-500 {
            color: var(--color-gray-500);
        }

        .border-gray-400 {
            border-color: var(--color-gray-400);
        }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            .dark\:bg-gray-900 {
                background-color: var(--color-gray-900);
            }
        }

        /* Standard (Light Mode) */
        .logo-dark {
            display: none;
        }

        /* Wenn das bevorzugte Farbschema des Nutzers Dark Mode ist */
        @media (prefers-color-scheme: dark) {
            .logo-light {
                display: none;
            }

            .logo-dark {
                display: block;
            }
        }

        /* Responsive Varianten */
        @media (min-width: 640px) {
            .sm\:items-center {
                align-items: center;
            }

            .sm\:px-6 {
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }

            .sm\:pt-0 {
                padding-top: 0;
            }

            .sm\:justify-start {
                justify-content: flex-start;
            }
        }

        @media (min-width: 1024px) {
            .lg\:px-8 {
                padding-left: 2rem;
                padding-right: 2rem;
            }
        }
    </style>

    <style>
        /* Sekundäre Schriftfamilie */
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif,
                "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
        }
    </style>
</head>

<body class="antialiased">
    <div
        class="relative flex items-top justify-center min-h-screen bg-gray-100 dark:bg-gray-900 sm:items-center sm:pt-0">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 flex items-center flex-col">
            <div class="mb-5">
                <a class="block" href="/">
                    <img src="/images/logo/logo-144x144.png" class="logo-light h-16" alt="Logo Light" />
                    <img src="/images/logo/logo-144x144-dark.png" class="logo-dark h-16" alt="Logo Dark" />
                </a>
            </div>
            <div class="flex items-center pt-8 sm:justify-start sm:pt-0 mt-2">
                <div class="px-4 text-lg text-gray-500 border-r border-gray-400 tracking-wider">
                    @yield('code')
                </div>
                <div class="ml-4 text-lg text-gray-500 uppercase tracking-wider">
                    @yield('message')
                </div>
            </div>
        </div>
    </div>
</body>

</html>
