<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header :title="$instructor->user?->fullName() ?: $instructor->employee_id" subtitle="Instructor profile">
            <x-ui.button variant="secondary" :href="route('instructors.edit', $instructor)" icon="pencil" wire:navigate>Edit</x-ui.button>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card>
                <h2 class="ui-section">Account</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Name</dt>
                        <dd class="mt-1 font-medium">{{ $instructor->user?->fullName() ?: $instructor->user?->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Email</dt>
                        <dd class="mt-1">{{ $instructor->user?->email }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Employee ID</dt>
                        <dd class="mt-1 font-medium">{{ $instructor->employee_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Status</dt>
                        <dd class="mt-1"><x-ui.badge :status="$instructor->is_active ? 'active' : 'closed'" /></dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="ui-section">Assignment</h2>
                <dl class="mt-4 space-y-4 text-sm">
                    <div>
                        <dt class="text-muted">Department</dt>
                        <dd class="mt-1 font-medium">{{ $instructor->department?->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Username</dt>
                        <dd class="mt-1">{{ $instructor->user?->username ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
