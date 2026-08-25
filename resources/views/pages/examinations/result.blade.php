<x-app-layout>
    <div class="ui-page">
        @php
            $score = $grade?->raw_score;
            $total = $grade?->total_points ?: $examination->total_items;
            $percent = $grade?->percentage;
            $passed = (bool) $grade?->passed;
            $pendingGrading = $grade?->status === \App\Enums\ResultStatus::PendingGrading;
            $summary = $breakdown['summary'] ?? null;
            $formatPoints = fn (?float $value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 2), '0'), '.');
        @endphp

        <p class="ui-kicker">{{ $examination->subject?->code }}</p>
        <h1 class="mt-2 ui-title">{{ $examination->title }}</h1>

        @if (! $grade)
            <x-ui.card class="mt-8">
                <x-ui.empty-state title="Results are not available yet." icon="bar-chart-3">
                    Complete and submit the examination to view your score.
                </x-ui.empty-state>
            </x-ui.card>
        @else
            @if ($pendingGrading)
                <x-ui.alert type="warning" class="mt-8">
                    Examination submitted. Some answers require manual grading. Your score below reflects auto-graded questions; essay scores will appear once your instructor finishes grading.
                </x-ui.alert>
            @endif

            <section class="mt-10">
                <p class="text-sm text-muted">Your Score</p>
                <p class="mt-2 text-4xl font-semibold tracking-tight">
                    {{ $formatPoints($score !== null ? (float) $score : null) }} / {{ $formatPoints($total !== null ? (float) $total : null) }}
                </p>
                <p class="mt-2 text-lg text-muted">{{ $formatPoints($percent !== null ? (float) $percent : null) }}%</p>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    @if ($pendingGrading)
                        <x-ui.badge status="pending_grading">Pending Grading</x-ui.badge>
                    @else
                        <x-ui.badge :status="$passed ? 'passed' : 'failed'" />
                    @endif
                </div>
                @if (! $pendingGrading && $percent !== null)
                    <div class="mt-6 h-2 overflow-hidden rounded-full bg-brand-soft" aria-hidden="true">
                        <div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, (float) $percent)) }}%"></div>
                    </div>
                @endif
            </section>

            @if ($summary && $summary['total'] > 0)
                <section class="mt-12">
                    <h2 class="ui-section">Performance</h2>
                    <dl class="mt-4 grid max-w-sm gap-3 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">Correct</dt><dd class="font-medium">{{ $summary['correct'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">Incorrect</dt><dd class="font-medium">{{ $summary['incorrect'] }}</dd></div>
                        @if ($summary['pending'] > 0)
                            <div class="flex justify-between"><dt class="text-muted">Pending review</dt><dd class="font-medium">{{ $summary['pending'] }}</dd></div>
                        @endif
                        @if ($summary['unanswered'] > 0)
                            <div class="flex justify-between"><dt class="text-muted">Unanswered</dt><dd class="font-medium">{{ $summary['unanswered'] }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-muted">Total</dt><dd class="font-medium">{{ $summary['total'] }}</dd></div>
                    </dl>
                </section>
            @endif

            @if (! empty($breakdown['items']))
                <section class="mt-12">
                    <h2 class="ui-section">Answer Review</h2>
                    <div class="mt-6 space-y-4">
                        @foreach ($breakdown['items'] as $item)
                            @php
                                $outcomeBadge = match ($item['outcome']) {
                                    'correct' => ['status' => 'passed', 'label' => 'Correct'],
                                    'incorrect' => ['status' => 'failed', 'label' => 'Incorrect'],
                                    'pending' => ['status' => 'pending_grading', 'label' => 'Pending review'],
                                    'graded' => ['status' => 'approved', 'label' => 'Graded'],
                                    default => ['status' => 'draft', 'label' => 'Unanswered'],
                                };
                            @endphp
                            <x-ui.card class="p-5">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-muted">Question {{ $item['number'] }}</p>
                                        <p class="mt-1 text-sm text-muted">{{ $item['type_label'] }}</p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-ui.badge :status="$outcomeBadge['status']">{{ $outcomeBadge['label'] }}</x-ui.badge>
                                        <span class="text-sm font-medium tabular-nums">
                                            @if ($item['requires_manual_grading'] && ! $item['is_graded'])
                                                Pending / {{ $formatPoints((float) $item['points']) }} pts
                                            @else
                                                {{ $formatPoints($item['points_earned']) }} / {{ $formatPoints((float) $item['points']) }} pts
                                            @endif
                                        </span>
                                    </div>
                                </div>

                                <p class="mt-4 text-base leading-7">{{ $item['text'] }}</p>

                                <dl class="mt-5 space-y-4 text-sm">
                                    <div>
                                        <dt class="font-medium text-muted">Your answer</dt>
                                        <dd class="mt-1 whitespace-pre-wrap leading-6">
                                            {{ $item['student_answer'] ?: 'No answer provided.' }}
                                        </dd>
                                    </div>

                                    @if (! empty($item['correct_answer']))
                                        <div>
                                            <dt class="font-medium text-muted">Correct answer</dt>
                                            <dd class="mt-1 whitespace-pre-wrap leading-6">{{ $item['correct_answer'] }}</dd>
                                        </div>
                                    @endif

                                    @if (! empty($item['feedback']))
                                        <div>
                                            <dt class="font-medium text-muted">Instructor feedback</dt>
                                            <dd class="mt-1 whitespace-pre-wrap leading-6">{{ $item['feedback'] }}</dd>
                                        </div>
                                    @endif

                                    @if (! empty($item['explanation']))
                                        <div>
                                            <dt class="font-medium text-muted">Explanation</dt>
                                            <dd class="mt-1 whitespace-pre-wrap leading-6">{{ $item['explanation'] }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </x-ui.card>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    </div>
</x-app-layout>
