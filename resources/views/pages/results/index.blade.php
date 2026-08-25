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
                <div class="overflow-x-auto">
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
