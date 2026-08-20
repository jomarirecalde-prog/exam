<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Examination Results" subtitle="Released scores and pending grades." />
        <x-ui.toolbar placeholder="Search results" />
        <div class="ui-table-wrap mt-4">
            @if ($grades->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No results yet." icon="bar-chart-3">
                        Results appear here after grading and release.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Exam</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grades as $grade)
                                <tr>
                                    <td class="font-medium">{{ $grade->student?->user?->fullName() ?: $grade->student?->user?->name }}</td>
                                    <td class="text-muted">{{ $grade->examination?->title }}</td>
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
