<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Create Section" subtitle="Group students for scheduling and examinations." />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('sections.store') }}" class="space-y-8">
                @csrf
                @include('pages.sections._form')

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Section</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('sections.index')" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
