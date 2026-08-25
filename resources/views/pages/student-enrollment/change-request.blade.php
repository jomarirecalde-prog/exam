<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Request Subject Change" subtitle="Submit a request to add or remove subject offerings. Changes require administrator approval.">
            <x-ui.button variant="secondary" :href="route('student.enrollment.index')" wire:navigate>Cancel</x-ui.button>
        </x-ui.page-header>

        <form method="post" action="{{ route('student.enrollment.change-request.store') }}" class="mt-6 space-y-6">
            @csrf

            <x-ui.card class="ui-card-pad">
                <h2 class="text-base font-semibold">Current Enrolled Subjects</h2>
                @if ($currentEnrollments->isEmpty())
                    <p class="mt-3 text-sm text-muted">You have no current subject enrollments.</p>
                @else
                    <div class="mt-4 space-y-2">
                        @foreach ($currentEnrollments as $enrollment)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line px-4 py-3">
                                <input type="checkbox" name="remove_subject_offering_ids[]" value="{{ $enrollment['offering']?->id }}" class="mt-1 h-5 w-5 shrink-0 rounded border-line">
                                <span>
                                    <span class="font-medium">{{ $enrollment['subject']->code }} — {{ $enrollment['subject']->name }}</span>
                                    <span class="mt-1 block text-sm text-muted">
                                        Instructor: {{ $enrollment['offering']?->instructorDisplayName() ?: 'To Be Announced' }}
                                        · Section: {{ $enrollment['offering']?->sectionDisplayName() ?: '—' }}
                                    </span>
                                    <span class="block text-sm text-muted">Mark to request removal</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card class="ui-card-pad">
                <h2 class="text-base font-semibold">Add Subject Offerings</h2>
                <p class="mt-2 text-sm text-muted">Select the specific class offering you are officially enrolled in.</p>
                <div class="mt-4 max-h-80 space-y-2 overflow-y-auto">
                    @forelse ($availableOfferings as $offering)
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-line px-4 py-3">
                            <input type="checkbox" name="add_subject_offering_ids[]" value="{{ $offering['id'] }}" class="mt-1 h-5 w-5 shrink-0 rounded border-line">
                            <span>
                                <span class="font-medium">{{ $offering['code'] }} — {{ $offering['name'] }}</span>
                                <span class="mt-1 block text-sm text-muted">
                                    Instructor: {{ $offering['instructor_name'] }} · Section: {{ $offering['section_name'] }}
                                </span>
                            </span>
                        </label>
                    @empty
                        <p class="text-sm text-muted">No additional subject offerings are available to add.</p>
                    @endforelse
                </div>
            </x-ui.card>

            <x-ui.card class="ui-card-pad">
                <x-ui.field label="Reason (optional)" for="reason">
                    <textarea id="reason" name="reason" rows="3" class="ui-input" placeholder="Explain why you need this subject change...">{{ old('reason') }}</textarea>
                </x-ui.field>
                @error('subject_offering_ids')<p class="ui-error">{{ $message }}</p>@enderror
                @error('request')<p class="ui-error">{{ $message }}</p>@enderror
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button type="submit">Submit Request</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
