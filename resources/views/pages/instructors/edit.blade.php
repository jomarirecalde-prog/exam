<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Edit Instructor" :subtitle="$instructor->user?->fullName() ?: $instructor->employee_id" />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('instructors.update', $instructor) }}" class="space-y-8">
                @csrf
                @method('PUT')
                @include('pages.instructors._form', ['instructor' => $instructor, 'departments' => $departments])

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Changes</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('instructors.show', $instructor)" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
