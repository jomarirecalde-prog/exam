<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Edit Subject" :subtitle="$subject->code . ' — ' . $subject->name" />

        <x-ui.card class="max-w-3xl">
            <form method="post" action="{{ route('subjects.update', $subject) }}" class="space-y-8">
                @csrf
                @method('PUT')
                @include('pages.subjects._form', ['subject' => $subject])

                <div class="flex flex-wrap gap-2 border-t border-line pt-6">
                    <x-primary-button>Save Changes</x-primary-button>
                    <x-ui.button variant="secondary" :href="route('subjects.show', $subject)" wire:navigate>Cancel</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
