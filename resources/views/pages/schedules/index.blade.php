<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Schedules" subtitle="Upcoming and past examination windows." />
        <div class="ui-table-wrap mt-4">
            @if ($exams->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No schedules yet." icon="calendar">
                        Scheduled examinations will appear in this calendar list.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Examination</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($exams as $exam)
                                <tr>
                                    <td class="font-medium">{{ $exam->title }}</td>
                                    <td class="text-muted">{{ optional($exam->examination_date)->format('M j, Y') ?: '—' }}</td>
                                    <td class="text-muted">{{ $exam->start_time ? substr($exam->start_time, 0, 5) : '—' }}</td>
                                    <td><x-ui.badge :status="$exam->statusKey()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-4 py-3">{{ $exams->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
