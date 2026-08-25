<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if(auth()->user()?->hasRole('student') && auth()->user()?->student)
            <meta name="exam-student-id" content="{{ auth()->user()->student->id }}">
            <meta name="offline-bootstrap-url" content="{{ route('offline.bootstrap') }}">
        @endif
        <meta name="theme-color" content="#142536">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/icon-192.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/icons/icon-192.svg">
        <title>{{ $title ?? config('examination.institution.name', config('app.name')) }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
        <script>
            (function () {
                const pref = localStorage.getItem('exam-theme') || 'system';
                const dark = pref === 'dark' || (pref !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
            })();
        </script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="h-full bg-canvas font-sans text-ink antialiased" x-data="examShell()">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-btn focus:bg-surface focus:px-3 focus:py-2">Skip to content</a>

        @include('layouts.partials.sidebar')

        <div
            class="min-h-full transition-[padding] duration-ui"
            :class="collapsed ? 'lg:pl-sidebar-collapsed' : 'lg:pl-sidebar'"
        >
            <livewire:layout.navigation />

            <main id="main-content" class="pb-12">
                @if (session('status'))
                    <div class="hidden" x-init="toast(@js(session('status')))"></div>
                @endif
                @if (session('error'))
                    <div class="hidden" x-init="toast(@js(session('error')), 'error')"></div>
                @endif
                {{ $slot }}
            </main>
        </div>

        <div
            x-show="mobileOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-30 bg-navy-950/40 lg:hidden"
            @click="mobileOpen = false"
        ></div>

        <x-ui.toast-stack />

        <div
            x-data="pwaInstallPrompt()"
            x-init="init()"
            x-show="canInstall || (updateAvailable && !updateDeferred)"
            x-cloak
            class="fixed bottom-4 right-4 z-40 max-w-sm rounded-modal border border-line bg-surface p-4 shadow-pop"
        >
            <template x-if="canInstall">
                <div>
                    <p class="text-sm font-semibold">Install Examination System</p>
                    <p class="mt-1 text-xs leading-5 text-muted">Install this application for faster access and offline examination support.</p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="btn-primary btn-sm" @click="install()">Install App</button>
                        <button type="button" class="btn-ghost btn-sm" @click="dismiss()">Not now</button>
                    </div>
                </div>
            </template>
            <template x-if="updateAvailable && !updateDeferred">
                <div>
                    <p class="text-sm font-semibold">Update available</p>
                    <p class="mt-1 text-xs text-muted">A new version of the application is available.</p>
                    <button type="button" class="btn-primary btn-sm mt-3" @click="applyUpdate()">Update now</button>
                </div>
            </template>
        </div>

        @livewireScripts
    </body>
</html>
