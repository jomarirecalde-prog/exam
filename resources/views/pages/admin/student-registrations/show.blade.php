<x-app-layout>
    <div class="ui-page" x-data="{ approveOpen: false, rejectOpen: false }">
        <x-ui.page-header :title="$student->user?->fullName() ?: 'Student Registration'" subtitle="Review submitted registration details.">
            <x-ui.button variant="secondary" :href="route('admin.student-registrations.index')" wire:navigate>Back to List</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card>
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-base font-semibold">Personal Information</h2>
                    </div>
                    <dl class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                        <div><dt class="text-sm text-muted">First Name</dt><dd class="mt-1 font-medium">{{ $student->user?->first_name ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Middle Name</dt><dd class="mt-1 font-medium">{{ $student->user?->middle_name ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Last Name</dt><dd class="mt-1 font-medium">{{ $student->user?->last_name ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Suffix</dt><dd class="mt-1 font-medium">{{ $student->user?->suffix ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Sex / Gender</dt><dd class="mt-1 font-medium">{{ $student->sex ? ucfirst($student->sex) : '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Date of Birth</dt><dd class="mt-1 font-medium">{{ $student->date_of_birth?->format('M j, Y') ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Contact Number</dt><dd class="mt-1 font-medium">{{ $student->phone ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Email</dt><dd class="mt-1 font-medium">{{ $student->user?->email }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-sm text-muted">Home Address</dt><dd class="mt-1 font-medium">{{ $student->home_address ?: '—' }}</dd></div>
                    </dl>
                </x-ui.card>

                <x-ui.card>
                    <div class="border-b border-line px-5 py-4">
                        <h2 class="text-base font-semibold">Academic Information</h2>
                    </div>
                    <dl class="grid gap-4 px-5 py-5 sm:grid-cols-2">
                        <div><dt class="text-sm text-muted">Student ID</dt><dd class="mt-1 font-medium">{{ $student->student_id }}</dd></div>
                        <div><dt class="text-sm text-muted">Department</dt><dd class="mt-1 font-medium">{{ $student->program?->department?->name ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Program</dt><dd class="mt-1 font-medium">{{ $student->program?->name ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Year Level</dt><dd class="mt-1 font-medium">{{ $student->yearLevel?->name ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Section</dt><dd class="mt-1 font-medium">{{ $student->section?->displayName() ?: '—' }}</dd></div>
                        <div><dt class="text-sm text-muted">Registration Date</dt><dd class="mt-1 font-medium">{{ $student->registered_at?->format('M j, Y g:i A') ?: '—' }}</dd></div>
                    </dl>
                </x-ui.card>
            </div>

            <div class="space-y-6">
                <x-ui.card class="ui-card-pad">
                    <h2 class="text-base font-semibold">Registration Status</h2>
                    <div class="mt-4">
                        <x-ui.badge :status="$student->registration_status->value" />
                    </div>

                    @if ($student->registration_status === \App\Enums\StudentRegistrationStatus::Approved)
                        <p class="mt-4 text-sm text-muted">Approved on {{ $student->approved_at?->format('M j, Y g:i A') }} by {{ $student->approver?->fullName() ?: 'Administrator' }}.</p>
                    @endif

                    @if ($student->registration_status === \App\Enums\StudentRegistrationStatus::Rejected && $student->rejection_reason)
                        <p class="mt-4 text-sm leading-6 text-muted">{{ $student->rejection_reason }}</p>
                    @endif

                    @if ($student->registration_status === \App\Enums\StudentRegistrationStatus::Pending)
                        <div class="mt-6 flex flex-col gap-2">
                            <x-ui.button type="button" @click="approveOpen = true">Approve</x-ui.button>
                            <x-ui.button type="button" variant="danger" @click="rejectOpen = true">Reject</x-ui.button>
                        </div>
                    @endif
                </x-ui.card>
            </div>
        </div>

        <div x-show="approveOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" @keydown.escape.window="approveOpen = false">
            <div class="ui-card ui-card-pad w-full max-w-md" @click.outside="approveOpen = false">
                <h2 class="text-lg font-semibold text-ink">Approve Student Registration?</h2>
                <p class="mt-2 text-sm leading-6 text-muted">The student will be granted access to their account and authorized student features.</p>
                <div class="mt-6 flex justify-end gap-2">
                    <x-ui.button variant="secondary" type="button" @click="approveOpen = false">Cancel</x-ui.button>
                    <form method="post" action="{{ route('admin.student-registrations.approve', $student) }}">
                        @csrf
                        <x-ui.button type="submit">Approve Student</x-ui.button>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4" @keydown.escape.window="rejectOpen = false">
            <div class="ui-card ui-card-pad w-full max-w-md" @click.outside="rejectOpen = false">
                <h2 class="text-lg font-semibold text-ink">Reject Student Registration?</h2>
                <p class="mt-2 text-sm leading-6 text-muted">The student will not be able to sign in. You may optionally provide a reason.</p>
                <form method="post" action="{{ route('admin.student-registrations.reject', $student) }}" class="mt-4 space-y-4">
                    @csrf
                    <x-ui.field label="Reason (optional)" for="rejection_reason">
                        <textarea id="rejection_reason" name="rejection_reason" rows="3" class="ui-input" placeholder="Your registration requires additional information. Please contact the administrator."></textarea>
                    </x-ui.field>
                    <div class="flex justify-end gap-2">
                        <x-ui.button variant="secondary" type="button" @click="rejectOpen = false">Cancel</x-ui.button>
                        <x-ui.button variant="danger" type="submit">Reject Registration</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
