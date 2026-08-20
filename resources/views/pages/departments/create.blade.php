<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Create Department" subtitle="Add an academic department to organize programs and faculty." />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('departments.store') }}" class="space-y-8">
                @csrf
                @include('pages.departments._form')

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Department</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('departments.index')" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
