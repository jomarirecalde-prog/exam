        <section>
            <h2 class="ui-kicker">Overview</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-ui.stat label="Assigned Subjects" :value="number_format($counts['assignedSubjects'])" />
                <x-ui.stat label="Sections" :value="number_format($counts['assignedSections'])" />
                <x-ui.stat label="Students" :value="number_format($counts['assignedStudents'])" />
                <x-ui.stat label="My Active Exams" :value="number_format($counts['activeExams'])" />
            </div>
        </section>

        <section>
            <div class="flex items-center justify-between gap-3">
                <h2 class="ui-section">My Classes</h2>
                <a href="{{ route('instructor.teaching.index') }}" class="text-sm font-medium hover:underline" wire:navigate>View all</a>
            </div>

            <div class="ui-table-wrap mt-4">
                @if ($teachingAssignments->isEmpty())
                    <div class="px-5">
                        <x-ui.empty-state title="No assigned subjects yet." icon="book-open">
                            Subjects assigned to you for the current term will appear here.
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
                                @foreach ($teachingAssignments as $assignment)
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
        </section>

        <section>
            <h2 class="ui-section">Upcoming Examinations</h2>
            <div class="ui-table-wrap mt-4">
                @if ($upcomingExams->isEmpty())
                    <div class="px-5">
                        <x-ui.empty-state title="No upcoming examinations." action="Create Examination" :action-href="route('examinations.create')" icon="clipboard-list">
                            Create an examination to see it here.
                        </x-ui.empty-state>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th>Examination</th>
                                    <th>Subject</th>
                                    <th>Section</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($upcomingExams as $exam)
                                    <tr>
                                        <td class="font-medium">{{ $exam->title }}</td>
                                        <td class="text-muted">{{ $exam->subject?->code }}</td>
                                        <td class="text-muted">{{ $exam->section?->name }}</td>
                                        <td><x-ui.badge :status="$exam->statusKey()" /></td>
                                        <td class="text-right">
                                            <a href="{{ route('examinations.index') }}" class="text-sm font-medium hover:underline">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
