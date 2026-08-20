<x-app-layout>
    <div class="ui-page">
        <x-ui.page-header title="Settings" subtitle="Institution and platform preferences." />

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card>
                <h2 class="ui-section">Institution</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-muted">Name</dt>
                        <dd class="mt-1 font-medium">{{ $institution['name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Address</dt>
                        <dd class="mt-1">{{ $institution['address'] ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted">Contact</dt>
                        <dd class="mt-1">{{ $institution['contact'] ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
            <x-ui.card>
                <h2 class="ui-section">Platform</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-muted">Mode</dt>
                        <dd class="mt-1"><x-ui.badge :status="$appMode === 'offline' ? 'offline' : 'active'">{{ $appMode === 'offline' ? 'Offline' : 'Online' }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="text-muted">Timezone</dt>
                        <dd class="mt-1 font-medium">{{ $timezone }}</dd>
                    </div>
                </dl>
                <a href="{{ route('profile') }}" class="btn-secondary mt-6 inline-flex" wire:navigate>Profile & password</a>
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
