<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Add Instructor" subtitle="Create a faculty account and assign a department." />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('instructors.store') }}" class="space-y-8">
                @csrf
                @include('pages.instructors._form', ['departments' => $departments])

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Instructor</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('instructors.index')" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
