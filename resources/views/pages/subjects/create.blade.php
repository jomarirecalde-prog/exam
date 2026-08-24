<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Create Subject" subtitle="Add a course that examinations and question banks can use." />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('subjects.store') }}" class="space-y-8">
                @csrf
                @include('pages.subjects._form')

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Subject</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('subjects.index')" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
