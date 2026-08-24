<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$subject->name" :subtitle="$subject->code . ' · ' . ($academicYear?->name ?: '—') . ' · ' . ($semester?->name ?: '—')">
            <x-ui.button variant="secondary" :href="route('instructor.teaching.index')" wire:navigate>Back to My Classes</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-3">
            <x-ui.card class="lg:col-span-1">
                <h2 class="ui-section">Subject</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Code</dt>
                        <dd class="mt-1 font-medium">{{ $subject->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Department</dt>
                        <dd class="mt-1">{{ $subject->department?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Units</dt>
                        <dd class="mt-1">{{ $subject->units }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Sections</dt>
                        <dd class="mt-1 font-medium">{{ number_format($sections->count()) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Students</dt>
                        <dd class="mt-1 font-medium">{{ number_format($sections->sum('student_count')) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card class="lg:col-span-2">
                <h2 class="ui-section">Sections</h2>

                @if ($sections->isEmpty())
                    <x-ui.empty-state class="mt-4" title="No sections found." icon="layers">
                        There are no sections linked to this subject for the selected term.
                    </x-ui.empty-state>
                @else
                    <div class="ui-table-wrap mt-4">
                        <div class="overflow-x-auto">
                            <table class="ui-table">
                                <thead>
                                    <tr>
                                        <th>Section</th>
                                        <th>Program</th>
                                        <th>Year level</th>
                                        <th>Students</th>
                                        <th class="w-28"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sections as $entry)
                                        @php($section = $entry['section'])
                                        <tr>
                                            <td>
                                                <p class="font-medium">{{ $section->displayName() }}</p>
                                                @if ($section->code && $section->name !== $section->code)
                                                    <p class="text-sm text-muted">{{ $section->code }}</p>
                                                @endif
                                            </td>
                                            <td class="text-muted">{{ $section->program?->code ?: '—' }}</td>
                                            <td class="text-muted">{{ $section->yearLevel?->name ?: '—' }}</td>
                                            <td class="text-muted">{{ number_format($entry['student_count']) }}</td>
                                            <td class="text-right">
                                                <a
                                                    href="{{ route('instructor.teaching.section', ['subject' => $subject, 'section' => $section, 'academic_year_id' => $academicYearId, 'semester_id' => $semesterId]) }}"
                                                    class="text-sm font-medium hover:underline"
                                                    wire:navigate
                                                >
                                                    View roster
                                                </a>
                                            </td>
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
