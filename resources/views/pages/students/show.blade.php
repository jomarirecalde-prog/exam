<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$student->user?->fullName() ?: $student->student_id" subtitle="Student profile">
            <x-ui.button variant="secondary" :href="route('students.edit', $student)" icon="pencil" wire:navigate>Edit</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card>
                <h2 class="ui-section">Personal Information</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">First Name</dt>
                        <dd class="mt-1 font-medium">{{ $student->user?->first_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Middle Name</dt>
                        <dd class="mt-1 font-medium">{{ $student->user?->middle_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Last Name</dt>
                        <dd class="mt-1 font-medium">{{ $student->user?->last_name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Suffix</dt>
                        <dd class="mt-1 font-medium">{{ $student->user?->suffix ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Sex / Gender</dt>
                        <dd class="mt-1 font-medium">{{ $student->sex ? ucfirst($student->sex) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Date of Birth</dt>
                        <dd class="mt-1 font-medium">{{ $student->date_of_birth?->format('M j, Y') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Contact Number</dt>
                        <dd class="mt-1 font-medium">{{ $student->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Email</dt>
                        <dd class="mt-1">{{ $student->user?->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Home Address</dt>
                        <dd class="mt-1 font-medium">{{ $student->home_address ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="ui-section">Academic Information</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Student ID</dt>
                        <dd class="mt-1 font-medium">{{ $student->student_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Department</dt>
                        <dd class="mt-1 font-medium">{{ $student->program?->department?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Program</dt>
                        <dd class="mt-1 font-medium">{{ $student->program?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Year Level</dt>
                        <dd class="mt-1 font-medium">{{ $student->yearLevel?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Section</dt>
                        <dd class="mt-1 font-medium">{{ $student->section?->displayName() ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="mt-1"><x-ui.badge :status="$student->is_active ? 'active' : 'closed'" /></dd>
                    </div>
                    <div>
                        <dt class="text-muted">Username</dt>
                        <dd class="mt-1">{{ $student->user?->username ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
