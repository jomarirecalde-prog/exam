<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Create Student Account — '.config('examination.institution.name', config('app.name')) }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <script>
            (function () {
                const pref = localStorage.getItem('exam-theme') || 'system';
                const dark = pref === 'dark' || (pref !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-canvas font-sans text-ink antialiased">
        <div class="mx-auto min-h-screen max-w-2xl px-4 py-8 sm:px-6 sm:py-12">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-ink">
                <x-icon name="arrow-left" :size="16" />
                Back to Home
            </a>

            {{ $slot }}
        </div>
        @livewireScripts
    </body>
</html>
