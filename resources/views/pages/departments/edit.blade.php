<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Edit Department" :subtitle="$department->name" />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('departments.update', $department) }}" class="space-y-8">
                @csrf
                @method('PUT')
                @include('pages.departments._form', ['department' => $department])

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Changes</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('departments.show', $department)" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
