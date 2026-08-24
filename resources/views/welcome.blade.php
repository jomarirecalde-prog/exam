<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('examination.institution.name') }} — Examination Platform</title>
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
    </head>
    <body class="bg-canvas font-sans text-ink antialiased">
        <header class="border-b border-line bg-surface">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <x-application-logo class="h-8 w-8 text-brand" />
                    <span class="text-sm font-semibold tracking-tight">{{ config('examination.institution.name') }}</span>
                </a>
                <nav class="flex items-center gap-2">
                    @auth
                        <x-ui.button href="{{ route('dashboard') }}">Dashboard</x-ui.button>
                    @else
                        <x-ui.button href="{{ route('student-registration.create') }}" variant="secondary">Register</x-ui.button>
                        <x-ui.button href="{{ route('login') }}">Sign In</x-ui.button>
                    @endauth
                </nav>
            </div>
        </header>

        <main>
            <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
                <p class="ui-kicker">Academic examinations</p>
                <h1 class="mt-4 max-w-3xl text-display sm:text-4xl">Modern Examination Management Platform</h1>
                <p class="mt-5 max-w-xl text-[17px] leading-8 text-muted">
                    Create, conduct, monitor, and evaluate examinations in one platform — online or on a local campus network.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('login') }}">Sign In</x-ui.button>
                    <x-ui.button href="#features" variant="secondary">Learn More</x-ui.button>
                </div>
            </section>

            <section class="border-y border-line bg-surface">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                        <div class="max-w-xl">
                            <h2 class="text-xl font-semibold tracking-tight sm:text-2xl">Are you a student?</h2>
                            <p class="mt-3 text-sm leading-7 text-muted sm:text-base">
                                Create your student account to access examinations, announcements, grades, and other available services.
                            </p>
                            <p class="mt-4 text-sm text-muted">
                                Already registered?
                                <a href="{{ route('login') }}" class="font-medium text-ink underline-offset-4 hover:underline">Sign in</a>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <x-ui.button href="{{ route('student-registration.create') }}">Register as Student</x-ui.button>
                            <x-ui.button href="{{ route('login') }}" variant="secondary">Sign In</x-ui.button>
                        </div>
                    </div>
                </div>
            </section>

            <section id="features" class="border-t border-line bg-surface">
                <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                    <h2 class="ui-section">Everything needed to run examinations</h2>
                    <p class="mt-2 max-w-xl text-muted">A focused workspace for academic staff and students — without clutter.</p>
                    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['clipboard-list', 'Online & Offline Exams', 'Conduct examinations in connected classrooms or fully offline LAN environments.'],
                            ['shield', 'Secure Examination', 'Timer controls, attempt snapshots, and monitoring designed for academic integrity.'],
                            ['check-circle', 'Automated Grading', 'Objective items are scored immediately. Essays remain in a clear review queue.'],
                            ['file-question', 'Question Bank', 'Reuse questions across prelim, midterm, and final examinations.'],
                            ['activity', 'Real-Time Monitoring', 'See who is in progress, who has submitted, and where attention is needed.'],
                            ['bar-chart-3', 'Examination Reports', 'Release results and export summaries without leaving the platform.'],
                        ] as [$icon, $title, $copy])
                            <article class="ui-card ui-card-pad">
                                <x-icon :name="$icon" :size="20" class="text-muted" />
                                <h3 class="mt-4 text-base font-semibold">{{ $title }}</h3>
                                <p class="mt-2 text-sm leading-6 text-muted">{{ $copy }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
                <h2 class="ui-section">How it works</h2>
                <ol class="mt-10 grid gap-6 sm:grid-cols-5">
                    @foreach ([
                        'Create Examination',
                        'Assign Students',
                        'Conduct Examination',
                        'Automatic/Manual Grading',
                        'Release Results',
                    ] as $index => $step)
                        <li>
                            <p class="text-xs font-medium text-faint">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</p>
                            <p class="mt-2 text-sm font-medium leading-6">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>
            </section>
        </main>

        <footer class="border-t border-line py-8 text-center text-sm text-muted">
            {{ config('examination.institution.name') }}
        </footer>
    </body>
</html>
