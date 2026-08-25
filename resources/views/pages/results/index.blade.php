<x-app-layout>
    @php
        $isStudent = auth()->user()?->hasRole('student');
    @endphp

    <div class="ui-page">
        <x-ui.page-header
            title="Examination Results"
            :subtitle="$isStudent ? 'Your examination scores.' : 'Released scores and pending grades.'"
        />
        <x-ui.toolbar placeholder="Search results" />
        <div class="ui-table-wrap mt-4">
            @if ($grades->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No results yet." icon="bar-chart-3">
                        @if ($isStudent)
                            Your scores will appear here after you submit an examination.
                        @else
                            Results appear here after grading and release.
                        @endif
                    </x-ui.empty-state>
                </div>
            @else
                <div class="divide-y divide-line md:hidden">
                    @foreach ($grades as $grade)
                        <article class="px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $grade->examination?->title }}</p>
                                    @unless ($isStudent)
                                        <p class="mt-1 text-sm text-muted">{{ $grade->student?->user?->fullName() ?: $grade->student?->user?->name }}</p>
                                    @endunless
                                    @if ($isStudent)
                                        <p class="mt-1 text-sm text-muted">{{ $grade->examination?->subject?->code }}</p>
                                    @endif
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
                                @unless ($isStudent)
                                    <th>Student</th>
                                @endunless
                                <th>Exam</th>
                                @if ($isStudent)
                                    <th>Subject</th>
                                @endif
                                <th>Score</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grades as $grade)
                                <tr>
                                    @unless ($isStudent)
                                        <td class="font-medium">{{ $grade->student?->user?->fullName() ?: $grade->student?->user?->name }}</td>
                                    @endunless
                                    <td class="{{ $isStudent ? 'font-medium' : 'text-muted' }}">{{ $grade->examination?->title }}</td>
                                    @if ($isStudent)
                                        <td class="text-muted">{{ $grade->examination?->subject?->code }}</td>
                                    @endif
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
    </div>
</x-app-layout>
