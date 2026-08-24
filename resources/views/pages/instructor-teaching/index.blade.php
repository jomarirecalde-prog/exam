<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="My Classes" subtitle="Subjects, sections, and students assigned to you." />

        <form method="get" action="{{ route('instructor.teaching.index') }}" class="grid gap-4 sm:grid-cols-3">
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
            @if ($assignments->isEmpty())
                <div class="px-5">
                    <x-ui.empty-state title="No assigned subjects yet." icon="book-open">
                        Subjects assigned to you for the selected term will appear here.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Term</th>
                                <th>Sections</th>
                                <th>Students</th>
                                <th class="w-28"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                <tr>
                                    <td>
                                        <p class="font-medium">{{ $assignment['subject']->name }}</p>
                                        <p class="text-sm text-muted">{{ $assignment['subject']->code }}</p>
                                    </td>
                                    <td class="text-muted">
                                        {{ $assignment['academic_year']?->name ?: '—' }}
                                        <span class="text-faint">·</span>
                                        {{ $assignment['semester']?->name ?: '—' }}
                                    </td>
                                    <td class="text-muted">{{ number_format($assignment['section_count']) }}</td>
                                    <td class="text-muted">{{ number_format($assignment['student_count']) }}</td>
                                    <td class="text-right">
                                        <a
                                            href="{{ route('instructor.teaching.show', ['subject' => $assignment['subject'], 'academic_year_id' => $assignment['academic_year_id'], 'semester_id' => $assignment['semester_id']]) }}"
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
