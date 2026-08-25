<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="My Subjects" subtitle="Subjects you are enrolled in for the current term." />

        <form method="get" action="{{ route('student.enrollment.index') }}" class="grid gap-4 sm:grid-cols-3">
            <x-ui.field label="Academic year" for="academic_year_id">
                <select id="academic_year_id" name="academic_year_id" class="ui-input">
                    <option value="">All academic years</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected((int) $academicYearId === (int) $year->id)>
                            {{ $year->name }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            <x-ui.field label="Semester" for="semester_id">
                <select id="semester_id" name="semester_id" class="ui-input">
                    <option value="">All semesters</option>
                    @foreach ($semesters as $semester)
                        <option value="{{ $semester->id }}" @selected((int) $semesterId === (int) $semester->id)>
                            {{ $semester->name }}
                        </option>
                    @endforeach
                </select>
            </x-ui.field>

            <div class="flex items-end">
                <x-ui.button variant="secondary" type="submit" size="sm">Apply filter</x-ui.button>
            </div>
        </form>

        <div class="ui-table-wrap mt-4">
            @if ($enrollments->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No enrolled subjects yet." icon="book-open">
                        Subjects assigned to your section for the selected term will appear here.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Section</th>
                                <th>Instructor</th>
                                <th>Term</th>
                                <th class="w-28"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($enrollments as $enrollment)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $enrollment['subject']->name }}</p>
                                        <p class="text-sm text-muted">{{ $enrollment['subject']->code }} · {{ $enrollment['subject']->units }} units</p>
                                    </td>
                                    <td class="text-muted">{{ $enrollment['section']->displayName() }}</td>
                                    <td class="text-muted">
                                        @if ($enrollment['instructors']->isEmpty())
                                            —
                                        @else
                                            {{ $enrollment['instructors']->map(fn ($instructor) => $instructor->user?->fullName() ?: '—')->implode(', ') }}
                                        @endif
                                    </td>
                                    <td class="text-muted">
                                        {{ $enrollment['academic_year']?->name ?: '—' }}
                                        <span class="text-faint">·</span>
                                        {{ $enrollment['semester']?->name ?: '—' }}
                                    </td>
                                    <td class="text-right">
                                        <a
                                            href="{{ route('student.enrollment.show', ['subject' => $enrollment['subject'], 'academic_year_id' => $enrollment['academic_year_id'], 'semester_id' => $enrollment['semester_id']]) }}"
                                            class="text-sm font-medium hover:underline"
                                            wire:navigate
                                        >
                                            View
                                        </a>
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
