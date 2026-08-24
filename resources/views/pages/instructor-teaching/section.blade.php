<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$section->displayName()" :subtitle="$subject->code . ' · ' . ($section->academicYear?->name ?: '—') . ' · ' . ($section->semester?->name ?: '—')">
            <x-ui.button
                variant="secondary"
                :href="route('instructor.teaching.show', ['subject' => $subject, 'academic_year_id' => $academicYearId, 'semester_id' => $semesterId])"
                wire:navigate
            >
                Back to Subject
            </x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card class="lg:col-span-1">
                <h2 class="ui-section">Section</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Subject</dt>
                        <dd class="mt-1 font-medium">{{ $subject->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Program</dt>
                        <dd class="mt-1">{{ $section->program?->code ?: '—' }} — {{ $section->program?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Year level</dt>
                        <dd class="mt-1">{{ $section->yearLevel?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Students</dt>
                        <dd class="mt-1 font-medium">{{ number_format($students->count()) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card class="lg:col-span-2">
                <h2 class="ui-section">Student Roster</h2>

                @if ($students->isEmpty())
                    <x-ui.empty-state class="mt-4" title="No students in this section." icon="graduation-cap">
                        Enrolled students for this term will appear here.
                    </x-ui.empty-state>
                @else
                    <div class="ui-table-wrap mt-4">
                        <div class="overflow-x-auto">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Student ID</th>
                                        <th>Name</th>
                                        <th>Program</th>
                                        <th>Year level</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($students as $student)
                                        <tr>
                                            <td class="font-medium">{{ $student->student_id }}</td>
                                            <td>{{ $student->user?->fullName() ?: '—' }}</td>
                                            <td class="text-muted">{{ $student->program?->code ?: '—' }}</td>
                                            <td class="text-muted">{{ $student->yearLevel?->name ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
