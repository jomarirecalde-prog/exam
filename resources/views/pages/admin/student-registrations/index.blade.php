<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Student Registrations" subtitle="Review and manage public student registration requests." />

        <form method="get" action="{{ route('admin.student-registrations.index') }}" class="ui-card ui-card-pad space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-ui.field label="Status" for="status">
                    <select id="status" name="status" class="ui-input">
                        <option value="">All statuses</option>
                        @foreach (['pending', 'approved', 'rejected'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Department" for="department_id">
                    <select id="department_id" name="department_id" class="ui-input">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((int) ($filters['department_id'] ?? 0) === $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Program" for="program_id">
                    <select id="program_id" name="program_id" class="ui-input">
                        <option value="">All programs</option>
                        @foreach ($programs as $program)
                            <option value="{{ $program->id }}" @selected((int) ($filters['program_id'] ?? 0) === $program->id)>{{ $program->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Year Level" for="year_level_id">
                    <select id="year_level_id" name="year_level_id" class="ui-input">
                        <option value="">All year levels</option>
                        @foreach ($yearLevels as $yearLevel)
                            <option value="{{ $yearLevel->id }}" @selected((int) ($filters['year_level_id'] ?? 0) === $yearLevel->id)>{{ $yearLevel->name }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Section" for="section_id">
                    <select id="section_id" name="section_id" class="ui-input">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected((int) ($filters['section_id'] ?? 0) === $section->id)>{{ $section->displayName() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field label="Search" for="q">
                    <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="ui-input" placeholder="Name, email, or student ID">
                </x-ui.field>
            </div>

            <div class="flex flex-wrap gap-2">
                <x-ui.button type="submit" size="sm">Apply Filters</x-ui.button>
                <x-ui.button variant="secondary" size="sm" :href="route('admin.student-registrations.index')" wire:navigate>Reset</x-ui.button>
            </div>
        </form>

        <div class="ui-table-wrap mt-6">
            @if ($registrations->isEmpty())
                <div class="px-5 py-8">
                    <x-ui.empty-state title="No registration requests found." icon="user-plus">
                        Student registration requests will appear here once submitted.
                    </x-ui.empty-state>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student ID</th>
                                <th>Program</th>
                                <th>Year Level</th>
                                <th>Section</th>
                                <th>Email</th>
                                <th>Registered</th>
                                <th>Status</th>
                                <th class="w-24"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($registrations as $registration)
                                <tr>
                                    <td class="font-medium">{{ $registration->user?->fullName() ?: '—' }}</td>
                                    <td class="text-muted">{{ $registration->student_id }}</td>
                                    <td class="text-muted">{{ $registration->program?->name ?: '—' }}</td>
                                    <td class="text-muted">{{ $registration->yearLevel?->name ?: '—' }}</td>
                                    <td class="text-muted">{{ $registration->section?->displayName() ?: '—' }}</td>
                                    <td class="text-muted">{{ $registration->user?->email }}</td>
                                    <td class="text-muted">{{ $registration->registered_at?->format('M j, Y') ?: '—' }}</td>
                                    <td>
                                        <x-ui.badge :status="$registration->registration_status?->value ?? 'pending'" />
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.student-registrations.show', $registration) }}" class="btn-ghost btn-sm" wire:navigate>View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-line px-4 py-3 text-sm text-muted">
                    {{ $registrations->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
