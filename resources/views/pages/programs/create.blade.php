<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Create Program" subtitle="Add a degree program under a department." />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('programs.store') }}" class="space-y-8">
                @csrf
                @include('pages.programs._form', ['departments' => $departments])

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Program</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('programs.index')" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
