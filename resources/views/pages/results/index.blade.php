<x-app-layout>
    @php
        $isStudent = auth()->user()?->hasRole('student');
    @endphp

    <div class="ui-page">
        @if ($isStudent)
            <x-ui.page-header
                title="Examination Results"
                subtitle="Your examination scores."
            />
            <x-ui.toolbar placeholder="Search results" />
            <div class="ui-table-wrap mt-4">
                @if ($grades->isEmpty())
                    <div class="px-5">
                        <x-ui.empty-state title="No results yet." icon="bar-chart-3">
                            Your scores will appear here after you submit an examination.
                        </x-ui.empty-state>
                    </div>
                @else
                    <div class="divide-y divide-line md:hidden">
                        @foreach ($grades as $grade)
                            <article class="px-4 py-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium">{{ $grade->examination?->title }}</p>
                                        <p class="mt-1 text-sm text-muted">{{ $grade->examination?->subject?->code }}</p>
                                    </div>
                                    <x-ui.badge :status="$grade->passed ? 'passed' : 'failed'" />
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div>
                                        <dt class="text-muted">Score</dt>
                                        <dd class="mt-0.5 font-medium">{{ rtrim(rtrim(number_format($grade->percentage, 1), '0'), '.') }}%</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted">Date</dt>
                                        <dd class="mt-0.5">{{ optional($grade->released_at ?: $grade->created_at)->format('M j, Y') }}</dd>
                                    </div>
                                </dl>
                                @if ($grade->examination_id)
                                    <div class="mt-3">
                                        <a href="{{ route('examinations.result', $grade->examination_id) }}" class="btn-ghost btn-sm">View</a>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Exam</th>
                                    <th>Subject</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($grades as $grade)
                                    <tr>
                                        <td class="font-medium">{{ $grade->examination?->title }}</td>
                                        <td class="text-muted">{{ $grade->examination?->subject?->code }}</td>
                                        <td>{{ rtrim(rtrim(number_format($grade->percentage, 1), '0'), '.') }}%</td>
                                        <td>
                                            <x-ui.badge :status="$grade->passed ? 'passed' : 'failed'" />
                                        </td>
                                        <td class="text-muted">{{ optional($grade->released_at ?: $grade->created_at)->format('M j, Y') }}</td>
                                        <td class="text-right">
                                            @if ($grade->examination_id)
                                                <a href="{{ route('examinations.result', $grade->examination_id) }}" class="btn-ghost btn-sm">View</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-line px-4 py-3">{{ $grades->links() }}</div>
                @endif
            </div>
        @else
            <x-ui.page-header
                title="Examination Results"
                subtitle="Select a completed examination to view each student's score."
            />

            <form method="GET" action="{{ route('results.index') }}" class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-end">
                <x-ui.field label="Find examination" class="w-full max-w-md">
                    <input
                        type="search"
                        name="q"
                        value="{{ $search ?? '' }}"
                        class="ui-input"
                        placeholder="Search by title or subject..."
                    />
                </x-ui.field>
                <div class="flex gap-2">
                    <button type="submit" class="btn-secondary">Search</button>
                    @if (($search ?? '') !== '')
                        <a href="{{ route('results.index') }}" class="btn-ghost">Clear</a>
                    @endif
                </div>
            </form>

            @if ($examinations->isEmpty())
                <x-ui.card class="mt-6">
                    <x-ui.empty-state title="No completed examinations yet." icon="bar-chart-3">
                        @if (($search ?? '') !== '')
                            No examinations matched your search. Try a different title or subject code.
                        @else
                            Examinations with submitted attempts will appear here once students finish taking them.
                        @endif
                    </x-ui.empty-state>
                </x-ui.card>
            @else
                <section class="mt-6">
                    <x-ui.card class="overflow-hidden" :padding="false">
                        <div class="divide-y divide-line">
                            @foreach ($examinations as $exam)
                                <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-medium uppercase tracking-wide text-muted">{{ $exam['subject_code'] ?? $exam['subject'] }}</p>
                                        <h3 class="mt-1 text-lg font-semibold">{{ $exam['title'] }}</h3>
                                        <p class="mt-1 text-sm text-muted">Section: {{ $exam['sections'] }}</p>
                                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-muted">
                                            <span>Status: <span class="font-medium text-ink">{{ $exam['status'] }}</span></span>
                                            <span>
                                                Submitted:
                                                <span class="font-semibold text-ink">{{ $exam['submitted_count'] }}</span>
                                                /
                                                <span>{{ $exam['eligible_count'] }}</span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="shrink-0">
                                        <a href="{{ $exam['results_url'] }}" class="btn-primary w-full sm:w-auto">
                                            View Results
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                    <div class="mt-4">{{ $examinations->withQueryString()->links() }}</div>
                </section>
            @endif
        @endif
    </div>
</x-app-layout>
