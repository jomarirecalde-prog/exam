<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Edit Program" :subtitle="$program->code . ' — ' . $program->name" />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('programs.update', $program) }}" class="space-y-8">
                @csrf
                @method('PUT')
                @include('pages.programs._form', ['program' => $program, 'departments' => $departments])

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Changes</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('programs.show', $program)" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
