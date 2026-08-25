<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="All Examinations" subtitle="Create and review examinations.">
            @if (auth()->user()->hasAnyRole(['superadmin', 'admin', 'instructor']))
                <x-ui.button :href="route('examinations.create')" icon="plus">Create Examination</x-ui.button>
            @endif
        </x-ui.page-header>

        <x-ui.toolbar placeholder="Search examinations">
            <select class="ui-input w-auto py-2 text-sm" aria-label="Filter status">
                <option>All statuses</option>
                <option>Draft</option>
                <option>Published</option>
                <option>Active</option>
                <option>Closed</option>
            </select>
        </x-ui.toolbar>

        <div class="ui-table-wrap mt-4">
            @if ($exams->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No examinations yet." action="Create Examination" :action-href="route('examinations.create')">
                        Create your first examination to get started.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Examination</th>
                                <th>Subject</th>
                                <th>Period</th>
                                <th>Sections</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($exams as $exam)
                                <tr>
                                    <td class="font-medium">
                                        {{ $exam->title }}
                                        @if ($exam->needs_section_review)
                                            <p class="mt-1 text-xs font-normal text-warning-ink">Needs section review</p>
                                        @endif
                                    </td>
                                    <td class="text-muted">{{ $exam->subject?->code }}</td>
                                    <td class="text-muted">{{ $exam->periodLabel() }}</td>
                                    <td>
                                        <x-examinations.section-badges :sections="$exam->sections" />
                                    </td>
                                    <td><x-ui.badge :status="$exam->statusKey()" /></td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            @if (auth()->user()->hasRole('student'))
                                                @php
                                                    $attempt = $studentAttempts[$exam->id] ?? null;
                                                @endphp
                                                @if ($attempt?->pending_submission_at)
                                                    <x-ui.badge status="pending">Submission Pending</x-ui.badge>
                                                @elseif ($attempt?->status === \App\Enums\AttemptStatus::InProgress)
                                                    <a href="{{ route('examinations.take', $exam) }}" class="btn-ghost btn-sm" wire:navigate>Resume</a>
                                                @elseif ($attempt?->status === \App\Enums\AttemptStatus::LockedViolationLimit)
                                                    <x-ui.badge status="failed">Locked</x-ui.badge>
                                                @elseif ($attempt?->status?->isTerminal())
                                                    <x-ui.badge status="submitted">Submitted</x-ui.badge>
                                                    <a href="{{ route('examinations.result', $exam) }}" class="btn-ghost btn-sm" wire:navigate>View Result</a>
                                                @else
                                                    <a href="{{ route('examinations.take', $exam) }}" class="btn-ghost btn-sm" wire:navigate>Take Exam</a>
                                                @endif
                                            @else
                                                <a href="{{ route('examinations.take', $exam) }}" class="btn-ghost btn-sm" wire:navigate>Take Exam</a>
                                                @if (auth()->user()->hasAnyRole(['superadmin', 'admin']) || auth()->user()->can('update', $exam))
                                                    <a href="{{ route('examinations.edit', $exam) }}" class="btn-ghost btn-sm" wire:navigate>Edit</a>
                                                @endif
                                            @endif
                                            <button type="button" class="btn-icon h-8 w-8" aria-label="More"><x-icon name="more-horizontal" :size="16" /></button>
                                        </div>
                                    </td>
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
