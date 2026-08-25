<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Subject Change Request" subtitle="Review requested enrollment changes.">
            <x-ui.button variant="secondary" :href="route('admin.student-subject-requests.index')" wire:navigate>Back</x-ui.button>
        </x-ui.page-header>

        @if (session('status'))
            <x-ui.alert class="mt-4">{{ session('status') }}</x-ui.alert>
        @endif

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-ui.card class="ui-card-pad">
                    <h2 class="text-base font-semibold">Student</h2>
                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-muted">Name</dt><dd class="mt-1 font-medium">{{ $changeRequest->student?->user?->fullName() }}</dd></div>
                        <div><dt class="text-muted">Student ID</dt><dd class="mt-1 font-medium">{{ $changeRequest->student?->student_id }}</dd></div>
                        <div><dt class="text-muted">Program</dt><dd class="mt-1">{{ $changeRequest->student?->program?->name ?: '—' }}</dd></div>
                        <div><dt class="text-muted">Section</dt><dd class="mt-1">{{ $changeRequest->student?->section?->displayName() ?: '—' }}</dd></div>
                        <div><dt class="text-muted">Term</dt><dd class="mt-1">{{ $changeRequest->academicYear?->name }} · {{ $changeRequest->semester?->name }}</dd></div>
                    </dl>
                </x-ui.card>

                <x-ui.card class="ui-card-pad">
                    <h2 class="text-base font-semibold">Requested Changes</h2>
                    @if (! empty($changeRequest->add_subject_offering_ids))
                        <h3 class="mt-4 text-sm font-semibold text-muted">Add Subject Offerings</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($changeRequest->add_subject_offering_ids as $offeringId)
                                @php $offering = $offerings->get($offeringId); @endphp
                                <li>
                                    + {{ $offering?->subject?->code }} — {{ $offering?->subject?->name ?: 'Unknown subject' }}
                                    <span class="block text-muted">
                                        {{ $offering?->instructorDisplayName() }} · {{ $offering?->sectionDisplayName() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($changeRequest->remove_subject_offering_ids))
                        <h3 class="mt-4 text-sm font-semibold text-muted">Remove Subject Offerings</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($changeRequest->remove_subject_offering_ids as $offeringId)
                                @php $offering = $offerings->get($offeringId); @endphp
                                <li>
                                    − {{ $offering?->subject?->code }} — {{ $offering?->subject?->name ?: 'Unknown subject' }}
                                    <span class="block text-muted">
                                        {{ $offering?->instructorDisplayName() }} · {{ $offering?->sectionDisplayName() }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($changeRequest->reason)
                        <p class="mt-4 text-sm"><span class="font-medium">Student reason:</span> {{ $changeRequest->reason }}</p>
                    @endif
                </x-ui.card>
            </div>

            <x-ui.card class="ui-card-pad h-fit">
                <h2 class="text-base font-semibold">Status</h2>
                <div class="mt-3"><x-ui.badge :status="$changeRequest->status->value" /></div>

                @if ($changeRequest->status === \App\Enums\StudentSubjectChangeRequestStatus::Pending)
                    <form method="post" action="{{ route('admin.student-subject-requests.approve', $changeRequest) }}" class="mt-6 space-y-3">
                        @csrf
                        <x-ui.field label="Admin notes (optional)" for="admin_notes">
                            <textarea id="admin_notes" name="admin_notes" rows="2" class="ui-input"></textarea>
                        </x-ui.field>
                        <x-ui.button type="submit" class="w-full">Approve Changes</x-ui.button>
                    </form>
                    <form method="post" action="{{ route('admin.student-subject-requests.reject', $changeRequest) }}" class="mt-3 space-y-3">
                        @csrf
                        <x-ui.button type="submit" variant="danger" class="w-full">Reject Request</x-ui.button>
                    </form>
                @else
                    <p class="mt-4 text-sm text-muted">
                        Reviewed on {{ $changeRequest->reviewed_at?->format('M j, Y g:i A') ?: '—' }}
                        by {{ $changeRequest->reviewer?->fullName() ?: 'Administrator' }}.
                    </p>
                    @if ($changeRequest->admin_notes)
                        <p class="mt-2 text-sm text-muted">{{ $changeRequest->admin_notes }}</p>
                    @endif
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
