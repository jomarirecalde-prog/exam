<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$subject->name" :subtitle="$subject->code">
            <x-ui.button variant="secondary" :href="route('subjects.edit', $subject)" icon="pencil" wire:navigate>Edit</x-ui.button>
        </x-ui.page-header>

        <x-ui.card class="max-w-2xl">
            <h2 class="ui-section">Subject</h2>
            <dl class="mt-4 space-y-4 text-sm">
                <div>
                    <dt class="text-muted">Code</dt>
                    <dd class="mt-1 font-medium">{{ $subject->code }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Name</dt>
                    <dd class="mt-1 font-medium">{{ $subject->name }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Department</dt>
                    <dd class="mt-1 font-medium">{{ $subject->department?->name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Units</dt>
                    <dd class="mt-1 font-medium">{{ $subject->units }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Description</dt>
                    <dd class="mt-1 leading-6">{{ $subject->description ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Status</dt>
                    <dd class="mt-1"><x-ui.badge :status="$subject->is_active ? 'active' : 'closed'" /></dd>
                </div>
            </dl>
        </x-ui.card>

        @if ($instructorAssignments->isNotEmpty())
            <x-ui.card class="max-w-2xl">
                <h2 class="ui-section">Assigned Instructors</h2>
                <ul class="mt-4 space-y-4 text-sm">
                    @foreach ($instructorAssignments as $assignment)
                        @php
                            $section = $sections->get($assignment->pivot->section_id);
                            $academicYear = $academicYears->get($assignment->pivot->academic_year_id);
                            $semester = $semesters->get($assignment->pivot->semester_id);
                        @endphp
                        <li class="rounded-lg border border-line p-4">
                            <p class="font-medium">{{ $assignment->user?->fullName() ?: $assignment->employee_id }}</p>
                            <p class="mt-1 text-muted">{{ $assignment->employee_id }}</p>
                            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div>
                                    <dt class="text-muted">Academic year</dt>
                                    <dd class="mt-1">{{ $academicYear?->name ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-muted">Semester</dt>
                                    <dd class="mt-1">{{ $semester?->name ?: '—' }}</dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-muted">Section</dt>
                                    <dd class="mt-1">{{ $section ? trim(($section->code ?: $section->name) . ($section->code && $section->name !== $section->code ? ' — ' . $section->name : '')) : 'All sections' }}</dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
