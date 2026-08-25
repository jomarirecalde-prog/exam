<x-app-layout>
    <div class="ui-page">
        <header class="space-y-2">
            <h1 class="ui-title">{{ $greeting }}.</h1>
            <p class="text-[15px] leading-7 text-muted">Here are your upcoming examinations.</p>
        </header>

        <section class="mt-10">
            <h2 class="ui-kicker">Upcoming</h2>
            <div class="mt-4 space-y-4">
                @forelse ($upcomingExams as $exam)
                    <article class="ui-card ui-card-pad flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold">{{ $exam->title }}</h3>
                            <p class="mt-1 text-sm text-muted">{{ $exam->subject?->code }} — {{ $exam->subject?->name }}</p>
                            <p class="mt-3 text-sm text-muted">{{ $exam->duration_minutes }} minutes</p>
                        </div>
                        <x-ui.button :href="route('examinations.take', $exam)" variant="secondary">View Examination</x-ui.button>
                    </article>
                @empty
                    <x-ui.card>
                        <x-ui.empty-state title="No upcoming examinations." icon="clipboard-list">
                            When your instructors publish an exam, it will appear here.
                        </x-ui.empty-state>
                    </x-ui.card>
                @endforelse
            </div>
        </section>

        <section class="mt-12">
            <h2 class="ui-kicker">Completed</h2>
            <div class="mt-4 space-y-3">
                @forelse ($completedAttempts as $attempt)
                    <article class="ui-card ui-card-pad flex items-center justify-between">
                        <div>
                            <p class="font-medium">{{ $attempt->examination?->title }}</p>
                            <p class="mt-1 text-sm text-muted">{{ optional($attempt->submitted_at)->format('M j, Y') }}</p>
                        </div>
                        <x-ui.badge status="closed" />
                    </article>
                @empty
                    <p class="text-sm text-muted">You have no completed examinations yet.</p>
                @endforelse
            </div>
        </section>

        <section class="mt-12">
            <h2 class="ui-kicker">Results</h2>
            <div class="mt-4 space-y-3">
                @forelse ($releasedGrades as $grade)
                    <article class="ui-card ui-card-pad flex items-center justify-between">
                        <div>
                            <p class="font-medium">{{ $grade->examination?->title }}</p>
                            <p class="mt-1 text-sm text-muted">{{ rtrim(rtrim(number_format($grade->percentage, 2), '0'), '.') }}%</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-ui.badge :status="$grade->passed ? 'passed' : 'failed'" />
                            <a href="{{ route('examinations.result', $grade->examination_id) }}" class="text-sm font-medium hover:underline">View</a>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-muted">Released results will appear here.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
