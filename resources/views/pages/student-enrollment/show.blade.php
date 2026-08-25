<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header
            :title="$subject->name"
            :subtitle="$subject->code . ' · ' . ($academicYear?->name ?: '—') . ' · ' . ($semester?->name ?: '—')"
        >
            <x-ui.button variant="secondary" :href="route('student.enrollment.index')" wire:navigate>Back to My Subjects</x-ui.button>
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
                    @if (filled($subject->description))
                        <div>
                            <dt class="text-muted">Description</dt>
                            <dd class="mt-1">{{ $subject->description }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card class="lg:col-span-2">
                <h2 class="ui-section">Enrollment</h2>
                <dl class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                    <div>
                        <dt class="text-muted">Section</dt>
                        <dd class="mt-1 font-medium">{{ $section->displayName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Program</dt>
                        <dd class="mt-1">{{ $section->program?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Year level</dt>
                        <dd class="mt-1">{{ $section->yearLevel?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Term</dt>
                        <dd class="mt-1">
                            {{ $academicYear?->name ?: '—' }}
                            <span class="text-faint">·</span>
                            {{ $semester?->name ?: '—' }}
                        </dd>
                    </div>
                </dl>

                <h3 class="ui-kicker mt-8">Instructor</h3>

                @if ($instructors->isEmpty())
                    <x-ui.empty-state class="mt-4" title="No instructor assigned yet." icon="users">
                        Your instructor will appear here once assigned to this subject.
                    </x-ui.empty-state>
                @else
                    <div class="mt-4 space-y-4">
                        @foreach ($instructors as $instructor)
                            <article class="rounded-lg border border-line px-4 py-4">
                                <p class="font-medium">{{ $instructor->user?->fullName() ?: '—' }}</p>
                                <dl class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">
                                    <div>
                                        <dt class="text-muted">Employee ID</dt>
                                        <dd class="mt-1">{{ $instructor->employee_id ?: '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-muted">Department</dt>
                                        <dd class="mt-1">{{ $instructor->department?->name ?: '—' }}</dd>
                                    </div>
                                    @if ($instructor->user?->email)
                                        <div class="sm:col-span-2">
                                            <dt class="text-muted">Email</dt>
                                            <dd class="mt-1">{{ $instructor->user->email }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </article>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
