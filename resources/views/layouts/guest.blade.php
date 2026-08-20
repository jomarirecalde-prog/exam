<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ?? 'Sign in — '.config('examination.institution.name', config('app.name')) }}</title>
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
        <div class="grid min-h-screen lg:grid-cols-2">
            <aside class="relative hidden flex-col justify-between bg-[var(--ui-sidebar)] px-12 py-12 text-white lg:flex">
                <div>
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-3" wire:navigate>
                        <x-application-logo class="h-9 w-9 text-white" />
                        <span class="text-sm font-medium tracking-wide text-white/80">{{ config('examination.institution.name') }}</span>
                    </a>
                    <h1 class="mt-16 max-w-sm text-3xl font-semibold leading-tight tracking-tight">Examination Platform</h1>
                    <p class="mt-4 max-w-sm text-sm leading-7 text-white/65">
                        Create, conduct, monitor, and evaluate examinations in one calm, professional workspace.
                    </p>
                </div>
                <div class="h-24 w-24 rounded-card border border-white/10 bg-white/5" aria-hidden="true"></div>
            </aside>

            <div class="flex items-center justify-center px-4 py-10 sm:px-8">
                <div class="w-full max-w-[400px]">
                    <div class="mb-8 flex items-center gap-3 lg:hidden">
                        <x-application-logo class="h-8 w-8 text-brand" />
                        <span class="text-sm font-medium">{{ config('examination.institution.name') }}</span>
                    </div>
                    <div class="ui-card ui-card-pad sm:p-8">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>
        @livewireScripts
    </body>
</html>
