<x-app-layout>
    <div class="ui-page" x-data="{
        addIds: [],
        removeIds: [],
        toggle(list, id) {
            const idx = list.indexOf(id);
            if (idx >= 0) list.splice(idx, 1);
            else list.push(id);
        }
    }">
        <x-ui.page-header title="Request Subject Change" subtitle="Submit a request to add or remove subjects. Changes require administrator approval.">
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
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-line px-4 py-3">
                                <input type="checkbox" name="remove_subject_ids[]" value="{{ $enrollment['subject']->id }}" class="h-5 w-5 rounded border-line">
                                <span>
                                    <span class="font-medium">{{ $enrollment['subject']->code }} — {{ $enrollment['subject']->name }}</span>
                                    <span class="block text-sm text-muted">Mark to request removal</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            <x-ui.card class="ui-card-pad">
                <h2 class="text-base font-semibold">Add Subjects</h2>
                <p class="mt-2 text-sm text-muted">Select subjects you are officially enrolled in but are not yet listed above.</p>
                <div class="mt-4 max-h-80 space-y-2 overflow-y-auto">
                    @foreach ($availableSubjects as $subject)
                        @if (! $currentEnrollments->contains(fn ($e) => (int) $e['subject']->id === (int) $subject->id))
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-line px-4 py-3">
                                <input type="checkbox" name="add_subject_ids[]" value="{{ $subject->id }}" class="h-5 w-5 rounded border-line">
                                <span class="font-medium">{{ $subject->code }} — {{ $subject->name }}</span>
                            </label>
                        @endif
                    @endforeach
                </div>
            </x-ui.card>

            <x-ui.card class="ui-card-pad">
                <x-ui.field label="Reason (optional)" for="reason">
                    <textarea id="reason" name="reason" rows="3" class="ui-input" placeholder="Explain why you need this subject change...">{{ old('reason') }}</textarea>
                </x-ui.field>
                @error('subject_ids')<p class="ui-error">{{ $message }}</p>@enderror
                @error('request')<p class="ui-error">{{ $message }}</p>@enderror
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button type="submit">Submit Request</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
