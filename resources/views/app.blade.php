<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=fallback" rel="stylesheet" />

        <!-- PWA -->
        @laravelPWA

        <!-- Scripts -->
        @routes

        @vite(['resources/css/app.css', 'resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body>
        <script>
            function updateFavicon() {
                const favicon = document.querySelector('link[rel="icon"][sizes="512x512"]');
                if (!favicon) return;
                
                const darkModeActive = window.matchMedia('(prefers-color-scheme: dark)').matches;
                favicon.href = darkModeActive ? "/images/logo/logo-512x512-dark.png?v=2" : "/images/logo/logo-512x512.png?v=2";
            }

            updateFavicon();

            const darkModeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            darkModeMediaQuery.addEventListener('change', updateFavicon);


            if (localStorage.getItem('sidebar-expanded') == 'true') {
                document.querySelector('body').classList.add('sidebar-expanded');
            } else {
                document.querySelector('body').classList.remove('sidebar-expanded');
            }
        </script>
        <x-inertia::app />
    </body>
</html>
