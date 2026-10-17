<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>DxClinic — Live Queue Window</title>

    <!-- Theme Bootstrap: Default strictly DAY/LIGHT mode unless explicitly stored 'night' -->
    <script>
        (function() {
            try {
                var theme = localStorage.getItem('dxclinic_livequeue_theme');
                if (theme === 'night' || theme === 'dark') {
                    document.documentElement.classList.add('theme-night', 'dark');
                } else {
                    document.documentElement.classList.remove('theme-night', 'dark');
                }
            } catch(e) {}
        })();
    </script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900" rel="stylesheet" />

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="font-sans antialiased h-full flex flex-col overflow-x-hidden selection:bg-blue-600 selection:text-white transition-colors duration-200">
    {{ $slot }}

    @livewireScripts
</body>
</html>

