<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=fallback" rel="stylesheet" />

        <!-- PWA: installable as an app (manifest, icons, service worker) -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#f9fafb" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
        <!-- Favicon: the transparent logo, dark lines on light tabs and white lines on dark tabs (the SVG
             switches by itself); Safari takes the ICO. ?v against cached old icons. -->
        <link rel="icon" href="/favicon.ico?v=4" sizes="48x48">
        <link rel="icon" href="/favicon.svg?v=4" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/images/pwa/apple-touch-icon.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="meSync">
        <script>
            // The install prompt can fire before Vue is mounted: keep it for the install button
            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                window.__pwaInstallPrompt = event;
                window.dispatchEvent(new Event('pwa-installable'));
            });

            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
            }
        </script>

        <!-- Scripts -->
        @routes

        @vite(['resources/css/app.css', 'resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body>
        <script>
            if (localStorage.getItem('sidebar-expanded') == 'true') {
                document.querySelector('body').classList.add('sidebar-expanded');
            } else {
                document.querySelector('body').classList.remove('sidebar-expanded');
            }
        </script>
        <x-inertia::app />
    </body>
</html>
