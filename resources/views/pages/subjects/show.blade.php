<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$subject->name" :subtitle="$subject->code">
            <x-ui.button variant="secondary" :href="route('subjects.edit', $subject)" icon="pencil" wire:navigate>Edit</x-ui.button>
        </x-ui.page-header>

        <x-ui.card class="max-w-2xl">
            <h2 class="ui-section">Subject</h2>
            <dl class="mt-4 space-y-4 text-sm">
                <div>
                    <dt class="text-muted">Code</dt>
                    <dd class="mt-1 font-medium">{{ $subject->code }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Name</dt>
                    <dd class="mt-1 font-medium">{{ $subject->name }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Department</dt>
                    <dd class="mt-1 font-medium">{{ $subject->department?->name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Units</dt>
                    <dd class="mt-1 font-medium">{{ $subject->units }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Description</dt>
                    <dd class="mt-1 leading-6">{{ $subject->description ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">Status</dt>
                    <dd class="mt-1"><x-ui.badge :status="$subject->is_active ? 'active' : 'closed'" /></dd>
                </div>
            </dl>
        </x-ui.card>
    </div>
</x-app-layout>
