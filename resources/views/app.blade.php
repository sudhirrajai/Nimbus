<!-- resources/views/app.blade.php -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/png" href="/favicon.png?v=2" />

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        
        <!-- Material Dashboard CSS and Fonts -->
        <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
        <link href="/assets/css/nucleo-icons.css" rel="stylesheet" />
        <link href="/assets/css/nucleo-svg.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
        <link id="pagestyle" href="/assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />

        <!-- Theme Initializer (Zero Flash) -->
        <script>
            (function() {
                try {
                    const saved = localStorage.getItem('nimbus_theme');
                    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const isDark = saved ? (saved === 'dark') : prefersDark;
                    if (isDark) {
                        document.documentElement.classList.add('dark');
                        if (document.body) {
                            document.body.classList.add('dark-version');
                        } else {
                            document.addEventListener('DOMContentLoaded', function() {
                                document.body.classList.add('dark-version');
                            });
                        }
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (e) {}
            })();
        </script>

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
        <script>
            document.addEventListener('show.bs.modal', function(e) {
                if (e.target && e.target.parentElement !== document.body) {
                    document.body.appendChild(e.target);
                }
            });
        </script>
        <style>
            body {
                overflow-x: hidden;
                overflow-y: hidden; /* Prevent double vertical scrollbar */
            }
            .main-content {
                overflow-y: auto;
                overflow-x: hidden;
            }
            /* Universal icon centering fix */
            .icon-shape, .timeline-step {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            .icon-shape i, .timeline-step i {
                margin: 0 !important;
                position: static !important;
                transform: none !important;
            }
            /* Rigid sidebar fix: prevent aside container from scrolling */
            .sidenav, #sidenav-main {
                display: block !important;
                overflow: hidden !important;
                overflow-x: hidden !important;
                overflow-y: hidden !important;
            }
            .sidenav > .ps__rail-y, .sidenav > .ps__rail-x {
                display: none !important;
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        @inertia
        
        <!-- Github buttons -->
        <script async defer src="https://buttons.github.io/buttons.js"></script>
    </body>
</html>