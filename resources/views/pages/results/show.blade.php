<x-app-layout>
    @php
        $sections = $examination->sections->pluck('name')->filter()->join(', ')
            ?: ($examination->subjectOffering?->section?->name ?? 'Unassigned');
        $formatPercent = fn (?float $value) => $value === null ? '—' : rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    @endphp

    <div class="ui-page">
        <x-ui.page-header :title="$examination->title" :subtitle="$examination->subject?->name ?? $examination->subject?->code">
            <a href="{{ route('results.index') }}" class="btn-secondary">Back to Examinations</a>
        </x-ui.page-header>

        <p class="mb-6 text-sm text-muted">
            Section: {{ $sections }}
            · Status: {{ $examination->status->label() }}
        </p>

        <form method="GET" action="{{ route('results.show', $examination) }}" class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <x-ui.field label="Switch examination" class="w-full max-w-md">
                <select
                    name="examination_id"
                    class="ui-input"
                    onchange="if (this.value) window.location.href = this.value"
                >
                    @foreach ($completedExaminations as $option)
                        <option
                            value="{{ route('results.show', $option) }}"
                            @selected($option->id === $examination->id)
                        >
                            {{ $option->title }}@if ($option->subject?->code) ({{ $option->subject->code }})@endif
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Search students" class="w-full max-w-md">
                <input
                    type="search"
                    name="q"
                    value="{{ $search }}"
                    class="ui-input"
                    placeholder="Search by name or student ID..."
                />
            </x-ui.field>

            <div class="flex gap-2">
                <button type="submit" class="btn-secondary">Search</button>
                @if ($search !== '')
                    <a href="{{ route('results.show', $examination) }}" class="btn-ghost">Clear</a>
                @endif
            </div>
        </form>

        <section class="mt-8">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                @foreach ([
                    ['label' => 'Submitted', 'value' => $summary['submitted']],
                    ['label' => 'Passed', 'value' => $summary['passed']],
                    ['label' => 'Failed', 'value' => $summary['failed']],
                    ['label' => 'Pending Grading', 'value' => $summary['pending']],
                    ['label' => 'Average Score', 'value' => $summary['submitted'] > 0 ? $formatPercent($summary['average']) : '—'],
                ] as $stat)
                    <div class="ui-card ui-card-pad">
                        <p class="text-sm text-muted">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-ink">{{ $stat['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="ui-table-wrap mt-8">
            @if ($attempts->isEmpty())
                <div class="px-5 py-8">
                    <x-ui.empty-state title="No student submissions yet." icon="bar-chart-3">
                        @if ($search !== '')
                            No students matched your search.
                        @else
                            Submitted attempts will appear here once students finish the examination.
                        @endif
                    </x-ui.empty-state>
                </div>
            @else
                <div class="divide-y divide-line md:hidden">
                    @foreach ($attempts as $attempt)
                        @php
                            $grade = $attempt->grade;
                            $studentName = $attempt->student?->user?->fullName() ?: $attempt->student?->user?->name;
                            $status = $grade?->status === \App\Enums\ResultStatus::PendingGrading
                                ? 'pending_grading'
                                : ($grade ? ($grade->passed ? 'passed' : 'failed') : 'draft');
                        @endphp
                        <article class="px-4 py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $studentName ?: 'Unknown student' }}</p>
                                    <p class="mt-1 text-sm text-muted">{{ $attempt->student?->student_id }}</p>
                                </div>
                                <x-ui.badge :status="$status" />
                            </div>
                            <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <div>
                                    <dt class="text-muted">Score</dt>
                                    <dd class="mt-0.5 font-medium">{{ $formatPercent($grade?->percentage !== null ? (float) $grade->percentage : null) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Submitted</dt>
                                    <dd class="mt-0.5">{{ optional($attempt->submitted_at)->format('M j, Y g:i A') ?: '—' }}</dd>
                                </div>
                            </dl>
                            @if ($attempt->student_id)
                                <div class="mt-3">
                                    <a
                                        href="{{ route('examinations.result', ['examination' => $examination, 'student' => $attempt->student_id]) }}"
                                        class="btn-ghost btn-sm"
                                    >
                                        View Details
                                    </a>
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>

                <div class="hidden overflow-x-auto md:block">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($attempts as $attempt)
                                @php
                                    $grade = $attempt->grade;
                                    $studentName = $attempt->student?->user?->fullName() ?: $attempt->student?->user?->name;
                                    $status = $grade?->status === \App\Enums\ResultStatus::PendingGrading
                                        ? 'pending_grading'
                                        : ($grade ? ($grade->passed ? 'passed' : 'failed') : 'draft');
                                @endphp
                                <tr>
                                    <td class="font-medium">{{ $studentName ?: 'Unknown student' }}</td>
                                    <td class="text-muted">{{ $attempt->student?->student_id ?: '—' }}</td>
                                    <td>{{ $formatPercent($grade?->percentage !== null ? (float) $grade->percentage : null) }}</td>
                                    <td>
                                        <x-ui.badge :status="$status" />
                                    </td>
                                    <td class="text-muted">{{ optional($attempt->submitted_at)->format('M j, Y g:i A') ?: '—' }}</td>
                                    <td class="text-right">
                                        @if ($attempt->student_id)
                                            <a
                                                href="{{ route('examinations.result', ['examination' => $examination, 'student' => $attempt->student_id]) }}"
                                                class="btn-ghost btn-sm"
                                            >
                                                View Details
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
