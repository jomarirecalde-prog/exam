<x-app-layout>
    <div class="ui-page">
        @php
            use App\Enums\ResultStatus;

            $score = $grade?->raw_score;
            $total = $grade?->total_points ?: $examination->total_items;
            $percent = $grade?->percentage;
            $passed = (bool) $grade?->passed;
            $correct = $score !== null ? (int) round($score) : null;
            $incorrect = $correct !== null && $total ? max(0, (int) $total - $correct) : null;
            $pendingGrading = $grade?->status === ResultStatus::PendingGrading;
        @endphp

        <p class="ui-kicker">{{ $examination->subject?->code }}</p>
        <h1 class="mt-2 ui-title">{{ $examination->title }}</h1>

        @if (! $grade)
            <x-ui.card class="mt-8">
                <x-ui.empty-state title="Results are not available yet." icon="bar-chart-3">
                    Complete and submit the examination to view your score.
                </x-ui.empty-state>
            </x-ui.card>
        @elseif ($pendingGrading && auth()->user()?->hasRole('student'))
            <x-ui.card class="mt-8">
                <x-ui.empty-state title="Examination submitted." icon="bar-chart-3">
                    Some answers require manual grading. Your final score will update here once grading is complete.
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <section class="mt-10">
                <p class="text-sm text-muted">Your Score</p>
                <p class="mt-2 text-4xl font-semibold tracking-tight">{{ rtrim(rtrim(number_format($score, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($total, 2), '0'), '.') }}</p>
                <p class="mt-2 text-lg text-muted">{{ rtrim(rtrim(number_format($percent, 1), '0'), '.') }}%</p>
                <div class="mt-4">
                    <x-ui.badge :status="$passed ? 'passed' : 'failed'" />
                </div>
                <div class="mt-6 h-2 overflow-hidden rounded-full bg-brand-soft" aria-hidden="true">
                    <div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, (float) $percent)) }}%"></div>
                </div>
            </section>

            <section class="mt-12">
                <h2 class="ui-section">Performance</h2>
                <dl class="mt-4 grid max-w-sm gap-3 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">Correct</dt><dd class="font-medium">{{ $correct }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Incorrect</dt><dd class="font-medium">{{ $incorrect }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">Total</dt><dd class="font-medium">{{ (int) $total }}</dd></div>
                </dl>
            </section>
        @endif
    </div>
</x-app-layout>
