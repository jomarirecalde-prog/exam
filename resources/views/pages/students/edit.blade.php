<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Edit Student" :subtitle="$student->user?->fullName() ?: $student->student_id" />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('students.update', $student) }}" class="space-y-8">
                @csrf
                @method('PUT')
                @include('pages.students._form', [
                    'student' => $student,
                    'departments' => $departments,
                    'programs' => $programs,
                    'yearLevels' => $yearLevels,
                    'sections' => $sections,
                ])

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Changes</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('students.show', $student)" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
